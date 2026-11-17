<?php

namespace App\Services;

use App\Enums\JenisPenugasan;
use App\Enums\SesiTugas;
use App\Enums\StatusIzin;
use App\Enums\StatusPengajuan;
use App\Enums\StatusPenugasan;
use App\Models\Izin;
use App\Models\MingguPiket;
use App\Models\PengajuanGantiBintang;
use App\Models\Penugasan;
use App\Models\User;
use App\Notifications\BintangDiberikan;
use App\Notifications\IzinDiajukan;
use App\Notifications\IzinDiputuskan;
use App\Notifications\JadwalBerubah;
use App\Notifications\JadwalDiterbitkan;
use App\Notifications\JadwalHarianMingguIni;
use App\Notifications\PengajuanBaru;
use App\Notifications\PengajuanDiputuskan;
use App\Notifications\PengingatPiket;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

/**
 * Semua email piket (PRD bagian 8). Email dikirim lewat antrean (lihat PemberitahuanPiket).
 */
class Pemberitahuan
{
    /** Jadwal Kamis/Minggu diterbitkan: ke semua warga aktif, berisi piketnya atau info bebas. */
    public function jadwalTerbit(MingguPiket $minggu, SesiTugas $sesi): int
    {
        $tanggal = $sesi === SesiTugas::Kamis ? $minggu->kamis() : $minggu->hariMinggu();
        $nama = $sesi === SesiTugas::Kamis ? 'Kamis' : ($minggu->kerjaBakti() ? 'Minggu (kerja bakti)' : 'Minggu');

        $baris = $minggu->penugasan()->bukanIzin()->whereIn('jenis', $sesi->jenisPenugasan())
            ->with(['tugas', 'membayar'])->get()->groupBy('user_id');

        $izin = Izin::query()->disetujui()->mencakup($tanggal)->pluck('user_id')->all();
        $jumlah = 0;

        foreach (User::query()->aktif()->orderBy('id')->get() as $u) {
            $punya = $baris->get($u->id, collect());

            if ($punya->isNotEmpty()) {
                $isi = ["Jadwal piket {$nama}, {$tanggal->translatedFormat('j F Y')}, sudah terbit. Piketmu:"];
                foreach ($punya as $p) {
                    $isi[] = '• '.$p->ringkasan();
                    if ($p->status === StatusPenugasan::BayarBintang && $p->membayar) {
                        $isi[] = '  Ini piket pengganti untuk bintang '.$p->membayar->tanggalLokal()->translatedFormat('j M').'. Kalau selesai, bintangnya lunas.';
                    }
                }
                $isi[] = 'Setelah selesai, tandai di web (atau panggil pengurus kalau piketmu wajib diperiksa).';
                $u->notify(new JadwalDiterbitkan("Piket {$nama} kamu: ".$punya->map->namaTugas()->join(', '), $isi, 'Lihat piket saya', 'warga.piket'));
            } else {
                $alasan = match (true) {
                    in_array($u->id, $izin, true) => 'karena kamu sedang izin pulang',
                    $sesi === SesiTugas::Kamis && $u->geng_id === $minggu->geng_harian_id => 'karena gengmu sedang piket harian minggu ini',
                    default => 'karena tugasnya sudah terbagi habis. Minggu depan kamu didahulukan',
                };
                $u->notify(new JadwalDiterbitkan(
                    "Piket {$nama}: minggu ini kamu bebas",
                    ["Jadwal piket {$nama}, {$tanggal->translatedFormat('j F Y')}, sudah terbit.", "Minggu ini kamu bebas piket {$nama} {$alasan}."],
                    'Lihat jadwal', 'warga.jadwal',
                ));
            }
            $jumlah++;
        }

        Penugasan::query()->whereIn('id', $baris->flatten()->pluck('id'))->update(['diberitahu_pada' => CarbonImmutable::now()]);

        return $jumlah;
    }

