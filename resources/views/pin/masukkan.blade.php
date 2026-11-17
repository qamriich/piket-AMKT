<x-layouts.polos judul="Masukkan PIN">
    <span class="letup flex h-14 w-14 items-center justify-center rounded-2xl bg-rock/20 text-rock-muda">
        <x-ikon nama="kunci" :ukuran="28" />
    </span>
    <h1 class="naik mt-5 text-[28px] font-extrabold leading-tight">Masukkan PIN pengurus</h1>
    <p class="naik t1 mt-2 text-[15px] leading-relaxed text-krem">Panel pengurus butuh PIN di setiap sesi baru.</p>

    <form method="POST" action="{{ route('pin.verifikasi') }}" class="kartu-kaca naik t2 mt-6 flex flex-col gap-4 p-5">
        @csrf
        <div>
            <label for="pin" class="label-gelap">PIN</label>
            <input id="pin" name="pin" type="password" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="current-password" required autofocus
                   class="isian-gelap text-center text-2xl tracking-[0.6em]">
            @error('pin') <p class="galat-gelap">{{ $message }}</p> @enderror
        </div>
        <button class="tombol-merino w-full">Lanjut</button>
    </form>

    <div class="naik t3 mt-4 flex items-center justify-between gap-3 text-sm text-rock-muda">
        <span>Lupa PIN? Hubungi pengelola aplikasi.</span>
        <a href="{{ route('dashboard') }}" class="font-bold text-merino no-underline">← Beranda warga</a>
    </div>
</x-layouts.polos>
