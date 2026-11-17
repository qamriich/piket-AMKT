<?php

namespace App\Models;

use App\Enums\SesiTugas;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TugasAktif extends Model
{
    protected $table = 'tugas_aktif';

    protected $fillable = ['minggu_piket_id', 'tugas_id', 'sesi'];

    protected function casts(): array
    {
        return ['sesi' => SesiTugas::class];
    }

    public function mingguPiket(): BelongsTo
    {
        return $this->belongsTo(MingguPiket::class);
    }

    public function tugas(): BelongsTo
    {
        return $this->belongsTo(Tugas::class)->withTrashed();
    }
}
