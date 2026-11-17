@php
    $g = $gelap;
    $kotak = $g ? 'overflow-hidden rounded-[18px] border border-white/10 bg-white/[.04]' : 'overflow-hidden rounded-[20px] border border-pasir bg-white';
    $garis = $g ? 'border-t border-white/[.07]' : 'border-t border-pasir-muda';
    $teksHari = $g ? 'text-white' : 'text-tinta';
    $teksRedup = $g ? 'text-rock-redup' : 'text-abu';
    $teksJam = $g ? 'text-krem' : 'text-[#334155]';
@endphp
<div class="flex flex-col gap-3">
    @if (! $ubah)
        <div class="{{ $kotak }}">
            @foreach ($perHari as $h => $hari)
                <div @class(['flex items-center justify-between gap-3 px-[18px] py-3.5', $garis => $h > 1])>
                    <span class="text-base font-extrabold {{ $teksHari }}">{{ $hari['nama'] }}</span>
                    @if ($hari['sesi'] === [])
                        <span class="text-[15px] {{ $teksRedup }}">Tidak ada kuliah</span>
                    @else
                        <span class="text-right text-[15px] font-bold {{ $teksJam }}">
                            {{ collect($hari['sesi'])->map(fn ($s) => str_replace(':', '.', $s['mulai']).'–'.str_replace(':', '.', $s['selesai']))->join(', ') }}
                        </span>
                    @endif
                </div>
            @endforeach
        </div>
        <button type="button" wire:click="mulaiUbah" class="{{ $g ? 'tombol-kaca' : 'tombol-utama' }} w-full">
            <x-ikon nama="pensil" :ukuran="18" /> Ubah jadwal kuliah
        </button>
    @else
        <form wire:submit="simpan" class="flex flex-col gap-3">
            @foreach ($perHari as $h => $hari)
                <div wire:key="hari-{{ $h }}" class="{{ $kotak }} px-[18px] py-3.5">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-base font-extrabold {{ $teksHari }}">{{ $hari['nama'] }}</span>
                        <button type="button" wire:click="tambah({{ $h }})"
                                class="tekan inline-flex min-h-[40px] items-center gap-1 rounded-xl px-3 text-sm font-bold {{ $g ? 'bg-white/10 text-merino' : 'bg-langit text-venice' }}">
                            <x-ikon nama="tambah" :ukuran="16" /> Tambah
                        </button>
                    </div>
                    @if ($hari['sesi'] === [])
                        <p class="m-0 mt-1 text-sm {{ $teksRedup }}">Tidak ada kuliah</p>
                    @endif
                    @foreach ($hari['sesi'] as $i => $s)
                        <div wire:key="sesi-{{ $i }}" class="letup mt-2.5 flex flex-wrap items-end gap-2">
                            <label class="flex min-w-[110px] flex-1 flex-col">
                                <span class="mb-1 text-xs font-bold {{ $teksRedup }}">Mulai</span>
                                <input type="time" wire:model="sesi.{{ $i }}.mulai" class="{{ $g ? 'isian-gelap' : 'isian' }}" required>
                            </label>
                            <label class="flex min-w-[110px] flex-1 flex-col">
                                <span class="mb-1 text-xs font-bold {{ $teksRedup }}">Selesai</span>
                                <input type="time" wire:model="sesi.{{ $i }}.selesai" class="{{ $g ? 'isian-gelap' : 'isian' }}" required>
                            </label>
                            <button type="button" wire:click="hapus({{ $i }})" aria-label="Hapus jam kuliah ini"
                                    class="tekan flex h-12 w-12 items-center justify-center rounded-xl {{ $g ? 'bg-red-500/15 text-red-300' : 'bg-red-50 text-red-700' }}">
                                <x-ikon nama="sampah" :ukuran="18" />
                            </button>
                            @error("sesi.$i.mulai") <p class="{{ $g ? 'galat-gelap' : 'galat' }} w-full">{{ $message }}</p> @enderror
                            @error("sesi.$i.selesai") <p class="{{ $g ? 'galat-gelap' : 'galat' }} w-full">{{ $message }}</p> @enderror
                        </div>
                    @endforeach
                </div>
            @endforeach
            <div class="grid grid-cols-2 gap-2.5">
                <button type="button" wire:click="batal" class="{{ $g ? 'tombol-kaca' : 'tombol-garis' }}">Batal</button>
                <button type="submit" class="{{ $g ? 'tombol-merino !min-h-[48px]' : 'tombol-utama' }}" wire:loading.attr="disabled" wire:target="simpan">
                    <span wire:loading.remove wire:target="simpan">Simpan</span>
                    <span wire:loading wire:target="simpan">Menyimpan…</span>
                </button>
            </div>
        </form>
    @endif
</div>
