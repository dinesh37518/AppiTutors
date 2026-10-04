<?php

declare(strict_types=1);

namespace App\Services\Email;

/**
 * Class EmailResult
 *
 * Immutable value object representing the provider-agnostic dispatch outcome.
 */
class EmailResult
{
    private bool $success;
    private ?string $messageId;
    private string $provider;
    private ?string $errorMessage;
    private string $timestamp;
    private array $metadata;

    public function __construct(
        bool $success,
        string $provider,
        ?string $messageId = null,
        ?string $errorMessage = null,
        array $metadata = [],
        ?string $timestamp = null
    ) {
        $this->success = $success;
        $this->provider = $provider;
        $this->messageId = $messageId;
        $this->errorMessage = $errorMessage;
        $this->metadata = $metadata;
        $this->timestamp = $timestamp ?? gmdate('Y-m-d\TH:i:s\Z');
    }

    public static function success(string $provider, ?string $messageId = null, array $metadata = []): self
    {
        return new self(
            success: true,
            provider: $provider,
            messageId: $messageId,
            errorMessage: null,
            metadata: $metadata
        );
    }

    public static function failure(string $provider, string $errorMessage, array $metadata = []): self
    {
        return new self(
            success: false,
            provider: $provider,
            messageId: null,
            errorMessage: $errorMessage,
            metadata: $metadata
        );
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getMessageId(): ?string
    {
        return $this->messageId;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getTimestamp(): string
    {
        return $this->timestamp;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'provider' => $this->provider,
            'message_id' => $this->messageId,
            'error_message' => $this->errorMessage,
            'timestamp' => $this->timestamp,
            'metadata' => $this->metadata,
        ];
    }
}
