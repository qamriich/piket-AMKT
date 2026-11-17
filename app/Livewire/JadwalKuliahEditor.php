<?php

namespace App\Livewire;

use App\Livewire\Concerns\BeriUmpanBalik;
use App\Models\JadwalKuliah;
use App\Models\LogAktivitas;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * F3: jadwal kuliah per hari. Diisi warga sendiri, atau oleh pengurus dari halaman Warga & geng.
 */
class JadwalKuliahEditor extends Component
{
    use BeriUmpanBalik;

    #[Locked]
    public int $userId;

    #[Locked]
    public bool $gelap = false;

    public bool $ubah = false;

    /** @var list<array{hari: int, mulai: string, selesai: string}> */
    public array $sesi = [];

    public function mount(?int $userId = null, bool $gelap = false): void
    {
        $this->userId = $userId ?? auth()->id();
        $this->gelap = $gelap;
        $this->pastikanBoleh();
        $this->muatUlang();
    }

    private function pastikanBoleh(): void
    {
        $saya = auth()->user();
        abort_unless($saya && ($saya->id === $this->userId || $saya->isPengurus()), 403);
    }

    private function muatUlang(): void
    {
        $this->sesi = JadwalKuliah::query()
            ->where('user_id', $this->userId)
            ->orderBy('hari')->orderBy('jam_mulai')
            ->get()
            ->map(fn (JadwalKuliah $k) => [
                'hari' => $k->hari,
                'mulai' => substr((string) $k->jam_mulai, 0, 5),
                'selesai' => substr((string) $k->jam_selesai, 0, 5),
            ])->all();
    }

    public function mulaiUbah(): void
    {
        $this->ubah = true;
    }

    public function batal(): void
    {
        $this->resetValidation();
        $this->muatUlang();
        $this->ubah = false;
    }

    public function tambah(int $hari): void
    {
        $this->sesi[] = ['hari' => max(1, min(7, $hari)), 'mulai' => '08:00', 'selesai' => '10:00'];
    }

    public function hapus(int $indeks): void
    {
        unset($this->sesi[$indeks]);
        $this->sesi = array_values($this->sesi);
    }

    public function simpan(): void
    {
        $this->pastikanBoleh();

        $data = $this->validate([
            'sesi' => ['array', 'max:40'],
            'sesi.*.hari' => ['required', 'integer', 'between:1,7'],
            'sesi.*.mulai' => ['required', 'date_format:H:i'],
            'sesi.*.selesai' => ['required', 'date_format:H:i', 'after:sesi.*.mulai'],
        ], [
            'sesi.*.mulai.required' => 'Jam mulai wajib diisi.',
            'sesi.*.mulai.date_format' => 'Jam mulai tidak valid.',
            'sesi.*.selesai.required' => 'Jam selesai wajib diisi.',
            'sesi.*.selesai.date_format' => 'Jam selesai tidak valid.',
            'sesi.*.selesai.after' => 'Jam selesai harus setelah jam mulai.',
        ]);

        DB::transaction(function () use ($data) {
            JadwalKuliah::query()->where('user_id', $this->userId)->delete();
            foreach ($data['sesi'] as $s) {
                JadwalKuliah::create([
                    'user_id' => $this->userId,
                    'hari' => $s['hari'],
                    'jam_mulai' => $s['mulai'],
                    'jam_selesai' => $s['selesai'],
                ]);
            }
        });

        if ($this->userId !== auth()->id()) {
            LogAktivitas::catat('jadwal_kuliah.diubah', User::find($this->userId), ['jumlah' => count($data['sesi'])]);
        }

        $this->muatUlang();
        $this->ubah = false;
        $this->toast('Jadwal kuliah disimpan.');
    }

    public function render()
    {
        $perHari = [];
        foreach (JadwalKuliah::NAMA_HARI as $h => $nama) {
            $perHari[$h] = ['nama' => $nama, 'sesi' => []];
        }
        foreach ($this->sesi as $i => $s) {
            $perHari[(int) $s['hari']]['sesi'][$i] = $s;
        }

        return view('livewire.jadwal-kuliah-editor', ['perHari' => $perHari]);
    }
}
