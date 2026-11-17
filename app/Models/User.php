<?php

namespace App\Models;

use App\Enums\JenisPenugasan;
use App\Enums\Role;
use App\Notifications\TautanPassword;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

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
            'harus_ganti_password' => 'boolean',
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

    public function izin(): HasMany
    {
        return $this->hasMany(Izin::class);
    }

    /** Warga yang izinnya (disetujui) menutupi seluruh waktu piket itu tidak ikut (R3, R3a). */
    public function scopeTidakIzinUntuk(Builder $query, JenisPenugasan $jenis, CarbonImmutable $tanggal): Builder
    {
        return $query->whereDoesntHave('izin', fn (Builder $q) => $q->disetujui()->menutupiPiket($jenis, $tanggal));
    }

    /** Izin yang disetujui menutupi seluruh waktu piket jenis ini di tanggal itu (R3a). */
    public function izinUntuk(JenisPenugasan $jenis, CarbonImmutable $tanggal): bool
    {
        return $this->izin()->disetujui()->menutupiPiket($jenis, $tanggal)->exists();
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

    /**
     * Halaman tujuan setelah login: pengurus ke Pengawasan, warga ke Beranda (PRD bagian 2 poin 4).
     * Selama password awal belum diganti, semua diarahkan ke "Buat password baru" (R2).
     */
    public function halamanAwal(): string
    {
        if ($this->harus_ganti_password) {
            return route('password.baru');
        }

        return $this->isPengurus() ? route('pengurus.pengawasan') : route('dashboard');
    }

    /** Pengurus mengisi password awal; warga wajib menggantinya saat login berikutnya (R2). */
    public function aturPasswordAwal(string $password): void
    {
        $this->forceFill([
            'password' => $password,
            'harus_ganti_password' => true,
            'remember_token' => Str::random(60),
        ])->save();
    }

    /** Email "Lupa password" (dikirim oleh password broker Laravel), berbahasa Indonesia. */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new TautanPassword($token));
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
