@props(['title' => null])
@php
    $user = auth()->user();
    $jumlahPengajuan = \App\Models\PengajuanGantiBintang::query()->where('status', 'menunggu')->count();
    $menu = [
        ['pengurus.pengawasan', 'pengurus.pengawasan', 'Pengawasan', 'mata', null],
        ['pengurus.jadwal', 'pengurus.jadwal|pengurus.jadwal-harian', 'Atur jadwal', 'kalender', null],
        ['pengurus.pengajuan', 'pengurus.pengajuan', 'Pengajuan', 'kotak-masuk', $jumlahPengajuan ?: null],
        ['pengurus.warga.index', 'pengurus.warga.*', 'Warga & geng', 'pengguna', null],
        ['pengurus.rekap', 'pengurus.rekap', 'Rekap bulanan', 'grafik', null],
        ['pengurus.pengaturan', 'pengurus.pengaturan|pengurus.tugas.*', 'Pengaturan', 'gerigi', null],
    ];
    $aktif = fn (string $pola) => collect(explode('|', $pola))->contains(fn ($p) => request()->routeIs($p));
    $menuBawah = [$menu[0], $menu[1], $menu[2], $menu[3]];
    $lainnyaAktif = $aktif($menu[4][1]) || $aktif($menu[5][1]);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    @include('partials.head', ['judul' => $title])
