<?php

namespace App\Livewire\Warga;

use App\Enums\JenisPenugasan;
use App\Enums\StatusPengajuan;
use App\Enums\StatusPenugasan;
use App\Models\Penugasan;
use App\Services\RingkasanWarga;
use App\Services\Tenggat;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.warga', ['tab' => 'beranda'])]
#[Title('Beranda')]
class Beranda extends Component
{
    public function render(RingkasanWarga $ringkas)
    {
        $user = auth()->user();
        $sekarang = $ringkas->sekarang();
        $aktif = $ringkas->piketAktif($user);
        $belum = $aktif->filter(fn (Penugasan $p) => $p->status->belumSelesai());

        $subjudul = match (true) {
            $belum->isNotEmpty() => 'Kamu dapat piket '.$belum->map(fn (Penugasan $p) => $p->jenis === JenisPenugasan::KerjaBakti ? 'kerja bakti' : $p->jenis->label())
                ->unique()->join(', ', ' dan ').' minggu ini. Yuk diselesaikan sebelum batasnya.',
            $aktif->isNotEmpty() => 'Piketmu minggu ini sudah beres. Terima kasih sudah menjaga asrama tetap bersih!',
            default => 'Minggu ini kamu bebas piket. Tetap cek jadwal, ya.',
        };

        return view('livewire.warga.beranda', [
            'user' => $user,
            'sekarang' => $sekarang,
            'geng' => $ringkas->gengHarian(),
            'subjudul' => $subjudul,
            'tenggat' => $ringkas->tenggatTerdekat($user),
            'selesaiBulanIni' => $ringkas->selesaiBulanIni($user),
            'bintang' => $ringkas->jumlahBintangBelumLunas($user),
            'kartu' => $this->kartu($ringkas, $aktif, $sekarang),
        ]);
    }

    /**
     * Kartu "Yang perlu kamu tahu hari ini", disusun dari data asli.
     *
     * @return list<array<string, mixed>>
     */
    private function kartu(RingkasanWarga $ringkas, $aktif, CarbonImmutable $sekarang): array
    {
        $user = auth()->user();
        $kartu = [];

        $urut = $aktif->sortBy(fn (Penugasan $p) => [$p->status->belumSelesai() ? 0 : 1, $p->tenggat->getTimestamp()])->take(3);
        foreach ($urut as $p) {
            $kartu[] = $this->kartuPiket($p, $sekarang);
        }

        $jumlahBintang = $ringkas->jumlahBintangBelumLunas($user);
        if ($jumlahBintang > 0) {
            $menunggu = $ringkas->pengajuanSaya($user)->contains('status', StatusPengajuan::Menunggu);
            $kartu[] = [
                'ikon' => 'utang', 'latar' => '#FEE2E2', 'warna' => '#B91C1C',
                'kicker' => 'UTANG PIKET',
                'judul' => $jumlahBintang.' bintang belum lunas',
                'petunjuk' => $menunggu
                    ? 'Pengajuan ganti menunggu jawaban pengurus.'
                    : 'Bintang Kamis dan Minggu dijadwalkan ulang otomatis. Bintang harian diganti di hari kosong.',
                'aksi' => $menunggu ? null : ['label' => 'Lihat', 'rute' => 'warga.utang', 'utama' => false],
                'badge' => $menunggu ? ['kelas' => StatusPengajuan::Menunggu->badgeClass(), 'label' => 'Menunggu'] : null,
            ];
        }

        $hariIni = $ringkas->harianPada($sekarang->startOfDay());
        $besok = $ringkas->harianPada($sekarang->addDay()->startOfDay());
        $terbit = (bool) $ringkas->mingguIni()?->sudahTerbit('harian');
        $petunjuk = match (true) {
            ! $terbit => 'Belum ada jadwal harian untuk minggu ini.',
            $hariIni === null => 'Hari ini kosong, tidak ada petugas harian.',
            $hariIni->status === StatusPenugasan::Selesai => 'Sudah selesai'.($hariIni->selesai_pada ? ' jam '.$hariIni->selesai_pada->format('H.i') : '').'.',
            default => 'Dikerjakan jam 04.00–09.00.',
        };
        if ($terbit && $besok && ! $sekarang->isSunday()) {
            $petunjuk .= ' Besok giliran '.$besok->user->namaDepan().'.';
        }
        $kartu[] = [
            'ikon' => 'jadwal', 'latar' => '#F1E6CC', 'warna' => '#7A5B1E',
            'kicker' => 'PIKET HARIAN HARI INI',
            'judul' => $hariIni ? $hariIni->user->namaDepan().($hariIni->user->geng ? ' · '.$hariIni->user->geng->nama : '') : 'Tidak ada yang piket',
            'petunjuk' => $petunjuk,
            'aksi' => ['label' => 'Jadwal', 'rute' => 'warga.jadwal', 'utama' => false],
            'badge' => null,
        ];

        if ($aktif->isEmpty()) {
            array_unshift($kartu, [
                'ikon' => 'kilau', 'latar' => '#DCFCE7', 'warna' => '#166534',
                'kicker' => 'MINGGU INI',
                'judul' => 'Kamu bebas piket',
                'petunjuk' => 'Belum ada piket untukmu di jadwal yang sudah terbit.',
                'aksi' => null,
                'badge' => ['kelas' => 'badge badge-kosong', 'label' => 'Bebas'],
            ]);
        }

        return $kartu;
    }

