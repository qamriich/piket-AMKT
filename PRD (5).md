# PRD Sistem Penjadwalan Piket AMKT Putri Mulawarman Makassar

Versi 16 November 2026

## Cara pakai dokumen ini

Simpan file ini sebagai `PRD.md` di folder utama project. Di Claude Code, awali percakapan dengan:

> Baca @PRD.md. Kamu adalah developer Laravel senior. Bangun aplikasi web sesuai PRD tersebut. Kerjakan bertahap sesuai bagian "Tahapan pengerjaan": mulai dari migrasi database dan seeder, lalu model, logika penjadwalan, halaman, dan notifikasi email. Jangan mengubah aturan bisnis tanpa bertanya. Kalau ada yang ambigu, tanyakan dulu sebelum menulis kode. Sekarang kerjakan Tahap 1 saja.

Untuk sesi lanjutan, cukup tulis misalnya "Baca @PRD.md, lanjut Tahap 2: penjadwalan".

## 1. Ringkasan produk

Aplikasi web berbasis Laravel untuk menyusun, membagikan, dan memantau jadwal piket di Asrama Mahasiswa Kalimantan Timur (AMKT) Putri Mulawarman Makassar. Saat ini pembagian piket dan pencatatan utang piket (bintang) dilakukan manual. Akibatnya, jadwal sering bentrok, ada tugas ganda, dan rekap sulit dibuat.

**Tujuan**

- Pengurus cukup memilih tugas yang aktif, lalu sistem membagi petugasnya secara otomatis dan bergilir.
- Setiap warga bisa login dengan email, melihat jadwalnya sendiri, dan mendapat email setiap kali jadwal diterbitkan.
- Utang piket (bintang) tercatat otomatis dan dijadwalkan ulang tanpa membuat tugas ganda.
- Pengurus bisa melihat rekap bulanan per warga.

**Termasuk dalam lingkup:** manajemen warga dan geng, rotasi geng mingguan, piket Harian, Kamis, dan Minggu (termasuk kerja bakti), toggle tugas, penugasan otomatis, tandai selesai, pemeriksaan, catatan pengurus, bintang otomatis, pengajuan ganti bintang harian, notifikasi email, dan rekap bulanan.

**Tidak termasuk:** sanksi selain bintang, aplikasi mobile native, dan pembayaran atau iuran.

## 2. Pengguna, peran, dan akses

Semua orang masuk lewat **halaman login dengan email dan password**, seperti web kampus. Akun dibuat oleh pengurus: pengurus mendaftarkan email warga dan menentukan role-nya. Sistem mengenali siapa yang login dari emailnya, lalu mengarahkan sesuai role.

| Peran | Siapa | Hak akses |
| --- | --- | --- |
| Warga | Semua penghuni asrama | Melihat dashboard dan jadwal sendiri, menandai piket sendiri selesai (kecuali warga yang wajib diperiksa), mengajukan ganti bintang harian, melihat riwayat dan bintang sendiri |
| Tata Tertib | Pengurus | Semua hak warga, ditambah: kelola warga dan geng, atur jadwal harian, aktifkan tugas Kamis/Minggu, terbitkan jadwal, periksa dan tandai piket orang lain, beri catatan, setujui atau tolak pengajuan ganti bintang |
| Ketua | Pengurus | Sama dengan Tata Tertib. Fokus utamanya memantau jalannya piket lewat dashboard pengawasan dan rekap |
| Bendahara, Sekretaris | Pengurus | Sama dengan Tata Tertib. Biasanya menjadi pemeriksa pengganti |

Pengurus juga anggota geng dan ikut piket, jadi pengurus punya dashboard warga sekaligus menu pengurus.

**Mekanisme login (revisi 17 Nov 2026, menggantikan link pribadi dan PIN)**

1. Pengurus menambah warga di halaman Warga & geng: nama, email, kamar, geng, role, dan wajib diperiksa. Tidak ada pendaftaran mandiri.
2. Saat membuat akun, pengurus mengisi password awal. Saat login pertama, warga wajib mengganti password sebelum bisa membuka halaman lain. Password bisa diganti kapan saja dari halaman Profil.
3. Halaman login berisi email, password, dan centang "Ingat saya" (sesi bertahan 30 hari). Ada link "Lupa password" yang mengirim email reset.
4. Setelah login, role warga diarahkan ke Beranda warga, sedangkan role pengurus (ketua, sekretaris, bendahara, tata_tertib) diarahkan ke panel pengurus (Pengawasan). Pengurus tetap bisa membuka tampilan warga lewat menu "Tampilan warga", dan di tampilan warga milik pengurus ada tombol "Panel pengurus" di header.
5. Pengurus bisa menekan "Kirim ulang email buat password" untuk warga yang belum pernah login atau lupa.
6. Akun pengurus pertama (Tata Tertib) dibuat lewat seeder atau perintah `php artisan app:create-admin`.
7. Login dibatasi 5 percobaan gagal per menit. Warga yang dinonaktifkan tidak bisa login.

