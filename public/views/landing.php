<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Portal ujian online penerimaan mahasiswa baru Politeknik Aceh untuk Peserta, Panitia, dan Admin.">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> | Politeknik Aceh</title>
    <link rel="stylesheet" href="/assets/app.css">
    <script src="/assets/landing.js" defer></script>
</head>
<body class="landing-body compact-landing">
<a class="skip-link" href="#main-content">Lewati ke konten utama</a>
<main id="main-content" tabindex="-1">
    <header class="landing-header" data-landing-nav>
        <div class="landing-nav">
            <a class="institution-mark" href="/" aria-label="Beranda PMB Politeknik Aceh">
                <img class="institution-logo" src="/assets/logo-politeknik-aceh.png" alt="Logo Politeknik Aceh" width="158" height="80">
                <span><b>PMB</b> Politeknik Aceh</span>
            </a>
            <nav aria-label="Navigasi utama">
                <a href="#alur">Alur ujian</a>
                <a href="#persiapan">Persiapan</a>
                <a class="quiet-button" href="/login">Masuk</a>
            </nav>
        </div>
    </header>

    <section class="landing-hero compact-hero" data-hero>
        <div class="hero-copy">
            <h1><span>Ujian terarah.</span> <em>Langkah lebih pasti.</em></h1>
            <p class="hero-lede">Portal resmi ujian online PMB Politeknik Aceh. Masuk dengan akun dari Admin PMB untuk melihat sesi dan jadwal Anda.</p>
            <div class="landing-cta">
                <a class="primary link-button" href="/login">Mulai ujian</a>
            </div>
        </div>
        <figure class="hero-visual">
            <picture>
                <source srcset="/assets/hero-pmb-students.webp" type="image/webp">
                <img src="/assets/hero-pmb-students.png" alt="Ilustrasi mahasiswa Politeknik Aceh bersiap mengikuti ujian online" width="1586" height="992" fetchpriority="high" decoding="async">
            </picture>
            <figcaption><strong>Ruang ujian terpadu</strong><span>Verifikasi, sesi, dan hasil dalam satu ruang peserta.</span></figcaption>
        </figure>
    </section>

    <section class="landing-flow" id="alur" aria-labelledby="alur-heading">
        <div class="landing-flow-intro">
            <h2 id="alur-heading">Alur peserta</h2>
            <p>Akun resmi diberikan Admin PMB melalui kanal resmi.</p>
        </div>
        <ol class="landing-flow-steps">
            <li><strong>Terima akun</strong><span>Kredensial resmi</span></li>
            <li><strong>Verifikasi data</strong><span>Validasi Panitia</span></li>
            <li><strong>Ikuti ujian</strong><span>Sesuai jadwal</span></li>
            <li><strong>Lihat hasil</strong><span>Setelah publikasi</span></li>
        </ol>
    </section>

    <section class="readiness-showcase" id="persiapan" aria-labelledby="persiapan-heading">
        <div class="readiness-showcase-inner" data-reveal>
            <header class="readiness-showcase-heading">
                <h2 id="persiapan-heading">Siap sebelum sesi dimulai.</h2>
                <p>Pastikan tiga hal penting ini agar proses masuk dan pengerjaan ujian tetap lancar.</p>
            </header>
            <ul class="readiness-checklist">
                <li><strong>Kredensial resmi</strong><span>Simpan nomor peserta dan password dari Admin PMB.</span></li>
                <li><strong>Data terverifikasi</strong><span>Pastikan identitas telah disetujui Panitia.</span></li>
                <li><strong>Perangkat siap</strong><span>Gunakan browser modern, koneksi stabil, dan kamera.</span></li>
            </ul>
            <div class="readiness-support" aria-label="Bantuan resmi PMB">
                <strong>Butuh bantuan?</strong>
                <a class="support-action support-action-whatsapp" href="https://wa.me/628116719201" target="_blank" rel="noopener" aria-label="Hubungi PMB melalui WhatsApp, membuka tab baru">
                    <span class="support-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false"><path d="M20 11.7a8 8 0 0 1-11.8 7l-4.2 1.1 1.1-4A8 8 0 1 1 20 11.7Z"/><path d="M8.2 7.7c.3-.3.8-.2 1 .2l.8 1.8c.1.3.1.6-.1.8l-.6.7c.7 1.5 1.9 2.7 3.4 3.4l.7-.7c.2-.2.5-.2.8-.1l1.8.8c.4.2.5.7.2 1-1 1.2-2.5 1.4-4.3.6a10 10 0 0 1-4.2-4.1c-.9-1.9-.7-3.4.5-4.4Z"/></svg>
                    </span>
                    <span>WhatsApp PMB</span>
                </a>
                <a class="support-action support-action-email" href="mailto:pmb@politeknikaceh.ac.id" aria-label="Kirim email kepada PMB">
                    <span class="support-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false"><path d="M4 6.5h16v11H4z"/><path d="m5 8 7 5 7-5"/></svg>
                    </span>
                    <span>Email PMB</span>
                </a>
            </div>
        </div>
    </section>
</main>
<footer class="landing-footer contact-footer" aria-label="Informasi Politeknik Aceh">
    <div class="landing-footer-main">
        <div class="footer-brand">
            <span class="institution-mark"><img class="institution-logo" src="/assets/logo-politeknik-aceh.png" alt="" width="158" height="80" loading="lazy" decoding="async"> PMB Politeknik Aceh</span>
            <p>Portal resmi ujian online penerimaan mahasiswa baru Politeknik Aceh.</p>
        </div>
        <div class="footer-address">
            <strong>Alamat kampus</strong>
            <address>Jln. Politeknik Aceh No. 1<br>Desa Pango Raya, Kec. Ulee Kareng<br>Kota Banda Aceh 23119</address>
        </div>
        <nav class="footer-contact" aria-label="Kontak PMB">
            <strong>Kontak PMB</strong>
            <a href="tel:+628116719201">0811-6719-201</a>
            <a href="mailto:pmb@politeknikaceh.ac.id">pmb@politeknikaceh.ac.id</a>
            <a href="https://politeknikaceh.ac.id/" target="_blank" rel="noopener">politeknikaceh.ac.id<span class="visually-hidden"> (membuka tab baru)</span></a>
        </nav>
    </div>
    <div class="landing-footer-meta">
        <span>&copy; <?= date('Y') ?> Politeknik Aceh</span>
        <span>Waktu sistem menggunakan WIB</span>
    </div>
</footer>
</body>
</html>
