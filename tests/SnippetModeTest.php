<?php

namespace Vskstudio\Takt\Laravel\Tests;

use Vskstudio\Takt\SnippetRenderer;

final class SnippetModeTest extends TestCase
{
    private function renderWithMode(string $mode): string
    {
        $this->app['config']->set('takt.mode', $mode);
        $this->app->forgetInstance(SnippetRenderer::class);

        return $this->app->make(SnippetRenderer::class)->render();
    }

    public function test_inline_mode_embeds_bundle(): void
    {
        $html = $this->renderWithMode('inline');

        $this->assertStringContainsString("(function(){'use strict';", $html);
        $this->assertStringNotContainsString('src=', $html);
        $this->assertStringContainsString('data-domain="example.com"', $html);
    }

    public function test_inline_bundle_does_not_reassign_the_global_takt(): void
    {
        $html = $this->renderWithMode('inline');

        $this->assertStringNotContainsString('var takt=', $html);
        $this->assertStringNotContainsString('window.takt=', $html);
        $this->assertStringNotContainsString('window.takt =', $html);
    }

    public function test_cdn_mode_uses_jsdelivr(): void
    {
        $html = $this->renderWithMode('cdn');

        $this->assertStringContainsString('cdn.jsdelivr.net', $html);
        $this->assertStringContainsString('src="https://cdn.jsdelivr.net', $html);
        $this->assertStringContainsString('data-domain="example.com"', $html);
    }

    public function test_asset_mode_uses_local_path(): void
    {
        $html = $this->renderWithMode('asset');

        $this->assertStringContainsString('src="/takt/takt.auto.js"', $html);
        $this->assertStringContainsString('data-domain="example.com"', $html);
    }

    public function test_autocapture_config_drives_data_auto(): void
    {
        $this->app['config']->set('takt.mode', 'cdn');
        $this->app['config']->set('takt.outbound', true);
        $this->app['config']->set('takt.files', true);
        $this->app['config']->set('takt.tagged', true);
        $this->app['config']->set('takt.not_found', true);
        $this->app['config']->set('takt.file_extensions', ['pdf', 'zip']);
        $this->app->forgetInstance(SnippetRenderer::class);

        $html = $this->app->make(SnippetRenderer::class)->render();

        $this->assertStringContainsString('data-auto="outbound,downloads,tagged,404"', $html);
        $this->assertStringContainsString('data-downloads-ext="pdf,zip"', $html);
    }

    public function test_advanced_options_drive_data_attrs(): void
    {
        $this->app['config']->set('takt.mode', 'cdn');
        $this->app['config']->set('takt.sample_rate', 0.5);
        $this->app['config']->set('takt.track_query', true);
        $this->app['config']->set('takt.query_params', ['utm_source', 'utm_medium']);
        $this->app['config']->set('takt.respect_dnt', false);
        $this->app['config']->set('takt.enabled', false);
        $this->app->forgetInstance(SnippetRenderer::class);

        $html = $this->app->make(SnippetRenderer::class)->render();

        $this->assertStringContainsString('data-sample-rate="0.5"', $html);
        $this->assertStringContainsString('data-track-query="true"', $html);
        $this->assertStringContainsString('data-query-params="utm_source,utm_medium"', $html);
        $this->assertStringContainsString('data-respect-dnt="false"', $html);
        $this->assertStringContainsString('data-enabled="false"', $html);
    }

    public function test_sdk_mode_renders_module_with_scrub_url(): void
    {
        $this->app['config']->set('takt.mode', 'sdk');
        $this->app['config']->set('takt.sample_rate', 0.25);
        $this->app['config']->set('takt.scrub_url', '(u)=>u.split("#")[0]');
        $this->app->forgetInstance(SnippetRenderer::class);

        $html = $this->app->make(SnippetRenderer::class)->render();

        $this->assertStringContainsString('<script type="module"', $html);
        $this->assertStringContainsString('import{init}from', $html);
        $this->assertStringContainsString('"sampleRate":0.25', $html);
        $this->assertStringContainsString('scrubUrl:(u)=>u.split("#")[0]', $html);
    }

    public function test_sdk_mode_passes_exclude_paths(): void
    {
        $this->app['config']->set('takt.mode', 'sdk');
        $this->app['config']->set('takt.exclude', ['/app', '/account']);
        $this->app->forgetInstance(SnippetRenderer::class);

        $html = $this->app->make(SnippetRenderer::class)->render();

        $this->assertStringContainsString('<script type="module"', $html);
        $this->assertStringContainsString('"exclude":["\/app","\/account"]', $html);
    }
}
