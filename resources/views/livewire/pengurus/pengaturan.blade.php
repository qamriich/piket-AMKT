<div class="flex flex-col gap-5">
    <div class="naik flex flex-col gap-1">
        <h1 class="m-0 text-[30px] font-extrabold leading-tight sm:text-[36px]">Pengaturan</h1>
        <span class="text-[15px] text-rock-muda">Rotasi geng mingguan dan batas jam kuliah untuk piket harian.</span>
    </div>

    @include('livewire.pengurus._subnav-pengaturan', ['subnav' => 'rotasi'])

    <div class="grid gap-5 lg:grid-cols-2">
        <form wire:submit="simpan" class="kartu-kaca naik t2 flex flex-col gap-4 rounded-3xl p-5 sm:p-6">
            <h2 class="m-0 text-lg font-extrabold">Rotasi geng</h2>

            <div>
                <label for="minggu_acuan" class="label-gelap">Minggu acuan</label>
                <input id="minggu_acuan" type="date" wire:model="minggu_acuan" required class="isian-gelap [color-scheme:dark]">
                <p class="mt-1.5 text-[13px] text-rock-muda">Pilih tanggal mana pun, sistem memakai hari Senin dari minggu itu.</p>
                @error('minggu_acuan') <p class="galat-gelap">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="geng_acuan" class="label-gelap">Geng piket harian di minggu acuan</label>
                <select id="geng_acuan" wire:model="geng_acuan" required class="isian-gelap">
                    @foreach ($gengs as $urutan => $g)
                        <option value="{{ $urutan }}">{{ $g->nama }}</option>
                    @endforeach
                </select>
                @error('geng_acuan') <p class="galat-gelap">{{ $message }}</p> @enderror
            </div>

            <h2 class="m-0 border-t border-white/10 pt-4 text-lg font-extrabold">Piket harian</h2>
            <div>
                <label for="batas_jam_kuliah" class="label-gelap">Batas jam kuliah</label>
                <input id="batas_jam_kuliah" type="time" wire:model="batas_jam_kuliah" required class="isian-gelap [color-scheme:dark]">
                <p class="mt-1.5 text-[13px] text-rock-muda">Warga yang kuliah pertamanya dimulai sebelum jam ini tidak disarankan piket harian pada hari itu.</p>
                @error('batas_jam_kuliah') <p class="galat-gelap">{{ $message }}</p> @enderror
            </div>

            <p class="m-0 rounded-2xl border border-amber-200/30 bg-amber-300/10 p-3.5 text-[13px] leading-normal text-amber-100">
                Mengubah rotasi hanya berlaku untuk minggu yang jadwalnya belum dibuat. Minggu yang sudah dibuat tetap memakai geng yang tercatat.
            </p>

            <button type="submit" class="tombol-merino" wire:loading.attr="disabled" wire:target="simpan">
                <span wire:loading.remove wire:target="simpan">Simpan pengaturan</span>
                <span wire:loading wire:target="simpan">Menyimpan…</span>
            </button>
        </form>

        <section class="kartu-kaca naik t3 flex flex-col overflow-hidden rounded-3xl">
            <header class="border-b border-white/10 px-5 py-4">
                <h2 class="m-0 text-lg font-extrabold">Pratinjau giliran 8 minggu</h2>
                <p class="m-0 text-[13px] text-rock-muda">Berdasarkan pengaturan yang tersimpan.</p>
            </header>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-[15px]" wire:loading.class="opacity-50" wire:target="simpan">
                    <thead class="text-xs uppercase tracking-[0.06em] text-rock-muda">
                        <tr>
                            <th class="px-5 py-3 font-bold">Minggu</th>
                            <th class="px-3 py-3 font-bold">Harian</th>
                            <th class="px-5 py-3 font-bold">Kamis</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pratinjau as $i => $p)
                            @php $ini = $p['senin']->equalTo($seninIni); @endphp
                            <tr wire:key="pr-{{ $p['senin']->toDateString() }}" @class(['baris border-t border-white/[.06]', 'bg-rock/15' => $ini])>
                                <td class="whitespace-nowrap px-5 py-2.5">
                                    {{ $p['senin']->translatedFormat('j M') }} – {{ $p['minggu']->translatedFormat('j M') }}
                                    @if ($ini) <span class="text-xs font-bold text-green-300">(ini)</span> @endif
                                </td>
                                <td class="px-3 py-2.5 font-extrabold">
                                    {{ $gengs[$p['geng_harian']]->nama ?? 'Geng '.$p['geng_harian'] }}
                                    <span class="text-xs font-semibold text-rock-muda">({{ $gengs[$p['geng_harian']]->anggota_aktif_count ?? 0 }})</span>
                                </td>
                                <td class="px-5 py-2.5 text-krem">
                                    {{ collect($p['geng_kamis'])->map(fn ($u) => $gengs[$u]->nama ?? 'Geng '.$u)->join(' & ') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
