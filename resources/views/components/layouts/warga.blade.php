@props(['tab' => null, 'title' => null])
@php
    $user = auth()->user();
    $ringkas = app(\App\Services\RingkasanWarga::class);
    $daftarTab = [
        ['beranda', 'dashboard', 'Beranda', 'beranda', null],
        ['piket', 'warga.piket', 'Piket', 'piket', $ringkas->adaPiketBelumSelesai($user) ? '#FF8A2B' : null],
        ['utang', 'warga.utang', 'Utang', 'utang', $ringkas->jumlahBintangBelumLunas($user) > 0 ? '#DC2626' : null],
        ['jadwal', 'warga.jadwal', 'Jadwal', 'jadwal', null],
        ['kuliah', 'warga.kuliah', 'Kuliah', 'kuliah', null],
    ];
    $indeks = collect($daftarTab)->search(fn ($t) => $t[0] === $tab);
    $indeks = $indeks === false ? -1 : $indeks;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    @include('partials.head', ['judul' => $title])
</head>
<body class="min-h-screen bg-merino text-teks antialiased">
    <div class="flex min-h-screen flex-col">
        <header class="sticky top-0 z-40 border-b border-pasir bg-white/95 backdrop-blur supports-[backdrop-filter]:bg-white/90">
            <div class="mx-auto flex max-w-2xl items-center justify-between gap-3 px-[18px] pb-2.5 pt-3.5">
                <a href="{{ route('dashboard') }}" wire:navigate class="flex min-w-0 items-center gap-2.5 text-left" aria-label="Ke beranda">
                    <x-logo :ukuran="40" />
                    <span class="flex min-w-0 flex-col">
                        <span class="text-[17px] font-extrabold text-tinta">Piket AMKT</span>
                        <span class="truncate text-xs font-semibold text-abu-tua">AMKT Putri Mulawarman Makassar</span>
                    </span>
                </a>
                <a href="{{ route('warga.profil') }}" wire:navigate aria-label="Profil saya"
                   @if ($tab === 'profil') aria-current="page" @endif
                   @class(['tekan flex h-[42px] w-[42px] flex-none items-center justify-center rounded-full text-base font-extrabold',
                       'bg-venice text-white' => $tab === 'profil', 'bg-langit text-venice' => $tab !== 'profil'])>{{ $user->inisial() }}</a>
            </div>

            <nav aria-label="Menu utama" x-data="penandaTab({{ $indeks }})" class="relative mx-auto grid max-w-2xl grid-cols-5 px-1.5 pt-0.5">
                @foreach ($daftarTab as $i => [$kunci, $rute, $label, $ikon, $titik])
                    @php $aktif = $kunci === $tab; @endphp
                    <a href="{{ route($rute) }}" wire:navigate @if ($aktif) aria-current="page" @endif
                       @class(['tab-ikon flex min-h-[60px] flex-col items-center justify-center gap-[5px] pb-2.5 pt-1.5', 'text-venice' => $aktif, 'text-abu' => ! $aktif])>
                        <span @class(['relative flex h-[30px] items-center justify-center rounded-full transition-all duration-300', 'w-[52px] bg-langit' => $aktif, 'w-10' => ! $aktif])>
                            <x-ikon :nama="$ikon" :ukuran="22" />
                            @if ($titik)
                                <span class="letup absolute right-2 top-0.5 h-[9px] w-[9px] rounded-full border-2 border-white" style="background: {{ $titik }}"></span>
                                <span class="sr-only">(ada yang perlu dilihat)</span>
                            @endif
                        </span>
                        <span class="text-xs font-bold leading-none">{{ $label }}</span>
                    </a>
                @endforeach
                @if ($indeks >= 0)
                    <span aria-hidden="true" class="absolute -bottom-px h-[3px] rounded-t-[3px] bg-venice"
                          :class="animasi && 'transition-[left] duration-[350ms] ease-[cubic-bezier(.2,.7,.2,1)]'"
                          style="width: calc((100% - 12px) / 5 - 24px); left: calc(18px + (100% - 12px) / 5 * {{ $indeks }})"
                          :style="`width: calc((100% - 12px) / 5 - 24px); left: calc(18px + (100% - 12px) / 5 * ${posisi})`"></span>
                @endif
            </nav>
        </header>

        <main class="mx-auto w-full max-w-2xl flex-1">
            {{ $slot }}
        </main>

        <footer class="mx-4 mt-auto flex flex-col gap-2.5 border-t border-pasir pb-7 pt-[18px] text-[13px] text-abu sm:mx-auto sm:w-full sm:max-w-2xl sm:px-4">
            <span class="leading-normal">© {{ now()->year }} Asrama Mahasiswa Kalimantan Timur Putri Mulawarman Makassar</span>
            @if ($user->isPengurus())
                <a href="{{ route('pengurus.pengawasan') }}" class="font-bold text-venice no-underline hover:text-tinta">Buka panel pengurus →</a>
            @endif
        </footer>
    </div>

    @include('partials.umpan-balik')
    @livewireScriptConfig
</body>
</html>
