<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProxyTransformHeaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_web_mengirim_direktif_no_transform(): void
    {
        $response = $this->get('/activate-account?email=xanderpakpahan21@gmail.com');

        $response->assertOk();
        $this->assertStringContainsString(
            'no-transform',
            (string) $response->headers->get('Cache-Control')
        );
    }

    public function test_no_transform_tidak_menghapus_cache_control_yang_sudah_ada(): void
    {
        $response = $this->get('/login');

        $cacheControl = (string) $response->headers->get('Cache-Control');

        $this->assertStringContainsString('no-transform', $cacheControl);
        $this->assertStringContainsString('private', $cacheControl);
    }
}
