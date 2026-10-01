<?php
$e = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$summary = $committeeDashboard['summary'];
$statusLabels = ['PENDING'=>'Menunggu','NEEDS_CORRECTION'=>'Perlu perbaikan','PASSED'=>'Lulus','NOT_PASSED'=>'Tidak lulus','PUBLISHED'=>'Dipublikasikan'];
$statusTone = static fn (?string $status): string => match ($status) {'PASSED','PUBLISHED'=>'success','NEEDS_CORRECTION'=>'warning','NOT_PASSED'=>'danger',default=>'info'};
$portalRole = 'COMMITTEE';
$portalActive = 'dashboard';
require __DIR__ . '/partials/portal-context.php';
?>
<!doctype html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= $e($title) ?> | Politeknik Aceh</title><link rel="stylesheet" href="/assets/app.css"></head>
<body class="portal-body role-dashboard-body"><div class="portal-shell" data-dashboard-role="COMMITTEE">
  <?php require __DIR__ . '/partials/portal-sidebar.php'; ?>
  <main id="main-content" tabindex="-1" class="portal-main">
    <header class="portal-toolbar">
      <div><h1>Meja kerja Panitia</h1><p>Kelola keputusan kelulusan dan laporan hasil peserta PMB.</p></div>
      <div class="portal-toolbar-actions"><span class="portal-date"><?= $e((new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->format('d M Y')) ?></span><a class="quiet-button" href="/panitia/laporan">Buka laporan</a><a class="primary link-button" href="/panitia/keputusan">Kelola Keputusan</a></div>
    </header>

    <section class="portal-metrics" aria-label="Ringkasan pekerjaan Panitia">
      <article class="portal-metric portal-metric-priority"><span>Menunggu keputusan</span><strong><?= $e(number_format($summary['awaiting_decision'], 0, ',', '.')) ?></strong><small>Hasil ujian sudah tersedia</small></article>
      <article class="portal-metric"><span>Perlu perbaikan</span><strong><?= $e(number_format($summary['needs_correction'], 0, ',', '.')) ?></strong><small>Peserta perlu menindaklanjuti catatan</small></article>
      <article class="portal-metric"><span>Lulus / Disetujui</span><strong><?= $e(number_format($summary['pending_verification'], 0, ',', '.')) ?></strong><small>Peserta terverifikasi aktif</small></article>
      <article class="portal-metric"><span>Siap dipublikasikan</span><strong><?= $e(number_format($summary['pending_publication'], 0, ',', '.')) ?></strong><small><?= $e(number_format($summary['published_decisions'], 0, ',', '.')) ?> keputusan sudah diumumkan</small></article>
    </section>

    <section class="portal-dashboard-grid">
      <div class="portal-primary-column">
        <article class="portal-panel">
          <header class="portal-panel-heading"><div><h2>Antrean peserta prioritas</h2><p>Peserta yang memerlukan tinjauan atau perbaikan catatan.</p></div><a href="/panitia/keputusan">Lihat seluruh antrean</a></header>
          <div class="portal-table-wrap"><table class="portal-table"><thead><tr><th scope="col">Nomor peserta</th><th scope="col">Nama</th><th scope="col">Program dan gelombang</th><th scope="col">Status</th><th scope="col">Tindakan</th></tr></thead><tbody>
          <?php if ($committeeDashboard['verification_queue'] === []): ?><tr><td colspan="5"><div class="portal-empty"><strong>Antrean peserta bersih</strong><span>Tidak ada peserta yang memerlukan perhatian khusus saat ini.</span></div></td></tr><?php endif; ?>
          <?php foreach ($committeeDashboard['verification_queue'] as $participantRow): ?><tr><td><span class="tabular-value"><?= $e($participantRow['registration_number']) ?></span></td><td><strong><?= $e($participantRow['full_name']) ?></strong></td><td><?= $e($participantRow['program_choice']) ?><small><?= $e($participantRow['wave']) ?></small></td><td><span class="status-pill status-<?= $e($statusTone($participantRow['verification_status'])) ?>"><?= $e($statusLabels[$participantRow['verification_status']] ?? $participantRow['verification_status']) ?></span></td><td><a class="portal-table-action" href="/panitia/keputusan?search=<?= urlencode($participantRow['registration_number']) ?>">Tinjau</a></td></tr><?php endforeach; ?>
          </tbody></table></div>
        </article>

        <article class="portal-panel">
          <header class="portal-panel-heading"><div><h2>Hasil ujian terbaru</h2><p>Status penetapan dan publikasi keputusan resmi.</p></div><a href="/panitia/keputusan">Kelola keputusan</a></header>
          <div class="portal-table-wrap"><table class="portal-table"><thead><tr><th scope="col">Peserta</th><th scope="col">Nilai</th><th scope="col">Keputusan</th><th scope="col">Publikasi</th></tr></thead><tbody>
          <?php if ($committeeDashboard['recent_results'] === []): ?><tr><td colspan="4"><div class="portal-empty"><strong>Belum ada hasil ujian</strong><span>Hasil peserta akan muncul setelah ujian dinilai.</span></div></td></tr><?php endif; ?>
          <?php foreach ($committeeDashboard['recent_results'] as $resultRow): ?><tr><td><strong><?= $e($resultRow['full_name']) ?></strong><small><?= $e($resultRow['registration_number']) ?></small></td><td><strong class="tabular-value"><?= $e(number_format((float) $resultRow['score'], 2, ',', '.')) ?></strong></td><td><span class="status-pill status-<?= $e($statusTone($resultRow['decision'])) ?>"><?= $e($statusLabels[$resultRow['decision']] ?? 'Belum ditetapkan') ?></span></td><td><?= $e(($resultRow['communication_status'] ?? '') === 'PUBLISHED' ? 'Sudah diumumkan' : 'Belum diumumkan') ?></td></tr><?php endforeach; ?>
          </tbody></table></div>
        </article>
      </div>

      <aside class="portal-secondary-column">
        <article class="portal-panel portal-priority-panel"><header class="portal-panel-heading"><div><h2>Alur kerja yang disarankan</h2><p>Kerjakan dari hasil ujian hingga publikasi keputusan.</p></div></header><ol class="portal-priority-list">
          <li><span>1</span><div><strong>Tetapkan keputusan kelulusan</strong><p>Gunakan hasil ujian yang sudah dinilai untuk menetapkan status peserta.</p><a href="/panitia/keputusan">Buka keputusan</a></div></li>
          <li><span>2</span><div><strong>Periksa dan cetak laporan</strong><p>Pastikan status publikasi dan rekap data PMB sudah konsisten.</p><a href="/panitia/laporan">Buka laporan</a></div></li>
        </ol></article>
      </aside>
    </section>
  </main>
</div></body></html>
