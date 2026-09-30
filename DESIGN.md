---
name: "PMB Politeknik Aceh"
description: "Pusat kendali PMB yang tenang, terbaca, dan berorientasi tindakan untuk Admin, Panitia, dan Peserta."
colors:
  navy: "#12386b"
  navy-deep: "#0a2348"
  maroon: "#8f1937"
  maroon-dark: "#71122b"
  portal-canvas: "#eef3f8"
  portal-panel: "#ffffff"
  portal-panel-soft: "#f6f8fb"
  portal-text: "#17243a"
  portal-muted: "#59697d"
  portal-rule: "#dfe6ee"
  portal-sidebar: "#0a294f"
  portal-sidebar-muted: "#afc2da"
  portal-sidebar-text: "#d9e5f2"
  metric-accent: "#7f9abc"
  focus: "#efb935"
  field-text: "#142033"
  field-border: "#bcc9d9"
  status-success-bg: "#e7f4ec"
  status-success-fg: "#155f3d"
  status-warning-bg: "#fff3d8"
  status-warning-fg: "#744900"
  status-danger-bg: "#fcebea"
  status-danger-fg: "#8e241c"
  status-info-bg: "#e8f3f5"
  status-info-fg: "#174f58"
  status-neutral-bg: "#eef2f4"
  status-neutral-fg: "#43525a"
typography:
  display:
    fontFamily: "Barlow, Segoe UI, sans-serif"
    fontSize: "clamp(26px, 2.3vw, 34px)"
    fontWeight: 800
    lineHeight: 1.05
    letterSpacing: "-0.03em"
  headline:
    fontFamily: "Barlow, Segoe UI, sans-serif"
    fontSize: "22px"
    fontWeight: 800
    lineHeight: 1.1
    letterSpacing: "-0.025em"
  title:
    fontFamily: "Barlow, Segoe UI, sans-serif"
    fontSize: "18px"
    fontWeight: 800
    lineHeight: 1.15
    letterSpacing: "-0.02em"
  body:
    fontFamily: "Barlow, Segoe UI, sans-serif"
    fontSize: "14px"
    fontWeight: 400
    lineHeight: 1.45
    letterSpacing: "normal"
  label:
    fontFamily: "Barlow, Segoe UI, sans-serif"
    fontSize: "13px"
    fontWeight: 600
    lineHeight: 1.3
    letterSpacing: "normal"
  data-label:
    fontFamily: "Barlow, Segoe UI, sans-serif"
    fontSize: "10.5px"
    fontWeight: 800
    lineHeight: 1.25
    letterSpacing: "0.05em"
rounded:
  field: "8px"
  control: "9px"
  navigation: "10px"
  compact-surface: "12px"
  panel: "14px"
  pill: "999px"
spacing:
  nav-gap: "5px"
  control-gap: "10px"
  metric-gap: "14px"
  panel-gap: "16px"
  panel-inset: "20px"
  page-inset: "24px"
  toolbar-gap: "32px"
