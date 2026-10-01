<?php
$e = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$portalRole = 'PARTICIPANT';
$portalActive = 'exams';
require __DIR__ . '/partials/portal-context.php';
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= $e($title) ?> · Politeknik Aceh</title><link rel="stylesheet" href="/assets/app.css"></head><body class="portal-body exam-sessions-body"><div class="portal-shell" data-dashboard-role="PARTICIPANT">
<?php require __DIR__ . '/partials/portal-sidebar.php'; ?>
<main id="main-content" tabindex="-1" class="portal-main portal-workspace exam-sessions-workspace">
<header class="portal-toolbar portal-page-toolbar"><div><h1>Sesi ujian saya</h1><p>Waktu server WIB menjadi acuan. Tindakan yang tersedia mengikuti status ujian Anda.</p></div><div class="portal-toolbar-actions"><a class="quiet-button" href="/dashboard">Ringkasan peserta</a></div></header>
<?php if ($examError): ?><div class="alert error"><?= $e($examError) ?></div><?php endif; ?>
<?php if ($sessions === []): ?><div class="card">Belum ada sesi ujian yang ditugaskan. Hubungi Panitia bila status administratif Anda sudah lengkap.</div><?php endif; ?>
<section class="session-list">
<?php foreach ($sessions as $session):
    $attemptStatus = $session['attempt_status'] ?? null;
    $isCompleted = (bool) $session['result_id'] || $attemptStatus === 'SUBMITTED';
    $statusLabel = $isCompleted ? 'Ujian selesai' : ($attemptStatus === 'PAUSED_REVIEW' ? 'Menunggu review Admin' : ($attemptStatus === 'IN_PROGRESS' ? 'Sedang berlangsung' : ($attemptStatus === 'READY' ? 'Pemeriksaan belum selesai' : 'Siap dimulai')));
?>
<article class="card compact session-card"><div><span class="status-pill status-<?= $isCompleted ? 'success' : ($attemptStatus === 'PAUSED_REVIEW' ? 'warning' : ($attemptStatus === 'IN_PROGRESS' ? 'info' : 'neutral')) ?>"><?= $e($statusLabel) ?></span><h2><?= $e($session['name']) ?></h2><p>Jadwal: <?= $e($session['starts_at_wib']) ?> sampai <?= $e($session['ends_at_wib']) ?></p><p><?= $e($session['duration_minutes']) ?> menit · <?= $e($session['security_mode'] === 'PROCTORING_LITE' ? 'Kamera dan layar penuh' : 'Layar penuh') ?></p></div><div class="session-action">
<?php if ($isCompleted): ?><a class="primary link-button" href="/ujian/hasil?attempt=<?= urlencode($session['attempt_id']) ?>">Lihat status hasil</a>
<?php elseif ($attemptStatus === 'PAUSED_REVIEW'): ?><div class="notice alert"><b>Attempt dijeda</b><br><small>Admin perlu meninjau insiden sebelum ujian dapat dilanjutkan. Timer server tetap berlaku.</small></div>
<?php elseif ($attemptStatus === 'IN_PROGRESS'): ?><a class="primary link-button" href="<?= $session['has_photo'] ? '/ujian/ruang' : '/ujian/security' ?>?attempt=<?= urlencode($session['attempt_id']) ?>"><?= $session['has_photo'] ? 'Lanjutkan ujian' : 'Lanjutkan pemeriksaan' ?></a>
<?php elseif ($attemptStatus === 'READY'): ?><a class="primary link-button" href="/ujian/security?attempt=<?= urlencode($session['attempt_id']) ?>">Lanjutkan pemeriksaan perangkat</a>
<?php else: ?><form method="post" action="/ujian/token"><input type="hidden" name="csrf" value="<?= $e($_SESSION['csrf']) ?>"><input type="hidden" name="session_id" value="<?= $e($session['id']) ?>"><?php if ($session['token_required']): ?><label>Token sesi<input name="token" autocomplete="off" required><small>Token diberikan Panitia/Admin melalui kanal resmi setelah Anda ditugaskan.</small></label><?php else: ?><input type="hidden" name="token" value=""><div class="notice"><b>Sesi tanpa token</b><br><small>Anda dapat masuk setelah menyetujui aturan ujian di bawah ini.</small></div><?php endif; ?><label class="consent"><input type="checkbox" name="exam_consent" value="1" required><span>Saya menyetujui aturan ujian, fullscreen/kamera, foto awal, dan pencatatan insiden keamanan.</span></label><button class="primary"><?= $session['token_required'] ? 'Validasi token & masuk' : 'Masuk sesi' ?></button></form><?php endif; ?>
</div></article><?php endforeach; ?>
</section></main></div></body></html>