**Penanda warga wajib diperiksa:** kolom `wajib_diperiksa` (boolean) di akun warga, diubah oleh pengurus. Kolom ini tidak tampil sebagai label khusus bagi warga.

## 3. Konsep inti

**Minggu piket** dihitung Senin sampai Minggu. Ada 3 geng yang bergiliran mengerjakan piket harian: Geng 1, Geng 2, Geng 3, lalu kembali ke Geng 1, tanpa terikat bulan. Pengurus menentukan satu minggu acuan (misalnya "minggu 16 Nov 2026 = Geng 1"), lalu sistem menghitung giliran minggu-minggu berikutnya.

| Jenis piket | Dikerjakan oleh | Waktu pengerjaan | Tenggat | Cara pembagian |
| --- | --- | --- | --- | --- |
| Harian | Geng yang bertugas minggu itu, 1 orang per hari | Setiap hari, jam 04.00–09.00 | Hari itu jam 09.00 | Satu orang mengerjakan semua tugas harian pada harinya. Ada hari yang boleh kosong |
| Kamis | 2 geng yang tidak sedang piket harian | Kamis | Jumat jam 18.00 | 1 tugas untuk 1 orang, ditentukan sistem secara bergilir |
| Minggu (tugas kecil) | Semua geng | Minggu | Senin jam 18.00 | 1 tugas untuk 1 orang, ditentukan sistem secara bergilir |
| Minggu (kerja bakti) | Semua warga | Minggu | Senin jam 18.00 | Tidak ada pembagian. Setiap warga punya satu entri "Kerja bakti" |

**Daftar tugas bawaan** (pengurus bisa menambah, mengubah, dan menghapusnya):

- **Harian:** membersihkan dapur (sapu dan pel), ruang tamu (sapu dan pel), pelataran dalam dan luar asrama (sapu dan pel), garasi (sapu dan pel), merapikan sepatu di garasi, menyapu halaman depan, aula (sapu dan pel), membuang sampah dan mencuci tong sampah, serta memasak nasi.
- **Kamis dan Minggu:** WC 1, WC 2, WC 3, WC 4, pelataran depan WC dan cuci baskom, aula depan, aula belakang, halaman 1, halaman 2, halaman 3, jendela kamar, garasi, serta lemari dapur. Setiap tugas dikerjakan oleh 1 orang. Kamis dan Minggu memakai daftar yang sama, tapi toggle aktifnya diatur terpisah setiap minggu.

## 4. Kebutuhan fungsional

**F1. Kelola warga** (pengurus)

- Tambah, ubah, dan nonaktifkan warga. Data yang diisi: nama, email, nomor kamar, geng, role (warga, ketua, sekretaris, bendahara, tata_tertib), dan `wajib_diperiksa`.
- Kirim ulang email buat password atau reset password.
- Warga yang nonaktif (misalnya sudah keluar asrama) tidak ikut penjadwalan, tapi riwayatnya tetap tersimpan.

**F2. Kelola geng dan rotasi** (pengurus)

- Memindahkan warga antar geng. Jumlah anggota setiap geng boleh berbeda.
- Mengatur minggu acuan rotasi dan melihat pratinjau giliran 8 minggu ke depan.

**F3. Jadwal kuliah warga**

- Warga atau pengurus mengisi jadwal kuliah per hari: hari, jam mulai, dan jam selesai.
- Jadwal ini dipakai untuk menyarankan pembagian hari piket harian (aturan B2).

**F4. Jadwal piket harian** (pengurus)

- Untuk setiap minggu, sistem membuat draf pembagian hari bagi anggota geng yang bertugas berdasarkan jadwal kuliah.
- Pengurus bisa mengubah draf itu (geser orang ke hari lain, atau kosongkan hari tertentu), lalu menerbitkannya.

**F5. Jadwal Kamis dan Minggu** (pengurus)

- Setiap minggu, pengurus membuka halaman "Atur Kamis" dan "Atur Minggu" yang berisi daftar tugas dengan toggle aktif atau nonaktif. Defaultnya mengikuti minggu sebelumnya.
- Khusus Minggu, ada pilihan mode: **Tugas kecil** atau **Kerja bakti**.
- Tombol "Buat pembagian" memanggil penugasan otomatis (aturan B3). Pengurus melihat pratinjau, bisa menukar petugas secara manual, lalu menekan "Terbitkan".

**F6. Tandai selesai**

- Warga biasa menandai piketnya sendiri sebagai selesai.
- Warga yang `wajib_diperiksa` tidak punya tombol selesai. Piketnya hanya bisa ditandai selesai oleh pengurus, setelah diperiksa tepat saat piket itu selesai dikerjakan.
- Pengurus bisa menandai selesai piket siapa pun dan membatalkan tanda selesai.
- Pengurus bisa menulis catatan di setiap piket, dan catatan itu terlihat oleh petugasnya.
- Tanda selesai yang diberikan setelah tenggat lewat tetap tercatat sebagai bintang.

**F7. Bintang** (aturan B4 dan B5)

- Bintang diberikan otomatis setelah tenggat lewat dan piket belum selesai.
- Bintang Kamis dan Minggu dijadwalkan ulang secara otomatis.
- Bintang harian dibayar lewat pengajuan ganti bintang.

