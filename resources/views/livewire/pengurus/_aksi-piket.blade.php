{{-- Aksi pengurus untuk satu piket (F6, R5): tandai selesai, batalkan, catatan, koreksi. Butuh $p, $jenisAksi, $statusKoreksi. --}}
@php
    use App\Enums\StatusPenugasan;
    $nama = $p->user->namaDepan();
    $pilihanKoreksi = collect($statusKoreksi)->reject(fn ($s) => $s === $p->status || ($s === StatusPenugasan::Bintang && $p->status === StatusPenugasan::Lunas));
@endphp
<div x-data="{ mode: null, catatan: @js($p->catatan ?? ''), ke: '', alasan: '' }" class="flex w-full flex-col gap-2 sm:w-auto sm:items-end">
    <div class="flex flex-wrap items-center gap-2 sm:justify-end">
        <button type="button" class="min-h-[44px] rounded-xl border border-white/15 px-3 text-sm font-bold text-krem hover:bg-white/[.06]"
                @click="mode = mode === 'catatan' ? null : 'catatan'" :aria-expanded="mode === 'catatan'">
            <x-ikon nama="pensil" :ukuran="16" class="inline" /> Catatan
        </button>

        @if ($jenisAksi === 'tandai')
            <button type="button" wire:click="tandaiSelesai({{ $p->id }})" wire:loading.attr="disabled" wire:target="tandaiSelesai({{ $p->id }})"
                    @class(['tekan min-h-[44px] min-w-[140px] rounded-xl px-3 text-sm font-extrabold',
                        'bg-merino text-navy' => $p->user->wajib_diperiksa,
                        'border border-rock/60 bg-rock/15 text-merino' => ! $p->user->wajib_diperiksa])>
                <span wire:loading.remove wire:target="tandaiSelesai({{ $p->id }})">{{ $p->user->wajib_diperiksa ? 'Periksa & selesai' : 'Tandai selesai' }}</span>
                <span wire:loading wire:target="tandaiSelesai({{ $p->id }})">Menyimpan…</span>
            </button>
        @elseif ($jenisAksi === 'batal')
            <button type="button" class="min-h-[44px] rounded-xl border border-white/15 px-3 text-sm font-bold text-rock-muda hover:bg-white/[.06]"
                    @click="$store.konfirmasi.tanya({ judul: @js('Batalkan tanda selesai '.$nama.'?'), pesan: 'Statusnya kembali menjadi belum dikerjakan. Kalau tidak ditandai lagi sebelum batas waktu, piketnya jadi bintang.', tombol: 'Batalkan selesai', bahaya: true, aksi: () => $wire.batalkanSelesai({{ $p->id }}) })">
                Batalkan
            </button>
        @elseif ($jenisAksi === 'koreksi' && $pilihanKoreksi->isNotEmpty())
            <button type="button" class="min-h-[44px] rounded-xl border border-white/15 px-3 text-sm font-bold text-merino hover:bg-white/[.06]"
                    @click="mode = mode === 'koreksi' ? null : 'koreksi'" :aria-expanded="mode === 'koreksi'">
                Koreksi
            </button>
        @endif
    </div>

    <form x-show="mode === 'catatan'" x-cloak x-transition.opacity class="flex w-full flex-col gap-2 sm:w-[320px]"
          @submit.prevent="$wire.simpanCatatan({{ $p->id }}, catatan).then(() => mode = null)">
        <label class="text-[13px] font-semibold text-rock-muda" for="catatan-{{ $p->id }}">Catatan untuk {{ $nama }} (terlihat olehnya)</label>
        <textarea id="catatan-{{ $p->id }}" x-model="catatan" rows="2" maxlength="500" class="isian-gelap !min-h-[64px] py-2 text-sm"
                  placeholder="Misalnya: lantai dapur masih basah"></textarea>
        <button type="submit" class="tombol-rock !min-h-[44px] text-sm">Simpan catatan</button>
    </form>

    @if ($jenisAksi === 'koreksi')
        <form x-show="mode === 'koreksi'" x-cloak x-transition.opacity class="flex w-full flex-col gap-2 sm:w-[320px]"
              @submit.prevent="$wire.koreksi({{ $p->id }}, ke, alasan).then(() => { mode = null; alasan = ''; ke = '' })">
            <span class="text-[13px] font-semibold text-rock-muda">Ubah status setelah batas waktu menjadi:</span>
            <div role="radiogroup" class="grid grid-cols-3 gap-1 rounded-xl bg-white/[.06] p-1">
                @foreach ($pilihanKoreksi as $s)
                    <button type="button" role="radio" class="segmen-gelap-tombol" :aria-checked="(ke === @js($s->value)).toString()" @click="ke = @js($s->value)">{{ $s->label() }}</button>
                @endforeach
            </div>
            <textarea x-model="alasan" rows="2" maxlength="500" required class="isian-gelap !min-h-[64px] py-2 text-sm"
                      aria-label="Catatan koreksi (wajib)" placeholder="Catatan wajib, misalnya: sudah dikerjakan, lupa ditandai"></textarea>
            <button type="submit" class="tombol-merino !min-h-[44px] text-sm" :disabled="!ke || alasan.trim().length < 3">Simpan koreksi</button>
        </form>
    @endif
</div>
