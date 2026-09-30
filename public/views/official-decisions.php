<?php
$e = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$counts = ['UNDECIDED'=>0,'PENDING'=>0,'SCHEDULED'=>0,'PUBLISHED'=>0];
foreach ($decisionQueue as $row) {
    if (!$row['decision_id']) $counts['UNDECIDED']++;
    elseif ($row['communication_status'] === 'PUBLISHED') $counts['PUBLISHED']++;
    elseif (!empty($row['scheduled_publish_at'])) $counts['SCHEDULED']++;
    else $counts['PENDING']++;
}
$portalRole = 'COMMITTEE';
$portalActive = 'decisions';
require __DIR__ . '/partials/portal-context.php';
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= $e($title) ?> · Politeknik Aceh</title><link rel="stylesheet" href="/assets/app.css"></head><body class="portal-body"><div class="portal-shell" data-dashboard-role="COMMITTEE">
<?php require __DIR__ . '/partials/portal-sidebar.php'; ?>
<main id="main-content" tabindex="-1" class="portal-main portal-workspace decision-page">
<header class="portal-toolbar portal-page-toolbar"><div><h1>Keputusan dan publikasi</h1><p>Tetapkan keputusan resmi, periksa catatan, lalu publikasikan sesuai jadwal.</p></div><div class="portal-toolbar-actions"><span class="portal-date"><?= count($decisionQueue) ?> hasil pada halaman</span></div></header>
<?php if ($decisionSuccess): ?><div class="alert success-alert"><?= $e($decisionSuccess) ?></div><?php endif; ?><?php if ($decisionError): ?><div class="alert error"><?= $e($decisionError) ?></div><?php endif; ?>

<section class="decision-control-grid">
  <article class="card compact"><h2>Jadwalkan publikasi serentak</h2><p class="intro">Jadwal berlaku untuk seluruh keputusan aktif yang masih pending saat tombol disimpan. Keputusan baru setelah itu tidak otomatis ikut.</p><form class="stack" method="post" action="/panitia/keputusan/jadwalkan"><input type="hidden" name="csrf" value="<?= $e($_SESSION['csrf']) ?>"><label>Tanggal dan waktu publikasi (WIB)<input type="datetime-local" name="publish_at" required></label><button class="primary">Jadwalkan seluruh keputusan pending</button></form><form method="post" action="/panitia/keputusan/batalkan-jadwal" onsubmit="return confirm('Batalkan seluruh jadwal publikasi yang masih pending?');"><input type="hidden" name="csrf" value="<?= $e($_SESSION['csrf']) ?>"><button class="quiet-button danger-button">Batalkan jadwal aktif</button></form></article>
  <article class="card compact"><h2>Tiga langkah menetapkan keputusan</h2><ol class="workflow-steps"><li><b>Tetapkan</b><span>Pilih hasil resmi dan isi catatan peserta.</span></li><li><b>Periksa</b><span>Gunakan filter Pending atau Terjadwal untuk pemeriksaan akhir.</span></li><li><b>Publikasikan</b><span>Kirim sekarang per peserta atau jadwalkan serentak.</span></li></ol></article>
</section>

<section class="decision-metrics" aria-label="Ringkasan halaman ini"><?php foreach (['UNDECIDED'=>'Belum diputuskan','PENDING'=>'Pending','SCHEDULED'=>'Terjadwal','PUBLISHED'=>'Dipublikasikan'] as $key => $label): ?><article><span><?= $e($label) ?> di halaman ini</span><b><?= $counts[$key] ?></b></article><?php endforeach; ?></section>

<form class="card compact decision-filter" method="get"><div class="filter-row"><label>Cari peserta<input name="search" value="<?= $e($decisionFilters['search'] ?? '') ?>" placeholder="Nama atau nomor peserta"></label><label>Status<select name="status"><option value="ALL">Semua status</option><?php foreach (['UNDECIDED'=>'Belum diputuskan','PENDING'=>'Pending','SCHEDULED'=>'Terjadwal','PUBLISHED'=>'Dipublikasikan'] as $value => $label): ?><option value="<?= $value ?>" <?= ($decisionFilters['status'] ?? 'ALL') === $value ? 'selected' : '' ?>><?= $e($label) ?></option><?php endforeach; ?></select></label><button class="primary">Terapkan filter</button><a class="quiet-button" href="/panitia/keputusan">Reset</a></div></form>

