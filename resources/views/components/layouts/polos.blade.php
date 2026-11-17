@props(['judul' => null])
<!DOCTYPE html>
<html lang="id">
<head>
    @include('partials.head', ['judul' => $judul])
</head>
<body class="latar-hero min-h-screen text-white antialiased">
    <div class="mx-auto flex min-h-screen max-w-md flex-col px-5 py-8">
        <a href="{{ route('beranda') }}" class="naik flex items-center gap-2.5 text-white no-underline">
            <x-logo :ukuran="42" />
            <span class="flex flex-col">
                <span class="text-[17px] font-extrabold">Piket AMKT</span>
                <span class="text-xs text-rock-muda">AMKT Putri Mulawarman Makassar</span>
            </span>
        </a>

        <main class="flex flex-1 flex-col justify-center py-10">
            {{ $slot }}
        </main>

        <footer class="text-[13px] leading-normal text-rock-muda">
            © {{ now()->year }} Asrama Mahasiswa Kalimantan Timur Putri Mulawarman Makassar
        </footer>
    </div>

    @include('partials.umpan-balik')
    @livewireScriptConfig
</body>
</html>
