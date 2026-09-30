<?php
$e = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$totalActiveQuestions = (int) ($examData['questionTotal'] ?? 0);
$sessionTones = ['DRAFT' => 'neutral', 'SCHEDULED' => 'info', 'CLOSED' => 'success'];
$sessionLabels = ['DRAFT' => 'Draf', 'SCHEDULED' => 'Terjadwal', 'CLOSED' => 'Selesai'];
$portalRole = 'ADMIN';
$portalActive = 'exams';
require __DIR__ . '/partials/portal-context.php';
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= $e($title) ?> · Politeknik Aceh</title><link rel="stylesheet" href="/assets/app.css"></head><body class="portal-body admin-exam-body"><div class="portal-shell" data-dashboard-role="ADMIN">
<?php require __DIR__ . '/partials/portal-sidebar.php'; ?>
<main id="main-content" tabindex="-1" class="portal-main portal-workspace admin-exam-page">
<header class="portal-toolbar portal-page-toolbar"><div><h1>Operasional ujian</h1><p>Buat sesi, tugaskan peserta, lalu tetapkan jadwal dari satu layar.</p></div><div class="portal-toolbar-actions"><a class="quiet-button" href="/admin/bank-soal">Buka Bank Soal</a></div></header>
<?php if ($adminSuccess): ?><div class="alert success-alert"><?= $e($adminSuccess) ?></div><?php endif; ?><?php if ($adminError): ?><div class="alert error"><?= $e($adminError) ?></div><?php endif; ?>

<section class="exam-facts" aria-label="Ringkasan operasional ujian"><span><b><?= $e($totalActiveQuestions) ?></b> soal aktif</span><span><b><?= count($examData['categories']) ?></b> kategori</span><span><b><?= count($examData['sessions']) ?></b> sesi tersimpan</span><span><b><?= count($examData['eligibleParticipants']) ?></b> peserta eligible</span></section>

<section class="exam-control-grid">
  <article class="portal-panel exam-control-panel">
    <header><div><h2>Buat sesi baru</h2><p>Tetapkan batas kelulusan dan pilih apakah sesi memakai token.</p></div></header>
    <form class="exam-create-form" method="post" action="/admin/sesi">
      <input type="hidden" name="csrf" value="<?= $e($_SESSION['csrf']) ?>">
      <label>Gelombang<select name="wave_id" required><option value="">Pilih gelombang</option><?php foreach ($examData['waves'] as $wave): ?><option value="<?= $e($wave['id']) ?>"><?= $e($wave['name']) ?></option><?php endforeach; ?></select></label>
      <label>Nama sesi<input name="name" placeholder="Contoh: Sesi CBT Pagi" required></label>
      <label>Batas nilai kelulusan<input type="number" name="passing_grade" min="0" max="100" step="0.01" value="60" aria-describedby="passing-grade-help" required><small id="passing-grade-help">Nilai yang sama atau lebih tinggi otomatis dikategorikan lulus.</small></label>
      <label>Token sesi<input name="token" minlength="8" autocomplete="off" placeholder="Minimal 8 karakter" aria-describedby="session-token-help"><small id="session-token-help">Boleh dikosongkan bila token dinonaktifkan.</small></label>
      <label class="session-token-toggle"><input type="checkbox" name="token_required" value="1" checked><span>Aktifkan token untuk sesi ini</span></label>
      <button class="primary">Buat sesi</button>
    </form>
  </article>

  <article class="portal-panel exam-control-panel exam-assignment-panel">
    <header><div><h2>Tugaskan peserta per jalur masuk</h2><p>Pilih sesi dan jalur masuk untuk menugaskan seluruh peserta eligible tanpa memilih satu per satu.</p></div></header>
    <form class="exam-assign-form" method="post" action="/admin/tugaskan/semua">
      <input type="hidden" name="csrf" value="<?= $e($_SESSION['csrf']) ?>">
      <label>Sesi ujian<select name="session_id" required><option value="">Pilih sesi ujian</option><?php foreach ($examData['sessions'] as $session): ?><option value="<?= $e($session['id']) ?>"><?= $e($session['name']) ?> · <?= $e($session['wave_name']) ?></option><?php endforeach; ?></select></label>
      <label>Jalur masuk<select name="admission_path" required><option value="">Pilih jalur masuk</option><option value="ALL">Semua Jalur Masuk (Total: <?= count($examData['eligibleParticipants']) ?> peserta)</option><?php foreach ($examData['admissionPaths'] as $path): ?><option value="<?= $e($path['name']) ?>">Jalur <?= $e($path['name']) ?> (<?= (int) $path['eligible_count'] ?> peserta)</option><?php endforeach; ?></select></label>
      <button class="primary">Tugaskan peserta</button>
    </form>
    <details class="bulk-assignment">
      <summary><span><b>Penugasan perorangan (opsional)</b><small>Pilih satu peserta tertentu jika hanya ingin menugaskan satu orang.</small></span></summary>
      <form class="exam-bulk-form" method="post" action="/admin/tugaskan">
        <input type="hidden" name="csrf" value="<?= $e($_SESSION['csrf']) ?>">
        <label>Sesi<select name="session_id" required><option value="">Pilih sesi</option><?php foreach ($examData['sessions'] as $session): ?><option value="<?= $e($session['id']) ?>"><?= $e($session['name']) ?> · <?= $e($session['wave_name']) ?></option><?php endforeach; ?></select></label>
        <label>Peserta eligible<select name="participant_id" required><option value="">Pilih peserta</option><?php foreach ($examData['eligibleParticipants'] as $participant): ?><option value="<?= $e($participant['id']) ?>"><?= $e($participant['registration_number']) ?> · <?= $e($participant['full_name']) ?> (Jalur: <?= $e($participant['admission_path'] ?? 'Reguler') ?>)</option><?php endforeach; ?></select></label>
        <button class="quiet-button">Tugaskan satu peserta</button>
      </form>
    </details>
  </article>
