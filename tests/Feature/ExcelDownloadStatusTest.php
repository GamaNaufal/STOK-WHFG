<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ExcelDownloadStatusTest extends TestCase
{
    private string $token = '1234567890abcdef1234567890abcdef';

    protected function setUp(): void
    {
        parent::setUp();
        Route::middleware(['web', 'auth'])->match(['get', 'post'], '/__test/excel-status', fn () => response('test-workbook', 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="filtered-report.xlsx"',
        ]));
        Route::middleware('web')->match(['get', 'post'], '/__test/excel-not-file', fn () => response()->json(['message' => 'No file'], 422));
        Route::middleware('web')->get('/__test/excel-html', fn () => response('<p>Login</p>'));
        Route::middleware('web')->get('/__test/pdf-status', fn () => response('pdf', 200, [
            'Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="test.pdf"',
        ]));
    }

    public function test_attachment_acknowledgement_survives_web_cookie_encryption_and_preserves_file(): void
    {
        $this->actingAs(new User(['name' => 'Test', 'role' => 'admin', 'is_active' => true]));
        $response = $this->get('/__test/excel-status?_excel_token='.$this->token);
        $response->assertOk()->assertContent('test-workbook')->assertHeader('Content-Disposition', 'attachment; filename="filtered-report.xlsx"');
        $cookie = collect($response->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === 'whfg_excel_'.$this->token);
        $this->assertNotNull($cookie);
        $this->assertSame('ready', $cookie->getValue());
        $this->assertFalse($cookie->isHttpOnly());
        $this->assertSame('/', $cookie->getPath());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertGreaterThan(time(), $cookie->getExpiresTime());
    }

    public function test_authentication_redirect_never_acknowledges_a_file(): void
    {
        $response = $this->get('/__test/excel-status?_excel_token='.$this->token);
        $response->assertRedirect(route('login'));
        $this->assertNoAcknowledgement($response);
    }

    public function test_normal_download_and_invalid_tokens_do_not_add_acknowledgement(): void
    {
        $this->actingAs(new User(['role' => 'admin', 'is_active' => true]));
        foreach (['', '?_excel_token=short', '?_excel_token[]=bad', '?_excel_token='.str_repeat('z', 32)] as $query) {
            $response = $this->get('/__test/excel-status'.$query);
            $response->assertOk()->assertContent('test-workbook');
            $this->assertNoAcknowledgement($response);
        }
    }

    public function test_errors_html_and_other_file_formats_never_acknowledge_excel(): void
    {
        foreach (['/__test/excel-not-file', '/__test/excel-html', '/__test/pdf-status'] as $url) {
            $this->assertNoAcknowledgement($this->get($url.'?_excel_token='.$this->token));
        }
        $this->assertNoAcknowledgement($this->post('/__test/excel-not-file?_excel_token='.$this->token));
    }

    public function test_https_acknowledgement_is_secure(): void
    {
        $this->actingAs(new User(['role' => 'admin', 'is_active' => true]));
        $response = $this->get('https://localhost/__test/excel-status?_excel_token='.$this->token);
        $cookie = collect($response->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === 'whfg_excel_'.$this->token);
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isSecure());
    }

    public function test_non_get_file_response_does_not_acknowledge(): void
    {
        $this->actingAs(new User(['role' => 'admin', 'is_active' => true]));
        $response = $this->post('/__test/excel-status?_excel_token='.$this->token);
        $response->assertOk()->assertContent('test-workbook');
        $this->assertNoAcknowledgement($response);
    }

    private function assertNoAcknowledgement($response): void
    {
        $this->assertFalse(collect($response->headers->getCookies())->contains(fn ($cookie) => str_starts_with($cookie->getName(), 'whfg_excel_')));
    }
}
