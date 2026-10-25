<?php

declare(strict_types=1);

namespace Zana\Session;

/**
 * Where session data lives between requests.
 */
interface SessionStoreInterface
{
    /**
     * @return array<string, mixed>|null null when the session does not exist or expired
     */
    public function read(string $id): ?array;

    /**
     * @param array<string, mixed> $data
     */
    public function write(string $id, array $data, int $lifetime): void;

    public function destroy(string $id): void;
}
