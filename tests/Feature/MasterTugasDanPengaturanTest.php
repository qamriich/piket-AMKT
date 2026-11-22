<?php

namespace Tests\Feature;

use App\Enums\JenisTugas;
use App\Enums\Role;
use App\Livewire\Pengurus\Pengaturan as HalamanPengaturan;
use App\Livewire\Pengurus\TugasIndex;
use App\Models\Pengaturan;
use App\Models\Tugas;
use App\Models\User;
use App\Services\RotasiGeng;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class MasterTugasDanPengaturanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buatGeng();
    }

    public function test_tambah_ubah_hapus_tugas(): void
    {
        $this->masukSebagaiPengurus();

        Livewire::test(TugasIndex::class)
            ->set('baru.kamis_minggu', 'WC 5')
            ->call('tambah', 'kamis_minggu')
            ->assertHasNoErrors()
            ->assertSet('baru.kamis_minggu', '')
            ->assertSee('WC 5');
        $tugas = Tugas::query()->where('nama', 'WC 5')->firstOrFail();
        $this->assertSame(JenisTugas::KamisMinggu, $tugas->jenis);
        $this->assertSame(1, $tugas->urutan);
        $this->assertTrue($tugas->aktif_default);

        Livewire::test(TugasIndex::class)
            ->call('mulaiUbah', $tugas->id)
            ->set('ubahNama', 'WC 5 (atas)')
            ->set('ubahUrutan', 20)
            ->set('ubahAktifDefault', false)
            ->call('simpanUbah')
            ->assertHasNoErrors();
        $tugas->refresh();
        $this->assertSame('WC 5 (atas)', $tugas->nama);
        $this->assertSame(20, $tugas->urutan);
        $this->assertFalse($tugas->aktif_default);
        $this->assertSame(JenisTugas::KamisMinggu, $tugas->jenis);

        Livewire::test(TugasIndex::class)->call('hapus', $tugas->id)->assertDontSee('WC 5 (atas)');
        $this->assertSoftDeleted($tugas);
        $this->get(route('pengurus.tugas.index'))->assertOk()->assertDontSee('WC 5 (atas)');
    }

    public function test_jenis_tugas_harus_valid_dan_nama_wajib(): void
    {
        $this->masukSebagaiPengurus();

        Livewire::test(TugasIndex::class)->call('tambah', 'harian')->assertHasErrors('baru.harian');
        Livewire::test(TugasIndex::class)->set('baru.bulanan', 'X')->call('tambah', 'bulanan')->assertHasErrors('jenis');
        $this->assertSame(0, Tugas::query()->count());
    }

    public function test_simpan_pengaturan_dan_minggu_acuan_dinormalkan_ke_senin(): void
    {
        $this->masukSebagaiPengurus();

        Livewire::test(HalamanPengaturan::class)
            ->set('minggu_acuan', '2026-11-26') // Kamis
            ->set('geng_acuan', 3)
            ->set('batas_jam_kuliah', '08:30')
            ->call('simpan')
            ->assertHasNoErrors()
            ->assertSet('minggu_acuan', '2026-11-23')
            ->assertDispatched('toast');

        $this->assertSame('2026-11-23', Pengaturan::ambil('minggu_acuan'));
        $this->assertSame('3', Pengaturan::ambil('geng_acuan'));
        $this->assertSame('08:30', Pengaturan::ambil('batas_jam_kuliah'));

        $rotasi = RotasiGeng::dariPengaturan();
        $this->assertSame(3, $rotasi->urutanGengHarian(now()->setDate(2026, 11, 23)));
        $this->assertSame(1, $rotasi->urutanGengHarian(now()->setDate(2026, 11, 30)));
    }

    public function test_validasi_pengaturan(): void
    {
        $this->masukSebagaiPengurus();

        Livewire::test(HalamanPengaturan::class)
            ->set('minggu_acuan', 'bukan-tanggal')->set('geng_acuan', 4)->set('batas_jam_kuliah', '25:00')
            ->call('simpan')
            ->assertHasErrors(['minggu_acuan', 'geng_acuan', 'batas_jam_kuliah']);
    }

    public function test_halaman_pengaturan_menampilkan_pratinjau_8_minggu(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 18));
        $this->masukSebagaiPengurus();

        $this->get(route('pengurus.pengaturan'))
            ->assertOk()
            ->assertSeeInOrder(['16 Nov – 22 Nov', '(ini)', '23 Nov – 29 Nov', '4 Jan – 10 Jan']);
    }

    public function test_seeder_lengkap(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('gengs', 3);
        $this->assertDatabaseCount('users', 15);
        $this->assertSame(4, User::pengurus()->count());
        $this->assertSame(9, Tugas::query()->jenis(JenisTugas::Harian)->count());
        $this->assertSame(20, Tugas::query()->jenis(JenisTugas::KamisMinggu)->count()); // 13 dari PRD + 7 dari catatan bintang September
        $this->assertSame('2026-11-16', Pengaturan::ambil('minggu_acuan'));
        $this->assertSame('09:00', Pengaturan::ambil('batas_jam_kuliah'));

        foreach ([1, 2, 3] as $u) {
            $this->assertSame(5, $this->geng($u)->anggota()->count());
        }

        // Data asli PRD bagian 13.
        $wilda = User::query()->where('email', 'wilda@amkt.test')->firstOrFail();
        $this->assertSame('Wilda', $wilda->nama);
        $this->assertSame(Role::TataTertib, $wilda->role);
        $this->assertSame($this->geng(3)->id, $wilda->geng_id);
        $this->assertNull($wilda->no_kamar);
        $this->assertSame(Role::Ketua, User::query()->where('nama', 'Gita')->value('role'));
        $this->assertSame(6, User::query()->where('wajib_diperiksa', true)->count());
        $this->assertEqualsCanonicalizing(
            ['Gita', 'Qamri', 'Adel', 'Wilda', 'Ping', 'Ain', 'Pipi', 'Fika', 'Nivia', 'Niya', 'Eca', 'Dilla', 'Dewi', 'Kia', 'Septi'],
            User::query()->pluck('nama')->all(),
        );

        // Di luar lokal (termasuk test) password sementara tidak dibuat.
        $this->assertSame(0, User::query()->whereNotNull('password')->count());
    }

    public function test_seeder_lokal_memberi_password_sementara(): void
    {
        $this->app['env'] = 'local';
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(15, User::query()->whereNotNull('password')->count());
        $this->assertTrue(Hash::check('piket123', User::query()->where('nama', 'Ping')->value('password')));
        // R2: password awal lokal tetap wajib diganti saat login pertama.
        $this->assertSame(15, User::query()->where('harus_ganti_password', true)->count());
    }
}
