# Panduan Penggunaan Sistem Ujian Online PMB Politeknik Aceh

Panduan ini menjelaskan cara menyiapkan aplikasi dan menjalankan seluruh proses ujian, mulai dari pembuatan peserta sampai publikasi keputusan. Dokumen ini ditujukan untuk Admin, Panitia, Peserta, petugas teknologi informasi, dan tim UAT.

Aplikasi tidak menyediakan pendaftaran publik atau pembayaran. Admin membuat akun peserta dan menyerahkan kredensial awal melalui kanal institusi yang aman.

## 1 Gambaran sistem

Aplikasi memiliki tiga peran.

| Peran | Tanggung jawab utama |
|---|---|
| Admin | Menyiapkan konfigurasi, peserta, bank soal, sesi, token, penugasan, pengawasan, laporan, dan retensi |
| Panitia | Memverifikasi identitas, menetapkan keputusan resmi, mempublikasikan keputusan, dan melihat laporan |
| Peserta | Masuk dengan akun yang dibuat Admin, mengikuti satu ujian, lalu melihat hasil dan keputusan yang dipublikasikan |

Aturan operasional utama:

1. setiap peserta memiliki nomor peserta unik yang dibuat sistem;
2. peserta dapat ditugaskan setelah identitas berstatus **Disetujui**;
3. peserta dan sesi harus berasal dari gelombang yang sama;
4. setiap peserta hanya dapat memiliki satu penugasan aktif, satu attempt, dan satu hasil;
5. nilai ujian dihitung otomatis, tetapi keputusan kelulusan dibuat terpisah oleh Panitia;
6. waktu jadwal ditampilkan dalam WIB;
7. token diberikan melalui kanal resmi dan tidak ditampilkan di dashboard Peserta; dan
8. foto awal ujian disimpan secara privat serta hanya dapat dibuka Admin.

Status **Perlu perbaikan** menyimpan catatan Panitia, tetapi aplikasi tidak menyediakan formulir perbaikan mandiri bagi Peserta. Koreksi data perlu ditangani oleh petugas yang berwenang sesuai prosedur institusi. Aplikasi juga tidak menyediakan reset password mandiri.

## 2 Persiapan sebelum menjalankan aplikasi

### Kebutuhan komputer

Siapkan:

- Windows 10 atau Windows 11;
- ruang penyimpanan yang cukup untuk XAMPP, database, log, dan foto awal ujian;
- koneksi internet jika XAMPP belum terpasang;
- browser modern dengan izin kamera dan layar penuh; dan
- hak akses Windows untuk menjalankan PowerShell serta memasang XAMPP jika diperlukan.

Aplikasi memerlukan PHP 8.2 atau lebih baru, MySQL atau MariaDB, serta ekstensi PHP `pdo_mysql`, `openssl`, `fileinfo`, `zip`, dan `xmlreader`. Skrip setup dapat memasang XAMPP 8.2 melalui `winget` jika komponen tersebut belum tersedia, dan mengaktifkan sendiri ekstensi PHP yang belum aktif pada `php.ini` (cadangan disimpan sebagai `php.ini.pmb-backup`).

### Penempatan paket

1. Ekstrak seluruh paket ke folder yang dapat ditulis.
2. Jangan menjalankan aplikasi langsung dari berkas ZIP.
3. Pertahankan struktur folder `app`, `database`, `public`, `scripts`, dan `storage`.
4. Pastikan `setup.bat` dan `stop-app.bat` berada pada tingkat folder yang sama dengan `README.md`.

## 3 Menjalankan aplikasi

### Setup pertama

1. Klik dua kali `setup.bat`.
2. Jika XAMPP belum ditemukan, pilih **Ya** ketika diminta memasang XAMPP 8.2.
3. Tunggu pemeriksaan PHP dan MySQL selesai.
4. Tunggu pembuatan `.env`, kunci enkripsi token, database, dan migrasi.
5. Pastikan tampil `[SELESAI] Aplikasi siap di http://127.0.0.1:8080`.
6. Buka http://127.0.0.1:8080 jika browser tidak terbuka otomatis.

Setup membuat database baru sesuai `DB_DATABASE` pada `.env`. Database yang sudah berisi tabel aplikasi tanpa tabel `schema_migrations` akan ditolak agar data lama tidak tertimpa.

Jika tampil `[GAGAL]`, baca pesan setelah tanda tersebut, lakukan perbaikan yang diminta, lalu jalankan `setup.bat` kembali. Jangan menutup jendela setup sebelum pesan berhasil atau gagal terlihat.

### Penggunaan berikutnya

