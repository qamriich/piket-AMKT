<x-layouts.polos judul="Buat password baru">
    <div class="flex flex-col">
        <span class="letup flex h-14 w-14 items-center justify-center rounded-2xl bg-rock/20 text-rock-muda">
            <x-ikon nama="kunci" :ukuran="28" />
        </span>
        <h1 class="naik mt-5 text-[30px] font-extrabold leading-tight">Halo, {{ $user->namaDepan() }}. Buat password baru</h1>
        <p class="naik t1 mt-2 text-[15px] leading-relaxed text-krem">
            Kamu masuk dengan password awal dari pengurus. Ganti dulu dengan password milikmu sendiri, lalu kamu bisa memakai web ini seperti biasa.
        </p>

        <form method="POST" action="{{ route('password.baru.simpan') }}" class="kartu-kaca naik t2 mt-6 flex flex-col gap-4 p-5" x-data="{ lihat: false }" novalidate>
            @csrf
            <div>
                <label for="password" class="label-gelap">Password baru</label>
                <div class="relative">
                    <input id="password" name="password" :type="lihat ? 'text' : 'password'" type="password" autocomplete="new-password" required autofocus class="isian-gelap pr-14">
                    <button type="button" @click="lihat = !lihat" class="absolute inset-y-0 right-0 flex w-12 items-center justify-center text-rock-muda hover:text-white"
                            :aria-label="lihat ? 'Sembunyikan password' : 'Tampilkan password'">
                        <x-ikon nama="mata" :ukuran="20" />
                    </button>
                </div>
                <p class="mt-1.5 text-[13px] text-rock-muda">Minimal 8 karakter dan berbeda dari password awal.</p>
                @error('password') <p class="galat-gelap" role="alert">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="password_confirmation" class="label-gelap">Ulangi password baru</label>
                <input id="password_confirmation" name="password_confirmation" :type="lihat ? 'text' : 'password'" type="password" autocomplete="new-password" required class="isian-gelap">
            </div>
            <button type="submit" class="tombol-merino w-full">Simpan password</button>
        </form>

        <form method="POST" action="{{ route('keluar') }}" class="naik t3 mt-5">
            @csrf
            <button type="submit" class="inline-flex items-center gap-1 text-sm font-bold text-merino">
                <x-ikon nama="keluar" :ukuran="16" /> Keluar dulu
            </button>
        </form>
    </div>
</x-layouts.polos>
