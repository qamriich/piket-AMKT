<?php

namespace App\Livewire\Concerns;

/**
 * Lapisan pengaman kedua untuk komponen pengurus: setiap request Livewire (termasuk aksi tanpa reload)
 * dicek role-nya, selain oleh persistent middleware EnsurePengurus (lihat AppServiceProvider).
 */
trait KhususPengurus
{
    public function bootKhususPengurus(): void
    {
        abort_unless(auth()->user()?->isPengurus(), 403, 'Halaman ini hanya untuk pengurus.');
    }
}
