<?php

namespace App\Models;

use App\Enums\JenisPenugasan;
use App\Enums\StatusPenugasan;
use App\Services\Tenggat;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;

class Penugasan extends Model
{
    protected $table = 'penugasan';

    protected $fillable = [
        'minggu_piket_id', 'user_id', 'tugas_id', 'jenis', 'tanggal', 'tenggat',
        'status', 'membayar_penugasan_id', 'selesai_oleh_id', 'selesai_pada', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'jenis' => JenisPenugasan::class,
            'status' => StatusPenugasan::class,
            'tanggal' => 'immutable_date',
            'tenggat' => 'immutable_datetime',
            'selesai_pada' => 'immutable_datetime',
        ];
    }

    public function mingguPiket(): BelongsTo
    {
        return $this->belongsTo(MingguPiket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tugas(): BelongsTo
    {
        return $this->belongsTo(Tugas::class)->withTrashed();
    }

    /** Baris Bintang yang dilunasi oleh baris Bayar Bintang ini. */
    public function membayar(): BelongsTo
    {
        return $this->belongsTo(self::class, 'membayar_penugasan_id');
    }

    /** Baris Bayar Bintang yang menunjuk ke baris Bintang ini. */
    public function pembayaran(): HasMany
    {
        return $this->hasMany(self::class, 'membayar_penugasan_id');
    }

    public function selesaiOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'selesai_oleh_id');
    }

    /**
     * Hanya penugasan yang jadwalnya sudah diterbitkan. Draf hanya terlihat di halaman Atur jadwal.
     */
    public function scopeTerbit(Builder $query): Builder
    {
        return $query->whereExists(function (QueryBuilder $q) {
            $q->selectRaw('1')
                ->from('minggu_piket as mp_terbit')
                ->whereColumn('mp_terbit.id', 'penugasan.minggu_piket_id')
                ->where(function (QueryBuilder $w) {
                    $w->where(fn ($a) => $a->where('penugasan.jenis', 'harian')->where('mp_terbit.status_harian', 'terbit'))
                        ->orWhere(fn ($a) => $a->where('penugasan.jenis', 'kamis')->where('mp_terbit.status_kamis', 'terbit'))
                        ->orWhere(fn ($a) => $a->whereIn('penugasan.jenis', ['minggu', 'kerja_bakti'])->where('mp_terbit.status_minggu', 'terbit'));
                });
        });
    }

    /**
     * Bintang yang belum lunas (PRD bagian 6): baris berstatus Bintang yang belum ditunjuk
     * oleh baris Bayar Bintang berstatus Selesai. Bayar Bintang yang terlewat tidak menambah
     * utang (B5), jadi hanya bintang asli (bukan baris pembayaran) yang dihitung.
     */
    public function scopeBintangBelumLunas(Builder $query): Builder
    {
        return $query->where('penugasan.status', StatusPenugasan::Bintang->value)
            ->whereNull('penugasan.membayar_penugasan_id')
            ->whereDoesntHave('pembayaran', fn (Builder $p) => $p->where('status', StatusPenugasan::Selesai->value));
    }

    /** Nama tugas untuk ditampilkan. */
    public function namaTugas(): string
    {
        return match ($this->jenis) {
            JenisPenugasan::Harian => 'Piket harian',
            JenisPenugasan::KerjaBakti => 'Kerja bakti',
            default => $this->tugas?->nama ?? 'Tugas',
        };
    }

    public function tanggalLokal(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->tanggal->toDateString(), config('app.timezone'));
    }

    public function tenggatLokal(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->tenggat->format('Y-m-d H:i:s'), config('app.timezone'));
    }

    /** Waktu mulai piket, untuk bar progres hitung mundur. */
    public function mulai(): CarbonImmutable
    {
        return Tenggat::mulai($this->jenis, $this->tanggalLokal());
    }

    /** Hari pengerjaan sudah tiba. */
    public function sudahBisaDikerjakan(?CarbonImmutable $sekarang = null): bool
    {
        return ($sekarang ?? CarbonImmutable::now())->greaterThanOrEqualTo($this->tanggalLokal()->startOfDay());
    }
}
