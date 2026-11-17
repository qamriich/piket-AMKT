<x-layouts.polos judul="Buat PIN pengurus">
    <span class="letup flex h-14 w-14 items-center justify-center rounded-2xl bg-rock/20 text-rock-muda">
        <x-ikon nama="perisai" :ukuran="28" />
    </span>
    <h1 class="naik mt-5 text-[28px] font-extrabold leading-tight">Buat PIN pengurus</h1>
    <p class="naik t1 mt-2 text-[15px] leading-relaxed text-krem">
        PIN 6 angka ini diminta setiap kali kamu membuka panel pengurus di sesi baru, supaya link pribadimu
        tidak langsung memberi akses pengurus kalau sampai tersebar.
    </p>

    <form method="POST" action="{{ route('pin.simpan') }}" class="kartu-kaca naik t2 mt-6 flex flex-col gap-4 p-5">
        @csrf
        <div>
            <label for="pin" class="label-gelap">PIN baru</label>
            <input id="pin" name="pin" type="password" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="new-password" required autofocus
                   class="isian-gelap text-center text-2xl tracking-[0.6em]">
            @error('pin') <p class="galat-gelap">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="pin_confirmation" class="label-gelap">Ulangi PIN</label>
            <input id="pin_confirmation" name="pin_confirmation" type="password" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="new-password" required
                   class="isian-gelap text-center text-2xl tracking-[0.6em]">
        </div>
        <button class="tombol-merino w-full">Simpan PIN</button>
    </form>
</x-layouts.polos>
