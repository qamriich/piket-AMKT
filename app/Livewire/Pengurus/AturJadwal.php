<?php

namespace App\Livewire\Pengurus;

use App\Enums\JenisPenugasan;
use App\Enums\JenisTugas;
use App\Enums\ModeMinggu;
use App\Enums\SesiTugas;
use App\Enums\StatusPenugasan;
use App\Livewire\Concerns\BeriUmpanBalik;
use App\Livewire\Concerns\KhususPengurus;
use App\Models\MingguPiket;
use App\Models\Penugasan;
use App\Models\Tugas;
use App\Models\User;
use App\Services\JadwalHarianPeriode;
use App\Services\JadwalMinggu;
use App\Services\PenugasanOtomatis;
use App\Services\RotasiGeng;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * F4 dan F5: atur jadwal harian, Kamis, dan Minggu, lalu terbitkan.
 */
#[Layout('components.layouts.pengurus')]
#[Title('Atur jadwal')]
class AturJadwal extends Component
{
    use BeriUmpanBalik, KhususPengurus;

    #[Url(as: 'minggu')]
    public string $tanggal = '';

    #[Url]
    public string $tab = 'harian';

    public ?int $tukarDari = null;

    /** Catatan hasil "Buat pembagian" terakhir (bintang ditunda, tugas tanpa petugas). */
    public array $catatan = ['harian' => [], 'kamis' => [], 'minggu' => []];

    /** Naik setiap pembagian dibuat, supaya hasilnya muncul dengan animasi. */
    public int $versi = 0;

    public function mount(): void
    {
        $this->tanggal = $this->senin()->toDateString();
        if (! in_array($this->tab, ['harian', 'kamis', 'minggu'], true)) {
            $this->tab = 'harian';
        }
    }

    private function senin(): CarbonImmutable
    {
        try {
            $t = $this->tanggal !== '' ? CarbonImmutable::createFromFormat('!Y-m-d', $this->tanggal, config('app.timezone')) : null;
        } catch (\Throwable) {
            $t = null;
        }

        return RotasiGeng::seninDari($t ?: CarbonImmutable::now());
    }

    private function minggu(): MingguPiket
    {
        return app(JadwalMinggu::class)->siapkan($this->senin());
    }

    public function pindahMinggu(int $arah): void
    {
        $this->tanggal = $this->senin()->addWeeks($arah > 0 ? 1 : -1)->toDateString();
        $this->reset('tukarDari', 'catatan');
    }

    public function mingguIni(): void
    {
        $this->tanggal = RotasiGeng::seninDari(CarbonImmutable::now())->toDateString();
        $this->reset('tukarDari', 'catatan');
    }

    public function pilihTab(string $tab): void
    {
        $this->tab = in_array($tab, ['harian', 'kamis', 'minggu'], true) ? $tab : 'harian';
        $this->tukarDari = null;
    }

    // ---------- Harian: otomatis dari jadwal periode (R1); di sini hanya pengganti per tanggal ----------

    public function aturHarian(string $tanggal, $userId, JadwalMinggu $jadwal): void
    {
        $user = $userId ? User::query()->find((int) $userId) : null;
        $this->coba(fn () => $jadwal->aturHarian(
            $this->minggu(),
            CarbonImmutable::createFromFormat('!Y-m-d', $tanggal, config('app.timezone')),
            $user,
        ), $user ? "{$user->namaDepan()} dijadwalkan." : 'Hari itu dikosongkan.');
    }

    // ---------- Kamis & Minggu (F5, B3) ----------

    public function ubahTugas(string $sesi, int $tugasId, bool $aktif, JadwalMinggu $jadwal): void
    {
        $tugas = Tugas::query()->findOrFail($tugasId);
        $this->coba(fn () => $jadwal->ubahTugasAktif($this->minggu(), SesiTugas::from($sesi), $tugas, $aktif),
            $aktif ? "{$tugas->nama} diaktifkan." : "{$tugas->nama} dimatikan.");
    }

    public function semuaTugas(string $sesi, bool $aktif, JadwalMinggu $jadwal): void
    {
        $minggu = $this->minggu();
        $this->coba(function () use ($sesi, $aktif, $jadwal, $minggu) {
            foreach (Tugas::query()->jenis(JenisTugas::KamisMinggu)->get() as $t) {
                $jadwal->ubahTugasAktif($minggu, SesiTugas::from($sesi), $t, $aktif);
            }
        }, $aktif ? 'Semua tugas diaktifkan.' : 'Semua tugas dimatikan.');
    }

    public function ubahMode(string $mode, JadwalMinggu $jadwal): void
    {
        $this->coba(fn () => $jadwal->ubahModeMinggu($this->minggu(), ModeMinggu::from($mode)), 'Jenis piket Minggu disimpan.');
        $this->catatan['minggu'] = [];
    }

