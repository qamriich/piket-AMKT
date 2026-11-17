@php
    use App\Enums\JenisPenugasan;
    use App\Enums\StatusPengajuan;
    use App\Enums\StatusPenugasan;
    $kicker = fn ($b) => match ($b->jenis) {
        JenisPenugasan::Harian => 'Piket harian',
        JenisPenugasan::Kamis => 'Piket Kamis',
        JenisPenugasan::Minggu => 'Piket Minggu',
        JenisPenugasan::KerjaBakti => 'Kerja bakti',
    };
    $adaKosong = $kosongPerMinggu->isNotEmpty();
@endphp
<div class="halaman flex flex-col gap-3.5 px-4 pb-8 pt-5 lg:px-0 lg:pt-8">
    <div class="flex flex-col gap-1">
        <h1 class="m-0 text-[26px] font-extrabold text-tinta">Utang piket</h1>
        <p class="m-0 text-[15px] leading-normal text-abu-tua">
            Piket yang lewat batas waktu jadi bintang. Bintang Kamis dan Minggu otomatis dijadwalkan ulang.
            Bintang harian kamu ganti sendiri: tekan <strong>Ajukan ganti</strong>, pilih hari kosong, lalu kirim ke pengurus.
        </p>
    </div>

    {{-- R6: setiap bintang yang belum lunas tampil sebagai kartu. --}}
    <div class="grid gap-3.5 lg:grid-cols-2 lg:items-start">
    @forelse ($belumLunas as $i => $b)
        @php
            $harian = $b->jenis === JenisPenugasan::Harian;
            $bayarBerjalan = $b->pembayaran->firstWhere('status', StatusPenugasan::BayarBintang);
            $bayarTerlewat = $b->pembayaran->firstWhere('status', StatusPenugasan::Bintang);
            $ajuan = $pengajuanTerakhir->get($b->id);
            $menunggu = $ajuan?->status === StatusPengajuan::Menunggu;
            $bisaAjukan = $harian && ! $menunggu && ! $bayarBerjalan;
        @endphp
        <article wire:key="bintang-{{ $b->id }}" x-data="{ buka: false, pilih: null }" x-muncul="{{ $i }}"
                 x-on:ganti-terkirim.window="if ($event.detail.id === {{ $b->id }}) { buka = false; pilih = null }"
                 class="kartu flex flex-col gap-3.5 border-l-4 border-l-red-400 p-[18px]">
            <div class="flex items-start justify-between gap-3">
                <div class="flex min-w-0 flex-col gap-1">
                    <span class="text-[13px] font-bold uppercase text-venice">{{ $kicker($b) }} · {{ $b->tanggalLokal()->translatedFormat('l, j M') }}</span>
                    <span class="text-base leading-normal text-[#334155]">
                        @if ($harian)
                            Belum dikerjakan sampai jam 09.00.
                        @else
                            {{ $b->jenis === JenisPenugasan::KerjaBakti ? 'Kerja bakti' : $b->namaTugas() }} belum selesai sampai batasnya.
                        @endif
                    </span>
                </div>
                <x-badge :status="$b->status" class="flex-none" />
            </div>

            @if (! $harian)
                <div class="flex items-start gap-3 rounded-[14px] bg-langit px-4 py-3 text-sm leading-normal text-venice-tua">
                    <x-ikon nama="kalender" :ukuran="18" class="mt-0.5 flex-none" />
                    @if ($bayarBerjalan)
                        <span>Sudah dijadwalkan ulang: <strong>{{ $bayarBerjalan->namaTugas() }}</strong>, {{ $bayarBerjalan->tanggalLokal()->translatedFormat('l, j M') }}.</span>
                    @else
                        <span>Akan dijadwalkan ulang di {{ $b->jenis === JenisPenugasan::Kamis ? 'Kamis' : 'Minggu' }} berikutnya.</span>
                    @endif
                </div>
            @else
                {{-- Status pengajuan terakhir: Menunggu, Diterima, atau Ditolak. --}}
                @if ($menunggu)
                    <div role="status" class="flex items-start justify-between gap-3 rounded-[14px] border border-amber-200 bg-amber-50 px-4 py-3.5">
                        <span class="flex flex-col gap-0.5">
                            <span class="text-[15px] font-extrabold text-amber-900">Pengajuan terkirim</span>
                            <span class="text-sm leading-normal text-amber-900">Ganti di {{ $ajuan->tanggal_diajukan->translatedFormat('l, j M') }}. Tunggu jawaban pengurus, ya.</span>
                        </span>
                        <x-badge :status="$ajuan->status" class="flex-none" />
                    </div>
                @elseif ($bayarBerjalan)
                    <div role="status" class="flex items-start justify-between gap-3 rounded-[14px] border border-green-200 bg-green-50 px-4 py-3.5">
                        <span class="flex flex-col gap-0.5">
                            <span class="text-[15px] font-extrabold text-green-900">Pengajuan diterima</span>
                            <span class="text-sm leading-normal text-green-900">
                                Piket ganti {{ $bayarBerjalan->tanggalLokal()->translatedFormat('l, j M') }} jam 04.00–09.00. Kalau selesai, bintang ini lunas.
                                @if ($ajuan?->catatan) <br>Catatan pengurus: {{ $ajuan->catatan }} @endif
                            </span>
                        </span>
                        <x-badge :status="StatusPengajuan::Diterima" class="flex-none" />
                    </div>
                @elseif ($ajuan?->status === StatusPengajuan::Ditolak)
                    <div role="status" class="flex items-start justify-between gap-3 rounded-[14px] border border-red-200 bg-red-50 px-4 py-3.5">
                        <span class="flex flex-col gap-0.5">
                            <span class="text-[15px] font-extrabold text-red-900">Pengajuan ditolak</span>
                            <span class="text-sm leading-normal text-red-900">
                                Untuk {{ $ajuan->tanggal_diajukan->translatedFormat('l, j M') }}.
                                @if ($ajuan->catatan) Catatan pengurus: {{ $ajuan->catatan }}. @endif
                                Pilih hari lain, ya.
                            </span>
                        </span>
                        <x-badge :status="$ajuan->status" class="flex-none" />
                    </div>
                @elseif ($bayarTerlewat)
                    <div class="rounded-[14px] bg-red-50 px-4 py-3 text-sm leading-normal text-red-900">
                        Piket gantinya ({{ $bayarTerlewat->tanggalLokal()->translatedFormat('l, j M') }}) terlewat. Utangmu tetap 1; ajukan hari lain.
                    </div>
                @endif

                @if ($bisaAjukan)
                    <button type="button" class="tombol-utama w-full" x-show="!buka" @click="buka = true" :aria-expanded="buka">
                        <x-ikon nama="kalender" :ukuran="20" /> Ajukan ganti
                    </button>

                    <div x-show="buka" x-collapse x-cloak class="flex flex-col gap-3 border-t border-pasir pt-3">
                        <span class="text-[15px] font-extrabold text-tinta">Pilih satu hari kosong:</span>
                        @if (! $adaKosong)
                            <p class="m-0 rounded-[14px] border border-dashed border-pasir px-4 py-3 text-sm text-abu-tua">
                                Belum ada hari kosong di jadwal harian. Cek lagi nanti, atau tanyakan ke Tata Tertib.
                            </p>
                        @else
                            <div class="flex max-h-[360px] flex-col gap-3 overflow-y-auto pr-1">
                                @foreach ($kosongPerMinggu as $senin => $hari)
                                    @php $s = \Carbon\CarbonImmutable::parse($senin); @endphp
                                    <div class="flex flex-col gap-2">
                                        <span class="text-[13px] font-bold uppercase tracking-[0.04em] text-abu">Minggu {{ $s->format('j') }}–{{ $s->addDays(6)->translatedFormat('j M') }}</span>
                                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                            @foreach ($hari as $t)
                                                <button type="button" class="tekan flex min-h-[60px] flex-col items-center justify-center gap-0.5 rounded-[14px] border-2"
                                                        :class="pilih === @js($t->toDateString()) ? 'border-venice bg-sorot text-venice' : 'border-pasir bg-white text-[#334155]'"
                                                        :aria-pressed="pilih === @js($t->toDateString())"
                                                        @click="pilih = @js($t->toDateString())">
                                                    <span class="text-[15px] font-extrabold">{{ $t->translatedFormat('l') }}</span>
                                                    <span class="text-[13px] font-semibold">{{ $t->translatedFormat('j M') }}</span>
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <button type="button" class="tombol-garis" @click="buka = false; pilih = null">Batal</button>
                                <button type="button" class="tombol-utama" :disabled="!pilih"
                                        @click="pilih && $wire.ajukan({{ $b->id }}, pilih)"
                                        wire:loading.attr="disabled" wire:target="ajukan">
                                    <span wire:loading.remove wire:target="ajukan">Kirim</span>
                                    <span wire:loading wire:target="ajukan">Mengirim…</span>
                                </button>
                            </div>
                            <span class="text-center text-[13px] text-abu" x-text="pilih ? 'Pengurus akan menerima atau menolak, lalu kamu dapat email.' : 'Pilih salah satu hari dulu.'">Pilih salah satu hari dulu.</span>
                        @endif
                    </div>
                @endif
            @endif
        </article>
    @empty
        <div class="kartu flex flex-col items-center gap-3 px-6 py-10 text-center lg:col-span-2">
            <span class="letup flex h-16 w-16 items-center justify-center rounded-full bg-green-100 text-green-700">
                <x-ikon nama="centang" :ukuran="30" :tebal="2.5" class="centang-gambar" />
            </span>
            <h2 class="m-0 text-xl font-extrabold text-tinta">Kamu tidak punya utang piket</h2>
            <p class="m-0 max-w-xs text-[15px] leading-normal text-abu-tua">Pertahankan, ya! Selesaikan piket sebelum batas waktunya supaya tidak jadi bintang.</p>
        </div>
    @endforelse
    </div>

    {{-- Riwayat bintang yang sudah lunas. --}}
    @if ($lunas->isNotEmpty())
        <h2 class="mb-0 mt-3 text-lg font-extrabold text-tinta">Riwayat bintang lunas</h2>
        <div class="grid gap-2.5 lg:grid-cols-2">
            @foreach ($lunas as $b)
                @php $bayarSelesai = $b->pembayaran->firstWhere('status', StatusPenugasan::Selesai); @endphp
                <div wire:key="lunas-{{ $b->id }}" class="kartu flex items-center justify-between gap-3 px-[18px] py-4">
                    <div class="flex min-w-0 flex-col gap-0.5">
                        <span class="text-[15px] font-extrabold text-tinta">{{ $kicker($b) }} · {{ $b->tanggalLokal()->translatedFormat('j M') }}</span>
                        <span class="text-sm text-abu-tua">
                            @if ($bayarSelesai) Sudah dibayar {{ $bayarSelesai->tanggalLokal()->translatedFormat('l, j M') }} @else Sudah lunas @endif
                        </span>
                    </div>
                    <x-badge :status="$b->status" class="flex-none" />
                </div>
            @endforeach
        </div>
    @endif
</div>
