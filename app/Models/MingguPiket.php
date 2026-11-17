<?php

namespace App\Models;

use App\Enums\JenisPenugasan;
use App\Enums\ModeMinggu;
use App\Enums\SesiTugas;
use App\Enums\StatusTerbit;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MingguPiket extends Model
{
    protected $table = 'minggu_piket';

    protected $fillable = [
        'tanggal_senin', 'geng_harian_id', 'mode_minggu',
        'status_harian', 'status_kamis', 'status_minggu',
    ];

    protected $attributes = [
        'mode_minggu' => 'tugas_kecil',
        'status_harian' => 'draf',
        'status_kamis' => 'draf',
        'status_minggu' => 'draf',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_senin' => 'immutable_date',
            'mode_minggu' => ModeMinggu::class,
            'status_harian' => StatusTerbit::class,
            'status_kamis' => StatusTerbit::class,
            'status_minggu' => StatusTerbit::class,
        ];
    }

    public function gengHarian(): BelongsTo
    {
        return $this->belongsTo(Geng::class, 'geng_harian_id');
    }

    public function tugasAktif(): HasMany
    {
        return $this->hasMany(TugasAktif::class);
    }

    public function penugasan(): HasMany
    {
        return $this->hasMany(Penugasan::class);
    }

    /** @param  JenisPenugasan|SesiTugas|string  $bagian  'harian', 'kamis', atau 'minggu' */
    public function sudahTerbit(JenisPenugasan|SesiTugas|string $bagian): bool
    {
        $kolom = match (true) {
            $bagian instanceof JenisPenugasan => $bagian->kolomStatus(),
            $bagian instanceof SesiTugas => 'status_'.$bagian->value,
            default => 'status_'.$bagian,
        };

        return $this->{$kolom} === StatusTerbit::Terbit;
    }

    /** Senin 00.00 WITA. */
    public function senin(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->tanggal_senin->toDateString(), config('app.timezone'));
    }

    public function kamis(): CarbonImmutable
    {
        return $this->senin()->addDays(3);
    }

    public function hariMinggu(): CarbonImmutable
    {
        return $this->senin()->addDays(6);
    }

    public function kerjaBakti(): bool
    {
        return $this->mode_minggu === ModeMinggu::KerjaBakti;
    }
}