Klik dua kali `setup.bat`. Migrasi yang sudah selesai akan dilewati dan konfigurasi `.env` yang sudah ada akan dipertahankan.

### Pilihan setup

Jalankan opsi berikut dari Command Prompt atau PowerShell di folder aplikasi:

```cmd
setup.bat -CheckOnly
setup.bat -NoBrowser
setup.bat -NoStartServer
setup.bat -AutoInstall
```

| Opsi | Penggunaan |
|---|---|
| `-CheckOnly` | Memeriksa prasyarat tanpa mengubah berkas atau database |
| `-NoBrowser` | Menjalankan setup tanpa membuka browser |
| `-NoStartServer` | Menyiapkan konfigurasi dan database saja |
| `-AutoInstall` | Menyetujui instalasi XAMPP otomatis |

### Menghentikan aplikasi

Klik dua kali `stop-app.bat`. Skrip membaca `runtime/server.pid` dan hanya menghentikan proses PHP yang teridentifikasi sebagai server aplikasi ini.

Menghentikan aplikasi tidak otomatis menghentikan MySQL XAMPP.

## 4 Login dan pemisahan peran

Buka `http://127.0.0.1:8080/login`.

| Peran | Identitas yang dapat digunakan | Kata sandi awal |
|---|---|---|
| Admin | `admin.uji@example.test` atau `admin.uji` | `Admin-Uji-2026` |
| Panitia | `panitia.uji@example.test` atau `panitia.uji` | `Panitia-Review-2026` |
| Peserta | Nomor peserta, email, atau username | Dibuat Admin; dapat dilihat kembali melalui daftar peserta |

Langkah login:

1. masukkan identitas akun;
2. masukkan kata sandi;
3. tekan **Masuk ke sistem**; dan
4. pastikan sistem mengarahkan pengguna ke dashboard sesuai peran.

Gunakan profil browser atau sesi privat yang berbeda jika Admin, Panitia, dan Peserta perlu dibuka bersamaan. Jangan membagikan satu sesi browser kepada beberapa peran.

Akun Admin dan Panitia bawaan hanya untuk demonstrasi lokal. Ganti atau hapus keduanya sebelum memasukkan data nyata.

## 5 Urutan proses lengkap

```text
Admin menyiapkan program dan gelombang
              ↓
Admin membuat atau mengimpor peserta
              ↓
Panitia memverifikasi identitas
              ↓
Admin menyiapkan kategori dan bank soal
              ↓
Admin membuat sesi, jadwal, dan token
              ↓
Admin menugaskan peserta yang telah disetujui
              ↓
Peserta memasukkan token dan menyetujui aturan
              ↓
Peserta menyelesaikan kamera, foto awal, dan layar penuh
              ↓
Peserta mengerjakan serta mengumpulkan ujian
              ↓
Sistem menghitung nilai dan Admin memeriksa pengawasan
              ↓
Panitia menetapkan serta mempublikasikan keputusan
              ↓
Admin dan Panitia memeriksa laporan serta retensi
```

Ikuti urutan ini saat menjalankan UAT pertama agar setiap data prasyarat tersedia sebelum tahap berikutnya.

## 6 Konfigurasi oleh Admin

Buka menu **Konfigurasi** atau alamat `/admin/konfigurasi`.

### Program studi

Untuk menambah program:

1. isi **Kode program**;
2. isi **Nama program**; dan
3. tekan **Tambah program**.

Kode harus terdiri dari 2 sampai 24 karakter berupa huruf kapital, angka, atau tanda hubung. Kode dan nama harus unik.

Untuk mengubah program:

1. buka program pada daftar;
2. perbarui kode atau nama;
3. tekan **Simpan**.

Gunakan **Nonaktifkan** jika program tidak lagi dipakai untuk peserta baru. Program yang sudah dipakai peserta tidak dapat dihapus; riwayat harus tetap dipertahankan.

### Jalur masuk

Pada **Konfigurasi > Kelola jalur masuk**, isi nama lalu tekan **Tambah jalur**. Buka baris jalur untuk mengubah nama, menonaktifkan, atau menghapusnya. Jalur yang sudah digunakan peserta tidak dapat dihapus; gunakan **Nonaktifkan** agar riwayat tetap tersedia. Mengubah nama juga memperbarui nama jalur pada data peserta terkait.

Jalur aktif menjadi pilihan pada form peserta dan template XLSX baru. Unduh kembali template setelah mengubah katalog. Nilai awal tersedia: Jalur Prestasi, KIP, Reguler, OSIS, Alijenjang, Sawit, dan BPA.

