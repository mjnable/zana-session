<?php

declare(strict_types=1);

namespace Zana\Session;

/**
 * The session of one request. SessionMiddleware loads it and saves it after
 * the response; it is only written, and its cookie only sent, when changed.
 */
final class Session implements SessionInterface
{
    private const string FLASH = '_flash';

    /** @var array<string, mixed> */
    private array $data;

    /** @var array<string, mixed> Flashed by the previous request */
    private array $flashed;

    private bool $changed = false;

    private ?string $previousId = null;

    private bool $invalidated = false;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(private ?string $id = null, array $data = [])
    {
        $flashed = $data[self::FLASH] ?? [];
        unset($data[self::FLASH]);
        /** @var array<string, mixed> $flashed */
        $flashed = is_array($flashed) ? $flashed : [];
        $this->flashed = $flashed;
        $this->data = $data;
        // Flash messages last one request: reading them changes the stored session.
        $this->changed = $this->flashed !== [];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->data) ? $this->data[$key] : $default;
    }

    public function set(string $key, mixed $value): void
    {
        self::assertKey($key);
        $this->data[$key] = $value;
        $this->changed = true;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function remove(string $key): void
    {
        if (array_key_exists($key, $this->data)) {
            unset($this->data[$key]);
            $this->changed = true;
        }
    }

    public function all(): array
    {
        return array_filter($this->data, static fn (string $key): bool => $key !== self::FLASH, ARRAY_FILTER_USE_KEY);
    }

    public function flash(string $key, mixed $value): void
    {
        $next = $this->data[self::FLASH] ?? [];
        $next = is_array($next) ? $next : [];
        $next[$key] = $value;
        $this->data[self::FLASH] = $next;
        $this->changed = true;
    }

    public function getFlash(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->flashed) ? $this->flashed[$key] : $default;
    }

    public function regenerate(): void
    {
        $this->previousId ??= $this->id;
        $this->id = null;
        $this->changed = true;
    }

    public function invalidate(): void
    {
        $this->previousId ??= $this->id;
        $this->id = null;
        $this->data = [];
        $this->flashed = [];
        $this->invalidated = true;
        $this->changed = false;
    }

    public function id(): ?string
    {
        return $this->id;
    }

    /**
     * @internal Used by SessionMiddleware.
     * @return array{changed: bool, invalidated: bool, previousId: ?string, data: array<string, mixed>}
     */
    public function state(): array
    {
        return ['changed' => $this->changed, 'invalidated' => $this->invalidated, 'previousId' => $this->previousId, 'data' => $this->data];
    }

    /**
     * @internal Used by SessionMiddleware when it first writes the session.
     */
    public function assignId(string $id): void
    {
        $this->id = $id;
    }

    private static function assertKey(string $key): void
    {
        if ($key === self::FLASH) {
            throw new \InvalidArgumentException('"' . self::FLASH . '" is reserved for flash messages; use flash().');
        }
    }
}
