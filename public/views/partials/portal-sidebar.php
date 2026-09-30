<?php
/** @var callable $e */
/** @var list<array{key:string,label:string,href:string}> $portalNav */
?>
<a class="skip-link portal-skip-link" href="#main-content">Lewati ke konten utama</a>
<aside class="portal-sidebar">
  <a class="portal-brand" href="<?= $e($portalHome) ?>">
    <span class="portal-brand-mark"><img src="/assets/logo-politeknik-aceh.png" alt=""></span>
    <span><strong>PMB</strong><small>Politeknik Aceh</small></span>
  </a>
  <div class="portal-role"><span>Ruang kerja</span><strong><?= $e($portalRoleLabel) ?></strong></div>
  <nav class="portal-nav" aria-label="Navigasi <?= $e($portalRoleLabel) ?>">
    <?php foreach ($portalNav as $item): ?>
      <a href="<?= $e($item['href']) ?>"<?= $item['key'] === $portalActive ? ' class="is-current" aria-current="page"' : '' ?>><?= $e($item['label']) ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="portal-sidebar-footer">
    <a href="/">Halaman utama</a>
    <form method="post" action="/logout">
      <input type="hidden" name="csrf" value="<?= $e($_SESSION['csrf']) ?>">
      <button>Keluar dari sistem</button>
    </form>
  </div>
</aside>
