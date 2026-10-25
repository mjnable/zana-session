# mj-zana/session

Server-side sessions and flash messages for Zana applications, stored in a
PSR-16 cache.

```
composer require mj-zana/session
```

Register `SessionServiceProvider` (and a PSR-16 cache, such as
`mj-zana/cache`). Add `SessionMiddleware` to the routes that need a
session, such as a back office, or to `http.middleware`.

```php
public function save(SessionInterface $session): ResponseInterface
{
    // …
    $session->flash('status', 'Published.');

    return $this->responses->redirect('/admin/posts', 303);
}
```

- **Untouched sessions cost nothing.** A request that never writes the
  session stores nothing and sends no cookie, so public pages stay
  cacheable.
- **Cookie:** `HttpOnly`, `SameSite=Lax`, `Secure` on HTTPS, scoped to the
  application's base path. Ids are 256 random bits.
- **Fixation:** call `regenerate()` after login, and `invalidate()` at
  logout.
- **Flash messages** last one request.
- **Lifetime:** `session.lifetime` is the inactivity timeout, in seconds
  (default 7200). Each request restarts it.
- **Cookie name:** `session.cookie` (default `zana_session`).

## License

MIT
