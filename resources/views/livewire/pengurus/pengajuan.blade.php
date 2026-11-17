<div class="flex flex-col gap-5">
    <div class="naik flex flex-col gap-1">
        <h1 class="m-0 text-[30px] font-extrabold leading-tight sm:text-[36px]">Pengajuan ganti bintang</h1>
        <span class="text-[15px] text-rock-muda">Warga memilih hari kosong untuk mengganti bintang harian. Pengajuan yang diterima mengisi hari itu dengan status Bayar Bintang.</span>
    </div>

    <div role="tablist" class="segmen-gelap naik t1 self-start">
        <button type="button" role="tab" class="segmen-gelap-tombol" aria-selected="{{ $tampil === 'menunggu' ? 'true' : 'false' }}" wire:click="setTampil('menunggu')">
            Menunggu @if ($jumlahMenunggu) ({{ $jumlahMenunggu }}) @endif
        </button>
        <button type="button" role="tab" class="segmen-gelap-tombol" aria-selected="{{ $tampil === 'riwayat' ? 'true' : 'false' }}" wire:click="setTampil('riwayat')">Riwayat</button>
    </div>

    <section class="kartu-kaca naik t2 flex flex-col gap-3 rounded-3xl p-[18px] sm:p-[22px]">
        <div wire:loading.flex wire:target="setTampil" class="flex-col gap-2">
            <div class="kerangka h-20"></div><div class="kerangka h-20"></div>
        </div>
        <div wire:loading.remove wire:target="setTampil" class="flex flex-col gap-3">
            @forelse ($daftar as $i => $a)
                <div wire:key="aj-{{ $a->id }}" x-data x-muncul="{{ min($i, 6) }}" class="flex flex-wrap items-center gap-3 rounded-2xl border border-white/[.08] p-4">
                    <div class="flex h-11 w-11 flex-none items-center justify-center rounded-full bg-langit font-extrabold text-venice">{{ $a->user->inisial() }}</div>
                    <div class="flex min-w-0 flex-[1_1_220px] flex-col gap-0.5">
                        <span class="text-base font-extrabold">{{ $a->user->nama }} <span class="text-sm font-semibold text-rock-muda">· {{ $a->user->geng?->nama }}</span></span>
                        <span class="text-sm text-krem">
                            Bintang harian {{ $a->penugasanBintang?->tanggalLokal()->translatedFormat('l, j M') }} → ganti {{ $a->tanggal_diajukan->translatedFormat('l, j M') }}
                        </span>
                        @if ($a->catatan)
                            <span class="text-[13px] text-rock-muda">Catatan: {{ $a->catatan }}</span>
                        @endif
                        @if ($a->diputuskanOleh)
                            <span class="text-[13px] text-rock-muda">Diputuskan {{ $a->diputuskanOleh->namaDepan() }}{{ $a->diputuskan_pada ? ', '.$a->diputuskan_pada->translatedFormat('j M H.i') : '' }}</span>
                        @endif
                    </div>
                    <x-badge :status="$a->status" />
                    @if ($a->status === \App\Enums\StatusPengajuan::Menunggu)
                        <div class="grid w-full grid-cols-2 gap-2 sm:w-auto">
                            <button type="button" disabled title="Segera tersedia" class="tombol-kaca">Tolak</button>
                            <button type="button" disabled title="Segera tersedia" class="tombol-rock">Terima</button>
                        </div>
                    @endif
                </div>
            @empty
                <div class="flex flex-col items-center gap-2 px-6 py-10 text-center">
                    <x-ikon nama="kotak-masuk" :ukuran="30" class="text-rock" />
                    <span class="text-base font-extrabold">{{ $tampil === 'menunggu' ? 'Tidak ada pengajuan yang menunggu' : 'Belum ada riwayat pengajuan' }}</span>
                    <span class="text-sm text-rock-muda">Semua aman.</span>
                </div>
            @endforelse
            <p class="m-0 flex items-start gap-2 text-[13px] text-rock-muda">
                <x-ikon nama="info" :ukuran="16" class="mt-0.5 flex-none" />
                Tombol Terima dan Tolak aktif di tahap berikutnya, bersama email pemberitahuan ke warga.
            </p>
        </div>
    </section>
</div>
