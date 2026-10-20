# Takt for Laravel


> 📚 **Documentation**: [taktlytics.com/docs/wrappers/laravel](https://taktlytics.com/docs/wrappers/laravel)

Laravel integration for [Takt](https://github.com/vskstudio) analytics. Drop a single `@takt`
Blade directive in your layout for privacy-friendly client-side tracking, and use the `Takt`
facade to send server-side events straight from your application code.

## Requirements

- PHP 8.1+
- Laravel 10, 11, or 12

## Installation

```bash
composer require vskstudio/takt-laravel
```

The service provider and the `Takt` facade are registered automatically through Laravel package
auto-discovery. There is nothing else to wire up.

## Configuration

The package ships with sane defaults and reads everything from your environment. To customise the
published config file:

```bash
php artisan vendor:publish --tag=takt-config
```

This writes `config/takt.php`. All values are environment-driven:

| Env variable             | Default                     | Description                                                              |
| ------------------------ | --------------------------- | ------------------------------------------------------------------------ |
| `TAKT_DOMAIN`            | `''`                        | The site/domain registered in Takt that data is attributed to.           |
| `TAKT_ENDPOINT`          | `https://taktlytics.com`    | Where events are collected. Defaults to the hosted Takt origin. Both the service origin (`https://taktlytics.com`) and the full collect URL (`https://taktlytics.com/api/event`) are accepted and behave identically; see [Endpoint](#endpoint). |
| `TAKT_SCRIPT_ORIGIN`     | `null`                      | First-party origin to serve the tracker + derive the endpoint from (`{origin}/api/event`): a custom domain you proxy through to dodge ad-blockers (endpoint wins over it). |
| `TAKT_API_KEY`           | `null`                      | Ingest-scoped API key used for server-side events (see below).           |
| `TAKT_MODE`              | `inline`                    | Snippet delivery mode: `inline`, `cdn`, `asset`, or `sdk` (full ES-module `init()`, required for `TAKT_SCRUB_URL`). |
| `TAKT_OUTBOUND`          | `false`                     | Track clicks on outbound links.                                          |
| `TAKT_FILES`            | `false`                     | Track file download clicks.                                              |
| `TAKT_FILE_EXTENSIONS`   | `''`                        | Comma-separated extensions to count as downloads (e.g. `pdf,zip,docx`). Empty keeps the tracker's default list. |
| `TAKT_TAGGED`            | `false`                     | Track elements tagged in HTML with `data-takt-event`.                    |
| `TAKT_NOT_FOUND`         | `false`                     | Track 404 pageviews.                                                      |
| `TAKT_EXCLUDE_LOCALHOST` | `true`                      | Skip tracking when running on localhost.                                 |
| `TAKT_NONCE`             | `null`                      | CSP nonce for the inline `<script>` (request-scoped, see config notes). |
| `TAKT_SAMPLE_RATE`       | `null`                      | Sample a fraction of hits, e.g. `0.5` keeps ~50%. Unset tracks everything. |
| `TAKT_TRACK_QUERY`       | `null`                      | Keep the raw query string + hash in tracked URLs (default strips them).  |
| `TAKT_QUERY_PARAMS`      | `''`                        | Comma-separated query params to keep when `TAKT_TRACK_QUERY` is off (e.g. `utm_source,utm_medium`). |
| `TAKT_EXCLUDE`           | `''`                        | Comma-separated path prefixes never tracked (e.g. `/app,/account`). Requires `TAKT_MODE=sdk`; segment-bounded. |
| `TAKT_RESPECT_DNT`       | `null`                      | Set to `false` to stop honoring the browser Do-Not-Track header.        |
| `TAKT_ENABLED`           | `null`                      | Kill-switch: set to `false` to disable tracking entirely.               |
| `TAKT_REDACT_ROUTES`     | `''`                        | Comma-separated sensitive routes sent as their pattern (e.g. `/verify/{token},/reset/{code}`). Snippet requires `TAKT_MODE=sdk`. See [Route redaction](#route-redaction). |
| `TAKT_ROUTE_TEMPLATES`   | `false`                     | Send every page as its Laravel route template (`/users/{id}`). Snippet requires `TAKT_MODE=sdk`. See [Route redaction](#route-redaction). |
| `TAKT_REDACT_UNDECODABLE_PATHS` | `true`                | Server-side events only: `false` matches a path that cannot be decoded like any other instead of masking it as `/[redacted]`. See [Route redaction](#route-redaction). |
| `TAKT_SCRUB_URL`         | `null`                      | Raw JS function to rewrite URLs before sending, e.g. `(u) => u.split('#')[0]`. **Requires `TAKT_MODE=sdk` and is dev-controlled only**. It is injected verbatim into the page; never build it from user input. |

Example `.env`:

```dotenv
TAKT_DOMAIN=example.com
TAKT_API_KEY=ingest_xxxxxxxxxxxxxxxx
TAKT_MODE=inline
```

### Endpoint

`TAKT_ENDPOINT` feeds two code paths at once: the browser snippet, which needs the full collect
URL, and the server-side sender, which needs the origin it appends `/api/event` to. Both forms are
therefore accepted and normalised for you, so these two settings are equivalent:

```dotenv
TAKT_ENDPOINT=https://taktlytics.com
TAKT_ENDPOINT=https://taktlytics.com/api/event
```

A value carrying any other path is taken as the collect URL verbatim. That is the case for a
same-origin first-party proxy (`TAKT_ENDPOINT=/collect`) used to dodge ad-blockers. Leave it unset
to talk to the hosted Takt service.

Takt itself is a managed service hosted in Europe; only the `/takt.js` measurement script can be
served from your own domain (see `asset` mode and `TAKT_SCRIPT_ORIGIN`).

## Client-side tracking

Add the `@takt` directive to the `<head>` of your layout:

```blade
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @takt
</head>
<body>
    @yield('content')
</body>
</html>
```

### Delivery modes

`TAKT_MODE` controls how the tracking script is delivered:

- **`inline`** (default): the script is embedded directly in the rendered HTML. Zero extra
  network requests, nothing to host.
- **`cdn`**: references the script from jsDelivr (`@vskstudio/takt-core`).
- **`asset`**: references a copy of the script served by your own application from
  `/takt/takt.auto.js` (prefixed with `TAKT_SCRIPT_ORIGIN` when set).
- **`sdk`**: loads the full SDK as an ES module and boots it with `init()`. Required for
  `TAKT_SCRUB_URL` (custom URL rewriting), which cannot be expressed as a data attribute.

## Server-side events

Use the `Takt` facade to record events from controllers, jobs, or anywhere in your application:

```php
use Vskstudio\Takt\Laravel\Facades\Takt;
use Vskstudio\Takt\Revenue;

// A custom event with properties and revenue
Takt::event('Signup', ['plan' => 'pro'], new Revenue('29.00', 'EUR'));

// A simple pageview
Takt::pageview();
```

Server-side events automatically attribute to the current request's IP address and User-Agent, so
they are correlated with the visitor that triggered them. Both methods accept an explicit URL and
referrer as their last arguments (`event(name, props, revenue, url, referrer)`,
`pageview(url, referrer)`), followed by an optional `route` template (see
[Route redaction](#route-redaction)).

> **API key scope:** `TAKT_API_KEY` must be an **ingest-scoped** key bound to the configured
> `TAKT_DOMAIN`. Keep it server-side only. It is never exposed to the browser.

## Route redaction

Query strings are stripped by default, but path segments are sent as they are: `/verify/abc123`
leaks the token. Two opt-in settings replace real paths with route templates, in the snippet and in
server-side events alike. In the snippet both require `TAKT_MODE=sdk`: the minimal snippet of the
other modes cannot honor them, so the `SnippetRenderer` throws.

```php
// config/takt.php
'mode' => 'sdk',
'redact_routes' => ['/verify/{token}', '/reset/{code}'],
```

`redact_routes` lists the sensitive routes. A matching path is sent as the pattern, every other path
keeps its real value. Patterns accept the Laravel syntax (`{token}`, `{page?}`) as well as
`[param]`, `[[optional]]`, `[...rest]`, `:param`, `:param?`, `*`, `**`, `*name`, `:name*` and `:name+`.
The browser SDK reads `{token}` as `[token]`, so a browser pageview reports `/verify/[token]` where a
server event reports `/verify/{token}`; write `/verify/[token]` if both must land on the same row.

A parameter can carry a regex constraint, `{id<\d+>}`, which must match the whole segment
(case-insensitively). The regex follows a restricted grammar, the same in the PHP and JavaScript
SDKs: alternation only at the top level, literals, `.`, `\d \D \w \W \s \S`, escaped ASCII
punctuation, classes `[...]` without nesting, and quantifiers bounded to 100. Groups, lookarounds,
backreferences, anchors and `/` are refused, and the grammar counts bytes, not characters. A
constrained parameter keeps its brace form in the browser, so the browser needs an SDK that reads
that form to enforce the constraint. A route is unusable when one of its constraints is refused,
when its constraints hold more than two variable quantifiers in total, when one segment holds more
than one constrained parameter or more than two variable quantifiers, when its ambiguity degree
exceeds one (`/{a}{b}/{c}{d}` is refused, `/{a}{b}/{c}` is accepted), or when it holds more than one
REST segment (`**`, `*name`, `:name*`, `:name+` or `[...name]`). An unusable route masks, as
`/[redacted]`, every path with the same shape; other paths are unaffected.

A path that cannot be decoded (a malformed escape such as `%ZZ`, or invalid UTF-8 once decoded) is
masked as `/[redacted]` while a route is configured. Set `TAKT_REDACT_UNDECODABLE_PATHS=false` to
match such a path like any other on server-side events: it is then sent as the template it matches,
and raw only when no route matches. The browser snippet keeps masking it, since the browser SDK has
no such option.

```php
'mode' => 'sdk',
'route_templates' => true,
```

`route_templates` sends every page as its route template: `/users/42` becomes `/users/{id}`. The
package reads it from the matched route of the current request (`$request->route()->uri()`), renders
it into the `@takt` snippet and uses it as the default `route` of every `Takt::pageview()` and
`Takt::event()` sent during that request. It suits fully private apps; on a public site it merges
every article into one row. A request with no matched route keeps its real path, still subject to
`redact_routes`.

Any server-side call can pick its own route template:

```php
Takt::event('Verified', url: url()->current(), route: '/verify/{token}');
```

## Container bindings

The service provider registers exactly two services:

| Service                        | Binding     | Notes                                                                 |
| ------------------------------ | ----------- | --------------------------------------------------------------------- |
| `Vskstudio\Takt\SnippetRenderer` | `scoped`    | Built from config once per request scope, so it carries the current route template; resolved by the `@takt` Blade directive. Rebind it per request if you need a fresh CSP nonce. |
| `Vskstudio\Takt\Takt`            | `scoped`    | Backs the `Takt` facade. **Not** a singleton: it captures the current request's IP/User-Agent, so it is rebuilt on each request scope and never leaks attribution across requests under long-lived workers such as Octane. |

## Dependency audit exemption (Laravel 10 and 11)

The Laravel 12 cells run `composer audit` after resolving dependencies, and block on any advisory. The Laravel 10.x and 11.x cells set `audit.block-insecure false` instead, because no patched version exists for the advisories that affect those lines:

- Laravel 11.x: `laravel/framework` 11.0.0 through 11.57.0 are all covered by advisories that no 11.x release fixes. The four that apply to 11.57.0 are `PKSA-d5tc-s1qs-h781`, `PKSA-m5cs-t1y6-qpcs`, `PKSA-3r5d-mb8f-1qw9` and `PKSA-mdq4-51ck-6kdq`, each fixed only in a 12.x or 13.x line.
- Laravel 10.x: the line is end of life and carries framework advisories with no patched 10.x release.

Because no fixed release exists, `composer update` cannot resolve a clean set for these lines, so the exemption is what lets the cells test the code at all. The exemption is limited to these two lines and will be removed as soon as a patched release is published for them, or when Laravel 10 and 11 are dropped from the matrix.

The committed `config.platform.php` value of `8.2.99` also applies locally, so `composer.lock` is resolved for PHP 8.2. CI overrides it per cell with the PHP version of that cell.

## License

MIT. See [LICENSE](LICENSE).
