<?php

namespace App\Models;

use App\Enums\StatusIzin;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Izin pulang (PRD bagian 15 R3). Selama izin disetujui, warga tidak masuk pool Kamis/Minggu
 * dan piket terbitnya di rentang itu dilepas (status Izin) dengan pengganti otomatis.
 */
class Izin extends Model
{
    protected $table = 'izin';

    protected $fillable = [
        'user_id', 'tanggal_mulai', 'tanggal_selesai', 'alasan', 'status',
        'dibuat_oleh_id', 'diputuskan_oleh_id', 'diputuskan_pada', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusIzin::class,
            'tanggal_mulai' => 'immutable_date',
            'tanggal_selesai' => 'immutable_date',
            'diputuskan_pada' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dibuatOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh_id');
    }

    public function diputuskanOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diputuskan_oleh_id');
    }

    public function scopeDisetujui(Builder $query): Builder
    {
        return $query->where('izin.status', StatusIzin::Disetujui->value);
    }

    /** Izin yang rentangnya mencakup tanggal tersebut. */
    public function scopeMencakup(Builder $query, CarbonImmutable $tanggal): Builder
    {
        return $query->whereDate('izin.tanggal_mulai', '<=', $tanggal->toDateString())
            ->whereDate('izin.tanggal_selesai', '>=', $tanggal->toDateString());
    }

    /** Izin yang rentangnya bersinggungan dengan rentang tersebut. */
    public function scopeBersinggungan(Builder $query, CarbonImmutable $mulai, CarbonImmutable $selesai): Builder
    {
        return $query->whereDate('izin.tanggal_mulai', '<=', $selesai->toDateString())
            ->whereDate('izin.tanggal_selesai', '>=', $mulai->toDateString());
    }

    public function mulai(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->tanggal_mulai->toDateString(), config('app.timezone'));
    }

    public function selesai(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->tanggal_selesai->toDateString(), config('app.timezone'));
    }

    /** Contoh: "20–23 Nov" atau "Kamis, 19 Nov". */
    public function rentangLabel(): string
    {
        $a = $this->mulai();
        $b = $this->selesai();
        if ($a->isSameDay($b)) {
            return $a->translatedFormat('l, j M');
        }
        if ($a->isSameMonth($b)) {
            return $a->format('j').'–'.$b->translatedFormat('j M');
        }

        return $a->translatedFormat('j M').' – '.$b->translatedFormat('j M');
    }
}
