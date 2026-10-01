<?php
$e = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$portalRole = 'ADMIN';
$portalActive = 'decisions';
require __DIR__ . '/partials/portal-context.php';

$activeSessionId = $decisionFilters['session_id'] ?? '';
$activeAdmissionPath = $decisionFilters['admission_path'] ?? '';
$activeSearch = $decisionFilters['search'] ?? '';
$activeStatus = $decisionFilters['status'] ?? 'ALL';

$hasActiveFilter = $activeSessionId !== '' || $activeAdmissionPath !== '' || $activeSearch !== '' || $activeStatus !== 'ALL';
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= $e($title) ?> | Politeknik Aceh</title>
  <link rel="stylesheet" href="/assets/app.css">
</head>
<body class="portal-body">
<div class="portal-shell" data-dashboard-role="ADMIN">
  <?php require __DIR__ . '/partials/portal-sidebar.php'; ?>
  <main id="main-content" tabindex="-1" class="portal-main portal-workspace decision-page">
    <header class="portal-toolbar portal-page-toolbar">
      <div>
        <h1>Keputusan Kelulusan & Publikasi</h1>
        <p>Tetapkan status kelulusan peserta per jalur masuk atau per sesi ujian, lalu publikasikan secara serentak.</p>
      </div>
      <div class="portal-toolbar-actions">
        <span class="portal-date"><?= $e((new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->format('d M Y')) ?></span>
      </div>
    </header>

    <?php if ($decisionSuccess): ?><div class="alert success-alert"><?= $e($decisionSuccess) ?></div><?php endif; ?>
    <?php if ($decisionError): ?><div class="alert error"><?= $e($decisionError) ?></div><?php endif; ?>

    <!-- Standard Portal Metrics Cards Grid -->
    <section class="portal-metrics portal-metrics-5" aria-label="Ringkasan Status Keputusan">
      <article class="portal-metric">
        <span>Total peserta ujian</span>
        <strong><?= $e(number_format($decisionStats['total_count'], 0, ',', '.')) ?></strong>
        <small>Hasil ujian terverifikasi</small>
      </article>

      <article class="portal-metric <?= $decisionStats['undecided_count'] > 0 ? 'portal-metric-priority' : '' ?>">
        <span>Belum diputuskan</span>
        <strong><?= $e(number_format($decisionStats['undecided_count'], 0, ',', '.')) ?></strong>
        <small>Perlu konfirmasi Admin</small>
      </article>

      <article class="portal-metric">
        <span>Menunggu publikasi</span>
        <strong><?= $e(number_format($decisionStats['pending_count'], 0, ',', '.')) ?></strong>
        <small>Draf keputusan tersimpan</small>
      </article>

      <article class="portal-metric">
        <span>Terjadwal</span>
        <strong><?= $e(number_format($decisionStats['scheduled_count'], 0, ',', '.')) ?></strong>
        <small>Menunggu rilis otomatis</small>
      </article>

      <article class="portal-metric">
        <span>Dipublikasikan</span>
        <strong><?= $e(number_format($decisionStats['published_count'], 0, ',', '.')) ?></strong>
        <small>Resmi tampil di peserta</small>
      </article>
    </section>

    <!-- Integrated Multi-Filter Bar & List Container -->
    <section class="portal-panel participant-management-panel" style="overflow: hidden; padding: 0; border-radius: 14px;">
      <form class="participant-filter-bar" method="get" action="/admin/keputusan" style="grid-template-columns: minmax(180px, 1fr) minmax(150px, .35fr) minmax(150px, .35fr) minmax(150px, .35fr) auto; padding: 12px 18px;">
        <label>Cari peserta<input name="search" value="<?= $e($activeSearch) ?>" placeholder="Nama atau nomor peserta"></label>
        
        <label>Jalur masuk
          <select name="admission_path" onchange="this.form.submit()">
            <option value="">Semua Jalur Masuk</option>
            <?php foreach ($decisionAdmissionPaths as $path): ?>
              <option value="<?= $e($path) ?>" <?= $activeAdmissionPath === $path ? 'selected' : '' ?>><?= $e($path) ?></option>
            <?php endforeach; ?>
          </select>
        </label>

        <label>Sesi ujian
          <select name="session_id" onchange="this.form.submit()">
            <option value="">Semua Sesi Ujian</option>
            <?php foreach ($decisionSessions as $s): ?>
              <option value="<?= $e($s['id']) ?>" <?= $activeSessionId === $s['id'] ? 'selected' : '' ?>><?= $e($s['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>

        <label>Status keputusan
          <select name="status" onchange="this.form.submit()">
            <option value="ALL" <?= $activeStatus === 'ALL' ? 'selected' : '' ?>>Semua Status</option>
            <option value="UNDECIDED" <?= $activeStatus === 'UNDECIDED' ? 'selected' : '' ?>>Belum Diputuskan</option>
            <option value="PENDING" <?= $activeStatus === 'PENDING' ? 'selected' : '' ?>>Menunggu Publikasi</option>
            <option value="SCHEDULED" <?= $activeStatus === 'SCHEDULED' ? 'selected' : '' ?>>Terjadwal</option>
            <option value="PUBLISHED" <?= $activeStatus === 'PUBLISHED' ? 'selected' : '' ?>>Dipublikasikan</option>
          </select>
        </label>

        <?php if ($hasActiveFilter): ?>
          <a class="quiet-button" href="/admin/keputusan" style="align-self: flex-end; margin-bottom: 2px;">Reset filter</a>
        <?php else: ?>
          <button class="primary" style="align-self: flex-end; margin-bottom: 2px;">Filter</button>
        <?php endif; ?>
      </form>

      <!-- Contextual Batch Action Bar -->
      <div class="decision-batch-bar">
        <div class="batch-bar-info">
          <label style="display:inline-flex; align-items:center; gap:6px; font-weight:600; font-size:11.5px; white-space:nowrap; cursor:pointer; color:var(--portal-text); user-select:none;">
            <input type="checkbox" id="select-all-decisions" onchange="toggleSelectAllDecisions(this)" style="width:15px; height:15px; cursor:pointer;">
            <span>Pilih Semua</span>
          </label>
        </div>
        <div class="batch-bar-actions">
          <form id="form-tetapkan-massal" method="post" action="/admin/keputusan/tetapkan-massal" style="display:inline;" onsubmit="return triggerConfirmModal(event, 'tetapkan');">
            <input type="hidden" name="csrf" value="<?= $e($_SESSION['csrf']) ?>">
            <input type="hidden" name="session_id" value="<?= $e($activeSessionId) ?>">
            <input type="hidden" name="admission_path" value="<?= $e($activeAdmissionPath) ?>">
            <div id="hidden-result-ids"></div>
            <button class="quiet-button batch-action-btn" type="submit">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
              <span id="label-tetapkan-massal">Tetapkan Massal</span>
            </button>
          </form>

          <button type="button" class="quiet-button batch-action-btn" onclick="document.getElementById('schedule-modal').showModal()">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            Jadwalkan Serentak
          </button>

          <form id="form-sembunyikan-nilai" method="post" action="/admin/keputusan/sembunyikan-nilai-massal" style="display:inline;" onsubmit="return triggerConfirmModal(event, 'sembunyikan_nilai');">
            <input type="hidden" name="csrf" value="<?= $e($_SESSION['csrf']) ?>">
            <div id="hidden-score-result-ids-hide"></div>
            <button class="quiet-button batch-action-btn" type="submit" title="Sembunyikan nilai ujian dari peserta terpilih">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
              <span id="label-sembunyikan-nilai">Sembunyikan Nilai</span>
            </button>
          </form>

          <form id="form-tampilkan-nilai" method="post" action="/admin/keputusan/tampilkan-nilai-massal" style="display:inline;" onsubmit="return triggerConfirmModal(event, 'tampilkan_nilai');">
            <input type="hidden" name="csrf" value="<?= $e($_SESSION['csrf']) ?>">
            <div id="hidden-score-result-ids-show"></div>
            <button class="quiet-button batch-action-btn" type="submit" title="Tampilkan nilai ujian ke peserta terpilih">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              <span id="label-tampilkan-nilai">Tampilkan Nilai</span>
            </button>
          </form>

          <form id="form-publikasikan-massal" method="post" action="/admin/keputusan/publikasikan-massal" style="display:inline;" onsubmit="return triggerConfirmModal(event, 'publikasikan');">
            <input type="hidden" name="csrf" value="<?= $e($_SESSION['csrf']) ?>">
            <input type="hidden" name="session_id" value="<?= $e($activeSessionId) ?>">
            <input type="hidden" name="admission_path" value="<?= $e($activeAdmissionPath) ?>">
            <div id="hidden-decision-ids"></div>
            <button class="primary batch-action-btn" type="submit">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
              <span id="label-publikasikan-massal">Publikasikan Massal</span>
            </button>
          </form>
        </div>
      </div>

      <!-- Interactive Data Table View -->
      <div class="decision-table-region">
        <?php if ($decisionQueue === []): ?>
          <div class="table-empty" style="padding: 36px 20px;">
            <b>Tidak ada data peserta</b>
            <span>Belum ada hasil ujian yang memenuhi kriteria filter saat ini.</span>
          </div>
        <?php else: ?>
          <div class="decision-table-wrapper">
            <table class="decision-table">
              <thead>
                <tr>
                  <th style="width: 36px; text-align: center;">#</th>
                  <th style="text-align: center;">No. Reg</th>
                  <th style="text-align: center;">Nama Peserta</th>
                  <th style="text-align: center;">Program Studi</th>
                  <th style="text-align: center;">Nilai Ujian</th>
                  <th style="text-align: center;">Rekomendasi</th>
                  <th style="text-align: center;">Keputusan Resmi</th>
                  <th style="text-align: center;">Status Publikasi</th>
                  <th style="text-align: center;">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($decisionQueue as $row):
                  $stateKey = !$row['decision_id'] ? 'UNDECIDED' : ($row['communication_status'] === 'PUBLISHED' ? 'PUBLISHED' : (!empty($row['scheduled_publish_at']) ? 'SCHEDULED' : 'PENDING'));
                  $stateLabel = ['UNDECIDED' => 'Belum Diputuskan', 'PENDING' => 'Menunggu Publikasi', 'SCHEDULED' => 'Terjadwal', 'PUBLISHED' => 'Dipublikasikan'][$stateKey];
                  $stateTone = ['UNDECIDED' => 'warning', 'PENDING' => 'info', 'SCHEDULED' => 'info', 'PUBLISHED' => 'success'][$stateKey];
                  $scoreVisible = (int) ($row['is_visible'] ?? 1) === 1;
                ?>
                  <tr>
                    <td style="text-align: center;">
                      <input type="checkbox" class="participant-select-checkbox" data-decision-id="<?= $e($row['decision_id'] ?? '') ?>" data-result-id="<?= $e($row['result_id']) ?>" onchange="updateDecisionSelectionState()" style="width:16px; height:16px; cursor:pointer;">
                    </td>
                    <td style="text-align: center;">
                      <code><?= $e($row['registration_number']) ?></code>
                    </td>
                    <td style="text-align: center;">
                      <b><?= $e($row['full_name']) ?></b>
                    </td>
                    <td style="text-align: center;">
                      <b><?= $e($row['program_choice']) ?></b>
                      <small>Sesi: <b><?= $e($row['session_name']) ?></b> &bull; <b><?= $e($row['admission_path']) ?></b></small>
                    </td>
                    <td style="text-align: center;">
                      <form method="post" action="/admin/keputusan/toggle-nilai" class="score-toggle-form" style="display:inline-flex; align-items:center; justify-content:center; gap:6px; margin:0; cursor:pointer !important;" onsubmit="return triggerConfirmModal(event, '<?= $scoreVisible ? 'toggle_hide' : 'toggle_show' ?>');">
                        <input type="hidden" name="csrf" value="<?= $e($_SESSION['csrf']) ?>">
                        <input type="hidden" name="result_id" value="<?= $e($row['result_id']) ?>">
                        <input type="hidden" name="is_visible" value="<?= $scoreVisible ? '0' : '1' ?>">
                        <b style="font-size: 14px; font-variant-numeric: tabular-nums;"><?= $e(number_format((float)$row['score'], 2, ',', '.')) ?></b>
                        <button type="submit" class="status-pill status-<?= $scoreVisible ? 'success' : 'neutral' ?> toggle-score-btn" style="cursor: pointer !important; border: 0; padding: 4px 8px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; transition: transform 0.15s ease;" title="<?= $scoreVisible ? 'Nilai tampil di peserta. Klik untuk menyembunyikan.' : 'Nilai disembunyikan dari peserta. Klik untuk menampilkan.' ?>">
                          <?php if ($scoreVisible): ?>
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="cursor: pointer !important; pointer-events: none;"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                          <?php else: ?>
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="cursor: pointer !important; pointer-events: none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                          <?php endif; ?>
                        </button>
                      </form>
                    </td>
                    <td style="text-align: center;">
                      <span class="status-pill status-<?= $row['automatic_decision'] === 'PASSED' ? 'success' : 'danger' ?>">
                        <?= $row['automatic_decision'] === 'PASSED' ? 'Lulus' : 'Tidak Lulus' ?>
                      </span>
                    </td>
                    <td style="text-align: center;">
                      <?php if ($row['decision_id']): ?>
                        <?php
                          $displayNote = $row['note'] ?? '';
                          if ($row['decision'] === 'PASSED' && (str_contains($displayNote, 'Belum memenuhi') || str_contains($displayNote, 'belum memenuhi'))) {
                            $displayNote = 'Dinyatakan Lulus berdasarkan nilai passing grade sesi ujian.';
                          } elseif ($row['decision'] === 'NOT_PASSED' && (str_contains($displayNote, 'Lulus') || str_contains($displayNote, 'lulus'))) {
                            $displayNote = 'Belum memenuhi batas nilai kelulusan minimum.';
                          }
                        ?>
                        <span class="status-pill status-<?= $row['decision'] === 'PASSED' ? 'success' : 'danger' ?>">
                          <?= $row['decision'] === 'PASSED' ? 'Lulus' : 'Tidak Lulus' ?>
                        </span>
                        <?php if (!empty($displayNote)): ?>
                          <small style="display: block; max-width: 190px; margin: 2px auto 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= $e($displayNote) ?>">
                            <?= $e($displayNote) ?>
                          </small>
                        <?php endif; ?>
                      <?php else: ?>
                        <small style="font-style: italic;">Belum Diatur</small>
                      <?php endif; ?>
                    </td>
                    <td style="text-align: center;">
                      <span class="status-pill status-<?= $e($stateTone) ?>"><?= $e($stateLabel) ?></span>
                      <?php if (!empty($row['scheduled_publish_at_wib'])): ?>
                        <small style="display: block; color: var(--navy); margin-top: 2px; font-weight: 600;"><?= $e($row['scheduled_publish_at_wib']) ?></small>
                      <?php endif; ?>
                    </td>
                    <td class="decision-action-cell">
                      <div class="decision-action-wrapper">
                        <button type="button" class="quiet-button decision-btn-sm" onclick="openSingleEditModal('<?= $e($row['result_id']) ?>', '<?= $e(addslashes($row['full_name'])) ?>', '<?= $e($row['registration_number']) ?>', '<?= $e($row['decision'] ?? '') ?>', '<?= $e($row['automatic_decision']) ?>', '<?= $e(addslashes($row['note'] ?? '')) ?>')">
                          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                          <?= $row['decision_id'] ? 'Ubah' : 'Tetapkan' ?>
                        </button>

                        <?php if (($row['communication_status'] ?? null) === 'PENDING'): ?>
                          <form method="post" action="/admin/keputusan/publikasikan" onsubmit="return triggerConfirmModal(event, 'publikasikan_single');">
                            <input type="hidden" name="csrf" value="<?= $e($_SESSION['csrf']) ?>">
                            <input type="hidden" name="decision_id" value="<?= $e($row['decision_id']) ?>">
                            <button class="primary decision-btn-sm" type="submit">
                              Publikasikan
                            </button>
                          </form>
                        <?php endif; ?>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

      <div style="padding: 12px 18px; border-top: 1px solid var(--portal-rule, #bcc9d9); background: var(--portal-panel-soft);">
        <?= $decisionPaginator->links('/admin/keputusan', ['search' => $activeSearch, 'status' => $activeStatus, 'session_id' => $activeSessionId, 'admission_path' => $activeAdmissionPath]) ?>
      </div>
    </section>

    <!-- Single Participant Edit Modal -->
    <dialog id="single-edit-modal" class="portal-modal">
      <div class="portal-modal-content" style="max-width: 480px;">
        <div class="portal-modal-header">
          <div class="modal-icon-badge">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
          </div>
          <div>
            <h3 id="modal-edit-participant-name">Tetapkan Keputusan Peserta</h3>
            <p id="modal-edit-reg-num" style="font-size: 12px; color: var(--portal-muted); margin: 2px 0 0;"></p>
          </div>
          <button type="button" class="modal-close-btn" onclick="document.getElementById('single-edit-modal').close()" aria-label="Tutup modal">&times;</button>
        </div>
        <form method="post" action="/admin/keputusan/tetapkan">
          <input type="hidden" name="csrf" value="<?= $e($_SESSION['csrf']) ?>">
          <input type="hidden" name="result_id" id="modal-edit-result-id">
          <div class="portal-modal-body" style="display: grid; gap: 12px; padding: 16px 20px;">
            <label style="display:grid; gap:6px; font-weight:700; font-size:12px; color:var(--navy-deep);">
              Keputusan Resmi Peserta
              <select name="decision" id="modal-edit-decision" onchange="syncModalNoteWithDecision(this)" style="width:100%; min-height:38px; padding:0 10px; border-radius:8px; border:1px solid #cbd5e1; font-size:12.5px;">
                <option value="PASSED">Lulus</option>
                <option value="NOT_PASSED">Belum Dinyatakan Lulus</option>
              </select>
            </label>
            <label style="display:grid; gap:6px; font-weight:700; font-size:12px; color:var(--navy-deep);">
              Catatan / Pesan Resmi untuk Peserta
              <textarea name="note" id="modal-edit-note" required placeholder="Dasar keputusan..." style="width:100%; min-height:70px; padding:8px 10px; border-radius:8px; border:1px solid #cbd5e1; font-size:12px; line-height:1.4;"></textarea>
            </label>
          </div>
          <div class="portal-modal-footer">
            <button type="button" class="quiet-button" onclick="document.getElementById('single-edit-modal').close()">Batal</button>
            <button class="primary batch-btn-primary" type="submit">Simpan Keputusan</button>
          </div>
        </form>
      </div>
    </dialog>

    <!-- Schedule Dialog Modal -->
    <dialog id="schedule-modal" class="portal-modal">
      <div class="portal-modal-content">
        <div class="portal-modal-header">
          <div class="modal-icon-badge">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          </div>
          <div>
            <h3>Jadwalkan Publikasi Serentak</h3>
            <p>Atur tanggal dan jam pengumuman otomatis ke seluruh akun peserta.</p>
          </div>
          <button type="button" class="modal-close-btn" onclick="document.getElementById('schedule-modal').close()" aria-label="Tutup modal">&times;</button>
        </div>
        <form method="post" action="/admin/keputusan/jadwalkan">
          <input type="hidden" name="csrf" value="<?= $e($_SESSION['csrf']) ?>">
          <div class="portal-modal-body">
            <p>Seluruh keputusan pending akan dipublikasikan secara otomatis pada waktu yang ditentukan di bawah ini.</p>
            <label style="display:grid; gap:6px; margin-top:10px; font-weight:600;">
              Tanggal & Waktu Publikasi (WIB)
              <input type="datetime-local" name="publish_at" required style="width:100%; min-height:38px; padding:6px 10px; border:1px solid var(--portal-rule); border-radius:6px;">
            </label>
          </div>
          <div class="portal-modal-footer">
            <button type="button" class="quiet-button" onclick="document.getElementById('schedule-modal').close()">Batal</button>
            <button class="primary" type="submit">Jadwalkan Publikasi</button>
          </div>
        </form>
      </div>
    </dialog>
    <!-- Action Confirm Dialog Modal -->
    <dialog id="action-confirm-modal" class="portal-modal">
      <div class="portal-modal-content" style="max-width: 440px;">
        <div class="portal-modal-header">
          <div class="modal-icon-badge" style="background: var(--portal-panel-soft, #f8fafc); color: var(--navy-deep, #0f172a);">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          </div>
          <div>
            <h3 id="confirm-modal-title">Konfirmasi Aksi</h3>
            <p style="font-size: 12px; color: var(--portal-muted); margin: 2px 0 0;">Mohon konfirmasi sebelum melanjutkan tindakan ini.</p>
          </div>
          <button type="button" class="modal-close-btn" onclick="document.getElementById('action-confirm-modal').close()" aria-label="Tutup modal">&times;</button>
        </div>
        <div class="portal-modal-body" style="padding: 18px 20px;">
          <p id="confirm-modal-message" style="font-size: 13.5px; color: var(--portal-text); line-height: 1.5; margin: 0;"></p>
        </div>
        <div class="portal-modal-footer">
          <button type="button" class="quiet-button" onclick="document.getElementById('action-confirm-modal').close()">Batal</button>
          <button type="button" class="primary" id="confirm-modal-action-btn" onclick="executePendingConfirmAction()">Lanjutkan</button>
        </div>
      </div>
    </dialog>
  </main>
</div>

<script>
let pendingConfirmForm = null;

function triggerConfirmModal(event, actionType, formRef = null) {
  const formElement = formRef || (event ? event.target : null);
  const checkboxes = Array.from(document.querySelectorAll('.participant-select-checkbox:checked'));
  const count = checkboxes.length;

  let title = 'Konfirmasi Aksi';
  let message = '';
  let showSubmit = true;

  if (actionType === 'tetapkan') {
    title = 'Tetapkan Keputusan Massal';
    message = count > 0 
      ? 'Tetapkan keputusan otomatis (sesuai passing grade) untuk ' + count + ' peserta terpilih ini?' 
      : 'Tetapkan keputusan otomatis (sesuai passing grade) untuk seluruh peserta pada filter aktif ini?';
  } else if (actionType === 'publikasikan') {
    title = 'Publikasikan Keputusan Massal';
    message = count > 0 
      ? 'Publikasikan keputusan resmi untuk ' + count + ' peserta terpilih ini sekarang ke akun peserta?' 
      : 'Publikasikan seluruh keputusan pending pada filter aktif ini sekarang ke akun peserta?';
  } else if (actionType === 'sembunyikan_nilai') {
    title = 'Sembunyikan Nilai Ujian';
    if (count === 0) {
      message = 'Silakan pilih setidaknya satu peserta terlebih dahulu dari tabel.';
      showSubmit = false;
    } else {
      message = 'Sembunyikan nilai ujian untuk ' + count + ' peserta terpilih ini dari layar peserta?';
    }
  } else if (actionType === 'tampilkan_nilai') {
    title = 'Tampilkan Nilai Ujian';
    if (count === 0) {
      message = 'Silakan pilih setidaknya satu peserta terlebih dahulu dari tabel.';
      showSubmit = false;
    } else {
      message = 'Tampilkan nilai ujian untuk ' + count + ' peserta terpilih ini di layar peserta?';
    }
  } else if (actionType === 'publikasikan_single') {
    title = 'Publikasikan Keputusan';
    message = 'Publikasikan keputusan resmi ini sekarang ke akun peserta?';
  } else if (actionType === 'toggle_hide') {
    title = 'Sembunyikan Nilai Ujian';
    message = 'Sembunyikan nilai ujian peserta ini dari layar akun peserta?';
  } else if (actionType === 'toggle_show') {
    title = 'Tampilkan Nilai Ujian';
    message = 'Tampilkan nilai ujian peserta ini di layar akun peserta?';
  }

  document.getElementById('confirm-modal-title').textContent = title;
  document.getElementById('confirm-modal-message').textContent = message;
  
  const submitBtn = document.getElementById('confirm-modal-action-btn');
  if (showSubmit) {
    submitBtn.style.display = 'inline-flex';
    submitBtn.textContent = 'Lanjutkan';
    pendingConfirmForm = formElement;
  } else {
    submitBtn.style.display = 'none';
    pendingConfirmForm = null;
  }

  if (event && event.preventDefault) event.preventDefault();
  document.getElementById('action-confirm-modal').showModal();
  return false;
}

function executePendingConfirmAction() {
  if (pendingConfirmForm) {
    const targetForm = pendingConfirmForm;
    pendingConfirmForm = null;
    document.getElementById('action-confirm-modal').close();
    targetForm.submit();
  } else {
    document.getElementById('action-confirm-modal').close();
  }
}

function toggleSelectAllDecisions(master) {
  const checkboxes = document.querySelectorAll('.participant-select-checkbox');
  checkboxes.forEach(cb => cb.checked = master.checked);
  updateDecisionSelectionState();
}

function updateDecisionSelectionState() {
  const checkboxes = Array.from(document.querySelectorAll('.participant-select-checkbox:checked'));
  const count = checkboxes.length;
  const master = document.getElementById('select-all-decisions');
  const total = document.querySelectorAll('.participant-select-checkbox').length;
  if (master) {
    master.checked = total > 0 && count === total;
  }

  const containerResultIds = document.getElementById('hidden-result-ids');
  const containerDecisionIds = document.getElementById('hidden-decision-ids');
  const containerHideScoreIds = document.getElementById('hidden-score-result-ids-hide');
  const containerShowScoreIds = document.getElementById('hidden-score-result-ids-show');

  containerResultIds.innerHTML = '';
  containerDecisionIds.innerHTML = '';
  if (containerHideScoreIds) containerHideScoreIds.innerHTML = '';
  if (containerShowScoreIds) containerShowScoreIds.innerHTML = '';

  checkboxes.forEach(cb => {
    const resId = cb.getAttribute('data-result-id');
    const decId = cb.getAttribute('data-decision-id');
    if (resId) {
      const inputRes = document.createElement('input');
      inputRes.type = 'hidden';
      inputRes.name = 'selected_result_ids[]';
      inputRes.value = resId;
      containerResultIds.appendChild(inputRes);

      if (containerHideScoreIds) {
        const inputHide = document.createElement('input');
        inputHide.type = 'hidden';
        inputHide.name = 'selected_result_ids[]';
        inputHide.value = resId;
        containerHideScoreIds.appendChild(inputHide);
      }
      if (containerShowScoreIds) {
        const inputShow = document.createElement('input');
        inputShow.type = 'hidden';
        inputShow.name = 'selected_result_ids[]';
        inputShow.value = resId;
        containerShowScoreIds.appendChild(inputShow);
      }
    }
    if (decId) {
      const inputDec = document.createElement('input');
      inputDec.type = 'hidden';
      inputDec.name = 'selected_decision_ids[]';
      inputDec.value = decId;
      containerDecisionIds.appendChild(inputDec);
    }
  });

  const labelTetapkan = document.getElementById('label-tetapkan-massal');
  const labelPublikasikan = document.getElementById('label-publikasikan-massal');
  const labelSembunyikan = document.getElementById('label-sembunyikan-nilai');
  const labelTampilkan = document.getElementById('label-tampilkan-nilai');

  if (count > 0) {
    labelTetapkan.textContent = 'Tetapkan (' + count + ')';
    labelPublikasikan.textContent = 'Publikasikan (' + count + ')';
    if (labelSembunyikan) labelSembunyikan.textContent = 'Sembunyikan (' + count + ')';
    if (labelTampilkan) labelTampilkan.textContent = 'Tampilkan (' + count + ')';
  } else {
    labelTetapkan.textContent = 'Tetapkan Massal';
    labelPublikasikan.textContent = 'Publikasikan Massal';
    if (labelSembunyikan) labelSembunyikan.textContent = 'Sembunyikan Nilai';
    if (labelTampilkan) labelTampilkan.textContent = 'Tampilkan Nilai';
  }
}

function syncModalNoteWithDecision(selectEl) {
  const noteEl = document.getElementById('modal-edit-note');
  if (selectEl.value === 'PASSED') {
    noteEl.value = 'Dinyatakan Lulus berdasarkan nilai passing grade sesi ujian.';
  } else {
    noteEl.value = 'Belum memenuhi batas nilai kelulusan minimum.';
  }
}

function openSingleEditModal(resultId, fullName, regNum, currentDecision, autoDecision, currentNote) {
  document.getElementById('modal-edit-result-id').value = resultId;
  document.getElementById('modal-edit-participant-name').textContent = 'Tetapkan Keputusan: ' + fullName;
  document.getElementById('modal-edit-reg-num').textContent = 'No. Registrasi: ' + regNum;
  
  const select = document.getElementById('modal-edit-decision');
  const targetDecision = currentDecision || autoDecision || 'PASSED';
  select.value = targetDecision;

  let note = currentNote || '';
  if (targetDecision === 'PASSED' && (!note || note.includes('Belum memenuhi') || note.includes('belum memenuhi'))) {
    note = 'Dinyatakan Lulus berdasarkan nilai passing grade sesi ujian.';
  } else if (targetDecision === 'NOT_PASSED' && (!note || note.includes('Lulus') || note.includes('lulus'))) {
    note = 'Belum memenuhi batas nilai kelulusan minimum.';
  }
  
  document.getElementById('modal-edit-note').value = note;
  document.getElementById('single-edit-modal').showModal();
}
</script>
</body>
</html>
