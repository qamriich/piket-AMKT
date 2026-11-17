<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris jadwal harian periode: di geng ini, pada hari ini (1 = Senin ... 7 = Minggu), petugasnya warga ini.
 */
class PeriodeHarianHari extends Model
{
    protected $table = 'periode_harian_hari';

    protected $fillable = ['periode_harian_id', 'geng_id', 'hari', 'user_id'];

    protected function casts(): array
    {
        return ['hari' => 'integer'];
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(PeriodeHarian::class, 'periode_harian_id');
    }

    public function geng(): BelongsTo
    {
        return $this->belongsTo(Geng::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
