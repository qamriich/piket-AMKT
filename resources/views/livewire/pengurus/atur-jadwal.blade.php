@php
    use App\Enums\StatusPenugasan;
    $statusBagian = fn (string $b) => $minggu->sudahTerbit($b);
    $labelTab = ['harian' => 'Harian', 'kamis' => 'Kamis', 'minggu' => 'Minggu'];
@endphp
<div class="flex flex-col gap-5">
    <div class="naik flex flex-wrap items-end justify-between gap-4">
        <div class="flex flex-col gap-1">
            <h1 class="m-0 text-[30px] font-extrabold leading-tight sm:text-[36px]">Atur jadwal</h1>
            <span class="text-[15px] text-rock-muda">{{ $minggu->gengHarian?->nama }} piket harian · pool Kamis {{ $gengKamis->pluck('nama')->join(' & ') }}</span>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" wire:click="pindahMinggu(-1)" class="tombol-kaca !min-h-[48px] !px-3" aria-label="Minggu sebelumnya"><x-ikon nama="kiri" /></button>
            <div class="kaca flex min-h-[48px] flex-col justify-center rounded-xl px-4 text-center">
                <span class="text-[15px] font-extrabold">{{ $minggu->senin()->translatedFormat('j M') }} – {{ $minggu->hariMinggu()->translatedFormat('j M Y') }}</span>
                @if ($minggu->senin()->equalTo($seninIni))
                    <span class="text-xs font-bold text-green-300">Minggu ini</span>
                @else
                    <button type="button" wire:click="mingguIni" class="text-xs font-bold text-rock-muda underline">Ke minggu ini</button>
                @endif
            </div>
            <button type="button" wire:click="pindahMinggu(1)" class="tombol-kaca !min-h-[48px] !px-3" aria-label="Minggu berikutnya"><x-ikon nama="kanan" /></button>
        </div>
    </div>

    <div role="tablist" aria-label="Bagian jadwal" class="segmen-gelap naik t1 self-start">
        @foreach ($labelTab as $k => $label)
            <button type="button" role="tab" class="segmen-gelap-tombol inline-flex items-center gap-2 !min-h-[44px] !px-4 !text-[15px]"
                    aria-selected="{{ $tab === $k ? 'true' : 'false' }}" wire:click="pilihTab('{{ $k }}')">
                {{ $label }}
                <span @class(['h-2 w-2 rounded-full', 'bg-green-400' => $statusBagian($k), 'bg-amber-300' => ! $statusBagian($k)])
                      title="{{ $statusBagian($k) ? 'Terbit' : 'Draf' }}"></span>
            </button>
        @endforeach
    </div>

    <div wire:loading.flex wire:target="pindahMinggu, mingguIni, pilihTab" class="flex-col gap-3">
        <div class="kerangka h-24"></div>
        <div class="kerangka h-64"></div>
    </div>

    <div wire:loading.remove wire:target="pindahMinggu, mingguIni, pilihTab" wire:key="panel-{{ $tab }}-{{ $minggu->id }}" class="halaman flex flex-col gap-5">
        {{-- Status bagian --}}
        <div @class(['flex flex-wrap items-center justify-between gap-3 rounded-2xl border px-4 py-3.5',
                'border-green-300/30 bg-green-500/10' => $statusBagian($tab), 'border-amber-200/30 bg-amber-300/10' => ! $statusBagian($tab)])>
            <span class="flex items-center gap-2.5 text-[15px] font-semibold">
                <x-ikon :nama="$statusBagian($tab) ? 'centang' : 'pensil'" :ukuran="18" />
                @if ($statusBagian($tab))
                    Sudah diterbitkan. Warga sudah bisa melihat jadwal ini.
                @else
                    Masih draf. Warga belum bisa melihatnya sampai kamu menerbitkan.
                @endif
            </span>
            @php
                $adaIsi = $tab === 'harian' ? $harianAda : $sesi[$tab]['baris']->isNotEmpty();
            @endphp
            @unless ($statusBagian($tab))
                <button type="button" class="tombol-merino !min-h-[48px]" @disabled(! $adaIsi)
                        @click="$store.konfirmasi.tanya({
                            judul: @js('Terbitkan jadwal '.($tab === 'harian' ? 'harian' : $labelTab[$tab]).'?'),
                            pesan: 'Setelah terbit, semua warga bisa melihat jadwal ini dan daftar tugasnya tidak bisa diubah lagi. Petugas masih bisa ditukar.',
                            tombol: 'Terbitkan',
                            aksi: () => $wire.terbitkan(@js($tab)),
                        })">
                    <x-ikon nama="kirim" :ukuran="18" /> Terbitkan
                </button>
            @endunless
        </div>

        @if ($catatan[$tab] !== [])
            <div class="letup flex flex-col gap-1.5 rounded-2xl border border-amber-200/40 bg-amber-300/10 px-4 py-3.5 text-[15px] leading-normal text-amber-100">
                <span class="flex items-center gap-2 font-extrabold text-amber-200"><x-ikon nama="info" :ukuran="18" /> Perlu diperhatikan</span>
                @foreach ($catatan[$tab] as $c)
                    <span>• {{ $c }}</span>
                @endforeach
            </div>
        @endif

        @if ($tab === 'harian')
            {{-- ================= HARIAN ================= --}}
            <section class="kartu-kaca flex flex-col gap-4 rounded-3xl p-[18px] sm:p-[22px]">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex max-w-xl flex-col gap-1">
                        <h2 class="m-0 text-xl font-extrabold">Piket harian · {{ $minggu->gengHarian?->nama }}</h2>
                        <span class="text-sm leading-normal text-rock-muda">
                            {{ $anggotaHarian->count() }} anggota aktif. Draf otomatis memakai jadwal kuliah: 1 orang maksimal 1 hari, hari yang tidak terisi tetap kosong untuk ganti bintang.
                        </span>
                    </div>
                    @unless ($statusBagian('harian'))
                        <button type="button" class="tombol-rock"
                                @if ($harianAda)
                                    @click="$store.konfirmasi.tanya({ judul: 'Susun ulang draf harian?', pesan: 'Pembagian hari yang sekarang akan diganti dengan saran baru dari jadwal kuliah.', tombol: 'Susun ulang', aksi: () => $wire.buatDrafHarian() })"
                                @else
                                    wire:click="buatDrafHarian"
                                @endif
                                wire:loading.attr="disabled" wire:target="buatDrafHarian">
                            <x-ikon nama="acak" :ukuran="18" />
                            <span wire:loading.remove wire:target="buatDrafHarian">{{ $harianAda ? 'Susun ulang draf' : 'Buat draf otomatis' }}</span>
                            <span wire:loading wire:target="buatDrafHarian">Menyusun…</span>
                        </button>
                    @endunless
                </div>

                <div class="grid gap-2.5 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ($hari as $i => $h)
                        @php
                            $p = $h['penugasan'];
                            $kunci = $terkunci($p);
                            $ganda = $p && ($hitungHari[$p->user_id] ?? 0) > 1;
                        @endphp
                        <div wire:key="hari-{{ $versi }}-{{ $h['tanggal']->toDateString() }}" style="animation-delay: {{ $i * 0.05 }}s"
                             @class(['letup flex flex-col gap-2.5 rounded-2xl border p-3.5',
                                 'border-rock/40 bg-rock/10' => $p && ! $kunci, 'border-orange-300/30 bg-orange-400/10' => $kunci, 'border-dashed border-white/20' => ! $p])>
                            <div class="flex items-center justify-between gap-2">
                                <span class="flex flex-col">
                                    <span class="text-[15px] font-extrabold">{{ $h['tanggal']->translatedFormat('l') }}</span>
                                    <span class="text-[13px] text-rock-muda">{{ $h['tanggal']->translatedFormat('j M') }}</span>
                                </span>
                                @if ($p)
                                    <x-badge :status="$p->status" />
                                @else
                                    <x-badge jenis="kosong" label="Kosong" />
                                @endif
                            </div>
                            @if ($kunci)
                                <span class="text-base font-extrabold">{{ $p->user->nama }}</span>
                                <span class="text-xs text-rock-muda">Terkunci, tidak bisa diubah.</span>
                            @else
                                <select aria-label="Petugas {{ $h['tanggal']->translatedFormat('l, j M') }}" class="isian-gelap !min-h-[46px] text-[15px]"
                                        wire:change="aturHarian('{{ $h['tanggal']->toDateString() }}', $event.target.value)">
                                    <option value="" @selected(! $p)>— Kosong —</option>
                                    @foreach ($anggotaHarian as $a)
                                        <option value="{{ $a->id }}" @selected($p?->user_id === $a->id)>{{ $a->nama }}</option>
                                    @endforeach
                                </select>
                                @if ($ganda)
                                    <span class="text-xs font-semibold text-amber-200">Orang ini dapat lebih dari 1 hari.</span>
                                @endif
                                <span class="text-xs leading-snug text-rock-muda">
                                    Bisa: {{ $h['bisa']->isNotEmpty() ? $h['bisa']->join(', ') : 'tidak ada (semua kuliah pagi)' }}
                                </span>
                            @endif
                        </div>
                    @endforeach
                </div>
                @if ($statusBagian('harian'))
                    <p class="m-0 text-[13px] text-rock-muda">Perubahan petugas setelah terbit tetap tercatat di log aktivitas. Email pemberitahuan ke warga yang terdampak aktif di tahap berikutnya.</p>
                @endif
            </section>
        @else
            {{-- ================= KAMIS / MINGGU ================= --}}
            @php
                $s = $sesi[$tab];
                $terbit = $s['terbit'];
                $kerjaBakti = $tab === 'minggu' && $minggu->kerjaBakti();
            @endphp

            <div class="flex flex-wrap items-start gap-5">
                <section class="kartu-kaca flex min-w-0 flex-[1_1_340px] flex-col gap-4 rounded-3xl p-[18px] sm:p-[22px]">
                    <div class="flex flex-col gap-1">
                        <h2 class="m-0 text-xl font-extrabold">Daftar tugas {{ $labelTab[$tab] }}</h2>
                        <span class="text-sm text-rock-muda">
                            @if ($tab === 'kamis')
                                {{ $minggu->kamis()->translatedFormat('l, j M') }} · batas Jumat 18.00
                            @else
                                {{ $minggu->hariMinggu()->translatedFormat('l, j M') }} · batas Senin 18.00
                            @endif
                        </span>
                    </div>

                    @if ($tab === 'minggu')
                        <div role="radiogroup" aria-label="Jenis piket Minggu" class="grid grid-cols-2 gap-1 rounded-xl bg-white/[.06] p-1">
                            <button type="button" role="radio" class="segmen-gelap-tombol" aria-checked="{{ $kerjaBakti ? 'false' : 'true' }}" @disabled($terbit)
                                    @if ($s['baris']->isNotEmpty() && $kerjaBakti)
                                        @click="$store.konfirmasi.tanya({ judul: 'Ganti ke tugas kecil?', pesan: 'Daftar kerja bakti yang sudah dibuat akan dihapus.', tombol: 'Ganti', aksi: () => $wire.ubahMode('tugas_kecil') })"
                                    @else wire:click="ubahMode('tugas_kecil')" @endif>Tugas kecil</button>
                            <button type="button" role="radio" class="segmen-gelap-tombol" aria-checked="{{ $kerjaBakti ? 'true' : 'false' }}" @disabled($terbit)
                                    @if ($s['baris']->isNotEmpty() && ! $kerjaBakti)
                                        @click="$store.konfirmasi.tanya({ judul: 'Ganti ke kerja bakti?', pesan: 'Pembagian tugas kecil yang sudah dibuat akan dihapus.', tombol: 'Ganti', aksi: () => $wire.ubahMode('kerja_bakti') })"
                                    @else wire:click="ubahMode('kerja_bakti')" @endif>Kerja bakti</button>
                        </div>
                    @endif

                    @if ($kerjaBakti)
                        <p class="letup m-0 rounded-xl bg-white/[.06] px-3.5 py-3 text-[15px] leading-normal text-krem">
                            Semua {{ $s['pool']->count() }} warga aktif ikut kerja bakti. Tidak ada pembagian tugas.
                            Bintang Minggu yang belum lunas ditunda ke Minggu berikutnya yang memakai tugas kecil.
                        </p>
                    @else
                        <div class="flex items-center justify-between gap-2 text-sm">
                            <span class="text-rock-muda"><strong class="text-white">{{ count($s['aktif_ids']) }}</strong> dari {{ $semuaTugas->count() }} tugas aktif</span>
                            @unless ($terbit)
                                <span class="flex gap-3">
                                    <button type="button" class="font-bold text-merino underline" wire:click="semuaTugas('{{ $tab }}', true)">Aktifkan semua</button>
                                    <button type="button" class="font-bold text-rock-muda underline" wire:click="semuaTugas('{{ $tab }}', false)">Matikan semua</button>
                                </span>
                            @endunless
                        </div>
                        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-1 2xl:grid-cols-2">
                            @foreach ($semuaTugas as $t)
                                @php $on = in_array($t->id, $s['aktif_ids'], true); @endphp
                                <div wire:key="tg-{{ $tab }}-{{ $t->id }}-{{ $on ? 1 : 0 }}" x-data="{ on: @js($on) }"
                                     class="flex min-h-[56px] items-center justify-between gap-3 rounded-2xl border px-3.5 transition-colors"
                                     :class="on ? 'border-rock/40 bg-rock/10' : 'border-white/10 bg-transparent'">
                                    <span class="text-[15px] font-semibold transition-colors" :class="on ? 'text-white' : 'text-rock-redup line-through'">{{ $t->nama }}</span>
                                    <button type="button" role="switch" class="sakelar" :aria-checked="on.toString()" aria-label="Aktifkan {{ $t->nama }}" @disabled($terbit)
                                            @click="on = !on; $wire.ubahTugas(@js($tab), {{ $t->id }}, on)"><span></span></button>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <span class="text-sm text-rock-muda">
                        Petugas: {{ $tab === 'kamis' ? $gengKamis->pluck('nama')->join(' & ') : 'semua warga aktif' }} · {{ $s['pool']->count() }} orang
                    </span>

                    @unless ($terbit)
                        <button type="button" class="tombol-merino"
                                @if ($s['baris']->isNotEmpty())
                                    @click="$store.konfirmasi.tanya({ judul: 'Buat ulang pembagian?', pesan: 'Pembagian dan tukar petugas yang sudah ada akan diganti dengan pembagian baru.', tombol: 'Buat ulang', aksi: () => $wire.bagikan(@js($tab)) })"
                                @else
                                    wire:click="bagikan('{{ $tab }}')"
                                @endif
                                wire:loading.attr="disabled" wire:target="bagikan">
                            <x-ikon nama="acak" :ukuran="18" />
                            <span wire:loading.remove wire:target="bagikan">
                                {{ $kerjaBakti ? 'Buat daftar kerja bakti' : ($s['baris']->isNotEmpty() ? 'Buat ulang pembagian' : 'Buat pembagian') }}
                            </span>
                            <span wire:loading wire:target="bagikan">Membagikan…</span>
                        </button>
                    @endunless
                </section>

                <section class="kartu-kaca flex min-w-0 flex-[999_1_460px] flex-col gap-4 rounded-3xl p-[18px] sm:p-[22px]">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="m-0 text-xl font-extrabold">{{ $kerjaBakti ? 'Peserta kerja bakti' : 'Pembagian' }}</h2>
                        @if ($tukarDari)
                            <button type="button" wire:click="batalTukar" class="text-sm font-bold text-rock-muda underline">Batal tukar</button>
                        @endif
                    </div>

                    @if ($tukarDari)
                        <div class="letup rounded-xl border border-rock/40 bg-rock/15 px-3.5 py-2.5 text-sm font-semibold text-merino">
                            Pilih tugas lain untuk ditukar dengan {{ $s['baris']->firstWhere('id', $tukarDari)?->user?->namaDepan() }}.
                        </div>
                    @endif

                    @if ($s['baris']->isEmpty() && ($kerjaBakti || $s['aktif']->isNotEmpty()))
                        <div class="flex flex-col items-center gap-2 rounded-2xl border border-dashed border-white/20 px-6 py-10 text-center">
                            <x-ikon nama="acak" :ukuran="28" class="text-rock" />
                            <span class="text-base font-extrabold">Belum ada pembagian</span>
                            <span class="max-w-sm text-sm text-rock-muda">Atur tugas yang aktif, lalu tekan "{{ $kerjaBakti ? 'Buat daftar kerja bakti' : 'Buat pembagian' }}". Sistem membagi petugas secara bergilir dan mendahulukan bintang yang belum lunas.</span>
                        </div>
                    @elseif ($kerjaBakti)
                        <div class="flex flex-wrap gap-2">
                            @foreach ($s['baris']->sortBy(fn ($p) => $p->user->nama) as $i => $p)
                                <span wire:key="kb-{{ $versi }}-{{ $p->id }}" class="letup inline-flex items-center gap-2 rounded-full bg-white/[.08] py-1.5 pl-1.5 pr-3 text-sm font-semibold"
                                      style="animation-delay: {{ min($i, 20) * 0.03 }}s">
                                    <span class="flex h-7 w-7 items-center justify-center rounded-full bg-langit text-xs font-extrabold text-venice">{{ $p->user->inisial() }}</span>
                                    {{ $p->user->nama }}
                                </span>
                            @endforeach
                        </div>
                    @elseif ($s['aktif']->isEmpty())
                        <p class="m-0 rounded-2xl border border-dashed border-white/20 px-6 py-8 text-center text-[15px] text-rock-muda">Tidak ada tugas yang aktif minggu ini.</p>
                    @else
                        <div class="flex flex-col overflow-hidden rounded-2xl border border-white/[.08]">
                            @foreach ($s['aktif'] as $i => $t)
                                @php
                                    $p = $s['per_tugas']->get($t->id);
                                    $kunci = $terkunci($p);
                                    $dipilih = $p && $tukarDari === $p->id;
                                @endphp
                                <div wire:key="bg-{{ $versi }}-{{ $tab }}-{{ $t->id }}" style="animation-delay: {{ $i * 0.05 }}s"
                                     @class(['letup baris flex flex-wrap items-center gap-3 px-4 py-3', 'border-t border-white/[.07]' => $i > 0,
                                         'bg-rock/15' => $dipilih, 'bg-orange-400/[.07]' => $kunci && ! $dipilih])>
                                    <div class="flex min-w-0 flex-[1_1_180px] flex-col gap-1">
                                        <span class="text-[13px] font-semibold text-rock-muda">{{ $t->nama }}</span>
                                        @if (! $p)
                                            <span class="text-[15px] font-extrabold text-amber-200">Belum ada petugas</span>
                                        @elseif ($kunci)
                                            <span class="text-base font-extrabold">{{ $p->user->nama }}</span>
                                            @if ($p->membayar)
                                                <span class="text-xs text-orange-200">Bayar bintang {{ $p->membayar->tanggalLokal()->translatedFormat('j M') }}</span>
                                            @endif
                                        @else
                                            <span class="text-base font-extrabold">{{ $p->user->nama }}</span>
                                        @endif
                                    </div>

                                    @if ($p)
                                        <x-badge :status="$p->status" />
                                    @endif

                                    @if (! $kunci)
                                        <select aria-label="Petugas {{ $t->nama }}" class="isian-gelap !min-h-[44px] !w-auto max-w-[200px] text-sm"
                                                wire:change="aturPetugas('{{ $tab }}', {{ $t->id }}, $event.target.value)">
                                            <option value="" @selected(! $p)>{{ $p ? '— Kosongkan —' : '— Pilih petugas —' }}</option>
                                            @foreach ($s['pool'] as $u)
                                                <option value="{{ $u->id }}" @selected($p?->user_id === $u->id)>{{ $u->nama }}</option>
                                            @endforeach
                                        </select>
                                        @if ($p)
                                            <button type="button" wire:click="pilihTukar({{ $p->id }})"
                                                    @class(['tekan inline-flex min-h-[44px] items-center gap-1.5 rounded-xl px-3 text-sm font-extrabold',
                                                        'bg-rock text-navy' => $dipilih || $tukarDari,
                                                        'border border-rock/60 bg-rock/15 text-merino' => ! $dipilih && ! $tukarDari])>
                                                <x-ikon nama="tukar" :ukuran="16" />
                                                {{ $dipilih ? 'Batal' : ($tukarDari ? 'Tukar ke sini' : 'Tukar') }}
                                            </button>
                                        @endif
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        @if ($s['bebas']->isNotEmpty())
                            <div class="flex flex-col gap-1 rounded-2xl bg-white/[.04] px-4 py-3">
                                <span class="text-[13px] font-semibold text-rock-muda">Bebas minggu ini ({{ $s['bebas']->count() }})</span>
                                <span class="text-[15px] font-semibold text-krem">{{ $s['bebas']->map->namaDepan()->join(', ') }}</span>
                                <span class="text-xs text-rock-redup">Mereka didahulukan di pembagian minggu berikutnya.</span>
                            </div>
                        @endif
                    @endif
                </section>
            </div>
        @endif
    </div>
</div>
