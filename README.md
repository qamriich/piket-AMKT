# Piket AMKT Putri Mulawarman

Sistem penjadwalan piket asrama. Spesifikasi lengkap ada di [PRD (2).md](<PRD (2).md>).

## Menjalankan di lokal (XAMPP)

```bash
composer install
npm install && npm run build
cp .env.example .env && php artisan key:generate   # kalau .env belum ada
# Buat database `piket_amkt` di MySQL XAMPP, lalu:
php artisan migrate:fresh --seed                   # 15 warga asli (PRD bagian 13)
php artisan serve
php artisan queue:work                             # di terminal lain, supaya email terkirim ke log
```

Buka `http://localhost:8000` lalu login dengan email `<nama>@amkt.test`, misalnya `wilda@amkt.test`
(Tata Tertib) atau `ping@amkt.test` (warga). **Khusus lokal**, semua akun hasil seeder memakai password
sementara `piket123`. Di server sungguhan password dibiarkan kosong; pengurus mengirim email
"Buat password" dari halaman Warga & geng.

Setelah login, warga diarahkan ke Beranda warga dan pengurus ke panel pengurus (Pengawasan).

Email lokal tidak benar-benar dikirim (`MAIL_MAILER=log`). Isinya bisa dibaca di `storage/logs/laravel.log`
setelah antrean diproses (`php artisan queue:work`, atau `--stop-when-empty` untuk sekali jalan).

Tampilan memakai Livewire 3 (tanpa reload), Tailwind CSS 4, dan Alpine.js bawaan Livewire.
HP memakai 5 tab ikon; laptop (≥ 1024 px) memakai navbar lebar dan dashboard 2 kolom (PRD bagian 14).
Setelah mengubah tampilan, jalankan `npm run build` (atau `npm run dev` selama mengembangkan).

| Bagian | Alamat |
| --- | --- |
| Login | `/login`, `/lupa-password`, link email `/password/buat/{token}` dan `/password/reset/{token}` |
| Web warga (5 tab) | `/dashboard`, `/piket`, `/utang`, `/jadwal`, `/kuliah`, dan `/profil` |
| Panel pengurus | `/pengurus` (pengawasan), `/pengurus/jadwal`, `/pengurus/pengajuan`, `/pengurus/warga`, `/pengurus/rekap`, `/pengurus/pengaturan`, `/pengurus/tugas` |

## Perintah artisan

| Perintah | Fungsi |
| --- | --- |
| `php artisan app:create-admin` | Membuat akun Tata Tertib pertama dan menampilkan link "Buat password" (`--kirim` untuk mengirim email) |

## Test

```bash
php artisan test
```

Test memakai SQLite in-memory.
