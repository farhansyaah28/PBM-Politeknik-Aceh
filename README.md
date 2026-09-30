# Sistem Ujian Online PMB Politeknik Aceh

Aplikasi ini mengelola peserta, verifikasi identitas, bank soal, sesi ujian, pengawasan ringan, penilaian otomatis, keputusan resmi, laporan, dan retensi data PMB Politeknik Aceh. Paket ini dapat dijalankan secara lokal di Windows tanpa Composer, Node.js, npm, atau Docker.

Alur pendaftaran publik dan pembayaran tidak tersedia. Admin membuat akun peserta, lalu Peserta, Panitia, dan Admin masuk melalui satu halaman login.

## Mulai cepat

1. Salin atau ekstrak seluruh paket ke folder yang dapat ditulis oleh pengguna Windows.
2. Klik dua kali `setup.bat`.
3. Jika XAMPP belum tersedia, setujui pemasangan XAMPP 8.2 ketika diminta.
4. Tunggu hingga tampil `[SELESAI] Aplikasi siap di http://127.0.0.1:8080` dan browser terbuka.
5. Untuk menghentikan server aplikasi, klik dua kali `stop-app.bat`.

Jalankan aplikasi dari folder hasil ekstraksi, bukan langsung dari berkas ZIP. Koneksi internet hanya diperlukan apabila XAMPP harus dipasang pada penggunaan pertama.

Jika jendela menampilkan `[GAGAL]`, baca pesan setelah tanda tersebut, lakukan perbaikan yang diminta, lalu jalankan `setup.bat` kembali. Setup yang diulang akan mempertahankan `.env` dan melewati migrasi yang sudah selesai.

## Kebutuhan sistem

- Windows 10 atau Windows 11.
- PowerShell dan Windows Package Manager atau `winget` jika instalasi XAMPP diperlukan.
- PHP 8.2 atau lebih baru dengan ekstensi `pdo_mysql`, `openssl`, `fileinfo`, `zip`, dan `xmlreader`.
- MySQL atau MariaDB.
- Browser modern dengan dukungan kamera dan layar penuh untuk pelaksanaan ujian.

Skrip setup mencari XAMPP di dalam `runtime/xampp` terlebih dahulu, kemudian pada instalasi XAMPP umum di drive `C:`, `D:`, `E:`, serta di folder `Program Files`. Jika tidak ditemukan, skrip dapat memasang XAMPP 8.2 melalui katalog `winget`.

Ekstensi PHP yang belum aktif tidak perlu diperbaiki manual. Setup akan membuka `php.ini` yang sedang dipakai PHP tersebut, membuat cadangan `php.ini.pmb-backup`, mengaktifkan ekstensi yang kurang, lalu memeriksanya kembali.

## Menjalankan dan menghentikan aplikasi

Cara yang disarankan adalah memakai berkas berikut:

```text
setup.bat
stop-app.bat
```

Opsi setup dapat dijalankan dari Command Prompt atau PowerShell pada folder aplikasi:

```cmd
setup.bat -CheckOnly
setup.bat -NoBrowser
setup.bat -NoStartServer
setup.bat -AutoInstall
```

| Opsi | Fungsi |
|---|---|
| `-CheckOnly` | Memeriksa PHP, ekstensi, MySQL, dan koneksi database tanpa mengubah berkas atau database |
| `-NoBrowser` | Menyiapkan aplikasi tanpa membuka browser |
| `-NoStartServer` | Menyiapkan konfigurasi dan database tanpa menjalankan server web |
| `-AutoInstall` | Memasang XAMPP melalui `winget` tanpa pertanyaan konfirmasi |

Pada proses normal, setup akan:

1. memeriksa PHP, ekstensi PHP, dan MySQL, serta mengaktifkan ekstensi PHP yang belum aktif secara otomatis;
2. membuat `.env` dari `.env.example` jika `.env` belum tersedia;
3. membuat kunci enkripsi token lokal tanpa menampilkannya;
4. menyiapkan penyimpanan privat;
5. menyalakan MySQL XAMPP jika diperlukan;
6. membuat database dan menjalankan migrasi `001` sampai `020`; dan
7. menjalankan server PHP lokal pada `http://127.0.0.1:8080`.

Migrasi yang sudah tercatat pada tabel `schema_migrations` akan dilewati. Setup tidak menimpa `.env` yang sudah ada dan menolak database lama yang berisi tabel aplikasi tetapi tidak memiliki riwayat migrasi.

## Alamat aplikasi dan akun demonstrasi

- Beranda: `http://127.0.0.1:8080`
- Login semua peran: `http://127.0.0.1:8080/login`

| Peran | Identitas login | Kata sandi awal |
|---|---|---|
| Admin | `admin.uji@example.test` atau `admin.uji` | `Admin-Uji-2026` |
| Panitia | `panitia.uji@example.test` atau `panitia.uji` | `Panitia-Review-2026` |
| Peserta | Nomor peserta, email, atau username | Dibuat Admin; dapat dilihat kembali oleh Admin dari daftar peserta |

