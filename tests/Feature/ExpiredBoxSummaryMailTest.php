<?php

namespace Tests\Feature;

use App\Mail\ExpiredBoxSummaryMail;
use App\Models\Box;
use App\Models\ExportStockBox;
use App\Models\PartSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExpiredBoxSummaryMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_summary_separates_local_and_export_warning_and_expired_boxes(): void
    {
        config(['mail.expired_box_summary_to' => null]);
        Mail::fake();
        $this->travelTo(now()->setDate(2026, 11, 10)->setTime(7, 0));
        $supervisor = User::factory()->create(['role' => 'supervisi']);
        $actor = User::factory()->create(['role' => 'admin_warehouse']);
        $part = PartSetting::create(['part_number' => 'EXPIRY-SUMMARY', 'qty_box' => 20]);
        foreach (['99210001' => 9, '99210002' => 12] as $number => $age) {
            Box::create(['box_number' => (string) $number, 'part_number' => $part->part_number, 'pcs_quantity' => 20,
                'qty_box' => 1, 'qr_code' => (string) $number, 'user_id' => $actor->id,
                'created_at' => now()->subMonthsNoOverflow($age), 'expired_status' => 'active']);
        }
        $this->exportBox('99220001', $part, $actor, 9);
        $this->exportBox('99220002', $part, $actor, 12);
        $this->exportBox('99220003', $part, $actor, 13, 'handling');

        $this->artisan('expired-box:send-summary')->assertSuccessful();

        Mail::assertSent(ExpiredBoxSummaryMail::class, function ($mail) use ($supervisor, $actor) {
            $this->assertSame(['99210001'], $mail->sections['Lokal']['warning']->pluck('box_number')->all());
            $this->assertSame(['99210002'], $mail->sections['Lokal']['expired']->pluck('box_number')->all());
            $this->assertSame(['99220001'], $mail->sections['Ekspor']['warning']->pluck('box_number')->all());
            $this->assertSame(['99220002'], $mail->sections['Ekspor']['expired']->pluck('box_number')->all());
            $html = $mail->render();
            $this->assertStringContainsString('<h2>Lokal</h2>', $html);
            $this->assertStringContainsString('<h2>Ekspor</h2>', $html);
            $this->assertStringNotContainsString('99220003', $html);

            return $mail->hasTo($supervisor->email) && ! $mail->hasTo($actor->email);
        });
        Mail::assertSentCount(1);
    }

    public function test_development_override_receives_export_only_summary_and_empty_reports_are_skipped(): void
    {
        Mail::fake();
        config(['mail.expired_box_summary_to' => 'development@example.test']);
        $supervisor = User::factory()->create(['role' => 'supervisi']);
        $this->artisan('expired-box:send-test')->assertSuccessful();
        Mail::assertNothingSent();

        $part = PartSetting::create(['part_number' => 'EXPIRY-ONLY-EXPORT', 'qty_box' => 20]);
        $this->exportBox('99220004', $part, $supervisor, 10);
        $this->artisan('expired-box:send-test')->assertSuccessful();

        Mail::assertSent(ExpiredBoxSummaryMail::class, fn ($mail) => $mail->hasTo('development@example.test')
            && ! $mail->hasTo($supervisor->email) && $mail->sections['Lokal']->isEmpty()
            && $mail->sections['Ekspor']['warning']->count() === 1);
    }

    private function exportBox(string $number, PartSetting $part, User $actor, int $age, string $state = 'received'): void
    {
        ExportStockBox::create(['box_number' => $number, 'part_setting_id' => $part->id, 'part_number' => $part->part_number,
            'pcs_quantity' => 20, 'state' => $state, 'first_received_at' => now()->subMonthsNoOverflow($age),
            'receive_token' => (string) Str::uuid(), 'received_by' => $actor->id]);
    }
}
