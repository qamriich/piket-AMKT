# Panduan deploy Piket AMKT Putri

Panduan ini belum terikat ke hosting tertentu. Hosting dipilih nanti; bagian di bawah menjelaskan apa yang dibutuhkan aplikasi dan langkah yang sama di semua hosting.

## 1. Kebutuhan server

- PHP 8.2 atau lebih baru, dengan ekstensi: `pdo_mysql`, `mbstring`, `openssl`, `ctype`, `fileinfo`, `xml`, `dom`, `zip`, dan **`gd`** (wajib untuk ekspor Excel).
- MySQL 8 atau MariaDB 10.4+.
- Composer 2. Node.js 20+ hanya perlu saat build tampilan (`npm run build`); hasilnya bisa dibuat di laptop lalu diunggah.
- Akun SMTP untuk email (Gmail dengan App Password, Brevo, atau Resend).
- HTTPS (biasanya otomatis dari hosting atau Let's Encrypt).

Cek ekstensi di server dengan `php -m`.

## 2. Langkah pemasangan (sama di semua hosting)

Jalankan di folder aplikasi di server:

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build            # atau unggah folder public/build hasil build di laptop

cp .env.production.example .env     # lalu isi bagian <...>
php artisan key:generate            # sekali saja, saat pertama pasang

php artisan migrate --force
php artisan db:seed --force         # sekali saja: geng, master tugas, pengaturan, 15 warga (tanpa password)
php artisan app:create-admin --nama="..." --email="..." --geng=1
                                    # akun pengurus pertama; password awal dicetak di layar dan wajib diganti saat login

php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Catatan:

- Web root (document root) harus mengarah ke folder `public/`, bukan ke folder aplikasi.
- Folder `storage/` dan `bootstrap/cache/` harus bisa ditulis oleh PHP.
- **Jangan pernah** menjalankan `migrate:fresh` atau `app:data-contoh` di server: `migrate:fresh` menghapus semua data, dan `app:data-contoh` sudah menolak berjalan di luar lokal.
- Seeder warga memakai email sementara `<nama>@amkt.test` dan tidak memberi password di produksi. Pengurus mengganti email lalu memakai "Reset password" di halaman Warga & geng.
- Setiap kali ada update kode: `composer install --no-dev`, `npm run build`, `php artisan migrate --force`, lalu ulangi tiga perintah `...:cache`.

## 3. Tugas berkala dan email (pilih salah satu)

Aplikasi perlu menjalankan `piket:jalankan` secara berkala (bintang otomatis, email piket harian, pengingat) dan mengirim email yang antre di tabel `jobs`. Walaupun tugas berkala terlambat, status bintang tetap benar saat halaman dibuka (B4), tetapi pengingat dan email butuh salah satu cara di bawah.

### Cara A: hosting punya cron (cPanel, VPS)

Tambahkan dua baris cron, masing-masing tiap menit (ganti path):

```cron
* * * * * cd /path/ke/aplikasi && php artisan schedule:run >> /dev/null 2>&1
* * * * * cd /path/ke/aplikasi && php artisan queue:work --stop-when-empty --max-time=50 --tries=3 >> /dev/null 2>&1
```

`schedule:run` menjalankan `piket:jalankan` tiap 15 menit (diatur di `routes/console.php`). `CRON_SECRET` boleh dikosongkan; route `/cron/tick` lalu mengembalikan 404.

### Cara B: hosting tanpa cron (serverless atau paket gratis)

1. Isi `CRON_SECRET` di `.env` dengan string acak panjang, lalu `php artisan config:cache`.
2. Di layanan cron eksternal gratis (misalnya cron-job.org), buat job **tiap 15 menit**:
   `GET https://<domain-anda>/cron/tick?key=<CRON_SECRET>`
3. Tiap panggilan menjalankan `piket:jalankan` lalu mengirim antrean email sampai kosong (paling lama `CRON_MAKS_DETIK` detik, default 40). Balasannya JSON, misalnya
   `{"ok":true,"piket":"Bintang baru: 0. Email harian: 0. Pengingat: 0."}`.

Keamanan: tanpa secret atau dengan secret salah, route membalas 404. Route ini tidak membuat sesi atau cookie, dan dibatasi 10 panggilan per menit. Jangan bagikan URL lengkap berisi secret.

## 4. Pilihan hosting

| Pilihan | Cocok untuk | Catatan |
| --- | --- | --- |
| Hosting PHP biasa (cPanel) | Paling sederhana | PHP, MySQL, dan cron sudah ada. Pakai cara A. Pastikan ekstensi `gd` aktif. |
| VPS | Kendali penuh | Pasang Nginx/Apache, PHP-FPM, MySQL sendiri. Pakai cara A. |
| Railway / Render | Tanpa urus server | Butuh Dockerfile atau buildpack PHP, dan database MySQL terpisah. Cron: pakai cron bawaan platform (cara A) atau cara B. |
| Vercel | Gratis | **Hati-hati**: runtime komunitas `vercel-php` yang dipakai PRD bagian 9 sudah diarsipkan pada 29 Okt 2026, dan Vercel kini menyarankan runtime container (Docker). Daftar ekstensi bawaan `vercel-php` juga tidak mencantumkan `gd`, padahal ekspor Excel membutuhkannya. Kalau tetap memilih Vercel, rencanakan memakai container, database terpisah (Aiven, TiDB Cloud, dll.), dan cara B. |

## 4a. Deploy yang dipakai: Vercel + TiDB Cloud Serverless

File yang sudah disiapkan:

- `vercel.json`: runtime `vercel-php@0.7.4` (PHP 8.3) untuk `api/index.php`, build Vite (`npm run build`) dengan output `public/`, file di `/build`, `/images`, `favicon.ico`, dan `robots.txt` disajikan statis, sisanya ke Laravel.
- `api/index.php`: memindah `storage` ke `/tmp/storage` dan cache bootstrap (config, route, package, service, event) ke `/tmp`.
- `composer.json` → `config.platform.ext-gd`: `vercel-php` tidak punya `gd`, padahal `phpspreadsheet` mencantumkannya sebagai syarat. Ekspor rekap tidak memanggil `gd` (tanpa gambar, autosize memakai perkiraan), jadi syarat itu dipalsukan supaya `composer install` di Vercel tidak gagal.
- `resources/certs/cacert.pem` + `MYSQL_ATTR_SSL_CA=resources/certs/cacert.pem`: TiDB Cloud wajib TLS. Path relatif dihitung dari folder aplikasi (lihat `config/database.php`).
- `bootstrap/app.php`: `trustProxies(at: '*')` supaya URL dan cookie memakai https di belakang proxy Vercel.

Variabel produksi disimpan di `.env.production` (tidak di-commit, tidak di-upload) lalu dimasukkan ke Vercel lewat `npx vercel env add`. Tambahan khusus Vercel: `LOG_CHANNEL=stderr`, `DB_PORT=4000`. Migration dan seeder dijalankan dari laptop ke TiDB. Tugas berkala memakai cara B (cron-job.org).

Catatan: `vercel-php` diarsipkan pada 29 Okt 2026. Kalau suatu saat runtime ini berhenti bisa dipakai, pindah ke runtime container Vercel atau ke hosting lain di tabel atas.

## 5. Cek setelah deploy

- [ ] `https://<domain-anda>/up` membalas 200.
- [ ] Halaman login terbuka lewat HTTPS, dan pengurus bisa login lalu mengganti password awal.
- [ ] Email terkirim: dari halaman Warga & geng, kirim "Reset password" ke akun sendiri.
- [ ] Tugas berkala berjalan: cara A, lihat tabel `jobs` kosong setelah beberapa menit; cara B, buka URL tick dan pastikan balasannya `"ok":true`.
- [ ] Rekap bulanan bisa diekspor ke Excel dan CSV.
- [ ] `APP_DEBUG=false` (halaman error tidak menampilkan detail kode).
