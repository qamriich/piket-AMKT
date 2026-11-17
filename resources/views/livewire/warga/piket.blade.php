@php
    use App\Enums\JenisPenugasan;
    use App\Enums\StatusPenugasan;
@endphp
<div class="halaman flex flex-col gap-3.5 px-4 pb-8 pt-5">
    <h1 class="m-0 text-[26px] font-extrabold text-tinta">Piket saya</h1>

    @forelse ($piket as $i => $p)
        @php
            $tanggal = $p->tanggalLokal();
            $bisa = $p->status->belumSelesai() && $p->sudahBisaDikerjakan($sekarang);
            $warnaGaris = match ($p->status) {
                StatusPenugasan::Selesai => 'border-green-500',
                StatusPenugasan::Bintang => 'border-red-400',
                StatusPenugasan::BayarBintang => 'border-orange-300',
                StatusPenugasan::Lunas => 'border-teal-300',
                default => $bisa ? 'border-venice' : 'border-pasir',
            };
            $kicker = match ($p->jenis) {
                JenisPenugasan::Harian => 'Piket harian',
                JenisPenugasan::Kamis => 'Piket Kamis',
                JenisPenugasan::Minggu => 'Piket Minggu',
                JenisPenugasan::KerjaBakti => 'Kerja bakti',
            };
        @endphp
        <article wire:key="piket-{{ $p->id }}" x-data x-muncul="{{ $i }}"
                 class="flex flex-col gap-3.5 rounded-[20px] border-2 bg-white p-[18px] transition-colors duration-300 {{ $warnaGaris }}">
            <div class="flex items-start justify-between gap-3">
                <div class="flex min-w-0 flex-col gap-1">
                    <span class="text-[13px] font-bold uppercase text-venice">{{ $kicker }} · {{ $tanggal->translatedFormat('l, j M') }}</span>
                    <span class="text-[22px] font-extrabold leading-tight text-tinta">
                        {{ $p->jenis === JenisPenugasan::Harian ? 'Semua tugas harian' : $p->namaTugas() }}
                    </span>
                    <span class="text-sm text-abu-tua">
                        @if ($p->jenis === JenisPenugasan::Harian) Jam 04.00–09.00 · @endif
                        Batas: {{ $p->tenggatLokal()->translatedFormat('l, j M') }} · {{ $p->tenggatLokal()->format('H.i') }}
                    </span>
                </div>
                <x-badge :status="$p->status" class="flex-none" />
            </div>

            @if ($p->status === StatusPenugasan::BayarBintang && $p->membayar)
                <div class="rounded-xl bg-orange-50 px-3 py-2.5 text-sm leading-normal text-orange-900">
                    Ini piket pengganti untuk bintang {{ $p->membayar->jenis->label() }} tanggal {{ $p->membayar->tanggalLokal()->translatedFormat('j M') }}.
                    Kalau selesai, bintangnya lunas.
                </div>
            @endif

            @if ($p->jenis === JenisPenugasan::Harian && $tugasHarian->isNotEmpty())
                <details class="group rounded-xl bg-merino/70 px-3 py-2.5">
                    <summary class="flex min-h-[32px] cursor-pointer list-none items-center justify-between text-sm font-bold text-tinta">
                        Yang dikerjakan ({{ $tugasHarian->count() }} tugas)
                        <x-ikon nama="bawah" :ukuran="18" class="transition-transform group-open:rotate-180" />
                    </summary>
                    <ul class="mt-2 flex flex-col gap-1.5 pl-1 text-sm text-abu-tua">
                        @foreach ($tugasHarian as $nama)
                            <li class="flex gap-2"><x-ikon nama="centang" :ukuran="16" class="mt-0.5 flex-none text-venice" />{{ $nama }}</li>
                        @endforeach
                    </ul>
                </details>
            @endif

            @if ($p->catatan)
                <div class="rounded-xl bg-merino px-3 py-2.5 text-sm leading-normal text-[#334155]">
                    <strong class="text-tinta">Catatan pengurus:</strong> {{ $p->catatan }}
                </div>
            @endif

            @if ($p->status === StatusPenugasan::Selesai)
                <div role="status" class="letup flex items-center gap-3 rounded-[14px] bg-green-100 px-4 py-3.5">
                    <span class="flex h-9 w-9 flex-none items-center justify-center rounded-full bg-green-700 text-white">
                        <x-ikon nama="centang" :ukuran="20" :tebal="3" class="centang-gambar" />
                    </span>
                    <span class="flex flex-col">
                        <span class="text-base font-extrabold text-green-900">Mantap, sudah tercatat!</span>
                        @if ($p->selesai_pada)
                            <span class="text-[13px] text-green-800">
                                Ditandai selesai {{ $p->selesai_pada->translatedFormat('l, H.i') }}
                                @if ($p->selesaiOleh && $p->selesai_oleh_id !== $user->id) oleh {{ $p->selesaiOleh->namaDepan() }} @endif
                            </span>
                        @endif
                    </span>
                </div>
            @elseif ($p->status === StatusPenugasan::Bintang)
                <div class="rounded-[14px] bg-red-50 px-4 py-3 text-sm leading-normal text-red-900">
                    Piket ini lewat batas waktu, jadi tercatat sebagai bintang.
                    @if ($p->jenis === JenisPenugasan::Harian)
                        Kamu bisa menggantinya di hari kosong lewat menu <a href="{{ route('warga.utang') }}" wire:navigate class="font-bold text-red-900 underline">Utang</a>.
                    @else
                        Pengurus akan menjadwalkan ulang di minggu berikutnya.
                    @endif
                </div>
            @elseif (! $bisa)
                <p class="m-0 flex items-center gap-2 text-sm text-abu-tua">
                    <x-ikon nama="jam" :ukuran="18" class="flex-none text-venice" />
                    Tombol selesai muncul {{ $tanggal->isSameDay($sekarang->addDay()) ? 'besok' : 'hari '.$tanggal->translatedFormat('l') }}.
                </p>
            @elseif ($user->wajib_diperiksa)
                <div class="flex items-start gap-3 rounded-[14px] bg-langit px-4 py-3 text-[15px] leading-normal text-venice-tua">
                    <x-ikon nama="perisai" :ukuran="20" class="mt-0.5 flex-none" />
                    <span>Setelah selesai, panggil pengurus untuk memeriksa. Pengurus yang akan menandai piketmu selesai.</span>
                </div>
            @else
                <div class="flex flex-col gap-1.5">
                    <button type="button" disabled class="tombol-utama w-full min-h-14 text-[17px]">
                        <x-ikon nama="centang" :ukuran="22" :tebal="2.5" />
                        Saya sudah selesai
                    </button>
                    <span class="text-center text-[13px] text-abu">Tombol ini segera aktif. Sementara itu, lapor ke pengurus setelah selesai.</span>
                </div>
            @endif
        </article>
    @empty
        <div class="kartu flex flex-col items-center gap-3 px-6 py-10 text-center">
            <span class="letup flex h-16 w-16 items-center justify-center rounded-full bg-green-100 text-green-700">
                <x-ikon nama="kilau" :ukuran="30" />
            </span>
            <h2 class="m-0 text-xl font-extrabold text-tinta">Minggu ini kamu bebas piket</h2>
            <p class="m-0 max-w-xs text-[15px] leading-normal text-abu-tua">Belum ada piket untukmu di jadwal yang sudah terbit. Piket baru akan muncul di sini setelah diterbitkan pengurus.</p>
            <a href="{{ route('warga.jadwal') }}" wire:navigate class="tombol-garis mt-1 no-underline">Lihat jadwal semua orang</a>
        </div>
    @endforelse

    <h2 class="mb-0 mt-2 text-lg font-extrabold text-tinta">Pengajuan saya</h2>
    @forelse ($pengajuan as $a)
        <div wire:key="ajuan-{{ $a->id }}" class="kartu flex items-center justify-between gap-3 px-[18px] py-4">
            <div class="flex min-w-0 flex-col gap-0.5">
                <span class="text-[15px] font-extrabold text-tinta">Ganti bintang {{ $a->penugasanBintang?->tanggalLokal()->translatedFormat('j M') }}</span>
                <span class="text-sm text-abu-tua">
                    Ganti di {{ $a->tanggal_diajukan->translatedFormat('l, j M') }}
                    @if ($a->catatan) · {{ $a->catatan }} @endif
                </span>
            </div>
            <x-badge :status="$a->status" class="flex-none" />
        </div>
    @empty
        <div class="kartu flex items-center justify-between gap-3 px-[18px] py-4">
            <span class="text-[15px] text-abu-tua">Belum ada pengajuan ganti bintang.</span>
            <x-badge jenis="netral" label="Belum ada" />
        </div>
    @endforelse
</div>
