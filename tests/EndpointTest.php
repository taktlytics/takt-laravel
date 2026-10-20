<?php

namespace Vskstudio\Takt\Laravel\Tests;

use ReflectionProperty;
use Vskstudio\Takt\Laravel\Endpoint;
use Vskstudio\Takt\Options;
use Vskstudio\Takt\SnippetRenderer;
use Vskstudio\Takt\Takt;

final class EndpointTest extends TestCase
{
    private function renderedEndpoint(string $endpoint): string
    {
        $this->app['config']->set('takt.endpoint', $endpoint);
        $this->app['config']->set('takt.mode', 'cdn');
        $this->app->forgetInstance(SnippetRenderer::class);

        preg_match('/data-endpoint="([^"]*)"/', $this->app->make(SnippetRenderer::class)->render(), $m);

        return $m[1] ?? '';
    }

    private function serverEndpoint(string $endpoint): string
    {
        $this->app['config']->set('takt.endpoint', $endpoint);
        $this->app->forgetScopedInstances();

        return (string) (new ReflectionProperty(Takt::class, 'endpoint'))->getValue($this->app->make(Takt::class));
    }

    public function test_bare_origin_gains_the_collect_path_in_the_snippet(): void
    {
        $this->assertSame('https://taktlytics.com/api/event', $this->renderedEndpoint('https://taktlytics.com'));
        $this->assertSame('https://ingest.example.com/api/event', $this->renderedEndpoint('https://ingest.example.com'));
    }

    public function test_bare_origin_stays_an_origin_server_side(): void
    {
        $this->assertSame('https://taktlytics.com', $this->serverEndpoint('https://taktlytics.com'));
        $this->assertSame('https://ingest.example.com', $this->serverEndpoint('https://ingest.example.com'));
    }

    public function test_full_collect_url_is_kept_verbatim_in_the_snippet(): void
    {
        $this->assertSame('https://taktlytics.com/api/event', $this->renderedEndpoint('https://taktlytics.com/api/event'));
        $this->assertSame('https://ingest.example.com/api/event', $this->renderedEndpoint('https://ingest.example.com/api/event'));
    }

    public function test_full_collect_url_is_reduced_to_an_origin_server_side(): void
    {
        $this->assertSame('https://taktlytics.com', $this->serverEndpoint('https://taktlytics.com/api/event'));
        $this->assertSame('https://ingest.example.com', $this->serverEndpoint('https://ingest.example.com/api/event'));
    }

    public function test_same_origin_proxy_path_is_left_untouched(): void
    {
        $this->assertSame('/collect', $this->renderedEndpoint('/collect'));
        $this->assertSame('/collect', $this->serverEndpoint('/collect'));
        $this->assertSame('/api/event', $this->renderedEndpoint('/api/event'));
    }

    public function test_trailing_slash_is_ignored(): void
    {
        $this->assertSame('https://taktlytics.com/api/event', $this->renderedEndpoint('https://taktlytics.com/'));
        $this->assertSame('https://taktlytics.com', $this->serverEndpoint('https://taktlytics.com/'));
    }

    public function test_blank_endpoint_falls_back_to_the_hosted_service(): void
    {
        $this->assertSame(Options::HOSTED_ENDPOINT, Endpoint::collect(''));
        $this->assertSame(Options::HOSTED_ORIGIN, Endpoint::origin(null));
    }

    public function test_plain_http_endpoint_is_refused_by_the_snippet(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->renderedEndpoint('http://localhost:8000');
    }

    public function test_plain_http_endpoint_is_refused_by_the_server_client(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->serverEndpoint('http://localhost:8000');
    }

    public function test_hosted_default_with_script_origin_lets_the_tracker_derive_first_party(): void
    {
        // L'origine première-partie doit rester déductible : endpoint hébergé par
        // défaut + script_origin ⇒ aucun data-endpoint rendu.
        $this->app['config']->set('takt.script_origin', 'https://m.example.com');

        $this->assertSame('', $this->renderedEndpoint('https://taktlytics.com'));
    }
}
