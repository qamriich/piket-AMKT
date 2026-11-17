{{-- Toast dan modal konfirmasi. Dipakai semua layout. Butuh x-data karena berada di luar komponen Livewire. --}}
<div x-data aria-live="polite" class="pointer-events-none fixed inset-x-0 top-3 z-[60] flex flex-col items-center gap-2 px-4 sm:top-5">
    <template x-for="t in $store.toast.daftar" :key="t.id">
        <div role="status"
             x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="-translate-y-3 opacity-0" x-transition:enter-end="translate-y-0 opacity-100"
             x-transition:leave="transition duration-200 ease-in" x-transition:leave-end="-translate-y-2 opacity-0"
             class="letup pointer-events-auto flex w-full max-w-md items-center gap-3 rounded-2xl px-4 py-3.5 text-[15px] font-semibold shadow-[0_14px_36px_rgba(5,28,44,.28)]"
             :class="t.jenis === 'gagal' ? 'bg-red-50 text-red-900 ring-1 ring-red-200' : 'bg-tinta text-white ring-1 ring-white/10'">
            <span class="flex h-8 w-8 flex-none items-center justify-center rounded-full"
                  :class="t.jenis === 'gagal' ? 'bg-red-600 text-white' : 'bg-green-500 text-white'">
                <template x-if="t.jenis !== 'gagal'"><x-ikon nama="centang" :ukuran="18" :tebal="3" /></template>
                <template x-if="t.jenis === 'gagal'"><x-ikon nama="silang" :ukuran="16" :tebal="3" /></template>
            </span>
            <span class="flex-1 leading-snug" x-text="t.pesan"></span>
            <button type="button" class="rounded-lg p-1 opacity-70 hover:opacity-100" @click="$store.toast.tutup(t.id)" aria-label="Tutup">
                <x-ikon nama="silang" :ukuran="18" />
            </button>
        </div>
    </template>
</div>

<div x-data x-cloak x-show="$store.konfirmasi.buka" class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center"
     @keydown.escape.window="$store.konfirmasi.batal()" role="dialog" aria-modal="true" aria-labelledby="judul-konfirmasi">
    <div x-show="$store.konfirmasi.buka" x-transition.opacity class="absolute inset-0 bg-navy-tua/60 backdrop-blur-sm" @click="$store.konfirmasi.batal()"></div>
    <div x-show="$store.konfirmasi.buka"
         x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="translate-y-6 scale-95 opacity-0" x-transition:enter-end="translate-y-0 scale-100 opacity-100"
         x-transition:leave="transition duration-150 ease-in" x-transition:leave-end="translate-y-4 opacity-0"
         class="relative w-full max-w-md rounded-3xl bg-white p-6 text-teks shadow-[0_24px_60px_rgba(5,28,44,.35)]">
        <div class="flex items-start gap-4">
            <span class="flex h-12 w-12 flex-none items-center justify-center rounded-2xl"
                  :class="$store.konfirmasi.bahaya ? 'bg-red-100 text-red-700' : 'bg-langit text-venice'">
                <x-ikon nama="info" :ukuran="24" />
            </span>
            <div class="min-w-0 flex-1">
                <h2 id="judul-konfirmasi" class="text-lg font-extrabold text-tinta" x-text="$store.konfirmasi.judul"></h2>
                <p class="mt-1.5 text-[15px] leading-relaxed text-abu-tua" x-show="$store.konfirmasi.pesan" x-text="$store.konfirmasi.pesan"></p>
            </div>
        </div>
        <div class="mt-6 grid grid-cols-2 gap-2.5">
            <button type="button" class="tombol-garis border-pasir text-abu-tua" @click="$store.konfirmasi.batal()">Batal</button>
            <button type="button" class="tombol-utama" :class="$store.konfirmasi.bahaya && '!bg-red-600'"
                    x-text="$store.konfirmasi.tombol" @click="$store.konfirmasi.ya()" x-ref="ya"
                    x-effect="$store.konfirmasi.buka && $nextTick(() => $refs.ya.focus())"></button>
        </div>
    </div>
</div>

@if (session('status'))
    <div x-data x-init="$nextTick(() => $store.toast.tampil(@js(session('status'))))"></div>
@endif
@if (session('error'))
    <div x-data x-init="$nextTick(() => $store.toast.tampil(@js(session('error')), 'gagal'))"></div>
@endif
