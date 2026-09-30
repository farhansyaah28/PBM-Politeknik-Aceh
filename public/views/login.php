<?php $e = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); ?>
<!doctype html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= $e($title) ?> | Politeknik Aceh</title><link rel="stylesheet" href="/assets/app.css"></head>
<body class="auth-body">
<main class="auth-shell">
    <a class="institution-mark auth-brand" href="/"><img class="institution-logo" src="/assets/logo-politeknik-aceh.png" alt="Logo Politeknik Aceh"><span><b>PMB</b> Politeknik Aceh</span></a>
    <section class="auth-layout" aria-labelledby="login-heading">
        <article class="auth-card">
            <h1 id="login-heading"><span>Masuk ke</span><span>sistem ujian</span></h1>
            <p class="intro"><strong>Satu login untuk Peserta, Panitia, dan Admin.</strong><span>Sistem membuka dashboard sesuai peran akun Anda.</span></p>
            <?php if (isset($_GET['logout'])): ?><div class="alert notice" role="status">Anda telah keluar dari akun.</div><?php endif; ?>
            <?php if ($loginError): ?><div class="alert error" role="alert"><?= $e($loginError) ?></div><?php endif; ?>
            <form class="auth-form" method="post" action="/login" novalidate>
                <input type="hidden" name="csrf" value="<?= $e($_SESSION['csrf']) ?>">
                <div class="stack">
                    <label><span class="auth-field-label">Email, username, atau nomor peserta</span><input name="identifier" value="<?= $e($loginOld['identifier'] ?? '') ?>" autocomplete="username" autofocus required></label>
                    <label><span class="auth-field-label">Password</span><input type="password" name="password" autocomplete="current-password" required></label>
                </div>
                <button class="primary auth-submit" type="submit">Masuk</button>
            </form>
            <aside class="auth-help" aria-label="Bantuan akses">
                <strong>Bantuan akses</strong>
                <p>Akun peserta diberikan Admin PMB. Jika akses bermasalah, hubungi Panitia dan jangan membagikan password.</p>
            </aside>
        </article>
        <figure class="auth-visual">
            <picture>
                <source srcset="/assets/login-pmb-student.webp" type="image/webp">
                <img src="/assets/login-pmb-student.png" alt="Ilustrasi mahasiswi Politeknik Aceh menggunakan laptop" width="1122" height="1402" fetchpriority="high" decoding="async">
            </picture>
            <figcaption><span>Ujian online PMB</span><strong>Akses tertib.<br>Fokus pada kemampuanmu.</strong></figcaption>
        </figure>
    </section>
</main>
</body>
</html>