**F8. Pengajuan ganti bintang harian**

- Warga memilih salah satu bintang hariannya, lalu memilih tanggal kosong (hari yang tidak ada petugas harian, di minggu mana pun ke depan). Sistem hanya menampilkan tanggal yang benar-benar kosong.
- Pengurus menyetujui atau menolak pengajuan, dengan catatan opsional. Warga mendapat email tentang hasilnya.
- Kalau disetujui, tanggal itu terisi oleh warga tersebut dengan status Bayar Bintang dan tidak lagi tersedia untuk pengajuan lain.

**F9. Dashboard pengawasan** (pengurus, terutama Ketua)

- Menampilkan piket hari ini dan minggu ini beserta statusnya, serta filter per jenis piket, geng, dan status.

**F10. Rekap bulanan**

- Menampilkan tabel per warga untuk satu bulan: jumlah piket terjadwal, jumlah yang selesai, jumlah bintang baru, jumlah bintang yang sudah lunas, dan jumlah bintang yang masih utang.
- Rekap bisa difilter per jenis piket dan diekspor ke Excel atau CSV.

## 5. Aturan bisnis

Semua waktu memakai zona `Asia/Makassar` (WITA). Aturan B3 dan B4 adalah inti sistem dan wajib punya unit test.

**B1. Rotasi geng**

`geng_harian(minggu) = ((geng_acuan - 1 + selisih_minggu_dari_acuan) mod 3) + 1`. Geng lainnya menjadi pool piket Kamis minggu itu.

**B2. Saran pembagian hari piket harian**

1. Kandidatnya adalah anggota aktif geng yang bertugas.
2. Seorang warga dianggap *bisa* pada hari D kalau kuliah pertamanya pada hari itu dimulai pada atau setelah `batas_jam_kuliah` (default 09.00, karena kuliah jam 08.00 tidak sempat setelah piket, bisa diubah di pengaturan), atau kalau tidak ada kuliah pada hari itu.
3. Setiap warga mendapat maksimal 1 hari per minggu. Warga dengan jumlah hari *bisa* paling sedikit diisi lebih dulu. Kalau ada beberapa pilihan, utamakan hari yang berbeda dari giliran sebelumnya.
4. Hari yang tidak terisi tetap **kosong**, dan hari kosong inilah yang bisa dipakai untuk bayar bintang harian.
5. Kalau ada warga yang tidak bisa di hari mana pun, sistem menampilkan peringatan dan pengurus menentukannya secara manual. Hasil dari B2 selalu berupa draf yang bisa diubah pengurus.

**B3. Penugasan otomatis Kamis dan Minggu (mode tugas kecil)**

Inputnya adalah daftar tugas aktif dan pool petugas. Pool Kamis adalah anggota aktif 2 geng non-harian. Pool Minggu adalah semua warga aktif.

1. **Bintang dulu.** Untuk setiap bintang yang belum lunas dengan jenis yang sama (bintang Kamis untuk Kamis, bintang Minggu untuk Minggu), diurutkan dari yang paling lama: kalau tugasnya aktif minggu ini dan pemilik bintang ada di pool, tugas itu dikunci untuk pemiliknya dengan status **Bayar Bintang**. Kalau tidak memenuhi syarat itu (tugasnya nonaktif, Minggu sedang kerja bakti, pemiliknya sedang di geng harian, atau tugas itu sudah dikunci bintang lain), bintangnya ditunda ke minggu berikutnya.
2. **Tugas reguler.** Tugas aktif yang belum dikunci dibagikan ke pool. Setiap orang mendapat maksimal 1 tugas reguler, termasuk pemilik bintang, sehingga pemilik bintang mengerjakan 2 tugas. Untuk setiap tugas, pilih orang yang paling lama tidak mengerjakan tugas itu. Kalau seri, pilih yang paling lama tidak mendapat tugas sama sekali, lalu acak.
3. Karena tugas yang dikunci bintang tidak ikut dibagikan, **tidak akan ada dua orang untuk satu tugas**. Orang yang "tergeser" (misalnya Nivia, yang biasanya dapat pelataran) otomatis mendapat tugas lain di langkah 2.
4. Kalau tugas lebih banyak dari orang, tugas sisanya ditandai "Belum ada petugas" dan pengurus mengisinya secara manual. Kalau orang lebih banyak dari tugas, sebagian orang bebas minggu itu, dan pada minggu berikutnya mereka diprioritaskan mendapat tugas.

**B3a. Mode kerja bakti (Minggu):** setiap warga aktif mendapat satu entri "Kerja bakti" tanpa pembagian tugas. Warga yang tidak menyelesaikan kerja bakti mendapat bintang Minggu. Bintang ini dibayar di Minggu berikutnya dengan 1 tugas kecil tambahan berstatus Bayar Bintang, diambil dari tugas aktif yang belum dikunci. Kalau Minggu berikutnya kerja bakti lagi, bintang itu dan bintang Minggu lain yang belum lunas ditunda ke Minggu berikutnya yang memakai mode tugas kecil.

**B4. Bintang otomatis**

