<?php

namespace App\Http\Controllers;

use App\Http\Middleware\RequirePin;
use App\Models\LogAktivitas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PinController extends Controller
{
    private const MAKS_PERCOBAAN = 5;

    public function buat(Request $request): View|RedirectResponse
    {
        if ($request->user()->punyaPin()) {
            return redirect()->route('pin.masukkan');
        }

        return view('pin.buat');
    }

    public function simpan(Request $request): RedirectResponse
    {
        abort_if($request->user()->punyaPin(), 403, 'PIN sudah dibuat.');

        $data = $request->validate([
            'pin' => ['required', 'digits:6', 'confirmed'],
        ], [
            'pin.digits' => 'PIN harus 6 digit angka.',
            'pin.confirmed' => 'Konfirmasi PIN tidak sama.',
        ]);

        $request->user()->setPin($data['pin']);
        RequirePin::tandaiTerverifikasi($request);
        LogAktivitas::catat('pin.dibuat', $request->user());

        return redirect()->intended(route('pengurus.pengawasan'))
            ->with('status', 'PIN berhasil dibuat. Ingat baik-baik, PIN akan diminta setiap sesi baru.');
    }

    public function masukkan(Request $request): View|RedirectResponse
    {
        if (! $request->user()->punyaPin()) {
            return redirect()->route('pin.buat');
        }

        return view('pin.masukkan');
    }

    public function verifikasi(Request $request): RedirectResponse
    {
        $user = $request->user();
        $kunci = 'pin:'.$user->id;

        if (RateLimiter::tooManyAttempts($kunci, self::MAKS_PERCOBAAN)) {
            throw ValidationException::withMessages([
                'pin' => 'Terlalu banyak percobaan. Coba lagi dalam '.RateLimiter::availableIn($kunci).' detik.',
            ]);
        }

        $request->validate(['pin' => ['required', 'digits:6']], ['pin.digits' => 'PIN harus 6 digit angka.']);

        if (! $user->cekPin($request->input('pin'))) {
            RateLimiter::hit($kunci, 300);
            throw ValidationException::withMessages(['pin' => 'PIN salah.']);
        }

        RateLimiter::clear($kunci);
        $request->session()->regenerate();
        RequirePin::tandaiTerverifikasi($request);

        return redirect()->intended(route('pengurus.pengawasan'));
    }
}
