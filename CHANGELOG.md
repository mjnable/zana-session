# Changelog

All Zana packages share one version number and are released together. A release that did
not change this package is not listed.

## 2.19.1 — 2026-11-07

### Security

- `SessionMiddleware` no longer writes back a session that is not stored any more, so a request still running when the
  user signs out, or when the id is regenerated, does not bring the old session back.

## 2.4.0 — 2026-10-26

### Changed

- Factory registrations declare the services they resolve with `uses()`, so the container checks
  their lifetimes at startup (FE-40).
- Requires `mj-zana/core` ^2.4.

## 2.3.0 — 2026-10-26

### Changed

- Tests and tooling (`tests/`, `phpunit.xml.dist`, `phpstan.neon.dist`) are no longer in Composer
  archives, so they stay out of production `vendor/` folders (FE-17).

## 2.2.0 — 2026-10-26

First release (FE-27). Sessions in a PSR-16 cache, flash messages, a middleware that sends a
cookie only when the session is written, `regenerate()` and `invalidate()`.