### Gelombang ujian

Isi:

- kode dan nama gelombang;
- jumlah soal;
- durasi dalam menit;
- passing grade antara 0 sampai 100; dan
- mode keamanan `WEB_STRICT` atau `PROCTORING_LITE`.

Tekan **Buat gelombang**. Pada daftar gelombang, Admin dapat mengubah konfigurasi atau mengaktifkan dan menonaktifkan gelombang.

Jumlah soal, durasi, passing grade, dan mode keamanan disalin ke sesi pada saat sesi dibuat. Jika konfigurasi gelombang diubah kemudian, periksa kembali sesi yang sudah ada.

## 7 Pengelolaan peserta oleh Admin

Buka menu **Peserta** atau alamat `/admin/peserta`.

### Membuat satu peserta

1. Isi nama lengkap.
2. Isi email yang valid dan belum digunakan.
3. Isi nomor telepon.
4. Isi asal sekolah.
5. Isi tahun lulus antara 1950 sampai 2100.
6. Pilih jalur masuk aktif.
7. Pilih program studi aktif.
8. Pilih gelombang aktif.
9. Isi username sepanjang 4 sampai 32 karakter atau biarkan kosong agar dibuat sistem.
10. Tekan **Tambah peserta**.
11. Salin nomor peserta dan kata sandi awal yang ditampilkan.

Admin dapat melihat kembali password dari kolom **Password** pada daftar peserta dengan menekan **Lihat password**. Password hanya ditampilkan pada pemuatan halaman tersebut dan setiap pembukaan dicatat pada audit log. Simpan dan kirim kredensial melalui kanal institusi yang aman.

Fitur tampilan ulang hanya berlaku untuk peserta yang dibuat setelah migrasi `017_recoverable_participant_credentials.sql`. Password peserta lama tidak dapat dipulihkan dari hash dan memerlukan prosedur reset password.

Peserta baru memiliki status identitas **Menunggu** dan belum dapat ditugaskan ke sesi.

### Mengisi template peserta

1. Tekan **Unduh template XLSX** pada bagian **Impor XLSX**.
2. Buka lembar `Petunjuk` dan baca aturan pengisian.
3. Kembali ke lembar data `Peserta`, yang harus tetap menjadi lembar pertama.
4. Isi data mulai baris kedua.
5. Gunakan pilihan kode program dan gelombang yang tersedia pada dropdown.
6. Simpan dalam format `.xlsx`.

Header pada baris pertama harus tetap persis seperti berikut:

```text
Nama Lengkap,Email,Nomor WhatsApp,Asal Sekolah,Tahun Lulus,Jalur Masuk,Kode Program Studi,Kode Gelombang
```

Aturan penting:

- jangan mengganti nama atau urutan kolom;
- jangan menambahkan judul di atas header;
- jangan memindahkan lembar `Petunjuk` menjadi lembar pertama;
- simpan nomor telepon sebagai teks agar angka nol di depan tidak hilang;
- gunakan email yang valid dan belum dipakai;
- gunakan kode program dan gelombang aktif; dan
- gunakan maksimal 2.000 baris data dan ukuran file maksimal 5 MB.

### Mengimpor peserta

1. Pilih file `.xlsx`.
2. Tekan **Impor**.
3. Tunggu hasil pemrosesan.
4. Salin kredensial setiap peserta yang berhasil dibuat.
5. Periksa pesan untuk setiap baris yang gagal.
6. Koreksi hanya baris yang gagal, kemudian unggah file koreksi.

Impor peserta diproses per baris. Baris valid tetap dibuat walaupun baris lain gagal. Karena itu, jangan mengunggah kembali seluruh file tanpa menghapus baris yang sudah berhasil; email yang sudah dibuat akan dianggap duplikat.

### Mencari peserta

Gunakan kolom pencarian untuk nama, email, atau nomor peserta. Gunakan filter status **Menunggu**, **Disetujui**, **Perlu perbaikan**, atau **Ditolak**. Daftar menampilkan 15 peserta per halaman.

## 8 Verifikasi identitas oleh Panitia

Buka menu **Verifikasi** atau alamat `/panitia/verifikasi`.

### Memilih peserta

1. Cari nama atau nomor peserta.
2. Pilih peserta dari antrean.
3. Cocokkan nomor peserta, kontak, asal sekolah, program, dan gelombang.
4. Periksa bukti pendukung melalui prosedur institusi yang berlaku.

### Checklist verifikasi

