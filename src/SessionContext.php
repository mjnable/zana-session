<?php

declare(strict_types=1);

namespace Zana\Session;

/**
 * The session of the current request (scoped), set by SessionMiddleware.
 */
final class SessionContext
{
    private ?Session $session = null;

    public function set(Session $session): void
    {
        $this->session = $session;
    }

    public function has(): bool
    {
        return $this->session !== null;
    }

    /**
     * @throws \LogicException When SessionMiddleware did not run for this request
     */
    public function session(): Session
    {
        return $this->session ?? throw new \LogicException('No session: add ' . SessionMiddleware::class . ' to http.middleware, or to the routes that need it.');
    }
}
