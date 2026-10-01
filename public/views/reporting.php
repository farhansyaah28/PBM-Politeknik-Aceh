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
<?php 
$filterQueryParams = [];
if (!empty($report['filters']['session_id'])) $filterQueryParams['session_id'] = $report['filters']['session_id'];
if (!empty($report['filters']['wave_id'])) $filterQueryParams['wave_id'] = $report['filters']['wave_id'];
if (!empty($report['filters']['search'])) $filterQueryParams['search'] = $report['filters']['search'];
if (!empty($report['filters']['admission_path'])) $filterQueryParams['admission_path'] = $report['filters']['admission_path'];
if (!empty($report['filters']['decision_status'])) $filterQueryParams['decision_status'] = $report['filters']['decision_status'];
$filterQueryString = $filterQueryParams !== [] ? '?' . http_build_query($filterQueryParams) : '';
$hasActiveFilter = !empty($report['filters']['session_id']) || !empty($report['filters']['wave_id']) || !empty($report['filters']['search']) || !empty($report['filters']['admission_path']) || !empty($report['filters']['decision_status']);
?>
  <header class="portal-toolbar portal-page-toolbar no-print"><div><h1>Laporan hasil PMB</h1><p>Ringkasan operasional, sebaran keputusan, dan kinerja hasil per program studi.</p></div><?php if (!$printMode): ?><div class="portal-toolbar-actions"><a class="quiet-button" href="<?= $e($reportBasePath) ?>/csv<?= $filterQueryString ?>">Ekspor CSV</a><a class="primary link-button" target="_blank" rel="noopener" href="<?= $e($reportBasePath) ?>/cetak<?= $filterQueryString ?>">Cetak laporan</a></div><?php endif; ?></header>

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

  <section class="portal-panel participant-management-panel" style="overflow: hidden; padding: 0;">
    <!-- Sleek Integrated Filter Stats Bar -->
    <div class="exam-facts" style="margin: 0; border: 0; border-bottom: 1px solid var(--portal-rule, #bcc9d9); border-radius: 0; background: var(--portal-panel-soft, #f8fafc);">
      <span><b><?= $e($report['stats']['total_count']) ?></b> Hasil Tampil</span>
      <span><b><?= $e(number_format($report['stats']['avg_score'], 2, ',', '.')) ?></b> Rata-rata Nilai</span>
      <span><b><?= $e(number_format($report['stats']['max_score'], 0)) ?> / <?= $e(number_format($report['stats']['min_score'], 0)) ?></b> Tertinggi / Terendah</span>
      <span><b><?= $e(number_format($report['stats']['pass_rate'], 1, ',', '.')) ?>%</b> Kelulusan (<?= $e($report['stats']['passed_count']) ?> Lulus)</span>
    </div>

    <?php if (!$printMode): ?>
      <form class="participant-filter-bar" method="get" action="<?= $e($reportBasePath) ?>" style="grid-template-columns: minmax(180px, 1fr) minmax(130px, .3fr) minmax(130px, .3fr) minmax(140px, .35fr) minmax(140px, .35fr) auto; padding: 12px 18px; border-bottom: 1px solid var(--portal-rule, #bcc9d9);">
        <label>Cari peserta<input name="search" value="<?= $e($report['filters']['search'] ?? '') ?>" placeholder="Nama, email, atau nomor"></label>
        <label>Gelombang<select name="wave_id" onchange="this.form.submit()"><option value="">Semua gelombang</option><?php foreach($report['waves'] as $w):?><option value="<?= $e($w['id']) ?>" <?= ($report['filters']['wave_id'] ?? '') === $w['id'] ? 'selected' : '' ?>><?= $e($w['name']) ?></option><?php endforeach;?></select></label>
        <label>Jalur masuk<select name="admission_path" onchange="this.form.submit()"><option value="">Semua jalur</option><?php foreach($report['admission_paths'] as $path):?><option value="<?= $e($path) ?>" <?= ($report['filters']['admission_path'] ?? '') === $path ? 'selected' : '' ?>><?= $e($path) ?></option><?php endforeach;?></select></label>
        <label>Sesi Ujian<select name="session_id" onchange="this.form.submit()"><option value="">Semua sesi</option><?php foreach($report['sessions'] as $s):?><option value="<?= $e($s['id']) ?>" <?= ($report['filters']['session_id'] ?? '') === $s['id'] ? 'selected' : '' ?>><?= $e($s['name']) ?></option><?php endforeach;?></select></label>
        <label>Status Keputusan<select name="decision_status" onchange="this.form.submit()"><option value="">Semua status</option><option value="PASSED" <?= ($report['filters']['decision_status'] ?? '') === 'PASSED' ? 'selected' : '' ?>>Lulus</option><option value="NOT_PASSED" <?= ($report['filters']['decision_status'] ?? '') === 'NOT_PASSED' ? 'selected' : '' ?>>Tidak Lulus</option><option value="PUBLISHED" <?= ($report['filters']['decision_status'] ?? '') === 'PUBLISHED' ? 'selected' : '' ?>>Dipublikasikan</option><option value="PENDING" <?= ($report['filters']['decision_status'] ?? '') === 'PENDING' ? 'selected' : '' ?>>Menunggu Publikasi</option></select></label>
        <?php if($hasActiveFilter):?>
          <a class="quiet-button" href="<?= $e($reportBasePath) ?>" style="align-self: flex-end; margin-bottom: 2px;">Reset filter</a>
        <?php endif;?>
      </form>
    <?php endif; ?>

    <div class="participant-table-region" style="overflow-x: auto; width: 100%;">
      <table class="participant-table report-table">
        <caption class="visually-hidden">Daftar hasil ujian PMB</caption>
        <thead>
          <tr>
            <th scope="col">Nomor peserta</th>
            <th scope="col">Peserta</th>
            <th scope="col">Program / gelombang / sesi</th>
            <th scope="col">Nilai</th>
            <th scope="col">Rincian jawaban</th>
            <th scope="col">Keputusan resmi</th>
            <th scope="col">Waktu nilai</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($report['rows'] === []): ?>
            <tr><td colspan="7"><div class="table-empty"><b>Belum ada hasil ujian</b><span>Data akan muncul setelah peserta menyelesaikan ujian dan hasil dinilai.</span></div></td></tr>
          <?php endif; ?>
          <?php foreach ($report['rows'] as $row): $published = ($row['communication_status'] ?? null) === 'PUBLISHED'; $tone = !$published ? 'warning' : ($row['official_decision'] === 'PASSED' ? 'success' : 'danger'); ?>
            <tr>
              <td><code class="reg-number-badge"><?= $e($row['registration_number']) ?></code></td>
              <td><b><?= $e($row['full_name']) ?></b></td>
              <td class="cell-program"><?= $e($row['program_name']) ?><br><small><?= $e($row['wave_name']) ?><?= (!empty($row['session_name']) && $row['session_name'] !== '-') ? ' · ' . $e($row['session_name']) : '' ?></small></td>
              <td><b class="table-score score-highlight"><?= $e(number_format((float) $row['score'], 2, ',', '.')) ?></b></td>
              <td>
                <div class="ans-breakdown">
                  <span class="ans-pill ans-correct"><?= $e($row['correct_count']) ?> benar</span>
                  <span class="ans-pill ans-incorrect"><?= $e($row['incorrect_count']) ?> salah</span>
                  <?php if ((int) ($row['unanswered_count'] ?? 0) > 0): ?>
                    <span class="ans-pill ans-unanswered"><?= $e($row['unanswered_count']) ?> kosong</span>
                  <?php endif; ?>
                </div>
              </td>
              <td>
                <div class="decision-cell">
                  <span class="status-pill status-<?= $e($tone) ?>"><?= $e($decisionLabel($row['official_decision'])) ?></span>
                  <small class="decision-publication-state"><?= $published ? 'Sudah dipublikasikan' : 'Menunggu publikasi' ?></small>
                </div>
              </td>
              <td class="cell-time"><?= $e($wib($row['scored_at'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if(!$printMode && $report['paginator']):?>
      <?= $report['paginator']->links($reportBasePath, $report['filters']) ?>
    <?php endif;?>
  </section>
</main></div><?php if ($printMode): ?><script>window.addEventListener('load',()=>window.print())</script><?php endif; ?></body>
</html>