    /**
     * Piket harian minggu ini: harian tidak diterbitkan (R1), jadi setiap petugas harian diberi tahu sekali
     * untuk hari-hari yang belum lewat. Dijalankan scheduler dan pengecekan ringan saat halaman dibuka.
     */
    public function harianMingguIni(?CarbonImmutable $sekarang = null): int
    {
        $sekarang ??= CarbonImmutable::now();
        $senin = RotasiGeng::seninDari($sekarang);

        $baris = Penugasan::query()->terbit()
            ->where('jenis', JenisPenugasan::Harian->value)
            ->whereIn('status', [StatusPenugasan::Terjadwal->value, StatusPenugasan::BayarBintang->value])
            ->whereNull('diberitahu_pada')
            ->whereDate('tanggal', '>=', $sekarang->toDateString())
            ->whereDate('tanggal', '<=', $senin->addDays(6)->toDateString())
            ->where('tenggat', '>', $sekarang->format('Y-m-d H:i:s'))
            ->with('user')
            ->orderBy('tanggal')
            ->get();

        $jumlah = 0;
        foreach ($baris->groupBy('user_id') as $punya) {
            // Ditandai dulu secara atomik supaya dua request bersamaan tidak mengirim dua kali.
            $ids = $punya->pluck('id');
            if (Penugasan::query()->whereIn('id', $ids)->whereNull('diberitahu_pada')->update(['diberitahu_pada' => $sekarang]) === 0) {
                continue;
            }
            $u = $punya->first()->user;
            if (! $u->aktif) {
                continue;
            }

            $hari = $punya->map(fn (Penugasan $p) => $p->tanggalLokal()->translatedFormat('l, j M'))->join(', ', ' dan ');
            $u->notify(new JadwalHarianMingguIni(
                "Piket harianmu minggu ini: {$hari}",
                [
                    "Minggu ini kamu piket harian pada {$hari}.",
                    ...$punya->map(fn (Penugasan $p) => '• '.$p->ringkasan())->all(),
                    'Semua tugas harian dikerjakan jam 04.00–09.00. Kalau lewat jam 09.00, piketnya jadi bintang.',
                ],
                'Lihat piket saya', 'warga.piket',
            ));
            $jumlah++;
        }

        return $jumlah;
    }

    /** Perubahan setelah terbit (B8): hanya ke warga yang terdampak. */
    public function berubah(?User $u, string $subjek, array $baris): void
    {
        if ($u && $u->aktif) {
            $u->notify(new JadwalBerubah($subjek, $baris, 'Lihat piket saya', 'warga.piket'));
        }
    }

    /** Mendapat bintang (B4). */
    public function bintang(Penugasan $p): void
    {
        $p->loadMissing(['user', 'tugas', 'membayar']);
        if (! $p->user->aktif) {
            return;
        }

        $isi = ['Piket ini belum ditandai selesai sampai batas waktunya, jadi tercatat sebagai bintang (utang piket):', '• '.$p->ringkasan()];
        $isi[] = match (true) {
            $p->membayar_penugasan_id !== null => 'Ini piket pengganti bintang, jadi utangmu tidak bertambah. Bintang aslinya tetap belum lunas dan '
                .($p->jenis === JenisPenugasan::Harian ? 'bisa kamu ajukan ganti lagi di hari kosong.' : 'akan dijadwalkan ulang otomatis di minggu berikutnya.'),
            $p->jenis === JenisPenugasan::Harian => 'Bintang harian dibayar dengan memilih hari kosong lewat menu Utang, lalu diajukan ke pengurus.',
            default => 'Bintang ini akan dijadwalkan ulang otomatis di piket '.($p->jenis === JenisPenugasan::Kamis ? 'Kamis' : 'Minggu').' berikutnya dengan status Bayar Bintang.',
        };
        $isi[] = 'Kalau sebenarnya sudah kamu kerjakan, sampaikan ke pengurus supaya statusnya dikoreksi.';

        $p->user->notify(new BintangDiberikan('Kamu mendapat bintang: '.$p->namaTugas(), $isi, 'Lihat utang piket', 'warga.utang'));
    }

    /**
     * Pengingat (bagian 8) untuk petugas yang belum selesai: harian mulai jam 20.00 malam sebelumnya,
     * Kamis mulai Jumat 12.00, Minggu dan kerja bakti mulai Senin 12.00. Masing-masing sekali.
     */
    public function pengingat(?CarbonImmutable $sekarang = null): int
    {
        $sekarang ??= CarbonImmutable::now();

        $calon = Penugasan::query()->terbit()
            ->whereIn('status', [StatusPenugasan::Terjadwal->value, StatusPenugasan::BayarBintang->value])
            ->whereNull('pengingat_dikirim_pada')
            ->where('tenggat', '>', $sekarang->format('Y-m-d H:i:s'))
            ->whereDate('tanggal', '>=', $sekarang->subDay()->toDateString())
            ->whereDate('tanggal', '<=', $sekarang->addDay()->toDateString())
            ->with(['user', 'tugas'])
            ->get()
            ->filter(fn (Penugasan $p) => $sekarang->greaterThanOrEqualTo($this->waktuPengingat($p)));

        $jumlah = 0;
        foreach ($calon as $p) {
            if (Penugasan::query()->whereKey($p->id)->whereNull('pengingat_dikirim_pada')->update(['pengingat_dikirim_pada' => $sekarang]) === 0) {
                continue;
            }
            if (! $p->user->aktif) {
                continue;
            }

            $kapan = $p->jenis === JenisPenugasan::Harian
                ? ($p->tanggalLokal()->isSameDay($sekarang) ? 'hari ini' : 'besok pagi')
                : 'sampai '.$p->tenggatLokal()->translatedFormat('l').' jam '.$p->tenggatLokal()->format('H.i');
            $p->user->notify(new PengingatPiket(
                'Pengingat piket: '.$p->namaTugas(),
                [
                    $p->jenis === JenisPenugasan::Harian
                        ? "Jangan lupa, {$kapan} kamu piket harian jam 04.00–09.00."
                        : "Piketmu belum ditandai selesai. Batasnya {$kapan}.",
                    '• '.$p->ringkasan(),
                    $p->user->wajib_diperiksa
                        ? 'Setelah selesai, panggil pengurus untuk memeriksa.'
                        : 'Setelah selesai, tekan "Saya sudah selesai" di web.',
                ],
                'Lihat piket saya', 'warga.piket',
            ));
            $jumlah++;
        }

        return $jumlah;
    }

