<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\Pengurus\WargaForm;
use App\Livewire\Pengurus\WargaIndex;
use App\Models\LogAktivitas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class KelolaWargaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buatGeng();
        Notification::fake();
    }

    private function isiForm($komponen, array $timpa = [])
    {
        $data = array_merge([
            'nama' => 'Gita Permata',
            'email' => 'Gita@Contoh.test',
            'no_kamar' => '5',
            'geng_id' => $this->geng(2)->id,
            'role' => 'warga',
            'wajib_diperiksa' => true,
            'password_awal' => 'awalgita123',
        ], $timpa);

        foreach ($data as $k => $v) {
            $komponen->set($k, $v);
        }

        return $komponen;
    }

    public function test_tambah_warga_dengan_password_awal_yang_wajib_diganti(): void
    {
        $this->masukSebagaiPengurus();

        $this->isiForm(Livewire::test(WargaForm::class))
            ->call('simpan')
            ->assertHasNoErrors()
            ->assertRedirect(route('pengurus.warga.index'));

        $warga = User::query()->where('email', 'gita@contoh.test')->firstOrFail();
        $this->assertSame('Gita Permata', $warga->nama);
        $this->assertSame($this->geng(2)->id, $warga->geng_id);
        $this->assertTrue($warga->wajib_diperiksa);
        $this->assertTrue($warga->aktif);
        $this->assertTrue(Hash::check('awalgita123', $warga->password));
        $this->assertTrue($warga->harus_ganti_password);
        $this->assertTrue(LogAktivitas::query()->where('aksi', 'warga.dibuat')->exists());
        Notification::assertNothingSent();
    }

    public function test_password_awal_wajib_minimal_8_karakter_dan_bisa_dibuat_acak(): void
    {
        $this->masukSebagaiPengurus();

        $this->isiForm(Livewire::test(WargaForm::class), ['password_awal' => ''])->call('simpan')->assertHasErrors('password_awal');
        $this->isiForm(Livewire::test(WargaForm::class), ['password_awal' => 'pendek'])->call('simpan')->assertHasErrors('password_awal');
        $this->assertDatabaseMissing('users', ['email' => 'gita@contoh.test']);

        $c = Livewire::test(WargaForm::class)->call('passwordAcak');
        $this->assertMatchesRegularExpression('/^[a-z]{6}[2-9]{3}$/', $c->get('password_awal'));
    }

    public function test_reset_password_oleh_pengurus(): void
    {
        $this->masukSebagaiPengurus();
        $warga = User::factory()->create(['geng_id' => $this->geng(2)->id, 'harus_ganti_password' => false]);
        $tokenIngatLama = $warga->remember_token;

        $this->get(route('pengurus.warga.index'))->assertSee('Reset password');
        Livewire::test(WargaIndex::class)->call('resetPassword', $warga->id, 'pendek')
            ->assertDispatched('toast', fn ($n, $p) => $p['jenis'] === 'gagal');
        $this->assertFalse($warga->fresh()->harus_ganti_password);

        Livewire::test(WargaIndex::class)->call('resetPassword', $warga->id, ' awalbaru99 ')
            ->assertDispatched('toast', fn ($n, $p) => $p['jenis'] === 'sukses');

        $warga->refresh();
        $this->assertTrue(Hash::check('awalbaru99', $warga->password));
        $this->assertTrue($warga->harus_ganti_password);
        $this->assertNotSame($tokenIngatLama, $warga->remember_token);
        $this->assertTrue(LogAktivitas::query()->where('aksi', 'warga.reset_password')->exists());
        $this->get(route('pengurus.warga.index'))->assertSee('Belum ganti password awal');
    }

    public function test_pengurus_tidak_bisa_reset_password_sendiri_dari_warga_dan_geng(): void
    {
        $saya = $this->masukSebagaiPengurus();

        Livewire::test(WargaIndex::class)->call('resetPassword', $saya->id, 'awalbaru99')
            ->assertDispatched('toast', fn ($n, $p) => $p['jenis'] === 'gagal');
        $this->assertFalse($saya->fresh()->harus_ganti_password);
    }

    public function test_validasi_email_unik_dan_role_valid(): void
    {
        $this->masukSebagaiPengurus();
        User::factory()->create(['email' => 'gita@contoh.test']);

        $this->isiForm(Livewire::test(WargaForm::class))->call('simpan')->assertHasErrors('email');
        $this->isiForm(Livewire::test(WargaForm::class), ['email' => 'lain@contoh.test', 'role' => 'presiden'])
            ->call('simpan')->assertHasErrors('role');
        $this->isiForm(Livewire::test(WargaForm::class), ['email' => 'lain@contoh.test', 'geng_id' => ''])
            ->call('simpan')->assertHasErrors('geng_id');

        $this->assertDatabaseMissing('users', ['email' => 'lain@contoh.test']);
    }

    public function test_ubah_warga_termasuk_role_dan_wajib_diperiksa(): void
    {
        $this->masukSebagaiPengurus();
        $warga = User::factory()->create(['geng_id' => $this->geng(1)->id, 'wajib_diperiksa' => true]);

        $this->isiForm(Livewire::test(WargaForm::class, ['warga' => $warga]), [
            'email' => $warga->email,
            'role' => 'bendahara',
            'wajib_diperiksa' => false,
        ])->call('simpan')->assertHasNoErrors()->assertRedirect(route('pengurus.warga.index'));

        $warga->refresh();
        $this->assertSame(Role::Bendahara, $warga->role);
        $this->assertFalse($warga->wajib_diperiksa);
        $this->assertSame($this->geng(2)->id, $warga->geng_id);
        $this->assertTrue(LogAktivitas::query()->where('aksi', 'warga.diubah')->exists());
    }

    public function test_pindah_geng(): void
    {
        $this->masukSebagaiPengurus();
        $warga = User::factory()->create(['geng_id' => $this->geng(1)->id]);

        Livewire::test(WargaIndex::class)
            ->call('pindahGeng', $warga->id, $this->geng(3)->id)
            ->assertDispatched('toast');

        $this->assertSame($this->geng(3)->id, $warga->fresh()->geng_id);
        $this->assertTrue(LogAktivitas::query()->where('aksi', 'warga.pindah_geng')->exists());
    }

    public function test_nonaktifkan_dan_aktifkan_kembali_riwayat_tetap_ada(): void
    {
        $this->masukSebagaiPengurus();
        $warga = User::factory()->create(['geng_id' => $this->geng(1)->id]);

        Livewire::test(WargaIndex::class)->call('ubahAktif', $warga->id);
        $this->assertFalse($warga->fresh()->aktif);
        $this->assertDatabaseHas('users', ['id' => $warga->id]);
        $this->assertNotContains($warga->id, User::aktif()->pluck('id'));

        Livewire::test(WargaIndex::class)->call('ubahAktif', $warga->id);
        $this->assertTrue($warga->fresh()->aktif);
    }

    public function test_pengurus_tidak_bisa_menonaktifkan_atau_menurunkan_diri_sendiri(): void
    {
        $saya = $this->masukSebagaiPengurus();
        $saya->update(['geng_id' => $this->geng(1)->id]);

        Livewire::test(WargaIndex::class)->call('ubahAktif', $saya->id)
            ->assertDispatched('toast', fn ($n, $p) => $p['jenis'] === 'gagal');
        $this->assertTrue($saya->fresh()->aktif);

        $this->isiForm(Livewire::test(WargaForm::class, ['warga' => $saya]), ['email' => $saya->email, 'role' => 'warga'])
            ->call('simpan')
            ->assertNoRedirect()
            ->assertDispatched('toast', fn ($n, $p) => $p['jenis'] === 'gagal');
        $this->assertSame(Role::TataTertib, $saya->fresh()->role);
    }

    public function test_halaman_daftar_dan_form_tampil(): void
    {
        $this->masukSebagaiPengurus();
        $warga = User::factory()->create(['nama' => "Nur'aini", 'geng_id' => $this->geng(2)->id, 'wajib_diperiksa' => true]);

        $this->get(route('pengurus.warga.index'))->assertOk()->assertSee("Nur'aini")->assertSee('Wajib diperiksa');
        $this->get(route('pengurus.warga.create'))->assertOk()->assertSee('Tambah warga');
        $this->get(route('pengurus.warga.edit', $warga))->assertOk()->assertSee('Jadwal kuliah');
    }

    public function test_cari_warga(): void
    {
        $this->masukSebagaiPengurus();
        User::factory()->create(['nama' => 'Gita Permata', 'geng_id' => $this->geng(2)->id]);
        User::factory()->create(['nama' => 'Dewi Kartika', 'geng_id' => $this->geng(2)->id]);

        Livewire::test(WargaIndex::class)->set('cari', 'gita')->assertSee('Gita Permata')->assertDontSee('Dewi Kartika');
    }

    public function test_label_wajib_diperiksa_tidak_tampil_di_halaman_warga(): void
    {
        $warga = User::factory()->create(['geng_id' => $this->geng(2)->id, 'wajib_diperiksa' => true]);
        $this->actingAs($warga);

        foreach (['dashboard', 'warga.piket', 'warga.utang', 'warga.jadwal', 'warga.kuliah', 'warga.profil'] as $rute) {
            $this->get(route($rute))->assertOk()->assertDontSee('Wajib diperiksa');
        }
    }

    public function test_create_admin_lewat_artisan(): void
    {
        $this->artisan('app:create-admin', [
            '--nama' => 'Rina Anggraini',
            '--email' => 'rina@contoh.test',
            '--geng' => 1,
            '--password' => 'awalrina99',
        ])->expectsOutputToContain('Password awal: awalrina99')->assertSuccessful();

        $admin = User::query()->where('email', 'rina@contoh.test')->firstOrFail();
        $this->assertSame(Role::TataTertib, $admin->role);
        $this->assertSame($this->geng(1)->id, $admin->geng_id);
        $this->assertTrue(Hash::check('awalrina99', $admin->password));
        $this->assertTrue($admin->harus_ganti_password);
    }

    public function test_create_admin_tanpa_password_membuat_password_acak(): void
    {
        $this->artisan('app:create-admin', ['--nama' => 'Rina', '--email' => 'rina@contoh.test', '--geng' => 1])
            ->expectsOutputToContain('Password awal:')->assertSuccessful();

        $this->assertTrue(User::query()->where('email', 'rina@contoh.test')->value('harus_ganti_password'));
    }

    public function test_create_admin_menolak_email_ganda(): void
    {
        User::factory()->create(['email' => 'rina@contoh.test']);

        $this->artisan('app:create-admin', ['--nama' => 'Rina', '--email' => 'rina@contoh.test', '--geng' => 1])
            ->assertFailed();
    }
}