| Kode | Pemeriksaan |
|---|---|
| V-01 | Data wajib lengkap dan dapat dibaca |
| V-02 | Nama sesuai bukti identitas |
| V-03 | Data identitas utama konsisten |
| V-04 | Email dan nomor kontak dapat digunakan |
| V-05 | Asal sekolah dan tahun lulus sesuai |
| V-06 | Program studi dan gelombang valid |
| V-07 | Dokumen pendukung dapat dibaca |
| V-08 | Tidak ditemukan akun duplikat |
| V-09 | Data dibuat melalui proses Admin |
| V-10 | Tidak ada hambatan administratif lain |

Untuk memilih **Disetujui**, seluruh butir V-01 sampai V-10 harus dicentang.

### Menyimpan keputusan

Pilih salah satu keputusan:

- **Disetujui** jika semua pemeriksaan terpenuhi;
- **Perlu perbaikan** jika data masih dapat dilengkapi atau dikoreksi; atau
- **Ditolak** jika peserta tidak dapat diterima berdasarkan prosedur institusi.

Catatan wajib diisi untuk **Perlu perbaikan** dan **Ditolak**. Setelah disimpan, keputusan baru masuk ke riwayat verifikasi dan status peserta diperbarui.

Peserta berstatus **Disetujui** langsung memenuhi syarat identitas untuk penugasan. Tidak ada tombol eligibility tambahan.

## 9 Bank soal oleh Admin

Buka menu **Bank soal** atau alamat `/admin/bank-soal`.

### Kategori yang digunakan untuk ujian

Untuk sesi dengan empat soal atau lebih, sistem membagi soal ke empat kode kategori berikut:

| Urutan | Kode | Nama awal |
|---|---|---|
| 1 | `VERBAL` | Kemampuan Verbal |
| 2 | `NUMERIK` | Kemampuan Numerik |
| 3 | `LOGIKA` | Penalaran Logis |
| 4 | `UMUM` | Pengetahuan Umum |

Keempat kategori harus tetap aktif dan memiliki soal aktif yang cukup. Sistem membagi jumlah soal secara merata; sisa pembagian dialokasikan sesuai urutan pada tabel. Contoh:

| Jumlah soal sesi | VERBAL | NUMERIK | LOGIKA | UMUM |
|---|---:|---:|---:|---:|
| 20 | 5 | 5 | 5 | 5 |
| 25 | 7 | 6 | 6 | 6 |

Kategori tambahan dapat disimpan, tetapi tidak menggantikan kebutuhan empat kode di atas pada sesi dengan empat soal atau lebih.

### Menambah soal satu per satu

1. Pilih kategori.
2. Isi pertanyaan.
3. Isi Opsi A, B, C, dan D.
4. Pilih kunci jawaban.
5. Tekan **Simpan soal**.
6. Periksa soal pada daftar di bagian bawah halaman.

Soal aktif dapat diedit atau dinonaktifkan. Soal nonaktif tetap tersimpan sebagai riwayat dan tidak dipilih untuk attempt baru.

### Mengisi template bank soal

1. Tekan **Unduh template XLSX**.
2. Baca lembar `Petunjuk`.
3. Pertahankan lembar `Bank Soal` sebagai lembar pertama.
4. Isi data mulai baris kedua.
5. Gunakan dropdown kode kategori aktif dan kunci jawaban.

Header wajib:

```text
Kode Kategori,Pertanyaan,Opsi A,Opsi B,Opsi C,Opsi D,Kunci Jawaban
```

Setiap baris harus memiliki kategori aktif, pertanyaan, empat opsi, dan **Kunci Jawaban** berupa `A`, `B`, `C`, atau `D`. Header teknis bahasa Inggris tetap dikenali untuk kompatibilitas; gunakan template terbaru agar susunan kolom benar.

### Mengimpor bank soal

1. Pilih file `.xlsx`.
2. Tekan **Validasi dan impor**.
3. Jika ada kesalahan, baca nomor baris pada pesan.
4. Perbaiki semua kesalahan dan unggah ulang file.

Impor bank soal bersifat atomik. Satu baris yang tidak valid atau duplikat membuat seluruh file dibatalkan. Sistem memeriksa duplikat di dalam file dan terhadap soal yang sudah ada pada kategori yang sama.

## 10 Sesi ujian dan penugasan oleh Admin

Buka menu **Ujian** atau alamat `/admin/ujian`.

### Membuat sesi

1. Pilih gelombang aktif.
2. Isi nama sesi.
3. Isi **Batas nilai kelulusan** antara 0 dan 100.
4. Centang **Aktifkan token untuk sesi ini** jika diperlukan, lalu isi token minimal 8 karakter. Jika dinonaktifkan, token boleh kosong.
5. Tekan **Buat sesi**.

