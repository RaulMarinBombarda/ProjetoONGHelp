<?php
// ============================================================
// admin/avisos.php — avisos escritos pela equipe para as ONGs
// ============================================================
//
// só o administrador envia (permissão avisos.enviar), o aviso vai para
// uma ONG ou para todas as aprovadas e chega como notificação no sino
// do site e em pages/notificacoes.php, cada envio fica registrado em
// avisos_equipe e nos logs, com quantas ONGs já leram
//
// POST acao=enviar: destino (todas|ong), ong_id, titulo, mensagem, link
// GET ?ong=N: abre o formulário com essa ONG escolhida (atalho da
// página de análise do cadastro)
// ============================================================

require_once __DIR__ . '/../includes/modules/admin-auth.php';
require_once __DIR__ . '/../includes/modules/notificacoes.php';

$admin = exigirPermissao($pdo, 'avisos.enviar');
csrfExigir();

$destinosLink = avisoDestinosLink();

// só ONGs aprovadas entram na lista, as outras não conseguem entrar no painel para ler
$ongsAprovadas = $pdo->query(
    "SELECT id, nome, cidade, estado FROM usuarios WHERE status = 'aprovada' ORDER BY nome ASC"
)->fetchAll();
$ongsPorId = array_column($ongsAprovadas, null, 'id');

$form = [
    'destino'  => 'todas',
    'ong_id'   => 0,
    'titulo'   => '',
    'mensagem' => '',
    'link'     => '',
];

$ongPedida = isset($_GET['ong']) && ctype_digit((string) $_GET['ong']) ? (int) $_GET['ong'] : 0;
if ($ongPedida > 0 && isset($ongsPorId[$ongPedida])) {
    $form['destino'] = 'ong';
    $form['ong_id']  = $ongPedida;
}

$erro = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['acao'] ?? '') === 'enviar') {
    $form = [
        'destino'  => ($_POST['destino'] ?? '') === 'ong' ? 'ong' : 'todas',
        'ong_id'   => isset($_POST['ong_id']) && ctype_digit((string) $_POST['ong_id']) ? (int) $_POST['ong_id'] : 0,
        'titulo'   => trim(preg_replace('/\s+/u', ' ', (string) ($_POST['titulo'] ?? '')) ?? ''),
        'mensagem' => trim(str_replace("\r\n", "\n", (string) ($_POST['mensagem'] ?? ''))),
        'link'     => (string) ($_POST['link'] ?? ''),
    ];

    $tamanhoTitulo   = mb_strlen($form['titulo'], 'UTF-8');
    $tamanhoMensagem = mb_strlen($form['mensagem'], 'UTF-8');

    if ($form['destino'] === 'ong' && !isset($ongsPorId[$form['ong_id']])) {
        $erro = 'Escolha uma ONG aprovada para receber o aviso.';
    } elseif ($tamanhoTitulo < AVISO_TITULO_MIN || $tamanhoTitulo > AVISO_TITULO_MAX) {
        $erro = 'O título precisa ter de ' . AVISO_TITULO_MIN . ' a ' . AVISO_TITULO_MAX . ' caracteres.';
    } elseif ($tamanhoMensagem < AVISO_MENSAGEM_MIN || $tamanhoMensagem > AVISO_MENSAGEM_MAX) {
        $erro = 'A mensagem precisa ter de ' . AVISO_MENSAGEM_MIN . ' a ' . AVISO_MENSAGEM_MAX . ' caracteres.';
    } elseif (!array_key_exists($form['link'], $destinosLink)) {
        $erro = 'Escolha um destino da lista para o botão do aviso.';
    }

    if ($erro === '') {
        $usuarioId = $form['destino'] === 'ong' ? $form['ong_id'] : null;
        $total = enviarAvisoEquipe(
            $pdo,
            (int) $admin['id'],
            $usuarioId,
            $form['titulo'],
            $form['mensagem'],
            $form['link'] !== '' ? $form['link'] : null
        );

        if ($total === 0) {
            $erro = 'Nenhuma ONG aprovada recebeu o aviso. Confira o destino e tente de novo.';
        } else {
            $paraQuem = $usuarioId === null
                ? 'todas as ONGs aprovadas (' . $total . ')'
                : (string) $ongsPorId[$usuarioId]['nome'];

            registrarLogAdmin(
                $pdo,
                'aviso_enviado',
                $usuarioId === null ? 'aviso' : 'usuario',
                $usuarioId,
                $usuarioId === null ? 'Todas as ONGs aprovadas' : (string) $ongsPorId[$usuarioId]['nome'],
                'Título: ' . $form['titulo'] . '. Destinatários: ' . $total . '.'
            );

            flashSet('admin_sucesso', 'Aviso enviado para ' . $paraQuem . '. Ele já aparece no sino do site.');
            header('Location: avisos.php');
            exit;
        }
    }
}

