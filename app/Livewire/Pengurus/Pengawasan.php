<?php

namespace App\Livewire\Pengurus;

use App\Enums\JenisPenugasan;
use App\Enums\JenisTugas;
use App\Enums\ModeMinggu;
use App\Enums\SesiTugas;
use App\Enums\StatusPengajuan;
use App\Enums\StatusPenugasan;
use App\Livewire\Concerns\BeriUmpanBalik;
use App\Livewire\Concerns\KhususPengurus;
use App\Livewire\Concerns\PutuskanPengajuan;
use App\Models\Geng;
use App\Models\PengajuanGantiBintang;
use App\Models\Penugasan;
use App\Models\Tugas;
use App\Models\User;
use App\Services\JadwalMinggu;
use App\Services\LayananIzin;
use App\Services\PelaksanaanPiket;
use App\Services\PenugasanOtomatis;
use App\Services\RotasiGeng;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * F9: dashboard pengawasan, halaman utama pengurus.
 */
#[Layout('components.layouts.pengurus')]
#[Title('Pengawasan')]
class Pengawasan extends Component
{
    use BeriUmpanBalik, KhususPengurus, PutuskanPengajuan;

    #[Url(as: 'rentang', except: 'hari')]
    public string $rentang = 'hari'; // hari | minggu

    #[Url(as: 'status', except: 'semua')]
    public string $filter = 'semua'; // semua | periksa | belum | selesai

    #[Url(except: '')]
    public string $jenis = '';

    #[Url(except: 0)]
    public int $geng = 0;

    public function setFilter(string $filter): void
    {
        $this->filter = in_array($filter, ['semua', 'periksa', 'belum', 'selesai'], true) ? $filter : 'semua';
    }

    public function setRentang(string $rentang): void
    {
        $this->rentang = $rentang === 'minggu' ? 'minggu' : 'hari';
    }

    public function periksaSekarang(): void
    {
        $this->rentang = 'hari';
        $this->filter = 'periksa';
        $this->dispatch('gulir-ke', id: 'periksa');
    }

    // ---- F6 dan R5: tandai selesai, batalkan, catatan, koreksi ----

    private function piket(int $id): ?Penugasan
    {
        $p = Penugasan::query()->with('user')->find($id);
        if (! $p) {
            $this->toast('Piket tidak ditemukan. Muat ulang halaman.', 'gagal');
        }

        return $p;
    }

    public function tandaiSelesai(int $id, PelaksanaanPiket $pelaksanaan): void
    {
        if ($p = $this->piket($id)) {
            $this->coba(fn () => $pelaksanaan->tandaiSelesai($p, auth()->user()), "Piket {$p->user->namaDepan()} ditandai selesai.");
        }
    }

    public function batalkanSelesai(int $id, PelaksanaanPiket $pelaksanaan): void
    {
        if ($p = $this->piket($id)) {
            $this->coba(fn () => $pelaksanaan->batalkanSelesai($p, auth()->user()), "Tanda selesai {$p->user->namaDepan()} dibatalkan.");
        }
    }

    public function simpanCatatan(int $id, ?string $catatan, PelaksanaanPiket $pelaksanaan): void
    {
        if ($p = $this->piket($id)) {
            $this->coba(fn () => $pelaksanaan->simpanCatatan($p, $catatan, auth()->user()),
                trim((string) $catatan) === '' ? 'Catatan dihapus.' : "Catatan disimpan. {$p->user->namaDepan()} bisa melihatnya.");
        }
    }

    public function koreksi(int $id, string $status, string $catatan, PelaksanaanPiket $pelaksanaan): void
    {
        $ke = StatusPenugasan::tryFrom($status);
        if ($ke === null) {
            $this->toast('Pilih status koreksinya.', 'gagal');

            return;
        }
        if ($p = $this->piket($id)) {
            $this->coba(fn () => $pelaksanaan->koreksi($p, $ke, $catatan, auth()->user()),
                "Status piket {$p->user->namaDepan()} dikoreksi menjadi {$ke->label()}.");
        }
    }

    // ---- Kartu cepat "Atur piket Minggu" (Tahap 2) ----

    public function ubahTugasMinggu(int $tugasId, bool $aktif, JadwalMinggu $jadwal): void
    {
        $minggu = $jadwal->siapkan(CarbonImmutable::now());
        $tugas = Tugas::query()->findOrFail($tugasId);
        $this->coba(fn () => $jadwal->ubahTugasAktif($minggu, SesiTugas::Minggu, $tugas, $aktif),
            $aktif ? "{$tugas->nama} diaktifkan." : "{$tugas->nama} dimatikan.");
    }

    public function ubahModeMinggu(string $mode, JadwalMinggu $jadwal): void
    {
        $minggu = $jadwal->siapkan(CarbonImmutable::now());
        $this->coba(fn () => $jadwal->ubahModeMinggu($minggu, ModeMinggu::from($mode)), 'Jenis piket Minggu disimpan.');
    }