Sesi baru mengambil jumlah soal, durasi, dan mode keamanan dari gelombang. Batas kelulusan ditetapkan pada sesi: nilai yang sama atau lebih tinggi masuk kategori lulus secara otomatis. Keputusan resmi tetap mengikuti penetapan dan publikasi Panitia.

### Mengatur jadwal

1. Buka detail sesi.
2. Isi waktu mulai dalam WIB.
3. Isi waktu selesai dalam WIB.
4. Pastikan waktu selesai lebih akhir daripada waktu mulai.
5. Periksa **Batas nilai kelulusan**, lalu tekan **Simpan jadwal & batas nilai**.

Setelah jadwal disimpan, sesi berstatus **Terjadwal**. Waktu pengerjaan peserta dibatasi oleh durasi ujian atau waktu selesai sesi, mana yang lebih dahulu.

### Mengelola token

Pada detail sesi, gunakan **Aktifkan token** lalu **Simpan token** untuk mengaktifkan atau menonaktifkan pemeriksaan token. Menonaktifkan token tidak melewati syarat verifikasi, penugasan, jadwal, persetujuan, atau pemeriksaan perangkat.

Admin dapat melihat ulang token pada detail sesi karena token disimpan dalam bentuk terenkripsi untuk tampilan Admin dan hash untuk validasi. Jika token diganti, token lama langsung tidak berlaku. Kosongkan field token baru untuk mempertahankan token tersimpan.

Sampaikan token hanya kepada peserta yang sudah ditugaskan dan gunakan kanal komunikasi resmi.

### Menugaskan peserta

Untuk penugasan satu peserta:

1. pilih sesi;
2. pilih peserta;
3. tekan **Tugaskan**.

Untuk satu gelombang:

1. pilih sesi pada bagian penugasan massal;
2. tekan **Tugaskan semua peserta eligible**; dan
3. periksa jumlah peserta yang berhasil ditugaskan.

Peserta hanya dapat ditugaskan jika:

- akun peserta aktif;
- identitas berstatus **Disetujui**;
- gelombang peserta sama dengan gelombang sesi;
- belum memiliki penugasan aktif;
- belum memiliki attempt; dan
- belum memiliki hasil.

Dashboard Peserta menampilkan sesi dan jadwal, tetapi tidak menampilkan token.

## 11 Pelaksanaan ujian oleh Peserta

Dashboard memuat status dan langkah berikutnya secara ringkas. Data identitas, kontak, pendidikan, dan pendaftaran berada pada menu **Profil peserta**. Bantuan Panitia tersedia melalui WhatsApp dan email pada dashboard serta profil.

### Persiapan perangkat

Sebelum jadwal dimulai, Peserta harus:

- memakai komputer atau laptop dengan kamera;
- memakai browser modern;
- memastikan izin kamera tersedia;
- menyiapkan koneksi yang stabil;
- menutup aplikasi atau tab yang tidak diperlukan; dan
- menyiapkan token dari Panitia atau Admin.

Alur pemeriksaan perangkat saat ini meminta kamera, foto awal, dan layar penuh.

### Membuka sesi

1. Login melalui `/login`.
2. Buka **Sesi ujian** atau `/ujian`.
3. Periksa nama sesi, jadwal WIB, durasi, dan mode keamanan.
4. Masukkan token bila proteksi token sesi diaktifkan.
5. Centang persetujuan aturan ujian dan pengawasan.
6. Tekan **Masuk sesi**.

Akses ditolak jika identitas belum disetujui, peserta belum ditugaskan, token salah, jadwal belum aktif atau sudah berakhir, atau bank soal tidak mencukupi.

### Pemeriksaan perangkat

1. Tekan **Periksa kamera dan lanjutkan**.
2. Izinkan layar penuh.
3. Izinkan kamera.
4. Pastikan wajah terlihat jelas pada kamera.
5. Tunggu foto awal terkirim.
6. Tunggu ruang soal terbuka.

Timer belum berjalan ketika Peserta masih berada pada pemeriksaan perangkat. Timer dimulai setelah ruang soal berhasil dibuka. Foto awal maksimal 2 MB, disimpan di `storage/private`, dan tidak tersedia melalui URL publik.

### Mengerjakan soal

Di ruang ujian:

- timer dan progres tampil pada bagian atas;
- daftar nomor menunjukkan soal yang sudah dan belum dijawab;
- jawaban disimpan otomatis;
- tombol **Soal sebelumnya** dan **Soal berikutnya** memindahkan tampilan soal; dan
- opsi jawaban dapat diacak untuk setiap attempt.

