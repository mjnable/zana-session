<?php

declare(strict_types=1);

namespace Zana\Session\Tests;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zana\Session\Session;
use Zana\Session\SessionContext;
use Zana\Session\SessionMiddleware;
use Zana\Session\SessionStoreInterface;

final class SessionTest extends TestCase
{
    private MemoryStore $store;

    protected function setUp(): void
    {
        $this->store = new MemoryStore();
    }

    public function testUntouchedSessionsSendNoCookieAndStoreNothing(): void
    {
        $read = null;
        $response = $this->visit(static function (Session $session) use (&$read): void {
            $read = $session->get('anything', 'default');
        });

        $this->assertSame('default', $read);

        $this->assertFalse($response->hasHeader('Set-Cookie'));
        $this->assertSame([], $this->store->sessions);
    }

    public function testWrittenSessionsGetASecureCookieAndComeBack(): void
    {
        $first = $this->visit(static fn (Session $s) => $s->set('user', 42), 'https://example.com/admin');
        $cookie = $first->getHeaderLine('Set-Cookie');

        $this->assertMatchesRegularExpression('/^zana_session=[0-9a-f]{64}; Path=\/; HttpOnly; SameSite=Lax; Secure$/', $cookie);
        $id = substr(explode(';', $cookie)[0], strlen('zana_session='));

        $seen = null;
        $second = $this->visit(static function (Session $s) use (&$seen): void {
            $seen = $s->get('user');
        }, cookie: $id);

        $this->assertSame(42, $seen);
        $this->assertFalse($second->hasHeader('Set-Cookie'), 'the browser keeps the same cookie');
    }

    public function testFlashMessagesLastOneRequest(): void
    {
        $id = $this->idFrom($this->visit(static fn (Session $s) => $s->flash('status', 'Saved.')));

        $read = [];
        foreach ([1, 2] as $visit) {
            $this->visit(static function (Session $s) use (&$read): void {
                $read[] = $s->getFlash('status');
            }, cookie: $id);
        }

        $this->assertSame(['Saved.', null], $read);
    }

    public function testRegenerateGivesANewIdAndDestroysTheOldOne(): void
    {
        $id = $this->idFrom($this->visit(static fn (Session $s) => $s->set('cart', [1])));

        $response = $this->visit(static function (Session $s): void {
            $s->regenerate();
            $s->set('user', 7);
        }, cookie: $id);
        $newId = $this->idFrom($response);

        $this->assertNotSame($id, $newId);
        $this->assertArrayNotHasKey($id, $this->store->sessions);
        $this->assertSame(['cart' => [1], 'user' => 7], $this->store->sessions[$newId]);
    }

    public function testInvalidateDestroysTheSessionAndExpiresTheCookie(): void
    {
        $id = $this->idFrom($this->visit(static fn (Session $s) => $s->set('user', 7)));

        $response = $this->visit(static fn (Session $s) => $s->invalidate(), cookie: $id);

        $this->assertSame([], $this->store->sessions);
        $this->assertStringContainsString('Max-Age=0', $response->getHeaderLine('Set-Cookie'));
    }

    public function testForgedOrExpiredIdsStartANewSession(): void
    {
        $seen = 'unset';
        foreach (['../../etc/passwd', str_repeat('a', 64)] as $cookie) {
            $this->visit(static function (Session $s) use (&$seen): void {
                $seen = $s->id();
            }, cookie: $cookie);
            $this->assertNull($seen);
        }
    }

    public function testTheFlashKeyIsReserved(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Session())->set('_flash', 'x');
    }

    /**
     * @param callable(Session): mixed $action
     */
    private function visit(callable $action, string $uri = 'http://example.com/', ?string $cookie = null): ResponseInterface
    {
        $context = new SessionContext();
        $request = new ServerRequest('GET', $uri);
        if ($cookie !== null) {
            $request = $request->withCookieParams(['zana_session' => $cookie]);
        }

        return (new SessionMiddleware($this->store, $context))->process($request, new class ($context, $action) implements RequestHandlerInterface {
            /** @param callable(Session): mixed $action */
            public function __construct(private readonly SessionContext $context, private $action)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                ($this->action)($this->context->session());

                return new Response();
            }
        });
    }

    private function idFrom(ResponseInterface $response): string
    {
        return substr(explode(';', $response->getHeaderLine('Set-Cookie'))[0], strlen('zana_session='));
    }
}

final class MemoryStore implements SessionStoreInterface
{
    /** @var array<string, array<string, mixed>> */
    public array $sessions = [];

    public function read(string $id): ?array
    {
        return $this->sessions[$id] ?? null;
    }

    public function write(string $id, array $data, int $lifetime): void
    {
        $this->sessions[$id] = $data;
    }

    public function destroy(string $id): void
    {
        unset($this->sessions[$id]);
    }
}
