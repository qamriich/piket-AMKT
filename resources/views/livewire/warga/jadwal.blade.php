@php
    $catatan = [
        'harian' => ($geng?->nama ?? 'Geng').' bertugas jam 04.00–09.00. Hari kosong bisa dipakai untuk ganti bintang harian.',
        'kamis' => 'Dikerjakan '.$gengKamis->pluck('nama')->join(' dan ').'. Batas Jumat, '.$senin->addDays(4)->translatedFormat('j M').' jam 18.00.',
        'minggu' => 'Dikerjakan semua geng. Batas Senin, '.$senin->addDays(7)->translatedFormat('j M').' jam 18.00.',
    ];
    // HP: daftar bertumpuk. Laptop (lg): tabel lebar 4 kolom (PRD bagian 14).
    $kolom = 'lg:grid lg:grid-cols-[minmax(200px,260px)_minmax(0,1fr)_140px_150px] lg:items-center lg:gap-4';
    $baris = fn (bool $milikku, int $i) => collect([
        'flex items-center justify-between gap-3 px-4 py-3 lg:px-6 lg:py-3.5', $kolom,
        $i > 0 ? 'border-t border-pasir-muda' : '',
        $milikku ? 'bg-sorot shadow-[inset_4px_0_0_var(--color-venice)]' : 'bg-white',
    ])->filter()->join(' ');
