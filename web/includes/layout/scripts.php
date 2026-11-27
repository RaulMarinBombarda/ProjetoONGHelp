<?php
// ============================================================
// includes/layout/scripts.php — scripts comuns do fim da página
// ============================================================
// Bootstrap + ui.js (interações compartilhadas) e, em seguida,
// os scripts próprios da página, informados em $page_scripts
// como caminhos relativos a assets/js/ (ex.: 'site/gestao.js')
$page_scripts = array_unique(array_filter((array) ($page_scripts ?? [])));
?>
<script src="<?= asset('libs/bootstrap/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= asset('js/core/ui.js') ?>"></script>
<?php foreach ($page_scripts as $script): ?>
<script src="<?= htmlspecialchars(asset('js/' . $script), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endforeach; ?>
