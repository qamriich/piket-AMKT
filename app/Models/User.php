<?php

namespace App\Models;

use App\Enums\Role;
use App\Notifications\TautanPassword;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'nama',
        'email',
        'no_kamar',
        'geng_id',
        'role',
        'wajib_diperiksa',
        'aktif',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'wajib_diperiksa' => 'boolean',
            'aktif' => 'boolean',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function geng(): BelongsTo
    {
        return $this->belongsTo(Geng::class);
    }

    public function jadwalKuliah(): HasMany
    {
        return $this->hasMany(JadwalKuliah::class);
    }

    public function penugasan(): HasMany
    {
        return $this->hasMany(Penugasan::class);
    }

    public function pengajuanGantiBintang(): HasMany
    {
        return $this->hasMany(PengajuanGantiBintang::class);
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('aktif', true);
    }

    public function scopePengurus(Builder $query): Builder
    {
        return $query->where('role', '!=', Role::Warga->value);
    }

    public function isPengurus(): bool
    {
        return $this->role->isPengurus();
    }

    /** Sudah membuat password (bisa login). */
    public function punyaPassword(): bool
    {
        return $this->password !== null;
    }

    /** Halaman tujuan setelah login: pengurus ke Pengawasan, warga ke Beranda (PRD bagian 2 poin 4). */
    public function halamanAwal(): string
    {
        return $this->isPengurus() ? route('pengurus.pengawasan') : route('dashboard');
    }

    /** Email "Lupa password" (dikirim oleh password broker Laravel), berbahasa Indonesia. */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new TautanPassword($token, TautanPassword::RESET));
    }

    public function namaDepan(): string
    {
        return (string) strtok((string) $this->nama, ' ');
    }

    public function inisial(): string
    {
        return mb_strtoupper(mb_substr(trim((string) $this->nama), 0, 1));
    }
}