Tunggu indikator penyimpanan selesai sebelum berpindah jika koneksi sedang lambat. Jika penyimpanan gagal, pilih jawaban kembali setelah koneksi pulih dan pastikan status penyimpanan berubah.

### Kejadian keamanan

Sistem mencatat:

- masuk dan keluar dari layar penuh;
- perpindahan tab atau jendela yang menyembunyikan halaman;
- izin atau penolakan kamera; dan
- pengambilan foto awal.

Tiga kejadian pelanggaran berupa keluar layar penuh, menyembunyikan tab, atau menolak kamera dapat mengubah attempt menjadi **Menunggu review Admin**. Pencatatan ini merupakan sinyal pengawasan dan perlu ditinjau sesuai SOP institusi.

### Mengumpulkan ujian

1. Periksa jumlah soal yang belum dijawab.
2. Tekan **Kumpulkan jawaban**.
3. Konfirmasikan pengumpulan.
4. Tunggu halaman hasil terbuka.

Jawaban tidak dapat diubah setelah attempt dikumpulkan. Sistem menghitung nilai, jumlah benar, salah, dan kosong. Jika waktu habis, proses terjadwal dapat mengumpulkan attempt secara otomatis.

## 12 Pengawasan oleh Admin

Buka menu **Pengawasan** atau alamat `/admin/pengawasan`.

Untuk setiap attempt, Admin dapat melihat:

- nama dan nomor peserta;
- sesi;
- status attempt;
- waktu mulai dan batas waktu;
- jumlah kejadian keamanan;
- jumlah pelanggaran; dan
- foto awal, jika tersedia.

Untuk attempt berstatus **Menunggu review Admin**:

1. buka data attempt;
2. periksa kejadian dan foto awal;
3. cocokkan dengan SOP pengawasan;
4. tekan tindakan untuk melanjutkan hanya jika peserta masih diizinkan; dan
5. komunikasikan keputusan operasional kepada pengawas atau Peserta.

Attempt hanya dapat dilanjutkan selama batas waktu attempt dan sesi belum berakhir. Melanjutkan attempt tidak menambah waktu.

## 13 Hasil dan keputusan resmi

### Hasil ujian

Setelah pengumpulan berhasil, Peserta dapat melihat:

- nilai dalam skala 0 sampai 100;
- jumlah jawaban benar;
- jumlah jawaban salah; dan
- jumlah jawaban kosong.

Nilai dihitung dari snapshot soal pada attempt. Perubahan bank soal setelah attempt dibuat tidak mengubah hasil tersebut.

### Keputusan Panitia

Buka menu **Keputusan** atau alamat `/panitia/keputusan`.

1. Cari peserta.
2. Periksa nilai dan data yang relevan.
3. Pilih **Lulus** atau **Tidak lulus**.
4. Isi catatan keputusan.
5. Simpan keputusan.

Keputusan baru disimpan sebagai **Menunggu publikasi**. Nilai atau passing grade tidak mempublikasikan keputusan secara otomatis.

### Publikasi keputusan

Panitia dapat:

- mempublikasikan keputusan langsung; atau
- menjadwalkan publikasi pada waktu tertentu dalam WIB.

Keputusan baru terlihat oleh Peserta setelah berstatus **Dipublikasikan**. Publikasi terjadwal memerlukan `scripts/process_scheduled_decisions.php` yang berjalan berkala.

Jika keputusan dikoreksi, aplikasi membuat riwayat baru dan mempertahankan keputusan lama sebagai catatan yang tidak lagi aktif.

## 14 Laporan

Admin membuka `/admin/laporan`. Panitia membuka `/panitia/laporan`.

Laporan memuat:

- jumlah peserta;
- jumlah peserta eligible;
- hasil yang sudah dinilai;
- keputusan yang sudah dipublikasikan;
- sebaran keputusan;
- rata-rata nilai per program studi; dan
- rincian peserta serta hasil.

Gunakan filter dan halaman untuk meninjau data. Pilih **Ekspor CSV** untuk analisis lanjutan atau **Cetak laporan** untuk tampilan cetak browser. Nilai yang dapat ditafsirkan sebagai formula spreadsheet dinetralkan pada ekspor CSV.

## 15 Retensi dan legal hold

Buka menu **Retensi** atau alamat `/admin/retensi`.

### Masa simpan default

Admin dapat mengatur masa simpan dalam tahun untuk:

- foto proctoring;
- kejadian keamanan ujian;
- persetujuan peserta; dan
- audit log.

### Pengecualian peserta

