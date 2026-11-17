<div class="halaman grid gap-3.5 px-4 pb-8 pt-5 lg:mx-auto lg:w-full lg:max-w-4xl lg:grid-cols-[320px_minmax(0,1fr)] lg:items-start lg:gap-5 lg:px-0 lg:pt-8">
    <section class="kartu naik flex flex-col items-center gap-2 rounded-3xl px-[18px] py-6 text-center lg:row-span-5 lg:py-10">
        <div class="letup flex h-[76px] w-[76px] items-center justify-center rounded-full bg-langit text-[30px] font-extrabold text-venice">{{ $user->inisial() }}</div>
        <h1 class="m-0 text-2xl font-extrabold text-tinta">{{ $user->nama }}</h1>
        <div class="flex flex-wrap justify-center gap-1.5">
            <span class="badge bg-langit text-venice">{{ $user->geng?->nama ?? 'Belum masuk geng' }}</span>
            <span class="badge badge-netral">{{ $user->role->label() }}</span>
        </div>
    </section>

    <div class="naik t1 overflow-hidden rounded-[20px] border border-pasir bg-white">
        <div class="flex justify-between gap-3 px-[18px] py-3.5">
            <span class="text-[15px] text-abu-tua">Kamar</span>
            <span class="text-[15px] font-bold text-tinta">{{ $user->no_kamar ?: '–' }}</span>
        </div>
        <div class="flex justify-between gap-3 border-t border-pasir-muda px-[18px] py-3.5">
            <span class="text-[15px] text-abu-tua">Geng</span>
            <span class="text-[15px] font-bold text-tinta">{{ $user->geng?->nama ?? '–' }}</span>
        </div>
        <div class="flex justify-between gap-3 border-t border-pasir-muda px-[18px] py-3.5">
            <span class="text-[15px] text-abu-tua">Email (untuk login)</span>
            <span class="min-w-0 truncate text-[15px] font-bold text-tinta">{{ $user->email }}</span>
        </div>
        <div class="flex justify-between gap-3 border-t border-pasir-muda px-[18px] py-3.5">
            <span class="text-[15px] text-abu-tua">Piket selesai (total)</span>
            <span x-data x-hitung data-nilai="{{ $selesaiTotal }}" class="text-[15px] font-bold text-tinta">{{ $selesaiTotal }}</span>
        </div>
    </div>

    @if ($user->isPengurus())
        <a href="{{ route('pengurus.pengawasan') }}" class="tombol-utama naik t2 no-underline">
            <x-ikon nama="mata" :ukuran="20" /> Buka panel pengurus
        </a>
    @endif

    <section x-data="{ buka: false, lihat: false }" x-on:password-diganti.window="buka = false" class="kartu naik t2 overflow-hidden">
        <button type="button" @click="buka = !buka" :aria-expanded="buka"
                class="flex min-h-[56px] w-full items-center justify-between gap-3 px-[18px] text-left">
            <span class="flex items-center gap-2.5 text-[15px] font-extrabold text-tinta"><x-ikon nama="kunci" :ukuran="20" class="text-venice" /> Ganti password</span>
            <x-ikon nama="bawah" :ukuran="18" class="text-abu transition-transform" ::class="buka && 'rotate-180'" />
        </button>
        <form x-show="buka" x-cloak x-collapse wire:submit="gantiPassword" class="flex flex-col gap-3 border-t border-pasir-muda px-[18px] pb-[18px] pt-4" novalidate>
            <div>
                <label for="password_lama" class="label">Password lama</label>
                <input id="password_lama" :type="lihat ? 'text' : 'password'" type="password" wire:model="password_lama" autocomplete="current-password" class="isian">
                @error('password_lama') <p class="galat">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="password_baru" class="label">Password baru</label>
                <input id="password_baru" :type="lihat ? 'text' : 'password'" type="password" wire:model="password_baru" autocomplete="new-password" class="isian">
                @error('password_baru') <p class="galat">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="password_baru_confirmation" class="label">Ulangi password baru</label>
                <input id="password_baru_confirmation" :type="lihat ? 'text' : 'password'" type="password" wire:model="password_baru_confirmation" autocomplete="new-password" class="isian">
            </div>
            <label class="flex items-center gap-2 text-sm font-semibold text-abu-tua">
                <input type="checkbox" x-model="lihat" class="h-4 w-4 accent-[#16587B]"> Tampilkan password
            </label>
            <button type="submit" class="tombol-utama" wire:loading.attr="disabled" wire:target="gantiPassword">
                <span wire:loading.remove wire:target="gantiPassword">Simpan password baru</span>
                <span wire:loading wire:target="gantiPassword">Menyimpan…</span>
            </button>
            <p class="m-0 text-[13px] text-abu">Lupa password lama? Keluar, lalu pakai "Lupa password" di halaman masuk.</p>
        </form>
    </section>

    <form method="POST" action="{{ route('keluar') }}" class="naik t3">
        @csrf
        <button type="submit" class="tekan flex min-h-[48px] w-full items-center justify-center gap-2 rounded-[14px] text-[15px] font-bold text-abu-tua hover:text-tinta">
            <x-ikon nama="keluar" :ukuran="18" /> Keluar dari perangkat ini
        </button>
    </form>

    <p class="m-0 text-center text-[13px] leading-normal text-[#64748B]">
        Data diri diubah oleh pengurus.
        @if ($tataTertib->isNotEmpty()) Hubungi {{ $tataTertib->join(', ', ' atau ') }} (Tata Tertib) kalau ada yang salah. @endif
    </p>
</div>