components:
  primary-action:
    backgroundColor: "{colors.maroon}"
    textColor: "{colors.portal-panel}"
    typography: "{typography.label}"
    rounded: "{rounded.control}"
    padding: "0 15px"
    height: "40px"
  primary-action-hover:
    backgroundColor: "{colors.maroon-dark}"
    textColor: "{colors.portal-panel}"
  quiet-action:
    backgroundColor: "{colors.portal-panel}"
    textColor: "{colors.portal-text}"
    typography: "{typography.label}"
    rounded: "{rounded.control}"
    padding: "0 15px"
    height: "40px"
  action-disabled:
    backgroundColor: "{colors.portal-rule}"
    textColor: "{colors.portal-muted}"
    typography: "{typography.label}"
    rounded: "{rounded.control}"
    padding: "0 15px"
    height: "40px"
  sidebar-item:
    backgroundColor: "transparent"
    textColor: "{colors.portal-sidebar-text}"
    typography: "{typography.label}"
    rounded: "{rounded.navigation}"
    padding: "0 13px"
    height: "42px"
  sidebar-item-active:
    backgroundColor: "{colors.portal-panel}"
    textColor: "{colors.navy-deep}"
  metric-card:
    backgroundColor: "{colors.portal-panel}"
    textColor: "{colors.portal-text}"
    rounded: "{rounded.panel}"
    padding: "18px 19px"
  panel:
    backgroundColor: "{colors.portal-panel}"
    textColor: "{colors.portal-text}"
    rounded: "{rounded.panel}"
    padding: "20px"
  text-field:
    backgroundColor: "{colors.portal-panel}"
    textColor: "{colors.field-text}"
    typography: "{typography.body}"
    rounded: "{rounded.field}"
    padding: "9px 10px"
    height: "43px"
  file-picker:
    backgroundColor: "{colors.portal-panel}"
    textColor: "{colors.portal-text}"
    typography: "{typography.label}"
    rounded: "{rounded.field}"
    padding: "0"
    height: "44px"
  status-success:
    backgroundColor: "{colors.status-success-bg}"
    textColor: "{colors.status-success-fg}"
    typography: "{typography.data-label}"
    rounded: "{rounded.pill}"
    padding: "7px 10px"
  status-warning:
    backgroundColor: "{colors.status-warning-bg}"
    textColor: "{colors.status-warning-fg}"
    typography: "{typography.data-label}"
    rounded: "{rounded.pill}"
    padding: "7px 10px"
  status-danger:
    backgroundColor: "{colors.status-danger-bg}"
    textColor: "{colors.status-danger-fg}"
    typography: "{typography.data-label}"
    rounded: "{rounded.pill}"
    padding: "7px 10px"
  status-info:
    backgroundColor: "{colors.status-info-bg}"
    textColor: "{colors.status-info-fg}"
    typography: "{typography.data-label}"
    rounded: "{rounded.pill}"
    padding: "7px 10px"
  status-neutral:
    backgroundColor: "{colors.status-neutral-bg}"
    textColor: "{colors.status-neutral-fg}"
    typography: "{typography.data-label}"
    rounded: "{rounded.pill}"
    padding: "7px 10px"
---

# Design System: PMB Politeknik Aceh

## Overview

**Creative North Star: "Pusat Kendali PMB yang Tenang"**

Sistem ini terasa seperti meja kendali institusional yang sudah tertata sebelum hari kerja dimulai. Kanvas biru-abu muda memberi ruang napas; sidebar navy menetapkan orientasi; panel putih, garis tipis, dan tipografi Barlow yang padat membuat informasi operasional mudah dipindai tanpa terasa ramai. Tema produksi yang didukung adalah light-only sampai varian gelap dirancang dan divalidasi sebagai sistem lengkap.

Ekspresi visual selalu mengikuti kebenaran proses PMB. Empat metrik per peran membuka setiap dashboard, pekerjaan utama mengambil kira-kira dua pertiga ruang, dan rel prioritas menjaga tindakan berikutnya tetap dekat. Marun muncul hanya ketika pengguna benar-benar dapat bertindak atau ketika sebuah prioritas perlu ditandai; status selalu membawa label teks, bukan warna semata. Ruang ujian melepaskan shell portal dan mempertahankan hanya progres, waktu, navigasi soal, jawaban, dan tindakan pengumpulan agar perhatian peserta tidak terpecah.

**Key Characteristics:**

- Desktop-first dengan fallback struktural yang tetap utuh pada layar sempit.
- Kepadatan operasional yang tenang, bukan dashboard dekoratif atau penuh widget.
- Empat metrik berbasis data nyata dan area kerja yang berbeda menurut peran.
- Panel datar dengan radius lembut, garis tipis, dan bayangan yang sangat terbatas.
- Tindakan serta status selalu jujur terhadap eligibility, penugasan, dan kondisi database.
- Tema produksi light-only; belum ada kontrak visual dark mode.
- Ruang ujian desktop-first yang bebas distraksi dan terpisah dari navigasi portal.

## Colors

Palet menggabungkan navy institusional, marun tindakan, dan netral biru-abu yang dingin agar status serta prioritas terbaca tanpa mengubah ruang kerja menjadi papan warna.

### Primary

- **Institutional Navy** (`navy`): tautan operasional, penekanan institusional, dan hubungan visual dengan identitas Politeknik Aceh.
- **Deep Institutional Navy** (`navy-deep`): teks item navigasi aktif di atas bidang putih.
- **Sidebar Navy** (`portal-sidebar`): bidang orientasi permanen yang membedakan navigasi dari pekerjaan.
- **Metric Accent Blue** (`metric-accent`): garis atas dua piksel pada metrik biasa.

### Secondary

