@php
    use App\Enums\JenisPenugasan;
    use App\Enums\StatusPenugasan;
@endphp
<div class="halaman flex flex-col gap-3.5 px-4 pb-8 pt-5 lg:px-0 lg:pt-8">
    <h1 class="m-0 text-[26px] font-extrabold text-tinta">Piket saya</h1>

    <div class="grid gap-3.5 lg:grid-cols-2 lg:items-start">
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
            @elseif ($p->status === StatusPenugasan::Izin)
                <div class="flex items-start gap-3 rounded-[14px] bg-slate-100 px-4 py-3 text-sm leading-normal text-slate-700">
                    <x-ikon nama="rumah" :ukuran="18" class="mt-0.5 flex-none" />
                    <span>Kamu sedang izin, jadi piket ini dilepas dan tidak menjadi bintang.
                        {{ $p->jenis === JenisPenugasan::Harian ? 'Pengurus yang mengatur penggantinya.' : 'Sudah dicarikan pengganti.' }}</span>
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
            @elseif (in_array($p->id, $bisaSendiri, true))
                <div class="flex flex-col gap-1.5">
                    <button type="button" class="tombol-utama w-full min-h-14 text-[17px]"
                            wire:click="selesai({{ $p->id }})" wire:loading.attr="disabled" wire:target="selesai({{ $p->id }})">
                        <span wire:loading.remove wire:target="selesai({{ $p->id }})" class="inline-flex items-center gap-2">
                            <x-ikon nama="centang" :ukuran="22" :tebal="2.5" />
                            Saya sudah selesai
                        </span>
                        <span wire:loading wire:target="selesai({{ $p->id }})">Menyimpan…</span>
                    </button>
                    <span class="text-center text-[13px] text-abu">Tekan setelah semua tugasnya beres, sebelum {{ $p->tenggatLokal()->translatedFormat('l') }} jam {{ $p->tenggatLokal()->format('H.i') }}.</span>
                </div>
            @else
                <div class="rounded-[14px] bg-red-50 px-4 py-3 text-sm leading-normal text-red-900">
                    Batas waktunya sudah lewat. Kalau sebenarnya sudah kamu kerjakan, sampaikan ke pengurus.
                </div>
            @endif
        </article>
    @empty
        <div class="kartu flex flex-col items-center gap-3 px-6 py-10 text-center lg:col-span-2">
            <span class="letup flex h-16 w-16 items-center justify-center rounded-full bg-green-100 text-green-700">
                <x-ikon nama="kilau" :ukuran="30" />
            </span>
            <h2 class="m-0 text-xl font-extrabold text-tinta">Minggu ini kamu bebas piket</h2>
            <p class="m-0 max-w-xs text-[15px] leading-normal text-abu-tua">Belum ada piket untukmu di jadwal yang sudah terbit. Piket baru akan muncul di sini setelah diterbitkan pengurus.</p>
            <a href="{{ route('warga.jadwal') }}" wire:navigate class="tombol-garis mt-1 no-underline">Lihat jadwal semua orang</a>
        </div>
    @endforelse
    </div>

    <section x-data="{ buka: false }" x-on:izin-terkirim.window="buka = false" class="kartu mt-2 flex flex-col gap-3 p-[18px]" aria-labelledby="judul-izin">
        <div class="flex items-center justify-between gap-3">
            <div class="flex min-w-0 items-center gap-3">
                <span class="flex h-11 w-11 flex-none items-center justify-center rounded-[14px] bg-langit text-venice"><x-ikon nama="rumah" :ukuran="22" /></span>
                <div class="flex min-w-0 flex-col">
                    <h2 id="judul-izin" class="m-0 text-lg font-extrabold text-tinta">Izin</h2>
                    <span class="text-sm text-abu-tua">Mau pergi atau pulang kampung? Ajukan dulu supaya piketmu dicarikan pengganti.</span>
                </div>
            </div>
            <button type="button" class="tombol-garis flex-none !min-h-[44px] px-4 text-sm" @click="buka = !buka" :aria-expanded="buka" x-text="buka ? 'Tutup' : 'Ajukan'">Ajukan</button>
        </div>

        <form x-show="buka" x-collapse x-cloak wire:submit="ajukanIzin" class="flex flex-col gap-3 border-t border-pasir pt-3">
            @include('livewire._isian-izin', ['gelap' => false, 'min' => $hariIni])
            <button type="submit" class="tombol-utama w-full" wire:loading.attr="disabled" wire:target="ajukanIzin">
                <span wire:loading.remove wire:target="ajukanIzin">Kirim ke pengurus</span>
                <span wire:loading wire:target="ajukanIzin">Mengirim…</span>
            </button>
            <span class="text-center text-[13px] leading-snug text-abu">Piket hanya diganti kalau izinmu menutupi seluruh waktu piketnya (harian 04.00–09.00, Kamis sampai Jumat 18.00, Minggu sampai Senin 18.00). Kalau izinnya cuma sebentar, piketnya tetap kamu kerjakan.</span>
        </form>

        @foreach ($izin as $iz)
            <div wire:key="izin-{{ $iz->id }}" class="flex items-center justify-between gap-3 rounded-xl bg-merino/60 px-3.5 py-3">
                <div class="flex min-w-0 flex-col gap-0.5">
                    <span class="text-[15px] font-extrabold text-tinta">{{ $iz->rentangLabel() }}</span>
                    @if ($iz->alasan || $iz->catatan)
                        <span class="text-sm text-abu-tua">{{ $iz->alasan }}@if ($iz->alasan && $iz->catatan) · @endif @if ($iz->catatan) Catatan pengurus: {{ $iz->catatan }} @endif</span>
                    @endif
                </div>
                <x-badge :status="$iz->status" class="flex-none" />
            </div>
        @endforeach
    </section>

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
