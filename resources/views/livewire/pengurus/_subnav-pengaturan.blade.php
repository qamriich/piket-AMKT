{{-- $subnav: 'rotasi' | 'tugas'. Tidak memakai request()->routeIs() karena saat aksi Livewire rutenya livewire.update. --}}
<div role="tablist" aria-label="Bagian pengaturan" class="segmen-gelap naik t1 self-start">
    <a href="{{ route('pengurus.pengaturan') }}" wire:navigate role="tab" class="segmen-gelap-tombol inline-flex items-center no-underline"
       aria-selected="{{ $subnav === 'rotasi' ? 'true' : 'false' }}">Rotasi & jam kuliah</a>
    <a href="{{ route('pengurus.tugas.index') }}" wire:navigate role="tab" class="segmen-gelap-tombol inline-flex items-center no-underline"
       aria-selected="{{ $subnav === 'tugas' ? 'true' : 'false' }}">Master tugas</a>
</div>
