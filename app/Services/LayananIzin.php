<?php

namespace App\Services;

use App\Enums\JenisPenugasan;
use App\Enums\SesiTugas;
use App\Enums\StatusIzin;
use App\Enums\StatusPenugasan;
use App\Models\Izin;
use App\Models\LogAktivitas;
use App\Models\Penugasan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * PRD bagian 15 R3 dan R3a: izin (nama fitur di web cukup "Izin"), dengan tanggal + jam.
 *
 * - Warga mengajukan, pengurus menyetujui atau menolak. Pengurus juga bisa langsung membuat izin (langsung disetujui).
 * - R3a: piket hanya terkena izin kalau izin menutupi SELURUH waktu pengerjaannya (Tenggat::jendela).
 *   Izin sebagian tidak mengubah apa pun: piketnya tetap miliknya, dan ia tetap masuk pool.
 * - Piket terbit yang tertutup izin menjadi Izin (tidak menjadi bintang).
 *   Kamis/Minggu: sistem memilih pengganti (B3 langkah 2). Kalau tidak ada calon, tugasnya "Belum ada petugas".
 *   Harian dan kerja bakti: tidak ada pengganti otomatis; hari harian menjadi kosong.
 * - Piket yang masih draf langsung dipindah ke pengganti (atau dikosongkan) tanpa status Izin dan tanpa email.
 */
class LayananIzin
{
    /** Rentang izin terpanjang, supaya salah ketik tahun tidak melepas piket berbulan-bulan. */
    public const MAKS_HARI = 62;

    public function __construct(
        private PenugasanOtomatis $otomatis,
        private Pemberitahuan $pemberitahuan,
    ) {}

    public function ajukan(User $user, CarbonImmutable $mulai, CarbonImmutable $selesai, ?string $alasan): Izin
    {
        $this->periksaRentang($user, $mulai, $selesai, bolehLampau: false);

        $izin = Izin::create([
            'user_id' => $user->id,
            'mulai' => $mulai,
            'selesai' => $selesai,
            'alasan' => $this->rapikan($alasan),
            'status' => StatusIzin::Menunggu,
            'dibuat_oleh_id' => $user->id,
        ]);

        $this->pemberitahuan->izinDiajukan($izin);

        return $izin;
    }

    /**
     * Pengurus langsung membuat izin untuk warga (langsung disetujui).
     *
     * @return array{izin: Izin, hasil: array{dilepas: list<string>, pengganti: list<string>, tanpa_petugas: list<string>}}
     */
    public function buatLangsung(User $warga, CarbonImmutable $mulai, CarbonImmutable $selesai, ?string $alasan, User $oleh): array
    {
        $this->pastikanPengurus($oleh);
        $this->periksaRentang($warga, $mulai, $selesai, bolehLampau: true);

        [$izin, $hasil] = DB::transaction(function () use ($warga, $mulai, $selesai, $alasan, $oleh) {
            $izin = Izin::create([
                'user_id' => $warga->id,
                'mulai' => $mulai,
                'selesai' => $selesai,
                'alasan' => $this->rapikan($alasan),
                'status' => StatusIzin::Disetujui,
                'dibuat_oleh_id' => $oleh->id,
                'diputuskan_oleh_id' => $oleh->id,
                'diputuskan_pada' => CarbonImmutable::now(),
            ]);

            return [$izin, $this->terapkan($izin)];
        });

        LogAktivitas::catat('izin.dibuat', $izin, ['user_id' => $warga->id] + $this->ringkasLog($hasil));
        $this->pemberitahuan->izinDiputuskan($izin, $hasil['dilepas']);

        return ['izin' => $izin, 'hasil' => $hasil];
    }

