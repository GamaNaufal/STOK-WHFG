<?php

namespace Tests\Feature;

use Tests\TestCase;

class UiComponentFeedbackTest extends TestCase
{
    public function test_error_alert_preserves_slot_content_and_danger_semantics(): void
    {
        $this->blade('<x-alert type="danger" icon="bi-exclamation-triangle"><ul><li>Email tidak valid.</li></ul></x-alert>')
            ->assertSee('Email tidak valid.')
            ->assertSee('role="alert"', false)
            ->assertSee('color: #991b1b', false)
            ->assertSee('bi bi-exclamation-triangle', false);
    }

    public function test_success_alert_displays_slot_and_explicit_messages(): void
    {
        $this->blade('<x-alert type="success">Profil diperbarui.</x-alert>')
            ->assertSee('Profil diperbarui.')
            ->assertSee('role="status"', false);

        $this->blade('<x-alert message="0">Fallback</x-alert>')
            ->assertSee('0')
            ->assertDontSee('Fallback');
    }

    public function test_alert_escapes_explicit_message(): void
    {
        $this->blade('<x-alert :message="$message" />', ['message' => '<script>unsafe()</script>'])
            ->assertSee('&lt;script&gt;unsafe()&lt;/script&gt;', false)
            ->assertDontSee('<script>unsafe()</script>', false);
    }
}
