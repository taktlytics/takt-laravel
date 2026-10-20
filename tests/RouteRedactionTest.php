<?php

namespace Vskstudio\Takt\Laravel\Tests;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Vskstudio\Takt\Laravel\RouteTemplate;
use Vskstudio\Takt\SnippetRenderer;
use Vskstudio\Takt\Takt;

final class RouteRedactionTest extends TestCase
{
    private function useSdk(): void
    {
        $this->app['config']->set('takt.mode', 'sdk');
    }

    public function test_config_defaults_leave_routes_untouched(): void
    {
        $this->assertSame([], config('takt.redact_routes'));
        $this->assertFalse(config('takt.route_templates'));
    }

    public function test_config_defaults_mask_undecodable_paths(): void
    {
        $this->assertTrue(config('takt.redact_undecodable_paths'));
    }

    public function test_redact_undecodable_paths_reads_false_from_the_environment(): void
    {
        foreach (['false', 'off'] as $value) {
            putenv("TAKT_REDACT_UNDECODABLE_PATHS={$value}");
            try {
                $config = require __DIR__.'/../config/takt.php';
            } finally {
                putenv('TAKT_REDACT_UNDECODABLE_PATHS');
            }

            $this->assertFalse($config['redact_undecodable_paths'], $value);
        }
    }

    public function test_sdk_snippet_carries_redact_routes(): void
    {
        $this->useSdk();
        $this->app['config']->set('takt.redact_routes', ['/verify/{token}', '/reset/[code]']);

        $html = $this->app->make(SnippetRenderer::class)->render();

        $this->assertStringContainsString('"redactRoutes":["\/verify\/[token]","\/reset\/[code]"]', $html);
    }

    public function test_redact_routes_outside_sdk_mode_throws(): void
    {
        $this->app['config']->set('takt.mode', 'cdn');
        $this->app['config']->set('takt.redact_routes', ['/verify/{token}']);

        $this->expectException(\InvalidArgumentException::class);
        $this->app->make(SnippetRenderer::class);
    }

    public function test_sdk_snippet_carries_the_current_route_template(): void
    {
        $this->useSdk();
        $this->app['config']->set('takt.route_templates', true);
        Route::get('/verify/{token}', fn () => app(SnippetRenderer::class)->render());

        $html = $this->get('/verify/abc123')->getContent();

        $this->assertIsString($html);
        $this->assertStringContainsString('"routeTemplates":true', $html);
        $this->assertStringContainsString('routeTemplate:()=>"\/verify\/{token}"', $html);
        $this->assertStringNotContainsString('abc123', $html);
    }

    public function test_route_template_is_not_rendered_when_route_templates_is_off(): void
    {
        $this->useSdk();
        Route::get('/verify/{token}', fn () => app(SnippetRenderer::class)->render());

        $html = $this->get('/verify/abc123')->getContent();

        $this->assertIsString($html);
        $this->assertStringNotContainsString('routeTemplate', $html);
    }

    public function test_snippet_renderer_is_rebuilt_per_request_scope(): void
    {
        $first = $this->app->make(SnippetRenderer::class);
        $this->app->forgetScopedInstances();

        $this->assertNotSame($first, $this->app->make(SnippetRenderer::class));
    }

    public function test_route_template_of_a_request(): void
    {
        Route::get('/users/{id?}', fn () => RouteTemplate::of(request()) ?? 'none');
        Route::get('/', fn () => RouteTemplate::of(request()) ?? 'none');

        $this->assertSame('/users/{id?}', $this->get('/users/42')->getContent());
        $this->assertSame('/', $this->get('/')->getContent());
        $this->assertNull(RouteTemplate::of(Request::create('/unrouted')));
        $this->assertNull(RouteTemplate::of(null));
    }

    public function test_server_client_receives_redact_routes(): void
    {
        $this->app['config']->set('takt.redact_routes', ['/verify/{token}']);

        $takt = $this->app->make(Takt::class);

        $this->assertSame(['/verify/{token}'], (new \ReflectionProperty($takt, 'redactRoutes'))->getValue($takt));
    }

    public function test_server_client_receives_redact_undecodable_paths(): void
    {
        $this->app['config']->set('takt.redact_undecodable_paths', false);

        $takt = $this->app->make(Takt::class);

        $this->assertFalse((new \ReflectionProperty($takt, 'redactUndecodablePaths'))->getValue($takt));
    }

    public function test_server_client_masks_undecodable_paths_when_the_setting_is_missing(): void
    {
        $this->app['config']->set('takt.redact_undecodable_paths', null);

        $takt = $this->app->make(Takt::class);

        $this->assertTrue((new \ReflectionProperty($takt, 'redactUndecodablePaths'))->getValue($takt));
    }

    public function test_server_client_defaults_to_the_current_route_when_route_templates_is_on(): void
    {
        $this->app['config']->set('takt.route_templates', true);
        Route::get('/verify/{token}', fn () => (string) $this->defaultRouteOf(app(Takt::class)));

        $this->assertSame('/verify/{token}', $this->get('/verify/abc123')->getContent());
    }

    public function test_server_client_has_no_default_route_when_route_templates_is_off(): void
    {
        Route::get('/verify/{token}', fn () => $this->defaultRouteOf(app(Takt::class)) ?? 'none');

        $this->assertSame('none', $this->get('/verify/abc123')->getContent());
    }

    private function defaultRouteOf(Takt $takt): ?string
    {
        $method = new \ReflectionMethod($takt, 'defaultRoute');

        $route = $method->invoke($takt);

        return is_string($route) ? $route : null;
    }
}
