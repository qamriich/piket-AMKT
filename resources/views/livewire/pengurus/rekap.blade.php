<div class="flex flex-col gap-5">
    <div class="naik flex flex-wrap items-end justify-between gap-4">
        <div class="flex flex-col gap-1">
            <h1 class="m-0 text-[30px] font-extrabold leading-tight sm:text-[36px]">Rekap bulanan</h1>
            <span class="text-[15px] text-rock-muda">{{ $awal->translatedFormat('F Y') }} · hanya jadwal yang sudah terbit</span>
        </div>
        <div class="flex flex-wrap gap-2">
            <input type="month" wire:model.live="bulan" aria-label="Bulan" class="isian-gelap !w-auto">
            <select wire:model.live="jenis" aria-label="Jenis piket" class="isian-gelap !w-auto">
                <option value="">Semua jenis</option>
                <option value="harian">Harian</option>
                <option value="kamis">Kamis</option>
                <option value="minggu">Minggu & kerja bakti</option>
            </select>
            <button type="button" class="tombol-kaca" wire:click="ekspor('xlsx')" wire:loading.attr="disabled" wire:target="ekspor" title="Unduh file Excel (.xlsx)">
                <x-ikon nama="unduh" :ukuran="18" /> Ekspor Excel
            </button>
            <button type="button" class="tombol-kaca" wire:click="ekspor('csv')" wire:loading.attr="disabled" wire:target="ekspor" title="Unduh CSV (pemisah titik koma)">
                <x-ikon nama="unduh" :ukuran="18" /> CSV
            </button>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-3.5 lg:grid-cols-4">
        @foreach ([['Jumlah piket', 'terjadwal', 'text-white'], ['Selesai', 'selesai', 'text-green-300'], ['Bintang baru', 'bintang_baru', 'text-red-300'], ['Utang belum lunas', 'utang', 'text-amber-200']] as $i => [$label, $k, $warna])
            <div class="kartu-kaca angkat naik t{{ $i + 1 }} flex flex-col gap-1.5 p-5">
                <span x-data x-hitung data-nilai="{{ $total[$k] }}" class="text-[36px] font-extrabold leading-none {{ $warna }}">{{ $total[$k] }}</span>
                <span class="text-sm font-semibold text-krem">{{ $label }}</span>
            </div>
        @endforeach
    </div>

    <section class="kartu-kaca naik t3 overflow-hidden rounded-3xl">
        <div class="overflow-x-auto" wire:loading.class="opacity-50">
            <table class="w-full min-w-[640px] text-left text-[15px]">
                <thead class="text-xs uppercase tracking-[0.06em] text-rock-muda">
                    <tr class="border-b border-white/10">
                        <th class="px-5 py-3.5 font-bold">Warga</th>
                        <th class="px-3 py-3.5 text-right font-bold">Jumlah piket</th>
                        <th class="px-3 py-3.5 text-right font-bold">Selesai</th>
                        <th class="px-3 py-3.5 text-right font-bold">Bintang baru</th>
                        <th class="px-3 py-3.5 text-right font-bold">Lunas</th>
                        <th class="px-5 py-3.5 text-right font-bold">Utang</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($warga as $r)
                        <tr wire:key="rk-{{ $r['user']->id }}" class="baris border-t border-white/[.06]">
                            <td class="px-5 py-3">
                                <span class="font-bold">{{ $r['user']->nama }}</span>
                                <span class="block text-[13px] text-rock-muda">{{ $r['user']->geng?->nama ?? 'Tanpa geng' }}@unless ($r['user']->aktif) · nonaktif @endunless</span>
                            </td>
                            <td class="px-3 py-3 text-right">{{ $r['terjadwal'] }}</td>
                            <td class="px-3 py-3 text-right text-green-300">{{ $r['selesai'] }}</td>
                            <td class="px-3 py-3 text-right {{ $r['bintang_baru'] ? 'text-red-300' : 'text-rock-redup' }}">{{ $r['bintang_baru'] }}</td>
                            <td class="px-3 py-3 text-right {{ $r['lunas'] ? 'text-teal-300' : 'text-rock-redup' }}">{{ $r['lunas'] }}</td>
                            <td class="px-5 py-3 text-right font-extrabold {{ $r['utang'] ? 'text-amber-200' : 'text-rock-redup' }}">{{ $r['utang'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
    <p class="m-0 flex items-start gap-2 text-[13px] text-rock-muda">
        <x-ikon nama="info" :ukuran="16" class="mt-0.5 flex-none" />
        Utang dihitung sampai akhir bulan yang dipilih. File ekspor berisi angka yang sama dengan tabel ini, ditambah baris total.
    </p>
</div>
