<?php

use App\Http\Controllers\LinkMasukController;
use App\Http\Controllers\PinController;
use App\Livewire\Pengurus;
use App\Livewire\Warga;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : view('beranda');
})->name('beranda');

// Link pribadi: satu-satunya cara masuk.
Route::get('/m/{token}', [LinkMasukController::class, 'masuk'])
    ->middleware('throttle:20,1')
    ->name('link.masuk');

Route::middleware('auth')->group(function () {
    Route::post('/keluar', [LinkMasukController::class, 'keluar'])->name('keluar');

    // Web warga: 5 tab + profil (Livewire, tanpa reload).
    Route::get('/dashboard', Warga\Beranda::class)->name('dashboard');
    Route::get('/piket', Warga\Piket::class)->name('warga.piket');
    Route::get('/utang', Warga\Utang::class)->name('warga.utang');
    Route::get('/jadwal', Warga\Jadwal::class)->name('warga.jadwal');
    Route::get('/kuliah', Warga\Kuliah::class)->name('warga.kuliah');
    Route::get('/profil', Warga\Profil::class)->name('warga.profil');

    // PIN pengurus.
    Route::middleware('pengurus')->prefix('pin')->name('pin.')->group(function () {
        Route::get('/buat', [PinController::class, 'buat'])->name('buat');
        Route::post('/buat', [PinController::class, 'simpan'])->name('simpan');
        Route::get('/', [PinController::class, 'masukkan'])->name('masukkan');
        Route::post('/', [PinController::class, 'verifikasi'])->name('verifikasi');
    });

    // Panel pengurus: role pengurus + PIN. Aksi Livewire ikut dicek lewat persistent middleware.
    Route::middleware(['pengurus', 'pin'])->prefix('pengurus')->name('pengurus.')->group(function () {
        Route::get('/', Pengurus\Pengawasan::class)->name('pengawasan');
        Route::get('/jadwal', Pengurus\AturJadwal::class)->name('jadwal');
        Route::get('/pengajuan', Pengurus\Pengajuan::class)->name('pengajuan');
        Route::get('/warga', Pengurus\WargaIndex::class)->name('warga.index');
        Route::get('/warga/baru', Pengurus\WargaForm::class)->name('warga.create');
        Route::get('/warga/{warga}/ubah', Pengurus\WargaForm::class)->name('warga.edit');
        Route::get('/tugas', Pengurus\TugasIndex::class)->name('tugas.index');
        Route::get('/rekap', Pengurus\Rekap::class)->name('rekap');
        Route::get('/pengaturan', Pengurus\Pengaturan::class)->name('pengaturan');
    });
});
