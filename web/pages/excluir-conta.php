<?php
// ============================================================
// pages/excluir-conta.php — desativação de conta (hard delete)
// projeto ONGHelp · Projeto Integrador 2026
// ============================================================
//
// mudança EM relação à versão anterior:
//   a versão original fazia apenas soft-delete (ativo = 0),
//   esta versão faz hard-delete: apaga o registro de usuarios
//   e, por CASCADE no banco, remove também movimentacoes e
//   projetos vinculados à ONG
//
// fluxo:
//   GET  → exibe tela de confirmação com formulário
//   POST → executa o DELETE e encerra a sessão
//
// exige sessão ativa — redireciona para login caso contrário
// ============================================================

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/modules/gestao.php';

// ── protege: só usuário logado acessa ────────────────────────
exigirLoginOng();
csrfExigir();

$id    = (int) $_SESSION['user_id'];
$erro  = '';

// ── POST: executa exclusão permanente ────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {

    // dupla verificação: o usuário confirmou no formulário
    if (!isset($_POST['confirmar'])) {
        $erro = 'Por favor, marque a caixa de confirmação para continuar.';
    } else {
        // arquivos enviados pela ONG: o banco apaga as linhas em cascata,
        // mas as fotos no disco precisam ser removidas aqui (LGPD)
        $stmtFoto = $pdo->prepare('SELECT foto_perfil FROM usuarios WHERE id = ?');
        $stmtFoto->execute([$id]);
        $fotoPerfil = (string) ($stmtFoto->fetchColumn() ?: '');

        $stmtFotos = $pdo->prepare(
            'SELECT f.arquivo FROM noticias_fotos f JOIN noticias n ON n.id = f.noticia_id WHERE n.usuario_id = ?'
        );
        $stmtFotos->execute([$id]);
        $fotosNoticias = $stmtFotos->fetchAll(PDO::FETCH_COLUMN);

        $stmtComprovantes = $pdo->prepare('SELECT comprovante FROM movimentacoes WHERE usuario_id = ? AND comprovante IS NOT NULL');
        $stmtComprovantes->execute([$id]);
        $comprovantes = $stmtComprovantes->fetchAll(PDO::FETCH_COLUMN);

        // DELETE em cascata: remove movimentações, projetos, notícias,
        // fotos, links e verificações (FOREIGN KEY ... ON DELETE CASCADE)
        $pdo->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$id]);

        if ($fotoPerfil !== '' && basename($fotoPerfil) === $fotoPerfil) {
            @unlink(ONGHELP_UPLOADS_DIR . '/perfis/' . $fotoPerfil);
        }
        foreach ($fotosNoticias as $arquivo) {
            noticiaFotoApagarArquivo((string) $arquivo); // ignora as fotos de demonstração
        }
        foreach ($comprovantes as $arquivo) {
            comprovanteApagar((string) $arquivo);
        }

        // encerra a sessão por completo
        encerrarLogin('user');

        // redireciona para home com aviso
        header('Location: ../index.php?conta=excluida');
        exit;
    }
}

// ── GET: busca nome da ONG para exibir na tela ────────────────
$stmt = $pdo->prepare("SELECT nome FROM usuarios WHERE id = ? AND status = 'aprovada'");
$stmt->execute([$id]);
$user = $stmt->fetch();

// se o usuário não for encontrado (conta já excluída), desloga
if (!$user) {
    encerrarLogin('user');
    header('Location: login.php');
    exit;
}

$page_title = 'Excluir Conta';
$active_nav = '';
include __DIR__ . '/../includes/layout/header.php';
?>

<section class="delete-page">
  <div class="container">

    <!-- ícone e título -->
    <div class="delete-hero">
      <div class="delete-hero-icon" aria-hidden="true">
        <i class="bi bi-heartbreak-fill"></i>
      </div>
      <h1>Que pena ver você ir!</h1>
      <p>Pedimos que leia com atenção antes de confirmar.</p>
    </div>

    <?php if ($erro): ?>
      <div class="alert alert-danger">
        <i class="bi bi-exclamation-circle" aria-hidden="true"></i> <?= htmlspecialchars($erro) ?>
      </div>
    <?php endif; ?>

    <!-- card de aviso -->
    <div class="card delete-panel">

      <p>
        Você está solicitando o encerramento da conta de
        <strong><?= htmlspecialchars($user['nome']) ?></strong>.
        Se confirmar, todos os dados abaixo serão <strong>definitivamente removidos</strong>
        — não será possível recuperá-los no futuro:
      </p>

      <ul>
        <li>O perfil público da sua organização</li>
        <li>O histórico de receitas e despesas registradas</li>
        <li>Os projetos e suas metas cadastradas</li>
        <li>As notícias publicadas, com as fotos, e os comprovantes enviados</li>
      </ul>

      <p>
        Se ficou com alguma dúvida ou prefere conversar antes de tomar essa decisão,
        fique à vontade para nos procurar. Teremos satisfação em ajudar.
      </p>

      <!-- formulário de confirmação -->
      <form method="POST" action="">
        <?= csrfField() ?>
        <div class="form-check mb-4">
          <input type="checkbox" name="confirmar" id="confirmar" class="form-check-input">
          <label for="confirmar" class="form-check-label">
            Sim, estou ciente e desejo encerrar a conta definitivamente.
          </label>
        </div>

        <div class="form-actions d-flex flex-wrap gap-2 justify-content-center">
          <a href="perfil-privado.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left" aria-hidden="true"></i> Voltar
          </a>
          <button type="submit" class="btn btn-danger">
            <i class="bi bi-trash" aria-hidden="true"></i> Sim, encerrar minha conta
          </button>
        </div>
      </form>
    </div>

  </div>
</section>

<?php include __DIR__ . '/../includes/layout/footer.php'; ?>
