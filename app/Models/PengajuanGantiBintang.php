<?php

namespace App\Models;

use App\Enums\StatusPengajuan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengajuanGantiBintang extends Model
{
    protected $table = 'pengajuan_ganti_bintang';

    protected $fillable = [
        'penugasan_bintang_id', 'user_id', 'tanggal_diajukan', 'status',
        'diputuskan_oleh_id', 'diputuskan_pada', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusPengajuan::class,
            'tanggal_diajukan' => 'immutable_date',
            'diputuskan_pada' => 'immutable_datetime',
        ];
    }

    public function penugasanBintang(): BelongsTo
    {
        return $this->belongsTo(Penugasan::class, 'penugasan_bintang_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function diputuskanOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diputuskan_oleh_id');
    }
}
