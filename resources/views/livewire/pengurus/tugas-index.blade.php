<div class="flex flex-col gap-5">
    <div class="naik flex flex-col gap-1">
        <h1 class="m-0 text-[30px] font-extrabold leading-tight sm:text-[36px]">Pengaturan</h1>
        <span class="text-[15px] text-rock-muda">Daftar tugas bawaan. Tugas yang dihapus disembunyikan, riwayat lamanya tetap tersimpan.</span>
    </div>

    @include('livewire.pengurus._subnav-pengaturan', ['subnav' => 'tugas'])

    <div class="grid gap-5 lg:grid-cols-2">
        @foreach ($kelompok as $gi => [$jenis, $judul, $keterangan, $daftar])
            <section wire:key="kel-{{ $jenis }}" class="kartu-kaca naik t{{ $gi + 2 }} flex flex-col overflow-hidden rounded-3xl">
                <header class="flex flex-col gap-0.5 border-b border-white/10 px-5 py-4">
                    <h2 class="m-0 text-lg font-extrabold">{{ $judul }} <span class="text-sm font-semibold text-rock-muda">({{ $daftar->count() }})</span></h2>
                    <span class="text-[13px] text-rock-muda">{{ $keterangan }}</span>
                </header>

                <ul class="m-0 flex list-none flex-col p-0">
                    @forelse ($daftar as $t)
                        <li wire:key="t-{{ $t->id }}" class="border-t border-white/[.06] px-5 py-3 first:border-t-0">
                            @if ($ubahId === $t->id)
                                <form wire:submit="simpanUbah" class="letup flex flex-col gap-2.5">
                                    <div class="flex gap-2">
                                        <input type="number" min="0" wire:model="ubahUrutan" aria-label="Urutan" class="isian-gelap !w-20">
                                        <input wire:model="ubahNama" aria-label="Nama tugas" required class="isian-gelap">
                                    </div>
                                    @error('ubahNama') <p class="galat-gelap !mt-0">{{ $message }}</p> @enderror
                                    @error('ubahUrutan') <p class="galat-gelap !mt-0">{{ $message }}</p> @enderror
                                    <label class="flex items-center gap-2.5 text-sm font-semibold">
                                        <input type="checkbox" wire:model="ubahAktifDefault" class="h-5 w-5 accent-[#84B3CE]"> Aktif default
                                    </label>
                                    <div class="flex gap-2">
                                        <button type="submit" class="tombol-rock !min-h-[44px] text-sm">Simpan</button>
                                        <button type="button" wire:click="batalUbah" class="tombol-kaca !min-h-[44px] text-sm">Batal</button>
                                    </div>
                                </form>
                            @else
                                <div class="flex items-center justify-between gap-2">
                                    <div class="min-w-0">
                                        <span class="mr-1 text-xs text-rock-redup">{{ $t->urutan }}.</span>
                                        <span class="font-semibold">{{ $t->nama }}</span>
                                        @unless ($t->aktif_default)
                                            <span class="badge badge-netral ml-1">Default nonaktif</span>
                                        @endunless
                                    </div>
                                    <div class="flex flex-none gap-1">
                                        <button type="button" wire:click="mulaiUbah({{ $t->id }})" class="tekan flex h-10 w-10 items-center justify-center rounded-xl text-rock-muda hover:bg-white/[.08]" aria-label="Ubah {{ $t->nama }}">
                                            <x-ikon nama="pensil" :ukuran="17" />
                                        </button>
                                        <button type="button" class="tekan flex h-10 w-10 items-center justify-center rounded-xl text-red-300 hover:bg-red-500/15" aria-label="Hapus {{ $t->nama }}"
                                                @click="$store.konfirmasi.tanya({ judul: @js('Hapus tugas '.$t->nama.'?'), pesan: 'Riwayat lama tetap tersimpan.', tombol: 'Hapus', bahaya: true, aksi: () => $wire.hapus({{ $t->id }}) })">
                                            <x-ikon nama="sampah" :ukuran="17" />
                                        </button>
                                    </div>
                                </div>
                            @endif
                        </li>
                    @empty
                        <li class="px-5 py-8 text-center text-sm text-rock-muda">Belum ada tugas.</li>
                    @endforelse
                </ul>

                <form wire:submit="tambah('{{ $jenis }}')" class="mt-auto flex flex-col gap-1 border-t border-white/10 bg-white/[.03] px-5 py-4">
                    <div class="flex gap-2">
                        <input wire:model="baru.{{ $jenis }}" placeholder="Tugas baru" aria-label="Nama tugas baru {{ $judul }}" class="isian-gelap">
                        <button type="submit" class="tombol-rock flex-none"><x-ikon nama="tambah" :ukuran="18" /> Tambah</button>
                    </div>
                    @error("baru.$jenis") <p class="galat-gelap">{{ $message }}</p> @enderror
                </form>
            </section>
        @endforeach
    </div>
</div>
