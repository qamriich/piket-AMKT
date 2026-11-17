<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class LogAktivitas extends Model
{
    protected $table = 'log_aktivitas';

    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'aksi', 'subjek_type', 'subjek_id', 'data', 'ip_address'];

    protected function casts(): array
    {
        return ['data' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subjek(): MorphTo
    {
        return $this->morphTo();
    }

    /** Catat aksi pengurus. */
    public static function catat(string $aksi, ?Model $subjek = null, array $data = []): self
    {
        return static::create([
            'user_id' => auth()->id(),
            'aksi' => $aksi,
            'subjek_type' => $subjek?->getMorphClass(),
            'subjek_id' => $subjek?->getKey(),
            'data' => $data ?: null,
            'ip_address' => request()?->ip(),
        ]);
    }
}
