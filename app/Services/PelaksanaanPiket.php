<?php

namespace App\Services;

use App\Enums\JenisPenugasan;
use App\Enums\StatusPengajuan;
use App\Enums\StatusPenugasan;
use App\Models\LogAktivitas;
use App\Models\Penugasan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * F6 (tandai selesai dan pemeriksaan), catatan pengurus, B5 (pelunasan bintang),
 * dan R5 (koreksi status oleh pengurus setelah tenggat).
 */
class PelaksanaanPiket
{
    /** Status tujuan koreksi R5. Lunas tetap diatur sistem (B5). */
    public const STATUS_KOREKSI = [StatusPenugasan::Selesai, StatusPenugasan::Bintang, StatusPenugasan::Izin];

    /** Warga boleh menandai piketnya sendiri: bukan wajib diperiksa, harinya sudah tiba, dan belum lewat tenggat. */
    public function bisaDitandaiSendiri(Penugasan $p, User $user, ?CarbonImmutable $sekarang = null): bool
    {
        $sekarang ??= CarbonImmutable::now();

        return $p->user_id === $user->id
            && ! $user->wajib_diperiksa
            && $p->status->belumSelesai()
            && $p->sudahBisaDikerjakan($sekarang)
            && ! $p->lewatTenggat($sekarang);
    }

    /** Pengurus bisa menandai selesai piket siapa pun yang harinya sudah tiba dan belum lewat tenggat. */
    public function bisaDitandaiPengurus(Penugasan $p, ?CarbonImmutable $sekarang = null): bool
    {
        $sekarang ??= CarbonImmutable::now();

        return $p->status->belumSelesai() && $p->sudahBisaDikerjakan($sekarang) && ! $p->lewatTenggat($sekarang);
    }

    public function tandaiSelesai(Penugasan $p, User $oleh): Penugasan
    {
        $sekarang = CarbonImmutable::now();
        $this->pastikanTerbit($p);
        $sendiri = $p->user_id === $oleh->id;

        if (! $oleh->isPengurus()) {
            if (! $sendiri) {
                throw ValidationException::withMessages(['piket' => 'Kamu hanya bisa menandai piketmu sendiri.']);
            }
            if ($oleh->wajib_diperiksa) {
                throw ValidationException::withMessages(['piket' => 'Piketmu ditandai selesai oleh pengurus setelah diperiksa. Panggil pengurus, ya.']);
            }
        }

        DB::transaction(function () use ($p, $oleh, $sekarang) {
            $p = Penugasan::query()->lockForUpdate()->findOrFail($p->id);

            if (! $p->status->belumSelesai()) {
                throw ValidationException::withMessages(['piket' => $p->status === StatusPenugasan::Selesai
                    ? 'Piket ini sudah ditandai selesai.'
                    : 'Piket ini tidak bisa ditandai selesai (status '.$p->status->label().').']);
            }
            if (! $p->sudahBisaDikerjakan($sekarang)) {
                throw ValidationException::withMessages(['piket' => 'Piket ini baru bisa ditandai selesai '.$p->tanggalLokal()->translatedFormat('l, j M').'.']);
            }
            // F6: tanda selesai setelah tenggat tetap tercatat sebagai bintang. Pengurus memakai Koreksi (R5).
            if ($p->lewatTenggat($sekarang)) {
                throw ValidationException::withMessages(['piket' => $oleh->isPengurus()
                    ? 'Sudah lewat batas waktu, jadi tetap tercatat bintang. Pakai tombol Koreksi kalau piketnya memang sudah dikerjakan.'
                    : 'Sudah lewat batas waktu, jadi piket ini tercatat sebagai bintang.']);
            }

            $p->update(['status' => StatusPenugasan::Selesai, 'selesai_oleh_id' => $oleh->id, 'selesai_pada' => $sekarang]);

            // B5: Bayar Bintang selesai → bintang aslinya Lunas.
            if ($p->membayar_penugasan_id) {
                Penugasan::query()->whereKey($p->membayar_penugasan_id)
                    ->where('status', StatusPenugasan::Bintang->value)
                    ->update(['status' => StatusPenugasan::Lunas->value]);
            }
        });

        // B7: semua aksi pengurus dicatat, termasuk siapa yang memeriksa dan kapan.
        if ($oleh->isPengurus() && ! $sendiri) {
            LogAktivitas::catat('piket.selesai', $p, ['oleh' => $oleh->id]);
        }

        return $p->refresh();
    }

