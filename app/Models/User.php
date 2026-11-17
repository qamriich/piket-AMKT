<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const PANJANG_TOKEN = 64;

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
        'token_hash',
        'pin_hash',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'wajib_diperiksa' => 'boolean',
            'aktif' => 'boolean',
            'token_dibuat_pada' => 'datetime',
        ];
    }

    // Tidak ada password: masuk hanya lewat link pribadi.
    public function getAuthPassword(): string
    {
        return '';
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

    public function namaDepan(): string
    {
        return (string) strtok((string) $this->nama, ' ');
    }

    public function inisial(): string
    {
        return mb_strtoupper(mb_substr(trim((string) $this->nama), 0, 1));
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function cariLewatToken(string $token): ?self
    {
        if (strlen($token) !== self::PANJANG_TOKEN) {
            return null;
        }

        return static::query()
            ->where('token_hash', self::hashToken($token))
            ->where('aktif', true)
            ->first();
    }

    /**
     * Membuat token link pribadi baru. Token lama otomatis tidak berlaku lagi,
     * dan semua cookie "ingat saya" yang lama ikut diputus.
     * Mengembalikan token asli (hanya ada di memori, tidak disimpan).
     */
    public function buatTokenBaru(): string
    {
        $token = Str::random(self::PANJANG_TOKEN);

        $this->forceFill([
            'token_hash' => self::hashToken($token),
            'token_dibuat_pada' => now(),
            'remember_token' => Str::random(60),
        ])->save();

        return $token;
    }

    public static function linkPribadi(string $token): string
    {
        return route('link.masuk', ['token' => $token]);
    }

    public function punyaPin(): bool
    {
        return $this->pin_hash !== null;
    }

    public function setPin(string $pin): void
    {
        $this->forceFill(['pin_hash' => Hash::make($pin)])->save();
    }

    public function cekPin(string $pin): bool
    {
        return $this->pin_hash !== null && Hash::check($pin, $this->pin_hash);
    }
}
