<?php

namespace App\Livewire\Pengurus;

use App\Livewire\Concerns\BeriUmpanBalik;
use App\Livewire\Concerns\KhususPengurus;
use App\Models\Geng;
use App\Models\LogAktivitas;
use App\Models\Pengaturan as ModelPengaturan;
use App\Services\RotasiGeng;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Pengaturan rotasi geng (F2) dan batas jam kuliah (B2). Aturannya sama dengan sebelumnya (PengaturanController).
 */
#[Layout('components.layouts.pengurus')]
#[Title('Pengaturan')]
class Pengaturan extends Component
{
    use BeriUmpanBalik, KhususPengurus;

    public string $minggu_acuan = '';

    public $geng_acuan = '';

    public string $batas_jam_kuliah = '';

    public function mount(): void
    {
        $p = ModelPengaturan::semua();
        $this->minggu_acuan = (string) $p['minggu_acuan'];
        $this->geng_acuan = (string) $p['geng_acuan'];
        $this->batas_jam_kuliah = substr((string) $p['batas_jam_kuliah'], 0, 5);
    }

    public function simpan(): void
    {
        $data = $this->validate([
            'minggu_acuan' => ['required', 'date_format:Y-m-d'],
            'geng_acuan' => ['required', 'integer', 'between:1,'.RotasiGeng::JUMLAH_GENG],
            'batas_jam_kuliah' => ['required', 'date_format:H:i'],
        ], [], [
            'minggu_acuan' => 'minggu acuan',
            'geng_acuan' => 'geng acuan',
            'batas_jam_kuliah' => 'batas jam kuliah',
        ]);

        // Minggu acuan selalu disimpan sebagai hari Senin dari minggu yang dipilih.
        $data['minggu_acuan'] = RotasiGeng::seninDari(
            CarbonImmutable::parse($data['minggu_acuan'], config('app.timezone'))
        )->toDateString();

        $lama = ModelPengaturan::semua();
        $berubah = [];
        foreach ($data as $key => $value) {
            if ((string) ($lama[$key] ?? '') !== (string) $value) {
                $berubah[$key] = ['dari' => $lama[$key] ?? null, 'ke' => (string) $value];
                ModelPengaturan::simpan($key, (string) $value);
            }
        }

        if ($berubah) {
            LogAktivitas::catat('pengaturan.diubah', null, $berubah);
        }

        $this->minggu_acuan = $data['minggu_acuan'];
        $this->toast('Pengaturan disimpan.');
    }

    public function render()
    {
        return view('livewire.pengurus.pengaturan', [
            'gengs' => Geng::query()->withCount('anggotaAktif')->orderBy('urutan')->get()->keyBy('urutan'),
            'pratinjau' => RotasiGeng::dariPengaturan()->pratinjau(8),
            'seninIni' => RotasiGeng::seninDari(now()),
        ]);
    }
}