    public function bagikanMinggu(JadwalMinggu $jadwal, PenugasanOtomatis $otomatis)
    {
        $minggu = $jadwal->siapkan(CarbonImmutable::now());
        $ok = $this->coba(fn () => $otomatis->bagikan($minggu, SesiTugas::Minggu));

        if ($ok) {
            session()->flash('status', 'Pembagian Minggu sudah dibuat. Cek dulu, lalu terbitkan.');

            return $this->redirectRoute('pengurus.jadwal', ['minggu' => $minggu->tanggal_senin->toDateString(), 'tab' => 'minggu'], navigate: true);
        }
    }

    public function render(JadwalMinggu $jadwal, PelaksanaanPiket $pelaksanaan, LayananIzin $izin)
    {
        $sekarang = CarbonImmutable::now();
        $hariIni = $sekarang->startOfDay();
        $senin = RotasiGeng::seninDari($sekarang);
        $minggu = $jadwal->siapkan($sekarang)->load('gengHarian');

        // Daftar piket: hari ini = piket yang jendela pengerjaannya mencakup hari ini.
        $daftar = Penugasan::query()
            ->terbit()
            ->with(['user.geng', 'tugas', 'selesaiOleh'])
            ->when($this->rentang === 'hari', fn ($q) => $q
                ->whereDate('tanggal', '<=', $hariIni->toDateString())
                ->whereDate('tenggat', '>=', $hariIni->toDateString()))
            ->when($this->rentang === 'minggu', fn ($q) => $q
                ->whereDate('tanggal', '>=', $senin->toDateString())
                ->whereDate('tanggal', '<=', $senin->addDays(6)->toDateString()))
            ->when($this->jenis !== '', fn ($q) => $q->whereIn('jenis', $this->jenis === 'minggu' ? ['minggu', 'kerja_bakti'] : [$this->jenis]))
            ->when($this->geng > 0, fn ($q) => $q->whereHas('user', fn ($u) => $u->where('geng_id', $this->geng)))
            ->get()
            ->sortBy(fn (Penugasan $p) => [
                $p->user->wajib_diperiksa && $p->status->belumSelesai() ? 0 : 1, // wajib diperiksa di atas
                $p->tanggal->toDateString(),
                array_search($p->jenis, JenisPenugasan::cases(), true),
                $p->tugas?->urutan ?? 0,
            ])->values();

        $perluDiperiksa = $daftar->filter(fn (Penugasan $p) => $p->user->wajib_diperiksa && $p->status->belumSelesai());
        $terlihat = $daftar->filter(fn (Penugasan $p) => match ($this->filter) {
            'periksa' => $p->user->wajib_diperiksa && $p->status->belumSelesai(),
            'belum' => $p->status->belumSelesai(),
            'selesai' => in_array($p->status, [StatusPenugasan::Selesai, StatusPenugasan::Lunas], true),
            default => true,
        })->values();

        $harianHariIni = $this->rentang === 'hari' ? $terlihat->firstWhere('jenis', JenisPenugasan::Harian) : null;
        if ($harianHariIni) {
            $terlihat = $terlihat->reject(fn ($p) => $p->is($harianHariIni))->values();
        }

        return view('livewire.pengurus.pengawasan', [
            'user' => auth()->user(),
            'sekarang' => $sekarang,
            'minggu' => $minggu,
            'sapaan' => $this->sapaan($sekarang),
            'jumlahWarga' => User::query()->aktif()->count(),
            'jumlahGeng' => Geng::query()->count(),
            'jumlahPengurus' => User::query()->aktif()->pengurus()->count(),
            'fokus' => $this->fokus($sekarang, $jadwal),
            'batang' => $this->batang($hariIni),
            'perluDiperiksa' => $perluDiperiksa,
            'harianHariIni' => $harianHariIni,
            'daftar' => $terlihat,
            'tenggatBerikut' => $this->tenggatBerikut($sekarang),
            'pekan' => $this->pekan($minggu, $hariIni),
            'pengajuan' => PengajuanGantiBintang::query()->where('status', StatusPengajuan::Menunggu->value)
                ->with(['user', 'penugasanBintang'])->oldest()->take(3)->get(),
            'jumlahPengajuan' => PengajuanGantiBintang::query()->where('status', StatusPengajuan::Menunggu->value)->count(),
            'tugasMinggu' => Tugas::query()->jenis(JenisTugas::KamisMinggu)->urut()->get(),
            'aktifMinggu' => $jadwal->tugasAktif($minggu, SesiTugas::Minggu)->pluck('id')->all(),
            'gengs' => Geng::query()->orderBy('urutan')->get(),
            // Aksi per baris: tandai (sebelum tenggat), batalkan (selesai, sebelum tenggat), koreksi (setelah tenggat).
            'aksi' => fn (Penugasan $p) => match (true) {
                $p->lewatTenggat($sekarang) => 'koreksi',
                $pelaksanaan->bisaDitandaiPengurus($p, $sekarang) => 'tandai',
                $p->status === StatusPenugasan::Selesai => 'batal',
                default => null,
            },
            'statusKoreksi' => PelaksanaanPiket::STATUS_KOREKSI,
            'sedangIzin' => $izin->sedangIzin($senin, $senin->addDays(7)),
        ]);
    }