- **Action Maroon** (`maroon`): tindakan utama, tautan prioritas, dan garis metrik yang membutuhkan perhatian.
- **Action Maroon Hover** (`maroon-dark`): umpan balik hover untuk tindakan utama.

### Tertiary

- **Focus Gold** (`focus`): ring dalam tiga piksel untuk identitas fokus, selalu dipasangkan dengan outline luar navy dua piksel agar indikator tetap mencapai kontras nonteks pada bidang terang.
- **Semantic Status Set** (`status-success-*`, `status-warning-*`, `status-danger-*`, `status-info-*`, `status-neutral-*`): pasangan bidang dan tinta untuk kondisi yang tetap dijelaskan dengan teks.

### Neutral

- **Blue-Gray Canvas** (`portal-canvas`): latar halaman utama yang memisahkan panel tanpa bayangan berat.
- **Paper White** (`portal-panel`): permukaan panel, metrik, tanggal, dan navigasi aktif.
- **Soft Table Surface** (`portal-panel-soft`): latar header tabel dan lapisan data sekunder.
- **Operational Ink** (`portal-text`): judul, angka, dan data utama.
- **Readable Muted Blue-Gray** (`portal-muted`): deskripsi, metadata, dan teks bantuan pada tema terang; nilai normatifnya adalah `#59697d`.
- **Thin Blue-Gray Rule** (`portal-rule`): pemisah panel, tabel, dan daftar dengan garis satu piksel.
- **Sidebar Muted** (`portal-sidebar-muted`) dan **Sidebar Text** (`portal-sidebar-text`): hierarki sekunder dan navigasi default di bidang navy.

### Named Rules

**The Reserved Maroon Rule.** Marun hanya menandai tindakan utama, tautan prioritas, atau satu metrik yang perlu perhatian; ia tidak menjadi warna dekoratif massal.

**The Text-Before-Color Rule.** Setiap status harus memiliki label teks yang dapat dipahami tanpa mengandalkan warna.

**The Light-Only Production Rule.** Gunakan palet terang yang terdokumentasi pada seluruh permukaan produksi; jangan mengaktifkan atau mengiklankan dark mode sebelum palet, aset, kontrol native, fokus, kontras, dan seluruh surface divalidasi bersama.

## Typography

**Display Font:** Barlow (with Segoe UI and sans-serif fallback)
**Body Font:** Barlow (with Segoe UI and sans-serif fallback)

**Character:** Satu keluarga sans-serif menjaga dashboard ringkas dan institusional. Perbedaan bobot, ukuran, dan jarak huruf—bukan pergantian font—membentuk hierarki.

### Hierarchy

- **Display** (800, `clamp(26px, 2.3vw, 34px)`, 1.05): judul ruang kerja pada toolbar.
- **Headline** (800, 22px, 1.1): instruksi utama di panel langkah berikutnya.
- **Title** (800, 18px, 1.15): judul panel dan kelompok informasi.
- **Body** (400, 14px, 1.45): deskripsi operasional; lebar baca biasanya dibatasi sekitar 60–65ch.
- **Label** (600, 13px, 1.3): tindakan, navigasi, judul item, dan metadata padat.
- **Data Label** (800, 10.5px, 0.05em letter spacing, uppercase): header tabel dan label data ringkas.

### Named Rules

**The One-Family Rule.** Gunakan Barlow pada seluruh ruang kerja dan bangun hierarki dengan bobot 400, 600, dan 800; jangan menambah font dekoratif ke layar operasional.

## Layout

Shell desktop memakai sidebar tetap 248px dan area utama fleksibel dengan inset halaman 24px hingga 46px. Toolbar ringkas diikuti empat metrik dalam satu baris dengan jarak 14px. Area kerja memakai dua kolom `2fr / 0.82fr`: kolom utama memuat tabel atau langkah kerja, sedangkan rel kanan memuat prioritas dan bantuan; jarak antarpanel konsisten 16px. Pada halaman operasional padat, toolbar dan konteks halaman tetap terlihat dalam satu viewport desktop, sedangkan daftar data panjang menggulir di dalam panelnya sendiri.

