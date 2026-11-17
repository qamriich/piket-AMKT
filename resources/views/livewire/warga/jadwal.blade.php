@php
    $catatan = [
        'harian' => ($geng?->nama ?? 'Geng').' bertugas jam 04.00–09.00. Hari kosong bisa dipakai untuk ganti bintang harian.',
        'kamis' => 'Dikerjakan '.$gengKamis->pluck('nama')->join(' dan ').'. Batas Jumat, '.$senin->addDays(4)->translatedFormat('j M').' jam 18.00.',
        'minggu' => 'Dikerjakan semua geng. Batas Senin, '.$senin->addDays(7)->translatedFormat('j M').' jam 18.00.',
    ];
@endphp
<div class="halaman flex flex-col gap-3 px-4 pb-8 pt-5" x-data="{ tab: @js($tabAwal) }">
    <div class="flex flex-col gap-0.5">
        <h1 class="m-0 text-[26px] font-extrabold text-tinta">Jadwal minggu ini</h1>
        <span class="text-sm text-abu-tua">{{ $senin->translatedFormat('j M') }} – {{ $senin->addDays(6)->translatedFormat('j M Y') }}</span>
    </div>

    <div role="tablist" aria-label="Jenis piket" class="segmen grid-cols-3">
        @foreach (['harian' => 'Harian', 'kamis' => 'Kamis', 'minggu' => 'Minggu'] as $k => $label)
            <button type="button" role="tab" class="segmen-tombol" id="tab-{{ $k }}" aria-controls="panel-{{ $k }}"
                    :aria-selected="tab === '{{ $k }}'" @click="tab = '{{ $k }}'">{{ $label }}</button>
        @endforeach
    </div>

    @foreach (['harian', 'kamis', 'minggu'] as $k)
        <div x-show="tab === '{{ $k }}'" x-cloak role="tabpanel" id="panel-{{ $k }}" aria-labelledby="tab-{{ $k }}"
             x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="translate-x-4 opacity-0" x-transition:enter-end="translate-x-0 opacity-100"
             class="flex flex-col gap-3">
            <p class="m-0 text-sm leading-normal text-abu-tua">{{ $catatan[$k] }}</p>

            @if (! $jadwal[$k]['terbit'])
                <div class="kartu flex flex-col items-center gap-2 px-6 py-8 text-center">
                    <x-ikon nama="jam" :ukuran="28" class="text-rock" />
                    <span class="text-base font-extrabold text-tinta">Belum diterbitkan</span>
                    <span class="text-sm text-abu-tua">Pengurus sedang menyusun jadwal {{ $k === 'harian' ? 'harian' : ucfirst($k) }} minggu ini.</span>
                </div>
            @else
                <div class="overflow-hidden rounded-[20px] border border-pasir bg-white">
                    @if ($k === 'harian')
                        @foreach ($jadwal['harian']['baris'] as $i => $b)
                            @php $p = $b['penugasan']; $milikku = $p && $p->user_id === $user->id; @endphp
                            <div @class(['flex items-center justify-between gap-3 px-4 py-3', 'border-t border-pasir-muda' => $i > 0,
                                    'bg-sorot shadow-[inset_4px_0_0_var(--color-venice)]' => $milikku, 'bg-white' => ! $milikku])>
                                <div class="flex min-w-0 flex-col gap-0.5">
                                    <span class="text-[13px] font-semibold text-abu-tua">
                                        {{ $b['tanggal']->translatedFormat('l, j M').($b['tanggal']->isSameDay($sekarang) ? ' (hari ini)' : '') }}
                                    </span>
                                    @if ($p)
                                        <span @class(['text-base', 'font-extrabold text-venice' => $milikku, 'font-bold text-tinta' => ! $milikku])>
                                            {{ $p->user->nama.($milikku ? ' (kamu)' : '') }}
                                        </span>
                                    @else
                                        <span class="text-base font-semibold italic text-abu">Tidak ada yang piket</span>
                                    @endif
                                </div>
                                @if ($p)
                                    <x-badge :status="$p->status" class="flex-none" />
                                @else
                                    <x-badge jenis="kosong" label="Kosong" class="flex-none" />
                                @endif
                            </div>
                        @endforeach
                    @elseif ($k === 'minggu' && $jadwal['minggu']['kerja_bakti'])
                        @php $milikku = $jadwal['minggu']['baris']->firstWhere('user_id', $user->id); @endphp
                        <div class="flex flex-col gap-2 px-4 py-4">
                            <span class="text-[13px] font-semibold text-abu-tua">{{ $senin->addDays(6)->translatedFormat('l, j M') }}</span>
                            <span class="text-lg font-extrabold text-tinta">Kerja bakti bersama</span>
                            <span class="text-sm text-abu-tua">Semua {{ $jadwal['minggu']['baris']->count() }} warga ikut, tanpa pembagian tugas.</span>
                            @if ($milikku)
                                <div class="mt-1 flex items-center justify-between gap-3 rounded-xl bg-sorot px-3 py-2.5 shadow-[inset_4px_0_0_var(--color-venice)]">
                                    <span class="font-extrabold text-venice">Kamu ikut</span>
                                    <x-badge :status="$milikku->status" />
                                </div>
                            @endif
                        </div>
                    @else
                        @php $baris = $jadwal[$k]['baris']; @endphp
                        @forelse ($baris as $i => $p)
                            @php $milikku = $p->user_id === $user->id; @endphp
                            <div @class(['flex items-center justify-between gap-3 px-4 py-3', 'border-t border-pasir-muda' => $i > 0,
                                    'bg-sorot shadow-[inset_4px_0_0_var(--color-venice)]' => $milikku, 'bg-white' => ! $milikku])>
                                <div class="flex min-w-0 flex-col gap-0.5">
                                    <span class="text-[13px] font-semibold text-abu-tua">{{ $p->namaTugas() }}</span>
                                    <span @class(['text-base', 'font-extrabold text-venice' => $milikku, 'font-bold text-tinta' => ! $milikku])>
                                        {{ $p->user->nama.($milikku ? ' (kamu)' : '') }}
                                    </span>
                                </div>
                                <x-badge :status="$p->status" class="flex-none" />
                            </div>
                        @empty
                            <div class="px-4 py-6 text-center text-sm text-abu-tua">Tidak ada tugas minggu ini.</div>
                        @endforelse
                        @if ($jadwal[$k]['bebas']->isNotEmpty())
                            <div class="flex items-center justify-between gap-3 border-t border-pasir-muda px-4 py-3">
                                <div class="flex min-w-0 flex-col gap-0.5">
                                    <span class="text-[13px] font-semibold text-abu-tua">Bebas minggu ini</span>
                                    <span class="text-base font-bold text-tinta">{{ $jadwal[$k]['bebas']->map->namaDepan()->join(', ') }}</span>
                                </div>
                                <x-badge jenis="kosong" label="Bebas" class="flex-none" />
                            </div>
                        @endif
                    @endif
                </div>
            @endif
        </div>
    @endforeach
</div>
