<?php

namespace App\Http\Controllers;

use App\Models\LogAktivitas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Halaman "Buat password baru" setelah login dengan password awal dari pengurus (PRD bagian 15 R2).
 * Sengaja berupa form biasa (bukan Livewire) supaya middleware WajibGantiPassword bisa memblokir
 * semua aksi Livewire lain selama password belum diganti.
 */
class PasswordBaruController extends Controller
{
    public function tampil(Request $request): View|RedirectResponse
    {
        if (! $request->user()->harus_ganti_password) {
            return redirect($request->user()->halamanAwal());
        }

        return view('auth.password-baru', ['user' => $request->user()]);
    }

    public function simpan(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'password.required' => 'Password baru wajib diisi.',
            'password.confirmed' => 'Ulangi password belum sama.',
            'password.min' => 'Password minimal 8 karakter.',
        ]);

        if ($user->password !== null && Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => 'Password baru tidak boleh sama dengan password awal.']);
        }

        $user->forceFill([
            'password' => $data['password'],
            'harus_ganti_password' => false,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        LogAktivitas::query()->create([
            'user_id' => $user->id,
            'aksi' => 'password.diganti_dari_awal',
            'subjek_type' => $user->getMorphClass(),
            'subjek_id' => $user->id,
            'ip_address' => $request->ip(),
        ]);

        return redirect($user->halamanAwal())->with('status', 'Password baru disimpan. Selamat datang!');
    }
}