- Setiap piket dengan status Terjadwal atau Bayar Bintang yang belum selesai saat tenggat lewat akan diubah sistem menjadi **Bintang**.
- Tenggatnya: Harian jam 09.00 di hari itu, Kamis hari Jumat jam 18.00, dan Minggu hari Senin jam 18.00.
- Pengecekan berjalan lewat scheduler (lihat bagian 9). Selain itu, ada pengecekan ringan setiap kali halaman dibuka supaya status tetap akurat meskipun scheduler terlambat.
- Izin pulang diatur lewat fitur Izin pulang (bagian 15). Pengurus juga bisa mengoreksi status setelah tenggat, misalnya Bintang menjadi Selesai kalau piket sebenarnya sudah dikerjakan, dengan catatan wajib.

**B5. Pelunasan bintang**

- Saat piket Bayar Bintang ditandai selesai, piket aslinya yang berstatus Bintang berubah menjadi **Lunas**.
- Kalau piket Bayar Bintang terlewat, utangnya tetap 1 (tidak bertambah). Piket aslinya tetap berstatus Bintang dan dijadwalkan ulang di minggu berikutnya.
- Bintang harian hanya bisa dilunasi lewat pengajuan ganti bintang (F8) di tanggal kosong.

**B6. Status piket**

Bintang hanya muncul sebagai status berupa badge warna, tanpa halaman atau ikon khusus.

| Status | Arti | Diubah oleh |
| --- | --- | --- |
| Terjadwal | Piket reguler yang belum dikerjakan | Sistem, saat jadwal diterbitkan |
| Bayar Bintang | Piket pengganti untuk melunasi bintang | Sistem (B3) atau persetujuan pengajuan (F8) |
| Selesai | Piket sudah dikerjakan | Petugas sendiri atau pengurus |
| Bintang | Lewat tenggat dan belum selesai, jadi utang | Sistem (B4) |
| Lunas | Bintang yang sudah dibayar | Sistem (B5) |

**B7. Pemeriksaan:** semua pengurus bisa memeriksa. Urutan prioritas pemeriksa (Tata Tertib, Bendahara, Sekretaris, lalu Ketua) hanya ditampilkan sebagai keterangan, tidak dipaksakan sistem. Sistem mencatat siapa yang menandai selesai dan kapan.

**B8. Perubahan setelah terbit:** kalau pengurus mengubah petugas di jadwal yang sudah terbit, hanya warga yang terdampak yang mendapat email pembaruan.

## 6. Model data

Ada 9 tabel inti, ditambah tabel bawaan Laravel (`sessions`, `jobs`, `cache`).

| Tabel | Kolom penting | Keterangan |
| --- | --- | --- |
| `users` | id, nama, email (unik), no_kamar, geng_id, role (enum: warga, ketua, sekretaris, bendahara, tata_tertib), wajib_diperiksa (bool), aktif (bool), password (hash), email_verified_at, remember_token | Warga dan pengurus ada di satu tabel |
| `gengs` | id, nama (Geng 1–3), urutan | Isinya hanya 3 baris |
| `jadwal_kuliah` | id, user_id, hari (1–7), jam_mulai, jam_selesai | Dipakai oleh B2 |
| `tugas` | id, nama, jenis (harian / kamis_minggu), urutan, aktif_default (bool) | Master daftar tugas |
| `minggu_piket` | id, tanggal_senin (unik), geng_harian_id, mode_minggu (tugas_kecil / kerja_bakti), status_harian, status_kamis, status_minggu (draf / terbit) | Satu baris per minggu |
| `tugas_aktif` | id, minggu_piket_id, tugas_id, sesi (kamis / minggu) | Hasil toggle |
| `penugasan` | id, minggu_piket_id, user_id, tugas_id (nullable), jenis (harian / kamis / minggu / kerja_bakti), tanggal, tenggat (datetime), status (enum B6), membayar_penugasan_id (nullable, FK ke penugasan), selesai_oleh_id, selesai_pada, catatan | Inti sistem. Baris Bayar Bintang menunjuk ke baris Bintang yang dilunasinya |
| `pengajuan_ganti_bintang` | id, penugasan_bintang_id, user_id, tanggal_diajukan, status (menunggu / diterima / ditolak), diputuskan_oleh_id, catatan | Untuk F8 |
| `pengaturan` | key, value | Berisi minggu_acuan, geng_acuan, batas_jam_kuliah, dan lainnya |

Aturan integritas data:

- Kombinasi (minggu_piket_id, tugas_id, jenis) bersifat unik untuk Kamis dan Minggu, sehingga satu tugas tidak bisa punya dua petugas.
- Satu tanggal harian hanya boleh punya satu penugasan.
- Bintang yang belum lunas adalah baris `penugasan` berstatus Bintang yang belum ditunjuk oleh baris Bayar Bintang berstatus Selesai.

## 7. Halaman dan UI

Tampilan dirancang mobile-first karena kebanyakan warga membuka lewat HP. Gunakan Blade, Tailwind CSS, dan Alpine.js. Setiap status punya badge warna: Terjadwal abu-abu, Bayar Bintang oranye, Selesai hijau, Bintang merah, dan Lunas biru.

