<div class="flex flex-col gap-5">
    <div class="naik flex flex-wrap items-end justify-between gap-4">
        <div class="flex flex-col gap-1">
            <h1 class="m-0 text-[30px] font-extrabold leading-tight sm:text-[36px]">Warga & geng</h1>
            <span class="text-[15px] text-rock-muda"><span x-data x-hitung data-nilai="{{ $jumlah }}">{{ $jumlah }}</span> orang ditampilkan</span>
        </div>
        <a href="{{ route('pengurus.warga.create') }}" wire:navigate class="tombol-merino no-underline"><x-ikon nama="tambah" :ukuran="20" /> Tambah warga</a>
    </div>

    <div class="naik t1 flex flex-wrap items-center gap-2">
        <div role="tablist" class="segmen-gelap">
            @foreach (['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif', 'semua' => 'Semua'] as $k => $label)
                <button type="button" role="tab" class="segmen-gelap-tombol" aria-selected="{{ $tampil === $k ? 'true' : 'false' }}" wire:click="setTampil('{{ $k }}')">{{ $label }}</button>
            @endforeach
        </div>
        <input type="search" wire:model.live.debounce.300ms="cari" placeholder="Cari nama, email, kamar…" aria-label="Cari warga" class="isian-gelap !w-full sm:!w-72">
    </div>

    <div class="grid gap-4 lg:grid-cols-3" wire:loading.class="opacity-60" wire:target="setTampil, cari">
        @foreach ($kelompok as $gi => $k)
            @php $anggota = $perGeng->get($k['id'], collect()); @endphp
            <section wire:key="geng-{{ $k['id'] }}" class="kartu-kaca naik t{{ min($gi + 2, 5) }} flex flex-col overflow-hidden rounded-3xl">
                <header class="flex items-center justify-between border-b border-white/10 px-5 py-4">
                    <h2 class="m-0 text-lg font-extrabold">{{ $k['nama'] }}</h2>
                    <span class="rounded-full bg-white/[.08] px-2.5 py-1 text-xs font-bold text-rock-muda">{{ $anggota->count() }} orang</span>
                </header>
                <ul class="m-0 flex list-none flex-col p-0">
                    @forelse ($anggota as $w)
                        <li wire:key="w-{{ $w->id }}" x-data="{ menu: false }" class="border-t border-white/[.06] px-5 py-3.5 first:border-t-0">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p @class(['m-0 truncate font-bold', 'text-rock-redup line-through' => ! $w->aktif])>{{ $w->nama }}</p>
                                    <p class="m-0 truncate text-[13px] text-rock-muda">{{ $w->no_kamar ? 'Kamar '.$w->no_kamar.' · ' : '' }}{{ $w->email }}</p>
                                    <div class="mt-1.5 flex flex-wrap gap-1">
                                        @if ($w->isPengurus())
                                            <span class="badge bg-rock/20 text-rock-muda">{{ $w->role->label() }}</span>
                                        @endif
                                        @if ($w->wajib_diperiksa)
                                            <span class="badge border border-merino/30 bg-merino/10 text-merino">Wajib diperiksa</span>
                                        @endif
                                        @if (! $w->aktif)
                                            <span class="badge badge-netral">Nonaktif</span>
                                        @endif
                                        @if (! $w->token_hash)
                                            <span class="badge badge-bintang">Link belum dikirim</span>
                                        @endif
                                    </div>
                                </div>
                                <button type="button" @click="menu = !menu" :aria-expanded="menu"
                                        class="tekan flex min-h-[40px] flex-none items-center gap-1 rounded-xl bg-white/[.06] px-3 text-sm font-bold text-krem">
                                    Aksi <x-ikon nama="bawah" :ukuran="16" class="transition-transform" ::class="menu && 'rotate-180'" />
                                </button>
                            </div>

                            <div x-show="menu" x-cloak x-collapse.duration.250ms class="mt-3 flex flex-col gap-2 rounded-2xl bg-white/[.05] p-3">
                                <label class="flex flex-col gap-1">
                                    <span class="text-xs font-bold text-rock-muda">Pindah geng</span>
                                    <select class="isian-gelap !min-h-[44px] text-sm" wire:change="pindahGeng({{ $w->id }}, $event.target.value)">
                                        @foreach ($gengs as $g)
                                            <option value="{{ $g->id }}" @selected($g->id === $w->geng_id)>{{ $g->nama }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <div class="flex flex-wrap gap-2">
                                    <a href="{{ route('pengurus.warga.edit', $w) }}" wire:navigate class="tombol-kecil-gelap no-underline"><x-ikon nama="pensil" :ukuran="16" />&nbsp;Ubah</a>
                                    @if ($w->aktif)
                                        <button type="button" class="tombol-kecil-gelap"
                                                @click="$store.konfirmasi.tanya({ judul: @js(($w->token_hash ? 'Kirim ulang link ke ' : 'Kirim link ke ').$w->nama.'?'), pesan: @js('Link baru dikirim ke '.$w->email.'. Link lama tidak akan berlaku lagi.'), tombol: 'Kirim link', aksi: () => $wire.kirimLink({{ $w->id }}) })">
                                            <x-ikon nama="surat" :ukuran="16" />&nbsp;{{ $w->token_hash ? 'Kirim ulang link' : 'Kirim link' }}
                                        </button>
                                    @endif
                                    @unless ($w->is($saya))
                                        @if ($w->aktif)
                                            <button type="button" class="tekan inline-flex min-h-[44px] items-center rounded-xl bg-red-500/15 px-3.5 text-sm font-extrabold text-red-200"
                                                    @click="$store.konfirmasi.tanya({ judul: @js('Nonaktifkan '.$w->nama.'?'), pesan: 'Dia tidak akan ikut penjadwalan lagi, tapi riwayatnya tetap tersimpan.', tombol: 'Nonaktifkan', bahaya: true, aksi: () => $wire.ubahAktif({{ $w->id }}) })">
                                                Nonaktifkan
                                            </button>
                                        @else
                                            <button type="button" class="tombol-rock !min-h-[44px] text-sm" wire:click="ubahAktif({{ $w->id }})">Aktifkan</button>
                                        @endif
                                    @endunless
                                </div>
                            </div>
                        </li>
                    @empty
                        <li class="px-5 py-8 text-center text-sm text-rock-muda">Tidak ada anggota.</li>
                    @endforelse
                </ul>
            </section>
        @endforeach
    </div>
</div>
