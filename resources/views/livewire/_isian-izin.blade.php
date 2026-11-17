{{-- Isian mulai/selesai izin dengan tanggal + jam dan tombol cepat (R3a). Butuh $gelap (bool) dan opsional $min. --}}
@php
    $isian = $gelap ? 'isian-gelap' : 'isian';
    $label = $gelap ? 'text-sm font-semibold text-krem' : 'text-sm font-bold text-tinta';
    $galat = $gelap ? 'text-[13px] text-red-300' : 'text-[13px] font-semibold text-red-700';
    $cepat = $gelap
        ? 'tekan min-h-[44px] rounded-xl border border-white/15 px-3 text-sm font-bold text-merino hover:bg-white/[.06]'
        : 'tekan min-h-[44px] rounded-xl border-[1.5px] border-rock bg-white px-3 text-sm font-bold text-venice';
@endphp
<div class="flex flex-wrap gap-2" role="group" aria-label="Isi cepat">
    <button type="button" class="{{ $cepat }}" wire:click="izinHariIniBesok">Hari ini sampai besok</button>
    <button type="button" class="{{ $cepat }}" wire:click="izinSehariPenuh">Sehari penuh</button>
</div>
<div class="grid gap-3 sm:grid-cols-2">
    <label class="flex flex-col gap-1.5 {{ $label }}">
        Mulai (tanggal dan jam)
        <input type="datetime-local" wire:model="izin_mulai" @isset($min) min="{{ $min }}" @endisset class="{{ $isian }}" required>
        @error('izin_mulai') <span class="{{ $galat }}">{{ $message }}</span> @enderror
    </label>
    <label class="flex flex-col gap-1.5 {{ $label }}">
        Selesai (tanggal dan jam)
        <input type="datetime-local" wire:model="izin_selesai" @isset($min) min="{{ $min }}" @endisset class="{{ $isian }}" required>
        @error('izin_selesai') <span class="{{ $galat }}">{{ $message }}</span> @enderror
    </label>
</div>
<label class="flex flex-col gap-1.5 {{ $label }}">
    Alasan (boleh dikosongkan)
    <input type="text" wire:model="izin_alasan" maxlength="500" class="{{ $isian }}" placeholder="Misalnya: pulang kampung, kegiatan kampus, KKN">
</label>
