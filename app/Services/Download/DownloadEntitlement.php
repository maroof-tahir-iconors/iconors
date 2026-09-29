<?php

namespace App\Services\Download;

/**
 * Result of an entitlement check.
 *
 * A value object rather than a bare bool so the caller always has to
 * handle the reason. Returning false alone tends to produce a generic
 * 403 that tells a paying customer nothing about why their download
 * failed — and tells support even less.
 */
final class DownloadEntitlement
{
    private function __construct(
        public readonly bool $allowed,
        public readonly string $accessType,
        public readonly string $reason,
    ) {}

    public static function allow(string $accessType): self
    {
        return new self(true, $accessType, '');
    }

    public static function deny(string $reason): self
    {
        return new self(false, 'none', $reason);
    }
}