    /** Pengurus membatalkan tanda selesai (sebelum tenggat). Setelah tenggat pakai Koreksi. */
    public function batalkanSelesai(Penugasan $p, User $oleh): Penugasan
    {
        $this->pastikanPengurus($oleh);
        $this->pastikanTerbit($p);

        DB::transaction(function () use ($p) {
            $p = Penugasan::query()->lockForUpdate()->findOrFail($p->id);

            if ($p->status !== StatusPenugasan::Selesai) {
                throw ValidationException::withMessages(['piket' => 'Piket ini belum ditandai selesai.']);
            }
            if ($p->lewatTenggat()) {
                throw ValidationException::withMessages(['piket' => 'Sudah lewat batas waktu. Pakai tombol Koreksi untuk mengubah statusnya.']);
            }

            $p->update([
                'status' => $p->membayar_penugasan_id ? StatusPenugasan::BayarBintang : StatusPenugasan::Terjadwal,
                'selesai_oleh_id' => null,
                'selesai_pada' => null,
            ]);

            if ($p->membayar_penugasan_id) {
                $this->bukaLagiBintang($p->membayar_penugasan_id, $p->id);
            }
        });

        LogAktivitas::catat('piket.batal_selesai', $p);

        return $p->refresh();
    }

    /** Catatan pengurus di piket mana pun, terlihat oleh petugasnya (F6). Kosong = hapus catatan. */
    public function simpanCatatan(Penugasan $p, ?string $catatan, User $oleh): Penugasan
    {
        $this->pastikanPengurus($oleh);
        $catatan = trim((string) $catatan);
        if (mb_strlen($catatan) > 500) {
            throw ValidationException::withMessages(['catatan' => 'Catatan maksimal 500 karakter.']);
        }

        $p->update(['catatan' => $catatan === '' ? null : $catatan]);
        LogAktivitas::catat('piket.catatan', $p, ['catatan' => $p->catatan]);

        return $p;
    }