| Halaman | Untuk | Isi utama |
| --- | --- | --- |
| Dashboard saya | Semua | Kartu "Piket saya minggu ini" (harian, Kamis, Minggu) dengan status, tenggat, catatan pengurus, dan tombol Selesai. Di bawahnya ada geng yang bertugas minggu ini dan jumlah bintang saya yang belum lunas |
| Jadwal minggu ini | Semua | Tiga tab (Harian, Kamis, Minggu) yang berisi daftar tugas, petugas, dan status. Warga bisa melihat jadwal semua orang |
| Riwayat saya | Semua | Daftar piket per bulan beserta statusnya, dan tombol "Ajukan ganti" pada bintang harian |
| Jadwal kuliah saya | Semua | Form untuk mengisi jadwal kuliah per hari |
| Pengawasan | Pengurus | Daftar piket hari ini dan minggu ini, filter, tombol tandai selesai atau batalkan, dan kolom catatan. Warga yang wajib diperiksa diurutkan di atas |
| Atur jadwal | Pengurus | Pilih minggu, lalu tiga tab: Harian (draf B2 yang bisa diubah), Kamis (toggle tugas), dan Minggu (pilih mode dan toggle tugas). Ada tombol Buat pembagian, pratinjau, tukar petugas, dan Terbitkan |
| Pengajuan | Pengurus | Daftar pengajuan ganti bintang dengan tombol Terima atau Tolak |
| Warga & geng | Pengurus | CRUD warga, pindah geng, ubah role, atur wajib diperiksa, dan kirim ulang email buat password |
| Master tugas | Pengurus | CRUD daftar tugas |
| Rekap bulanan | Pengurus | Tabel per warga dan tombol ekspor |
| Pengaturan | Pengurus | Minggu acuan, geng acuan, dan batas jam kuliah |

### 7a. Arah desain dan kemudahan pakai (revisi 17 Nov 2026)

Prioritas utamanya: warga yang tidak melek teknologi bisa memakai web ini **tanpa diajari**. Referensi gaya visual yang disukai pengurus: https://www.ruanglab.com/ dan https://kalam.umi.ac.id. Gayanya modern, rapi, dengan hero section, angka statistik besar, kartu-kartu, dan animasi halus.

**Satu halaman untuk warga.** Warga tidak perlu berpindah-pindah menu. Semua yang dibutuhkan warga ada di satu halaman yang di-scroll:

1. **Sapaan dan hero:** "Halo, Nivia", nama geng yang bertugas minggu ini, dan hitung mundur ke tenggat piket terdekat.
2. **Kartu piket saya:** kartu besar berwarna sesuai status, dengan satu tombol besar "Saya sudah selesai". Setelah ditekan, muncul animasi centang dan kartunya langsung berubah hijau tanpa reload halaman.
3. **Angka ringkas:** total piket selesai bulan ini, jumlah bintang yang belum lunas, dan hari kosong yang tersedia. Angkanya tampil dengan animasi hitung naik.
4. **Jadwal minggu ini:** tab Harian, Kamis, dan Minggu dalam satu blok, dengan nama sendiri ditandai. Tab bisa diganti tanpa reload.
5. **Bintang dan pengajuan:** kalau punya bintang harian, ada tombol "Pilih hari ganti" yang membuka kalender kecil berisi hari kosong saja.
6. **Jadwal kuliah saya:** bisa dilipat (accordion) supaya tidak memenuhi layar.

**Menu pengurus** dibuat sebagai satu panel dengan sidebar (di HP berupa menu bawah), bukan halaman-halaman yang terpisah jauh. Panel pengawasan menjadi halaman utama pengurus.

**Interaksi**

- Semua aksi (tandai selesai, toggle tugas, tukar petugas, terima pengajuan) berjalan tanpa reload halaman dan langsung memberi feedback berupa toast "Berhasil disimpan".
- Atur jadwal Kamis/Minggu: daftar tugas berupa kartu dengan toggle besar. Hasil "Buat pembagian" muncul dengan animasi, dan petugas bisa ditukar dengan drag-and-drop atau tombol tukar.
- Konfirmasi sebelum aksi penting (Terbitkan, Batalkan selesai) memakai modal yang jelas, bukan alert browser.
- Skeleton loading saat data dimuat, dan halaman kosong yang ramah (misalnya "Minggu ini kamu bebas piket").

**Tampilan**

- Mobile-first. Tombol utama minimal 48 px tingginya, teks minimal 16 px, dan bahasa sehari-hari tanpa istilah teknis.
- Palet: Venice Blue #16587B (warna utama, tombol), Rock Blue #84B3CE (biru lembut, aksen di latar gelap), Merino #F5EEDD (latar krem), tanpa oranye sebagai warna tombol. Tombol utama memakai Venice Blue di latar terang dan Merino di latar gelap. Warna status mengikuti psikologi warna: Selesai hijau, Bintang merah, Bayar Bintang dan Menunggu oranye/kuning, Terjadwal biru, Lunas teal. Panel pengurus dan bagian atas Beranda warga memakai latar biru gelap #0B3550 dengan kartu kaca transparan dan animasi masuk, hitung naik, serta efek tekan. Sudut kartu membulat, bayangan lembut, ikon yang jelas (misalnya Lucide), dan font modern seperti Plus Jakarta Sans.
- Animasi masuk yang halus saat scroll dan transisi antar-tab.
- Teknologi: Laravel + Livewire 3 (supaya interaktif tanpa reload) + Tailwind CSS + Alpine.js.