<section class="decision-list">
<?php if ($decisionQueue === []): ?><article class="card compact"><h2>Tidak ada data pada filter ini</h2><p class="intro">Hasil baru muncul setelah attempt berstatus SCORED.</p></article><?php endif; ?>
<?php foreach ($decisionQueue as $row):
    $stateKey = !$row['decision_id'] ? 'UNDECIDED' : ($row['communication_status'] === 'PUBLISHED' ? 'PUBLISHED' : (!empty($row['scheduled_publish_at']) ? 'SCHEDULED' : 'PENDING'));
    $state = ['UNDECIDED' => 'Belum diputuskan', 'PENDING' => 'Menunggu publikasi', 'SCHEDULED' => 'Terjadwal', 'PUBLISHED' => 'Dipublikasikan'][$stateKey];
    $stateTone = ['UNDECIDED' => 'neutral', 'PENDING' => 'warning', 'SCHEDULED' => 'info', 'PUBLISHED' => 'success'][$stateKey];
?>
<article class="card compact decision-card">
  <div class="decision-participant"><div class="decision-card-heading"><span class="status-pill status-<?= $e($stateTone) ?>"><?= $e($state) ?></span><small><?= $e($row['session_name']) ?></small></div><h2><?= $e($row['registration_number']) ?> · <?= $e($row['full_name']) ?></h2><strong class="decision-score">Nilai <?= $e(number_format((float) $row['score'], 2, ',', '.')) ?></strong><p class="automatic-decision">Kategori otomatis: <b><?= $row['automatic_decision'] === 'PASSED' ? 'Lulus' : 'Tidak lulus' ?></b> berdasarkan batas nilai <?= $e(number_format((float) $row['result_passing_grade'], 2, ',', '.')) ?>.</p>
  <?php if ($row['decision_id']): ?><div class="decision-note"><b><?= $row['decision'] === 'PASSED' ? 'Lulus' : 'Belum dinyatakan lulus' ?></b><p><?= nl2br($e($row['note'])) ?></p><?php if (!empty($row['scheduled_publish_at_wib'])): ?><small>Akan dipublikasikan <?= $e($row['scheduled_publish_at_wib']) ?></small><?php endif; ?></div><?php endif; ?></div>
  <div class="decision-action"><details <?= !$row['decision_id'] ? 'open' : '' ?>><summary><?= $row['decision_id'] ? 'Ubah keputusan' : 'Konfirmasi keputusan' ?></summary><form class="stack" method="post" action="/panitia/keputusan/tetapkan"><input type="hidden" name="csrf" value="<?= $e($_SESSION['csrf']) ?>"><input type="hidden" name="result_id" value="<?= $e($row['result_id']) ?>"><?php $selectedDecision = $row['decision'] ?? $row['automatic_decision']; ?><label>Keputusan<select name="decision"><option value="PASSED" <?= $selectedDecision === 'PASSED' ? 'selected' : '' ?>>Lulus</option><option value="NOT_PASSED" <?= $selectedDecision === 'NOT_PASSED' ? 'selected' : '' ?>>Belum dinyatakan lulus</option></select><small>Terisi otomatis dari batas nilai sesi; Panitia dapat mengoreksi sebelum publikasi.</small></label><label>Catatan untuk peserta<textarea name="note" required placeholder="Dasar keputusan atau informasi resmi."><?= $e($row['note'] ?? '') ?></textarea></label><button class="quiet-button"><?= $row['decision_id'] ? 'Simpan sebagai keputusan koreksi' : 'Konfirmasi keputusan otomatis' ?></button></form></details>
  <?php if (($row['communication_status'] ?? null) === 'PENDING'): ?><form method="post" action="/panitia/keputusan/publikasikan" onsubmit="return confirm('Publikasikan keputusan ini sekarang kepada peserta?');"><input type="hidden" name="csrf" value="<?= $e($_SESSION['csrf']) ?>"><input type="hidden" name="decision_id" value="<?= $e($row['decision_id']) ?>"><button class="primary">Publikasikan sekarang</button></form><?php endif; ?></div>
</article>
<?php endforeach; ?>
<?= $decisionPaginator->links('/panitia/keputusan',['search'=>$decisionFilters['search']??'','status'=>$decisionFilters['status']??'ALL']) ?>
</section></main></div></body></html>
