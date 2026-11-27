<?php
// ============================================================
// includes/components/auth-lateral.php — lateral das telas de
// login e cadastro de ONG (marca, chamada e benefícios),
// requer $pdo
// ============================================================

$totalOngsAtivas = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE status = 'aprovada'")->fetchColumn();
$finalidade = 'o ONGHelp para publicar relatórios, divulgar seu canal de doações e conquistar a confiança dos seus apoiadores.';
$fraseOngs = match (true) {
    $totalOngsAtivas === 0 => 'Use ' . $finalidade,
    $totalOngsAtivas === 1 => '1 ONG já usa ' . $finalidade,
    default                => $totalOngsAtivas . ' ONGs já usam ' . $finalidade,
};
?>
  <aside class="login-aside">
    <a class="login-brand" href="../index.php" aria-label="ONGHelp — página inicial">
      <img src="<?= asset('img/icone-onghelp.png') ?>" alt="" class="brand-icon" width="36" height="36">
      <span>ONGHelp</span>
    </a>

    <div class="login-aside-body">
      <h1>Gestão e transparência para quem transforma o Brasil</h1>
      <p><?= e($fraseOngs) ?></p>

      <div class="login-benefits glass-dark">
        <ul class="login-benefits-list">
          <li class="login-benefit">
            <span class="login-benefit-check" aria-hidden="true"><i class="bi bi-check-lg"></i></span>
            Perfil público após aprovação
          </li>
          <li class="login-benefit">
            <span class="login-benefit-check" aria-hidden="true"><i class="bi bi-check-lg"></i></span>
            Portal de transparência financeira
          </li>
          <li class="login-benefit">
            <span class="login-benefit-check" aria-hidden="true"><i class="bi bi-check-lg"></i></span>
            Divulgação de doações via PIX
          </li>
          <li class="login-benefit">
            <span class="login-benefit-check" aria-hidden="true"><i class="bi bi-check-lg"></i></span>
            Painel de gestão de projetos
          </li>
        </ul>
      </div>
    </div>

    <div class="login-aside-footer">
      &copy; <?= date('Y') ?> ONGHelp · Projeto Integrador
    </div>
  </aside>
