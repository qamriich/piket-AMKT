<?php

namespace Tests\Feature;

use App\Livewire\Pengurus\WargaIndex;
use App\Mail\LinkPribadiMail;
use App\Models\User;
use App\Services\LinkPribadi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class LinkPribadiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buatGeng();
    }

    private function tokenDari(string $url): string
    {
        return basename(parse_url($url, PHP_URL_PATH));
    }

    public function test_token_64_karakter_dan_hanya_hash_yang_disimpan(): void
    {
        $user = User::factory()->create();
        $token = $user->buatTokenBaru();

        $this->assertSame(64, strlen($token));
        $this->assertSame(hash('sha256', $token), $user->fresh()->token_hash);
        $this->assertDatabaseMissing('users', ['token_hash' => $token]);
    }

    public function test_membuka_link_membuat_sesi_dengan_cookie_ingat_saya_lalu_ke_dashboard(): void
    {
        $user = User::factory()->create();
        $token = $user->buatTokenBaru();

        $response = $this->get("/m/{$token}");

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        $cookie = collect($response->headers->getCookies())
            ->first(fn ($c) => str_starts_with($c->getName(), 'remember_web_'));
        $this->assertNotNull($cookie, 'Cookie ingat saya harus dibuat.');
        $this->assertGreaterThan(now()->addDays(360)->getTimestamp(), $cookie->getExpiresTime());
        $this->assertLessThan(now()->addDays(370)->getTimestamp(), $cookie->getExpiresTime());

        $this->get(route('dashboard'))->assertOk()->assertSee('Halo, ', false)->assertSee($user->namaDepan());
    }

    public function test_link_salah_ditolak(): void
    {
        $this->get('/m/'.str_repeat('a', 64))->assertNotFound()->assertSee('Link tidak berlaku');
        $this->get('/m/pendek')->assertNotFound();
        $this->assertGuest();
    }

    public function test_link_warga_nonaktif_ditolak(): void
    {
        $user = User::factory()->nonaktif()->create();
        $token = $user->buatTokenBaru();

        $this->get("/m/{$token}")->assertNotFound();
        $this->assertGuest();
    }

    public function test_kirim_ulang_membuat_token_baru_dan_token_lama_tidak_berlaku(): void
    {
        Mail::fake();
        $warga = User::factory()->create();
        $tokenLama = $warga->buatTokenBaru();

        $this->masukSebagaiPengurus();
        Livewire::test(WargaIndex::class)->call('kirimLink', $warga->id)->assertDispatched('toast');

        $urlBaru = null;
        Mail::assertQueued(LinkPribadiMail::class, function (LinkPribadiMail $mail) use ($warga, &$urlBaru) {
            $urlBaru = $mail->url;

            return $mail->hasTo($warga->email) && $mail->kirimUlang;
        });

        auth()->logout();
        $this->get("/m/{$tokenLama}")->assertNotFound();
        $this->get('/m/'.$this->tokenDari($urlBaru))->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($warga);
    }

    public function test_kirim_ulang_memutus_cookie_ingat_saya_lama(): void
    {
        $user = User::factory()->create();
        $rememberLama = $user->remember_token;

        app(LinkPribadi::class)->buat($user);

        $this->assertNotSame($rememberLama, $user->fresh()->remember_token);
    }

    public function test_email_link_pribadi_berbahasa_indonesia_berisi_link(): void
    {
        $user = User::factory()->create(['nama' => 'Gita Permata']);
        $mail = new LinkPribadiMail($user, 'http://localhost/m/abc', false);

        $mail->assertHasSubject('Link pribadi jadwal piket AMKT Putri');
        $mail->assertSeeInHtml('Halo, Gita Permata');
        $mail->assertSeeInHtml('http://localhost/m/abc');
        $mail->assertSeeInHtml('jangan bagikan link ini');
    }

    public function test_warga_yang_dinonaktifkan_langsung_keluar(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $user->update(['aktif' => false]);

        $this->get(route('dashboard'))->assertRedirect(route('beranda'));
        $this->assertGuest();
    }

    public function test_tamu_diarahkan_ke_beranda(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('beranda'));
        $this->get('/')->assertOk()->assertSee('link pribadi');
    }

    public function test_keluar(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('keluar'))->assertRedirect(route('beranda'));
        $this->assertGuest();
    }
}
