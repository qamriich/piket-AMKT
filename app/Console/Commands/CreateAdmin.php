<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Livewire\Pengurus\WargaForm;
use App\Models\Geng;
use App\Models\LogAktivitas;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * Membuat akun pengurus pertama (Tata Tertib), PRD bagian 2 poin 6.
 */
class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin
        {--nama= : Nama lengkap}
        {--email= : Email}
        {--kamar= : Nomor kamar}
        {--geng= : Urutan geng (1-3)}
        {--password= : Password awal (kalau kosong, dibuat acak)}';

    protected $description = 'Membuat akun pengurus Tata Tertib dengan password awal yang wajib diganti saat login pertama';

    public function handle(): int
    {
        // Kalau nama dan email sudah diberikan lewat opsi, jangan tanya hal yang opsional.
        $modeTanya = ! $this->option('nama') || ! $this->option('email');

        $nama = $this->option('nama') ?: text('Nama', required: true);
        $email = strtolower(trim($this->option('email') ?: text('Email', required: true)));
        $kamar = $this->option('kamar') ?? ($modeTanya ? text('Nomor kamar (boleh kosong)') : null);

        $gengs = Geng::query()->orderBy('urutan')->get();
        if ($gengs->isEmpty()) {
            $this->error('Tabel geng masih kosong. Jalankan dulu: php artisan db:seed --class=GengSeeder');

            return self::FAILURE;
        }

        $urutanGeng = $this->option('geng') ?? select('Geng', $gengs->pluck('nama', 'urutan')->all());

        $validator = Validator::make(
            ['nama' => $nama, 'email' => $email, 'geng' => $urutanGeng],
            [
                'nama' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'unique:users,email'],
                'geng' => ['required', 'integer', 'exists:gengs,urutan'],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $pesan) {
                $this->error($pesan);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'nama' => $nama,
            'email' => $email,
            'no_kamar' => $kamar ?: null,
            'geng_id' => $gengs->firstWhere('urutan', (int) $urutanGeng)->id,
            'role' => Role::TataTertib,
            'wajib_diperiksa' => false,
            'aktif' => true,
        ]);

        $password = (string) ($this->option('password') ?: WargaForm::buatPasswordAcak());
        if (mb_strlen($password) < 8) {
            $user->delete();
            $this->error('Password awal minimal 8 karakter.');

            return self::FAILURE;
        }

        $user->aturPasswordAwal($password);
        LogAktivitas::catat('admin.dibuat_lewat_artisan', $user);

        $this->info("Akun Tata Tertib untuk {$user->nama} dibuat.");
        $this->line("Password awal: {$password}");
        $this->warn('Login di /login dengan email ini. Password awal wajib diganti saat login pertama.');

        return self::SUCCESS;
    }
}
