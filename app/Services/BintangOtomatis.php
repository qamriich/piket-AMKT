<?php

namespace App\Services;

use App\Enums\StatusPenugasan;
use App\Models\Penugasan;
use Carbon\CarbonImmutable;

/**
 * Aturan B4: piket terbit berstatus Belum dikerjakan (terjadwal) atau Bayar Bintang yang belum selesai
 * saat tenggat lewat diubah menjadi Bintang. Dijalankan scheduler tiap 15 menit dan pengecekan ringan
 * setiap halaman dibuka.
 *
 * B5: Bayar Bintang yang terlewat ikut menjadi Bintang, tapi tidak menambah utang (baris pembayaran
 * tidak dihitung di scopeBintangBelumLunas). Bintang aslinya tetap Bintang dan dijadwalkan ulang.
 */
class BintangOtomatis
{
    public function __construct(private Pemberitahuan $pemberitahuan) {}

    /** @return int Jumlah piket yang baru menjadi bintang. */
    public function proses(?CarbonImmutable $sekarang = null): int
    {
        $sekarang ??= CarbonImmutable::now();
        $belum = [StatusPenugasan::Terjadwal->value, StatusPenugasan::BayarBintang->value];

        $lewat = Penugasan::query()->terbit()
            ->whereIn('status', $belum)
            ->where('tenggat', '<', $sekarang->format('Y-m-d H:i:s'))
            ->orderBy('tenggat')
            ->get();

        $jumlah = 0;
        foreach ($lewat as $p) {
            // Update bersyarat: kalau request lain sudah memproses (atau piketnya baru saja ditandai selesai), lewati.
            $berubah = Penugasan::query()->whereKey($p->id)->whereIn('status', $belum)
                ->update(['status' => StatusPenugasan::Bintang->value]);
            if ($berubah === 0) {
                continue;
            }

            $p->status = StatusPenugasan::Bintang;
            $this->pemberitahuan->bintang($p);
            $jumlah++;
        }

        return $jumlah;
    }
}