    /**
     * R5: pengurus mengubah status piket setelah tenggat, dengan catatan wajib.
     * Contoh: Bintang → Selesai (sebenarnya sudah dikerjakan), Selesai → Bintang, Bintang → Izin.
     */
    public function koreksi(Penugasan $p, StatusPenugasan $ke, string $catatan, User $oleh): Penugasan
    {
        $this->pastikanPengurus($oleh);
        $this->pastikanTerbit($p);
        $catatan = trim($catatan);
        $sekarang = CarbonImmutable::now();

        if (! in_array($ke, self::STATUS_KOREKSI, true)) {
            throw ValidationException::withMessages(['status' => 'Status koreksi hanya Selesai, Bintang, atau Izin.']);
        }
        if (mb_strlen($catatan) < 3) {
            throw ValidationException::withMessages(['catatan' => 'Catatan koreksi wajib diisi.']);
        }
        if (mb_strlen($catatan) > 500) {
            throw ValidationException::withMessages(['catatan' => 'Catatan maksimal 500 karakter.']);
        }

        $dari = null;
        DB::transaction(function () use ($p, $ke, $catatan, $oleh, $sekarang, &$dari) {
            $p = Penugasan::query()->lockForUpdate()->findOrFail($p->id);
            $dari = $p->status;

            if (! $p->lewatTenggat($sekarang)) {
                throw ValidationException::withMessages(['piket' => 'Koreksi hanya untuk piket yang sudah lewat batas waktu. Sebelum batas, pakai Tandai selesai atau Batalkan.']);
            }
            if ($dari === $ke || ($ke === StatusPenugasan::Bintang && $dari === StatusPenugasan::Lunas)) {
                throw ValidationException::withMessages(['status' => 'Statusnya sudah '.$dari->label().'.']);
            }

            // Baris Izin yang dihidupkan lagi tidak boleh bentrok dengan pengganti yang sudah ada.
            if ($dari === StatusPenugasan::Izin && $this->slotSudahDipakai($p)) {
                throw ValidationException::withMessages(['status' => 'Tugas ini sudah dipegang pengganti, jadi statusnya tidak bisa diubah dari Izin.']);
            }

            $data = ['status' => $ke, 'catatan' => $catatan];
            if ($ke === StatusPenugasan::Selesai) {
                $data += ['selesai_oleh_id' => $oleh->id, 'selesai_pada' => $sekarang];
            } else {
                $data += ['selesai_oleh_id' => null, 'selesai_pada' => null];
            }

            if ($p->membayar_penugasan_id) {
                // Baris Bayar Bintang: selesai → bintang aslinya lunas; batal selesai → bintang aslinya terbuka lagi.
                $p->update($data);
                if ($ke === StatusPenugasan::Selesai) {
                    Penugasan::query()->whereKey($p->membayar_penugasan_id)->where('status', StatusPenugasan::Bintang->value)
                        ->update(['status' => StatusPenugasan::Lunas->value]);
                } elseif ($dari === StatusPenugasan::Selesai) {
                    $this->bukaLagiBintang($p->membayar_penugasan_id, $p->id);
                }

                return;
            }

            // Bintang asli yang ternyata sudah dibayar tetap Lunas.
            if ($ke === StatusPenugasan::Bintang && $p->pembayaran()->where('status', StatusPenugasan::Selesai->value)->exists()) {
                $data['status'] = StatusPenugasan::Lunas;
            }
            $p->update($data);

            // Bintang asli yang dikoreksi jadi Selesai/Izin bukan utang lagi: lepaskan pembayarannya.
            if (in_array($dari, [StatusPenugasan::Bintang, StatusPenugasan::Lunas], true) && $ke !== StatusPenugasan::Bintang) {
                // Bayar Bintang yang masih berjalan menjadi piket biasa. Bayar Bintang yang selesai tetap selesai.
                $p->pembayaran()->where('status', StatusPenugasan::BayarBintang->value)
                    ->update(['status' => StatusPenugasan::Terjadwal->value, 'membayar_penugasan_id' => null]);
                $p->pembayaran()->where('status', StatusPenugasan::Selesai->value)->update(['membayar_penugasan_id' => null]);
                // Bayar Bintang yang terlewat tetap menunjuk ke sini supaya tidak dihitung sebagai bintang baru.

                $p->pengajuanGantiBintang()->where('status', StatusPengajuan::Menunggu->value)->update([
                    'status' => StatusPengajuan::Ditolak->value,
                    'diputuskan_oleh_id' => $oleh->id,
                    'diputuskan_pada' => $sekarang,
                    'catatan' => 'Bintangnya sudah dikoreksi pengurus, jadi tidak perlu diganti.',
                ]);
            }
        });

        LogAktivitas::catat('piket.koreksi', $p, ['dari' => $dari?->value, 'ke' => $ke->value, 'catatan' => $catatan]);

        return $p->refresh();
    }

    /** Bintang asli kembali belum lunas, kecuali masih ada pembayaran lain yang selesai. */
    private function bukaLagiBintang(int $bintangId, int $kecuali): void
    {
        $masihLunas = Penugasan::query()->where('membayar_penugasan_id', $bintangId)
            ->whereKeyNot($kecuali)->where('status', StatusPenugasan::Selesai->value)->exists();

        if (! $masihLunas) {
            Penugasan::query()->whereKey($bintangId)->where('status', StatusPenugasan::Lunas->value)
                ->update(['status' => StatusPenugasan::Bintang->value]);
        }
    }

    private function slotSudahDipakai(Penugasan $p): bool
    {
        $q = Penugasan::query()->bukanIzin()->whereKeyNot($p->id)->where('jenis', $p->jenis->value);

        return match ($p->jenis) {
            JenisPenugasan::Harian => $q->whereDate('tanggal', $p->tanggal->toDateString())->exists(),
            JenisPenugasan::KerjaBakti => false,
            default => $q->where('minggu_piket_id', $p->minggu_piket_id)->where('tugas_id', $p->tugas_id)->exists(),
        };
    }

    private function pastikanPengurus(User $oleh): void
    {
        if (! $oleh->isPengurus()) {
            throw ValidationException::withMessages(['piket' => 'Hanya pengurus yang bisa melakukan ini.']);
        }
    }

    private function pastikanTerbit(Penugasan $p): void
    {
        if (! Penugasan::query()->terbit()->whereKey($p->id)->exists()) {
            throw ValidationException::withMessages(['piket' => 'Jadwal piket ini belum diterbitkan.']);
        }
    }
}
