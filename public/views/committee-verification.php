<?php
$e=static fn($value):string=>htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');
$identityChecks=['V-01'=>'Data wajib lengkap dan dapat dibaca','V-02'=>'Nama sesuai bukti identitas','V-03'=>'Data identitas utama konsisten','V-04'=>'Email dan nomor kontak dapat digunakan','V-05'=>'Asal sekolah dan tahun lulus sesuai','V-06'=>'Program studi dan gelombang valid','V-07'=>'Dokumen pendukung dapat dibaca','V-08'=>'Tidak ditemukan akun duplikat','V-09'=>'Data dibuat melalui proses Admin','V-10'=>'Tidak ada hambatan administratif lain'];
$statusLabels=['PENDING'=>'Menunggu','APPROVED'=>'Disetujui','NEEDS_CORRECTION'=>'Perlu perbaikan','REJECTED'=>'Ditolak'];
$statusTone=static fn(string $status):string=>match($status){'APPROVED'=>'success','NEEDS_CORRECTION'=>'warning','REJECTED'=>'danger',default=>'info'};
$portalRole='COMMITTEE';
$portalActive='verification';
require __DIR__.'/partials/portal-context.php';
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= $e($title) ?> | Politeknik Aceh</title><link rel="stylesheet" href="/assets/app.css"></head><body class="portal-body verification-page-body"><div class="portal-shell" data-dashboard-role="COMMITTEE">
<?php require __DIR__.'/partials/portal-sidebar.php'; ?>
<main id="main-content" tabindex="-1" class="portal-main portal-workspace verification-page">
<header class="portal-toolbar portal-page-toolbar"><div><h1>Verifikasi peserta</h1><p>Tinjau data peserta secara berurutan atau gunakan verifikasi massal untuk menyetujui seluruh antrean.</p></div><div class="portal-toolbar-actions"><span class="portal-date"><?= $e($queuePaginator->total) ?> dalam antrean</span><button class="primary bulk-verify-btn" type="button" onclick="document.getElementById('bulk-verify-modal').showModal()">Verifikasi & Setujui Semua</button></div></header>
<?php if($committeeSuccess):?><div class="alert success-alert" role="status"><?= $e($committeeSuccess) ?></div><?php endif;?><?php if($committeeError):?><div class="alert error" role="alert"><?= $e($committeeError) ?></div><?php endif;?>
<section class="committee-layout"><aside class="portal-panel queue-panel"><form class="queue-search" method="get"><label>Cari peserta<input name="q" placeholder="Nama atau nomor peserta" value="<?= $e($_GET['q']??'') ?>"></label><button class="primary">Cari</button></form><p class="queue-count"><?= $e($queuePaginator->total) ?> peserta ditemukan</p><nav class="queue-list" aria-label="Daftar peserta verifikasi"><?php foreach($queue as $row):$selected=$workspace&&$workspace['id']===$row['id'];?><a class="queue-item <?= $selected?'is-selected':'' ?>" href="/panitia/verifikasi?<?= $e(http_build_query(['peserta'=>$row['id'],'q'=>$_GET['q']??'','page'=>$queuePaginator->page])) ?>" <?= $selected?'aria-current="true"':'' ?>><b><?= $e($row['full_name']) ?></b><small><?= $e($row['registration_number']) ?></small><span><?= $e($statusLabels[$row['verification_status']]??$row['verification_status']) ?></span></a><?php endforeach;?></nav><?= $queuePaginator->links('/panitia/verifikasi',['q'=>$_GET['q']??'']) ?></aside>
<section class="verification-workspace"><?php if(!$workspace):?><div class="portal-panel verification-empty"><h2>Belum ada peserta</h2><p>Peserta yang dibuat Admin akan tampil di sini.</p></div><?php else:?><header class="portal-panel participant-summary"><div><h2><?= $e($workspace['full_name']) ?></h2><p class="participant-number"><?= $e($workspace['registration_number']) ?></p></div><dl><div><dt>Kontak</dt><dd><?= $e($workspace['email']) ?><small><?= $e($workspace['phone_number']) ?></small></dd></div><div><dt>Asal sekolah</dt><dd><?= $e($workspace['school_name']) ?></dd></div><div><dt>Program dan gelombang</dt><dd><?= $e($workspace['program_choice']) ?><small><?= $e($workspace['wave']) ?></small></dd></div></dl><span class="status-pill status-<?= $workspace['eligible']?'success':'warning' ?>"><?= $workspace['eligible']?'Eligible ujian':'Belum eligible' ?></span></header>
<form class="portal-panel verification-card" method="post" action="/panitia/verifikasi/identitas"><input type="hidden" name="csrf" value="<?= $e($_SESSION['csrf']) ?>"><input type="hidden" name="participant_id" value="<?= $e($workspace['id']) ?>"><input type="hidden" name="q" value="<?= $e($_GET['q']??'') ?>"><?php foreach($identityChecks as $code=>$label):?><input type="hidden" name="checks[]" value="<?= $e($code) ?>"><?php endforeach;?><div class="verification-card-heading"><div><h2>Status verifikasi identitas</h2><p>Data berkas peserta divalidasi secara otomatis saat masuk sistem.</p></div><span class="status-pill status-<?= $e($statusTone($workspace['verification_status'])) ?>"><?= $e($statusLabels[$workspace['verification_status']]??$workspace['verification_status']) ?></span></div><div class="auto-verify-card-body"><div class="auto-verify-banner"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg><div><strong>Verifikasi Otomatis Aktif</strong><p>Seluruh butir pemeriksaan identitas (V-01 s/d V-10) telah divalidasi sistem. Peserta ini siap untuk ditugaskan ke sesi ujian.</p></div></div><div class="verification-decision-panel"><div class="decision-fields"><label>Keputusan verifikasi<select name="decision"><option value="APPROVED" <?= $workspace['last_identity_decision']==='APPROVED'?'selected':'' ?>>Disetujui (Lulus Verifikasi)</option><option value="NEEDS_CORRECTION" <?= $workspace['last_identity_decision']==='NEEDS_CORRECTION'?'selected':'' ?>>Perlu perbaikan berkas</option><option value="REJECTED" <?= $workspace['last_identity_decision']==='REJECTED'?'selected':'' ?>>Ditolak</option></select></label><label>Catatan untuk peserta<textarea name="note" placeholder="Tuliskan catatan perbaikan atau alasan penolakan bila ada berkas bermasalah."><?= $e($workspace['identity_note']??'') ?></textarea></label></div><button class="primary">Simpan keputusan</button></div></div></form><?php endif;?></section></section>

