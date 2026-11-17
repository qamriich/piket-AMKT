<?php

namespace App\Console\Commands;

use App\Models\LogAktivitas;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Menghapus PIN pengurus yang lupa PIN. Pengurus itu akan diminta membuat PIN baru.
 */
class ResetPin extends Command
{
    protected $signature = 'app:reset-pin {email : Email pengurus}';

    protected $description = 'Menghapus PIN pengurus supaya bisa membuat PIN baru';

    public function handle(): int
    {
        $user = User::query()->where('email', strtolower($this->argument('email')))->first();

        if (! $user) {
            $this->error('Akun dengan email itu tidak ditemukan.');

            return self::FAILURE;
        }

        $user->forceFill(['pin_hash' => null])->save();
        LogAktivitas::catat('pin.direset_lewat_artisan', $user);

        $this->info("PIN {$user->nama} dihapus. Dia akan diminta membuat PIN baru saat membuka menu pengurus.");

        return self::SUCCESS;
    }
}
