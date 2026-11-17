<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Jadwal harian periode (PRD bagian 15 R1), misalnya "Semester Ganjil 2026/2027".
 */
class PeriodeHarian extends Model
{
    protected $table = 'periode_harian';

    protected $fillable = ['nama', 'tanggal_mulai', 'tanggal_selesai'];

    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'immutable_date',
            'tanggal_selesai' => 'immutable_date',
        ];
    }

    public function hari(): HasMany
    {
        return $this->hasMany(PeriodeHarianHari::class);
    }

    /** Periode yang mencakup tanggal tersebut. */
    public function scopeMencakup(Builder $query, CarbonImmutable $tanggal): Builder
    {
        return $query->whereDate('tanggal_mulai', '<=', $tanggal->toDateString())
            ->whereDate('tanggal_selesai', '>=', $tanggal->toDateString());
    }

    public function mulai(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->tanggal_mulai->toDateString(), config('app.timezone'));
    }

    public function selesai(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->tanggal_selesai->toDateString(), config('app.timezone'));
    }

    public function mencakup(CarbonImmutable $tanggal): bool
    {
        return $tanggal->startOfDay()->betweenIncluded($this->mulai(), $this->selesai());
    }
}
