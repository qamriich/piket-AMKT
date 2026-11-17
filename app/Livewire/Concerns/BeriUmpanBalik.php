<?php

namespace App\Livewire\Concerns;

use Illuminate\Validation\ValidationException;

/**
 * Toast "Berhasil disimpan" dan penanganan galat dari service tanpa reload.
 */
trait BeriUmpanBalik
{
    protected function toast(string $pesan, string $jenis = 'sukses'): void
    {
        $this->dispatch('toast', pesan: $pesan, jenis: $jenis);
    }

    /**
     * Menjalankan aksi; galat validasi dari service ditampilkan sebagai toast merah.
     */
    protected function coba(callable $aksi, ?string $pesanSukses = null): bool
    {
        try {
            $aksi();
        } catch (ValidationException $e) {
            $this->toast((string) collect($e->errors())->flatten()->first(), 'gagal');

            return false;
        }

        if ($pesanSukses !== null) {
            $this->toast($pesanSukses);
        }

        return true;
    }
}
