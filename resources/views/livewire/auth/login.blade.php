<div class="flex flex-col">
    <span class="kaca naik inline-flex items-center gap-2 self-start rounded-full px-3 py-[7px] text-[13px] font-semibold text-merino">
        <span class="denyut h-2 w-2 rounded-full bg-green-400"></span>
        Jadwal piket asrama
    </span>
    <h1 class="naik t1 mt-4 text-[34px] font-extrabold leading-[1.1] tracking-[-0.02em]">
        Masuk ke <span class="text-rock">Piket AMKT</span>
    </h1>
    <p class="naik t1 mt-2 text-base leading-relaxed text-krem">Pakai email yang didaftarkan pengurus dan password milikmu.</p>

    <form wire:submit="masuk" class="kartu-kaca naik t2 mt-6 flex flex-col gap-4 p-5" novalidate>
        <div>
            <label for="email" class="label-gelap">Email</label>
            <input id="email" type="email" wire:model="email" autocomplete="username" inputmode="email" required autofocus class="isian-gelap" placeholder="nama@email.com">
            @error('email') <p class="galat-gelap" role="alert">{{ $message }}</p> @enderror
        </div>
        <div x-data="{ lihat: false }">
            <div class="flex items-center justify-between">
                <label for="password" class="label-gelap">Password</label>
                <a href="{{ route('password.request') }}" wire:navigate class="mb-1.5 text-sm font-bold text-rock-muda no-underline hover:text-merino">Lupa password?</a>
            </div>
            <div class="relative">
                <input id="password" :type="lihat ? 'text' : 'password'" type="password" wire:model="password" autocomplete="current-password" required class="isian-gelap pr-14">
                <button type="button" @click="lihat = !lihat" class="absolute inset-y-0 right-0 flex w-12 items-center justify-center text-rock-muda hover:text-white"
                        :aria-label="lihat ? 'Sembunyikan password' : 'Tampilkan password'">
                    <x-ikon nama="mata" :ukuran="20" />
                </button>
            </div>
            @error('password') <p class="galat-gelap" role="alert">{{ $message }}</p> @enderror
        </div>
        <label class="flex min-h-[44px] cursor-pointer items-center gap-3 text-[15px] font-semibold text-krem">
            <input type="checkbox" wire:model="ingat" class="h-5 w-5 accent-[#84B3CE]">
            Ingat saya selama 30 hari
        </label>
        <button type="submit" class="tombol-merino w-full" wire:loading.attr="disabled" wire:target="masuk">
            <span wire:loading.remove wire:target="masuk">Masuk →</span>
            <span wire:loading wire:target="masuk">Memeriksa…</span>
        </button>
    </form>

    <div class="kaca naik t3 mt-4 flex items-start gap-3 rounded-[20px] p-4">
        <span class="flex h-10 w-10 flex-none items-center justify-center rounded-xl bg-rock/20 text-rock-muda">
            <x-ikon nama="surat" :ukuran="20" />
        </span>
        <p class="m-0 text-sm leading-normal text-krem">
            Belum punya password? Buka email <strong class="text-white">"Buat password"</strong> yang dikirim saat akunmu dibuat,
            atau tekan <strong class="text-white">Lupa password</strong>. Akun dibuat oleh pengurus, tidak ada pendaftaran sendiri.
        </p>
    </div>
</div>
