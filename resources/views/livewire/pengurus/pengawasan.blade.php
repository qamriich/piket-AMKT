@php
    use App\Enums\JenisPenugasan;
    use App\Enums\StatusPenugasan;
    $warnaAvatar = [['#F5EEDD', '#7A5B1E'], ['#DBEAFE', '#1E40AF'], ['#DCFCE7', '#166534'], ['#FCE7F3', '#9D174D'], ['#EDE9FE', '#5B21B6']];
    $mingguTerbit = $minggu->sudahTerbit('minggu');
@endphp
<div class="flex flex-col gap-[26px]" x-on:gulir-ke.window="$nextTick(() => document.getElementById($event.detail.id)?.scrollIntoView({ behavior: 'smooth', block: 'start' }))">

    {{-- R3: tugas tanpa petugas (misalnya tidak ada calon pengganti untuk warga yang izin) tampil paling atas. --}}
    @if ($tanpaPetugas->isNotEmpty())
        <section class="naik flex flex-col gap-3 rounded-3xl border border-orange-300/40 bg-orange-400/[.12] p-[18px] sm:p-[22px]" aria-labelledby="judul-tanpa-petugas">
            <div class="flex flex-col gap-0.5">
                <h2 id="judul-tanpa-petugas" class="m-0 flex items-center gap-2 text-lg font-extrabold">
                    <x-ikon nama="info" :ukuran="20" class="text-orange-200" />
                    Belum ada petugas ({{ $tanpaPetugas->count() }})
                </h2>
                <span class="text-sm text-krem">Tidak ada calon pengganti otomatis. Pilih petugasnya; ia akan mendapat email.</span>
            </div>
            <div class="flex flex-col gap-2">
                @foreach ($tanpaPetugas as $b)
                    <div wire:key="kosong-{{ $b['minggu']->id }}-{{ $b['sesi']->value }}-{{ $b['tugas']->id }}" x-data="{ pilih: '' }"
                         class="flex flex-wrap items-center gap-3 rounded-2xl border border-white/[.08] bg-navy/40 px-4 py-3">
                        <div class="flex min-w-0 flex-[1_1_200px] flex-col gap-0.5">
                            <span class="text-[13px] font-semibold text-rock-muda">Piket {{ $b['sesi']->label() }} · {{ $b['tanggal']->translatedFormat('l, j M') }}</span>
                            <span class="text-base font-extrabold">{{ $b['tugas']->nama }}</span>
                        </div>
                        <select x-model="pilih" aria-label="Petugas untuk {{ $b['tugas']->nama }}" class="isian-gelap !min-h-[44px] !w-auto py-0 text-sm">
                            <option value="">Pilih petugas</option>
                            @foreach ($b['calon'] as $c)
                                <option value="{{ $c['id'] }}">{{ $c['nama'] }}{{ $c['sudah'] ? ' (sudah ada tugas)' : '' }}</option>
                            @endforeach
                        </select>
                        <button type="button" class="tombol-merino !min-h-[44px] text-sm" :disabled="!pilih"
                                @click="$wire.isiPetugas({{ $b['minggu']->id }}, @js($b['sesi']->value), {{ $b['tugas']->id }}, pilih)">Isi</button>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="flex flex-wrap items-stretch gap-[22px]">
        <div class="naik flex min-w-0 flex-[1_1_380px] flex-col justify-center gap-[18px]">
            <span class="kaca inline-flex items-center gap-2.5 self-start rounded-full px-3.5 py-2 text-sm font-semibold text-merino">
                <span class="denyut h-[9px] w-[9px] rounded-full bg-green-400"></span>
                Minggu ke-{{ $sekarang->isoWeek() }} · {{ $minggu->gengHarian?->nama }} piket harian
            </span>
            <h1 class="m-0 text-[34px] font-extrabold leading-[1.08] tracking-[-0.02em] sm:text-[46px]">
                {{ $sapaan }},<br><span class="text-rock">{{ $user->namaDepan() }}</span>
            </h1>
            <p class="m-0 max-w-[440px] text-[17px] leading-relaxed text-krem">
                Hari ini {{ $sekarang->translatedFormat('l') }}.
                {{ $fokus['label'] === 'Piket harian minggu ini' ? 'Piket harian berjalan jam 04.00–09.00' : str_replace(' selesai', '', $fokus['label']).' sedang berjalan, '.$fokus['keterangan'] }}.
                @if ($perluDiperiksa->count() > 0)
                    Ada {{ $perluDiperiksa->count() }} piket yang menunggu kamu periksa.
                @else
                    Belum ada piket yang perlu diperiksa.
                @endif
            </p>
            <div class="flex flex-wrap gap-3">
                <button type="button" wire:click="periksaSekarang" class="tombol-merino">Periksa sekarang →</button>
                <a href="{{ route('dashboard') }}" class="tombol-kaca !min-h-[54px] px-[22px] text-base no-underline">Lihat tampilan warga</a>
            </div>
            <div class="flex flex-wrap gap-[18px] text-sm text-rock-muda">
                <span><strong class="text-white" x-data x-hitung data-nilai="{{ $jumlahWarga }}">{{ $jumlahWarga }}</strong> warga aktif</span>
                <span><strong class="text-white">{{ $jumlahGeng }}</strong> geng</span>
                <span><strong class="text-white">{{ $jumlahPengurus }}</strong> pengurus</span>
            </div>
        </div>

        <div class="grid min-w-0 flex-[1_1_460px] grid-cols-2 gap-3.5">
            <div class="kartu-kaca angkat naik t1 flex flex-col gap-2.5 p-5">
                <div class="flex items-start justify-between">
                    <div class="flex h-[42px] w-[42px] items-center justify-center rounded-xl bg-rock/20 text-rock-muda">
                        <x-ikon nama="centang" :ukuran="20" :tebal="2.2" />
                    </div>
                    <div aria-hidden="true" class="flex h-9 items-end gap-1" title="Piket yang ditandai selesai, 7 hari terakhir">
                        @foreach ($batang as $i => $b)
                            <span class="batang block w-1.5 rounded-[3px] {{ $b['hari_ini'] ? 'bg-rock' : 'bg-green-400' }}"
                                  style="height: {{ $b['tinggi'] }}px; animation-delay: {{ 0.3 + $i * 0.07 }}s"></span>
                        @endforeach
                    </div>
                </div>
                <span class="text-[40px] font-extrabold leading-none">
                    <span x-data x-hitung data-nilai="{{ $fokus['selesai'] }}">{{ $fokus['selesai'] }}</span><span class="text-xl text-rock-muda"> / {{ $fokus['total'] }}</span>
                </span>
                <div class="flex flex-col">
                    <span class="text-[15px] font-bold">{{ $fokus['label'] }}</span>
                    <span class="text-[13px] text-rock-muda">{{ $fokus['keterangan'] }}</span>
                </div>
            </div>

            <div class="kartu-kaca angkat naik t2 flex flex-col gap-2.5 p-5">
                <div class="flex min-h-[42px]">
                    @forelse ($perluDiperiksa->take(5)->values() as $i => $p)
                        @php [$bg, $fg] = $warnaAvatar[$i % 5]; @endphp
                        <span class="flex h-[38px] w-[38px] items-center justify-center rounded-full border-2 border-navy text-sm font-extrabold {{ $i ? '-ml-2.5' : '' }}"
                              style="background: {{ $bg }}; color: {{ $fg }}" title="{{ $p->user->nama }}">{{ $p->user->inisial() }}</span>
                    @empty
                        <div class="flex h-[42px] w-[42px] items-center justify-center rounded-xl bg-rock/20 text-rock-muda">
                            <x-ikon nama="perisai" :ukuran="20" />
                        </div>
                    @endforelse
                </div>
                <span x-data x-hitung data-nilai="{{ $perluDiperiksa->count() }}" class="text-[40px] font-extrabold leading-none text-merino">{{ $perluDiperiksa->count() }}</span>
                <div class="flex flex-col">
                    <span class="text-[15px] font-bold">Perlu diperiksa</span>
                    <span class="text-[13px] text-rock-muda">warga wajib diperiksa</span>
                </div>
            </div>

            <div class="kartu-kaca angkat naik t3 col-span-2 flex flex-col gap-4 p-5"
                 @if ($tenggatBerikut) x-data="hitungMundur(@js($tenggatBerikut->tenggatLokal()->toIso8601String()), @js($tenggatBerikut->mulai()->toIso8601String()))" @endif>
                <div class="flex items-center justify-between gap-3">
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="flex h-[42px] w-[42px] flex-none items-center justify-center rounded-xl bg-rock/20 text-rock-muda">
                            <x-ikon nama="kalender" :ukuran="20" />
                        </div>
                        <div class="flex min-w-0 flex-col">
                            @if ($tenggatBerikut)
                                <span class="text-[13px] text-rock-muda">Berikutnya · {{ $tenggatBerikut->tenggatLokal()->translatedFormat('l, j M') }}</span>
                                <span class="truncate text-base font-extrabold">Batas piket {{ $tenggatBerikut->jenis === JenisPenugasan::KerjaBakti ? 'kerja bakti' : $tenggatBerikut->jenis->label() }} {{ $tenggatBerikut->tenggatLokal()->format('H.i') }}</span>
                            @else
                                <span class="text-[13px] text-rock-muda">Berikutnya</span>
                                <span class="text-base font-extrabold">Tidak ada tenggat yang berjalan</span>
                            @endif
                        </div>
                    </div>
                    @if ($tenggatBerikut)
                        <span class="flex-none text-sm text-rock-muda"><strong class="text-[30px] text-merino" x-text="angka">0</strong> <span x-text="satuan"></span></span>
                    @endif
                </div>
                <div class="relative h-1.5 rounded-full bg-white/[.12]">
                    <div class="isi-bar absolute inset-y-0 left-0 rounded-full bg-green-400" style="width: 0" @if ($tenggatBerikut) :style="`width: ${persen}%`" @endif></div>
                </div>
                <div class="grid grid-cols-7 gap-1">
                    @foreach ($pekan as $w)
                        <div class="flex min-w-0 flex-col gap-[3px]">
                            <span @class(['text-[13px] font-extrabold', 'text-rock' => $w['keadaan'] === 'sekarang', 'text-green-300' => $w['keadaan'] === 'selesai', 'text-rock-muda' => ! in_array($w['keadaan'], ['sekarang', 'selesai'])])>{{ $w['hari'] }}</span>
                            <span @class(['truncate text-[13px]', 'italic text-rock-redup' => in_array($w['keadaan'], ['kosong', 'draf']), 'font-semibold text-merino' => ! in_array($w['keadaan'], ['kosong', 'draf'])])>{{ $w['nama'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <div class="flex flex-wrap items-start gap-5">
        <section id="periksa" class="kartu-kaca naik t4 flex min-w-0 flex-[999_1_480px] scroll-mt-24 flex-col gap-4 rounded-3xl p-[18px] sm:p-[22px]">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-col gap-0.5">
                    <h2 class="m-0 text-xl font-extrabold">{{ $rentang === 'hari' ? 'Piket hari ini' : 'Piket minggu ini' }}</h2>
                    <span class="text-sm text-rock-muda">
                        {{ $rentang === 'hari' ? $sekarang->translatedFormat('l, j M') : $minggu->senin()->translatedFormat('j M').' – '.$minggu->hariMinggu()->translatedFormat('j M') }}
                    </span>
                </div>
                <div role="tablist" aria-label="Rentang" class="segmen-gelap">
                    <button type="button" role="tab" class="segmen-gelap-tombol" aria-selected="{{ $rentang === 'hari' ? 'true' : 'false' }}" wire:click="setRentang('hari')">Hari ini</button>
                    <button type="button" role="tab" class="segmen-gelap-tombol" aria-selected="{{ $rentang === 'minggu' ? 'true' : 'false' }}" wire:click="setRentang('minggu')">Minggu ini</button>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <div role="tablist" aria-label="Saring status" class="segmen-gelap">
                    @foreach (['semua' => 'Semua', 'periksa' => 'Perlu diperiksa', 'belum' => 'Belum', 'selesai' => 'Selesai'] as $k => $label)
                        <button type="button" role="tab" class="segmen-gelap-tombol" aria-selected="{{ $filter === $k ? 'true' : 'false' }}" wire:click="setFilter('{{ $k }}')">{{ $label }}</button>
                    @endforeach
                </div>
                @if ($rentang === 'minggu')
                    <select wire:model.live="jenis" aria-label="Jenis piket" class="isian-gelap !min-h-[44px] !w-auto py-0 text-sm">
                        <option value="">Semua jenis</option>
                        <option value="harian">Harian</option>
                        <option value="kamis">Kamis</option>
                        <option value="minggu">Minggu</option>
                    </select>
                    <select wire:model.live="geng" aria-label="Geng" class="isian-gelap !min-h-[44px] !w-auto py-0 text-sm">
                        <option value="0">Semua geng</option>
                        @foreach ($gengs as $g)
                            <option value="{{ $g->id }}">{{ $g->nama }}</option>
                        @endforeach
                    </select>
                @endif
            </div>

            <div wire:loading.flex wire:target="setFilter, setRentang, jenis, geng, periksaSekarang" class="flex-col gap-2">
                @for ($i = 0; $i < 4; $i++)
                    <div class="kerangka h-[62px]"></div>
                @endfor
            </div>

            <div wire:loading.remove wire:target="setFilter, setRentang, jenis, geng, periksaSekarang" class="flex flex-col gap-4">
                @if ($harianHariIni)
                    <div class="letup flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-rock/35 bg-rock/[.14] px-4 py-3.5">
                        <div class="flex min-w-0 flex-[1_1_200px] flex-col gap-0.5">
                            <span class="text-xs font-bold tracking-[0.04em] text-rock-muda">HARIAN · 04.00–09.00</span>
                            <span class="flex flex-wrap items-center gap-2 text-base font-extrabold">
                                {{ $harianHariIni->user->nama }}
                                @if ($harianHariIni->user->wajib_diperiksa)
                                    <span class="rounded-full border border-merino/30 bg-merino/10 px-2 py-[3px] text-[11px] font-bold text-merino">Wajib diperiksa</span>
                                @endif
                            </span>
                        </div>
                        <x-badge :status="$harianHariIni->status"
                                 :label="$harianHariIni->status === StatusPenugasan::Selesai && $harianHariIni->selesai_pada ? 'Selesai '.$harianHariIni->selesai_pada->format('H.i') : null" />
                        @if ($harianHariIni->catatan)
                            <span class="w-full text-[13px] text-krem">Catatan: {{ $harianHariIni->catatan }}</span>
                        @endif
                        @include('livewire.pengurus._aksi-piket', ['p' => $harianHariIni, 'jenisAksi' => $aksi($harianHariIni)])
                    </div>
                @endif

                <div class="flex flex-col overflow-hidden rounded-2xl border border-white/[.08]">
                    @forelse ($daftar as $i => $p)
                        @php $selesai = in_array($p->status, [StatusPenugasan::Selesai, StatusPenugasan::Lunas], true); @endphp
                        <div wire:key="baris-{{ $p->id }}" @class(['baris flex flex-wrap items-center gap-3 px-4 py-3', 'border-t border-white/[.07]' => $i > 0, 'bg-green-500/[.06]' => $selesai])>
                            <div class="flex min-w-0 flex-[1_1_200px] flex-col gap-[3px]">
                                <span class="text-[13px] font-semibold text-rock-muda">
                                    @if ($rentang === 'minggu') {{ $p->tanggalLokal()->translatedFormat('D, j M') }} · @endif
                                    {{ $p->jenis === JenisPenugasan::Harian ? 'Harian' : $p->namaTugas() }}
                                    @if ($p->status === StatusPenugasan::BayarBintang) · ganti bintang @endif
                                </span>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-base font-extrabold">{{ $p->user->nama }}</span>
                                    @if ($p->user->wajib_diperiksa)
                                        <span class="rounded-full border border-merino/30 bg-merino/10 px-2 py-[3px] text-[11px] font-bold text-merino">Wajib diperiksa</span>
                                    @endif
                                </div>
                                @if ($p->catatan)
                                    <span class="text-[13px] text-krem">Catatan: {{ $p->catatan }}</span>
                                @endif
                            </div>
                            <x-badge :status="$p->status"
                                     :label="$p->status === StatusPenugasan::Selesai && $p->selesai_pada ? 'Selesai '.$p->selesai_pada->format('H.i').($p->selesaiOleh && $p->selesai_oleh_id !== $p->user_id ? ' · '.$p->selesaiOleh->namaDepan() : '') : null" />
                            @include('livewire.pengurus._aksi-piket', ['p' => $p, 'jenisAksi' => $aksi($p)])
                        </div>
                    @empty
                        <div class="flex flex-col items-center gap-2 px-7 py-8 text-center text-[15px] text-rock-muda">
                            <x-ikon nama="kilau" :ukuran="26" class="text-rock" />
                            @if ($filter === 'periksa')
                                Tidak ada piket yang perlu diperiksa. Semua aman.
                            @else
                                Tidak ada piket di saringan ini.
                            @endif
                        </div>
                    @endforelse
                </div>
                <p class="m-0 flex items-start gap-2 text-[13px] leading-normal text-rock-muda">
                    <x-ikon nama="info" :ukuran="16" class="mt-0.5 flex-none" />
                    Daftar ini hanya berisi jadwal yang sudah terbit. Tandai selesai saat piket diperiksa, sebelum batas waktunya.
                    Setelah batas lewat, ubah status lewat Koreksi dengan catatan.
                </p>
            </div>
        </section>

        <aside class="flex min-w-0 flex-[1_1_320px] flex-col gap-5">
            <section class="kartu-kaca naik t4 flex flex-col gap-3.5 rounded-3xl p-[22px]">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="m-0 text-lg font-extrabold">Pengajuan ganti bintang</h2>
                    @if ($jumlahPengajuan)
                        <span class="letup flex h-[22px] min-w-[22px] items-center justify-center rounded-full bg-rock px-1.5 text-xs font-extrabold text-navy">{{ $jumlahPengajuan }}</span>
                    @endif
                </div>
                @forelse ($pengajuan as $a)
                    <div wire:key="ajuan-{{ $a->id }}" class="flex flex-col gap-3">
                        <div class="flex items-center gap-3">
                            <div class="flex h-[42px] w-[42px] flex-none items-center justify-center rounded-full bg-langit font-extrabold text-venice">{{ $a->user->inisial() }}</div>
                            <div class="flex min-w-0 flex-col gap-0.5">
                                <span class="text-base font-extrabold">{{ $a->user->nama }}</span>
                                <span class="text-sm text-krem">
                                    Bintang harian {{ $a->penugasanBintang?->tanggalLokal()->translatedFormat('j M') }} → ganti {{ $a->tanggal_diajukan->translatedFormat('l, j M') }}
                                </span>
                            </div>
                        </div>
                        @include('livewire.pengurus._putuskan-pengajuan', ['a' => $a])
                    </div>
                @empty
                    <p class="m-0 text-[15px] leading-normal text-krem">Belum ada pengajuan yang menunggu.</p>
                @endforelse
                @if ($jumlahPengajuan > 3)
                    <a href="{{ route('pengurus.pengajuan') }}" wire:navigate class="text-sm font-bold text-merino no-underline">Lihat semua pengajuan →</a>
                @endif
            </section>

            <section class="kartu-kaca naik t4 flex flex-col gap-3 rounded-3xl p-[22px]" aria-labelledby="judul-izin">
                <div class="flex items-center justify-between gap-3">
                    <h2 id="judul-izin" class="m-0 text-lg font-extrabold">Sedang izin minggu ini</h2>
                    <a href="{{ route('pengurus.pengajuan', ['tampil' => 'izin']) }}" wire:navigate class="text-sm font-bold text-merino no-underline">Izin →</a>
                </div>
                @forelse ($sedangIzin as $iz)
                    <div wire:key="izin-{{ $iz->id }}" class="flex items-center gap-3">
                        <div class="flex h-[38px] w-[38px] flex-none items-center justify-center rounded-full bg-white/10 text-sm font-extrabold text-merino">{{ $iz->user->inisial() }}</div>
                        <div class="flex min-w-0 flex-col">
                            <span class="text-[15px] font-extrabold">{{ $iz->user->nama }} <span class="text-sm font-semibold text-rock-muda">· {{ $iz->user->geng?->nama }}</span></span>
                            <span class="text-sm text-krem">{{ $iz->rentangLabel() }}@if ($iz->alasan) · {{ $iz->alasan }} @endif</span>
                        </div>
                    </div>
                @empty
                    <p class="m-0 text-[15px] leading-normal text-krem">Tidak ada yang izin minggu ini.</p>
                @endforelse
            </section>

            <section class="kartu-kaca naik t5 flex flex-col gap-3.5 rounded-3xl p-[22px]">
                <div class="flex flex-col gap-0.5">
                    <h2 class="m-0 text-lg font-extrabold">Atur piket Minggu, {{ $minggu->hariMinggu()->translatedFormat('j M') }}</h2>
                    <span class="text-sm text-rock-muda">{{ $minggu->kerjaBakti() ? 'Kerja bakti' : 'Tugas kecil' }}</span>
                </div>

                @if ($mingguTerbit)
                    <p class="m-0 rounded-xl bg-white/[.06] px-3.5 py-3 text-[15px] leading-normal text-krem">
                        {{ $minggu->kerjaBakti() ? 'Daftar kerja bakti' : 'Pembagian tugas kecil' }} sudah bisa dilihat semua warga.
                    </p>
                    <a href="{{ route('pengurus.jadwal', ['minggu' => $minggu->tanggal_senin->toDateString(), 'tab' => 'minggu']) }}" wire:navigate class="tombol-kaca no-underline">Lihat pembagian</a>
                @else
                    <div role="radiogroup" aria-label="Jenis piket Minggu" class="grid grid-cols-2 gap-1 rounded-xl bg-white/[.06] p-1">
                        <button type="button" role="radio" class="segmen-gelap-tombol" aria-checked="{{ $minggu->kerjaBakti() ? 'false' : 'true' }}" wire:click="ubahModeMinggu('tugas_kecil')">Tugas kecil</button>
                        <button type="button" role="radio" class="segmen-gelap-tombol" aria-checked="{{ $minggu->kerjaBakti() ? 'true' : 'false' }}" wire:click="ubahModeMinggu('kerja_bakti')">Kerja bakti</button>
                    </div>

                    @if ($minggu->kerjaBakti())
                        <p class="letup m-0 rounded-xl bg-white/[.06] px-3.5 py-3 text-[15px] leading-normal text-krem">
                            Semua {{ $jumlahWarga }} warga ikut kerja bakti. Tidak ada pembagian tugas.
                        </p>
                    @else
                        <div class="flex flex-col gap-0.5">
                            @foreach ($tugasMinggu as $t)
                                @php $on = in_array($t->id, $aktifMinggu, true); @endphp
                                <div wire:key="tm-{{ $t->id }}" x-data="{ on: @js($on) }" class="flex min-h-[44px] items-center justify-between gap-3">
                                    <span class="text-[15px] font-semibold transition-colors" :class="on ? 'text-white' : 'text-rock-redup line-through'">{{ $t->nama }}</span>
                                    <button type="button" role="switch" class="sakelar" :aria-checked="on.toString()" aria-label="Aktifkan {{ $t->nama }}"
                                            @click="on = !on; $wire.ubahTugasMinggu({{ $t->id }}, on)"><span></span></button>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <button type="button" class="tombol-merino" wire:click="bagikanMinggu" wire:loading.attr="disabled" wire:target="bagikanMinggu">
                        <span wire:loading.remove wire:target="bagikanMinggu">
                            {{ $minggu->kerjaBakti() ? 'Buat daftar kerja bakti →' : 'Bagikan '.count($aktifMinggu).' tugas →' }}
                        </span>
                        <span wire:loading wire:target="bagikanMinggu">Membagikan…</span>
                    </button>
                    <span class="-mt-1 text-center text-[13px] text-rock-muda">Kamu bisa cek dan tukar petugas sebelum menerbitkan.</span>
                @endif
            </section>
        </aside>
    </div>
</div>