## 8. Notifikasi email

Semua email dikirim lewat queue Laravel memakai Mailable berbahasa Indonesia, dan setiap email berisi link ke web.

| Pemicu | Penerima | Isi |
| --- | --- | --- |
| Akun dibuat atau email dikirim ulang | Warga tersebut | Link untuk membuat password |
| Jadwal harian, Kamis, atau Minggu diterbitkan | Semua warga aktif | Piket penerima (termasuk Bayar Bintang) dan tenggatnya, atau info "minggu ini kamu bebas" |
| Jadwal diubah setelah terbit | Warga yang terdampak | Perubahan tugas |
| Pengingat | Petugas yang belum selesai | Harian: malam sebelumnya jam 20.00. Kamis: Jumat jam 12.00. Minggu: Senin jam 12.00 |
| Mendapat bintang | Pemilik bintang | Tugas yang terlewat dan keterangan bahwa bintang itu akan dijadwalkan ulang |
| Pengajuan ganti bintang diterima atau ditolak | Pengaju | Keputusan pengurus dan catatannya |
| Ada pengajuan baru | Semua pengurus | Ringkasan pengajuan |

## 9. Teknologi dan hosting

Laravel bisa jalan di Vercel, tapi lewat runtime PHP buatan komunitas (`vercel-php`) yang menjalankan aplikasi sebagai serverless function, bukan server biasa. Konsekuensinya ada empat hal yang wajib diatur:

| Hal | Masalah di Vercel | Solusi yang dipakai |
| --- | --- | --- |
| Database | Vercel tidak menyediakan MySQL | Pakai database terpisah yang punya paket gratis, misalnya MySQL atau PostgreSQL di Aiven, TiDB Cloud, Neon, atau Supabase |
| Scheduler (bintang otomatis, pengingat) | Cron Vercel paket Hobby (gratis) hanya bisa berjalan sekali sehari, dan waktunya meleset sampai 1 jam | Buat route rahasia `GET /cron/tick?key=<CRON_SECRET>` yang menjalankan `schedule:run`, lalu panggil route itu setiap 15 menit dari layanan cron eksternal gratis seperti cron-job.org. Ditambah pengecekan ringan setiap kali halaman dibuka (B4) |
| Queue email | Tidak ada proses worker yang terus berjalan | Pakai `QUEUE_CONNECTION=database`, lalu `/cron/tick` juga menjalankan `queue:work --stop-when-empty` |
| File dan sesi | Sistem file hanya sementara (`/tmp`) | Simpan sesi dan cache di database. Aplikasi ini tidak punya fitur unggah file |

**Stack:** PHP 8.3, Laravel 11 atau 12, Blade, Tailwind CSS, Alpine.js, MySQL atau PostgreSQL, SMTP (Gmail dengan App Password, Brevo, atau Resend), dan `maatwebsite/excel` untuk ekspor.

**Kebutuhan non-fungsional**

- Halaman dashboard terbuka dalam waktu kurang dari 2 detik lewat koneksi HP.
- Password disimpan sebagai hash (bcrypt). Route pengurus dilindungi middleware role. Route `/cron/tick` dilindungi secret.
- Semua aksi pengurus (terbitkan, tukar petugas, ubah status, setujui pengajuan) dicatat di log aktivitas.
- Aplikasi menyertakan seeder dengan data contoh: data asli 15 orang (lihat bagian 13), dan daftar tugas bawaan.
- Unit test wajib untuk B1, B2, B3, B4, dan B5.

Kalau deploy ke Vercel terlalu merepotkan, alternatif yang lebih mudah untuk Laravel adalah Railway, Render, atau hosting PHP biasa yang sudah punya cron dan MySQL.

