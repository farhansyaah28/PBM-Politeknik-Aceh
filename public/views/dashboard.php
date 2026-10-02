<?php
$e = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$identityLabels = ['PENDING'=>'Menunggu verifikasi','NEEDS_CORRECTION'=>'Perlu perbaikan','APPROVED'=>'Terverifikasi','REJECTED'=>'Ditolak'];
$identityStatus = $identityLabels[$participant['verification_status']] ?? 'Dalam peninjauan';
$identityTone = match ($participant['verification_status']) {'APPROVED'=>'success','NEEDS_CORRECTION'=>'warning','REJECTED'=>'danger',default=>'info'};
$examTone = match ($participant['exam_summary']['state']) {'COMPLETED'=>'success','PAUSED_REVIEW'=>'warning','IN_PROGRESS','READY','ASSIGNED'=>'info',default=>'neutral'};
$examStateLabels = ['COMPLETED'=>'Selesai','PAUSED_REVIEW'=>'Dalam peninjauan','IN_PROGRESS'=>'Sedang berlangsung','READY'=>'Persiapan perangkat','ASSIGNED'=>'Sudah ditugaskan','UNASSIGNED'=>'Belum ditugaskan'];
$latestResult = $participant['results'][0] ?? null;
$hasHiddenResult = $participant['has_hidden_result'] ?? false;
$allResults = $participant['all_results'] ?? [];
$latestAllResult = $allResults[0] ?? null;
$decisionPublished = $latestAllResult && ($latestAllResult['decision_communication_status'] ?? null) === 'PUBLISHED';
$decisionLabel = !$decisionPublished ? 'Menunggu keputusan' : (($latestAllResult['official_decision'] ?? '') === 'PASSED' ? 'Lulus' : 'Tidak lulus');
$decisionTone = !$decisionPublished ? 'info' : (($latestAllResult['official_decision'] ?? '') === 'PASSED' ? 'success' : 'danger');
$currentSession = $participant['exam_sessions'][0] ?? null;
$tokenRequired = $currentSession ? (bool) ($currentSession['token_required'] ?? true) : null;
$automaticDecisionLabel = !$latestResult ? null : (($latestResult['automatic_decision'] ?? '') === 'PASSED' ? 'Lulus' : 'Tidak lulus');
$examActionAvailable = $participant['eligible'] && $currentSession !== null;
$examUnavailableLabel = $participant['eligible'] ? 'Menunggu penugasan' : 'Verifikasi diproses';
$nextStepTitle = !$participant['eligible']
    ? 'Selesaikan verifikasi identitas'
    : ($currentSession ? $participant['exam_summary']['title'] : 'Menunggu penugasan sesi');
$nextStepDescription = !$participant['eligible']
    ? 'Panitia sedang memeriksa data Anda. Ikuti catatan perbaikan bila tersedia.'
    : ($currentSession
        ? $participant['exam_summary']['description']
        : 'Data Anda telah terverifikasi. Admin akan menugaskan sesi ujian dan jadwalnya akan tampil otomatis di halaman ini.');