    private function kartuPiket(Penugasan $p, CarbonImmutable $sekarang): array
    {
        $tanggal = $p->tanggalLokal();
        $kicker = match ($p->jenis) {
            JenisPenugasan::Harian => $tanggal->isSameDay($sekarang) ? 'PIKET HARIAN HARI INI' : 'PIKET HARIAN · '.$tanggal->translatedFormat('l, j M'),
            JenisPenugasan::Kamis => 'PIKET KAMIS, '.$tanggal->translatedFormat('j M'),
            JenisPenugasan::Minggu => 'PIKET MINGGU, '.$tanggal->translatedFormat('j M'),
            JenisPenugasan::KerjaBakti => 'KERJA BAKTI, '.$tanggal->translatedFormat('j M'),
        };

        [$latar, $warna] = match ($p->status) {
            StatusPenugasan::Selesai => ['#DCFCE7', '#166534'],
            StatusPenugasan::Bintang => ['#FEE2E2', '#B91C1C'],
            StatusPenugasan::BayarBintang => ['#FFE4CC', '#9A3412'],
            default => ['#DCEAF3', '#16587B'],
        };

        $bisaSekarang = $p->status->belumSelesai() && $p->sudahBisaDikerjakan($sekarang);
        $petunjuk = match (true) {
            $p->status === StatusPenugasan::Selesai => 'Sudah ditandai selesai. Terima kasih!',
            $p->status === StatusPenugasan::Bintang => 'Lewat batas waktu, jadi bintang.',
            $bisaSekarang => 'Batas '.Tenggat::label($p->tenggatLokal(), $sekarang),
            default => 'Dikerjakan '.$tanggal->translatedFormat('l, j M').'.',
        };
        if ($p->status === StatusPenugasan::BayarBintang && $p->membayar) {
            $petunjuk = 'Ganti bintang '.$p->membayar->tanggalLokal()->translatedFormat('j M').'. '.$petunjuk;
        }

        return [
            'ikon' => match ($p->jenis) {
                JenisPenugasan::Harian => 'jam',
                JenisPenugasan::Kamis => 'piket',
                JenisPenugasan::Minggu => 'matahari',
                JenisPenugasan::KerjaBakti => 'pengguna',
            },
            'latar' => $latar, 'warna' => $warna,
            'kicker' => Str::upper($kicker),
            'judul' => $p->jenis === JenisPenugasan::Harian ? 'Semua tugas harian' : $p->namaTugas(),
            'petunjuk' => $petunjuk,
            'aksi' => $bisaSekarang ? ['label' => 'Lihat', 'rute' => 'warga.piket', 'utama' => true] : null,
            'badge' => $bisaSekarang ? null : ['kelas' => $p->status->badgeClass(), 'label' => $p->status->label()],
        ];
    }
}
