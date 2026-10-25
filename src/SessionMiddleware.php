<?php

declare(strict_types=1);

namespace Zana\Session;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zana\Http\BasePath;

/**
 * Loads the session from its cookie before the handler, and saves it after.
 *
 * - A session nobody wrote to is never stored and sends no cookie, so public
 *   pages stay cacheable (FE-33).
 * - The cookie is HttpOnly, SameSite=Lax, Secure on HTTPS requests, and
 *   scoped to the application's base path.
 * - Ids are 256 random bits; an unknown or malformed id starts a new session.
 * - The lifetime counts inactivity: each request stores the session again.
 */
final readonly class SessionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private SessionStoreInterface $store,
        private SessionContext $context,
        private string $cookie = 'zana_session',
        private int $lifetime = 7200,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $id = $request->getCookieParams()[$this->cookie] ?? self::cookieFromHeader($request->getHeaderLine('Cookie'), $this->cookie);
        $data = is_string($id) && preg_match('/^[0-9a-f]{64}$/', $id) === 1 ? $this->store->read($id) : null;

        $session = $data === null ? new Session() : new Session((string) $id, $data);
        $this->context->set($session);

        $response = $handler->handle($request);

        $state = $session->state();
        if ($state['previousId'] !== null) {
            $this->store->destroy($state['previousId']);
        }
        if ($state['invalidated'] && !$state['changed']) {
            return $response->withAddedHeader('Set-Cookie', $this->cookieHeader('', $request, expire: true));
        }
        if (!$state['changed']) {
            if ($session->id() !== null) {
                // Each visit restarts the inactivity lifetime.
                $this->store->write($session->id(), $state['data'], $this->lifetime);
            }

            return $response;
        }

        $sessionId = $session->id();
        $new = $sessionId === null;
        if ($new) {
            $sessionId = bin2hex(random_bytes(32));
            $session->assignId($sessionId);
        }
        $this->store->write($sessionId, $state['data'], $this->lifetime);

        return $new ? $response->withAddedHeader('Set-Cookie', $this->cookieHeader($sessionId, $request)) : $response;
    }

    private function cookieHeader(string $value, ServerRequestInterface $request, bool $expire = false): string
    {
        $base = $request->getAttribute(BasePath::ATTRIBUTE, '');
        $parts = [
            "{$this->cookie}={$value}",
            'Path=' . (is_string($base) && $base !== '' ? $base : '/'),
            'HttpOnly',
            'SameSite=Lax',
        ];
        if ($request->getUri()->getScheme() === 'https') {
            $parts[] = 'Secure';
        }
        if ($expire) {
            $parts[] = 'Max-Age=0';
        }

        return implode('; ', $parts);
    }

    private static function cookieFromHeader(string $header, string $name): ?string
    {
        foreach (explode(';', $header) as $pair) {
            [$key, $value] = array_pad(explode('=', trim($pair), 2), 2, '');
            if ($key === $name) {
                return $value;
            }
        }

        return null;
    }
}
