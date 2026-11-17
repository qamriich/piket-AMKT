{{-- Tombol Terima/Tolak pengajuan ganti bintang dengan catatan opsional (F8). Butuh $a. --}}
<div x-data="{ catatan: '', tulis: false }" class="flex w-full flex-col gap-2 sm:w-auto">
    <div x-show="tulis" x-cloak x-transition.opacity>
        <textarea x-model="catatan" rows="2" maxlength="500" aria-label="Catatan untuk {{ $a->user->namaDepan() }}"
                  class="isian-gelap !min-h-[60px] py-2 text-sm" placeholder="Catatan untuk {{ $a->user->namaDepan() }} (boleh kosong)"></textarea>
    </div>
    <div class="grid grid-cols-2 gap-2">
        <button type="button" class="tombol-kaca"
                @click="$store.konfirmasi.tanya({ judul: @js('Tolak pengajuan '.$a->user->namaDepan().'?'), pesan: catatan ? 'Catatan: ' + catatan : 'Ia akan mendapat email dan bisa memilih hari lain.', tombol: 'Tolak', bahaya: true, aksi: () => $wire.tolakPengajuan({{ $a->id }}, catatan) })">
            Tolak
        </button>
        <button type="button" class="tombol-rock" wire:loading.attr="disabled" wire:target="terimaPengajuan({{ $a->id }})"
                @click="$wire.terimaPengajuan({{ $a->id }}, catatan)">
            Terima
        </button>
    </div>
    <button type="button" class="self-start text-[13px] font-bold text-rock-muda underline" x-show="!tulis" @click="tulis = true">+ Tambah catatan</button>
</div>
