<?php
$e = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$wib = static fn (string $value): string => (new DateTimeImmutable($value, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('d M Y H:i') . ' WIB';
$decisionLabel = static fn (?string $value): string => $value === 'PASSED' ? 'Lulus' : ($value === 'NOT_PASSED' ? 'Belum dinyatakan lulus' : 'Belum ditetapkan');
$isAdminReport = ($reportPortal ?? 'COMMITTEE') === 'ADMIN';
$reportBasePath = $isAdminReport ? '/admin/laporan' : '/panitia/laporan';
$decisionTotal = max(1, array_sum(array_column($report['visualizations']['decision_distribution'], 'count')));
$portalRole = $isAdminReport ? 'ADMIN' : 'COMMITTEE';
$portalActive = 'reports';
require __DIR__ . '/partials/portal-context.php';
?>
<!doctype html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= $e($title) ?> | Politeknik Aceh</title><link rel="stylesheet" href="/assets/app.css"></head>
<body class="portal-body<?= $printMode ? ' print-mode' : '' ?>"><div class="portal-shell" data-dashboard-role="<?= $e($portalRole) ?>">
  <?php require __DIR__ . '/partials/portal-sidebar.php'; ?>
  <main id="main-content" tabindex="-1" class="portal-main portal-workspace report-page">
  <header class="portal-toolbar portal-page-toolbar no-print"><div><h1>Laporan hasil PMB</h1><p>Ringkasan operasional, sebaran keputusan, dan kinerja hasil per program studi.</p></div><?php if (!$printMode): ?><div class="portal-toolbar-actions"><a class="quiet-button" href="<?= $e($reportBasePath) ?>/csv">Ekspor CSV</a><a class="primary link-button" target="_blank" rel="noopener" href="<?= $e($reportBasePath) ?>/cetak">Cetak laporan</a></div><?php endif; ?></header>

  <section class="report-summary" aria-label="Ringkasan laporan"><article><span>Peserta terdaftar</span><b><?= $e($report['summary']['participants']) ?></b></article><article><span>Peserta eligible</span><b><?= $e($report['summary']['eligible_participants']) ?></b></article><article><span>Hasil dinilai</span><b><?= $e($report['summary']['scored_results']) ?></b></article><article><span>Keputusan dipublikasikan</span><b><?= $e($report['summary']['published_decisions']) ?></b></article></section>

  <section class="report-visualizations" aria-label="Visualisasi laporan">
    <figure class="report-chart" id="decision-distribution-chart" aria-labelledby="decision-chart-title">
      <figcaption><h2 id="decision-chart-title">Sebaran keputusan resmi</h2><p>Memisahkan keputusan yang telah diumumkan dari hasil yang masih menunggu proses Panitia.</p></figcaption>
      <div class="chart-list"><?php foreach ($report['visualizations']['decision_distribution'] as $item): $tone = $item['key'] === 'PASSED' ? 'success' : ($item['key'] === 'NOT_PASSED' ? 'danger' : 'warning'); ?><div class="chart-row"><div class="chart-label"><span><?= $e($item['label']) ?></span><b><?= $e($item['count']) ?></b></div><progress class="chart-progress chart-progress-<?= $e($tone) ?>" max="<?= $e($decisionTotal) ?>" value="<?= $e($item['count']) ?>" aria-label="<?= $e($item['label']) ?>: <?= $e($item['count']) ?> dari <?= $e($decisionTotal) ?> hasil"></progress></div><?php endforeach; ?></div>
    </figure>
    <figure class="report-chart" id="program-performance-chart" aria-labelledby="program-chart-title">
      <figcaption><h2 id="program-chart-title">Rata-rata nilai per program</h2><p>Bandingkan kecenderungan nilai tanpa menggantikan pemeriksaan data peserta pada tabel.</p></figcaption>
      <?php if ($report['visualizations']['program_performance'] === []): ?><div class="chart-empty">Belum ada hasil yang dapat divisualisasikan.</div><?php else: ?><div class="chart-list"><?php foreach ($report['visualizations']['program_performance'] as $item): ?><div class="chart-row"><div class="chart-label"><span><?= $e($item['label']) ?><small><?= $e($item['participant_count']) ?> peserta</small></span><b><?= $e(number_format((float) $item['average_score'], 1, ',', '.')) ?></b></div><progress class="chart-progress chart-progress-info" max="100" value="<?= $e($item['average_score']) ?>" aria-label="Rata-rata nilai <?= $e($item['label']) ?>: <?= $e(number_format((float) $item['average_score'], 1, ',', '.')) ?> dari 100"></progress></div><?php endforeach; ?></div><?php endif; ?>
    </figure>
  </section>

  <section class="card compact report-table-wrap"><table class="report-table"><caption class="visually-hidden">Daftar hasil ujian PMB</caption><thead><tr><th scope="col">Nomor peserta</th><th scope="col">Peserta</th><th scope="col">Program / gelombang</th><th scope="col">Nilai</th><th scope="col">Rincian jawaban</th><th scope="col">Keputusan resmi</th><th scope="col">Waktu nilai</th></tr></thead><tbody><?php if ($report['rows'] === []): ?><tr><td colspan="7"><div class="table-empty"><b>Belum ada hasil ujian</b><span>Data akan muncul setelah peserta menyelesaikan ujian dan hasil dinilai.</span></div></td></tr><?php endif; ?><?php foreach ($report['rows'] as $row): $published = ($row['communication_status'] ?? null) === 'PUBLISHED'; $tone = !$published ? 'warning' : ($row['official_decision'] === 'PASSED' ? 'success' : 'danger'); ?><tr><td><?= $e($row['registration_number']) ?></td><td><b><?= $e($row['full_name']) ?></b></td><td><?= $e($row['program_name']) ?><br><small><?= $e($row['wave_name']) ?></small></td><td><b class="table-score"><?= $e(number_format((float) $row['score'], 2, ',', '.')) ?></b></td><td><?= $e($row['correct_count']) ?> benar, <?= $e($row['incorrect_count']) ?> salah, <?= $e($row['unanswered_count']) ?> kosong</td><td><span class="status-pill status-<?= $e($tone) ?>"><?= $e($decisionLabel($row['official_decision'])) ?></span><small class="decision-publication-state"><?= $published ? 'Sudah dipublikasikan' : 'Menunggu publikasi' ?></small></td><td><?= $e($wib($row['scored_at'])) ?></td></tr><?php endforeach; ?></tbody></table><?php if(!$printMode&&$report['paginator']):?><?= $report['paginator']->links($reportBasePath) ?><?php endif;?></section>
</main></div><?php if ($printMode): ?><script>window.addEventListener('load',()=>window.print())</script><?php endif; ?></body>
</html>
