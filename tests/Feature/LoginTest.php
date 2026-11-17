<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\Auth\AturPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\LupaPassword;
use App\Livewire\Warga\Profil;
use App\Models\User;
use App\Notifications\TautanPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Login email + password (PRD bagian 2) dan password awal yang wajib diganti (bagian 15 R2).
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

    private function cookieIngat(): ?\Symfony\Component\HttpFoundation\Cookie
    {
        return collect(Cookie::getQueuedCookies())->first(fn ($c) => str_starts_with($c->getName(), 'remember_web_'));
    }

    // ---------- Login ----------

    public function test_tamu_diarahkan_ke_halaman_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('login'))->assertOk()->assertSee('Masuk ke')->assertSee('Ingat saya')->assertSee('Lupa password?');
    }

    public function test_link_pribadi_pin_dan_email_buat_password_sudah_dihapus(): void
    {
        $this->get('/m/'.str_repeat('a', 64))->assertNotFound();
        $this->get('/pin')->assertNotFound();
        $this->get('/password/buat/abc')->assertNotFound();
        $this->assertFalse(Schema::hasColumns('users', ['token_hash']));
        $this->assertFalse(Schema::hasColumns('users', ['pin_hash']));
    }

    public function test_warga_login_masuk_ke_beranda_warga(): void
    {
        $this->akun();

        Livewire::test(Login::class)
            ->set('email', ' Ping@AMKT.test ')->set('password', 'password')
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

    public function test_akun_tanpa_password_tidak_bisa_login(): void
    {
        User::factory()->tanpaPassword()->create(['email' => 'ping@amkt.test']);

        Livewire::test(Login::class)->set('email', 'ping@amkt.test')->set('password', 'apa saja')->call('masuk')->assertHasErrors('email');
        $this->assertGuest();
    }

    public function test_ingat_saya_membuat_cookie_30_hari(): void
    {
        $this->akun();

        Livewire::test(Login::class)->set('email', 'ping@amkt.test')->set('password', 'password')->set('ingat', true)->call('masuk');

        $this->assertNotNull($this->cookieIngat(), 'Cookie "ingat saya" harus dibuat.');
        $this->assertEqualsWithDelta(now()->addDays(30)->getTimestamp(), $this->cookieIngat()->getExpiresTime(), 120);
    }

    public function test_tanpa_ingat_saya_tidak_ada_cookie_panjang(): void
    {
        $this->akun();

        Livewire::test(Login::class)->set('email', 'ping@amkt.test')->set('password', 'password')->call('masuk');

        $this->assertNull($this->cookieIngat());
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

    // ---------- R2: password awal wajib diganti ----------

    public function test_login_dengan_password_awal_diarahkan_ke_buat_password_baru(): void
    {
        $this->akun(data: ['harus_ganti_password' => true]);

        Livewire::test(Login::class)->set('email', 'ping@amkt.test')->set('password', 'password')
            ->call('masuk')->assertRedirect(route('password.baru'));
    }

    public function test_selama_belum_ganti_semua_halaman_diarahkan_ke_buat_password_baru(): void
    {
        $this->actingAs($this->akun(Role::TataTertib, ['harus_ganti_password' => true]));

        foreach (['dashboard', 'warga.piket', 'warga.profil', 'pengurus.pengawasan', 'pengurus.warga.index'] as $rute) {
            $this->get(route($rute))->assertRedirect(route('password.baru'));
        }
        $this->get('/')->assertRedirect(route('password.baru'));
        $this->get(route('password.baru'))->assertOk()->assertSee('Buat password baru');

        // Aksi Livewire (tanpa reload) juga diblokir.
        $this->withHeader('X-Livewire', 'true')->post('/livewire/update', [])->assertForbidden();

        // Keluar tetap boleh.
        $this->post(route('keluar'))->assertRedirect(route('login'));
    }

    public function test_password_baru_minimal_8_dan_tidak_boleh_sama_dengan_password_awal(): void
    {
        $user = $this->akun(data: ['harus_ganti_password' => true]);
        $this->actingAs($user);

        $this->post(route('password.baru.simpan'), ['password' => 'pendek', 'password_confirmation' => 'pendek'])->assertSessionHasErrors('password');
        $this->post(route('password.baru.simpan'), ['password' => 'rahasia123', 'password_confirmation' => 'beda12345'])->assertSessionHasErrors('password');
        $this->post(route('password.baru.simpan'), ['password' => 'password', 'password_confirmation' => 'password'])
            ->assertSessionHasErrors(['password' => 'Password baru tidak boleh sama dengan password awal.']);
        $this->assertTrue($user->fresh()->harus_ganti_password);

        $this->post(route('password.baru.simpan'), ['password' => 'rahasia123', 'password_confirmation' => 'rahasia123'])
            ->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertFalse($user->harus_ganti_password);
        $this->assertTrue(Hash::check('rahasia123', $user->password));
        $this->get(route('dashboard'))->assertOk();
        $this->get(route('password.baru'))->assertRedirect(route('dashboard'));
    }

    public function test_pengurus_setelah_ganti_password_masuk_ke_panel(): void
    {
        $this->actingAs($this->akun(Role::Ketua, ['harus_ganti_password' => true]));

        $this->post(route('password.baru.simpan'), ['password' => 'rahasia123', 'password_confirmation' => 'rahasia123'])
            ->assertRedirect(route('pengurus.pengawasan'));
    }

    public function test_ganti_password_di_profil(): void
    {
        $user = $this->akun();
        $this->actingAs($user);
        $this->get(route('warga.profil'))->assertSee('Ganti password');

        Livewire::test(Profil::class)
            ->set('password_lama', 'salah')->set('password_baru', 'rahasia123')->set('password_baru_confirmation', 'rahasia123')
            ->call('gantiPassword')->assertHasErrors(['password_lama' => 'current_password']);

        Livewire::test(Profil::class)
            ->set('password_lama', 'password')->set('password_baru', 'password')->set('password_baru_confirmation', 'password')
            ->call('gantiPassword')->assertHasErrors('password_baru');

        Livewire::test(Profil::class)
            ->set('password_lama', 'password')->set('password_baru', 'rahasia123')->set('password_baru_confirmation', 'rahasia123')
            ->call('gantiPassword')->assertHasNoErrors()->assertDispatched('toast')->assertSet('password_lama', '');

        $this->assertTrue(Hash::check('rahasia123', $user->fresh()->password));
    }

    // ---------- Lupa password (email) ----------

    public function test_lupa_password_mengirim_email_reset_tanpa_membocorkan_email_terdaftar(): void
    {
        Notification::fake();
        $user = $this->akun();

        Livewire::test(LupaPassword::class)->set('email', 'ping@amkt.test')->call('kirim')->assertSet('terkirim', true);
        Notification::assertSentTo($user, TautanPassword::class, fn (TautanPassword $n) => str_contains($n->url($user), '/password/reset/'));

        Livewire::test(LupaPassword::class)->set('email', 'tidakada@amkt.test')->call('kirim')->assertSet('terkirim', true);
        Notification::assertSentTimes(TautanPassword::class, 1);
    }

    public function test_reset_lewat_email_mematikan_wajib_ganti_lalu_masuk_sesuai_role(): void
    {
        $user = $this->akun(Role::TataTertib, ['harus_ganti_password' => true]);
        $token = Password::broker()->createToken($user);

        $this->get(route('password.reset', ['token' => $token, 'email' => 'ping@amkt.test']))->assertOk()->assertSee('Buat password baru');

        Livewire::withQueryParams(['email' => 'ping@amkt.test'])
            ->test(AturPassword::class, ['token' => $token])
            ->set('password', 'rahasia-baru')->set('password_confirmation', 'rahasia-baru')
            ->call('simpan')
            ->assertHasNoErrors()
            ->assertRedirect(route('pengurus.pengawasan'));

        $user->refresh();
        $this->assertTrue(Hash::check('rahasia-baru', $user->password));
        $this->assertFalse($user->harus_ganti_password);
        $this->assertAuthenticatedAs($user);
    }

    public function test_link_reset_kedaluwarsa_setelah_60_menit_dan_token_salah_ditolak(): void
    {
        $user = $this->akun();
        $token = Password::broker()->createToken($user);

        $this->travel(61)->minutes();
        Livewire::withQueryParams(['email' => 'ping@amkt.test'])
            ->test(AturPassword::class, ['token' => $token])
            ->set('password', 'rahasia123')->set('password_confirmation', 'rahasia123')
            ->call('simpan')->assertHasErrors('email');

        Livewire::withQueryParams(['email' => 'ping@amkt.test'])
            ->test(AturPassword::class, ['token' => 'token-palsu'])
            ->set('password', 'rahasia123')->set('password_confirmation', 'rahasia123')
            ->call('simpan')->assertHasErrors('email');
        $this->assertGuest();
    }

    public function test_email_reset_berbahasa_indonesia(): void
    {
        $user = User::factory()->create(['nama' => 'Ping', 'email' => 'ping@amkt.test']);
        $pesan = (new TautanPassword('abc'))->toMail($user);

        $this->assertSame('Reset password web piket AMKT Putri', $pesan->subject);
        $this->assertSame('Halo, Ping', $pesan->greeting);
        $this->assertStringContainsString('/password/reset/abc?email=ping%40amkt.test', $pesan->actionUrl);
        $this->assertStringContainsString('60 menit', implode(' ', $pesan->outroLines));
    }
}
