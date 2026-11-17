<?php

namespace App\Livewire\Pengurus;

use App\Enums\JenisTugas;
use App\Livewire\Concerns\BeriUmpanBalik;
use App\Livewire\Concerns\KhususPengurus;
use App\Models\LogAktivitas;
use App\Models\Tugas;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Master daftar tugas. Aturannya sama dengan sebelumnya (TugasController).
 */
#[Layout('components.layouts.pengurus')]
#[Title('Master tugas')]
class TugasIndex extends Component
{
    use BeriUmpanBalik, KhususPengurus;

    /** @var array<string, string> jenis => nama tugas baru */
    public array $baru = ['harian' => '', 'kamis_minggu' => ''];

    public ?int $ubahId = null;

    public string $ubahNama = '';

    public $ubahUrutan = '';

    public bool $ubahAktifDefault = true;

    public function tambah(string $jenis): void
    {
        $this->validate([
            "baru.$jenis" => ['required', 'string', 'max:255'],
        ], [], ["baru.$jenis" => 'nama tugas']);

        $jenisEnum = JenisTugas::tryFrom($jenis);
        if (! $jenisEnum) {
            $this->addError('jenis', 'Jenis tugas tidak valid.');

            return;
        }

        $data = [
            'nama' => trim($this->baru[$jenis]),
            'jenis' => $jenisEnum,
            'aktif_default' => true,
            'urutan' => (int) Tugas::query()->where('jenis', $jenis)->max('urutan') + 1,
        ];
        $tugas = Tugas::create($data);
        LogAktivitas::catat('tugas.dibuat', $tugas, array_merge($data, ['jenis' => $jenis]));

        $this->baru[$jenis] = '';
        $this->toast("Tugas \"{$tugas->nama}\" ditambahkan.");
    }

    public function mulaiUbah(int $id): void
    {
        $t = Tugas::query()->findOrFail($id);
        $this->resetValidation();
        $this->ubahId = $t->id;
        $this->ubahNama = $t->nama;
        $this->ubahUrutan = $t->urutan;
        $this->ubahAktifDefault = $t->aktif_default;
    }

    public function batalUbah(): void
    {
        $this->reset('ubahId', 'ubahNama', 'ubahUrutan', 'ubahAktifDefault');
    }

    public function simpanUbah(): void
    {
        $tugas = Tugas::query()->findOrFail($this->ubahId);

        $data = $this->validate([
            'ubahNama' => ['required', 'string', 'max:255'],
            'ubahUrutan' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'ubahAktifDefault' => ['boolean'],
        ], [], ['ubahNama' => 'nama', 'ubahUrutan' => 'urutan']);

        $tugas->fill(['nama' => $data['ubahNama'], 'aktif_default' => $data['ubahAktifDefault']]);
        if ($data['ubahUrutan'] !== null && $data['ubahUrutan'] !== '') {
            $tugas->urutan = (int) $data['ubahUrutan'];
        }
        $berubah = $tugas->getDirty();
        $tugas->save();

        if ($berubah) {
            LogAktivitas::catat('tugas.diubah', $tugas, $berubah);
        }

        $this->batalUbah();
        $this->toast("Tugas \"{$tugas->nama}\" disimpan.");
    }

    /** Hapus: disembunyikan dari daftar (soft delete), riwayat penugasan lama tetap utuh. */
    public function hapus(int $id): void
    {
        $tugas = Tugas::query()->findOrFail($id);
        $tugas->delete();
        LogAktivitas::catat('tugas.dihapus', $tugas);

        $this->toast("Tugas \"{$tugas->nama}\" dihapus.");
    }

    public function render()
    {
        return view('livewire.pengurus.tugas-index', [
            'kelompok' => [
                ['harian', 'Harian', 'Dikerjakan semuanya oleh satu orang pada harinya.', Tugas::query()->jenis(JenisTugas::Harian)->urut()->get()],
                ['kamis_minggu', 'Kamis & Minggu', '1 orang per tugas. "Aktif default" menentukan toggle awal di minggu pertama.', Tugas::query()->jenis(JenisTugas::KamisMinggu)->urut()->get()],
            ],
        ]);
    }
}