$enviados = avisosEnviados($pdo);
$totalAprovadas = count($ongsAprovadas);
$textoConfirmar = 'O aviso vai para todas as ' . $totalAprovadas . ' ONGs aprovadas e não pode ser desfeito.';

$admin_page_title    = 'Avisos às ONGs';
$admin_page_subtitle = 'Escreva um aviso para uma ONG ou para todas, ele chega pelo sino de notificações do site';
$admin_active        = 'avisos';
include __DIR__ . '/../includes/layout/admin-header.php';
?>

<?php if ($erro !== ''): ?>
  <div class="alert alert-danger" role="alert">
    <i class="bi bi-exclamation-circle" aria-hidden="true"></i> <?= htmlspecialchars($erro) ?>
  </div>
<?php endif; ?>

<div class="admin-avisos-grid">

  <!-- ── novo aviso ── -->
  <section class="admin-card" aria-labelledby="avisoNovoTitulo">
    <div class="admin-card-header">
      <div>
        <h2 class="admin-card-title" id="avisoNovoTitulo">Novo aviso</h2>
        <p class="admin-card-subtitle">Só ONGs aprovadas recebem, as outras não conseguem entrar no painel para ler.</p>
      </div>
    </div>

    <form method="POST" action="avisos.php" class="admin-avisos-form" data-aviso-form>
      <?= csrfField() ?>
      <input type="hidden" name="acao" value="enviar">

      <fieldset class="mb-3">
        <legend class="admin-label">Para quem</legend>
        <div class="admin-avisos-destinos">
          <label class="admin-avisos-destino">
            <input type="radio" name="destino" value="todas" <?= $form['destino'] === 'todas' ? 'checked' : '' ?> data-aviso-destino>
            <span class="admin-avisos-destino-icone" aria-hidden="true"><i class="bi bi-broadcast"></i></span>
            <span>
              <strong>Todas as ONGs aprovadas</strong>
              <small><?= $totalAprovadas ?> <?= $totalAprovadas === 1 ? 'organização' : 'organizações' ?> hoje</small>
            </span>
          </label>
          <label class="admin-avisos-destino">
            <input type="radio" name="destino" value="ong" <?= $form['destino'] === 'ong' ? 'checked' : '' ?> data-aviso-destino>
            <span class="admin-avisos-destino-icone" aria-hidden="true"><i class="bi bi-building"></i></span>
            <span>
              <strong>Uma ONG</strong>
              <small>Escolha na lista abaixo</small>
            </span>
          </label>
        </div>
      </fieldset>

      <div class="mb-3" data-aviso-ong-campo<?= $form['destino'] === 'ong' ? '' : ' hidden' ?>>
        <label class="admin-label" for="avisoOng">ONG</label>
        <select class="form-select" name="ong_id" id="avisoOng">
          <option value="">Escolha uma ONG aprovada</option>
          <?php foreach ($ongsAprovadas as $ong): ?>
            <option value="<?= (int) $ong['id'] ?>" <?= (int) $ong['id'] === $form['ong_id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($ong['nome']) ?><?= $ong['cidade'] ? ' · ' . htmlspecialchars($ong['cidade']) . ($ong['estado'] ? '/' . htmlspecialchars($ong['estado']) : '') : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="mb-3">
        <label class="admin-label" for="avisoTitulo">Título</label>
        <input type="text" class="form-control" name="titulo" id="avisoTitulo"
               value="<?= htmlspecialchars($form['titulo']) ?>"
               minlength="<?= AVISO_TITULO_MIN ?>" maxlength="<?= AVISO_TITULO_MAX ?>" required
               placeholder="Ex.: Atualize os dados do seu perfil" data-aviso-contar>
        <small class="admin-avisos-contador" data-aviso-contador-de="avisoTitulo"><?= mb_strlen($form['titulo'], 'UTF-8') ?>/<?= AVISO_TITULO_MAX ?></small>
      </div>

      <div class="mb-3">
        <label class="admin-label" for="avisoMensagem">Mensagem</label>
        <textarea class="form-control" name="mensagem" id="avisoMensagem" rows="5"
                  minlength="<?= AVISO_MENSAGEM_MIN ?>" maxlength="<?= AVISO_MENSAGEM_MAX ?>" required
                  placeholder="Explique o que a ONG precisa saber ou fazer." data-aviso-contar><?= htmlspecialchars($form['mensagem']) ?></textarea>
        <small class="admin-avisos-contador" data-aviso-contador-de="avisoMensagem"><?= mb_strlen($form['mensagem'], 'UTF-8') ?>/<?= AVISO_MENSAGEM_MAX ?></small>
      </div>

      <div class="mb-4">
        <label class="admin-label" for="avisoLink">Botão "Abrir" leva para</label>
        <select class="form-select" name="link" id="avisoLink">
          <?php foreach ($destinosLink as $valor => $rotulo): ?>
            <option value="<?= htmlspecialchars($valor) ?>" <?= $valor === $form['link'] ? 'selected' : '' ?>><?= htmlspecialchars($rotulo) ?></option>
          <?php endforeach; ?>
        </select>
        <p class="admin-dica admin-dica-esquerda mb-0">Só páginas do próprio ONGHelp, para nenhum aviso levar a um site de fora.</p>
      </div>

      <button type="submit" class="btn btn-primary"
              data-aviso-enviar
              data-confirmar-todas="<?= htmlspecialchars($textoConfirmar) ?>"
              <?= $form['destino'] === 'todas' ? 'data-confirmar="' . htmlspecialchars($textoConfirmar) . '" data-confirmar-titulo="Enviar para todas as ONGs?" data-confirmar-botao="Enviar aviso"' : '' ?>>
        <i class="bi bi-send" aria-hidden="true"></i> Enviar aviso
      </button>
    </form>
  </section>

  <!-- ── prévia ── -->
  <aside class="admin-card admin-avisos-previa" aria-labelledby="avisoPreviaTitulo">
    <h2 class="admin-card-title" id="avisoPreviaTitulo">Como a ONG vai ver</h2>
    <p class="admin-card-subtitle">Assim o aviso aparece no sino e em Notificações.</p>

    <div class="admin-avisos-item" aria-hidden="true">
      <span class="admin-avisos-item-icone"><i class="bi bi-megaphone"></i></span>
      <div class="admin-avisos-item-corpo">
        <p class="admin-avisos-item-titulo">
          <span data-aviso-previa="titulo"><?= $form['titulo'] !== '' ? htmlspecialchars($form['titulo']) : 'Título do aviso' ?></span>
          <span class="admin-avisos-item-nova">nova</span>
        </p>
        <p class="admin-avisos-item-texto" data-aviso-previa="mensagem"><?= $form['mensagem'] !== '' ? htmlspecialchars($form['mensagem']) : 'A mensagem aparece aqui.' ?></p>
        <span class="admin-avisos-item-data">agora</span>
      </div>
      <span class="admin-avisos-item-botao" data-aviso-previa="botao"<?= $form['link'] === '' ? ' hidden' : '' ?>>Abrir</span>
    </div>

    <ul class="admin-avisos-regras">
      <li><i class="bi bi-bell" aria-hidden="true"></i> O sino do site mostra a contagem de avisos novos até a ONG abrir.</li>
      <li><i class="bi bi-journal-text" aria-hidden="true"></i> Cada envio fica nos Logs, com autor, data e destino.</li>
      <li><i class="bi bi-arrow-repeat" aria-hidden="true"></i> Os avisos automáticos (aprovação, suspensão, moderação) continuam saindo sozinhos.</li>
    </ul>
  </aside>
</div>

<!-- ── histórico ── -->
<section class="admin-card" aria-labelledby="avisosEnviadosTitulo">
  <div class="admin-card-header">
    <div>
      <h2 class="admin-card-title" id="avisosEnviadosTitulo">Avisos enviados</h2>
      <p class="admin-card-subtitle">Os 30 envios mais recentes e quantas ONGs já leram cada um.</p>
    </div>
  </div>

  <?php if (!$enviados): ?>
    <div class="admin-vazio admin-vazio-sm">
      <i class="bi bi-megaphone" aria-hidden="true"></i>
      <strong>Nenhum aviso enviado ainda</strong>
      <span>Os envios aparecem aqui assim que você mandar o primeiro.</span>
    </div>
  <?php else: ?>
    <ul class="admin-avisos-lista">
      <?php foreach ($enviados as $aviso):
        $total = (int) $aviso['destinatarios'];
        $lidas = (int) $aviso['lidas'];
        $pct   = $total > 0 ? (int) round($lidas / $total * 100) : 0;
        $para  = $aviso['destino'] === 'todas'
            ? 'Todas as ONGs aprovadas'
            : ($aviso['ong_nome'] !== null ? (string) $aviso['ong_nome'] : 'ONG excluída');
      ?>
        <li class="admin-avisos-enviado">
          <div class="admin-avisos-enviado-texto">
            <strong><?= htmlspecialchars($aviso['titulo']) ?></strong>
            <p><?= htmlspecialchars($aviso['mensagem']) ?></p>
            <span class="admin-avisos-enviado-meta">
              <span><i class="bi <?= $aviso['destino'] === 'todas' ? 'bi-broadcast' : 'bi-building' ?>" aria-hidden="true"></i> <?= htmlspecialchars($para) ?></span>
              <span><i class="bi bi-person" aria-hidden="true"></i> <?= htmlspecialchars($aviso['admin_nome'] ?? 'Equipe ONGHelp') ?></span>
              <time datetime="<?= htmlspecialchars(date('c', strtotime((string) $aviso['criado_em']))) ?>"><i class="bi bi-calendar3" aria-hidden="true"></i> <?= fmtDataHora($aviso['criado_em']) ?></time>
              <?php if ($aviso['link'] !== null): ?>
                <span><i class="bi bi-link-45deg" aria-hidden="true"></i> <?= htmlspecialchars($destinosLink[$aviso['link']] ?? $aviso['link']) ?></span>
              <?php endif; ?>
            </span>
          </div>
          <div class="admin-avisos-leitura">
            <span><strong><?= $lidas ?></strong> de <?= $total ?> <?= $lidas === 1 ? 'leu' : 'leram' ?></span>
            <span class="admin-avisos-barra" role="img" aria-label="<?= $pct ?>% das ONGs leram"><span style="--lido:<?= $pct ?>%;"></span></span>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<?php
$page_scripts = ['admin/avisos.js'];
include __DIR__ . '/../includes/layout/admin-footer.php';
?>