Akun Admin dan Panitia di atas hanya untuk demonstrasi lokal. Ganti atau hapus akun tersebut sebelum aplikasi menyimpan data nyata atau digunakan secara resmi.

Password peserta yang dibuat setelah migrasi `017_recoverable_participant_credentials.sql` tersimpan dalam bentuk terenkripsi untuk kebutuhan tampilan ulang oleh Admin. Setiap pembukaan password dicatat pada audit log. Password peserta yang sudah ada sebelum migrasi tersebut tidak dapat dipulihkan dari hash lama dan memerlukan prosedur reset password.

## Alur operasional

```text
Admin membuat atau mengimpor peserta
              ↓
Panitia memverifikasi identitas peserta
              ↓
Admin menyiapkan bank soal dan sesi ujian
              ↓
Admin menugaskan peserta yang telah disetujui
              ↓
Peserta memasukkan token jika diaktifkan dan menyelesaikan pemeriksaan perangkat
              ↓
Peserta mengerjakan ujian dan sistem menghitung nilai
              ↓
Admin memeriksa attempt dan kejadian keamanan
              ↓
Panitia menetapkan dan mempublikasikan keputusan resmi
              ↓
Peserta melihat hasil dan keputusan yang telah dipublikasikan
```

Persetujuan identitas berstatus `APPROVED` menjadi syarat penugasan ujian. Aplikasi menerapkan satu penugasan aktif, satu attempt, dan satu hasil untuk setiap peserta. Nilai ujian tidak otomatis menjadi keputusan kelulusan.

## Fitur menurut peran

### Admin

- `/admin/dashboard` untuk ringkasan pekerjaan Admin.
- `/admin/konfigurasi` untuk program studi, gelombang, jumlah soal, durasi, passing grade, dan mode keamanan.
- `/admin/peserta` untuk membuat peserta satu per satu, mengimpor XLSX, mencari, dan memfilter peserta.
- `/admin/bank-soal` untuk kategori, soal pilihan ganda, kunci jawaban, status aktif, dan impor XLSX.
- `/admin/ujian` untuk sesi, jadwal, token, dan penugasan peserta.
- `/admin/pengawasan` untuk attempt, kejadian keamanan, foto awal, dan kelanjutan attempt yang dijeda.
- `/admin/laporan` untuk ringkasan, rincian hasil, ekspor CSV, dan cetak.
- `/admin/retensi` untuk masa simpan, pengecualian peserta, dan legal hold.

### Panitia

- `/panitia/dashboard` untuk ringkasan pekerjaan Panitia.
- `/panitia/verifikasi` untuk checklist dan keputusan verifikasi identitas.
- `/panitia/keputusan` untuk menetapkan, menjadwalkan, dan mempublikasikan keputusan resmi.
- `/panitia/laporan` untuk laporan hasil dan ekspor CSV.

### Peserta

- `/dashboard` untuk status identitas, informasi peserta, sesi, hasil, dan keputusan.
- `/ujian` untuk memasukkan token, memberikan persetujuan ujian, menjalankan pemeriksaan perangkat, mengerjakan soal, dan melihat hasil.

Alamat lama `/admin/login` dan `/panitia/login` diarahkan ke `/login`. Alamat `/daftar` tidak menyediakan pendaftaran mandiri.

## Impor XLSX

Template peserta dan bank soal diunduh dari aplikasi setelah Admin login. Setiap template memiliki lembar data sebagai lembar pertama dan lembar `Petunjuk` yang berisi contoh, aturan, serta daftar kode aktif.

| Jenis data | Unduh template | Batas | Perilaku ketika ada kesalahan |
|---|---|---|---|
| Peserta | `/admin/peserta/template` | 5 MB dan 2.000 baris | Baris valid dibuat; baris tidak valid dilaporkan |
| Bank soal | `/admin/bank-soal/template` | 5 MB dan 2.000 baris | Seluruh impor dibatalkan jika satu baris tidak valid atau duplikat |

Header peserta:

```text
full_name,email,phone_number,school_name,graduation_year,program_code,wave_code
```

Header bank soal:

```text
category_code,question_text,option_a,option_b,option_c,option_d,correct_option
```

Jangan mengubah nama, urutan, atau posisi header pada baris pertama lembar data. Petunjuk rinci tersedia dalam [Panduan Penggunaan](PANDUAN-PENGGUNAAN.md).

## Konfigurasi lokal

Konfigurasi berada di `.env`. Berkas ini dibuat otomatis pada setup pertama dan tidak boleh dibagikan karena memuat konfigurasi lokal serta kunci enkripsi token.

