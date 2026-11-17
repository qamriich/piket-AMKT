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
            <div class="mx-auto flex max-w-2xl items-center justify-between gap-2.5 px-[18px] pb-2.5 pt-3.5 lg:max-w-[1200px] lg:gap-6 lg:px-6 lg:py-3">
                <a href="{{ route('dashboard') }}" wire:navigate class="flex min-w-0 items-center gap-2.5 text-left no-underline" aria-label="Ke beranda">
                    <x-logo :ukuran="40" />
                    <span class="flex min-w-0 flex-col">
                        <span class="whitespace-nowrap text-[17px] font-extrabold text-tinta">Piket AMKT</span>
                        <span class="truncate text-xs font-semibold text-abu-tua">AMKT Putri Mulawarman Makassar</span>
                    </span>
                </a>

                {{-- Laptop: 5 menu teks dengan ikon di navbar atas (PRD bagian 14) --}}
                <nav aria-label="Menu utama" class="hidden flex-1 items-center justify-center gap-1 lg:flex">
                    @foreach ($daftarTab as [$kunci, $rute, $label, $ikon, $titik])
                        @php $aktif = $kunci === $tab; @endphp
                        <a href="{{ route($rute) }}" wire:navigate @if ($aktif) aria-current="page" @endif
                           @class(['tekan relative inline-flex min-h-[44px] items-center gap-2 rounded-full px-4 text-[15px] font-bold no-underline',
                               'bg-langit text-venice' => $aktif, 'text-abu hover:bg-merino hover:text-tinta' => ! $aktif])>
                            <x-ikon :nama="$ikon" :ukuran="19" />
                            {{ $label }}
                            @if ($titik)
                                <span class="letup absolute right-2 top-2 h-2 w-2 rounded-full" style="background: {{ $titik }}"></span>
                                <span class="sr-only">(ada yang perlu dilihat)</span>
                            @endif
                        </a>
                    @endforeach
                </nav>

                <div class="flex flex-none items-center gap-2">
                    @if ($user->isPengurus())
                        <a href="{{ route('pengurus.pengawasan') }}"
                           class="tekan inline-flex min-h-[42px] items-center gap-1.5 whitespace-nowrap rounded-full bg-navy px-3 text-[13px] font-extrabold text-white no-underline shadow-[0_6px_16px_rgba(11,53,80,.25)] sm:px-3.5 lg:px-4 lg:text-sm">
                            <x-ikon nama="mata" :ukuran="17" class="hidden sm:block" /> Panel pengurus
                        </a>
                    @endif
                    <a href="{{ route('warga.profil') }}" wire:navigate aria-label="Profil saya"
                       @if ($tab === 'profil') aria-current="page" @endif
                       @class(['tekan flex h-[42px] w-[42px] flex-none items-center justify-center rounded-full text-base font-extrabold no-underline',
                           'bg-venice text-white' => $tab === 'profil', 'bg-langit text-venice' => $tab !== 'profil'])>{{ $user->inisial() }}</a>
                </div>
            </div>

            {{-- HP: 5 tab ikon di bawah header --}}
            <nav aria-label="Menu utama" x-data="penandaTab({{ $indeks }})" class="relative mx-auto grid max-w-2xl grid-cols-5 px-1.5 pt-0.5 lg:hidden">
                @foreach ($daftarTab as $i => [$kunci, $rute, $label, $ikon, $titik])
                    @php $aktif = $kunci === $tab; @endphp
                    <a href="{{ route($rute) }}" wire:navigate @if ($aktif) aria-current="page" @endif
                       @class(['tab-ikon flex min-h-[60px] flex-col items-center justify-center gap-[5px] pb-2.5 pt-1.5 no-underline', 'text-venice' => $aktif, 'text-abu' => ! $aktif])>
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

        <main class="mx-auto w-full max-w-2xl flex-1 lg:max-w-[1200px] lg:px-6">
            {{ $slot }}
        </main>

        <footer class="mx-4 mt-auto flex flex-col gap-2.5 border-t border-pasir pb-7 pt-[18px] text-[13px] text-abu sm:mx-auto sm:w-full sm:max-w-2xl sm:px-4 lg:max-w-[1200px] lg:flex-row lg:items-center lg:justify-between lg:px-6">
            <span class="leading-normal">© {{ now()->year }} Asrama Mahasiswa Kalimantan Timur Putri Mulawarman Makassar</span>
            <form method="POST" action="{{ route('keluar') }}">
                @csrf
                <button type="submit" class="inline-flex min-h-[40px] items-center gap-1.5 font-bold text-venice hover:text-tinta">
                    <x-ikon nama="keluar" :ukuran="16" /> Keluar
                </button>
            </form>
        </footer>
    </div>

    @include('partials.umpan-balik')
    @livewireScriptConfig
</body>
</html>
