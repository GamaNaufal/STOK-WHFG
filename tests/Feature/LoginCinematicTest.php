<?php

namespace Tests\Feature;

use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginCinematicTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_keeps_a_native_accessible_credentials_form(): void
    {
        $response = $this->get(route('login'))->assertOk()->assertViewIs('shared.auth.login');
        $xpath = $this->html($response->getContent());

        $this->assertSame(1, $xpath->query('//form[translate(@method, "post", "POST")="POST"]')->length);
        $this->assertSame(route('login'), $xpath->evaluate('string(//form/@action)'));
        $this->assertNotSame('', $xpath->evaluate('string(//form/input[@name="_token"]/@value)'));
        $this->assertSame(1, $xpath->query('//form//input[@id="email" and @name="email" and @type="email" and @autocomplete="username" and @required]')->length);
        $this->assertSame(1, $xpath->query('//form//label[@for="email"]')->length);
        $this->assertSame(1, $xpath->query('//form//input[@id="password" and @name="password" and @type="password" and @autocomplete="current-password" and @required]')->length);
        $this->assertSame(1, $xpath->query('//form//label[@for="password"]')->length);
        $this->assertSame(1, $xpath->query('//form//button[@id="togglePassword" and @type="button" and @aria-label]')->length);
        $this->assertSame(1, $xpath->query('//form//button[@type="submit" and not(@disabled)]')->length);
        $this->assertSame(0, $xpath->query('//form//video')->length);
    }

    public function test_warehouse_media_is_deferred_and_has_a_static_fallback(): void
    {
        $response = $this->get(route('login'))->assertOk();
        $xpath = $this->html($response->getContent());

        $this->assertSame(1, $xpath->query('//video[@preload="none" and @muted and @loop and @playsinline]')->length);
        $this->assertSame(0, $xpath->query('//video/@src | //video/source/@src')->length,
            'A mobile or reduced-motion visit must not start a video download from the initial HTML.');
        $this->assertGreaterThan(0, $xpath->query('//video/@data-src | //video/source/@data-src')->length);
        $this->assertGreaterThan(0, $xpath->query('//picture//img[@src]')->length,
            'The warehouse still must remain visible when JavaScript or video is unavailable.');
        $response->assertSee('warehouse_fg_login_poster.webp', false)
            ->assertSee('warehouse_fg_login_mobile.webp', false)
            ->assertSee('js/pages/login-cinematic.js', false);
    }

    public function test_failed_login_preserves_email_and_error_but_never_password(): void
    {
        User::factory()->create(['email' => 'warehouse@example.test']);

        $this->from(route('login'))->post(route('login'), [
            'email' => 'warehouse@example.test',
            'password' => 'wrong-password-never-redisplay',
        ])->assertRedirect(route('login'))->assertSessionHasErrors('email');
        $this->assertGuest();

        $response = $this->get(route('login'))->assertOk()
            ->assertSee('Email atau password salah')
            ->assertDontSee('wrong-password-never-redisplay');
        $xpath = $this->html($response->getContent());
        $this->assertSame('warehouse@example.test', $xpath->evaluate('string(//input[@name="email"]/@value)'));
        $this->assertSame('', $xpath->evaluate('string(//input[@name="password"]/@value)'));
        $this->assertSame(1, $xpath->query('//*[@role="alert"]')->length);
    }

    public function test_authenticated_users_keep_the_existing_dashboard_redirect(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('login'))->assertRedirect(route('dashboard'));
    }

    private function html(string $content): DOMXPath
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$content);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }
}
