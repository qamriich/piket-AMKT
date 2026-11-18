<?php

namespace App\Console\Commands;

use App\Enums\JenisPenugasan;
use App\Enums\JenisTugas;
use App\Enums\Role;
use App\Enums\SesiTugas;
use App\Enums\StatusIzin;
use App\Enums\StatusPenugasan;
use App\Models\Izin;
use App\Models\MingguPiket;
use App\Models\PengajuanGantiBintang;
use App\Models\Penugasan;
use App\Models\PeriodeHarian;
use App\Models\Tugas;
use App\Models\TugasAktif;
use App\Models\User;
use App\Services\JadwalHarianPeriode;
use App\Services\JadwalMinggu;
use App\Services\LayananIzin;
use App\Services\RotasiGeng;
use App\Services\Tenggat;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Data percobaan untuk melihat fitur Tahap 3 (khusus lokal). Aman dijalankan berulang kali:
 * data yang sudah ada dipakai ulang, bukan dibuat dobel.
 *
 * - Ping: 1 bintang harian minggu lalu, belum lunas dan belum diajukan (pengajuannya di-reset).
 * - Eca: 1 bintang Kamis minggu lalu ("Akan dijadwalkan ulang").
 * - Dilla: 1 bintang harian yang sudah lunas (riwayat).
 * - Jadwal harian periode "Data contoh" dengan hari kosong (Rabu, Jumat, Minggu), supaya ada pilihan hari ganti.
 * - Adel: izin Kamis minggu ini 07.00 sampai Jumat 20.00 (disetujui), supaya pengganti otomatisnya terlihat.
 */
class DataContoh extends Command
{
    protected $signature = 'app:data-contoh';

    protected $description = 'Membuat data percobaan (bintang, jadwal periode, izin). Khusus lokal, bisa dijalankan berulang';

    public const NAMA_PERIODE = 'Data contoh';

    /** Hari periode yang diisi (Senin, Selasa, Kamis, Sabtu). Rabu, Jumat, dan Minggu dibiarkan kosong. */
    private const HARI_TERISI = [1, 2, 4, 6];

    /** @var list<array{0: string, 1: string}> */
    private array $laporan = [];

    public function handle(JadwalMinggu $jadwal, JadwalHarianPeriode $periode, LayananIzin $izin): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Perintah ini khusus lokal dan tidak boleh dijalankan di '.app()->environment().'.');