@endphp
<div class="halaman flex flex-col gap-3 px-4 pb-8 pt-5 lg:gap-4 lg:px-0 lg:pt-8" x-data="{ tab: @js($tabAwal) }">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div class="flex flex-col gap-0.5">
            <h1 class="m-0 text-[26px] font-extrabold text-tinta lg:text-[32px]">Jadwal minggu ini</h1>
            <span class="text-sm text-abu-tua">{{ $senin->translatedFormat('j M') }} – {{ $senin->addDays(6)->translatedFormat('j M Y') }}</span>
        </div>

        <div role="tablist" aria-label="Jenis piket" class="segmen grid-cols-3 lg:w-[420px]">
            @foreach (['harian' => 'Harian', 'kamis' => 'Kamis', 'minggu' => 'Minggu'] as $k => $label)
                <button type="button" role="tab" class="segmen-tombol" id="tab-{{ $k }}" aria-controls="panel-{{ $k }}"
                        :aria-selected="tab === '{{ $k }}'" @click="tab = '{{ $k }}'">{{ $label }}</button>
            @endforeach
        </div>
    </div>

    @foreach (['harian', 'kamis', 'minggu'] as $k)
        <div x-show="tab === '{{ $k }}'" x-cloak role="tabpanel" id="panel-{{ $k }}" aria-labelledby="tab-{{ $k }}"
             x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="translate-x-4 opacity-0" x-transition:enter-end="translate-x-0 opacity-100"
             class="flex flex-col gap-3">
            <p class="m-0 text-sm leading-normal text-abu-tua">{{ $catatan[$k] }}</p>

            @if (! $jadwal[$k]['terbit'])
                <div class="kartu flex flex-col items-center gap-2 px-6 py-8 text-center lg:py-14">
                    <x-ikon nama="jam" :ukuran="28" class="text-rock" />
                    <span class="text-base font-extrabold text-tinta">Belum ada jadwal</span>
                    <span class="text-sm text-abu-tua">Pengurus sedang menyusun jadwal {{ $k === 'harian' ? 'harian' : ucfirst($k) }} minggu ini.</span>
                </div>
            @elseif ($k === 'minggu' && $jadwal['minggu']['kerja_bakti'])
                @php $milikku = $jadwal['minggu']['baris']->firstWhere('user_id', $user->id); @endphp
                <div class="kartu flex flex-col gap-2 px-4 py-4 lg:px-6 lg:py-6">
                    <span class="text-[13px] font-semibold text-abu-tua">{{ $senin->addDays(6)->translatedFormat('l, j M') }}</span>
                    <span class="text-lg font-extrabold text-tinta">Kerja bakti bersama</span>
                    <span class="text-sm text-abu-tua">Semua {{ $jadwal['minggu']['baris']->count() }} warga ikut, tanpa pembagian tugas.</span>
                    @if ($milikku)
                        <div class="mt-1 flex items-center justify-between gap-3 rounded-xl bg-sorot px-3 py-2.5 shadow-[inset_4px_0_0_var(--color-venice)] lg:max-w-md">
                            <span class="font-extrabold text-venice">Kamu ikut</span>
                            <x-badge :status="$milikku->status" />
                        </div>
                    @endif
                    <div class="mt-2 hidden flex-wrap gap-2 lg:flex">
                        @foreach ($jadwal['minggu']['baris']->sortBy(fn ($p) => $p->user->nama) as $p)
                            <span class="badge badge-netral">{{ $p->user->nama }}</span>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="overflow-hidden rounded-[20px] border border-pasir bg-white" role="table" aria-label="Jadwal {{ $k }}">
                    <div role="row" class="hidden border-b border-pasir bg-merino/60 px-6 py-3 text-xs font-extrabold uppercase tracking-[0.06em] text-judul-kecil {{ $kolom }}">
                        <span role="columnheader">{{ $k === 'harian' ? 'Hari' : 'Tugas' }}</span>
                        <span role="columnheader">Petugas</span>
                        <span role="columnheader">Geng</span>
                        <span role="columnheader">Status</span>
                    </div>

                    @if ($k === 'harian')
                        @foreach ($jadwal['harian']['baris'] as $i => $b)
                            @php $p = $b['penugasan']; $milikku = $p && $p->user_id === $user->id; $hariIni = $b['tanggal']->isSameDay($sekarang); @endphp
                            <div role="row" class="{{ $baris($milikku, $i) }}">
                                <div class="flex min-w-0 flex-col gap-0.5 lg:contents">
                                    <span role="cell" class="text-[13px] font-semibold text-abu-tua lg:text-[15px] lg:font-bold lg:text-tinta">
                                        {{ $b['tanggal']->translatedFormat('l, j M').($hariIni ? ' (hari ini)' : '') }}
                                    </span>
                                    <span role="cell" @class(['text-base', 'font-extrabold text-venice' => $milikku, 'font-bold text-tinta lg:font-semibold' => $p && ! $milikku, 'font-semibold italic text-abu' => ! $p])>
                                        {{ $p ? $p->user->nama.($milikku ? ' (kamu)' : '') : 'Tidak ada yang piket' }}
                                    </span>
                                </div>
                                <span role="cell" class="hidden text-sm text-abu-tua lg:block">{{ $p?->user->geng?->nama ?? '–' }}</span>
                                <span role="cell" class="flex-none">
                                    @if ($p) <x-badge :status="$p->status" /> @else <x-badge jenis="kosong" label="Kosong" /> @endif
                                </span>
                            </div>
                        @endforeach
                    @else
                        @forelse ($jadwal[$k]['baris'] as $i => $p)
                            @php $milikku = $p->user_id === $user->id; @endphp
                            <div role="row" class="{{ $baris($milikku, $i) }}">
                                <div class="flex min-w-0 flex-col gap-0.5 lg:contents">
                                    <span role="cell" class="text-[13px] font-semibold text-abu-tua lg:text-[15px] lg:font-bold lg:text-tinta">{{ $p->namaTugas() }}</span>
                                    <span role="cell" @class(['text-base', 'font-extrabold text-venice' => $milikku, 'font-bold text-tinta lg:font-semibold' => ! $milikku])>
                                        {{ $p->user->nama.($milikku ? ' (kamu)' : '') }}
                                    </span>
                                </div>
                                <span role="cell" class="hidden text-sm text-abu-tua lg:block">{{ $p->user->geng?->nama ?? '–' }}</span>
                                <span role="cell" class="flex-none"><x-badge :status="$p->status" /></span>
                            </div>
                        @empty
                            <div class="px-4 py-6 text-center text-sm text-abu-tua">Tidak ada tugas minggu ini.</div>
                        @endforelse
                        @if ($jadwal[$k]['bebas']->isNotEmpty())
                            <div role="row" class="{{ $baris(false, 1) }}">
                                <div class="flex min-w-0 flex-col gap-0.5 lg:contents">
                                    <span role="cell" class="text-[13px] font-semibold text-abu-tua lg:text-[15px] lg:font-bold lg:text-tinta">Bebas minggu ini</span>
                                    <span role="cell" class="text-base font-bold text-tinta lg:font-semibold">{{ $jadwal[$k]['bebas']->map->namaDepan()->join(', ') }}</span>
                                </div>
                                <span role="cell" class="hidden text-sm text-abu-tua lg:block">–</span>
                                <span role="cell" class="flex-none"><x-badge jenis="kosong" label="Bebas" /></span>
                            </div>
                        @endif
                    @endif
                </div>
            @endif
        </div>
    @endforeach
</div>
