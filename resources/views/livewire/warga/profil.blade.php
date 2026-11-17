<div class="halaman flex flex-col gap-3.5 px-4 pb-8 pt-5">
    <section class="kartu naik flex flex-col items-center gap-2 rounded-3xl px-[18px] py-6 text-center">
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
            <span class="text-[15px] text-abu-tua">Email</span>
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

    <button type="button" class="tombol-garis naik t2"
            wire:loading.attr="disabled" wire:target="kirimUlangLink"
            @click="$store.konfirmasi.tanya({
                judul: 'Kirim link masuk baru?',
                pesan: @js('Link baru dikirim ke '.$user->email.'. Link lama di email sebelumnya tidak bisa dipakai lagi.'),
                tombol: 'Kirim link',
                aksi: () => $wire.kirimUlangLink(),
            })">
        <x-ikon nama="surat" :ukuran="20" />
        <span wire:loading.remove wire:target="kirimUlangLink">Kirim ulang link masuk ke email</span>
        <span wire:loading wire:target="kirimUlangLink">Mengirim…</span>
    </button>

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