Gunakan pengecualian jika seluruh data peserta tertentu membutuhkan masa simpan berbeda.

1. pilih peserta;
2. isi masa simpan dalam tahun;
3. isi alasan;
4. tekan **Simpan pengecualian peserta**.

Pilih **Kembali ke default** untuk menghapus pengecualian.

### Legal hold

Legal hold mencegah data peserta diperlakukan sebagai data yang boleh dihapus menurut jadwal retensi biasa.

1. pilih peserta;
2. isi alasan legal hold;
3. terapkan legal hold;
4. lepaskan hanya setelah dasar penahanan berakhir; dan
5. isi catatan pelepasan.

Menu ini mencatat kebijakan, pengecualian, dan legal hold. Aplikasi tidak menjalankan penghapusan data otomatis. Pelaksanaan penghapusan tetap memerlukan prosedur terpisah yang disahkan institusi.

## 16 Proses terjadwal

Dua skrip harus dijalankan berkala pada lingkungan operasional:

| Skrip | Fungsi |
|---|---|
| `scripts/process_expired_attempts.php` | Mengumpulkan attempt yang melewati batas waktu |
| `scripts/process_scheduled_decisions.php` | Mempublikasikan keputusan terjadwal yang sudah jatuh tempo |

Gunakan Windows Task Scheduler atau scheduler server. Jalankan dengan PHP 8.2 dan path absolut. Contoh jika XAMPP terpasang di `C:\xampp`:

```cmd
C:\xampp\php\php.exe C:\path\ke\aplikasi\scripts\process_expired_attempts.php
C:\xampp\php\php.exe C:\path\ke\aplikasi\scripts\process_scheduled_decisions.php
```

Ganti `C:\path\ke\aplikasi` dengan path sebenarnya. Interval satu menit disarankan untuk operasi resmi.

## 17 Penanganan masalah

| Masalah | Pemeriksaan dan tindakan |
|---|---|
| Setup tidak menemukan `winget` | Pasang App Installer dari Microsoft Store, lalu jalankan setup kembali |
| Ekstensi PHP belum aktif | Setup mengaktifkannya otomatis pada `php.ini`; jika gagal menulis, jalankan `setup.bat` melalui klik kanan lalu **Run as administrator** |
| Ekstensi tetap tidak aktif setelah setup | Berkas DLL ekstensi tidak tersedia pada instalasi PHP tersebut; pasang XAMPP 8.2 standar di `C:\xampp`, lalu ulangi setup |
| MySQL tidak aktif | Periksa XAMPP Control Panel dan konflik port MySQL |
| Akses database ditolak | Periksa host, port, username, dan password MySQL pada `.env` |
| Database lama ditolak | Gunakan database kosong khusus aplikasi pada `DB_DATABASE` |
| Port `8080` dipakai aplikasi lain | Hentikan aplikasi lain tersebut lalu jalankan `setup.bat` kembali |
| Browser tidak terbuka | Buka `http://127.0.0.1:8080` secara manual |
| Tidak dapat login | Gunakan `/login`; periksa identitas, kata sandi, dan status akun |
| Peserta lupa kata sandi | Admin membuka menu **Peserta** lalu menekan **Lihat password**; untuk peserta lama yang dibuat sebelum migrasi `017`, gunakan prosedur reset oleh petugas teknis berwenang |
| Peserta tidak dapat ditugaskan | Pastikan akun aktif, identitas Disetujui, gelombang sama, dan tidak ada penugasan, attempt, atau hasil sebelumnya |
| Impor peserta ditolak | Gunakan template terbaru, pertahankan lembar dan header, periksa kode aktif, email, tahun, batas 5 MB, dan 2.000 baris |
| Impor soal dibatalkan | Perbaiki semua baris tidak valid dan duplikat; satu kesalahan membatalkan seluruh file |
| Bank soal tidak mencukupi | Pastikan kategori `VERBAL`, `NUMERIK`, `LOGIKA`, dan `UMUM` aktif serta memenuhi kuota |
| Token ditolak | Periksa token terbaru, penugasan, status sesi, dan jadwal WIB |
| Kamera atau layar penuh gagal | Periksa izin browser dan perangkat; gunakan HTTPS pada produksi |
| Jawaban gagal tersimpan | Pulihkan koneksi, pilih kembali jawaban, dan tunggu konfirmasi penyimpanan |
| Attempt dijeda | Admin meninjau attempt pada menu Pengawasan selama waktu masih tersedia |
| Attempt tidak terkumpul saat waktu habis | Pastikan proses `process_expired_attempts.php` berjalan |
| Keputusan terjadwal tidak terbit | Pastikan proses `process_scheduled_decisions.php` berjalan dan waktu server benar |
| Keputusan belum terlihat | Pastikan keputusan sudah Dipublikasikan, bukan hanya Disimpan atau Terjadwal |
| Server gagal berjalan | Periksa `runtime/server-error.log` |
| Server tidak berhenti | Jalankan `stop-app.bat` dan periksa `runtime/server.pid` |

