<?php

namespace App\Livewire\Pengurus;

use App\Livewire\Concerns\BeriUmpanBalik;
use App\Livewire\Concerns\KhususPengurus;
use App\Models\Geng;
use App\Models\JadwalKuliah;
use App\Models\PeriodeHarian;
use App\Models\User;
use App\Services\DrafHarian;
use App\Services\JadwalHarianPeriode;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * PRD bagian 15 R1: jadwal harian periode. Disusun sekali (draf dari jadwal kuliah, B2),
 * lalu tiap minggu penugasan harian dibuat otomatis untuk geng yang bertugas.
 */
#[Layout('components.layouts.pengurus')]
#[Title('Jadwal harian periode')]
class JadwalHarian extends Component
{
    use BeriUmpanBalik, KhususPengurus;

    #[Url(as: 'periode', except: null)]
    public ?int $periodeId = null;

    public string $nama = '';

    public string $mulai = '';

    public string $selesai = '';

    /** @var array<int|string, array<int|string, string>> geng_id => [hari => user_id ('' = kosong)] */
    public array $peta = [];

    /** @var array<int|string, list<string>> geng_id => peringatan draf B2 */
    public array $peringatan = [];

    public bool $adaPerubahan = false;

    public int $versi = 0;

    public function mount(JadwalHarianPeriode $layanan): void
    {
        if ($this->periodeId === null || ! PeriodeHarian::query()->whereKey($this->periodeId)->exists()) {
            $this->periodeId = ($layanan->aktifPada(CarbonImmutable::now()) ?? PeriodeHarian::query()->latest('tanggal_mulai')->first())?->id;
        }
        $this->muat();
    }

    private function periode(): ?PeriodeHarian
    {
        return $this->periodeId ? PeriodeHarian::query()->with('hari')->find($this->periodeId) : null;
    }

    private function muat(): void
    {
        $periode = $this->periode();
        $this->peringatan = [];
        $this->adaPerubahan = false;

        if ($periode === null) {
            $this->periodeId = null;
            $this->nama = '';
            $this->mulai = CarbonImmutable::now()->toDateString();
            $this->selesai = CarbonImmutable::now()->addMonths(6)->toDateString();
            $this->peta = $this->petaKosong();

            return;
        }

        $this->nama = $periode->nama;
        $this->mulai = $periode->tanggal_mulai->toDateString();
        $this->selesai = $periode->tanggal_selesai->toDateString();
        $this->peta = collect(app(JadwalHarianPeriode::class)->peta($periode))
            ->map(fn (array $hari) => array_map(fn ($u) => $u === null ? '' : (string) $u, $hari))
            ->all();
    }

    private function petaKosong(): array
    {
        return Geng::query()->orderBy('urutan')->pluck('id')
            ->mapWithKeys(fn ($id) => [$id => array_fill(1, 7, '')])->all();
    }

    public function pilihPeriode(int $id): void
    {
        $this->periodeId = $id;
        $this->muat();
    }

    public function periodeBaru(): void
    {
        $this->periodeId = null;
        $this->muat();
    }

    public function updatedPeta(): void
    {
        $this->adaPerubahan = true;
    }

    public function simpanPeriode(JadwalHarianPeriode $layanan): void
    {
        $this->validate([
            'nama' => ['required', 'string', 'max:100'],
            'mulai' => ['required', 'date_format:Y-m-d'],
            'selesai' => ['required', 'date_format:Y-m-d', 'after_or_equal:mulai'],
        ], [
            'nama.required' => 'Nama periode wajib diisi.',
            'mulai.required' => 'Tanggal mulai wajib diisi.',
            'selesai.required' => 'Tanggal selesai wajib diisi.',
            'selesai.after_or_equal' => 'Tanggal selesai harus sama dengan atau setelah tanggal mulai.',
        ]);

        $baru = $this->periodeId === null;
        $this->coba(function () use ($layanan) {
            $periode = $layanan->simpanPeriode(
                $this->periode(),
                trim($this->nama),
                CarbonImmutable::createFromFormat('!Y-m-d', $this->mulai, config('app.timezone')),
                CarbonImmutable::createFromFormat('!Y-m-d', $this->selesai, config('app.timezone')),
            );
            $this->periodeId = $periode->id;
        }, $baru ? 'Periode dibuat. Sekarang isi hari tiap geng, lalu simpan.' : 'Periode disimpan.');
    }

    public function susunDraf(int $gengId, DrafHarian $draf): void
    {
        $geng = Geng::query()->findOrFail($gengId);
        $hasil = $draf->susunUntukGeng($geng);

        $this->peta[$gengId] = array_map(fn ($u) => $u === null ? '' : (string) $u, $hasil['jadwal']);
        $this->peringatan[$gengId] = $hasil['peringatan'];
        $this->adaPerubahan = true;
        $this->versi++;
        $this->toast("Draf {$geng->nama} disusun dari jadwal kuliah. Cek dulu, lalu simpan.");
    }

    public function susunSemua(DrafHarian $draf): void
    {
        foreach (Geng::query()->orderBy('urutan')->get() as $geng) {
            $hasil = $draf->susunUntukGeng($geng);
            $this->peta[$geng->id] = array_map(fn ($u) => $u === null ? '' : (string) $u, $hasil['jadwal']);
            $this->peringatan[$geng->id] = $hasil['peringatan'];
        }
        $this->adaPerubahan = true;
        $this->versi++;
        $this->toast('Draf semua geng disusun dari jadwal kuliah. Cek dulu, lalu simpan.');
    }

    public function simpanJadwal(JadwalHarianPeriode $layanan): void
    {
        $periode = $this->periode();
        if ($periode === null) {
            $this->toast('Simpan nama dan tanggal periode dulu.', 'gagal');

            return;
        }

        $ok = $this->coba(fn () => $layanan->simpanHari($periode, $this->peta),
            'Jadwal harian disimpan. Berlaku mulai minggu depan; minggu ini tidak berubah.');
        if ($ok) {
            $this->adaPerubahan = false;
        }
    }

    public function render(DrafHarian $draf)
    {
        $gengs = Geng::query()->orderBy('urutan')->get();
        $anggota = User::query()->aktif()->whereNotNull('geng_id')->orderBy('nama')->get()->groupBy('geng_id');
        $bisa = $draf->hariBisa($anggota->flatten());

        $dipilih = collect($this->peta)->flatten()->filter()->map(fn ($id) => (int) $id);
        $ganda = $dipilih->countBy()->filter(fn ($n) => $n > 1)->keys()->all();

        return view('livewire.pengurus.jadwal-harian', [
            'daftarPeriode' => PeriodeHarian::query()->orderByDesc('tanggal_mulai')->get(),
            'periodeAktif' => app(JadwalHarianPeriode::class)->aktifPada(CarbonImmutable::now()),
            'gengs' => $gengs,
            'anggota' => $anggota,
            'bisa' => $bisa,
            'ganda' => $ganda,
            'tanpaHari' => $gengs->mapWithKeys(fn (Geng $g) => [$g->id => ($anggota[$g->id] ?? collect())
                ->reject(fn (User $u) => in_array((string) $u->id, $this->peta[$g->id] ?? [], true))->values()]),
            'namaHari' => JadwalKuliah::NAMA_HARI,
        ]);
    }
}
