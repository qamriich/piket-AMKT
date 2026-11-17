<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\Auth\AturPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\LupaPassword;
use App\Models\User;
use App\Notifications\TautanPassword;
use App\Services\AkunPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Login email + password (PRD bagian 2, revisi 17 Nov 2026).
 */
class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buatGeng();
    }

    private function akun(Role $role = Role::Warga, array $data = []): User
    {
        return User::factory()->role($role)->create(array_merge(['email' => 'ping@amkt.test'], $data));
    }

    public function test_tamu_diarahkan_ke_halaman_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('login'))->assertOk()->assertSee('Masuk ke')->assertSee('Ingat saya')->assertSee('Lupa password?');
    }

    public function test_route_link_pribadi_dan_pin_sudah_dihapus(): void
    {
        $this->get('/m/'.str_repeat('a', 64))->assertNotFound();
        $this->get('/pin')->assertNotFound();
        $this->assertFalse(Schema::hasColumns('users', ['token_hash']));
        $this->assertFalse(Schema::hasColumns('users', ['pin_hash']));
    }

    public function test_warga_login_masuk_ke_beranda_warga(): void
    {
        $this->akun();

        Livewire::test(Login::class)
            ->set('email', ' Ping@AMKT.test ')
            ->set('password', 'password')
            ->call('masuk')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
    }

    public function test_pengurus_login_masuk_ke_panel_pengurus(): void
    {
        foreach ([Role::Ketua, Role::Sekretaris, Role::Bendahara, Role::TataTertib] as $i => $role) {
            $this->akun($role, ['email' => "pengurus{$i}@amkt.test"]);

            Livewire::test(Login::class)
                ->set('email', "pengurus{$i}@amkt.test")->set('password', 'password')
                ->call('masuk')
                ->assertRedirect(route('pengurus.pengawasan'));

            auth()->logout();
        }
    }

    public function test_yang_sudah_login_membuka_login_atau_beranda_diarahkan_sesuai_role(): void
    {
        $this->actingAs($this->akun(Role::TataTertib));
        $this->get(route('login'))->assertRedirect(route('pengurus.pengawasan'));
        $this->get('/')->assertRedirect(route('pengurus.pengawasan'));
    }

    public function test_password_salah_ditolak(): void
    {
        $this->akun();

        Livewire::test(Login::class)->set('email', 'ping@amkt.test')->set('password', 'salah')
            ->call('masuk')->assertHasErrors('email')->assertSet('password', '');
        $this->assertGuest();
    }

    public function test_dibatasi_5_percobaan_gagal_per_menit(): void
    {
        $this->akun();

        for ($i = 0; $i < 5; $i++) {
            Livewire::test(Login::class)->set('email', 'ping@amkt.test')->set('password', 'salah')->call('masuk');
        }

        // Percobaan ke-6 ditolak walau password benar.
        Livewire::test(Login::class)->set('email', 'ping@amkt.test')->set('password', 'password')
            ->call('masuk')->assertHasErrors('email')->assertSee('Terlalu banyak percobaan');
        $this->assertGuest();

        $this->travel(61)->seconds();
        Livewire::test(Login::class)->set('email', 'ping@amkt.test')->set('password', 'password')->call('masuk')->assertHasNoErrors();
        $this->assertAuthenticated();
    }

    public function test_warga_nonaktif_tidak_bisa_login(): void
    {
        $this->akun(data: ['aktif' => false]);

        Livewire::test(Login::class)->set('email', 'ping@amkt.test')->set('password', 'password')->call('masuk')->assertHasErrors('email');
        $this->assertGuest();
    }

    public function test_akun_yang_belum_membuat_password_tidak_bisa_login(): void
    {
        User::factory()->tanpaPassword()->create(['email' => 'ping@amkt.test']);

        Livewire::test(Login::class)->set('email', 'ping@amkt.test')->set('password', '')->call('masuk')->assertHasErrors('password');
        Livewire::test(Login::class)->set('email', 'ping@amkt.test')->set('password', 'apa saja')->call('masuk')->assertHasErrors('email');
        $this->assertGuest();
    }

    public function test_ingat_saya_membuat_cookie_30_hari(): void
    {
        $this->akun();

        Livewire::test(Login::class)->set('email', 'ping@amkt.test')->set('password', 'password')->set('ingat', true)->call('masuk');

        $cookie = collect(Cookie::getQueuedCookies())
            ->first(fn ($c) => str_starts_with($c->getName(), 'remember_web_'));
        $this->assertNotNull($cookie, 'Cookie "ingat saya" harus dibuat.');
        $this->assertEqualsWithDelta(now()->addDays(30)->getTimestamp(), $cookie->getExpiresTime(), 120);
        $this->assertNotNull(auth()->user()->fresh()->remember_token);
    }

    public function test_tanpa_ingat_saya_tidak_ada_cookie_panjang(): void
    {
        $this->akun();

        Livewire::test(Login::class)->set('email', 'ping@amkt.test')->set('password', 'password')->call('masuk');

        $this->assertNull(collect(Cookie::getQueuedCookies())
            ->first(fn ($c) => str_starts_with($c->getName(), 'remember_web_')));
    }

    public function test_warga_yang_dinonaktifkan_langsung_keluar(): void
    {
        $user = $this->akun();
        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $user->update(['aktif' => false]);

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_keluar(): void
    {
        $this->actingAs($this->akun());

        $this->post(route('keluar'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_lupa_password_mengirim_email_reset_tanpa_membocorkan_email_terdaftar(): void
    {
        Notification::fake();
        $user = $this->akun();

        Livewire::test(LupaPassword::class)->set('email', 'ping@amkt.test')->call('kirim')->assertSet('terkirim', true);
        Notification::assertSentTo($user, TautanPassword::class, fn (TautanPassword $n) => $n->jenis === TautanPassword::RESET
            && str_contains($n->url($user), '/password/reset/'));

        // Email tidak terdaftar: jawabannya sama, tidak ada email terkirim.
        Livewire::test(LupaPassword::class)->set('email', 'tidakada@amkt.test')->call('kirim')->assertSet('terkirim', true);
        Notification::assertSentTimes(TautanPassword::class, 1);
    }

    public function test_reset_password_lalu_langsung_masuk_sesuai_role(): void
    {
        $user = $this->akun(Role::TataTertib);
        $token = Password::broker()->createToken($user);

        $this->get(route('password.reset', ['token' => $token, 'email' => 'ping@amkt.test']))->assertOk()->assertSee('Buat password baru');

        Livewire::withQueryParams(['email' => 'ping@amkt.test'])
            ->test(AturPassword::class, ['token' => $token])
            ->set('password', 'rahasia-baru')
            ->set('password_confirmation', 'rahasia-baru')
            ->call('simpan')
            ->assertHasNoErrors()
            ->assertRedirect(route('pengurus.pengawasan'));

        $this->assertTrue(Hash::check('rahasia-baru', $user->fresh()->password));
        $this->assertAuthenticatedAs($user);
    }

    public function test_link_buat_password_dari_email_masih_bisa_dipakai_di_hari_ke_6(): void
    {
        Notification::fake();
        $user = User::factory()->tanpaPassword()->create(['email' => 'ping@amkt.test']);
        app(AkunPassword::class)->kirimBuatPassword($user);

        $token = null;
        Notification::assertSentTo($user, TautanPassword::class, function (TautanPassword $n) use (&$token) {
            $token = $n->token;

            return $n->jenis === TautanPassword::BUAT;
        });

        // Link reset biasa (60 menit) sudah lewat, tapi link buat password masih berlaku.
        $this->travel(6)->days();
        Livewire::withQueryParams(['email' => 'ping@amkt.test'])
            ->test(AturPassword::class, ['token' => $token, 'jenis' => 'buat'])
            ->assertSet('jenis', 'buat')
            ->set('password', 'rahasia123')->set('password_confirmation', 'rahasia123')
            ->call('simpan')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertTrue(Hash::check('rahasia123', $user->password));
        $this->assertNotNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
    }

    public function test_halaman_buat_password_lewat_route_dan_kedaluwarsa_setelah_7_hari(): void
    {
        $user = User::factory()->tanpaPassword()->create(['email' => 'ping@amkt.test']);
        $url = app(AkunPassword::class)->linkBuatPassword($user);
        $this->assertStringContainsString('/password/buat/', $url);

        $this->get($url)->assertOk()->assertSee('Buat password');

        // Hari ke-6: masih berlaku.
        $this->travel(6)->days();
        $this->assertTrue(Password::broker('buat')->tokenExists($user, basename(parse_url($url, PHP_URL_PATH))));
        $this->assertFalse(Password::broker('users')->tokenExists($user, basename(parse_url($url, PHP_URL_PATH))));

        // Hari ke-8: kedaluwarsa.
        $this->travel(2)->days();
        $this->assertFalse(Password::broker('buat')->tokenExists($user, basename(parse_url($url, PHP_URL_PATH))));
    }

    public function test_ulangi_password_harus_sama(): void
    {
        $user = User::factory()->tanpaPassword()->create(['email' => 'ping@amkt.test']);
        $url = app(AkunPassword::class)->linkBuatPassword($user);
        $token = basename(parse_url($url, PHP_URL_PATH));

        $this->get($url)->assertOk();
        Livewire::withQueryParams(['email' => 'ping@amkt.test'])
            ->test(AturPassword::class, ['token' => $token, 'jenis' => 'buat'])
            ->set('password', 'rahasia123')
            ->set('password_confirmation', 'beda')
            ->call('simpan')
            ->assertHasErrors('password');

        $this->assertFalse($user->fresh()->punyaPassword());
    }

    public function test_token_salah_ditolak(): void
    {
        $this->akun();

        Livewire::withQueryParams(['email' => 'ping@amkt.test'])
            ->test(AturPassword::class, ['token' => 'token-palsu'])
            ->set('password', 'rahasia123')->set('password_confirmation', 'rahasia123')
            ->call('simpan')
            ->assertHasErrors('email');
        $this->assertGuest();
    }

    public function test_email_buat_password_berbahasa_indonesia(): void
    {
        $user = User::factory()->tanpaPassword()->create(['nama' => 'Ping', 'email' => 'ping@amkt.test']);
        $pesan = (new TautanPassword('abc', TautanPassword::BUAT))->toMail($user);

        $this->assertSame('Buat password untuk web piket AMKT Putri', $pesan->subject);
        $this->assertSame('Halo, Ping', $pesan->greeting);
        $this->assertSame('Buat password', $pesan->actionText);
        $this->assertStringContainsString('/password/buat/abc?email=ping%40amkt.test', $pesan->actionUrl);
        $this->assertStringContainsString('7 hari', implode(' ', $pesan->outroLines));
    }
}