Pada lebar sampai 1180px, sidebar menyusut menjadi 220px, metrik menjadi dua kolom, dan proporsi area kerja menjadi `1.7fr / 0.8fr`. Pada 860px, sidebar berubah menjadi header struktural dengan navigasi horizontal yang dapat digulir dan area kerja menjadi satu kolom. Pada 620px, toolbar, metrik, detail peserta, dan tindakan disusun vertikal; tabel mempertahankan konteks dengan overflow horizontal, bukan memotong kolom.

Konteks halaman menyatu dengan heading dan metadata operasional: judul `h1`, deskripsi ringkas, tanggal atau jumlah antrean, status, serta tindakan yang relevan. Peta konteks bersama boleh menyimpan label peran dan struktur navigasi, tetapi tidak diwujudkan sebagai panel terpisah atau eyebrow presentasional di atas heading.

Ruang ujian adalah surface desktop-first dengan lebar minimum 1024px. Ia tidak memakai sidebar portal; command bar sticky merangkum identitas, progres jawaban, status simpan, sisa waktu, dan tindakan pengumpulan. Sistem langsung meminta layar penuh saat pemeriksaan perangkat dan saat ruang ujian dibuka; bila kebijakan browser membutuhkan interaksi pengguna, tampilkan satu konfirmasi pemulihan, bukan toggle permanen. Navigator soal 238–250px mendampingi satu soal aktif per layar dengan tombol sebelumnya dan berikutnya.

### Named Rules

**The Context-in-Heading Rule.** Gabungkan konteks peran dan halaman ke heading, deskripsi, serta metadata/tindakan yang relevan; jangan menambahkan panel konteks atau eyebrow dekoratif yang mengulang informasi.

**The Distraction-Free Exam Rule.** Di ruang ujian, tampilkan hanya informasi dan kontrol yang diperlukan untuk mengerjakan, menavigasi, menyimpan, dan mengumpulkan jawaban.

## Elevation & Depth

Sistem datar secara default. Kedalaman terutama datang dari kontras kanvas terhadap panel, garis satu piksel, header tabel bernada lembut, dan garis aksen dua piksel; panel dashboard tidak memakai bayangan. Bayangan hanya mengangkat tindakan marun agar tetap terlihat sebagai aksi utama.

### Shadow Vocabulary

- **Primary Action Lift** (`0 8px 20px rgba(143,25,55,.16)`): hanya untuk tombol atau tautan aksi utama berwarna marun.

### Named Rules

**The Thin-Rule Rule.** Panel tetap datar saat diam; gunakan garis dan perbedaan tonal sebagai struktur, bukan tumpukan bayangan kartu.

## Shapes

Panel dan metrik memakai sudut lembut 14px. Tombol toolbar memakai radius 9px, item navigasi 10px, alert serta mark logo 12px, dan field 8px. Status berbentuk pill penuh 999px, sedangkan nomor urut prioritas memakai kotak kecil beradius 8px. Hampir semua permukaan dibatasi garis satu piksel agar struktur tetap ringan.

## Components

### Buttons

- **Shape:** tombol toolbar yang ringkas dan membulat lembut (9px), tinggi 40px, dengan padding horizontal 15px.
- **Primary:** marun dengan teks putih, bobot 600, dan bayangan aksi yang terbatas.
- **Hover / Focus:** marun menggelap saat hover; semua aksi mendapat outline fokus emas 3px dengan offset 3px; active state turun 1px.
- **Secondary / Quiet:** panel putih, garis tipis, dan teks gelap; hover mempertegas garis tanpa menambah bayangan.
- **Unavailable:** bidang netral, teks muted, tanpa bayangan atau transform, `cursor: not-allowed`, dan `aria-disabled="true"`.

### Chips

- **Style:** pill ringkas dengan bidang, tinta, dan garis semantik; label dashboard menggunakan ukuran 10.5px.
- **State:** gunakan success, warning, danger, info, atau neutral berdasarkan arti proses dan selalu tampilkan label seperti “Menunggu”, “Perlu perbaikan”, atau “Terverifikasi”.

### Cards / Containers

- **Corner Style:** sudut lembut (14px).
- **Background:** panel putih di atas kanvas biru-abu; header tabel memakai panel-soft.
- **Shadow Strategy:** datar; lihat Thin-Rule Rule.
- **Border:** garis portal satu piksel.
- **Internal Padding:** umumnya 18–20px; metrik memakai 18px 19px.

### Inputs / Fields

