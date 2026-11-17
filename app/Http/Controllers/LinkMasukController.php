<?php

namespace App\Http\Controllers;

use App\Http\Middleware\RequirePin;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class LinkMasukController extends Controller
{
    /** GET /m/{token}: masuk lewat link pribadi. */
    public function masuk(Request $request, string $token): Response
    {
        $user = User::cariLewatToken($token);

        if (! $user) {
            return response()->view('auth.link-tidak-valid', status: 404);
        }

        if (Auth::check()) {
            Auth::logout();
        }

        // Cookie "ingat saya" berlaku 1 tahun (config/auth.php).
        Auth::login($user, remember: true);
        $request->session()->regenerate();
        $request->session()->forget(RequirePin::SESSION_KEY);

        // Redirect supaya token tidak tertinggal di address bar atau riwayat.
        return redirect()->route('dashboard');
    }

    public function keluar(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('beranda')
            ->with('status', 'Kamu sudah keluar. Buka lagi link pribadi dari email untuk masuk.');
    }
}