    /** @return array{dilepas: list<string>, pengganti: list<string>, tanpa_petugas: list<string>} */
    public function setujui(Izin $izin, User $oleh, ?string $catatan = null): array
    {
        $this->pastikanMenunggu($izin, $oleh);

        $hasil = DB::transaction(function () use ($izin, $oleh, $catatan) {
            $izin->update([
                'status' => StatusIzin::Disetujui,
                'diputuskan_oleh_id' => $oleh->id,
                'diputuskan_pada' => CarbonImmutable::now(),
                'catatan' => $this->rapikan($catatan),
            ]);

            return $this->terapkan($izin);
        });

        LogAktivitas::catat('izin.disetujui', $izin, $this->ringkasLog($hasil));
        $this->pemberitahuan->izinDiputuskan($izin, $hasil['dilepas']);

        return $hasil;
    }

    public function tolak(Izin $izin, User $oleh, ?string $catatan = null): void
    {
        $this->pastikanMenunggu($izin, $oleh);

        $izin->update([
            'status' => StatusIzin::Ditolak,
            'diputuskan_oleh_id' => $oleh->id,
            'diputuskan_pada' => CarbonImmutable::now(),
            'catatan' => $this->rapikan($catatan),
        ]);

        LogAktivitas::catat('izin.ditolak', $izin, ['catatan' => $izin->catatan]);
        $this->pemberitahuan->izinDiputuskan($izin);
    }

    /** Piket milik warga ini yang seluruh waktu pengerjaannya tertutup izin (R3a). */
    public function piketTertutup(Izin $izin): Collection
    {
        // Piket Kamis/Minggu bisa berakhir sehari setelah tanggalnya, jadi rentang tanggal diperlebar sehari ke belakang.
        return Penugasan::query()
            ->where('user_id', $izin->user_id)
            ->whereIn('status', [StatusPenugasan::Terjadwal->value, StatusPenugasan::BayarBintang->value])
            ->whereDate('tanggal', '>=', $izin->mulaiLokal()->subDay()->toDateString())
            ->whereDate('tanggal', '<=', $izin->selesaiLokal()->toDateString())
            ->where('tenggat', '>', CarbonImmutable::now()->format('Y-m-d H:i:s'))
            ->with(['mingguPiket', 'tugas'])
            ->orderBy('tanggal')
            ->get()
            ->filter(fn (Penugasan $p) => $izin->menutupi(...Tenggat::jendela($p->jenis, $p->tanggalLokal())))
            ->values();
    }

    /**
     * Melepas piket yang tertutup izin dan memilih pengganti.
     *
     * @return array{dilepas: list<string>, pengganti: list<string>, tanpa_petugas: list<string>}
     */
    public function terapkan(Izin $izin): array
    {
        $hasil = ['dilepas' => [], 'pengganti' => [], 'tanpa_petugas' => []];
        $izin->loadMissing('user');

        foreach ($this->piketTertutup($izin) as $p) {
            $minggu = $p->mingguPiket;
            $terbit = $minggu->sudahTerbit($p->jenis);
            $sesi = match ($p->jenis) {
                JenisPenugasan::Kamis => SesiTugas::Kamis,
                JenisPenugasan::Minggu => SesiTugas::Minggu,
                default => null,
            };

            // Harian dan kerja bakti tidak punya pengganti otomatis.
            if ($sesi === null) {
                if ($terbit) {
                    $p->update(['status' => StatusPenugasan::Izin]);
                    $hasil['dilepas'][] = $p->ringkasan();
                } else {
                    $p->delete();
                }

                continue;
            }

            $pengganti = $this->otomatis->pilihPengganti($minggu, $sesi, $p->tugas_id, [$izin->user_id]);

            if (! $terbit) {
                // Draf: warga belum pernah melihatnya, jadi cukup dipindah atau dikosongkan.
                $pengganti
                    ? $p->update(['user_id' => $pengganti->id, 'status' => StatusPenugasan::Terjadwal, 'membayar_penugasan_id' => null])
                    : $p->delete();

                continue;
            }

            $p->update(['status' => StatusPenugasan::Izin]);
            $hasil['dilepas'][] = $p->ringkasan();

            if (! $pengganti) {
                $hasil['tanpa_petugas'][] = $p->namaTugas().' ('.$sesi->label().')';

                continue;
            }

            $baru = Penugasan::create([
                'minggu_piket_id' => $p->minggu_piket_id,
                'user_id' => $pengganti->id,
                'tugas_id' => $p->tugas_id,
                'jenis' => $p->jenis,
                'tanggal' => $p->tanggal->toDateString(),
                'tenggat' => $p->tenggat,
                'status' => StatusPenugasan::Terjadwal,
                'diberitahu_pada' => CarbonImmutable::now(),
            ]);
            $baru->setRelation('tugas', $p->tugas);
            $hasil['pengganti'][] = $p->namaTugas().': '.$pengganti->namaDepan();

            $this->pemberitahuan->berubah($pengganti, 'Kamu jadi pengganti: '.$p->namaTugas(), [
                "{$izin->user->namaDepan()} sedang izin, jadi sistem memilihmu sebagai pengganti.",
                '• '.$baru->ringkasan(),
            ]);
        }

        return $hasil;
    }

