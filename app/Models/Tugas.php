<?php

namespace App\Models;

use App\Enums\JenisTugas;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tugas extends Model
{
    use SoftDeletes;

    protected $table = 'tugas';

    protected $fillable = ['nama', 'jenis', 'urutan', 'aktif_default'];

    protected function casts(): array
    {
        return [
            'jenis' => JenisTugas::class,
            'urutan' => 'integer',
            'aktif_default' => 'boolean',
        ];
    }

    public function scopeJenis(Builder $query, JenisTugas $jenis): Builder
    {
        return $query->where('jenis', $jenis->value);
    }

    public function scopeUrut(Builder $query): Builder
    {
        return $query->orderBy('urutan')->orderBy('id');
    }
}
