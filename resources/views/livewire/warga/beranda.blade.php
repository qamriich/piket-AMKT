<div class="halaman flex flex-col">
    <section class="latar-hero flex flex-col gap-4 px-[18px] pb-6 pt-[26px] text-white sm:rounded-b-[28px]">
        <span class="kaca naik inline-flex items-center gap-2 self-start rounded-full px-3 py-[7px] text-[13px] font-semibold text-merino">
            <span class="denyut h-2 w-2 rounded-full bg-green-400"></span>
            {{ $sekarang->translatedFormat('l, j M') }} · {{ $geng?->nama ?? 'Geng' }} piket harian
        </span>

        <h1 class="naik t1 m-0 text-[34px] font-extrabold leading-[1.1] tracking-[-0.02em]">
            Halo, <span class="text-rock">{{ $user->namaDepan() }}</span>
        </h1>
        <p class="naik t1 m-0 text-base leading-relaxed text-krem">{{ $subjudul }}</p>

        <div class="grid grid-cols-2 gap-2.5">
            @if ($tenggat)
                <a href="{{ route('warga.piket') }}" wire:navigate
                   x-data="hitungMundur(@js($tenggat->tenggatLokal()->toIso8601String()), @js($tenggat->mulai()->toIso8601String()))"
                   class="kaca angkat naik t2 col-span-2 flex flex-col gap-3 rounded-[20px] p-4 text-left text-white no-underline">
                    <span class="flex w-full items-center justify-between gap-3">
                        <span class="flex min-w-0 flex-col gap-0.5">
                            <span class="text-[13px] text-rock-muda">Tenggat terdekat · {{ $tenggat->tenggatLokal()->translatedFormat('l H.i') }}</span>
                            <span class="truncate text-lg font-extrabold">{{ $tenggat->jenis === \App\Enums\JenisPenugasan::Harian ? 'Piket harian' : $tenggat->namaTugas() }}</span>
                        </span>
                        <span class="flex-none text-right text-[13px] text-rock-muda">
                            <strong class="text-[28px] text-merino" x-text="angka">{{ max(0, (int) floor($sekarang->diffInDays($tenggat->tenggatLokal()))) }}</strong>
                            <span x-text="satuan">hari lagi</span>
                        </span>
                    </span>
                    <span class="relative block h-1.5 w-full rounded-full bg-white/15">
                        <span class="isi-bar absolute inset-y-0 left-0 rounded-full bg-rock" :style="`width: ${persen}%`" style="width: 0"></span>
                    </span>
                </a>
            @else
                <div class="kaca naik t2 col-span-2 flex items-center gap-3 rounded-[20px] p-4">
                    <span class="flex h-10 w-10 flex-none items-center justify-center rounded-xl bg-green-400/20 text-green-300">
                        <x-ikon nama="centang" :ukuran="20" :tebal="2.5" />
                    </span>
                    <span class="flex flex-col gap-0.5">
                        <span class="text-[13px] text-rock-muda">Tenggat terdekat</span>
                        <span class="text-base font-extrabold">Tidak ada yang dikejar. Aman!</span>
                    </span>
                </div>
            @endif

            <div class="kaca angkat naik t3 flex flex-col gap-1.5 rounded-[20px] p-4">
                <span x-data x-hitung data-nilai="{{ $selesaiBulanIni }}" class="text-[32px] font-extrabold leading-none">{{ $selesaiBulanIni }}</span>
                <span class="text-[13px] font-semibold leading-snug text-krem">Piket selesai bulan ini</span>
            </div>
            <div class="kaca angkat naik t3 flex flex-col gap-1.5 rounded-[20px] p-4">
                <span x-data x-hitung data-nilai="{{ $bintang }}" @class(['text-[32px] font-extrabold leading-none', 'text-red-300' => $bintang > 0, 'text-white' => $bintang === 0])>{{ $bintang }}</span>
                <span class="text-[13px] font-semibold leading-snug text-krem">Bintang belum lunas</span>
            </div>
        </div>

        <a href="{{ route('warga.piket') }}" wire:navigate class="tombol-merino naik t4 no-underline">Lihat piket saya →</a>
    </section>

    <div class="flex flex-col gap-5 px-4 pb-8 pt-5">
        <section class="flex flex-col gap-3" aria-labelledby="judul-tahu">
            <h2 id="judul-tahu" class="judul-bagian">Yang perlu kamu tahu hari ini</h2>
            <div class="flex flex-col gap-2.5">
                @foreach ($kartu as $i => $k)
                    <div wire:key="kartu-{{ $i }}-{{ md5($k['kicker'].$k['judul']) }}" x-data x-muncul="{{ $i }}"
                         class="kartu-info flex min-h-[84px] items-center gap-3.5 rounded-[18px] border border-pasir bg-white py-3.5 pl-4 pr-3.5 shadow-[0_1px_2px_rgba(14,47,68,.04)]">
                        <span class="flex h-12 w-12 flex-none items-center justify-center rounded-[14px] transition-colors duration-300" style="background: {{ $k['latar'] }}; color: {{ $k['warna'] }}">
                            <x-ikon :nama="$k['ikon']" :ukuran="22" />
                        </span>
                        <span class="flex min-w-0 flex-1 flex-col gap-[3px]">
                            <span class="text-xs font-bold tracking-[0.04em] text-abu">{{ $k['kicker'] }}</span>
                            <span class="text-[17px] font-extrabold text-tinta">{{ $k['judul'] }}</span>
                            <span class="text-sm leading-snug text-abu">{{ $k['petunjuk'] }}</span>
                        </span>
                        @if ($k['aksi'])
                            <a href="{{ route($k['aksi']['rute']) }}" wire:navigate
                               @class(['tekan inline-flex min-h-[44px] flex-none items-center gap-1 whitespace-nowrap rounded-xl px-3.5 text-sm font-extrabold no-underline',
                                   'bg-venice text-white' => $k['aksi']['utama'],
                                   'border-[1.5px] border-rock bg-white text-venice' => ! $k['aksi']['utama']])>
                                {{ $k['aksi']['label'] }}<x-ikon nama="kanan" :ukuran="16" class="panah" />
                            </a>
                        @elseif ($k['badge'])
                            <span class="letup flex-none {{ $k['badge']['kelas'] }}">{{ $k['badge']['label'] }}</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    </div>
</div>
