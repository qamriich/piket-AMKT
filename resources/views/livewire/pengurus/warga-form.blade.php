<div class="flex flex-col gap-5">
    <div class="naik flex flex-col gap-2">
        <a href="{{ route('pengurus.warga.index') }}" wire:navigate class="inline-flex items-center gap-1 self-start text-sm font-bold text-rock-muda no-underline hover:text-merino">
            <x-ikon nama="kiri" :ukuran="16" /> Warga & geng
        </a>
        <h1 class="m-0 text-[30px] font-extrabold leading-tight sm:text-[36px]">{{ $baru ? 'Tambah warga' : 'Ubah data '.$warga->nama }}</h1>
    </div>

    <div class="flex flex-wrap items-start gap-5">
        <form wire:submit="simpan" class="kartu-kaca naik t1 flex min-w-0 flex-[1_1_420px] flex-col gap-4 rounded-3xl p-5 sm:p-6">
            <div>
                <label for="nama" class="label-gelap">Nama</label>
                <input id="nama" wire:model="nama" required class="isian-gelap" autocomplete="off">
                @error('nama') <p class="galat-gelap">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="email" class="label-gelap">Email</label>
                <input id="email" type="email" wire:model="email" required class="isian-gelap" autocomplete="off">
                @error('email') <p class="galat-gelap">{{ $message }}</p> @enderror
                @unless ($baru)
                    <p class="mt-1.5 text-[13px] text-rock-muda">Email ini dipakai untuk login. Kalau diganti, kirim ulang email buat password dari halaman Warga & geng supaya warga tahu.</p>
                @endunless
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="no_kamar" class="label-gelap">Nomor kamar</label>
                    <input id="no_kamar" wire:model="no_kamar" class="isian-gelap">
                    @error('no_kamar') <p class="galat-gelap">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="geng_id" class="label-gelap">Geng</label>
                    <select id="geng_id" wire:model="geng_id" required class="isian-gelap">
                        <option value="">Pilih geng</option>
                        @foreach ($gengs as $g)
                            <option value="{{ $g->id }}">{{ $g->nama }}</option>
                        @endforeach
                    </select>
                    @error('geng_id') <p class="galat-gelap">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="role" class="label-gelap">Role</label>
                <select id="role" wire:model="role" required class="isian-gelap">
                    @foreach (\App\Enums\Role::options() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('role') <p class="galat-gelap">{{ $message }}</p> @enderror
            </div>

            <label class="flex cursor-pointer items-start gap-3 rounded-2xl bg-white/[.05] p-3.5">
                <input type="checkbox" wire:model="wajib_diperiksa" class="mt-1 h-5 w-5 flex-none accent-[#84B3CE]">
                <span>
                    <span class="font-bold">Wajib diperiksa</span>
                    <span class="block text-sm leading-normal text-rock-muda">Warga ini tidak bisa menandai piketnya sendiri. Piketnya hanya bisa ditandai selesai oleh pengurus setelah diperiksa. Tanda ini tidak terlihat oleh warga.</span>
                </span>
            </label>

            @if ($baru)
                <div class="rounded-2xl bg-white/[.05] p-3.5">
                    <label for="password_awal" class="label-gelap">Password awal</label>
                    <div class="flex gap-2">
                        <input id="password_awal" type="text" wire:model="password_awal" autocomplete="off" class="isian-gelap font-mono tracking-wider" placeholder="minimal 8 karakter">
                        <button type="button" wire:click="passwordAcak" class="tombol-kaca flex-none !min-h-[48px] text-sm">
                            <x-ikon nama="acak" :ukuran="16" /> Acak
                        </button>
                    </div>
                    @error('password_awal') <p class="galat-gelap">{{ $message }}</p> @enderror
                    <p class="mt-1.5 text-[13px] leading-normal text-rock-muda">Sampaikan password ini langsung ke warga. Saat login pertama, ia wajib menggantinya dengan password sendiri.</p>
                </div>
            @endif

            <div class="flex flex-wrap gap-2 pt-1">
                <button type="submit" class="tombol-merino" wire:loading.attr="disabled" wire:target="simpan">
                    <span wire:loading.remove wire:target="simpan">{{ $baru ? 'Simpan & buat akun' : 'Simpan' }}</span>
                    <span wire:loading wire:target="simpan">Menyimpan…</span>
                </button>
                <a href="{{ route('pengurus.warga.index') }}" wire:navigate class="tombol-kaca !min-h-[54px] no-underline">Batal</a>
            </div>
        </form>

        @unless ($baru)
            <section class="kartu-kaca naik t2 flex min-w-0 flex-[1_1_340px] flex-col gap-3 rounded-3xl p-5 sm:p-6">
                <div class="flex flex-col gap-1">
                    <h2 class="m-0 text-lg font-extrabold">Jadwal kuliah</h2>
                    <span class="text-sm text-rock-muda">Dipakai untuk menyusun draf piket harian.</span>
                </div>
                <livewire:jadwal-kuliah-editor :user-id="$warga->id" :gelap="true" :key="'kuliah-'.$warga->id" />
            </section>
        @endunless
    </div>
</div>
