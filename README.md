# Piket AMKT Putri Mulawarman

Sistem penjadwalan piket asrama. Spesifikasi lengkap ada di [PRD.md](PRD.md).

## Menjalankan di lokal (XAMPP)

```bash
composer install
npm install && npm run build
cp .env.example .env && php artisan key:generate   # kalau .env belum ada
# Buat database `piket_amkt` di MySQL XAMPP, lalu:
php artisan migrate:fresh --seed                   # menampilkan link pribadi data contoh
php artisan serve
```

Buka salah satu link `http://localhost:8000/m/...` yang tampil di output seeder.

Tampilan memakai Livewire 3 (tanpa reload), Tailwind CSS 4, dan Alpine.js bawaan Livewire.
Setelah mengubah tampilan, jalankan `npm run build` (atau `npm run dev` selama mengembangkan).

| Bagian | Alamat |
| --- | --- |
| Web warga (5 tab) | `/dashboard`, `/piket`, `/utang`, `/jadwal`, `/kuliah`, dan `/profil` |
| Panel pengurus | `/pengurus` (pengawasan), `/pengurus/jadwal`, `/pengurus/pengajuan`, `/pengurus/warga`, `/pengurus/rekap`, `/pengurus/pengaturan`, `/pengurus/tugas` |
Rina Anggraini adalah Tata Tertib. Saat pertama membuka menu pengurus, kamu diminta membuat PIN 6 digit.

Email lokal tidak benar-benar dikirim (`MAIL_MAILER=log`). Isinya bisa dilihat di `storage/logs/laravel.log`
setelah menjalankan `php artisan queue:work --stop-when-empty`.

## Perintah artisan

| Perintah | Fungsi |
| --- | --- |
| `php artisan app:create-admin` | Membuat akun Tata Tertib pertama dan menampilkan link pribadinya (`--kirim` untuk mengirim email) |
| `php artisan app:reset-pin {email}` | Menghapus PIN pengurus yang lupa PIN |

## Test

```bash
php artisan test
```

Test memakai SQLite in-memory.