$portalRole = 'PARTICIPANT';
$portalActive = 'dashboard';
require __DIR__ . '/partials/portal-context.php';
?>
<!doctype html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= $e($title) ?> | Politeknik Aceh</title><link rel="stylesheet" href="/assets/app.css"></head>
<body class="portal-body role-dashboard-body participant-dashboard-body"><div class="portal-shell" data-dashboard-role="PARTICIPANT">
  <?php require __DIR__ . '/partials/portal-sidebar.php'; ?>
  <main id="main-content" tabindex="-1" class="portal-main">
    <header class="portal-toolbar participant-portal-toolbar">
      <div><h1>Selamat datang, <?= $e($participant['full_name']) ?></h1><p><?= $e($participant['registration_number']) ?> | <?= $e($participant['program_choice']) ?></p></div>
      <div class="portal-toolbar-actions"><span class="status-pill status-<?= $e($identityTone) ?>"><?= $e($identityStatus) ?></span><?php if ($examActionAvailable): ?><a class="primary link-button" href="/ujian">Buka sesi ujian</a><?php else: ?><span class="primary link-button portal-disabled-action" aria-disabled="true"><?= $e($examUnavailableLabel) ?></span><?php endif; ?></div>
    </header>

    <?php if ($participant['verification_status'] === 'NEEDS_CORRECTION' && !empty($participant['identity_note'])): ?><section class="portal-alert" role="alert"><div><strong>Tindakan diperlukan</strong><p><?= nl2br($e($participant['identity_note'])) ?></p></div><small>Ditinjau <?= $e($participant['identity_reviewed_at_wib'] ?? '') ?></small></section><?php endif; ?>

    <section class="portal-metrics" aria-label="Ringkasan status peserta">
      <article class="portal-metric"><span>Verifikasi identitas</span><strong class="portal-metric-text"><?= $e($identityStatus) ?></strong><small><?= $participant['eligible'] ? 'Data siap untuk penugasan ujian' : 'Menunggu pemeriksaan Panitia' ?></small></article>
      <article class="portal-metric"><span>Status sesi</span><strong class="portal-metric-text"><?= $e($participant['eligible'] ? $participant['exam_summary']['title'] : 'Belum dapat diakses') ?></strong><small><?= $e($currentSession['name'] ?? 'Belum ada sesi') ?></small></article>
      <article class="portal-metric">
        <span>Nilai terbaru</span>
        <?php if ($latestResult): ?>
          <strong><?= $e(number_format((float) $latestResult['score'], 2, ',', '.')) ?></strong>
          <small>Hasil ujian telah dinilai</small>
        <?php elseif ($hasHiddenResult): ?>
          <strong class="portal-metric-text" style="color:var(--portal-muted);">Disembunyikan</strong>
          <small>Nilai ujian disembunyikan oleh Panitia</small>
        <?php else: ?>
          <strong>-</strong>
          <small>Belum ada hasil ujian</small>
        <?php endif; ?>
      </article>
      <article class="portal-metric <?= $decisionPublished ? '' : 'portal-metric-priority' ?>"><span>Keputusan resmi</span><strong class="portal-metric-text"><?= $e($decisionLabel) ?></strong><small><?= $decisionPublished ? 'Keputusan telah dipublikasikan' : 'Diumumkan melalui sistem setelah ditetapkan' ?></small></article>
    </section>

    <section class="participant-dashboard-grid">
        <article class="portal-panel participant-next-panel">
          <header class="portal-panel-heading"><div><h2>Langkah Anda berikutnya</h2><p>Status diperbarui otomatis sesuai proses PMB.</p></div><span class="status-pill status-<?= $e($examTone) ?>"><?= $e($examStateLabels[$participant['exam_summary']['state']] ?? 'Dalam proses') ?></span></header>
          <div class="participant-next-content"><div><h3><?= $e($nextStepTitle) ?></h3><p><?= $e($nextStepDescription) ?></p></div><?php if ($examActionAvailable): ?><a class="primary link-button" href="/ujian">Lihat sesi saya</a><?php else: ?><span class="primary link-button portal-disabled-action" aria-disabled="true"><?= $e($examUnavailableLabel) ?></span><?php endif; ?></div>
          <?php if ($currentSession): ?><dl class="participant-session-strip"><div><dt>Sesi</dt><dd><?= $e($currentSession['name']) ?></dd></div><div><dt>Mulai</dt><dd><?= $e($currentSession['starts_at_wib'] ?? '-') ?></dd></div><div><dt>Selesai</dt><dd><?= $e($currentSession['ends_at_wib'] ?? '-') ?></dd></div><div><dt>Durasi</dt><dd><?= $e($currentSession['duration_minutes']) ?> menit</dd></div></dl><?php endif; ?>
        </article>

        <article class="portal-panel portal-priority-panel participant-preparation-panel"><header class="portal-panel-heading"><div><h2>Persiapan ujian</h2><p>Tiga hal penting sebelum mulai.</p></div></header><ol class="portal-priority-list">
          <li><span>1</span><div><strong>Gunakan perangkat desktop</strong><p>Pastikan browser terbaru, kamera, dan koneksi stabil.</p></div></li>
          <li><span>2</span><div><strong><?= $tokenRequired === false ? 'Sesi tanpa token' : 'Siapkan token sesi' ?></strong><p><?= $tokenRequired === false ? 'Anda dapat masuk langsung setelah menyetujui aturan ujian.' : 'Token disampaikan melalui kanal resmi Panitia.' ?></p></div></li>
          <li><span>3</span><div><strong>Ikuti pemeriksaan keamanan</strong><p>Izinkan kamera dan tetap berada di halaman ujian.</p></div></li>
        </ol></article>

        <div class="participant-support-stack<?= ($latestResult || $latestAllResult) ? '' : ' participant-support-stack-single' ?>">
          <?php if ($latestResult): ?>
            <article class="portal-panel participant-result-panel"><header><div><h2>Hasil terbaru</h2><span class="status-pill status-<?= $e($decisionTone) ?>"><?= $e($decisionLabel) ?></span></div><strong><?= $e(number_format((float) $latestResult['score'], 2, ',', '.')) ?></strong><small>Nilai ujian &bull; batas lulus <?= $e(number_format((float) $latestResult['result_passing_grade'], 2, ',', '.')) ?></small><p>Kategori otomatis: <b><?= $e($automaticDecisionLabel) ?></b>. Keputusan resmi tetap mengikuti publikasi Panitia.</p></header><a href="/ujian/hasil?attempt=<?= urlencode($latestResult['attempt_id']) ?>">Lihat rincian hasil</a></article>
          <?php elseif ($latestAllResult): ?>
            <article class="portal-panel participant-result-panel"><header><div><h2>Hasil terbaru</h2><span class="status-pill status-<?= $e($decisionTone) ?>"><?= $e($decisionLabel) ?></span></div><strong style="font-size:24px; color:var(--portal-muted, #64748b);">Disembunyikan</strong><small>Nilai ujian disembunyikan oleh Panitia</small><p>Nilai angka ujian disembunyikan. Keputusan resmi Panitia dapat dilihat pada rincian hasil.</p></header><a href="/ujian/hasil?attempt=<?= urlencode($latestAllResult['attempt_id']) ?>">Lihat rincian hasil</a></article>
          <?php endif; ?>
          <article class="portal-panel portal-help-panel"><h2>Butuh bantuan?</h2><p>Hubungi Panitia dengan menyebutkan nama dan nomor peserta agar pemeriksaan lebih cepat.</p><div class="participant-help-links"><a href="https://wa.me/628116719201">WhatsApp 0811-6719-201</a><a href="mailto:pmb@politeknikaceh.ac.id">pmb@politeknikaceh.ac.id</a></div></article>
        </div>
    </section>
  </main>
</div></body></html>