Sumber: [Vercel Cron usage and pricing](https://vercel.com/docs/cron-jobs/usage-and-pricing), [vercel-community/php](https://github.com/vercel-community/php).

## 10. Kriteria penerimaan

- [ ] Rotasi geng benar untuk 12 minggu berturut-turut dari minggu acuan.
- [ ] Draf harian tidak pernah menaruh warga di hari ketika kuliahnya dimulai sebelum `batas_jam_kuliah`.
- [ ] Contoh dari pengurus berjalan benar: tugas aktif WC 1–4 dibagi otomatis menjadi WC 1 Nivia, WC 2 Dilla, WC 3 Gita, WC 4 Dewi, dan tidak ada nama yang muncul dua kali sebagai tugas reguler.
- [ ] Kasus bintang berjalan benar: Gita kena bintang pelataran di Kamis minggu ke-1. Di Kamis minggu ke-2, pelataran dipegang Gita dengan status Bayar Bintang, Nivia mendapat tugas lain, dan Gita tetap mendapat 1 tugas reguler.
- [ ] Kalau pelataran di-toggle off pada minggu ke-2, bintang Gita pindah ke minggu ke-3.
- [ ] Piket yang belum selesai saat tenggat lewat berubah menjadi Bintang paling lambat 15 menit setelah tenggat.
- [ ] Warga yang `wajib_diperiksa` tidak melihat tombol Selesai, tapi pengurus melihatnya.
- [ ] Pengajuan ganti bintang harian hanya menampilkan tanggal yang benar-benar kosong. Setelah disetujui, tanggal itu terisi, dan bintangnya menjadi Lunas setelah piket itu selesai.
- [ ] Setiap penerbitan jadwal mengirim email ke semua warga aktif.
- [ ] Rekap bulanan bisa diekspor dan angkanya cocok dengan data penugasan.

## 11. Tahapan pengerjaan (4 minggu)

1. **Minggu 1, fondasi:** setup Laravel dan database, migrasi, seeder, login email dan password, CRUD warga, geng, dan master tugas, serta pengaturan rotasi.
2. **Minggu 2, penjadwalan:** jadwal kuliah, draf harian (B2), toggle Kamis dan Minggu, penugasan otomatis (B3), terbitkan jadwal, dan unit test.
3. **Minggu 3, pelaksanaan:** dashboard warga, tandai selesai dan pemeriksaan, catatan, bintang otomatis dan pelunasan (B4, B5), pengajuan ganti bintang, serta email.
4. **Minggu 4, penyelesaian:** dashboard pengawasan, rekap dan ekspor, deploy ke Vercel beserta cron eksternal, uji coba dengan data asli, dan perbaikan.

## 12. Keputusan tambahan

Semua pertanyaan terbuka sudah dijawab pengurus pada 16 November 2026.

- **Bintang dari kerja bakti** menjadi bintang tugas kecil dan dikerjakan di Minggu berikutnya (lihat B3a).
- **Bintang harian** tidak punya batas waktu bayar dan tetap tercatat sebagai utang sampai dibayar.
- **Satu hari piket harian** hanya boleh diisi 1 orang. Kalau anggota geng lebih dari 7, sisanya diatur manual oleh pengurus.
- **`batas_jam_kuliah` = 09.00.** Warga yang kuliahnya dimulai sebelum jam 09.00 tidak dijadwalkan piket harian pada hari itu.

## 13. Data warga awal (untuk seeder)

Nama panggilan saja. Email sementara `<nama>@amkt.test`, diganti pengurus dari halaman Warga & geng. Nomor kamar dikosongkan.

| Nama | Role | Geng | Wajib diperiksa |
| --- | --- | --- | --- |
| Gita | ketua | 1 | tidak |
| Qamri | sekretaris | 1 | tidak |
| Adel | bendahara | 3 | tidak |
| Wilda | tata_tertib | 3 | tidak |
| Ping | warga | 2 | tidak |
| Ain | warga | 3 | tidak |
| Pipi | warga | 2 | tidak |
| Fika | warga | 3 | tidak |
| Nivia | warga | 2 | ya |
| Niya | warga | 3 | ya |
| Eca | warga | 2 | ya |
| Dilla | warga | 1 | ya |
| Dewi | warga | 1 | ya |
| Kia | warga | 2 | ya |
| Septi | warga | 1 | tidak |

## 14. Tampilan responsif

- HP (< 768 px): layout satu kolom seperti desain-warga.html, dengan 5 tab ikon di bawah header.
- Laptop (≥ 1024 px): web warga menjadi dashboard lebar. Navbar atas berisi logo, 5 menu teks dengan ikon, dan avatar. Konten maksimal 1200 px di tengah. Bagian atas Beranda dibagi 2 kolom (sapaan di kiri, kartu bento di kanan), kartu "Yang perlu kamu tahu" disusun 2 kolom, dan jadwal tampil sebagai tabel lebar.
- Panel pengurus memakai sidebar di laptop dan menu bawah di HP, seperti desain-pengurus.html.

## 15. Revisi 17 Nov 2026 (sore): jadwal harian per periode, izin pulang, password awal

Bagian ini menggantikan aturan lama di bagian 3, 4, dan 5 kalau bertentangan.

**R1. Jadwal harian per periode, bukan per minggu**

- Tata Tertib membuat **jadwal harian periode** (misalnya "Semester Ganjil 2026/2027", dengan tanggal mulai dan selesai). Isinya: untuk tiap geng, tiap anggota punya satu hari tetap (Senin–Minggu), dan hari tanpa anggota menjadi kosong.
- Draf hari dibuat dari jadwal kuliah (aturan B2), lalu diubah dan disimpan pengurus satu kali per periode.
- Setiap minggu, sistem otomatis membuat penugasan harian dari jadwal periode untuk geng yang sedang bertugas. **Tidak ada tombol Terbitkan untuk harian.**
- Kalau anggota geng berubah di tengah periode, pengurus cukup mengubah jadwal periode, dan perubahannya berlaku mulai minggu berikutnya.
- Yang diterbitkan tiap minggu hanya **piket Kamis dan piket Minggu**.

**R2. Password awal dan wajib ganti**

- Kolom tambahan di `users`: `harus_ganti_password` (bool, default true).
- Selama `harus_ganti_password` = true, semua halaman mengarah ke halaman "Buat password baru". Password baru minimal 8 karakter dan tidak boleh sama dengan password awal.
- Ada menu Ganti password di Profil dan "Lupa password" di halaman login (lewat email).
- Pengurus bisa menekan "Reset password" di Warga & geng, yang mengembalikan akun ke password awal baru dan mengaktifkan lagi `harus_ganti_password`.

**R3. Izin (revisi 17 Nov malam: pakai jam; nama fitur di web cukup "Izin")**

- Tabel baru `izin`: id, user_id, mulai (tanggal + jam), selesai (tanggal + jam), alasan (opsional, misalnya pulang kampung, kegiatan kampus, KKN), status (menunggu / disetujui / ditolak), dibuat_oleh_id, diputuskan_oleh_id. Izin milik satu warga tidak boleh bertumpuk waktunya (form menolak kalau bentrok dengan izinnya yang lain). Banyak warga yang berbeda boleh izin di waktu yang sama, tanpa batasan jumlah orang.
- Pengurus bisa langsung membuat izin (langsung disetujui). Warga bisa mengajukan izin dari halaman Piket saya, lalu pengurus menyetujui atau menolak, dan warga mendapat email.
- Selama izin disetujui, warga tidak masuk pool pembagian Kamis dan Minggu yang tanggal piketnya jatuh di rentang izin. Bintang lama miliknya juga ditunda.
- Kalau warga sudah dapat piket yang sudah terbit lalu izinnya disetujui, piket itu berstatus **Izin** (abu-abu, tidak menjadi bintang). Sistem otomatis memilih **pengganti** (keputusan final 18 Nov) dengan aturan yang sama seperti B3 langkah 2: warga di pool yang sedang di asrama, belum punya tugas reguler di sesi itu, dan paling lama tidak mengerjakan tugas tersebut. Pengganti mendapat email. Kalau tidak ada calon, tugas ditandai "Belum ada petugas" dan muncul paling atas di panel Pengawasan untuk diisi pengurus. Pengurus tetap bisa mengganti pengganti otomatis secara manual.
- Untuk piket harian, hari milik warga yang izin menjadi kosong. Pengurus bisa memilih pengganti secara manual.
- Panel Pengawasan menampilkan daftar siapa yang sedang pulang minggu ini.

**R3a. Kapan piket dianggap terkena izin**

- Izin bisa sebentar (beberapa jam atau satu hari, misalnya kegiatan kelompok) atau lama (pulang kampung). Contoh: izin Kamis 19 Nov jam 08.00 sampai Jumat 20 Nov jam 20.00.
- Piket **diganti** hanya kalau izin menutupi **seluruh** waktu pengerjaannya: harian jam 04.00–09.00 di hari itu, Kamis dari Kamis 09.00 sampai Jumat 18.00, Minggu dari Minggu 09.00 sampai Senin 18.00. Pada contoh di atas (mulai Kamis 08.00, sebelum 09.00, dan selesai Jumat 20.00, setelah 18.00), piket Kamis orang itu diganti.
- Kalau izin hanya menutupi sebagian waktu (misalnya pergi Kamis jam 10.00–14.00), piketnya tetap miliknya, karena masih ada waktu untuk mengerjakannya. Pengurus tetap bisa mengganti petugas secara manual.
- Di form izin, mulai dan selesai diisi dengan pemilih tanggal dan jam. Ada tombol cepat "Hari ini sampai besok" dan "Sehari penuh".

**R6. Pengajuan ganti bintang harian harus terlihat jelas**

- Di halaman Utang (warga), setiap bintang harian yang belum lunas tampil sebagai kartu dengan tombol **"Ajukan ganti"**. Bukan hanya baris riwayat.
- Tombol itu membuka form: daftar hari kosong yang masih tersedia (minggu mana pun ke depan), pilih satu, lalu kirim. Setelah dikirim, kartu menampilkan status Menunggu, Diterima, atau Ditolak.
- Bintang Kamis dan Minggu tidak punya tombol ajukan, karena dijadwalkan ulang otomatis. Kartunya menampilkan keterangan "Akan dijadwalkan ulang di Kamis/Minggu berikutnya".
- Riwayat bintang yang sudah lunas tetap ditampilkan di bagian bawah halaman yang sama.

**R4. Label status**

- Status `terjadwal` ditampilkan ke pengguna sebagai **"Belum dikerjakan"**.
- Status baru **Izin** (abu-abu) untuk piket yang dilepas karena izin pulang.
- Status jadwal (Draf/Terbit) hanya ditampilkan di halaman Atur jadwal, bukan di tiap piket.

**R5. Koreksi oleh pengurus**

Pengurus bisa mengubah status piket mana pun setelah tenggat (misalnya Bintang menjadi Selesai), dengan catatan wajib. Perubahannya dicatat di log aktivitas.