| Variabel | Kegunaan |
|---|---|
| `APP_URL` | Alamat dasar aplikasi |
| `APP_TIMEZONE` | Zona waktu aplikasi; nilai yang digunakan adalah `Asia/Jakarta` |
| `TOKEN_ENCRYPTION_KEY` | Kunci enkripsi untuk menampilkan ulang token kepada Admin |
| `DB_HOST`, `DB_PORT` | Alamat dan port MySQL |
| `DB_DATABASE` | Nama database aplikasi |
| `DB_USERNAME`, `DB_PASSWORD` | Kredensial MySQL |
| `PRIVATE_STORAGE_PATH` | Lokasi penyimpanan privat foto awal ujian |

Jika port atau kredensial MySQL berbeda, ubah `.env` lalu jalankan `setup.bat` kembali. Gunakan database kosong khusus aplikasi agar data lain tidak tersentuh.

## Struktur paket

```text
app/                  Logika aplikasi dan layanan PHP
database/migrations/  Skema, perubahan skema, dan data awal
public/               Entry point, halaman, JavaScript, font, dan CSS
scripts/              Setup serta proses terjadwal
storage/private/      Penyimpanan privat foto awal ujian
runtime/              PID dan log server lokal yang dibuat saat aplikasi berjalan
.env.example          Contoh konfigurasi
setup.bat             Setup dan start aplikasi di Windows
stop-app.bat          Stop server aplikasi lokal
README.md              Petunjuk instalasi dan gambaran sistem
PANDUAN-PENGGUNAAN.md  Panduan operasional seluruh peran
ERD-WEBSITE-PMB.md     Struktur dan hubungan database aktif
```

## Proses terjadwal

Dua skrip perlu dijalankan berkala pada lingkungan operasional:

- `scripts/process_expired_attempts.php` mengumpulkan attempt yang melewati batas waktu.
- `scripts/process_scheduled_decisions.php` mempublikasikan keputusan terjadwal yang sudah jatuh tempo.

Gunakan Task Scheduler atau scheduler server yang andal. Jalankan setiap skrip dengan PHP 8.2 menggunakan path absolut ke executable PHP dan ke file skrip. Untuk operasi resmi, interval satu menit disarankan agar pengumpulan dan publikasi tidak terlambat lama.

## Batas penggunaan lokal dan produksi

Server PHP yang dijalankan oleh `setup.bat` ditujukan untuk demonstrasi, pelatihan, dan pengujian lokal. Sebelum produksi:

1. ganti atau hapus seluruh akun demonstrasi;
2. gunakan HTTPS dan web server produksi yang dikonfigurasi dengan aman;
3. simpan `.env`, database, token, dan `storage/private` di lokasi yang terlindungi;
4. sahkan data peserta, bank soal, jadwal, keputusan, SOP pengawasan, dan kebijakan retensi;
5. siapkan backup database dan penyimpanan privat, lalu uji pemulihannya;
6. aktifkan kedua proses terjadwal dan monitoring operasional; dan
7. lakukan UAT, pengujian keamanan, beban, perangkat, browser, dan aksesibilitas.

Foto awal disimpan di penyimpanan privat dan hanya dapat dibuka oleh Admin yang telah login. Aplikasi mengirim kebijakan keamanan browser seperti Content Security Policy, perlindungan framing, `nosniff`, referrer policy, dan permission policy. HSTS hanya efektif ketika aplikasi disajikan melalui HTTPS.

## Mengatasi kendala umum

| Kendala | Tindakan |
|---|---|
| `winget` tidak ditemukan | Pasang App Installer dari Microsoft Store, lalu jalankan `setup.bat` kembali |
| Ekstensi PHP belum aktif | Setup mengaktifkannya sendiri; jika pesan gagal tetap muncul, jalankan `setup.bat` melalui klik kanan lalu **Run as administrator** agar `php.ini` dapat ditulis |
| Ekstensi tetap tidak aktif setelah setup | Berkas DLL ekstensi tidak ada pada instalasi PHP tersebut; pasang XAMPP 8.2 standar di `C:\xampp`, lalu jalankan `setup.bat` kembali |
| MySQL tidak dapat dijalankan | Periksa XAMPP Control Panel dan konflik port MySQL |
| Akses database ditolak | Periksa `DB_USERNAME` dan `DB_PASSWORD` dalam `.env` |
| Database lama ditolak | Gunakan nama database kosong pada `DB_DATABASE` |
| Port `8080` digunakan aplikasi lain | Hentikan aplikasi yang memakai port tersebut, kemudian jalankan setup kembali |
| Browser tidak terbuka | Buka `http://127.0.0.1:8080` secara manual |
| Server aplikasi gagal berjalan | Periksa `runtime/server-error.log` |
| Server tidak berhenti | Jalankan `stop-app.bat`; skrip hanya menghentikan proses PHP yang tercatat sebagai server aplikasi ini |

## Dokumentasi

- [Panduan Penggunaan](PANDUAN-PENGGUNAAN.md) menjelaskan langkah setiap peran, impor data, ujian, pengawasan, keputusan, retensi, dan UAT.
- [ERD Website PMB](ERD-WEBSITE-PMB.md) menjelaskan tabel aktif, hubungan data, status, serta aturan integritas.
