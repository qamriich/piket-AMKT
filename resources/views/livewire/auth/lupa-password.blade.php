<div class="flex flex-col">
    <span class="letup flex h-14 w-14 items-center justify-center rounded-2xl bg-rock/20 text-rock-muda">
        <x-ikon nama="kunci" :ukuran="28" />
    </span>
    <h1 class="naik mt-5 text-[30px] font-extrabold leading-tight">Lupa password</h1>
    <p class="naik t1 mt-2 text-[15px] leading-relaxed text-krem">Tulis email akunmu. Kami kirim link untuk membuat password baru.</p>

    @if ($terkirim)
        <div role="status" class="letup mt-6 flex items-start gap-3 rounded-[20px] border border-green-300/30 bg-green-500/10 p-4">
            <span class="flex h-9 w-9 flex-none items-center justify-center rounded-full bg-green-500 text-white">
                <x-ikon nama="centang" :ukuran="18" :tebal="3" />
            </span>
            <p class="m-0 text-[15px] leading-normal text-green-50">
                Kalau <strong>{{ $email }}</strong> terdaftar dan aktif, link reset sudah dikirim. Cek kotak masuk dan folder spam.
                Link berlaku 60 menit.
            </p>
        </div>
    @else
        <form wire:submit="kirim" class="kartu-kaca naik t2 mt-6 flex flex-col gap-4 p-5" novalidate>
            <div>
                <label for="email" class="label-gelap">Email</label>
                <input id="email" type="email" wire:model="email" autocomplete="username" inputmode="email" required autofocus class="isian-gelap">
                @error('email') <p class="galat-gelap" role="alert">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="tombol-merino w-full" wire:loading.attr="disabled" wire:target="kirim">
                <span wire:loading.remove wire:target="kirim">Kirim link reset</span>
                <span wire:loading wire:target="kirim">Mengirim…</span>
            </button>
        </form>
    @endif

    <a href="{{ route('login') }}" wire:navigate class="naik t3 mt-5 inline-flex items-center gap-1 self-start text-sm font-bold text-merino no-underline">
        <x-ikon nama="kiri" :ukuran="16" /> Kembali ke halaman masuk
    </a>
</div>
