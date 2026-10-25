<?php

declare(strict_types=1);

namespace Zana\Session;

interface SessionInterface
{
    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value): void;

    public function has(string $key): bool;

    public function remove(string $key): void;

    /**
     * @return array<string, mixed>
     */
    public function all(): array;

    /**
     * A value for the next request only, such as "Saved." after a redirect.
     */
    public function flash(string $key, mixed $value): void;

    /**
     * A value flashed by the previous request.
     */
    public function getFlash(string $key, mixed $default = null): mixed;

    /**
     * Give the session a new id, keeping its data. Call it after login, so an
     * id an attacker planted before is worthless (session fixation).
     */
    public function regenerate(): void;

    /**
     * Destroy the session and its data, e.g. at logout.
     */
    public function invalidate(): void;

    /**
     * The id, or null for a session never written.
     */
    public function id(): ?string;
}
