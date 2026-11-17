<div class="flex flex-col">
    <span class="letup flex h-14 w-14 items-center justify-center rounded-2xl bg-rock/20 text-rock-muda">
        <x-ikon nama="kunci" :ukuran="28" />
    </span>
    <h1 class="naik mt-5 text-[30px] font-extrabold leading-tight">Buat password baru</h1>
    <p class="naik t1 mt-2 text-[15px] leading-relaxed text-krem">Tulis password baru untuk akunmu, lalu kamu langsung masuk.</p>

    <form wire:submit="simpan" class="kartu-kaca naik t2 mt-6 flex flex-col gap-4 p-5" novalidate x-data="{ lihat: false }">
        <div>
            <label for="email" class="label-gelap">Email</label>
            <input id="email" type="email" wire:model="email" autocomplete="username" required class="isian-gelap">
            @error('email') <p class="galat-gelap" role="alert">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="password" class="label-gelap">Password baru</label>
            <div class="relative">
                <input id="password" :type="lihat ? 'text' : 'password'" type="password" wire:model="password" autocomplete="new-password" required autofocus class="isian-gelap pr-14">
                <button type="button" @click="lihat = !lihat" class="absolute inset-y-0 right-0 flex w-12 items-center justify-center text-rock-muda hover:text-white"
                        :aria-label="lihat ? 'Sembunyikan password' : 'Tampilkan password'">
                    <x-ikon nama="mata" :ukuran="20" />
                </button>
            </div>
            <p class="mt-1.5 text-[13px] text-rock-muda">Minimal 8 karakter.</p>
            @error('password') <p class="galat-gelap" role="alert">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="password_confirmation" class="label-gelap">Ulangi password</label>
            <input id="password_confirmation" :type="lihat ? 'text' : 'password'" type="password" wire:model="password_confirmation" autocomplete="new-password" required class="isian-gelap">
        </div>
        <button type="submit" class="tombol-merino w-full" wire:loading.attr="disabled" wire:target="simpan">
            <span wire:loading.remove wire:target="simpan">Simpan & masuk</span>
            <span wire:loading wire:target="simpan">Menyimpan…</span>
        </button>
    </form>

    <a href="{{ route('password.request') }}" wire:navigate class="naik t3 mt-5 inline-flex items-center gap-1 self-start text-sm font-bold text-merino no-underline">
        Link kedaluwarsa? Minta link baru →
    </a>
</div>
