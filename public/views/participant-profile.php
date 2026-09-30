<?php
$e = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$identityLabels = ['PENDING'=>'Menunggu verifikasi','NEEDS_CORRECTION'=>'Perlu perbaikan','APPROVED'=>'Terverifikasi','REJECTED'=>'Ditolak'];
$identityStatus = $identityLabels[$participant['verification_status']] ?? 'Dalam peninjauan';
$identityTone = match ($participant['verification_status']) {'APPROVED'=>'success','NEEDS_CORRECTION'=>'warning','REJECTED'=>'danger',default=>'info'};
$portalRole = 'PARTICIPANT';
$portalActive = 'profile';
require __DIR__ . '/partials/portal-context.php';
?>
<!doctype html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= $e($title) ?> | Politeknik Aceh</title><link rel="stylesheet" href="/assets/app.css"></head>
<body class="portal-body participant-profile-body"><div class="portal-shell" data-dashboard-role="PARTICIPANT">
  <?php require __DIR__ . '/partials/portal-sidebar.php'; ?>
  <main id="main-content" tabindex="-1" class="portal-main portal-workspace participant-profile-workspace">
    <header class="portal-toolbar portal-page-toolbar">
      <div><h1>Profil peserta</h1><p>Periksa data identitas, pendidikan, dan pendaftaran Anda.</p></div>
      <div class="portal-toolbar-actions"><span class="status-pill status-<?= $e($identityTone) ?>"><?= $e($identityStatus) ?></span><a class="quiet-button" href="/dashboard">Kembali ke dashboard</a></div>
    </header>

    <?php if ($participant['verification_status'] === 'NEEDS_CORRECTION' && !empty($participant['identity_note'])): ?>
      <section class="portal-alert" role="alert"><div><strong>Data perlu diperbaiki</strong><p><?= nl2br($e($participant['identity_note'])) ?></p></div><small>Ditinjau <?= $e($participant['identity_reviewed_at_wib'] ?? '') ?></small></section>
    <?php endif; ?>

    <section class="portal-panel participant-profile-panel" aria-labelledby="participant-profile-heading">
      <header class="portal-panel-heading"><div><h2 id="participant-profile-heading">Data peserta</h2><p>Hubungi Panitia jika terdapat data yang tidak sesuai.</p></div></header>
      <div class="participant-profile-groups">
        <section><h3>Identitas &amp; kontak</h3><dl class="portal-data-grid"><div><dt>Nomor peserta</dt><dd class="tabular-value"><?= $e($participant['registration_number']) ?></dd></div><div><dt>Nama lengkap</dt><dd><?= $e($participant['full_name']) ?></dd></div><div><dt>Email</dt><dd><?= $e($participant['email']) ?></dd></div><div><dt>Telepon</dt><dd><?= $e($participant['phone_number']) ?></dd></div></dl></section>
        <section><h3>Pendidikan</h3><dl class="portal-data-grid"><div><dt>Asal sekolah</dt><dd><?= $e($participant['school_name']) ?></dd></div><div><dt>Tahun lulus</dt><dd><?= $e($participant['graduation_year']) ?></dd></div></dl></section>
        <section><h3>Pendaftaran</h3><dl class="portal-data-grid"><div><dt>Jalur masuk</dt><dd><?= $e($participant['admission_path']) ?></dd></div><div><dt>Program studi</dt><dd><?= $e($participant['program_choice']) ?></dd></div><div><dt>Gelombang</dt><dd><?= $e($participant['wave']) ?></dd></div><div><dt>Status verifikasi</dt><dd><?= $e($identityStatus) ?></dd></div></dl></section>
      </div>
    </section>

    <aside class="portal-panel portal-help-panel participant-profile-help">
      <h2>Data tidak sesuai?</h2>
      <p>Hubungi Panitia dengan menyebutkan nama dan nomor peserta agar pemeriksaan lebih cepat.</p>
      <div class="participant-help-links"><a href="https://wa.me/628116719201">WhatsApp 0811-6719-201</a><a href="mailto:pmb@politeknikaceh.ac.id">pmb@politeknikaceh.ac.id</a></div>
    </aside>
  </main>
</div></body></html>