    private function sapaan(CarbonImmutable $t): string
    {
        return match (true) {
            $t->hour < 11 => 'Selamat pagi',
            $t->hour < 15 => 'Selamat siang',
            $t->hour < 18 => 'Selamat sore',
            default => 'Selamat malam',
        };
    }

    /**
     * Sesi yang sedang berjalan: Kamis (Kamis–Jumat), Minggu (Sabtu–Senin), selain itu Harian.
     *
     * @return array{label: string, keterangan: string, selesai: int, total: int}
     */
    private function fokus(CarbonImmutable $t, JadwalMinggu $jadwal): array
    {
        $hari = $t->dayOfWeekIso;
        $senin = RotasiGeng::seninDari($t);

        [$jenis, $mulai, $akhir, $label, $ket] = match (true) {
            in_array($hari, [4, 5], true) => [['kamis'], $senin->addDays(3), $senin->addDays(3), 'Piket Kamis selesai', 'batas Jumat, 18.00'],
            in_array($hari, [6, 7], true) => [['minggu', 'kerja_bakti'], $senin->addDays(6), $senin->addDays(6), 'Piket Minggu selesai', 'batas Senin, 18.00'],
            $hari === 1 && $t->hour < 18 => [['minggu', 'kerja_bakti'], $senin->subDay(), $senin->subDay(), 'Piket Minggu selesai', 'batas hari ini, 18.00'],
            default => [['harian'], $senin, $senin->addDays(6), 'Piket harian minggu ini', 'setiap hari, batas 09.00'],
        };

        $baris = Penugasan::query()->terbit()->whereIn('jenis', $jenis)
            ->whereDate('tanggal', '>=', $mulai->toDateString())
            ->whereDate('tanggal', '<=', $akhir->toDateString())
            ->pluck('status');

        return [
            'label' => $label,
            'keterangan' => $ket,
            'selesai' => $baris->filter(fn ($s) => in_array($s, [StatusPenugasan::Selesai, StatusPenugasan::Lunas], true))->count(),
            'total' => $baris->count(),
        ];
    }

    /** @return list<array{nilai: int, tinggi: int, hari_ini: bool}> 7 hari terakhir: jumlah piket yang ditandai selesai. */
    private function batang(CarbonImmutable $hariIni): array
    {
        $awal = $hariIni->subDays(6);
        $perHari = Penugasan::query()
            ->whereNotNull('selesai_pada')
            ->where('selesai_pada', '>=', $awal->format('Y-m-d H:i:s'))
            ->pluck('selesai_pada')
            ->countBy(fn ($t) => $t->toDateString());

        $maks = max(1, (int) $perHari->max());
        $hasil = [];
        for ($i = 0; $i < 7; $i++) {
            $tgl = $awal->addDays($i)->toDateString();
            $nilai = (int) ($perHari[$tgl] ?? 0);
            $hasil[] = ['nilai' => $nilai, 'tinggi' => max(4, (int) round($nilai / $maks * 36)), 'hari_ini' => $i === 6];
        }

        return $hasil;
    }

    private function tenggatBerikut(CarbonImmutable $t): ?Penugasan
    {
        return Penugasan::query()->terbit()
            ->whereIn('status', [StatusPenugasan::Terjadwal->value, StatusPenugasan::BayarBintang->value])
            ->where('tenggat', '>=', $t->format('Y-m-d H:i:s'))
            ->orderBy('tenggat')
            ->first();
    }

    /** @return Collection<int, array{hari: string, nama: string, keadaan: string}> petugas harian Senin–Minggu */
    private function pekan($minggu, CarbonImmutable $hariIni): Collection
    {
        $harian = $minggu->penugasan()->bukanIzin()->where('jenis', 'harian')->with('user')->get()
            ->keyBy(fn (Penugasan $p) => $p->tanggal->toDateString());
        $terbit = $minggu->sudahTerbit('harian');

        return collect(range(0, 6))->map(function (int $i) use ($minggu, $harian, $hariIni, $terbit) {
            $t = $minggu->senin()->addDays($i);
            $p = $harian->get($t->toDateString());

            return [
                'hari' => $t->translatedFormat('D'),
                'nama' => $p ? $p->user->namaDepan() : 'Kosong',
                'keadaan' => match (true) {
                    $p === null => 'kosong',
                    ! $terbit => 'draf',
                    $p->status === StatusPenugasan::Selesai => 'selesai',
                    $t->isSameDay($hariIni) => 'sekarang',
                    default => 'nanti',
                },
            ];
        });
    }
}
