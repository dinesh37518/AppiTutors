<?php

declare(strict_types=1);

namespace App\Auth;

use App\Auth\Exceptions\AccountInactiveException;
use App\Auth\Exceptions\InvalidTokenException;
use App\Auth\Exceptions\UserNotRegisteredException;
use App\Database\Database;
use Kreait\Firebase\Contract\Auth as FirebaseAuth;
use Kreait\Firebase\Factory;
use PDO;
use Throwable;

class FirebaseTokenVerifier
{
    private ?FirebaseAuth $firebaseAuth;
    private PDO $pdo;

    public function __construct(?FirebaseAuth $firebaseAuth = null, ?PDO $pdo = null)
    {
        $this->firebaseAuth = $firebaseAuth;
        $this->pdo = $pdo ?? Database::getConnection();
    }

    /**
     * Lazy-load or return the FirebaseAuth instance.
     */
    public function getFirebaseAuth(): FirebaseAuth
    {
        if ($this->firebaseAuth === null) {
            $cfgFile = file_exists(dirname(__DIR__, 2) . '/config/firebase.php')
                ? dirname(__DIR__, 2) . '/config/firebase.php'
                : dirname(__DIR__, 3) . '/config/firebase.php';
            $config = require $cfgFile;
            $factory = new Factory();

            $credentialsPath = $config['credentials_path'] ?? '';
            $workspaceRoot = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 3);
            $fullCredsPath = str_starts_with($credentialsPath, '/') || preg_match('/^[a-zA-Z]:/', $credentialsPath)
                ? $credentialsPath
                : $workspaceRoot . '/' . ltrim($credentialsPath, '/\\');

            if (!empty($credentialsPath) && file_exists($fullCredsPath)) {
                $factory = $factory->withServiceAccount($fullCredsPath);
            }

            if (!empty($config['project_id'])) {
                $factory = $factory->withProjectId($config['project_id']);
            }

            $this->firebaseAuth = $factory->createAuth();
        }

        return $this->firebaseAuth;
    }

    /**
     * Extract Bearer token from Authorization header.
     *
     * @param string|null $header
     * @return string
     * @throws InvalidTokenException
     */
    public function extractBearerToken(?string $header = null): string
    {
        if ($header === null) {
            $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
            if (empty($header) && function_exists('apache_request_headers')) {
                $headers = apache_request_headers();
                $header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
            }
        }

        if (!preg_match('/^Bearer\s+(.+)$/i', trim($header), $matches)) {
            throw new InvalidTokenException('Missing or malformed Authorization header. Expected Bearer token.');
        }

        $token = trim($matches[1]);
        if (empty($token)) {
            throw new InvalidTokenException('Empty Bearer token provided.');
        }

        return $token;
    }

    /**
     * Cryptographically verify Firebase ID token using Firebase Admin SDK.
     *
     * @param string $tokenString
     * @return array Verified claims ['uid' => string, 'email' => ?string, 'email_verified' => bool]
     * @throws InvalidTokenException
     */
    public function verifyFirebaseToken(string $tokenString): array
    {
        try {
            $auth = $this->getFirebaseAuth();
            $verifiedToken = $auth->verifyIdToken($tokenString);

            $claims = $verifiedToken->claims();
            $uid = (string) $claims->get('sub');
            $email = $claims->get('email') ? (string) $claims->get('email') : null;
            $emailVerified = (bool) $claims->get('email_verified', false);

            if (empty($uid)) {
                throw new InvalidTokenException('Token missing subject (sub) claim.');
            }

            return [
                'uid' => $uid,
                'email' => $email,
                'email_verified' => $emailVerified,
            ];
        } catch (InvalidTokenException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new InvalidTokenException('Invalid, expired, or unverified Firebase ID token: ' . $e->getMessage(), $e);
        }
    }

    /**
     * Resolve application user record strictly from MySQL using trusted Firebase UID.
     *
     * @param string $firebaseUid
     * @param bool $emailVerified
     * @return UserContext
     * @throws UserNotRegisteredException
     */
    public function resolveUser(string $firebaseUid, bool $emailVerified = false): UserContext
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, firebase_uid, email, display_name, role, status FROM `users` WHERE `firebase_uid` = ? LIMIT 1'
        );
        $stmt->execute([$firebaseUid]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new UserNotRegisteredException($firebaseUid);
        }

        return UserContext::fromDatabaseRow($row, $emailVerified);
    }

    /**
     * End-to-end request authentication:
     * 1. Extracts Bearer token
     * 2. Verifies with Firebase Admin SDK
     * 3. Resolves MySQL user authority
     *
     * @param string|null $tokenString Optional override
     * @param bool $requireActive Default true
     * @return UserContext
     * @throws InvalidTokenException
     * @throws UserNotRegisteredException
     * @throws AccountInactiveException
     */
    public function authenticateRequest(?string $tokenString = null, bool $requireActive = true): UserContext
    {
        $token = $tokenString ?? $this->extractBearerToken();
        $claims = $this->verifyFirebaseToken($token);

        $user = $this->resolveUser($claims['uid'], $claims['email_verified']);

        if ($requireActive && !$user->isActive()) {
            throw new AccountInactiveException($user->status, "User account status is {$user->status}. Access denied.");
        }

        return $user;
    }
}
