@php
    use App\Enums\JenisPenugasan;
    use App\Enums\StatusPenugasan;
@endphp
<div class="halaman flex flex-col gap-3.5 px-4 pb-8 pt-5">
    <div class="flex flex-col gap-1">
        <h1 class="m-0 text-[26px] font-extrabold text-tinta">Utang piket</h1>
        <p class="m-0 text-[15px] leading-normal text-abu-tua">
            Piket yang lewat batas waktu jadi bintang. Bintang Kamis dan Minggu otomatis dijadwalkan ulang.
            Bintang harian kamu ganti sendiri di hari kosong.
        </p>
    </div>

    @forelse ($bintang as $i => $b)
        @php
            $belumLunas = $b->status === StatusPenugasan::Bintang;
            $bayarBerjalan = $b->pembayaran->firstWhere('status', StatusPenugasan::BayarBintang);
            $bayarSelesai = $b->pembayaran->firstWhere('status', StatusPenugasan::Selesai);
            $pengajuan = $menunggu->get($b->id);
            $kicker = match ($b->jenis) {
                JenisPenugasan::Harian => 'Piket harian',
                JenisPenugasan::Kamis => 'Piket Kamis',
                JenisPenugasan::Minggu => 'Piket Minggu',
                JenisPenugasan::KerjaBakti => 'Kerja bakti',
            };
        @endphp

        @if ($belumLunas)
            <article wire:key="bintang-{{ $b->id }}" x-data="{ pilih: null }" x-muncul="{{ $i }}" class="kartu flex flex-col gap-3.5 p-[18px]">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex min-w-0 flex-col gap-1">
                        <span class="text-[13px] font-bold uppercase text-venice">{{ $kicker }} · {{ $b->tanggalLokal()->translatedFormat('l, j M') }}</span>
                        <span class="text-base leading-normal text-[#334155]">
                            @if ($b->jenis === JenisPenugasan::Harian)
                                Belum dikerjakan sampai jam 09.00.
                            @else
                                {{ $b->jenis === JenisPenugasan::KerjaBakti ? 'Kerja bakti' : $b->namaTugas() }} belum selesai sampai batasnya.
                            @endif
                        </span>
                    </div>
                    <x-badge :status="$b->status" class="flex-none" />
                </div>

                @if ($b->jenis !== JenisPenugasan::Harian)
                    <div class="flex items-start gap-3 rounded-[14px] bg-langit px-4 py-3 text-sm leading-normal text-venice-tua">
                        <x-ikon nama="kalender" :ukuran="18" class="mt-0.5 flex-none" />
                        @if ($bayarBerjalan)
                            <span>Sudah dijadwalkan ulang: <strong>{{ $bayarBerjalan->namaTugas() }}</strong>, {{ $bayarBerjalan->tanggalLokal()->translatedFormat('l, j M') }}.</span>
                        @else
                            <span>Akan dijadwalkan ulang otomatis di piket {{ $b->jenis === JenisPenugasan::Kamis ? 'Kamis' : 'Minggu' }} berikutnya.</span>
                        @endif
                    </div>
                @elseif ($pengajuan)
                    <div role="status" class="flex flex-col gap-1 rounded-[14px] border border-amber-200 bg-amber-50 px-4 py-3.5">
                        <span class="text-[15px] font-extrabold text-amber-900">Pengajuan terkirim</span>
                        <span class="text-sm leading-normal text-amber-900">Ganti di {{ $pengajuan->tanggal_diajukan->translatedFormat('l, j M') }}. Tunggu jawaban pengurus, ya.</span>
                    </div>
                @elseif ($bayarBerjalan)
                    <div class="rounded-[14px] bg-langit px-4 py-3 text-sm text-venice-tua">
                        Dijadwalkan ganti {{ $bayarBerjalan->tanggalLokal()->translatedFormat('l, j M') }}.
                    </div>
                @else
                    <div class="flex flex-col gap-2.5">
                        <span class="text-sm font-bold text-tinta">Pilih hari kosong untuk ganti:</span>
                        @if ($tanggalKosong->isEmpty())
                            <p class="m-0 rounded-[14px] border border-dashed border-pasir px-4 py-3 text-sm text-abu-tua">
                                Belum ada hari kosong di jadwal yang sudah terbit. Cek lagi setelah jadwal minggu depan terbit.
                            </p>
                        @else
                            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                @foreach ($tanggalKosong as $t)
                                    <button type="button" class="tekan flex min-h-[60px] flex-col items-center justify-center gap-0.5 rounded-[14px] border-2"
                                            :class="pilih === @js($t->toDateString()) ? 'border-venice bg-sorot text-venice' : 'border-pasir bg-white text-[#334155]'"
                                            :aria-pressed="pilih === @js($t->toDateString())"
                                            @click="pilih = @js($t->toDateString())">
                                        <span class="text-[15px] font-extrabold">{{ $t->translatedFormat('l') }}</span>
                                        <span class="text-[13px] font-semibold">{{ $t->translatedFormat('j M') }}</span>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                        <button type="button" disabled class="tombol-utama w-full">Ajukan ganti ke pengurus</button>
                        <span class="text-center text-[13px] text-abu">Pengajuan lewat aplikasi segera tersedia. Sementara itu, sampaikan pilihan harimu ke Tata Tertib.</span>
                    </div>
                @endif
            </article>
        @else
            <div wire:key="bintang-{{ $b->id }}" x-data x-muncul="{{ $i }}" class="kartu flex items-center justify-between gap-3 px-[18px] py-4">
                <div class="flex min-w-0 flex-col gap-0.5">
                    <span class="text-[15px] font-extrabold text-tinta">{{ $kicker }} · {{ $b->tanggalLokal()->translatedFormat('j M') }}</span>
                    <span class="text-sm text-abu-tua">
                        @if ($bayarSelesai) Sudah dibayar {{ $bayarSelesai->tanggalLokal()->translatedFormat('j M') }} @else Sudah lunas @endif
                    </span>
                </div>
                <x-badge :status="$b->status" class="flex-none" />
            </div>
        @endif
    @empty
        <div class="kartu flex flex-col items-center gap-3 px-6 py-10 text-center">
            <span class="letup flex h-16 w-16 items-center justify-center rounded-full bg-green-100 text-green-700">
                <x-ikon nama="centang" :ukuran="30" :tebal="2.5" class="centang-gambar" />
            </span>
            <h2 class="m-0 text-xl font-extrabold text-tinta">Kamu tidak punya utang piket</h2>
            <p class="m-0 max-w-xs text-[15px] leading-normal text-abu-tua">Pertahankan, ya! Selesaikan piket sebelum batas waktunya supaya tidak jadi bintang.</p>
        </div>
    @endforelse
</div>
