<div class="flex flex-col gap-5">
    <div class="naik flex flex-col gap-1">
        <h1 class="m-0 text-[30px] font-extrabold leading-tight sm:text-[36px]">Bintang</h1>
        <span class="text-[15px] text-rock-muda">Catat bintang secara manual, misalnya utang bintang dari sebelum aplikasi dipakai. Bintang ini ikut dijadwalkan bayar seperti bintang biasa, tanpa email ke warga.</span>
    </div>

    <div class="grid gap-5 lg:grid-cols-[minmax(0,380px)_minmax(0,1fr)]">
        <section class="kartu-kaca naik t2 flex flex-col gap-4 self-start rounded-3xl p-5">
            <h2 class="m-0 text-lg font-extrabold">Tambah bintang</h2>

            <form wire:submit="tambah" class="flex flex-col gap-3.5">
                <label class="flex flex-col gap-1.5 text-sm font-semibold">
                    Warga
                    <select wire:model="wargaId" required class="isian-gelap">
                        <option value="">Pilih warga</option>
                        @foreach ($daftarWarga as $w)
                            <option value="{{ $w->id }}">{{ $w->nama }} · {{ $w->geng?->nama ?? 'Tanpa geng' }}</option>
                        @endforeach
                    </select>
                    @error('wargaId') <span class="galat-gelap">{{ $message }}</span> @enderror
                </label>

                <label class="flex flex-col gap-1.5 text-sm font-semibold">
                    Jenis piket
                    <select wire:model.live="jenis" class="isian-gelap">
                        @foreach (\App\Enums\JenisPenugasan::cases() as $j)
                            <option value="{{ $j->value }}">{{ $j->label() }}</option>
                        @endforeach
                    </select>
                </label>

                @if ($pakaiTugas)
                    <label class="flex flex-col gap-1.5 text-sm font-semibold">
                        Tugas yang terlewat
                        <select wire:model="tugasId" required class="isian-gelap">
                            <option value="">Pilih tugas</option>
                            @foreach ($daftarTugas as $t)
                                <option value="{{ $t->id }}">{{ $t->nama }}</option>
                            @endforeach
                        </select>
                        <span class="text-xs font-normal text-rock-muda">Bintang dibayar dengan tugas yang sama di minggu berikutnya. Tugas yang belum ada bisa ditambah di Pengaturan, Master tugas.</span>
                        @error('tugasId') <span class="galat-gelap">{{ $message }}</span> @enderror
                    </label>
                @endif

                <label class="flex flex-col gap-1.5 text-sm font-semibold">
                    Tanggal piket yang terlewat
                    <input type="date" wire:model="tanggal" max="{{ $hariIni }}" required class="isian-gelap">
                    <span class="text-xs font-normal text-rock-muda">
                        @switch($jenis)
                            @case('kamis') Harus hari Kamis. @break
                            @case('minggu') @case('kerja_bakti') Harus hari Minggu. @break
                            @default Bintang harian dibayar lewat ganti bintang di hari kosong.
                        @endswitch
                        Bintang yang paling lama dibayar duluan.
                    </span>
                    @error('tanggal') <span class="galat-gelap">{{ $message }}</span> @enderror
                </label>

                <label class="flex flex-col gap-1.5 text-sm font-semibold">
                    Catatan <span class="font-normal text-rock-muda">(opsional)</span>
                    <input wire:model="catatan" maxlength="500" placeholder="Misalnya: dari catatan September" class="isian-gelap">
                </label>

                <button type="submit" class="tombol-rock" wire:loading.attr="disabled" wire:target="tambah">
                    <x-ikon nama="tambah" :ukuran="18" /> Catat bintang
                </button>
            </form>
        </section>

        <section class="kartu-kaca naik t3 flex flex-col overflow-hidden rounded-3xl">
            <header class="flex flex-col gap-0.5 border-b border-white/10 px-5 py-4">
                <h2 class="m-0 text-lg font-extrabold">Belum lunas <span class="text-sm font-semibold text-rock-muda">({{ $bintang->count() }})</span></h2>
                <span class="text-[13px] text-rock-muda">Semua bintang yang masih utang, dari yang paling lama.</span>
            </header>

            <ul class="m-0 flex list-none flex-col p-0">
                @forelse ($bintang as $b)
                    @php $bayar = $b->pembayaran->first(); @endphp
                    <li wire:key="b-{{ $b->id }}" class="baris flex items-center justify-between gap-3 border-t border-white/[.06] px-5 py-3 first:border-t-0">
                        <div class="min-w-0">
                            <span class="font-bold">{{ $b->user?->nama }}</span>
                            <span class="text-[13px] text-rock-muda">· {{ $b->user?->geng?->nama ?? 'Tanpa geng' }}</span>
                            @if ($b->manual)
                                <span class="badge badge-netral ml-1">Manual</span>
                            @endif
                            <span class="block text-[13px] text-krem">
                                {{ $b->jenis->label() }}@if ($b->tugas) · {{ $b->tugas->nama }}@endif · {{ $b->tanggal->translatedFormat('D, j M Y') }}
                            </span>
                            <span class="block text-[13px] {{ $bayar ? 'text-teal-300' : 'text-rock-muda' }}">
                                @if ($bayar)
                                    Dijadwalkan bayar {{ $bayar->tanggal->translatedFormat('D, j M') }}
                                @elseif ($b->pengajuan_ganti_bintang_exists)
                                    Sudah diajukan ganti bintang
                                @else
                                    Menunggu dijadwalkan
                                @endif
                                @if ($b->catatan) · {{ $b->catatan }} @endif
                            </span>
                        </div>
                        @if ($b->manual && ! $bayar && ! $b->pengajuan_ganti_bintang_exists)
                            <button type="button" class="tekan flex h-10 w-10 flex-none items-center justify-center rounded-xl text-red-300 hover:bg-red-500/15" aria-label="Hapus bintang {{ $b->user?->nama }}"
                                    @click="$store.konfirmasi.tanya({ judul: @js('Hapus bintang '.$b->user?->nama.'?'), pesan: 'Pakai ini kalau bintangnya salah catat.', tombol: 'Hapus', bahaya: true, aksi: () => $wire.hapus({{ $b->id }}) })">
                                <x-ikon nama="sampah" :ukuran="17" />
                            </button>
                        @endif
                    </li>
                @empty
                    <li class="px-5 py-10 text-center text-sm text-rock-muda">Tidak ada bintang yang belum lunas.</li>
                @endforelse
            </ul>
        </section>
    </div>
</div>
