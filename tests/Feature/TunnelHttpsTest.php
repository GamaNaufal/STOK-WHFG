<?php

namespace Tests\Feature;

use Tests\TestCase;

class TunnelHttpsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_https_forwarded_by_local_tunnel_generates_secure_assets_and_login_action(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeader('X-Forwarded-Proto', 'https')
            ->get('http://warehouse-preview.example.test/login')
            ->assertOk()
            ->assertSee('href="https://warehouse-preview.example.test/css/login-ui.css"', false)
            ->assertSee('src="https://warehouse-preview.example.test/vendor/bootstrap-5.3.0/dist/js/bootstrap.bundle.min.js"', false)
            ->assertSee('action="https://warehouse-preview.example.test/login"', false)
            ->assertDontSee('http://warehouse-preview.example.test/', false);
    }

    public function test_ipv6_loopback_proxy_does_not_override_host_or_port_from_forwarding_headers(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '::1'])
            ->withHeaders([
                'X-Forwarded-Proto' => 'https',
                'X-Forwarded-Host' => 'untrusted.example.test',
                'X-Forwarded-Port' => '8443',
            ])
            ->get('http://warehouse-preview.example.test/login')
            ->assertOk()
            ->assertSee('action="https://warehouse-preview.example.test/login"', false)
            ->assertDontSee('untrusted.example.test', false)
            ->assertDontSee(':8443', false);
    }

    public function test_direct_local_http_keeps_working_without_forwarded_protocol(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->get('http://localhost:8001/login')
            ->assertOk()
            ->assertSee('href="http://localhost:8001/css/login-ui.css"', false)
            ->assertSee('action="http://localhost:8001/login"', false);
    }

    public function test_direct_remote_client_cannot_spoof_https_with_a_forwarding_header(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.20'])
            ->withHeader('X-Forwarded-Proto', 'https')
            ->get('http://warehouse-preview.example.test/login')
            ->assertOk()
            ->assertSee('action="http://warehouse-preview.example.test/login"', false)
            ->assertSee('href="http://warehouse-preview.example.test/css/login-ui.css"', false);
    }
}