- **Style:** tinggi minimum 43px, padding 9px 10px, bidang putih, garis field, dan radius 8px.
- **Focus:** outline emas 3px dengan offset 3px.
- **Error / Disabled:** keadaan tetap dijelaskan dengan teks; kontrol disabled tidak boleh tampak aktif.

### File Picker

- **Structure:** pertahankan input file native yang menutupi seluruh control, label bidang berbahasa Indonesia, tombol semu “Pilih berkas”, dan area nama berkas dalam permukaan setinggi 44px dengan radius 8px.
- **Empty / Selected:** state kosong berbunyi “Belum ada berkas dipilih”; setelah pemilihan, tampilkan nama file aktual dan potong secara elipsis tanpa menghilangkan akses native input.
- **Focus / Disabled / Error:** focus-visible memakai ring dalam emas 3px dan outline luar navy 2px dengan offset 3px; kombinasi dua lapis ini wajib pada tautan, tombol, field, navigator soal, dan file picker. Disabled meredup dan memakai kursor tidak tersedia; invalid memakai `aria-invalid="true"`, garis merah, dan wash merah sangat muda. Pesan validasi tetap berbahasa Indonesia.

### Navigation

- Sidebar desktop memakai bidang navy dan item setinggi 42px. Item default berwarna biru-putih lembut, hover mendapat wash putih tipis dan bergeser 2px, sedangkan item aktif menjadi putih dengan teks navy gelap. Pada layar menengah navigasi berubah menjadi baris horizontal yang dapat digulir, dengan footer tetap terpisah oleh garis.
- Setiap shell portal menyediakan tautan “Lewati ke konten utama” sebagai elemen pertama yang dapat difokuskan. Tautan disembunyikan secara visual saat tidak aktif dan muncul jelas ketika menerima fokus keyboard.
- Pada layar sentuh, tautan navigasi, tombol, field, dan kontrol ringkasan memiliki tinggi target minimum 44px.

### System Recovery Page

- Halaman kesalahan seperti 404 memakai identitas institusi, kode status, penjelasan singkat berbahasa Indonesia, serta dua jalur pemulihan yang jelas: kembali ke beranda dan masuk ke sistem.
- Gunakan kanvas, panel, tipografi, garis, dan tindakan yang sama dengan portal; jangan memakai ilustrasi dekoratif atau istilah teknis yang tidak membantu pengguna pulih.

### Metric Strip

Setiap peran memiliki tepat empat metrik. Metrik memakai angka tabular 32px atau teks ringkas 19px, garis atas biru untuk keadaan biasa, dan satu garis marun untuk prioritas. Nilai serta keterangan selalu berasal dari keadaan aplikasi dan database.

### Priority Rail

Rel prioritas memakai daftar bernomor 27px, pemisah garis, judul singkat, deskripsi kecil, dan tautan marun. Urutan harus mencerminkan pekerjaan yang paling berdampak bagi peran saat itu.

### Participant Next Action

Tindakan peserta bersifat kondisional. Tautan aktif ke sesi hanya muncul ketika peserta eligible dan sesi sudah ditugaskan; selain itu komponen menjadi status noninteraktif dengan label yang menjelaskan apakah verifikasi masih diproses atau penugasan masih ditunggu.

Pada desktop, panel ini mengikuti tinggi kontennya dan ditempatkan di atas bantuan peserta, sementara panel persiapan berada pada rel kanan. Jangan meregangkan isi panel hanya untuk menyamai tinggi rel kanan; status dan tindakan berikutnya harus terbaca tanpa ruang kosong berlebihan.

### Configuration Catalogs

Program studi, jalur masuk, dan gelombang adalah katalog Admin. Jalur masuk aktif menjadi sumber tunggal untuk form peserta dan dropdown template impor XLSX; data yang sudah dipakai tidak boleh dihapus, tetapi dapat dinonaktifkan agar riwayat tetap utuh.

### Exam Assignment

Pada desktop, pilihan sesi dan peserta berada dalam dua kolom sama lebar dengan tombol “Tugaskan” pada baris tindakan tersendiri. Form jadwal memakai dua kolom tanggal, lalu batas nilai dan baris simpan. Penugasan massal dipisahkan oleh garis tipis dan disclosure selebar panel. Pada layar kecil, field menjadi satu kolom tanpa memotong tombol.

### Disclosures and Panel Rhythm

