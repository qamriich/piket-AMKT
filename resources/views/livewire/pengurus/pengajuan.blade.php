<div class="flex flex-col gap-5">
    <div class="naik flex flex-col gap-1">
        <h1 class="m-0 text-[30px] font-extrabold leading-tight sm:text-[36px]">Pengajuan</h1>
        <span class="text-[15px] text-rock-muda">
            Ganti bintang: warga memilih hari kosong untuk mengganti bintang harian, dan pengajuan yang diterima mengisi hari itu dengan status Bayar Bintang.
            Izin: piket yang seluruh waktunya tertutup izin dilepas dan dicarikan pengganti.
        </span>
    </div>

    <div role="tablist" class="segmen-gelap naik t1 self-start">
        <button type="button" role="tab" class="segmen-gelap-tombol" aria-selected="{{ $tampil === 'menunggu' ? 'true' : 'false' }}" wire:click="setTampil('menunggu')">
            Ganti bintang @if ($jumlahMenunggu) ({{ $jumlahMenunggu }}) @endif
        </button>
        <button type="button" role="tab" class="segmen-gelap-tombol" aria-selected="{{ $tampil === 'riwayat' ? 'true' : 'false' }}" wire:click="setTampil('riwayat')">Riwayat</button>
        <button type="button" role="tab" class="segmen-gelap-tombol" aria-selected="{{ $tampil === 'izin' ? 'true' : 'false' }}" wire:click="setTampil('izin')">
            Izin @if ($jumlahIzin) ({{ $jumlahIzin }}) @endif
        </button>
    </div>

    <div wire:loading.flex wire:target="setTampil" class="kartu-kaca flex-col gap-2 rounded-3xl p-[18px]">
        <div class="kerangka h-20"></div><div class="kerangka h-20"></div>
    </div>

    @if ($tampil !== 'izin')
        <section wire:loading.remove wire:target="setTampil" class="kartu-kaca naik t2 flex flex-col gap-3 rounded-3xl p-[18px] sm:p-[22px]">
            @forelse ($daftar as $i => $a)
                <div wire:key="aj-{{ $a->id }}" x-data x-muncul="{{ min($i, 6) }}" class="flex flex-wrap items-center gap-3 rounded-2xl border border-white/[.08] p-4">
                    <div class="flex h-11 w-11 flex-none items-center justify-center rounded-full bg-langit font-extrabold text-venice">{{ $a->user->inisial() }}</div>
                    <div class="flex min-w-0 flex-[1_1_220px] flex-col gap-0.5">
                        <span class="text-base font-extrabold">{{ $a->user->nama }} <span class="text-sm font-semibold text-rock-muda">· {{ $a->user->geng?->nama }}</span></span>
                        <span class="text-sm text-krem">
                            Bintang harian {{ $a->penugasanBintang?->tanggalLokal()->translatedFormat('l, j M') }} → ganti {{ $a->tanggal_diajukan->translatedFormat('l, j M') }}
                        </span>
                        @if ($a->catatan)
                            <span class="text-[13px] text-rock-muda">Catatan: {{ $a->catatan }}</span>
                        @endif
                        @if ($a->diputuskanOleh)
                            <span class="text-[13px] text-rock-muda">Diputuskan {{ $a->diputuskanOleh->namaDepan() }}{{ $a->diputuskan_pada ? ', '.$a->diputuskan_pada->translatedFormat('j M H.i') : '' }}</span>
                        @endif
                    </div>
                    <x-badge :status="$a->status" />
                    @if ($a->status === \App\Enums\StatusPengajuan::Menunggu)
                        @include('livewire.pengurus._putuskan-pengajuan', ['a' => $a])
                    @endif
                </div>
            @empty
                <div class="flex flex-col items-center gap-2 px-6 py-10 text-center">
                    <x-ikon nama="kotak-masuk" :ukuran="30" class="text-rock" />
                    <span class="text-base font-extrabold">{{ $tampil === 'menunggu' ? 'Tidak ada pengajuan yang menunggu' : 'Belum ada riwayat pengajuan' }}</span>
                    <span class="text-sm text-rock-muda">Semua aman.</span>
                </div>
            @endforelse
            <p class="m-0 flex items-start gap-2 text-[13px] text-rock-muda">
                <x-ikon nama="info" :ukuran="16" class="mt-0.5 flex-none" />
                Warga mendapat email setiap kali pengajuannya diterima atau ditolak, beserta catatanmu.
            </p>
        </section>
    @else
        <div wire:loading.remove wire:target="setTampil" class="flex flex-wrap items-start gap-5">
            <section class="kartu-kaca naik t2 flex min-w-0 flex-[999_1_420px] flex-col gap-3 rounded-3xl p-[18px] sm:p-[22px]">
                <h2 class="m-0 text-lg font-extrabold">Menunggu persetujuan</h2>
                @forelse ($izinMenunggu as $iz)
                    <div wire:key="izin-{{ $iz->id }}" x-data="{ catatan: '', tulis: false }" class="flex flex-wrap items-center gap-3 rounded-2xl border border-white/[.08] p-4">
                        <div class="flex h-11 w-11 flex-none items-center justify-center rounded-full bg-langit font-extrabold text-venice">{{ $iz->user->inisial() }}</div>
                        <div class="flex min-w-0 flex-[1_1_220px] flex-col gap-0.5">
                            <span class="text-base font-extrabold">{{ $iz->user->nama }} <span class="text-sm font-semibold text-rock-muda">· {{ $iz->user->geng?->nama }}</span></span>
                            <span class="text-sm text-krem">Izin {{ $iz->rentangLabel() }}</span>
                            @if ($iz->alasan)
                                <span class="text-[13px] text-rock-muda">Alasan: {{ $iz->alasan }}</span>
                            @endif
                        </div>
                        <div class="flex w-full flex-col gap-2 sm:w-auto">
                            <textarea x-show="tulis" x-cloak x-model="catatan" rows="2" maxlength="500" aria-label="Catatan untuk {{ $iz->user->namaDepan() }}"
                                      class="isian-gelap !min-h-[60px] py-2 text-sm" placeholder="Catatan (boleh kosong)"></textarea>
                            <div class="grid grid-cols-2 gap-2">
                                <button type="button" class="tombol-kaca"
                                        @click="$store.konfirmasi.tanya({ judul: @js('Tolak izin '.$iz->user->namaDepan().'?'), pesan: 'Piketnya tetap berjalan seperti biasa.', tombol: 'Tolak', bahaya: true, aksi: () => $wire.tolakIzin({{ $iz->id }}, catatan) })">Tolak</button>
                                <button type="button" class="tombol-rock"
                                        @click="$store.konfirmasi.tanya({ judul: @js('Setujui izin '.$iz->user->namaDepan().'?'), pesan: 'Piket terbit yang seluruh waktunya tertutup izin ini menjadi Izin dan sistem memilih pengganti.', tombol: 'Setujui', aksi: () => $wire.setujuiIzin({{ $iz->id }}, catatan) })">Setujui</button>
                            </div>
                            <button type="button" class="self-start text-[13px] font-bold text-rock-muda underline" x-show="!tulis" @click="tulis = true">+ Tambah catatan</button>
                        </div>
                    </div>
                @empty
                    <p class="m-0 text-[15px] text-krem">Tidak ada izin yang menunggu.</p>
                @endforelse

                <h2 class="mb-0 mt-3 text-lg font-extrabold">Riwayat izin</h2>
                @forelse ($izinRiwayat as $iz)
                    <div wire:key="izin-r-{{ $iz->id }}" class="flex flex-wrap items-center justify-between gap-3 border-t border-white/[.07] pt-3">
                        <div class="flex min-w-0 flex-col gap-0.5">
                            <span class="text-[15px] font-extrabold">{{ $iz->user->nama }} · <span class="font-semibold text-krem">{{ $iz->rentangLabel() }}</span></span>
                            <span class="text-[13px] text-rock-muda">
                                @if ($iz->alasan) {{ $iz->alasan }} · @endif
                                {{ $iz->diputuskanOleh ? 'Diputuskan '.$iz->diputuskanOleh->namaDepan() : '' }}
                                @if ($iz->catatan) · Catatan: {{ $iz->catatan }} @endif
                            </span>
                        </div>
                        <x-badge :status="$iz->status" />
                    </div>
                @empty
                    <p class="m-0 text-[15px] text-krem">Belum ada riwayat izin.</p>
                @endforelse
            </section>

            <section class="kartu-kaca naik t3 flex min-w-0 flex-[1_1_300px] flex-col gap-3 rounded-3xl p-[22px]">
                <div class="flex flex-col gap-0.5">
                    <h2 class="m-0 text-lg font-extrabold">Buat izin</h2>
                    <span class="text-sm text-rock-muda">Izin yang dibuat pengurus langsung disetujui.</span>
                </div>
                <form wire:submit="buatIzin" class="flex flex-col gap-3">
                    <label class="flex flex-col gap-1.5 text-sm font-semibold text-krem">
                        Warga
                        <select wire:model="izin_user" class="isian-gelap" required>
                            <option value="">Pilih warga</option>
                            @foreach ($warga as $w)
                                <option value="{{ $w->id }}">{{ $w->nama }}</option>
                            @endforeach
                        </select>
                        @error('izin_user') <span class="text-[13px] text-red-300">{{ $message }}</span> @enderror
                    </label>
                    @include('livewire._isian-izin', ['gelap' => true])
                    <button type="submit" class="tombol-merino" wire:loading.attr="disabled" wire:target="buatIzin">
                        <span wire:loading.remove wire:target="buatIzin">Simpan izin</span>
                        <span wire:loading wire:target="buatIzin">Menyimpan…</span>
                    </button>
                </form>
            </section>
        </div>
    @endif
</div>
