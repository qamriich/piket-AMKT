<?php

use App\Http\Controllers\KeluarController;
use App\Http\Controllers\PasswordBaruController;
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

// Link "Lupa password" dari email. Tidak memakai middleware guest supaya tetap bisa dibuka walau di perangkat itu ada akun lain.
Route::get('/password/reset/{token}', Auth\AturPassword::class)->middleware('throttle:20,1')->name('password.reset');

Route::middleware('auth')->group(function () {
    Route::post('/keluar', KeluarController::class)->name('keluar');

    // Wajib ganti password awal saat login pertama (PRD bagian 15 R2).
    Route::get('/password/baru', [PasswordBaruController::class, 'tampil'])->name('password.baru');
    Route::post('/password/baru', [PasswordBaruController::class, 'simpan'])->middleware('throttle:10,1')->name('password.baru.simpan');

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
        Route::get('/jadwal-harian', Pengurus\JadwalHarian::class)->name('jadwal-harian');
        Route::get('/pengajuan', Pengurus\Pengajuan::class)->name('pengajuan');
        Route::get('/warga', Pengurus\WargaIndex::class)->name('warga.index');
        Route::get('/warga/baru', Pengurus\WargaForm::class)->name('warga.create');
        Route::get('/warga/{warga}/ubah', Pengurus\WargaForm::class)->name('warga.edit');
        Route::get('/tugas', Pengurus\TugasIndex::class)->name('tugas.index');
        Route::get('/rekap', Pengurus\Rekap::class)->name('rekap');
        Route::get('/pengaturan', Pengurus\Pengaturan::class)->name('pengaturan');
    });
});
