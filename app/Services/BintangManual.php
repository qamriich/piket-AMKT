<?php

namespace App\Services;

use App\Enums\JenisPenugasan;
use App\Enums\JenisTugas;
use App\Enums\StatusPenugasan;
use App\Enums\StatusTerbit;
use App\Models\LogAktivitas;
use App\Models\MingguPiket;
use App\Models\Penugasan;
use App\Models\Tugas;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Bintang yang dicatat manual oleh pengurus, misalnya utang bintang dari sebelum aplikasi dipakai.
 *
 * Bintang manual sama persis dengan bintang otomatis (B4): baris penugasan berstatus Bintang, sehingga ikut
 * dijadwalkan ulang (B3, bintang dulu), bisa diajukan ganti bintang (F8), dan terhitung di rekap.
 * Bedanya: tidak mengirim email (peristiwanya sudah lama) dan boleh dihapus selama belum dijadwalkan bayar.
 */
class BintangManual
{
    public function __construct(private JadwalMinggu $jadwal) {}

    /**
     * @throws ValidationException
     */
    public function tambah(User $warga, JenisPenugasan $jenis, CarbonImmutable $tanggal, ?Tugas $tugas = null, ?string $catatan = null): Penugasan
    {
        $tanggal = $tanggal->startOfDay();
        $this->periksa($warga, $jenis, $tanggal, $tugas);

        return DB::transaction(function () use ($warga, $jenis, $tanggal, $tugas, $catatan) {
            $senin = RotasiGeng::seninDari($tanggal);
            $sudahAda = MingguPiket::query()->whereDate('tanggal_senin', $senin->toDateString())->exists();
            $minggu = $this->jadwal->cariAtauBuatTanpaPeriode($tanggal);

            // Minggu lampau yang baru dibuat hanya berisi catatan lama. Semua bagiannya ditandai terbit:
            // bintangnya terhitung di rekap bulan itu, dan jadwal periode tidak akan pernah diterapkan ke
            // minggu lampau ini (terapkan() melewati minggu yang harian-nya sudah terbit), sehingga tidak muncul
            // piket harian lampau yang langsung menjadi bintang. Minggu yang sudah ada tidak diubah statusnya.
            if (! $sudahAda && $senin->lt(RotasiGeng::seninDari(CarbonImmutable::now()))) {
                $minggu->update([
                    'status_harian' => StatusTerbit::Terbit,
                    'status_kamis' => StatusTerbit::Terbit,
                    'status_minggu' => StatusTerbit::Terbit,
                ]);
            }

            $this->pastikanSlotKosong($minggu, $jenis, $tanggal, $tugas);

            $sekarang = CarbonImmutable::now();
            $bintang = Penugasan::create([
                'minggu_piket_id' => $minggu->id,
                'user_id' => $warga->id,
                'tugas_id' => $this->pakaiTugas($jenis) ? $tugas?->id : null,
                'jenis' => $jenis,
                'tanggal' => $tanggal->toDateString(),
                'tenggat' => Tenggat::untuk($jenis, $tanggal),
                'status' => StatusPenugasan::Bintang,
                'manual' => true,
                'catatan' => filled($catatan) ? trim($catatan) : null,
                // Jangan kirim email piket harian atau pengingat untuk catatan lama.
                'diberitahu_pada' => $sekarang,
                'pengingat_dikirim_pada' => $sekarang,
            ]);

            LogAktivitas::catat('bintang.manual_ditambah', $bintang, [
                'warga' => $warga->nama,
                'jenis' => $jenis->value,
                'tugas' => $tugas?->nama,
                'tanggal' => $tanggal->toDateString(),
            ]);

            return $bintang;
        });
    }

    /**
     * Hapus bintang manual yang salah catat. Bintang otomatis dan bintang yang sudah dijadwalkan bayar
     * (atau sudah lunas) tidak bisa dihapus di sini; pakai koreksi status (R5) untuk itu.
     *
     * @throws ValidationException
     */
    public function hapus(Penugasan $bintang): void
    {
        if (! $bintang->manual || $bintang->status !== StatusPenugasan::Bintang) {
            throw ValidationException::withMessages(['bintang' => 'Hanya bintang manual yang belum lunas yang bisa dihapus.']);
        }

        if ($bintang->pembayaran()->exists() || $bintang->pengajuanGantiBintang()->exists()) {
            throw ValidationException::withMessages(['bintang' => 'Bintang ini sudah dijadwalkan bayar atau sudah diajukan ganti, jadi tidak bisa dihapus.']);
        }

        DB::transaction(function () use ($bintang) {
            LogAktivitas::catat('bintang.manual_dihapus', $bintang, [
                'warga' => $bintang->user?->nama,
                'jenis' => $bintang->jenis->value,
                'tugas' => $bintang->tugas?->nama,
                'tanggal' => $bintang->tanggal->toDateString(),
            ]);
            $bintang->delete();
        });
    }

    private function pakaiTugas(JenisPenugasan $jenis): bool
    {
        return in_array($jenis, [JenisPenugasan::Kamis, JenisPenugasan::Minggu], true);
    }

    private function periksa(User $warga, JenisPenugasan $jenis, CarbonImmutable $tanggal, ?Tugas $tugas): void
    {
        $galat = match (true) {
            ! $warga->aktif => "{$warga->nama} sudah nonaktif.",
            $tanggal->gt(CarbonImmutable::now()->startOfDay()) => 'Tanggal bintang tidak boleh setelah hari ini.',
            $jenis === JenisPenugasan::Kamis && ! $tanggal->isThursday() => 'Piket Kamis harus bertanggal hari Kamis.',
            in_array($jenis, [JenisPenugasan::Minggu, JenisPenugasan::KerjaBakti], true) && ! $tanggal->isSunday() => 'Piket Minggu dan kerja bakti harus bertanggal hari Minggu.',
            $this->pakaiTugas($jenis) && $tugas === null => 'Pilih tugasnya. Bintang Kamis dan Minggu dibayar dengan tugas yang sama.',
            $this->pakaiTugas($jenis) && $tugas->jenis !== JenisTugas::KamisMinggu => 'Tugas itu bukan tugas Kamis/Minggu.',
            default => null,
        };

        if ($galat !== null) {
            throw ValidationException::withMessages(['bintang' => $galat]);
        }
    }

    /** Aturan integritas yang sama dengan tabel: satu petugas per tugas per minggu, satu orang per tanggal harian. */
    private function pastikanSlotKosong(MingguPiket $minggu, JenisPenugasan $jenis, CarbonImmutable $tanggal, ?Tugas $tugas): void
    {
        $bentrok = match (true) {
            $jenis === JenisPenugasan::Harian => Penugasan::query()->bukanIzin()
                ->where('jenis', JenisPenugasan::Harian->value)
                ->whereDate('tanggal', $tanggal->toDateString())
                ->with('user')->first(),
            $this->pakaiTugas($jenis) => Penugasan::query()->bukanIzin()
                ->where('minggu_piket_id', $minggu->id)
                ->where('jenis', $jenis->value)
                ->where('tugas_id', $tugas->id)
                ->with('user')->first(),
            default => null,
        };

        if ($bentrok !== null) {
            $nama = $bentrok->user?->nama ?? 'orang lain';
            $apa = $jenis === JenisPenugasan::Harian
                ? 'Piket harian '.$tanggal->translatedFormat('j M Y')
                : "{$tugas->nama} ({$jenis->label()}, ".$tanggal->translatedFormat('j M Y').')';

            throw ValidationException::withMessages(['bintang' => "{$apa} sudah tercatat untuk {$nama}."]);
        }
    }
}
