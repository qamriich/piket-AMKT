<?php

use App\Http\Controllers\KeluarController;
use App\Livewire\Auth;
use App\Livewire\Pengurus;
use App\Livewire\Warga;
use Illuminate\Support\Facades\Route;

// Beranda: tamu ke halaman login, yang sudah login ke halaman sesuai role.
Route::get('/', function () {
    return auth()->check() ? redirect(auth()->user()->halamanAwal()) : redirect()->route('login');
})->name('beranda');

// Login email + password (PRD bagian 2). Tidak ada pendaftaran sendiri.
Route::middleware('guest')->group(function () {
    Route::get('/login', Auth\Login::class)->name('login');
    Route::get('/lupa-password', Auth\LupaPassword::class)->name('password.request');
});

// Link dari email. Tidak memakai middleware guest supaya tetap bisa dibuka walau di perangkat itu ada akun lain yang sedang masuk.
Route::middleware('throttle:20,1')->group(function () {
    Route::get('/password/buat/{token}', Auth\AturPassword::class)->name('password.buat');
    Route::get('/password/reset/{token}', Auth\AturPassword::class)->name('password.reset');
});

Route::middleware('auth')->group(function () {
    Route::post('/keluar', KeluarController::class)->name('keluar');

    // Web warga: 5 tab + profil (Livewire, tanpa reload).
    Route::get('/dashboard', Warga\Beranda::class)->name('dashboard');
    Route::get('/piket', Warga\Piket::class)->name('warga.piket');
    Route::get('/utang', Warga\Utang::class)->name('warga.utang');
    Route::get('/jadwal', Warga\Jadwal::class)->name('warga.jadwal');
    Route::get('/kuliah', Warga\Kuliah::class)->name('warga.kuliah');
    Route::get('/profil', Warga\Profil::class)->name('warga.profil');

    // Panel pengurus: hanya role pengurus. Aksi Livewire ikut dicek lewat persistent middleware.
    Route::middleware('pengurus')->prefix('pengurus')->name('pengurus.')->group(function () {
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
