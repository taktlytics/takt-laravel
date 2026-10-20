<?php

namespace Vskstudio\Takt\Laravel;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Vskstudio\Takt\Options;
use Vskstudio\Takt\SnippetRenderer;
use Vskstudio\Takt\Takt;

final class TaktServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/takt.php', 'takt');

        $this->app->scoped(SnippetRenderer::class, function ($app) {
            $c = $app['config']['takt'];

            return new SnippetRenderer(Options::fromArray([
                'domain' => $c['domain'],
                'endpoint' => Endpoint::collect($c['endpoint']),
                'scriptOrigin' => $c['script_origin'],
                'mode' => $c['mode'],
                'outbound' => $c['outbound'],
                'files' => $c['files'],
                'tagged' => $c['tagged'],
                'notFound' => $c['not_found'],
                'fileExtensions' => $c['file_extensions'],
                'excludeLocalhost' => $c['exclude_localhost'],
                'nonce' => $c['nonce'],
                'sampleRate' => $c['sample_rate'],
                'trackQuery' => $c['track_query'],
                'queryParams' => $c['query_params'],
                'exclude' => $c['exclude'],
                'respectDnt' => $c['respect_dnt'],
                'enabled' => $c['enabled'],
                'scrubUrl' => $c['scrub_url'],
                'redactRoutes' => $c['redact_routes'] ?? [],
                'routeTemplates' => $c['route_templates'] ?? false,
                'routeTemplate' => ($c['route_templates'] ?? false) ? RouteTemplate::of($app['request'] ?? null) : null,
            ]));
        });

        // Scoped (not singleton): the visitor IP/user-agent are bound from the
        // current request, so the instance must not survive across requests under
        // long-lived workers such as Octane.
        $this->app->scoped(Takt::class, function ($app) {
            $c = $app['config']['takt'];
            $takt = new Takt(Endpoint::origin($c['endpoint']), $c['domain'], $c['api_key'], redactRoutes: self::routeList($c['redact_routes'] ?? []), redactUndecodablePaths: $c['redact_undecodable_paths'] ?? true);
            $request = $app['request'] ?? null;
            if ($request !== null) {
                $takt = $takt->withVisitor($request->ip(), $request->userAgent());
            }
            if ($c['route_templates'] ?? false) {
                $takt = $takt->withRoute(static fn (): ?string => RouteTemplate::of($app['request'] ?? null));
            }

            return $takt;
        });
    }

    /** @return list<string> */
    private static function routeList(mixed $routes): array
    {
        return is_array($routes) ? array_values(array_filter(array_map(
            static fn (mixed $route): string => is_scalar($route) ? trim((string) $route) : '',
            $routes,
        ), static fn (string $route): bool => $route !== '')) : [];
    }

    public function boot(): void
    {
        $this->publishes([__DIR__.'/../config/takt.php' => config_path('takt.php')], 'takt-config');

        Blade::directive('takt', static function () {
            return '<?php echo app(\\Vskstudio\\Takt\\SnippetRenderer::class)->render(); ?>';
        });
    }
}
