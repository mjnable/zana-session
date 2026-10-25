<?php

declare(strict_types=1);

namespace Zana\Session;

use Psr\SimpleCache\CacheInterface;
use Zana\Configuration\ConfigurationInterface;
use Zana\Container\Contracts\ServiceCollectionInterface;
use Zana\Container\Contracts\ServiceResolverInterface;
use Zana\Core\ServiceProvider;

/**
 * Registers the session store (the application's PSR-16 cache), the scoped
 * SessionContext and SessionInterface, and SessionMiddleware. Reads
 * session.cookie (default zana_session) and session.lifetime (seconds of
 * inactivity, default 7200).
 *
 * Add SessionMiddleware to http.middleware, or only to the routes that need
 * a session, such as a back office.
 */
final class SessionServiceProvider extends ServiceProvider
{
    public function register(ServiceCollectionInterface $services): void
    {
        $services
            ->trySingleton(SessionStoreInterface::class, static function (ServiceResolverInterface $r): SessionStoreInterface {
                if (!$r->has(CacheInterface::class)) {
                    throw new \LogicException('Sessions need a PSR-16 cache: register mj-zana/cache, or a ' . SessionStoreInterface::class . '.');
                }
                $cache = $r->get(CacheInterface::class);
                assert($cache instanceof CacheInterface);

                return new CacheSessionStore($cache);
            })
            ->scoped(SessionContext::class)
            ->scoped(SessionInterface::class, static function (ServiceResolverInterface $r): SessionInterface {
                $context = $r->get(SessionContext::class);
                assert($context instanceof SessionContext);

                return $context->session();
            })
            ->scoped(SessionMiddleware::class, static function (ServiceResolverInterface $r): SessionMiddleware {
                $config = $r->get(ConfigurationInterface::class);
                $store = $r->get(SessionStoreInterface::class);
                $context = $r->get(SessionContext::class);
                assert($store instanceof SessionStoreInterface && $context instanceof SessionContext);

                return new SessionMiddleware($store, $context, $config->string('session.cookie', 'zana_session'), $config->int('session.lifetime', 7200));
            });
    }

    public function origin(): string
    {
        return 'package:mj-zana/session';
    }
}
