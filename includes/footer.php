<?php
$base = (basename(dirname($_SERVER['SCRIPT_NAME'])) === 'pages') ? '../' : '';
$footerLogado = !empty($logado) || !empty($_SESSION['user_id']);
?>

<footer class="site-footer">

  <!-- Colunas -->
  <div class="container">
    <div class="ft-grade">
      <div class="ft-marca">
        <a class="ft-logo" href="<?= $base ?>index.php" aria-label="ONGHelp — página inicial">
          <img src="<?= $base ?>assets/img/brand/icone-onghelp.png" alt="" width="36" height="36">
          <span>ONGHelp</span>
        </a>
        <p>
          Plataforma de gestão e transparência para ONGs brasileiras. Conectamos
          organizações sociais a quem quer acompanhar, doar e fazer parte.
        </p>
        <ul class="ft-selos">
          <li><i class="bi bi-patch-check" aria-hidden="true"></i> Cadastros verificados</li>
          <li><i class="bi bi-qr-code" aria-hidden="true"></i> Doações via PIX direto à ONG</li>
          <li><i class="bi bi-file-earmark-bar-graph" aria-hidden="true"></i> Relatórios em CSV e PDF</li>
        </ul>
      </div>

      <nav class="ft-coluna" aria-label="Explorar">
        <h2>Explorar</h2>
        <ul>
          <li><a href="<?= $base ?>index.php">Início</a></li>
          <li><a href="<?= $base ?>pages/ongs.php">Diretório de ONGs</a></li>
          <li><a href="<?= $base ?>pages/transparencia.php">Portal de Transparência</a></li>
          <li><a href="<?= $base ?>pages/noticias-publicas.php">Notícias</a></li>
        </ul>
      </nav>

      <nav class="ft-coluna" aria-label="Para ONGs">
        <h2>Para ONGs</h2>
        <ul>
          <?php if ($footerLogado): ?>
            <li><a href="<?= $base ?>pages/gestao.php">Painel de gestão</a></li>
            <li><a href="<?= $base ?>pages/noticias.php">Minhas notícias</a></li>
            <li><a href="<?= $base ?>pages/perfilprivado.php">Meu perfil</a></li>
          <?php else: ?>
            <li><a href="<?= $base ?>pages/cadastro.php">Cadastrar organização</a></li>
            <li><a href="<?= $base ?>pages/login.php">Entrar</a></li>
          <?php endif; ?>
        </ul>
      </nav>

      <nav class="ft-coluna" aria-label="Institucional">
        <h2>Institucional</h2>
        <ul>
          <li><a href="<?= $base ?>pages/privacidade.php">Política de privacidade</a></li>
          <li><a href="<?= $base ?>pages/termos.php">Termos de uso</a></li>
          <li><a href="mailto:contato@gmail.com">Fale conosco</a></li>
        </ul>
      </nav>

      <div class="ft-coluna ft-contato">
        <h2>Contato</h2>
        <ul>
          <li><i class="bi bi-envelope" aria-hidden="true"></i><a href="mailto:contato@gmail.com">contato@gmail.com</a></li>
          <li><i class="bi bi-telephone" aria-hidden="true"></i><a href="tel:+5516997079789">(16) 99707-9789</a></li>
          <li><i class="bi bi-geo-alt" aria-hidden="true"></i><span>Taquaritinga, SP — Brasil</span></li>
          <li><i class="bi bi-clock" aria-hidden="true"></i><span>Seg. a sex., 9h às 18h</span></li>
        </ul>
      </div>
    </div>

    <!-- Linha final -->
    <div class="ft-base">
      <span>&copy; <?= date('Y') ?> ONGHelp · Projeto Integrador</span>
      <span class="ft-base-nota">Valores financeiros declarados pelas próprias organizações.</span>
      <a href="<?= $base ?>pages/equipe.php" class="ft-equipe" rel="nofollow">
        <i class="bi bi-people" aria-hidden="true"></i> Faça parte da equipe <span aria-hidden="true">→</span>
      </a>
    </div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= $base ?>assets/js/ui.js?v=3"></script>
<?php if (!empty($page_script)): ?>
<script src="<?= $base ?>assets/js/<?= htmlspecialchars($page_script) ?>?v=12"></script>
<?php endif; ?>
</body>
</html>