<dialog id="bulk-verify-modal" class="portal-modal">
  <div class="portal-modal-content">
    <div class="portal-modal-header">
      <div class="modal-icon-badge">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
      </div>
      <div>
        <h3>Konfirmasi Verifikasi Massal</h3>
        <p>Tindakan ini akan menyetujui seluruh peserta yang ada dalam antrean.</p>
      </div>
      <button type="button" class="modal-close-btn" onclick="document.getElementById('bulk-verify-modal').close()" aria-label="Tutup modal">&times;</button>
    </div>
    <div class="portal-modal-body">
      <p>Apakah Anda yakin ingin menyetujui <strong>seluruh peserta</strong> yang belum disetujui secara massal?</p>
      <ul class="modal-info-list">
        <li>Status verifikasi identitas akan diubah menjadi <strong>Disetujui (APPROVED)</strong>.</li>
        <li>Seluruh checklist verifikasi <strong>V-01 sampai V-10</strong> akan diaktifkan otomatis.</li>
        <li>Peserta yang telah disetujui akan langsung <strong>eligible</strong> untuk ditugaskan ke sesi ujian.</li>
      </ul>
    </div>
    <div class="portal-modal-footer">
      <button type="button" class="quiet-button" onclick="document.getElementById('bulk-verify-modal').close()">Batal</button>
      <form method="post" action="/panitia/verifikasi/semua">
        <input type="hidden" name="csrf" value="<?= $e($_SESSION['csrf']) ?>">
        <button class="primary" type="submit">Ya, Setujui Semua Peserta</button>
      </form>
    </div>
  </div>
</dialog>
</main></div></body></html>
