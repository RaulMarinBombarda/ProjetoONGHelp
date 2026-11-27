<?php
$base = baseUrl();
$footerLogado = !empty($logado) || !empty($_SESSION['user_id']);
?>

</main>

<footer class="site-footer">

  <!-- colunas -->
  <div class="container">
    <div class="ft-grade">
      <div class="ft-marca">
        <a class="ft-logo" href="<?= url('index.php') ?>" aria-label="ONGHelp — página inicial">
          <img src="<?= asset('img/icone-onghelp.png') ?>" alt="" width="36" height="36">
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
          <li><a href="<?= url('index.php') ?>">Início</a></li>
          <li><a href="<?= url('pages/ongs.php') ?>">Diretório de ONGs</a></li>
          <li><a href="<?= url('pages/transparencia.php') ?>">Portal de Transparência</a></li>
          <li><a href="<?= url('pages/noticias.php') ?>">Notícias</a></li>
          <li><a href="<?= url('pages/legislacao.php') ?>">Legislação</a></li>
        </ul>
      </nav>

      <nav class="ft-coluna" aria-label="Para ONGs">
        <h2>Para ONGs</h2>
        <ul>
          <?php if ($footerLogado): ?>
            <li><a href="<?= url('pages/gestao.php') ?>">Painel de gestão</a></li>
            <li><a href="<?= url('pages/minhas-noticias.php') ?>">Minhas notícias</a></li>
            <li><a href="<?= url('pages/notificacoes.php') ?>">Notificações</a></li>
            <li><a href="<?= url('pages/perfil-privado.php') ?>">Meu perfil</a></li>
          <?php else: ?>
            <li><a href="<?= url('pages/cadastro.php') ?>">Cadastrar organização</a></li>
            <li><a href="<?= url('pages/termos.php') ?>#cadastro">Como verificamos</a></li>
            <li><a href="<?= url('pages/login.php') ?>">Entrar</a></li>
          <?php endif; ?>
        </ul>
      </nav>

      <nav class="ft-coluna" aria-label="Institucional">
        <h2>Institucional</h2>
        <ul>
          <li><a href="<?= url('pages/privacidade.php') ?>">Política de privacidade</a></li>
          <li><a href="<?= url('pages/termos.php') ?>">Termos de uso</a></li>
          <li><a href="<?= url('pages/contato.php') ?>">Fale conosco</a></li>
        </ul>
      </nav>

      <div class="ft-coluna ft-contato">
        <h2>Contato</h2>
        <ul>
          <?php // o e-mail leva à página de contato (Gmail, copiar, programa de e-mail), um mailto sozinho não abre nada sem programa de e-mail configurado ?>
          <li><i class="bi bi-envelope" aria-hidden="true"></i><a href="<?= url('pages/contato.php') ?>"><?= ONGHELP_EMAIL_EQUIPE ?></a></li>
          <li class="ft-whats">
            <i class="bi bi-whatsapp" aria-hidden="true"></i>
            <div>
              <span class="ft-whats-rotulo" id="ftWhatsRotulo">WhatsApp</span>
              <ul class="ft-whats-numeros" aria-labelledby="ftWhatsRotulo">
                <?php foreach (whatsappsEquipe() as $contatoEquipe): ?>
                  <li><a href="<?= e($contatoEquipe['url']) ?>" target="_blank" rel="noopener" aria-label="WhatsApp <?= e($contatoEquipe['exibicao']) ?>"><?= e($contatoEquipe['curto']) ?></a></li>
                <?php endforeach; ?>
              </ul>
            </div>
          </li>
          <li><i class="bi bi-chat-dots" aria-hidden="true"></i><a href="<?= url('pages/contato.php') ?>">Todas as formas de contato</a></li>
          <li><i class="bi bi-geo-alt" aria-hidden="true"></i><span>Taquaritinga, SP — Brasil</span></li>
        </ul>
      </div>
    </div>

    <!-- linha final -->
    <div class="ft-base">
      <span>&copy; <?= date('Y') ?> ONGHelp · Projeto Integrador</span>
      <span class="ft-base-nota">Valores financeiros declarados pelas próprias organizações.</span>
      <a href="<?= url('pages/equipe.php') ?>" class="ft-equipe" rel="nofollow">
        <i class="bi bi-people" aria-hidden="true"></i> Faça parte da equipe <span aria-hidden="true">→</span>
      </a>
    </div>
  </div>
</footer>

<?php include __DIR__ . '/../components/modal-confirmar.php'; ?>
<?php include __DIR__ . '/scripts.php'; ?>
</body>
</html>
