<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\UserContext;
use App\Authorization\Authorization;
use App\Authorization\ForbiddenException;
use App\Database\Database;
use App\Logging\Logger;
use App\Services\Exceptions\ValidationException;
use App\Support\Timezone;
use PDO;
use Throwable;

class DbsService
{
    public const STATUS_NOT_SUBMITTED = 'NOT_SUBMITTED';
    public const STATUS_SUBMITTED = 'SUBMITTED';
    public const STATUS_VERIFIED = 'VERIFIED';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_EXPIRED = 'EXPIRED';

    public const ALLOWED_STATUSES = [
        self::STATUS_NOT_SUBMITTED,
        self::STATUS_SUBMITTED,
        self::STATUS_VERIFIED,
        self::STATUS_REJECTED,
        self::STATUS_EXPIRED,
    ];

    public const MAX_FILE_SIZE = 5242880; // 5 MB

    public const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/png',
        'image/jpeg',
    ];

    public const ALLOWED_EXTENSIONS = [
        'pdf',
        'png',
        'jpg',
        'jpeg',
    ];

    private PDO $pdo;
    private Logger $logger;
    private AuditService $audit;
    private string $storageDir;

    public function __construct(?PDO $pdo = null, ?Logger $logger = null, ?AuditService $audit = null, ?string $storageDir = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
        $this->logger = $logger ?? new Logger();
        $this->audit = $audit ?? new AuditService($this->pdo, $this->logger);
        $defaultStorage = is_dir(dirname(__DIR__, 3) . '/storage/private/dbs')
            ? dirname(__DIR__, 3) . '/storage/private/dbs'
            : (is_dir(dirname(__DIR__, 2) . '/storage/private/dbs') ? dirname(__DIR__, 2) . '/storage/private/dbs' : dirname(__DIR__, 3) . '/storage/private/dbs');
        $this->storageDir = $storageDir ?? $defaultStorage;

        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0750, true);
        }
    }

    /**
     * Submit DBS certificate information and optional verification document.
     *
     * @param int $tutorUserId
     * @param array $metadata E.g. ['certificate_number' => '...', 'issue_date' => '...']
     * @param array|null $file $_FILES['dbs_document'] structure
     * @param UserContext $currentUser
     * @return array
     * @throws ForbiddenException
     * @throws ValidationException
     */
    public function submitDbs(int $tutorUserId, array|string $metadata, ?array $file, UserContext $currentUser): array
    {
        if (is_string($metadata)) {
            $metadata = ['certificate_number' => $metadata];
        }

        // 1. Authorization: Only TUTOR or MANAGER can submit DBS; Tutor can only submit own DBS
        Authorization::requireRole($currentUser, [Authorization::ROLE_TUTOR, Authorization::ROLE_MANAGER]);
        if (!$currentUser->isManager()) {
            Authorization::assertOwnership($tutorUserId, $currentUser->id, 'You cannot submit DBS documentation for another tutor.');
        }

        // 2. Inactive account enforcement
        if ($currentUser->status === 'SUSPENDED' || $currentUser->status === 'DELETED') {
            throw new ForbiddenException("Account is {$currentUser->status}. Action denied.", 'ACCOUNT_NOT_ACTIVE');
        }

        // 3. Verify tutor profile exists
        $stmt = $this->pdo->prepare('SELECT user_id, dbs_status, approval_status FROM `tutor_profiles` WHERE `user_id` = ? LIMIT 1');
        $stmt->execute([$tutorUserId]);
        $profile = $stmt->fetch();
        if (!$profile) {
            throw new ValidationException('Tutor profile not found.', 'TUTOR_NOT_FOUND', 404);
        }

        // 4. Validate and process file upload if provided
        $storedFilename = null;
        $originalFilename = null;
        $mimeType = null;
        $fileSize = 0;

        if ($file !== null && !empty($file['tmp_name'])) {
            $validation = $this->validateUploadedFile($file);
            $storedFilename = $validation['stored_name'];
            $originalFilename = $validation['original_name'];
            $mimeType = $validation['mime_type'];
            $fileSize = $validation['size'];
        }

        // 5. Update tutor profile dbs_status
        $stmtUpdate = $this->pdo->prepare('
            UPDATE `tutor_profiles` 
            SET `dbs_status` = "SUBMITTED", `updated_at` = UTC_TIMESTAMP() 
            WHERE `user_id` = ?
        ');
        $stmtUpdate->execute([$tutorUserId]);

        // 6. Record metadata in secure storage record file (outside web root)
        $docRecord = [
            'tutor_user_id' => $tutorUserId,
            'certificate_number' => !empty($metadata['certificate_number']) ? trim((string) $metadata['certificate_number']) : null,
            'issue_date' => !empty($metadata['issue_date']) ? trim((string) $metadata['issue_date']) : null,
            'stored_filename' => $storedFilename,
            'original_filename' => $originalFilename,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'submitted_at_utc' => Timezone::nowUtc(),
            'submitted_by_user_id' => $currentUser->id,
            'retention_policy_status' => 'CLIENT_DECISION_OPEN',
        ];

        file_put_contents(
            $this->storageDir . "/meta_{$tutorUserId}.json",
            json_encode($docRecord, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        // 7. Audit log (sanitized: no sensitive certificate or file contents)
        $this->audit->log(
            action: 'TUTOR_DBS_SUBMITTED',
            entityType: 'tutor_profile',
            entityId: $tutorUserId,
            actorUserId: $currentUser->id,
            metadata: [
                'has_document_upload' => ($storedFilename !== null),
                'file_mime' => $mimeType,
                'file_size' => $fileSize,
                'retention_policy' => 'OPEN_CLIENT_DECISION',
            ]
        );

        $this->logger->info("DBS submitted for Tutor ID {$tutorUserId} by User ID {$currentUser->id}");

        return [
            'tutor_user_id' => $tutorUserId,
            'dbs_status' => self::STATUS_SUBMITTED,
            'dbs_certificate_number' => $docRecord['certificate_number'],
            'has_document' => ($storedFilename !== null),
            'submitted_at' => $docRecord['submitted_at_utc'],
            'retention_note' => 'Physical evidence retention period is an open client decision (DISC-020).',
        ];
    }

    /**
     * Retrieve stored DBS document for authorized viewing / downloading.
     *
     * @param int $tutorUserId
     * @param UserContext $currentUser
     * @return array ['path' => string, 'mime_type' => string, 'original_name' => string]
     * @throws ForbiddenException
     * @throws ValidationException
     */
    public function getDbsDocument(int $tutorUserId, UserContext $currentUser): array
    {
        // Enforce authorization: Sensitive raw DBS document retrieval is restricted to authorized MANAGER only
        Authorization::requireRole($currentUser, [Authorization::ROLE_MANAGER], 'Access denied. Only authorized managers can retrieve raw DBS documents.');
        Authorization::requireActiveStatus($currentUser);

        $metaFile = $this->storageDir . "/meta_{$tutorUserId}.json";
        if (!file_exists($metaFile)) {
            throw new ValidationException('No DBS document found for this tutor.', 'DOCUMENT_NOT_FOUND', 404);
        }

        $meta = json_decode((string) file_get_contents($metaFile), true);
        if (empty($meta['stored_filename'])) {
            throw new ValidationException('No file attachment uploaded with DBS submission.', 'FILE_NOT_ATTACHED', 404);
        }

        $filePath = $this->storageDir . '/' . basename($meta['stored_filename']);
        if (!file_exists($filePath)) {
            throw new ValidationException('Stored document file missing from private storage.', 'FILE_MISSING', 404);
        }

        return [
            'path' => $filePath,
            'mime_type' => $meta['mime_type'] ?? 'application/octet-stream',
            'original_name' => $meta['original_filename'] ?? 'dbs_document.bin',
        ];
    }

    /**
     * Manager decision on DBS submission (VERIFY or REJECT).
     *
     * @param int $tutorUserId
     * @param string $decision 'VERIFY' | 'REJECT'
     * @param string|null $notes
     * @param UserContext $manager
     * @return array
     * @throws ForbiddenException
     * @throws ValidationException
     */
    public function managerReviewDbs(int $tutorUserId, string $decision, ?string $notes, UserContext $manager): array
    {
        Authorization::requireRole($manager, [Authorization::ROLE_MANAGER]);
        Authorization::requireActiveStatus($manager);

        if (!in_array(strtoupper($decision), ['VERIFY', 'REJECT'], true)) {
            throw new ValidationException('Invalid DBS review decision. Expected VERIFY or REJECT.', 'INVALID_DECISION', 422);
        }

        $newDbsStatus = strtoupper($decision) === 'VERIFY' ? self::STATUS_VERIFIED : self::STATUS_REJECTED;

        $stmt = $this->pdo->prepare('SELECT user_id, dbs_status, approval_status FROM `tutor_profiles` WHERE `user_id` = ? LIMIT 1');
        $stmt->execute([$tutorUserId]);
        $profile = $stmt->fetch();
        if (!$profile) {
            throw new ValidationException('Tutor profile not found.', 'TUTOR_NOT_FOUND', 404);
        }

        $stmtUpdate = $this->pdo->prepare('
            UPDATE `tutor_profiles` 
            SET `dbs_status` = ?, `updated_at` = UTC_TIMESTAMP() 
            WHERE `user_id` = ?
        ');
        $stmtUpdate->execute([$newDbsStatus, $tutorUserId]);

        $actionName = ($newDbsStatus === self::STATUS_VERIFIED) ? 'MANAGER_VERIFY_DBS' : 'MANAGER_REJECT_DBS';

        $this->audit->log(
            action: $actionName,
            entityType: 'tutor_profile',
            entityId: $tutorUserId,
            actorUserId: $manager->id,
            metadata: [
                'decision' => $decision,
                'new_dbs_status' => $newDbsStatus,
                'previous_dbs_status' => $profile['dbs_status'],
                'notes_length' => $notes !== null ? mb_strlen($notes) : 0,
            ]
        );

        $this->logger->info("Manager ID {$manager->id} marked DBS for Tutor ID {$tutorUserId} as {$newDbsStatus}");

        return [
            'tutor_user_id' => $tutorUserId,
            'dbs_status' => $newDbsStatus,
            'decision' => $decision,
            'reviewed_by' => $manager->id,
            'reviewed_at' => Timezone::nowUtc(),
        ];
    }

    /**
     * Manager DBS verification convenience method.
     */
    public function verifyDbs(int $tutorUserId, UserContext $manager, ?string $notes = null): array
    {
        return $this->managerReviewDbs($tutorUserId, 'VERIFY', $notes, $manager);
    }

    /**
     * Manager DBS rejection convenience method.
     */
    public function rejectDbs(int $tutorUserId, ?string $reason, UserContext $manager): array
    {
        return $this->managerReviewDbs($tutorUserId, 'REJECT', $reason, $manager);
    }

    /**
     * Validate uploaded file type, size, extension, and content safety.
     *
     * @param array $file
     * @return array ['stored_name' => string, 'original_name' => string, 'mime_type' => string, 'size' => int]
     * @throws ValidationException
     */
    private function validateUploadedFile(array $file): array
    {
        if (!isset($file['error']) || is_array($file['error'])) {
            throw new ValidationException('Invalid file upload parameters.', 'INVALID_UPLOAD_PARAMS', 422);
        }

        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new ValidationException('File exceeds maximum upload size (5 MB).', 'FILE_TOO_LARGE', 422);
            case UPLOAD_ERR_NO_FILE:
                throw new ValidationException('No file was uploaded.', 'NO_FILE_UPLOADED', 422);
            default:
                throw new ValidationException('File upload error code: ' . $file['error'], 'UPLOAD_ERROR', 422);
        }

        $fileSize = (int) ($file['size'] ?? 0);
        if ($fileSize <= 0 || $fileSize > self::MAX_FILE_SIZE) {
            throw new ValidationException('File size must be between 1 byte and 5 MB.', 'FILE_TOO_LARGE', 422);
        }

        $originalName = (string) ($file['name'] ?? 'upload');
        // Prevent path traversal
        $originalName = basename($originalName);

        // Extension check
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new ValidationException('Invalid file extension. Allowed extensions: PDF, PNG, JPG, JPEG.', 'INVALID_FILE_EXTENSION', 422);
        }

        // Strict rejection of multiple extensions containing executable patterns (e.g. evil.php.jpg)
        if (preg_match('/\.(php|phtml|phar|exe|sh|bat|cmd|pl|py|cgi|js)(\.|$)/i', $originalName)) {
            throw new ValidationException('Executable or script files are strictly forbidden.', 'EXECUTABLE_UPLOAD_BLOCKED', 422);
        }

        $tmpPath = (string) $file['tmp_name'];
        if (!is_file($tmpPath) || !is_readable($tmpPath)) {
            throw new ValidationException('Uploaded temporary file is unreadable.', 'TMP_FILE_UNREADABLE', 422);
        }

        // Strict MIME check using finfo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo ? finfo_file($finfo, $tmpPath) : false;
        if ($finfo) {
            finfo_close($finfo);
        }

        if (!$detectedMime || !in_array($detectedMime, self::ALLOWED_MIME_TYPES, true)) {
            throw new ValidationException("Invalid file MIME type ({$detectedMime}). Allowed types: PDF, PNG, JPEG.", 'INVALID_MIME_TYPE', 422);
        }

        // Inspection for embedded PHP scripts / tags
        $sample = file_get_contents($tmpPath, false, null, 0, 4096);
        if ($sample !== false && (stripos($sample, '<?php') !== false || stripos($sample, '<?=') !== false || stripos($sample, '<script') !== false)) {
            throw new ValidationException('Script payload detected inside file body.', 'MALICIOUS_PAYLOAD_DETECTED', 422);
        }

        // Generate safe random UUID-style storage filename
        $storedFilename = bin2hex(random_bytes(16)) . '.' . $extension;
        $destination = $this->storageDir . '/' . $storedFilename;

        $moved = is_uploaded_file($tmpPath)
            ? move_uploaded_file($tmpPath, $destination)
            : copy($tmpPath, $destination);

        if (!$moved) {
            throw new ValidationException('Failed to store document in secure private storage.', 'STORAGE_WRITE_FAILED', 500);
        }

        // Enforce private read/write permissions
        chmod($destination, 0640);

        return [
            'stored_name' => $storedFilename,
            'original_name' => $originalName,
            'mime_type' => $detectedMime,
            'size' => $fileSize,
        ];
    }
}
