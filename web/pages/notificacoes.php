<?php
// ============================================================
// pages/notificacoes.php — todos os avisos da ONG logada
// ============================================================
//
// POST acao=abrir (com id): marca o aviso como lido e leva ao link dele
// POST acao=ler_todas: marca todos como lidos
// os itens do sino no topo do site enviam para cá
// ============================================================

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/modules/notificacoes.php';

exigirLoginOng();
csrfExigir();

$id = (int) $_SESSION['user_id'];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $acao = (string) ($_POST['acao'] ?? '');

    if ($acao === 'abrir') {
        $stmt = $pdo->prepare('SELECT link FROM notificacoes WHERE id = ? AND usuario_id = ?');
        $stmt->execute([(int) ($_POST['id'] ?? 0), $id]);
        $link = $stmt->fetchColumn();

        if ($link !== false) {
            marcarNotificacoesLidas($pdo, $id, (int) $_POST['id']);
            // o link foi conferido ao gravar e é conferido de novo aqui
            if ($link !== null && notificacaoLinkValido((string) $link)) {
                header('Location: ' . url((string) $link));
                exit;
            }
        }
    } elseif ($acao === 'ler_todas') {
        marcarNotificacoesLidas($pdo, $id);
        flashSet('notificacoes_ok', 'Todos os avisos foram marcados como lidos.');
    }

    header('Location: notificacoes.php');
    exit;
}

$aviso        = flashGet('notificacoes_ok');
$notificacoes = notificacoesDaOng($pdo, $id, 100);
$naoLidas     = count(array_filter($notificacoes, static fn (array $n): bool => $n['lida_em'] === null));

$page_title = 'Notificações';
$active_nav = '';
include __DIR__ . '/../includes/layout/header.php';
?>

<?php if ($aviso !== ''): ?>
<div class="app-toast" role="status" aria-live="polite">
  <div class="alert alert-success alert-dismissible d-flex align-items-center gap-2" role="alert">
    <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
    <span><?= e($aviso) ?></span>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
  </div>
</div>
<?php endif; ?>

<section class="notif-page">
  <div class="container">
    <header class="notif-head">
      <div>
        <span class="gst-eyebrow">Painel da <?= e($_SESSION['user_nome'] ?? '') ?></span>
        <h1 class="notif-titulo">Notificações</h1>
        <p class="notif-sub">
          Avisos da Equipe ONGHelp sobre o seu cadastro, as suas notícias e as novidades da plataforma.
        </p>
      </div>

      <?php if ($naoLidas > 0): ?>
        <form method="POST" action="notificacoes.php">
          <?= csrfField() ?>
          <input type="hidden" name="acao" value="ler_todas">
          <button type="submit" class="btn btn-outline-secondary">
            <i class="bi bi-check2-all" aria-hidden="true"></i> Marcar todas como lidas
          </button>
        </form>
      <?php endif; ?>
    </header>

    <div class="notif-painel glass-1">
      <?php if (!$notificacoes): ?>
        <div class="notif-vazio">
          <span class="gst-empty-icon" aria-hidden="true"><i class="bi bi-bell"></i></span>
          <p>Nenhuma notificação por enquanto.</p>
        </div>
      <?php else: ?>
        <ul class="notif-lista">
          <?php foreach ($notificacoes as $n):
            $lida = $n['lida_em'] !== null;
          ?>
          <li class="notif-item<?= $lida ? '' : ' is-nova' ?>">
            <span class="notif-icone" aria-hidden="true"><i class="bi <?= notificacaoIcone((string) $n['tipo']) ?>"></i></span>
            <div class="notif-corpo">
              <p class="notif-item-titulo">
                <?= e($n['titulo']) ?>
                <?php if (!$lida): ?><span class="notif-nova">nova</span><?php endif; ?>
              </p>
              <?php if ($n['mensagem'] !== ''): ?>
                <p class="notif-item-texto"><?= e($n['mensagem']) ?></p>
              <?php endif; ?>
              <time class="notif-item-data" datetime="<?= e(date('c', strtotime($n['criado_em']))) ?>"
                    title="<?= e(fmtDataHora($n['criado_em'])) ?>"><?= e(notificacaoQuando($n['criado_em'])) ?></time>
            </div>
            <?php if ($n['link'] !== null || !$lida): ?>
              <form method="POST" action="notificacoes.php" class="notif-acao">
                <?= csrfField() ?>
                <input type="hidden" name="acao" value="abrir">
                <input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
                <button type="submit" class="btn btn-sm <?= $n['link'] !== null ? 'btn-outline-secondary' : 'btn-link' ?>">
                  <?= $n['link'] !== null ? 'Abrir' : 'Marcar como lida' ?>
                </button>
              </form>
            <?php endif; ?>
          </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php include __DIR__ . '/../includes/layout/footer.php'; ?>
