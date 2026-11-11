<?php
// ============================================================
// pages/desativar.php — Desativação de conta (hard delete)
// Projeto ONGHelp · Projeto Integrador 2026
// ============================================================
//
// MUDANÇA EM RELAÇÃO À VERSÃO ANTERIOR:
//   A versão original fazia apenas soft-delete (ativo = 0).
//   Esta versão faz hard-delete: apaga o registro de usuarios
//   e, por CASCADE no banco, remove também movimentacoes e
//   projetos vinculados à ONG.
//
// FLUXO:
//   GET  → exibe tela de confirmação com formulário
//   POST → executa o DELETE e encerra a sessão
//
// Exige sessão ativa — redireciona para login caso contrário.
// ============================================================

require_once '../includes/db.php';
require_once '../includes/helpers.php';
require_once '../includes/session.php';
require_once '../includes/csrf.php';

// ── Protege: só usuário logado acessa ────────────────────────
exigirLoginOng();
csrfExigir();

$id    = (int) $_SESSION['user_id'];
$erro  = '';

// ── POST: executa exclusão permanente ────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {

    // Dupla verificação: o usuário confirmou no formulário
    if (!isset($_POST['confirmar'])) {
        $erro = 'Por favor, marque a caixa de confirmação para continuar.';
    } else {
        // DELETE em cascata: remove também movimentacoes e projetos
        // (graças às FOREIGN KEY ... ON DELETE CASCADE no SQL)
        $pdo->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$id]);

        // Encerra a sessão por completo
        destruirSessao();

        // Redireciona para home com aviso
        header('Location: ../index.php?conta=excluida');
        exit;
    }
}

// ── GET: busca nome da ONG para exibir na tela ────────────────
$stmt = $pdo->prepare("SELECT nome FROM usuarios WHERE id = ? AND status = 'aprovada'");
$stmt->execute([$id]);
$user = $stmt->fetch();

// Se o usuário não for encontrado (conta já excluída), desloga
if (!$user) {
    destruirSessao();
    header('Location: login.php');
    exit;
}

$page_title = 'Excluir Conta';
$active_nav = '';
include '../includes/header.php';
?>

<section class="delete-page">
  <div class="container">

    <!-- Ícone e título -->
    <div class="delete-hero">
      <div class="delete-hero-icon" aria-hidden="true">
        <i class="bi bi-heartbreak-fill"></i>
      </div>
      <h2>Que pena ver você ir!</h2>
      <p>Pedimos que leia com atenção antes de confirmar.</p>
    </div>

    <?php if ($erro): ?>
      <div class="alert alert-danger">
        <i class="bi bi-exclamation-circle" aria-hidden="true"></i> <?= htmlspecialchars($erro) ?>
      </div>
    <?php endif; ?>

    <!-- Card de aviso -->
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
      </ul>

      <p>
        Se ficou com alguma dúvida ou prefere conversar antes de tomar essa decisão,
        fique à vontade para nos procurar. Teremos satisfação em ajudar.
      </p>

      <!-- Formulário de confirmação -->
      <form method="POST" action="">
        <?= csrfField() ?>
        <div class="form-check mb-4">
          <input type="checkbox" name="confirmar" id="confirmar" class="form-check-input">
          <label for="confirmar" class="form-check-label">
            Sim, estou ciente e desejo encerrar a conta definitivamente.
          </label>
        </div>

        <div class="form-actions d-flex flex-wrap gap-2 justify-content-center">
          <a href="perfilprivado.php" class="btn btn-outline-secondary">
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

<?php include '../includes/footer.php'; ?>
