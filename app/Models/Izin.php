<?php

namespace App\Models;

use App\Enums\JenisPenugasan;
use App\Enums\StatusIzin;
use App\Services\Tenggat;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Izin (PRD bagian 15 R3, R3a): mulai dan selesai memakai tanggal + jam. Piket hanya terkena izin
 * kalau izin yang disetujui menutupi seluruh waktu pengerjaannya (lihat Tenggat::jendela).
 */
class Izin extends Model
{
    protected $table = 'izin';

    protected $fillable = [
        'user_id', 'mulai', 'selesai', 'alasan', 'status',
        'dibuat_oleh_id', 'diputuskan_oleh_id', 'diputuskan_pada', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusIzin::class,
            'mulai' => 'immutable_datetime',
            'selesai' => 'immutable_datetime',
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

    /** Izin yang menutupi SELURUH rentang tersebut (R3a). */
    public function scopeMenutupi(Builder $query, CarbonImmutable $dari, CarbonImmutable $sampai): Builder
    {
        return $query->where('izin.mulai', '<=', $dari->format('Y-m-d H:i:s'))
            ->where('izin.selesai', '>=', $sampai->format('Y-m-d H:i:s'));
    }

    /** Izin yang menutupi seluruh waktu pengerjaan satu piket (R3a). */
    public function scopeMenutupiPiket(Builder $query, JenisPenugasan $jenis, CarbonImmutable $tanggal): Builder
    {
        [$dari, $sampai] = Tenggat::jendela($jenis, $tanggal);

        return $query->menutupi($dari, $sampai);
    }

    /** Izin yang rentangnya bersinggungan dengan rentang tersebut (walau sebentar). */
    public function scopeBersinggungan(Builder $query, CarbonImmutable $dari, CarbonImmutable $sampai): Builder
    {
        return $query->where('izin.mulai', '<', $sampai->format('Y-m-d H:i:s'))
            ->where('izin.selesai', '>', $dari->format('Y-m-d H:i:s'));
    }

    public function mulaiLokal(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->mulai->format('Y-m-d H:i:s'), config('app.timezone'));
    }

    public function selesaiLokal(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->selesai->format('Y-m-d H:i:s'), config('app.timezone'));
    }

    public function menutupi(CarbonImmutable $dari, CarbonImmutable $sampai): bool
    {
        return $this->mulaiLokal()->lessThanOrEqualTo($dari) && $this->selesaiLokal()->greaterThanOrEqualTo($sampai);
    }

    /** Contoh: "Kamis, 19 Nov 08.00 – Jumat, 20 Nov 20.00" atau "Kamis, 19 Nov 10.00–14.00". */
    public function rentangLabel(): string
    {
        $a = $this->mulaiLokal();
        $b = $this->selesaiLokal();

        if ($a->isSameDay($b)) {
            return $a->translatedFormat('l, j M').' '.$a->format('H.i').'–'.$b->format('H.i');
        }

        return $a->translatedFormat('l, j M').' '.$a->format('H.i').' – '.$b->translatedFormat('l, j M').' '.$b->format('H.i');
    }
}
