<?php
$e = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$wib = static function (string $value): string {
    return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('d M Y H:i') . ' WIB';
};
$attemptLabels = ['IN_PROGRESS' => 'Sedang berjalan', 'PAUSED_REVIEW' => 'Dijeda untuk review', 'SUBMITTED' => 'Sudah dikumpulkan'];
$attemptTones = ['IN_PROGRESS' => 'info', 'PAUSED_REVIEW' => 'warning', 'SUBMITTED' => 'success'];
$portalRole = 'ADMIN';
$portalActive = 'monitoring';
require __DIR__ . '/partials/portal-context.php';
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= $e($title) ?> · Politeknik Aceh</title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body class="portal-body"><div class="portal-shell" data-dashboard-role="ADMIN">
<?php require __DIR__ . '/partials/portal-sidebar.php'; ?>
<main id="main-content" tabindex="-1" class="portal-main portal-workspace monitoring-workspace">
    <header class="portal-toolbar portal-page-toolbar">
        <div><h1>Pengawasan ujian</h1><p>Tinjau attempt aktif, insiden keamanan, dan riwayat ujian peserta.</p></div>
        <div class="portal-toolbar-actions"><span class="portal-date"><?= $e($monitoringPaginator->total) ?> attempt</span></div>
    </header>

    <?php if ($monitoringSuccess): ?><div class="alert success-alert"><?= $e($monitoringSuccess) ?></div><?php endif; ?>
    <?php if ($monitoringError): ?><div class="alert error"><?= $e($monitoringError) ?></div><?php endif; ?>

    <section class="monitoring-list">
        <?php if ($monitoringRows === []): ?>
            <article class="card compact"><h2>Belum ada attempt untuk diawasi</h2><p class="intro">Attempt aktif, dalam review, dan riwayat yang sudah dikumpulkan akan tampil di sini.</p></article>
        <?php endif; ?>

        <?php foreach ($monitoringRows as $row): ?>
            <article class="card compact monitoring-card">
                <div>
                    <h2><?= $e($row['registration_number']) ?> · <?= $e($row['full_name']) ?></h2>
                    <p class="monitoring-state"><?= $e($attemptLabels[$row['attempt_status']] ?? $row['attempt_status']) ?> · <?= (int) $row['violation_count'] ?> pelanggaran</p>
                    <p class="intro">
                        <?= $e($row['session_name']) ?> · Mulai <?= $e($wib($row['started_at'])) ?> · Berakhir attempt <?= $e($wib($row['expires_at'])) ?>
                        <?php if ($row['attempt_status'] === 'SUBMITTED' && $row['submitted_at']): ?> · Dikumpulkan <?= $e($wib($row['submitted_at'])) ?><?php endif; ?>
                    </p>
                    <div class="monitoring-meta">
                        <span><?= (int) $row['event_count'] ?> event keamanan</span>
                        <?php if ($row['photo_id']): ?>
                            <a class="text-link" target="_blank" rel="noopener" href="/admin/foto-proctoring?foto=<?= urlencode($row['photo_id']) ?>">Buka foto awal privat</a>
                        <?php else: ?>
                            <span>Foto awal belum tersedia</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="monitoring-action">
                    <?php if ($row['attempt_status'] === 'PAUSED_REVIEW'): ?>
                        <span class="status-pill status-warning">Dijeda untuk review</span>
                        <form method="post" action="/admin/pengawasan/lanjutkan"><input type="hidden" name="csrf" value="<?= $e($_SESSION['csrf']) ?>"><input type="hidden" name="attempt_id" value="<?= $e($row['attempt_id']) ?>"><button class="primary">Lanjutkan setelah review</button></form>
                        <small>Keputusan ini dicatat. Waktu ujian tidak diperpanjang.</small>
                    <?php elseif ($row['attempt_status'] === 'SUBMITTED'): ?>
                        <span class="status-pill status-success">Sudah dikumpulkan</span><small>Riwayat dan foto awal tetap privat.</small>
                    <?php else: ?>
                        <span class="status-pill status-<?= $e($attemptTones[$row['attempt_status']] ?? 'neutral') ?>"><?= $e($attemptLabels[$row['attempt_status']] ?? $row['attempt_status']) ?></span>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
        <?= $monitoringPaginator->links('/admin/pengawasan') ?>
    </section>
</main></div>
</body>
</html>
