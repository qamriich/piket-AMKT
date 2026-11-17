# Piket AMKT Putri Mulawarman

Sistem penjadwalan piket asrama. Spesifikasi lengkap ada di [PRD (3).md](<PRD (3).md>).

## Menjalankan di lokal (XAMPP)

```bash
composer install
npm install && npm run build
cp .env.example .env && php artisan key:generate   # kalau .env belum ada
# Buat database `piket_amkt` di MySQL XAMPP, lalu:
php artisan migrate:fresh --seed                   # 15 warga asli (PRD bagian 13)
php artisan serve
php artisan queue:work                             # di terminal lain, supaya email terkirim ke log
php artisan schedule:work                          # di terminal lain: bintang otomatis + pengingat tiap 15 menit
```

Buka `http://localhost:8000` lalu login dengan email `<nama>@amkt.test`, misalnya `wilda@amkt.test`
(Tata Tertib) atau `ping@amkt.test` (warga). **Khusus lokal**, semua akun hasil seeder memakai password
awal `piket123`, yang **wajib diganti saat login pertama** (PRD bagian 15 R2). Di server sungguhan password
dibiarkan kosong; pengurus mengisinya lewat "Reset password" di halaman Warga & geng.

Setelah login (dan mengganti password awal), warga diarahkan ke Beranda warga dan pengurus ke panel
pengurus (Pengawasan).

Piket harian disusun sekali per periode di **Atur jadwal → Jadwal harian periode** (R1). Setiap minggu,
piket harian geng yang bertugas dibuat otomatis tanpa perlu diterbitkan; yang diterbitkan tiap minggu
hanya piket Kamis dan Minggu.

Email lokal tidak benar-benar dikirim (`MAIL_MAILER=log`). Isinya bisa dibaca di `storage/logs/laravel.log`
setelah antrean diproses (`php artisan queue:work`, atau `--stop-when-empty` untuk sekali jalan).

**Pelaksanaan (Tahap 3).** Warga menekan "Saya sudah selesai" di Piket saya atau Beranda (kecuali yang wajib
diperiksa: pengurus yang menandai di Pengawasan). Setelah batas waktu, piket yang belum selesai otomatis jadi
Bintang (scheduler + pengecekan ringan setiap halaman dibuka); pengurus mengubahnya lewat **Koreksi** dengan
catatan wajib. Bintang harian diganti lewat tombol "Ajukan ganti" di menu Utang (hari kosong sampai 8 minggu ke
depan, dihitung dari jadwal periode), Izin (tanggal + jam) diajukan dari Piket saya, dan keduanya diputuskan di
menu Pengajuan. Piket hanya diganti kalau izin menutupi seluruh waktu pengerjaannya (R3a). Semua email (jadwal terbit, perubahan, pengingat, bintang, pengajuan, izin)
lewat antrean.

Tampilan memakai Livewire 3 (tanpa reload), Tailwind CSS 4, dan Alpine.js bawaan Livewire.
HP memakai 5 tab ikon; laptop (≥ 1024 px) memakai navbar lebar dan dashboard 2 kolom (PRD bagian 14).
Setelah mengubah tampilan, jalankan `npm run build` (atau `npm run dev` selama mengembangkan).

| Bagian | Alamat |
| --- | --- |
| Login | `/login`, `/lupa-password`, `/password/baru` (wajib ganti password awal), link email `/password/reset/{token}` |
| Web warga (5 tab) | `/dashboard`, `/piket`, `/utang`, `/jadwal`, `/kuliah`, dan `/profil` |
| Panel pengurus | `/pengurus` (pengawasan), `/pengurus/jadwal`, `/pengurus/jadwal-harian`, `/pengurus/pengajuan`, `/pengurus/warga`, `/pengurus/rekap`, `/pengurus/pengaturan`, `/pengurus/tugas` |

## Perintah artisan

| Perintah | Fungsi |
| --- | --- |
| `php artisan app:create-admin` | Membuat akun Tata Tertib pertama dengan password awal (`--password=`, kalau kosong dibuat acak) |
| `php artisan piket:jalankan` | Bintang otomatis (B4), email piket harian minggu ini, dan pengingat. Dijadwalkan tiap 15 menit |

## Test

```bash
php artisan test
```

Test memakai SQLite in-memory.
