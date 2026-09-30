<?php
$e = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$summary = $adminDashboard['summary'];
$statusLabels = ['DRAFT'=>'Draf','SCHEDULED'=>'Terjadwal','CLOSED'=>'Ditutup','PENDING'=>'Menunggu','APPROVED'=>'Disetujui','NEEDS_CORRECTION'=>'Perlu perbaikan','REJECTED'=>'Ditolak'];
$statusTone = static fn (string $status): string => match ($status) {'APPROVED','SCHEDULED'=>'success','NEEDS_CORRECTION'=>'warning','REJECTED','CLOSED'=>'danger',default=>'info'};
$portalRole = 'ADMIN';
$portalActive = 'dashboard';
require __DIR__ . '/partials/portal-context.php';
?>
<!doctype html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= $e($title) ?> | Politeknik Aceh</title><link rel="stylesheet" href="/assets/app.css"></head>
<body class="portal-body role-dashboard-body"><div class="portal-shell" data-dashboard-role="ADMIN">
  <?php require __DIR__ . '/partials/portal-sidebar.php'; ?>
  <main id="main-content" tabindex="-1" class="portal-main">
    <header class="portal-toolbar">
      <div><h1>Pusat kendali PMB</h1><p>Pantau kesiapan peserta, soal, dan sesi ujian dari satu ruang kerja.</p></div>
      <div class="portal-toolbar-actions"><span class="portal-date"><?= $e((new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->format('d M Y')) ?></span><a class="quiet-button" href="/admin/peserta">Tambah peserta</a><a class="primary link-button" href="/admin/ujian">Kelola sesi</a></div>
    </header>

    <section class="portal-metrics" aria-label="Ringkasan operasional Admin">
      <article class="portal-metric"><span>Peserta terdaftar</span><strong><?= $e(number_format($summary['participants'], 0, ',', '.')) ?></strong><small><?= $e(number_format($summary['eligible'], 0, ',', '.')) ?> siap mengikuti ujian</small></article>
      <article class="portal-metric"><span>Bank soal aktif</span><strong><?= $e(number_format($summary['active_questions'], 0, ',', '.')) ?></strong><small>Soal siap digunakan dalam sesi</small></article>
      <article class="portal-metric"><span>Sesi terjadwal</span><strong><?= $e(number_format($summary['scheduled_sessions'], 0, ',', '.')) ?></strong><small><?= $e(number_format($summary['active_attempts'], 0, ',', '.')) ?> proses ujian aktif</small></article>
      <article class="portal-metric portal-metric-priority"><span>Perlu ditindaklanjuti</span><strong><?= $e(number_format($summary['pending_verification'], 0, ',', '.')) ?></strong><small>Verifikasi menunggu atau perlu perbaikan</small></article>
    </section>

    <section class="portal-dashboard-grid">
      <div class="portal-primary-column">
        <article class="portal-panel">
          <header class="portal-panel-heading"><div><h2>Sesi ujian terbaru</h2><p>Jadwal, penugasan, dan aktivitas peserta.</p></div><a href="/admin/ujian">Lihat semua sesi</a></header>
          <div class="portal-table-wrap"><table class="portal-table"><thead><tr><th scope="col">Sesi</th><th scope="col">Jadwal</th><th scope="col">Peserta</th><th scope="col">Status</th></tr></thead><tbody>
          <?php if ($adminDashboard['sessions'] === []): ?><tr><td colspan="4"><div class="portal-empty"><strong>Belum ada sesi ujian</strong><span>Buat sesi pertama untuk mulai menugaskan peserta.</span></div></td></tr><?php endif; ?>
          <?php foreach ($adminDashboard['sessions'] as $session): ?><tr><td><strong><?= $e($session['name']) ?></strong><small><?= $e($session['wave_name'] ?: 'Tanpa gelombang') ?></small></td><td><?= $e($session['starts_at_wib']) ?><small>sampai <?= $e($session['ends_at_wib']) ?></small></td><td><strong><?= $e($session['assigned_count']) ?></strong><small><?= $e($session['attempt_count']) ?> sudah membuka ujian</small></td><td><span class="status-pill status-<?= $e($statusTone($session['status'])) ?>"><?= $e($statusLabels[$session['status']] ?? $session['status']) ?></span></td></tr><?php endforeach; ?>
          </tbody></table></div>
        </article>

        <article class="portal-panel">
          <header class="portal-panel-heading"><div><h2>Peserta terbaru</h2><p>Data peserta yang terakhir dibuat oleh Admin.</p></div><a href="/admin/peserta">Kelola peserta</a></header>
          <div class="portal-table-wrap"><table class="portal-table"><thead><tr><th scope="col">Nomor peserta</th><th scope="col">Nama</th><th scope="col">Program dan gelombang</th><th scope="col">Verifikasi</th></tr></thead><tbody>
          <?php if ($adminDashboard['recent_participants'] === []): ?><tr><td colspan="4"><div class="portal-empty"><strong>Belum ada peserta</strong><span>Tambahkan peserta satuan atau gunakan impor XLSX.</span></div></td></tr><?php endif; ?>
          <?php foreach ($adminDashboard['recent_participants'] as $participantRow): ?><tr><td><span class="tabular-value"><?= $e($participantRow['registration_number']) ?></span></td><td><strong><?= $e($participantRow['full_name']) ?></strong></td><td><?= $e($participantRow['program_choice']) ?><small><?= $e($participantRow['wave']) ?></small></td><td><span class="status-pill status-<?= $e($statusTone($participantRow['verification_status'])) ?>"><?= $e($statusLabels[$participantRow['verification_status']] ?? $participantRow['verification_status']) ?></span></td></tr><?php endforeach; ?>
          </tbody></table></div>
        </article>
      </div>

      <aside class="portal-secondary-column">
        <article class="portal-panel portal-priority-panel"><header class="portal-panel-heading"><div><h2>Prioritas hari ini</h2><p>Urutan kerja yang paling berdampak.</p></div></header><ol class="portal-priority-list">
          <li><span>1</span><div><strong>Tinjau peserta menunggu</strong><p><?= $e($summary['pending_verification']) ?> data masih memerlukan keputusan Panitia.</p><a href="/admin/peserta?status=PENDING">Buka daftar peserta</a></div></li>
          <li><span>2</span><div><strong>Pastikan sesi siap</strong><p>Periksa jadwal, token, dan penugasan peserta.</p><a href="/admin/ujian">Periksa sesi</a></div></li>
          <li><span>3</span><div><strong>Tinjau hasil dan keputusan</strong><p><?= $e($summary['scored_results']) ?> hasil dinilai, <?= $e($summary['published_decisions']) ?> keputusan dipublikasikan.</p><a href="/admin/laporan">Buka laporan</a></div></li>
        </ol></article>
      </aside>
    </section>
  </main>
</div></body></html>
