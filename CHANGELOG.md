# vskstudio/takt-laravel

## 0.8.0

### Breaking Changes

- The endpoint must be `https`, local development included. `takt-core-php` 0.7 refuses
  any `http://` endpoint (`http://localhost` too) and any scheme-less `//host` value, so the API
  key is never sent in clear. `TAKT_ENDPOINT` set to such a value now makes the `@takt` directive
  and the `Takt` service throw an `InvalidArgumentException`. This was already in effect since
  0.7.0 and is documented here. Use an `https` collector in development.
- In template mode (`TAKT_ROUTE_TEMPLATES` / `route_templates`), the referrer is always reduced to
  its origin, and a referrer that is not `http(s)` is dropped. A page URL whose route cannot be
  resolved is masked as `/[redacted]` (or the matching `redact_routes` pattern), never sent raw, and
  a URL that cannot be parsed is sent with an empty `u`, which the ingest refuses. Events that
  used to carry a raw path or a full cross-host referrer now carry the masked form.

### Patch Changes

- Fix the `@takt` inline snippet replacing `window.takt`. The bundled `takt.auto.js` of
  `takt-core-php` 0.7.0 assigned the global `takt`, so `takt('Event')` threw and, from the first
  pageview on, events went out with an empty URL. Requires `vskstudio/takt-core-php` 0.7.3, which
  ships the fixed bundle (`@vskstudio/takt-core@0.12.3`).

## 0.7.0

### Minor Changes

- `TAKT_REDACT_UNDECODABLE_PATHS` (`redact_undecodable_paths`, default `true`)
  is passed to the server-side `Takt` client as its `redactUndecodablePaths`
  argument. `false` matches a path that cannot be decoded like any other instead
  of masking it as `/[redacted]`. The browser snippet is unaffected.
- The route redaction documentation describes the constraint grammar, the
  REST segment and ambiguity limits, and the unusable-route masking of
  `takt-core-php`.
- Requires `vskstudio/takt-core-php` 0.7.

## 0.6.0

### Minor Changes

- Route redaction. `redact_routes` (`TAKT_REDACT_ROUTES`) lists sensitive routes
  sent as their pattern instead of the real path (`/verify/abc` becomes
  `/verify/{token}`), and `route_templates` (`TAKT_ROUTE_TEMPLATES`) sends every
  page as its Laravel route template, read from the matched route of the current
  request. Both apply to the `@takt` snippet, where they require `mode: sdk`,
  and to the `Takt` facade: the current route template becomes the default
  `route` of `pageview()` and `event()`, which also accept an explicit `route`.
- The `SnippetRenderer` binding is now `scoped` instead of `singleton`, so the
  rendered snippet follows the current request under long-lived workers.
- Requires `vskstudio/takt-core-php` 0.6.

## 0.5.1

### Patch Changes

- `TAKT_ENDPOINT` no longer means two different things depending on the code path.
  It fed both the browser snippet, which expects the full collect URL, and the
  server-side sender, which expects an origin it appends `/api/event` to, so the
  shipped default (`https://taktlytics.com`) made the browser post to the service
  root, while the full URL form (`https://taktlytics.com/api/event`) made the
  server post to `/api/event/api/event`. Both forms are now normalised on read to
  whatever each path needs, so every existing configuration keeps working and both
  paths agree. A value carrying any other path (a same-origin first-party proxy
  such as `/collect`) is still used verbatim.
- Docs: the accepted `endpoint` forms are spelled out in the README and the
  published config file; the container bindings are documented with their real
  scope (`SnippetRenderer` is a singleton, `Takt` is `scoped`, not a singleton);
  the `Takt` facade docblock now carries the full `event()`/`pageview()`
  signatures.

Releases before 0.5.1 are documented in the repository's git tags.
