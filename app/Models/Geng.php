<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Geng extends Model
{
    protected $fillable = ['nama', 'urutan'];

    protected function casts(): array
    {
        return ['urutan' => 'integer'];
    }

    public function anggota(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function anggotaAktif(): HasMany
    {
        return $this->anggota()->where('aktif', true);
    }
}