</section>

<section class="portal-panel exam-session-panel">
  <header class="portal-panel-heading"><div><h2>Sesi ujian tersimpan</h2><p>Atur jadwal, batas kelulusan, dan proteksi token per sesi.</p></div><a href="/admin/bank-soal"><?= $e($totalActiveQuestions) ?> soal siap digunakan</a></header>
  <div class="exam-session-list">
    <?php if ($examData['sessions'] === []): ?><div class="portal-empty"><strong>Belum ada sesi ujian</strong><span>Buat sesi pertama melalui form di atas.</span></div><?php endif; ?>
    <?php foreach ($examData['sessions'] as $session): ?>
      <details class="session-admin-item">
        <summary><span><b><?= $e($session['name']) ?></b><small><?= $e($session['wave_name']) ?> · <?= $e($session['question_count']) ?> soal · <?= $e($session['duration_minutes']) ?> menit · batas lulus <?= $e(number_format((float) $session['passing_grade'], 2, ',', '.')) ?> · <?= $session['token_required'] ? 'token aktif' : 'tanpa token' ?> · <?= $e($session['assignment_count']) ?> peserta</small></span><span class="status-pill status-<?= $e($sessionTones[$session['status']] ?? 'neutral') ?>"><?= $e($sessionLabels[$session['status']] ?? $session['status']) ?></span></summary>
        <div class="session-edit-grid">
          <div class="session-token-compact"><b>Proteksi token</b><?php if (!$session['token_required']): ?><span>Token dinonaktifkan untuk sesi ini.</span><?php elseif (!empty($session['token_plaintext'])): ?><code><?= $e($session['token_plaintext']) ?></code><small>Dapat ditampilkan ulang kepada Admin.</small><?php else: ?><span>Token aktif tetapi nilai lama tidak dapat dibuka. Tetapkan token baru.</span><?php endif; ?>
            <form class="session-token-form" method="post" action="/admin/sesi/token" onsubmit="return confirm('Simpan pengaturan token sesi ini?');"><input type="hidden" name="csrf" value="<?= $e($_SESSION['csrf']) ?>"><input type="hidden" name="session_id" value="<?= $e($session['id']) ?>"><label class="session-token-toggle"><input type="checkbox" name="token_required" value="1" <?= $session['token_required'] ? 'checked' : '' ?>><span>Aktifkan token</span></label><label>Token baru <span>(kosongkan untuk mempertahankan)</span><input name="token" minlength="8" autocomplete="off" placeholder="Minimal 8 karakter"></label><button class="quiet-button">Simpan token</button></form>
          </div>
          <form class="session-schedule-form" method="post" action="/admin/sesi/jadwal"><input type="hidden" name="csrf" value="<?= $e($_SESSION['csrf']) ?>"><input type="hidden" name="session_id" value="<?= $e($session['id']) ?>"><label>Mulai (WIB)<input type="datetime-local" name="starts_at" value="<?= $e($session['starts_at_wib_input'] ?? '') ?>" required></label><label>Selesai (WIB)<input type="datetime-local" name="ends_at" value="<?= $e($session['ends_at_wib_input'] ?? '') ?>" required></label><label>Batas nilai kelulusan<input type="number" name="passing_grade" min="0" max="100" step="0.01" value="<?= $e($session['passing_grade']) ?>" required></label><button class="primary">Simpan jadwal & batas nilai</button></form>
        </div>
      </details>
    <?php endforeach; ?>
  </div>
</section>
</main></div></body></html>