    public function waktuPengingat(Penugasan $p): CarbonImmutable
    {
        $tanggal = $p->tanggalLokal();

        return $p->jenis === JenisPenugasan::Harian
            ? $tanggal->subDay()->setTime(20, 0)
            : $tanggal->addDay()->setTime(12, 0);
    }

    /** Ada pengajuan ganti bintang baru: ke semua pengurus aktif. */
    public function pengajuanBaru(PengajuanGantiBintang $a): void
    {
        $a->loadMissing(['user', 'penugasanBintang']);
        Notification::send(User::query()->aktif()->pengurus()->get(), new PengajuanBaru(
            "Pengajuan ganti bintang dari {$a->user->namaDepan()}",
            [
                "{$a->user->nama} mengajukan ganti bintang harian.",
                '• Bintang: piket harian '.$a->penugasanBintang->tanggalLokal()->translatedFormat('l, j M'),
                '• Ganti di: '.$a->tanggal_diajukan->translatedFormat('l, j M Y'),
                'Terima atau tolak lewat menu Pengajuan.',
            ],
            'Buka pengajuan', 'pengurus.pengajuan',
        ));
    }

    public function pengajuanDiputuskan(PengajuanGantiBintang $a): void
    {
        $a->loadMissing(['user', 'penugasanBintang', 'diputuskanOleh']);
        $diterima = $a->status === StatusPengajuan::Diterima;
        $tanggal = $a->tanggal_diajukan->translatedFormat('l, j M Y');

        $isi = [$diterima
            ? "Pengajuan ganti bintangmu diterima. Kamu piket harian pada {$tanggal} jam 04.00–09.00 dengan status Bayar Bintang. Kalau selesai, bintang tanggal {$a->penugasanBintang->tanggalLokal()->translatedFormat('j M')} lunas."
            : "Pengajuan ganti bintangmu untuk {$tanggal} ditolak. Kamu bisa memilih hari kosong lain lewat menu Utang."];
        if ($a->catatan) {
            $isi[] = 'Catatan pengurus: '.$a->catatan;
        }

        $a->user->notify(new PengajuanDiputuskan(
            'Pengajuan ganti bintang '.($diterima ? 'diterima' : 'ditolak'),
            $isi,
            $diterima ? 'Lihat piket saya' : 'Pilih hari lain',
            $diterima ? 'warga.piket' : 'warga.utang',
        ));
    }

    public function izinDiajukan(Izin $izin): void
    {
        $izin->loadMissing('user');
        $isi = ["{$izin->user->nama} mengajukan izin pulang {$izin->rentangLabel()}."];
        if ($izin->alasan) {
            $isi[] = 'Alasan: '.$izin->alasan;
        }
        $isi[] = 'Setujui atau tolak lewat menu Pengajuan → Izin pulang.';

        Notification::send(User::query()->aktif()->pengurus()->get(), new IzinDiajukan(
            "Izin pulang dari {$izin->user->namaDepan()}", $isi, 'Buka pengajuan', 'pengurus.pengajuan',
        ));
    }

    /** @param  list<string>  $dilepas  Ringkasan piket yang dilepas karena izin */
    public function izinDiputuskan(Izin $izin, array $dilepas = []): void
    {
        $izin->loadMissing('user');
        $disetujui = $izin->status === StatusIzin::Disetujui;

        $isi = [$disetujui
            ? "Izin pulangmu {$izin->rentangLabel()} disetujui. Selama izin, kamu tidak dijadwalkan piket Kamis dan Minggu."
            : "Izin pulangmu {$izin->rentangLabel()} ditolak, jadi piketmu tetap berjalan seperti biasa."];
        if ($disetujui && $dilepas !== []) {
            $isi[] = 'Piket yang dilepas (status Izin, tidak menjadi bintang):';
            foreach ($dilepas as $d) {
                $isi[] = '• '.$d;
            }
        }
        if ($izin->catatan) {
            $isi[] = 'Catatan pengurus: '.$izin->catatan;
        }

        $izin->user->notify(new IzinDiputuskan('Izin pulang '.($disetujui ? 'disetujui' : 'ditolak'), $isi, 'Lihat piket saya', 'warga.piket'));
    }
}