Semua menu buka/tutup memakai elemen native `details` dan `summary`, chevron di sisi kanan, status nonwarna, indikator terbuka, serta fokus keyboard yang terlihat. Summary mengatur padding baris; isi terbuka mengatur padding sendiri agar menu tertutup tidak menyisakan ruang kosong.

Panel profil, daftar peserta, dan daftar sesi adalah shell tanpa padding ganda. Header dan isi memiliki inset konsisten. Form impor dibuka sebagai baris penuh. Katalog program dan jalur masuk berdampingan pada desktop, sedangkan pembuatan gelombang mengambil baris penuh. Jangan mengunci halaman dengan `overflow:hidden` jika membuka form akan memotong isi; area utama tetap dapat digulir dan tabel panjang boleh menggulir sendiri.

### Participant Profile Data

Kelompok Identitas, Pendidikan, dan Pendaftaran memakai header kolom serta pemisah baris penuh yang konsisten. Aturan garis grid generik tidak boleh menghasilkan garis putus, segmen kosong, atau batas kiri pada setiap baris di dalam kelompok.

### Status and Check Icons

Status tetap didahului teks dan warna semantik. Jika sebuah check atau mark penyelesaian diperlukan, gunakan authored inline SVG dengan geometri yang disengaja, `currentColor`, dan `aria-hidden="true"` saat dekoratif; jangan gunakan glyph Unicode, icon font, atau karakter font sebagai ikon status.

### Exam Room

Ruang ujian adalah surface bebas distraksi: command bar sticky, progres jawaban, status simpan, timer server, navigator soal, satu pertanyaan aktif, navigasi sebelumnya/berikutnya, dan tindakan pengumpulan. Layar penuh diminta otomatis dan hanya memunculkan konfirmasi pemulihan ketika browser menolak permintaan tanpa gestur pengguna. Ia memakai kanvas biru-abu serta panel putih yang sama, tetapi sengaja menghilangkan sidebar, metrik dashboard, toggle layar penuh, dan akses cepat yang tidak relevan.

## Do's and Don'ts

### Do:

- **Do** mulai dashboard peran dengan empat metrik yang berasal dari database.
- **Do** pertahankan kolom utama sekitar dua pertiga lebar dan rel prioritas di sisi kanan pada desktop.
- **Do** gunakan label teks bersama setiap warna status dan pertahankan fokus keyboard yang terlihat.
- **Do** ubah tindakan peserta menjadi status noninteraktif yang jujur ketika eligibility atau sesi belum tersedia.
- **Do** hormati `prefers-reduced-motion` dengan memangkas transisi dan animasi menjadi 0.01ms.
- **Do** pertahankan produksi light-only sampai dark mode dirancang dan divalidasi lintas seluruh surface.
- **Do** letakkan konteks halaman pada heading, deskripsi, dan metadata alih-alih membuat panel konteks atau eyebrow tambahan.
- **Do** pertahankan file picker berbahasa Indonesia dengan state kosong, nama file terpilih, fokus, disabled, dan error yang dapat dipahami.
- **Do** gunakan authored inline SVG untuk check atau ikon status, dengan label teks status tetap menjadi sumber makna utama.
- **Do** jaga ruang ujian bebas distraksi dengan hanya menampilkan kontrol yang diperlukan peserta.
- **Do** pertahankan konteks halaman dalam viewport desktop dan gulirkan hanya daftar atau formulir panjang di dalam panel terkait.

### Don't:

- **Don't** menampilkan angka contoh, status buatan, atau klaim yang tidak berasal dari data aplikasi.
- **Don't** menampilkan CTA aktif menuju ujian ketika peserta belum eligible atau belum memiliki sesi.
- **Don't** memakai marun sebagai dekorasi luas atau menambahkan warna status tanpa label.
- **Don't** mengganti struktur garis tipis dengan bayangan berat dan kartu mengambang berlapis-lapis.
- **Don't** memecah pola navigasi, komponen, atau istilah antar-Admin, Panitia, dan Peserta.
- **Don't** mengikuti preferensi dark sistem atau mengekspos dark mode yang belum memiliki spesifikasi dan bukti validasi.
- **Don't** mengulang konteks halaman dalam panel terpisah atau eyebrow presentasional.
- **Don't** membiarkan file picker kembali ke label browser generik yang tidak konsisten dengan bahasa Indonesia dan state desain.
- **Don't** memakai glyph Unicode, emoji, atau icon font untuk check dan ikon status.