</head>
<body class="latar-panel min-h-screen text-white antialiased">
    <div class="lg:flex">
        <nav aria-label="Menu pengurus" class="sticky top-0 hidden h-screen w-[248px] flex-none flex-col gap-[22px] overflow-y-auto border-r border-white/[.08] bg-navy-tua/50 px-4 py-6 lg:flex">
            <a href="{{ route('pengurus.pengawasan') }}" wire:navigate class="flex items-center gap-2.5 px-2 text-white no-underline">
                <x-logo :ukuran="40" />
                <span class="flex flex-col">
                    <span class="text-[17px] font-extrabold">Piket AMKT</span>
                    <span class="text-xs text-rock-muda">Panel pengurus</span>
                </span>
            </a>

            <div class="flex flex-col gap-1">
                @foreach ($menu as [$rute, $pola, $label, $ikon, $angka])
                    <a href="{{ route($rute) }}" wire:navigate @if ($aktif($pola)) aria-current="page" @endif
                       @class(['navi flex min-h-[46px] items-center gap-3 rounded-xl px-3 text-[15px] no-underline', 'font-bold' => $aktif($pola), 'font-semibold text-krem' => ! $aktif($pola)])>
                        <x-ikon :nama="$ikon" :ukuran="20" />
                        <span class="flex-1">{{ $label }}</span>
                        @if ($angka)
                            <span class="letup flex h-[22px] min-w-[22px] items-center justify-center rounded-full bg-rock px-1.5 text-xs font-extrabold text-navy">{{ $angka }}</span>
                        @endif
                    </a>
                @endforeach
            </div>

            <div class="mt-auto flex flex-col gap-1">
                <span class="px-3 pb-1 text-xs font-bold tracking-[0.06em] text-rock-muda">TAMPILAN WARGA</span>
                <a href="{{ route('dashboard') }}" class="navi flex min-h-[46px] items-center gap-3 rounded-xl px-3 text-[15px] font-semibold text-krem no-underline">
                    <x-ikon nama="rumah" :ukuran="20" /> Beranda warga
                </a>
                <a href="{{ route('warga.jadwal') }}" class="navi flex min-h-[46px] items-center gap-3 rounded-xl px-3 text-[15px] font-semibold text-krem no-underline">
                    <x-ikon nama="daftar" :ukuran="20" /> Jadwal minggu ini
                </a>
                <a href="{{ route('warga.piket') }}" class="tombol-merino mt-2 !min-h-[50px] text-[15px] no-underline">Lihat piket saya →</a>
                <form method="POST" action="{{ route('keluar') }}" class="mt-1">
                    @csrf
                    <button type="submit" class="navi flex min-h-[44px] w-full items-center gap-3 rounded-xl px-3 text-sm font-semibold text-rock-muda">
                        <x-ikon nama="keluar" :ukuran="18" /> Keluar
                    </button>
                </form>
            </div>
        </nav>

        <div class="min-w-0 flex-1">
            <header class="sticky top-0 z-30 flex items-center justify-between gap-3 border-b border-white/[.08] bg-navy/85 px-4 py-3 backdrop-blur lg:hidden">
                <a href="{{ route('pengurus.pengawasan') }}" wire:navigate class="flex items-center gap-2.5 text-white no-underline">
                    <x-logo :ukuran="34" />
                    <span class="flex flex-col">
                        <span class="text-base font-extrabold leading-tight">Piket AMKT</span>
                        <span class="text-xs text-rock-muda">Panel pengurus</span>
                    </span>
                </a>
                <a href="{{ route('dashboard') }}" class="tekan flex h-10 w-10 items-center justify-center rounded-full bg-rock text-base font-extrabold text-navy no-underline" aria-label="Ke tampilan warga">{{ $user->inisial() }}</a>
            </header>

            <main class="mx-auto flex max-w-[1240px] flex-col gap-[26px] px-4 pb-32 pt-6 sm:px-6 lg:px-8 lg:pb-11 lg:pt-8">
                {{ $slot }}

                <footer class="mt-2 flex flex-wrap items-center justify-between gap-3 border-t border-white/10 pt-[18px] text-[13px] text-rock-muda">
                    <span>© {{ now()->year }} Asrama Mahasiswa Kalimantan Timur Putri Mulawarman Makassar</span>
                    <span class="flex gap-[18px]">
                        <span>Prioritas pemeriksa: Tata Tertib, Bendahara, Sekretaris, Ketua</span>
                    </span>
                </footer>
            </main>
        </div>
    </div>

    {{-- Menu bawah di HP --}}
    <nav aria-label="Menu pengurus" x-data="{ lainnya: false }" class="fixed inset-x-0 bottom-0 z-40 lg:hidden">
        <div x-show="lainnya" x-cloak x-transition.opacity class="fixed inset-0 bg-navy-tua/60 backdrop-blur-sm" @click="lainnya = false"></div>
        <div x-show="lainnya" x-cloak
             x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
             x-transition:leave="transition duration-200 ease-in" x-transition:leave-end="translate-y-full"
             class="relative mx-2 mb-2 flex flex-col gap-1 rounded-3xl border border-white/10 bg-navy p-3 shadow-[0_-12px_40px_rgba(0,0,0,.35)]">
            @foreach ([$menu[4], $menu[5], ['pengurus.tugas.index', 'pengurus.tugas.*', 'Master tugas', 'daftar-centang', null]] as [$rute, $pola, $label, $ikon])
                <a href="{{ route($rute) }}" wire:navigate @click="lainnya = false" @if ($aktif($pola)) aria-current="page" @endif
                   class="navi flex min-h-[52px] items-center gap-3 rounded-xl px-3 text-base font-semibold text-krem no-underline">
                    <x-ikon :nama="$ikon" :ukuran="20" /> {{ $label }}
                </a>
            @endforeach
            <a href="{{ route('dashboard') }}" class="navi flex min-h-[52px] items-center gap-3 rounded-xl px-3 text-base font-semibold text-krem no-underline">
                <x-ikon nama="rumah" :ukuran="20" /> Tampilan warga
            </a>
            <form method="POST" action="{{ route('keluar') }}">
                @csrf
                <button type="submit" class="navi flex min-h-[52px] w-full items-center gap-3 rounded-xl px-3 text-base font-semibold text-rock-muda">
                    <x-ikon nama="keluar" :ukuran="20" /> Keluar
                </button>
            </form>
        </div>
        <div class="grid grid-cols-5 border-t border-white/10 bg-navy-tua/90 pb-[env(safe-area-inset-bottom)] backdrop-blur-lg">
            @foreach ($menuBawah as [$rute, $pola, $label, $ikon, $angka])
                @php $on = $aktif($pola); @endphp
                <a href="{{ route($rute) }}" wire:navigate @if ($on) aria-current="page" @endif
                   @class(['tab-ikon flex min-h-[64px] flex-col items-center justify-center gap-1 no-underline', 'text-white' => $on, 'text-rock-muda' => ! $on])>
                    <span @class(['relative flex h-[30px] items-center justify-center rounded-full transition-all duration-300', 'w-[52px] bg-rock text-navy' => $on, 'w-10' => ! $on])>
                        <x-ikon :nama="$ikon" :ukuran="21" />
                        @if ($angka)
                            <span class="absolute -right-1 -top-1 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-orange-400 px-1 text-[11px] font-extrabold text-navy">{{ $angka }}</span>
                        @endif
                    </span>
                    <span class="text-[11px] font-bold leading-none">{{ \Illuminate\Support\Str::before($label, ' &') }}</span>
                </a>
            @endforeach
            <button type="button" @click="lainnya = !lainnya" :aria-expanded="lainnya"
                    @class(['tab-ikon flex min-h-[64px] flex-col items-center justify-center gap-1', 'text-white' => $lainnyaAktif, 'text-rock-muda' => ! $lainnyaAktif])>
                <span @class(['flex h-[30px] items-center justify-center rounded-full', 'w-[52px] bg-rock text-navy' => $lainnyaAktif, 'w-10' => ! $lainnyaAktif])>
                    <x-ikon nama="lainnya" :ukuran="21" />
                </span>
                <span class="text-[11px] font-bold leading-none">Lainnya</span>
            </button>
        </div>
    </nav>

    @include('partials.umpan-balik')
    @livewireScriptConfig
</body>
</html>