            return self::FAILURE;
        }

        $warga = User::query()->whereIn('nama', ['Ping', 'Eca', 'Dilla', 'Adel'])->get()->keyBy('nama');
        $pengurus = User::query()->aktif()->where('role', Role::TataTertib->value)->orderBy('id')->first()
            ?? User::query()->aktif()->pengurus()->orderBy('id')->first();
        if ($warga->count() < 4 || ! $pengurus) {
            $this->error('Warga Ping, Eca, Dilla, Adel, atau akun pengurus belum ada. Jalankan dulu: php artisan migrate:fresh --seed');

            return self::FAILURE;
        }

        $sekarang = CarbonImmutable::now();
        $seninIni = RotasiGeng::seninDari($sekarang);
        $mingguLalu = $this->mingguLalu($jadwal, $seninIni->subWeek());

        DB::transaction(function () use ($mingguLalu, $warga) {
            $this->bintangPing($mingguLalu, $warga['Ping']);
            $this->bintangEca($mingguLalu, $warga['Eca']);
            $this->lunasDilla($mingguLalu, $warga['Dilla']);
        });

        $this->periode($periode, $seninIni);
        $this->izinAdel($izin, $jadwal, $warga['Adel'], $pengurus, $seninIni);

        $this->table(['Data', 'Keterangan'], $this->laporan);

        return self::SUCCESS;
    }

    /** Minggu lalu dibuat sebagai minggu yang sudah terbit (tanpa membuat piket otomatis yang sudah lewat). */
    private function mingguLalu(JadwalMinggu $jadwal, CarbonImmutable $senin): MingguPiket
    {
        $m = $jadwal->cari($senin) ?? MingguPiket::create([
            'tanggal_senin' => $senin->toDateString(),
            'geng_harian_id' => RotasiGeng::dariPengaturan()->gengHarian($senin)->id,
        ]);
        $m->update(['status_harian' => 'terbit', 'status_kamis' => 'terbit', 'status_minggu' => 'terbit']);

        return $m;
    }

    /** Satu baris harian untuk warga ini di tanggal itu. Kalau tanggalnya dipegang orang lain, cari tanggal lain di minggu yang sama. */
    private function harian(MingguPiket $m, User $u, int $hariKe, StatusPenugasan $status, ?Penugasan $membayar = null): Penugasan
    {
        $milikSendiri = Penugasan::query()->bukanIzin()->where('minggu_piket_id', $m->id)->where('jenis', 'harian')
            ->where('user_id', $u->id)
            ->when($membayar, fn ($q) => $q->where('membayar_penugasan_id', $membayar->id), fn ($q) => $q->whereNull('membayar_penugasan_id'))
            ->first();

        if (! $milikSendiri) {
            $terisi = Penugasan::query()->bukanIzin()->where('jenis', 'harian')
                ->whereDate('tanggal', '>=', $m->senin()->toDateString())
                ->whereDate('tanggal', '<=', $m->hariMinggu()->toDateString())
                ->pluck('tanggal')->map(fn ($t) => $t->toDateString())->all();
            $urutan = array_merge([$hariKe], array_diff(range(0, 6), [$hariKe]));
            $tanggal = collect($urutan)->map(fn ($i) => $m->senin()->addDays($i))
                ->first(fn (CarbonImmutable $t) => ! in_array($t->toDateString(), $terisi, true));
            abort_if($tanggal === null, 1, "Semua hari harian minggu {$m->senin()->toDateString()} sudah terisi.");

            $milikSendiri = new Penugasan([
                'minggu_piket_id' => $m->id,
                'user_id' => $u->id,
                'jenis' => JenisPenugasan::Harian,
                'tanggal' => $tanggal->toDateString(),
                'tenggat' => Tenggat::untuk(JenisPenugasan::Harian, $tanggal),
                'membayar_penugasan_id' => $membayar?->id,
            ]);
        }

        $selesai = $status === StatusPenugasan::Selesai;
        $milikSendiri->fill([
            'status' => $status,
            'selesai_oleh_id' => $selesai ? $u->id : null,
            'selesai_pada' => $selesai ? $milikSendiri->tenggatLokal()->subHours(2) : null,
            'diberitahu_pada' => $milikSendiri->tenggatLokal()->subDays(2),
        ])->save();

        return $milikSendiri;
    }

    private function bintangPing(MingguPiket $m, User $ping): void
    {
        $b = $this->harian($m, $ping, 1, StatusPenugasan::Bintang); // Selasa

        // "Belum diajukan": pengajuan dan piket gantinya (kalau pernah dicoba) dikembalikan ke awal.
        PengajuanGantiBintang::query()->where('penugasan_bintang_id', $b->id)->delete();
        Penugasan::query()->where('membayar_penugasan_id', $b->id)->delete();

        $this->laporan[] = ['Ping: bintang harian', $b->tanggalLokal()->translatedFormat('l, j M').', belum lunas, belum diajukan'];
    }

    private function bintangEca(MingguPiket $m, User $eca): void
    {
        $tanggal = $m->kamis();
        $sudah = $m->penugasan()->bukanIzin()->where('jenis', 'kamis')->where('user_id', $eca->id)->whereNull('membayar_penugasan_id')->with('tugas')->first();

        if (! $sudah) {
            $terpakai = $m->penugasan()->bukanIzin()->where('jenis', 'kamis')->pluck('tugas_id')->all();
            $tugas = Tugas::query()->jenis(JenisTugas::KamisMinggu)->urut()->whereNotIn('id', $terpakai)->firstOrFail();
            TugasAktif::query()->firstOrCreate(['minggu_piket_id' => $m->id, 'tugas_id' => $tugas->id, 'sesi' => SesiTugas::Kamis->value]);

            $sudah = Penugasan::create([
                'minggu_piket_id' => $m->id,
                'user_id' => $eca->id,
                'tugas_id' => $tugas->id,
                'jenis' => JenisPenugasan::Kamis,
                'tanggal' => $tanggal->toDateString(),
                'tenggat' => Tenggat::untuk(JenisPenugasan::Kamis, $tanggal),
                'status' => StatusPenugasan::Bintang,
                'diberitahu_pada' => $tanggal->subDays(2),
            ]);
            $sudah->load('tugas');
        } else {
            $sudah->update(['status' => StatusPenugasan::Bintang, 'selesai_oleh_id' => null, 'selesai_pada' => null]);
        }

        $this->laporan[] = ['Eca: bintang Kamis', $sudah->namaTugas().', '.$tanggal->translatedFormat('l, j M').' (dijadwalkan ulang otomatis)'];
    }

    private function lunasDilla(MingguPiket $m, User $dilla): void
    {
        $asli = $this->harian($m, $dilla, 0, StatusPenugasan::Lunas); // Senin
        $bayar = $this->harian($m, $dilla, 4, StatusPenugasan::Selesai, $asli); // Jumat

        $this->laporan[] = ['Dilla: bintang lunas', 'Bintang '.$asli->tanggalLokal()->translatedFormat('j M').', dibayar '.$bayar->tanggalLokal()->translatedFormat('l, j M')];
    }

    /**
     * Jadwal harian periode dengan hari kosong. Dimulai di minggu pertama (dalam 4 minggu ke depan) yang
     * jadwal hariannya belum dibuat, supaya minggu yang sudah diisi manual tidak berubah.
     */
    private function periode(JadwalHarianPeriode $layanan, CarbonImmutable $seninIni): void
    {
        if ($ada = PeriodeHarian::query()->where('nama', self::NAMA_PERIODE)->first()) {
            $this->laporan[] = ['Jadwal harian periode', "Sudah ada: {$ada->mulai()->translatedFormat('j M Y')} – {$ada->selesai()->translatedFormat('j M Y')}"];

            return;
        }

        $mulai = collect(range(1, 4))->map(fn ($i) => $seninIni->addWeeks($i))
            ->first(fn (CarbonImmutable $s) => ! MingguPiket::query()->whereDate('tanggal_senin', $s->toDateString())->where('status_harian', 'terbit')->exists())
            ?? $seninIni->addWeeks(4);
        $selesai = $mulai->addWeeks(8)->subDay();

        if ($lain = PeriodeHarian::query()->whereDate('tanggal_mulai', '<=', $selesai->toDateString())->whereDate('tanggal_selesai', '>=', $mulai->toDateString())->first()) {
            $this->laporan[] = ['Jadwal harian periode', "Dilewati: sudah ada periode \"{$lain->nama}\" di rentang itu"];

            return;
        }

        $peta = [];
        foreach (User::query()->aktif()->whereNotNull('geng_id')->orderBy('id')->get()->groupBy('geng_id') as $gengId => $anggota) {
            $peta[$gengId] = array_fill(1, 7, null);
            foreach ($anggota->take(count(self::HARI_TERISI))->values() as $i => $u) {
                $peta[$gengId][self::HARI_TERISI[$i]] = $u->id;
            }
        }

        $p = $layanan->simpanPeriode(null, self::NAMA_PERIODE, $mulai, $selesai);
        $layanan->simpanHari($p, $peta);

        $this->laporan[] = ['Jadwal harian periode', "\"Data contoh\" {$mulai->translatedFormat('j M')} – {$selesai->translatedFormat('j M Y')}; Rabu, Jumat, Minggu kosong"];
    }

    private function izinAdel(LayananIzin $layanan, JadwalMinggu $jadwal, User $adel, User $pengurus, CarbonImmutable $seninIni): void
    {
        $mulai = $seninIni->addDays(3)->setTime(7, 0);   // Kamis 07.00
        $selesai = $seninIni->addDays(4)->setTime(20, 0); // Jumat 20.00
        $jadwal->siapkan($seninIni);

        $ada = Izin::query()->where('user_id', $adel->id)
            ->whereIn('status', [StatusIzin::Menunggu->value, StatusIzin::Disetujui->value])
            ->bersinggungan($mulai, $selesai)->first();
        if ($ada) {
            $this->laporan[] = ['Adel: izin', 'Sudah ada: '.$ada->rentangLabel().' ('.$ada->status->label().')'];

            return;
        }

        $hasil = $layanan->buatLangsung($adel, $mulai, $selesai, 'Kegiatan kampus (data contoh)', $pengurus)['hasil'];

        $ket = 'Disetujui. ';
        $ket .= match (true) {
            $hasil['pengganti'] !== [] => 'Pengganti otomatis: '.implode(', ', $hasil['pengganti']),
            $hasil['tanpa_petugas'] !== [] => 'Tidak ada calon pengganti: '.implode(', ', $hasil['tanpa_petugas']).' (Belum ada petugas)',
            default => 'Adel tidak punya piket terbit yang tertutup izin ini',
        };
        $this->laporan[] = ['Adel: izin', $ket];
    }
}
