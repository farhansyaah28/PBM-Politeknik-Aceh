<?php
$e = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$defaultPolicies = $retentionConfiguration['defaults'];
$participantOverrides = $retentionConfiguration['overrides'];
$portalRole = 'ADMIN';
$portalActive = 'retention';
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
<main id="main-content" tabindex="-1" class="portal-main portal-workspace retention-page">
    <header class="portal-toolbar portal-page-toolbar">
        <div><h1>Retensi dan legal hold</h1><p>Atur masa simpan, pengecualian peserta, dan perlindungan data untuk kebutuhan audit.</p></div>
        <div class="portal-toolbar-actions"><span class="portal-date">Tata kelola data</span></div>
    </header>

    <?php if ($retentionSuccess): ?><div class="alert success-alert"><?= $e($retentionSuccess) ?></div><?php endif; ?>
    <?php if ($retentionError): ?><div class="alert error"><?= $e($retentionError) ?></div><?php endif; ?>

    <section class="report-summary">
        <article><span>Foto proctoring</span><b><?= $e($retentionInventory['proctoring_photos']) ?></b></article>
        <article><span>Event keamanan</span><b><?= $e($retentionInventory['security_events']) ?></b></article>
        <article><span>Record privat</span><b><?= $e($retentionInventory['private_records']) ?></b></article>
        <article><span>Legal hold aktif</span><b><?= $e($retentionInventory['active_holds']) ?></b></article>
    </section>

    <section class="retention-section">
        <article class="card compact retention-guide-card">
            <h2>Panduan memilih fitur retensi</h2>
            <div class="retention-guide">
                <div><span>Default</span><b>Masa simpan umum</b><small>Aturan dalam tahun untuk seluruh peserta dan setiap jenis data.</small></div>
                <div><span>Pengecualian</span><b>Masa simpan khusus peserta</b><small>Durasi berbeda untuk seluruh data satu peserta.</small></div>
                <div><span>Individu</span><b>Legal hold satu peserta</b><small>Untuk audit, keberatan, atau sengketa satu peserta.</small></div>
                <div><span>Menyeluruh</span><b>Legal hold seluruh peserta</b><small>Untuk audit atau investigasi yang mencakup seluruh peserta.</small></div>
            </div>
        </article>
    </section>

    <section class="retention-section">
        <div class="retention-section-heading"><h2>Kebijakan masa simpan</h2><p>Atur aturan umum terlebih dahulu, lalu gunakan pengecualian hanya untuk peserta yang membutuhkan perlakuan khusus.</p></div>
        <div class="retention-workspace retention-policy-grid">
        <article class="portal-panel retention-card">
            <h2>Kebijakan masa simpan default</h2>
            <p class="intro">Berlaku untuk seluruh peserta, kecuali peserta yang memiliki pengecualian di bawah. Isi dalam tahun. Gunakan ini untuk aturan normal, bukan kasus perorangan.</p>
            <form class="stack retention-form" method="post" action="/admin/retensi/default">
                <input type="hidden" name="csrf" value="<?= $e($_SESSION['csrf']) ?>">
                <div class="retention-default-fields">
                    <label>Foto proctoring (tahun)<input type="number" name="PROCTORING_PHOTOS" min="1" max="100" value="<?= $e($defaultPolicies['PROCTORING_PHOTOS'] ?? 1) ?>" required></label>
                    <label>Event keamanan ujian (tahun)<input type="number" name="SECURITY_EVENTS" min="1" max="100" value="<?= $e($defaultPolicies['SECURITY_EVENTS'] ?? 1) ?>" required></label>
                    <label>Persetujuan peserta (tahun)<input type="number" name="CONSENT_RECORDS" min="1" max="100" value="<?= $e($defaultPolicies['CONSENT_RECORDS'] ?? 5) ?>" required></label>
                    <label>Audit log (tahun)<input type="number" name="AUDIT_LOGS" min="1" max="100" value="<?= $e($defaultPolicies['AUDIT_LOGS'] ?? 5) ?>" required></label>
                </div>
                <button class="primary">Simpan masa simpan default</button>
            </form>
        </article>

        <article class="portal-panel retention-card">
            <h2>Atur pengecualian masa simpan peserta</h2>
            <p class="intro">Gunakan bila seluruh data seorang peserta perlu disimpan lebih lama atau lebih singkat daripada kebijakan umum. Ini bukan legal hold: Admin tetap menentukan jumlah tahun dan dapat mengembalikan peserta ke aturan default.</p>
            <form class="stack retention-form" method="post" action="/admin/retensi/pengecualian">
                <input type="hidden" name="csrf" value="<?= $e($_SESSION['csrf']) ?>">
                <label>Peserta<select name="participant_id" required><option value="">Pilih peserta</option><?php foreach ($retentionParticipants as $participant): ?><option value="<?= $e($participant['id']) ?>"><?= $e($participant['registration_number']) ?> · <?= $e($participant['full_name']) ?></option><?php endforeach; ?></select></label>
                <label>Masa simpan seluruh data peserta (tahun)<input type="number" name="retention_years" min="1" max="100" value="5" required></label>
                <label>Alasan pengecualian<textarea name="reason" required placeholder="Contoh: keberatan peserta masih dalam penanganan."></textarea></label>
                <button class="primary">Simpan pengecualian peserta</button>
            </form>
        </article>
        </div>
    </section>

    <section class="retention-section">
        <article class="portal-panel retention-list-card">
            <h2><?= count($participantOverrides) ?> peserta menggunakan masa simpan khusus</h2>
            <div class="config-list">
                <?php foreach ($participantOverrides as $override): ?>
                    <div><span><b><?= $e($override['registration_number']) ?> · <?= $e($override['full_name']) ?></b><small><?= $e($override['retention_years']) ?> tahun untuk seluruh data peserta · <?= $e($override['reason']) ?></small></span><form method="post" action="/admin/retensi/pengecualian/hapus" onsubmit="return confirm('Hapus pengecualian ini? Peserta akan kembali memakai masa simpan default.');"><input type="hidden" name="csrf" value="<?= $e($_SESSION['csrf']) ?>"><input type="hidden" name="participant_id" value="<?= $e($override['participant_id']) ?>"><button class="quiet-button danger-button">Kembali ke default</button></form></div>
                <?php endforeach; ?>
                <?php if ($participantOverrides === []): ?><p class="intro">Belum ada pengecualian masa simpan peserta.</p><?php endif; ?>
            </div>
        </article>
    </section>

    <section class="retention-section">
        <div class="retention-section-heading"><h2>Legal hold untuk perlindungan data</h2><p>Gunakan legal hold ketika penyimpanan data harus dipertahankan sampai audit, keberatan, atau investigasi selesai.</p></div>
        <div class="retention-workspace retention-hold-grid">
        <article class="portal-panel retention-card">
            <h2>Terapkan legal hold satu peserta</h2>
            <p class="intro">Gunakan untuk audit, keberatan, sengketa, atau investigasi yang hanya melibatkan satu peserta. Legal hold tidak menetapkan jumlah tahun dan harus dicabut manual ketika kebutuhan penyimpanan sudah selesai.</p>
            <form class="stack retention-form" method="post" action="/admin/retensi/tahan">
                <input type="hidden" name="csrf" value="<?= $e($_SESSION['csrf']) ?>">
                <label>Peserta<select name="participant_id" required><option value="">Pilih peserta</option><?php foreach ($retentionParticipants as $participant): ?><option value="<?= $e($participant['id']) ?>"><?= $e($participant['registration_number']) ?> · <?= $e($participant['full_name']) ?></option><?php endforeach; ?></select></label>
                <label>Alasan<textarea name="reason" required placeholder="Contoh: sengketa atau keberatan yang masih berjalan."></textarea></label>
                <button class="primary">Aktifkan legal hold</button>
            </form>
        </article>

        <article class="portal-panel retention-card">
            <h2>Terapkan legal hold seluruh peserta</h2>
            <p class="intro">Gunakan ketika audit atau investigasi berlaku untuk seluruh peserta. Fitur ini menerapkan legal hold massal, bukan mengubah masa simpan dalam tahun. Tindakan ini tidak menghapus maupun mengubah data peserta.</p>
            <form class="stack retention-form" method="post" action="/admin/retensi/semua" onsubmit="return confirm('Aktifkan legal hold untuk seluruh peserta? Peserta yang sudah ditahan akan dilewati.');">
                <input type="hidden" name="csrf" value="<?= $e($_SESSION['csrf']) ?>">
                <label>Alasan retensi<textarea name="reason" required placeholder="Contoh: audit penerimaan mahasiswa baru Tahun Akademik 2026/2027."></textarea></label>
                <label class="consent-check"><input type="checkbox" name="confirm_all" value="1" required> <span>Saya memahami bahwa legal hold akan diterapkan kepada seluruh peserta yang belum memiliki legal hold aktif.</span></label>
                <button class="quiet-button">Aktifkan legal hold seluruh peserta</button>
            </form>
        </article>
        </div>
    </section>

    <section class="retention-section">
        <article class="portal-panel retention-list-card">
            <h2><?= $e($retentionPaginator->total) ?> peserta dengan legal hold aktif</h2>
            <p class="intro">Legal hold aktif diprioritaskan atas kebijakan masa simpan khusus maupun default. Cabut hold hanya setelah alasan penyimpanan selesai.</p>
            <div class="config-list">
                <?php foreach ($retentionHolds as $hold): ?>
                    <div><span><b><?= $e($hold['registration_number']) ?> · <?= $e($hold['full_name']) ?></b><small><?= $e($hold['reason']) ?></small></span><form method="post" action="/admin/retensi/lepas" onsubmit="return confirm('Cabut legal hold ini? Data peserta tidak akan dihapus.');"><input type="hidden" name="csrf" value="<?= $e($_SESSION['csrf']) ?>"><input type="hidden" name="hold_id" value="<?= $e($hold['id']) ?>"><button class="quiet-button danger-button">Cabut hold</button></form></div>
                <?php endforeach; ?>
                <?php if ($retentionHolds === []): ?><p class="intro">Belum ada legal hold aktif.</p><?php endif; ?>
            </div>
            <?= $retentionPaginator->links('/admin/retensi') ?>
        </article>
    </section>
</main></div>
</body>
</html>
