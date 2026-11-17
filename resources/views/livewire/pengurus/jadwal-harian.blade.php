<div class="flex flex-col gap-5">
    <div class="naik flex flex-col gap-2">
        <a href="{{ route('pengurus.jadwal') }}" wire:navigate class="inline-flex items-center gap-1 self-start text-sm font-bold text-rock-muda no-underline hover:text-merino">
            <x-ikon nama="kiri" :ukuran="16" /> Atur jadwal
        </a>
        <h1 class="m-0 text-[30px] font-extrabold leading-tight sm:text-[36px]">Jadwal harian periode</h1>
        <p class="m-0 max-w-3xl text-[15px] leading-relaxed text-rock-muda">
            Atur sekali per periode (misalnya satu semester): tiap anggota geng punya satu hari tetap. Setiap minggu, piket harian
            otomatis dibuat untuk geng yang sedang bertugas, tanpa perlu diterbitkan. Perubahan berlaku mulai minggu depan.
        </p>
    </div>

    {{-- Daftar periode --}}
    <div class="naik t1 flex flex-wrap items-center gap-2">
        @foreach ($daftarPeriode as $p)
            <button type="button" wire:key="per-{{ $p->id }}" wire:click="pilihPeriode({{ $p->id }})"
                    @class(['tekan flex min-h-[52px] flex-col items-start justify-center rounded-xl border px-4 text-left',
                        'border-rock bg-rock text-navy' => $periodeId === $p->id,
                        'border-white/15 bg-white/[.05] text-white' => $periodeId !== $p->id])>
                <span class="flex items-center gap-2 text-[15px] font-extrabold">
                    {{ $p->nama }}
                    @if ($periodeAktif?->id === $p->id)
                        <span @class(['rounded-full px-2 py-0.5 text-[11px] font-bold', 'bg-navy/15' => $periodeId === $p->id, 'bg-green-400/20 text-green-200' => $periodeId !== $p->id])>Berlaku sekarang</span>
                    @endif
                </span>
                <span @class(['text-xs', 'text-navy/80' => $periodeId === $p->id, 'text-rock-muda' => $periodeId !== $p->id])>
                    {{ $p->tanggal_mulai->translatedFormat('j M Y') }} – {{ $p->tanggal_selesai->translatedFormat('j M Y') }}
                </span>
            </button>
        @endforeach
        <button type="button" wire:click="periodeBaru" @class(['tekan inline-flex min-h-[52px] items-center gap-1.5 rounded-xl border border-dashed px-4 text-sm font-bold',
                'border-rock bg-rock/15 text-merino' => $periodeId === null, 'border-white/25 text-rock-muda hover:text-white' => $periodeId !== null])>
            <x-ikon nama="tambah" :ukuran="18" /> Periode baru
        </button>
    </div>

    {{-- Nama dan rentang periode --}}
    <form wire:submit="simpanPeriode" class="kartu-kaca naik t2 grid gap-4 rounded-3xl p-5 sm:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_minmax(0,1fr)_auto] sm:items-end sm:p-6">
        <div>
            <label for="nama" class="label-gelap">Nama periode</label>
            <input id="nama" wire:model="nama" class="isian-gelap" placeholder="Semester Ganjil 2026/2027">
            @error('nama') <p class="galat-gelap">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="mulai" class="label-gelap">Mulai</label>
            <input id="mulai" type="date" wire:model="mulai" class="isian-gelap [color-scheme:dark]">
            @error('mulai') <p class="galat-gelap">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="selesai" class="label-gelap">Selesai</label>
            <input id="selesai" type="date" wire:model="selesai" class="isian-gelap [color-scheme:dark]">
            @error('selesai') <p class="galat-gelap">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="tombol-rock !min-h-[48px]" wire:loading.attr="disabled" wire:target="simpanPeriode">
            {{ $periodeId ? 'Simpan periode' : 'Buat periode' }}
        </button>
    </form>

    @if ($periodeId)
        <div class="naik t3 flex flex-wrap items-center justify-between gap-3">
            <p class="m-0 text-sm text-rock-muda">Pilih petugas tiap hari. Satu orang hanya satu hari; hari tanpa petugas tetap kosong (dipakai untuk ganti bintang).</p>
            <button type="button" class="tombol-kaca" wire:click="susunSemua" wire:loading.attr="disabled" wire:target="susunSemua">
                <x-ikon nama="acak" :ukuran="18" /> Susun draf semua geng dari jadwal kuliah
            </button>
        </div>

        <div class="grid gap-5 xl:grid-cols-3">
            @foreach ($gengs as $gi => $g)
                @php $daftarAnggota = $anggota[$g->id] ?? collect(); @endphp
                <section wire:key="geng-{{ $g->id }}-{{ $versi }}" class="kartu-kaca letup flex flex-col gap-3 rounded-3xl p-[18px]" style="animation-delay: {{ $gi * 0.06 }}s">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="m-0 text-lg font-extrabold">{{ $g->nama }} <span class="text-sm font-semibold text-rock-muda">· {{ $daftarAnggota->count() }} orang</span></h2>
                        <button type="button" class="tombol-kecil-gelap" wire:click="susunDraf({{ $g->id }})">
                            <x-ikon nama="acak" :ukuran="16" />&nbsp;Draf
                        </button>
                    </div>

                    @if (! empty($peringatan[$g->id]))
                        <div class="flex flex-col gap-1 rounded-xl border border-amber-200/40 bg-amber-300/10 px-3 py-2.5 text-[13px] leading-normal text-amber-100">
                            @foreach ($peringatan[$g->id] as $pesan)
                                <span>• {{ $pesan }}</span>
                            @endforeach
                        </div>
                    @endif

                    <div class="flex flex-col gap-2">
                        @foreach ($namaHari as $h => $namaH)
                            @php
                                $dipilih = $peta[$g->id][$h] ?? '';
                                $yangBisa = $daftarAnggota->filter(fn ($u) => in_array($h, $bisa[$u->id] ?? [], true));
                            @endphp
                            <div class="grid grid-cols-[72px_minmax(0,1fr)] items-center gap-2">
                                <span class="text-sm font-extrabold">{{ $namaH }}</span>
                                <div class="flex flex-col gap-1">
                                    <select wire:model.live="peta.{{ $g->id }}.{{ $h }}" aria-label="{{ $g->nama }} hari {{ $namaH }}"
                                            @class(['isian-gelap !min-h-[44px] text-[15px]', '!border-amber-300' => $dipilih !== '' && in_array((int) $dipilih, $ganda, true)])>
                                        <option value="">— Kosong —</option>
                                        @foreach ($daftarAnggota as $u)
                                            <option value="{{ $u->id }}">{{ $u->nama }}{{ in_array($h, $bisa[$u->id] ?? [], true) ? '' : ' (kuliah pagi)' }}</option>
                                        @endforeach
                                    </select>
                                    @if ($dipilih === '' && $yangBisa->isNotEmpty())
                                        <span class="truncate text-[12px] text-rock-redup">Bisa: {{ $yangBisa->map->namaDepan()->join(', ') }}</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if ($tanpaHari[$g->id]->isNotEmpty())
                        <p class="m-0 rounded-xl bg-white/[.05] px-3 py-2 text-[13px] text-krem">
                            Belum dapat hari: {{ $tanpaHari[$g->id]->map->namaDepan()->join(', ') }}
                        </p>
                    @endif
                </section>
            @endforeach
        </div>

        @if ($ganda)
            <p class="m-0 rounded-2xl border border-amber-200/40 bg-amber-300/10 px-4 py-3 text-sm text-amber-100">
                Ada orang yang dipilih di lebih dari satu hari (ditandai garis kuning). Satu orang hanya boleh satu hari.
            </p>
        @endif

        <div class="sticky bottom-20 z-20 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-white/10 bg-navy/90 px-4 py-3 backdrop-blur lg:bottom-4">
            <span class="text-sm {{ $adaPerubahan ? 'font-bold text-amber-200' : 'text-rock-muda' }}">
                {{ $adaPerubahan ? 'Ada perubahan yang belum disimpan.' : 'Semua perubahan sudah disimpan.' }}
            </span>
            <button type="button" class="tombol-merino !min-h-[48px]" @disabled($ganda)
                    @click="$store.konfirmasi.tanya({ judul: 'Simpan jadwal harian periode?', pesan: 'Berlaku mulai minggu depan. Piket harian minggu ini tidak berubah.', tombol: 'Simpan', aksi: () => $wire.simpanJadwal() })">
                <x-ikon nama="centang" :ukuran="18" /> Simpan jadwal harian
            </button>
        </div>
    @endif
</div>