## 18 Checklist UAT

### Admin

- [ ] Login mengarah ke Dashboard Admin.
- [ ] Program studi dapat ditambah, diubah, diaktifkan, dan dinonaktifkan.
- [ ] Gelombang menyimpan jumlah soal, durasi, passing grade, dan mode keamanan.
- [ ] Peserta satuan dapat dibuat dan kredensial ditampilkan.
- [ ] Template peserta memiliki lembar data dan `Petunjuk`.
- [ ] Baris peserta valid berhasil dibuat dan baris salah dilaporkan.
- [ ] Kategori dan soal dapat ditambah.
- [ ] Soal dapat diedit, dinonaktifkan, dan diaktifkan kembali.
- [ ] Template bank soal memiliki lembar data dan `Petunjuk`.
- [ ] Satu baris soal yang salah membatalkan seluruh impor.
- [ ] Sesi, jadwal, token, dan penugasan dapat disimpan.
- [ ] Penugasan massal hanya mengambil peserta eligible dari gelombang yang sama.
- [ ] Pengawasan menampilkan attempt, kejadian, dan foto awal.
- [ ] Laporan dapat difilter, diekspor, dan dicetak.
- [ ] Masa simpan, pengecualian, dan legal hold dapat dicatat.

### Panitia

- [ ] Login mengarah ke Dashboard Panitia.
- [ ] Pencarian peserta berfungsi.
- [ ] Persetujuan mewajibkan seluruh checklist V-01 sampai V-10.
- [ ] Catatan diwajibkan untuk Perlu perbaikan dan Ditolak.
- [ ] Peserta Disetujui muncul sebagai eligible.
- [ ] Keputusan dapat disimpan.
- [ ] Publikasi langsung dan terjadwal berfungsi.
- [ ] Laporan dan ekspor CSV dapat diakses.

### Peserta

- [ ] Login dengan nomor peserta, email, atau username berfungsi.
- [ ] Dashboard menampilkan status identitas dan sesi yang benar.
- [ ] Token hanya diterima pada sesi yang ditugaskan dan pada jadwal aktif.
- [ ] Persetujuan ujian tercatat.
- [ ] Kamera, foto awal, dan layar penuh berfungsi.
- [ ] Timer baru berjalan setelah ruang soal dibuka.
- [ ] Navigasi soal dan penyimpanan otomatis berfungsi.
- [ ] Kejadian keamanan tercatat.
- [ ] Pengumpulan menghasilkan nilai dan rincian jawaban.
- [ ] Keputusan hanya terlihat setelah dipublikasikan.

### Operasional

- [ ] Setup dapat dijalankan ulang tanpa mengulang migrasi.
- [ ] `stop-app.bat` hanya menghentikan server aplikasi.
- [ ] Proses attempt kedaluwarsa berjalan sesuai jadwal.
- [ ] Proses publikasi keputusan berjalan sesuai jadwal.
- [ ] Backup database dan `storage/private` dapat dipulihkan.
- [ ] Waktu server dan tampilan WIB sudah benar.

## 19 Checklist sebelum produksi

Sebelum aplikasi digunakan secara resmi:

1. ganti atau hapus akun Admin dan Panitia demonstrasi;
2. buat akun internal dengan identitas individual dan kata sandi kuat;
3. gunakan HTTPS serta web server produksi;
4. lindungi `.env`, database, kunci token, log, dan `storage/private`;
5. sahkan program, gelombang, peserta, bank soal, passing grade, jadwal, dan token;
6. sahkan checklist verifikasi, SOP pengawasan, keputusan, retensi, legal hold, dan prosedur koreksi data;
7. siapkan backup database dan penyimpanan privat, lalu lakukan uji pemulihan;
8. aktifkan proses attempt kedaluwarsa dan publikasi terjadwal;
9. sinkronkan waktu server dan pantau kapasitas penyimpanan;
10. lakukan UAT dengan data sintetis;
11. lakukan pengujian keamanan, beban, browser, perangkat, kamera, dan aksesibilitas; dan
12. tetapkan petugas dukungan untuk gangguan login, data peserta, jaringan, dan perangkat ujian.
