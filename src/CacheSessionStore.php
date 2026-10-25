<?php

declare(strict_types=1);

namespace Zana\Session;

use Psr\SimpleCache\CacheInterface;

/**
 * Sessions in the application's PSR-16 cache. Use a cache shared by every
 * web server (files on one server, Redis or a database otherwise).
 */
final readonly class CacheSessionStore implements SessionStoreInterface
{
    private const string PREFIX = 'zana.session.';

    public function __construct(private CacheInterface $cache)
    {
    }

    public function read(string $id): ?array
    {
        $data = $this->cache->get(self::PREFIX . $id);
        if (!is_array($data)) {
            return null;
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    public function write(string $id, array $data, int $lifetime): void
    {
        $this->cache->set(self::PREFIX . $id, $data, $lifetime);
    }

    public function destroy(string $id): void
    {
        $this->cache->delete(self::PREFIX . $id);
    }
}