    public function bagikan(string $sesi, PenugasanOtomatis $otomatis): void
    {
        $s = SesiTugas::from($sesi);
        $this->tukarDari = null;

        $this->coba(function () use ($s, $otomatis) {
            $hasil = $otomatis->bagikan($this->minggu(), $s);
            $catatan = [];
            foreach ($hasil['ditunda'] as $d) {
                $b = $d['bintang']->loadMissing('user', 'tugas');
                $catatan[] = "Bintang {$b->user->namaDepan()} ({$b->namaTugas()}, {$b->tanggalLokal()->translatedFormat('j M')}) ditunda: {$d['alasan']}.";
            }
            if ($hasil['tanpa_petugas']->isNotEmpty()) {
                $catatan[] = 'Orangnya kurang. Tugas tanpa petugas: '.$hasil['tanpa_petugas']->pluck('nama')->join(', ').'. Pilih petugasnya secara manual.';
            }
            $this->catatan[$s->value] = $catatan;
            $this->versi++;
        }, 'Pembagian '.$s->label().' sudah dibuat.');
    }

    public function aturPetugas(string $sesi, int $tugasId, $userId, JadwalMinggu $jadwal): void
    {
        $tugas = Tugas::query()->findOrFail($tugasId);
        $user = $userId ? User::query()->find((int) $userId) : null;
        $this->coba(fn () => $jadwal->aturPetugasTugas($this->minggu(), SesiTugas::from($sesi), $tugas, $user),
            $user ? "{$tugas->nama}: {$user->namaDepan()}." : "{$tugas->nama} dikosongkan.");
    }

    public function pilihTukar(int $id, JadwalMinggu $jadwal): void
    {
        if ($this->tukarDari === null) {
            $this->tukarDari = $id;

            return;
        }
        if ($this->tukarDari === $id) {
            $this->tukarDari = null;

            return;
        }

        $minggu = $this->minggu();
        $a = $minggu->penugasan()->with('user')->find($this->tukarDari);
        $b = $minggu->penugasan()->with('user')->find($id);
        $this->tukarDari = null;

        if (! $a || ! $b) {
            $this->toast('Tugas tidak ditemukan. Muat ulang halaman.', 'gagal');

            return;
        }

        $this->coba(fn () => $jadwal->tukar($a, $b), "{$a->user->namaDepan()} dan {$b->user->namaDepan()} sudah ditukar.");
    }

    public function batalTukar(): void
    {
        $this->tukarDari = null;
    }

    public function terbitkan(string $bagian, JadwalMinggu $jadwal): void
    {
        // Hanya Kamis dan Minggu yang diterbitkan (R1); service menolak "harian".
        $label = $bagian === 'harian' ? 'harian' : ucfirst($bagian);
        $this->coba(fn () => $jadwal->terbitkan($this->minggu(), $bagian), "Jadwal {$label} diterbitkan dan sekarang terlihat oleh warga.");
    }

    public function render(JadwalMinggu $jadwal, JadwalHarianPeriode $jadwalPeriode)
    {
        $minggu = $this->minggu()->load('gengHarian');
        $semua = $minggu->penugasan()->bukanIzin()->with(['user', 'tugas', 'membayar'])->get(); // baris Izin sudah dilepas (R3)

        $anggotaHarian = $jadwal->anggotaHarian($minggu);
        $harian = $semua->where('jenis', JenisPenugasan::Harian)->keyBy(fn (Penugasan $p) => $p->tanggal->toDateString());
        $hitungHari = $harian->countBy('user_id');

        $hari = collect(range(0, 6))->map(function (int $i) use ($minggu, $harian, $jadwalPeriode) {
            $t = $minggu->senin()->addDays($i);

            return [
                'tanggal' => $t,
                'penugasan' => $harian->get($t->toDateString()),
                'periode' => $jadwalPeriode->aktifPada($t),
            ];
        });

        $sesi = [];
        foreach (SesiTugas::cases() as $s) {
            $aktif = $jadwal->tugasAktif($minggu, $s);
            $baris = $semua->whereIn('jenis', array_map(fn ($j) => JenisPenugasan::from($j), $s->jenisPenugasan()));
            $pool = $jadwal->pool($minggu, $s);
            $perTugas = $baris->whereNotNull('tugas_id')->keyBy('tugas_id');
            $dapat = $baris->pluck('user_id')->unique();

            $sesi[$s->value] = [
                'terbit' => $minggu->sudahTerbit($s),
                'aktif' => $aktif,
                'aktif_ids' => $aktif->pluck('id')->all(),
                'baris' => $baris,
                'per_tugas' => $perTugas,
                'pool' => $pool,
                'bebas' => $baris->isEmpty() ? collect() : $pool->reject(fn (User $u) => $dapat->contains($u->id))->values(),
            ];
        }

        return view('livewire.pengurus.atur-jadwal', [
            'minggu' => $minggu,
            'seninIni' => RotasiGeng::seninDari(CarbonImmutable::now()),
            'hari' => $hari,
            'anggotaHarian' => $anggotaHarian,
            'wargaLain' => User::query()->aktif()->where('geng_id', '!=', $minggu->geng_harian_id)->orderBy('nama')->get(),
            'periodeMinggu' => $hari->pluck('periode')->filter()->unique('id')->values(),
            'hitungHari' => $hitungHari,
            'harianAda' => $harian->isNotEmpty(),
            'sesi' => $sesi,
            'semuaTugas' => Tugas::query()->jenis(JenisTugas::KamisMinggu)->urut()->get(),
            'gengKamis' => $jadwal->gengKamis($minggu),
            'terkunci' => fn (?Penugasan $p) => $p && $p->status !== StatusPenugasan::Terjadwal,
        ]);
    }
}
