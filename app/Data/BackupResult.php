<?php

namespace App\Data;

class BackupResult
{
    private function __construct(
        public readonly string $status,
        public readonly ?string $filename = null,
        public readonly ?string $path = null,
        public readonly ?string $sha256 = null,
        public readonly ?string $completedAt = null,
        public readonly ?string $error = null,
    ) {}

    public static function successful(string $filename, ?string $path = null, ?string $sha256 = null, ?string $completedAt = null): self
    {
        return new self('success', $filename, $path, $sha256, $completedAt);
    }

    public static function failed(?string $error = null): self
    {
        return new self('failed', error: $error);
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }
}
