<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\Pengurus\WargaForm;
use App\Livewire\Pengurus\WargaIndex;
use App\Models\LogAktivitas;
use App\Models\User;
use App\Notifications\TautanPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'kirim_email' => true,
        ], $timpa);

        foreach ($data as $k => $v) {
            $komponen->set($k, $v);
        }

        return $komponen;
    }

    public function test_tambah_warga_dan_kirim_email_buat_password(): void
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
        $this->assertFalse($warga->punyaPassword());

        Notification::assertSentTo($warga, TautanPassword::class, fn (TautanPassword $n) => $n->jenis === TautanPassword::BUAT
            && str_contains($n->url($warga), '/password/buat/'));
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'gita@contoh.test']);
        $this->assertTrue(LogAktivitas::query()->where('aksi', 'warga.dibuat')->exists());
    }

    public function test_tambah_warga_tanpa_kirim_email(): void
    {
        $this->masukSebagaiPengurus();

        $this->isiForm(Livewire::test(WargaForm::class), ['kirim_email' => false])->call('simpan')->assertHasNoErrors();

        Notification::assertNothingSent();
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'gita@contoh.test']);
    }

    public function test_kirim_ulang_email_buat_password_dari_warga_dan_geng(): void
    {
        $this->masukSebagaiPengurus();
        $warga = User::factory()->tanpaPassword()->create(['geng_id' => $this->geng(2)->id]);

        $this->get(route('pengurus.warga.index'))->assertSee('Belum buat password')->assertSee('Kirim ulang email buat password');
        Livewire::test(WargaIndex::class)->call('kirimBuatPassword', $warga->id)->assertDispatched('toast', fn ($n, $p) => $p['jenis'] === 'sukses');

        Notification::assertSentTo($warga, TautanPassword::class, fn (TautanPassword $n) => $n->jenis === TautanPassword::BUAT);
        $this->assertTrue(LogAktivitas::query()->where('aksi', 'warga.email_buat_password')->exists());
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

    public function test_tidak_bisa_kirim_email_ke_warga_nonaktif(): void
    {
        $this->masukSebagaiPengurus();
        $warga = User::factory()->nonaktif()->create();

        Livewire::test(WargaIndex::class)->call('kirimBuatPassword', $warga->id)
            ->assertDispatched('toast', fn ($n, $p) => $p['jenis'] === 'gagal');
        Notification::assertNothingSent();
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
        ])->expectsOutputToContain('Link buat password:')->assertSuccessful();

        $admin = User::query()->where('email', 'rina@contoh.test')->firstOrFail();
        $this->assertSame(Role::TataTertib, $admin->role);
        $this->assertSame($this->geng(1)->id, $admin->geng_id);
        $this->assertFalse($admin->punyaPassword());
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'rina@contoh.test']);
        Notification::assertNothingSent(); // tanpa --kirim, link hanya ditampilkan
    }

    public function test_create_admin_menolak_email_ganda(): void
    {
        User::factory()->create(['email' => 'rina@contoh.test']);

        $this->artisan('app:create-admin', ['--nama' => 'Rina', '--email' => 'rina@contoh.test', '--geng' => 1])
            ->assertFailed();
    }
}