    /** Warga yang izinnya (disetujui) bersinggungan dengan rentang tersebut, untuk panel Pengawasan. */
    public function sedangIzin(CarbonImmutable $dari, CarbonImmutable $sampai): Collection
    {
        return Izin::query()->disetujui()->bersinggungan($dari, $sampai)
            ->with('user.geng')->orderBy('mulai')->get();
    }

    private function periksaRentang(User $user, CarbonImmutable $mulai, CarbonImmutable $selesai, bool $bolehLampau): void
    {
        if ($selesai->lessThanOrEqualTo($mulai)) {
            throw ValidationException::withMessages(['izin' => 'Waktu selesai harus setelah waktu mulai.']);
        }
        if (! $bolehLampau && $selesai->lessThanOrEqualTo(CarbonImmutable::now())) {
            throw ValidationException::withMessages(['izin' => 'Waktu izin sudah lewat.']);
        }
        if ($mulai->diffInDays($selesai) > self::MAKS_HARI) {
            throw ValidationException::withMessages(['izin' => 'Izin paling lama '.self::MAKS_HARI.' hari.']);
        }
        if (! $user->aktif) {
            throw ValidationException::withMessages(['izin' => 'Warga ini tidak aktif.']);
        }

        $bentrok = Izin::query()->where('user_id', $user->id)
            ->whereIn('status', [StatusIzin::Menunggu->value, StatusIzin::Disetujui->value])
            ->bersinggungan($mulai, $selesai)
            ->exists();
        if ($bentrok) {
            throw ValidationException::withMessages(['izin' => 'Sudah ada izin di waktu itu.']);
        }
    }

    private function pastikanPengurus(User $oleh): void
    {
        if (! $oleh->isPengurus()) {
            throw ValidationException::withMessages(['izin' => 'Hanya pengurus yang bisa melakukan ini.']);
        }
    }

    private function pastikanMenunggu(Izin $izin, User $oleh): void
    {
        $this->pastikanPengurus($oleh);
        if ($izin->status !== StatusIzin::Menunggu) {
            throw ValidationException::withMessages(['izin' => 'Izin ini sudah diputuskan.']);
        }
    }

    private function rapikan(?string $teks): ?string
    {
        $teks = trim((string) $teks);
        if (mb_strlen($teks) > 500) {
            throw ValidationException::withMessages(['izin' => 'Teks maksimal 500 karakter.']);
        }

        return $teks === '' ? null : $teks;
    }

    private function ringkasLog(array $hasil): array
    {
        return ['dilepas' => count($hasil['dilepas']), 'pengganti' => $hasil['pengganti'], 'tanpa_petugas' => $hasil['tanpa_petugas']];
    }
}
