<?php
// ============================================================
// pages/noticias.php — Minhas notícias (ONG logada)
// ============================================================
// Lista, publica, despublica e exclui as notícias da própria ONG.
// Notícias ocultas pela equipe ficam somente para leitura.

require_once '../includes/db.php';
require_once '../includes/helpers.php';
require_once '../includes/session.php';
require_once '../includes/csrf.php';
require_once '../includes/noticias.php';

exigirLoginOng();
csrfExigir();

$ongId = (int) ($_SESSION['user_id'] ?? 0);
$metodo = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');

// ── Ações POST ────────────────────────────────────────────
if ($metodo === 'POST') {
    $acao = trim((string) ($_POST['acao'] ?? ''));
    $noticiaId = (int) ($_POST['noticia_id'] ?? 0);

    if ($acao === 'publicar') {
        $resultado = publicarNoticiaDaOng($pdo, $noticiaId, $ongId);

        if ($resultado['ok']) {
            flashSet('success', 'Notícia publicada com sucesso.');
            flashSet('noticia_publicada', (string) $noticiaId);
        } else {
            flashSet('error', $resultado['erro']);
        }
    } elseif ($acao === 'despublicar') {
        $resultado = despublicarNoticiaDaOng($pdo, $noticiaId, $ongId);

        if ($resultado['ok']) {
            flashSet('success', 'Notícia despublicada. Ela voltou para os rascunhos.');
        } else {
            flashSet('error', $resultado['erro']);
        }
    } elseif ($acao === 'excluir') {
        $resultado = excluirNoticiaDaOng($pdo, $noticiaId, $ongId);

        if ($resultado['ok']) {
            flashSet('success', 'Notícia excluída.');
        } else {
            flashSet('error', $resultado['erro']);
        }
    } else {
        flashSet('error', 'Ação inválida.');
    }

    header('Location: noticias.php');
    exit;
}

// ── Mensagens ─────────────────────────────────────────────
$mensagem = flashGet('success');
$tipoMensagem = 'success';

if ($mensagem === '') {
    $erroFlash = flashGet('error');

    if ($erroFlash !== '') {
        $mensagem = $erroFlash;
        $tipoMensagem = 'danger';
    }
}

if ($mensagem === '' && isset($_GET['publicada'])) {
    $mensagem = 'Notícia publicada com sucesso.';
} elseif ($mensagem === '' && isset($_GET['excluida'])) {
    $mensagem = 'Notícia excluída.';
} elseif ($mensagem === '' && isset($_GET['nao_encontrada'])) {
    $mensagem = 'A notícia não foi encontrada ou não pertence à sua ONG.';
    $tipoMensagem = 'warning';
}

// ── Elegibilidade e bloqueio ──────────────────────────────
$elegibilidade = ongPodePublicarNoticias($pdo, $ongId);
$bloqueioAtivo = bloqueioPublicacaoNoticias($pdo, $ongId);
$podePublicar = (bool) $elegibilidade['pode'];

// ── Consulta ──────────────────────────────────────────────
$stmt = $pdo->prepare(
    'SELECT n.*,
            (SELECT COUNT(*) FROM noticias_fotos f WHERE f.noticia_id = n.id) AS total_fotos,
            (SELECT f.arquivo FROM noticias_fotos f
              WHERE f.noticia_id = n.id ORDER BY f.ordem, f.id LIMIT 1) AS capa_miniatura
     FROM noticias n
     WHERE n.usuario_id = ?
     ORDER BY CASE n.estado WHEN \'publicada\' THEN 1 WHEN \'rascunho\' THEN 2 ELSE 3 END,
              COALESCE(n.publicada_em, n.atualizado_em) DESC,
              n.id DESC'
);
$stmt->execute([$ongId]);
$noticias = $stmt->fetchAll();

$stmtContagens = $pdo->prepare(
    'SELECT estado, COUNT(*) AS total FROM noticias WHERE usuario_id = ? GROUP BY estado'
);
$stmtContagens->execute([$ongId]);
$contagens = ['rascunho' => 0, 'publicada' => 0, 'oculta' => 0];

foreach ($stmtContagens->fetchAll() as $linha) {
    $contagens[(string) $linha['estado']] = (int) $linha['total'];
}

$totalGeral = array_sum($contagens);
$temNoticiaPublica = file_exists(__DIR__ . '/noticia.php');
$canalEquipe = 'mailto:equipe.onghelp@gmail.com?subject='
    . rawurlencode('Notícia ocultada — ONGHelp');

$page_title = 'Minhas notícias';
$active_nav = '';
$page_robots = 'noindex, nofollow';
$page_script = 'noticias-lista.js';
include '../includes/header.php';
?>

<section class="gestao-page gst-page nmg-page">
  <div class="container">

    <header class="gst-head nmg-head">
      <div class="gst-head-copy">
        <span class="gst-eyebrow">Notícias da ONG</span>
        <h1 class="gst-title">Minhas notícias</h1>
        <p class="gst-sub">Divulgue as ações e projetos da sua ONG.</p>
      </div>
      <a class="btn btn-primary nmg-head-primary" href="noticia-editar.php">
        <i class="bi bi-plus-lg" aria-hidden="true"></i> Publicar notícia
      </a>
    </header>

    <?php if ($mensagem !== ''): ?>
      <div class="app-toast" role="<?= $tipoMensagem === 'success' ? 'status' : 'alert' ?>" aria-live="<?= $tipoMensagem === 'success' ? 'polite' : 'assertive' ?>">
        <div class="alert alert-<?= htmlspecialchars($tipoMensagem, ENT_QUOTES, 'UTF-8') ?> alert-dismissible d-flex align-items-center gap-2" role="alert">
          <i class="bi <?= $tipoMensagem === 'success' ? 'bi-check-circle-fill' : ($tipoMensagem === 'warning' ? 'bi-exclamation-triangle-fill' : 'bi-x-circle-fill') ?>" aria-hidden="true"></i>
          <span><?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?></span>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
      </div>
    <?php endif; ?>

    <?php if (!$podePublicar): ?>
      <div class="nmg-bloqueio" role="status">
        <i class="bi bi-lock-fill" aria-hidden="true"></i>
        <div>
          <strong><?= $bloqueioAtivo ? 'Publicação bloqueada pela equipe' : 'Publicação indisponível' ?></strong>
          <p><?= htmlspecialchars((string) $elegibilidade['motivo'], ENT_QUOTES, 'UTF-8') ?></p>
        </div>
      </div>
    <?php endif; ?>

    <div class="gst-kpi-band nmg-kpi-band glass-1" aria-label="Resumo das notícias">
      <div class="gst-kpi">
        <span class="gst-kpi-label">Publicadas</span>
        <strong class="gst-kpi-value nmg-kpi-value is-publicada"><?= (int) $contagens['publicada'] ?></strong>
      </div>
      <div class="gst-kpi">
        <span class="gst-kpi-label">Rascunhos</span>
        <strong class="gst-kpi-value nmg-kpi-value is-rascunho"><?= (int) $contagens['rascunho'] ?></strong>
      </div>
      <div class="gst-kpi">
        <span class="gst-kpi-label">Ocultas pela equipe</span>
        <strong class="gst-kpi-value nmg-kpi-value is-oculta"><?= (int) $contagens['oculta'] ?></strong>
      </div>
    </div>

    <?php if (!$noticias): ?>
      <section class="nmg-vazio glass-1" aria-labelledby="nmgVazioTitulo">
        <span class="nmg-vazio-icon" aria-hidden="true"><i class="bi bi-newspaper"></i></span>
        <h2 id="nmgVazioTitulo">Conte o que sua ONG está fazendo</h2>
        <p>
          Publique ações, projetos e conquistas da sua organização. Você pode
          salvar como rascunho e publicar quando estiver pronto.
        </p>
        <a class="btn btn-primary" href="noticia-editar.php">
          <i class="bi bi-plus-lg" aria-hidden="true"></i> Publicar notícia
        </a>
      </section>
    <?php else: ?>
      <section class="nmg-panel glass-1" aria-labelledby="nmgListaTitulo">
        <div class="nmg-panel-head">
          <h2 class="nmg-panel-title" id="nmgListaTitulo">Todas as notícias</h2>
          <span class="nmg-panel-count"><?= (int) $totalGeral ?> <?= $totalGeral === 1 ? 'notícia' : 'notícias' ?></span>
        </div>

        <div class="nmg-table-wrap">
          <table class="nmg-table">
            <caption class="visually-hidden">Notícias da ONG</caption>
            <thead>
              <tr>
                <th scope="col" class="nmg-th-noticia">Notícia</th>
                <th scope="col">Estado</th>
                <th scope="col">Data</th>
                <th scope="col">Fotos</th>
                <th scope="col"><span class="visually-hidden">Ações</span></th>
              </tr>
            </thead>

            <?php foreach ($noticias as $noticia): ?>
              <?php
              $id = (int) $noticia['id'];
              $estadoNoticia = (string) $noticia['estado'];
              $tituloNoticia = trim((string) $noticia['titulo']);
              $tituloExibicao = $tituloNoticia !== '' ? $tituloNoticia : 'Rascunho sem título';
              $tipoNoticia = trim((string) $noticia['tipo']);
              $capa = noticiaFotoUrl((string) ($noticia['capa_miniatura'] ?? ''), '../');
              $totalFotos = (int) ($noticia['total_fotos'] ?? 0);
              $dataBase = $estadoNoticia === 'publicada' && !empty($noticia['publicada_em'])
                  ? (string) $noticia['publicada_em']
                  : (string) $noticia['atualizado_em'];
              $podeEditar = noticiaPodeSerEditada($noticia);
              $podeExcluir = noticiaPodeSerExcluida($noticia);
              $podeDespublicar = noticiaPodeSerDespublicada($noticia);
              $podeRepublicar = noticiaPodeSerPublicada($noticia);
              $linkVer = 'noticia-editar.php?id=' . $id;
              $abrirNovaAba = false;

              if ($estadoNoticia === 'publicada' && $temNoticiaPublica) {
                  $slugNoticia = trim((string) ($noticia['slug'] ?? ''));
                  $linkVer = 'noticia.php?id=' . $id
                      . ($slugNoticia !== '' ? '&slug=' . rawurlencode($slugNoticia) : '');
                  $abrirNovaAba = true;
              }

              $motivoOcultacao = trim((string) ($noticia['ocultacao_mensagem'] ?? ''));

              if ($motivoOcultacao === '') {
                  $motivoOcultacao = 'Motivo não informado pela equipe.';
              } elseif (!preg_match('/[.!?]$/u', $motivoOcultacao)) {
                  $motivoOcultacao .= '.';
              }
              ?>
              <tbody class="nmg-group">
                <tr class="nmg-row<?= $estadoNoticia === 'oculta' ? ' is-oculta' : '' ?>">
                  <td class="nmg-cell-noticia">
                    <div class="nmg-news">
                      <a class="nmg-thumb" href="<?= htmlspecialchars($linkVer, ENT_QUOTES, 'UTF-8') ?>"<?= $abrirNovaAba ? ' target="_blank" rel="noopener"' : '' ?> tabindex="-1" aria-hidden="true">
                        <?php if ($capa !== ''): ?>
                          <img src="<?= htmlspecialchars($capa, ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy">
                        <?php else: ?>
                          <span class="nmg-thumb-vazia"><i class="bi bi-image"></i></span>
                        <?php endif; ?>
                        <span class="nmg-thumb-count" title="<?= $totalFotos ?> <?= $totalFotos === 1 ? 'foto' : 'fotos' ?>">
                          <i class="bi bi-camera" aria-hidden="true"></i><?= $totalFotos ?>
                        </span>
                      </a>

                      <div class="nmg-news-copy">
                        <a class="nmg-title" href="<?= htmlspecialchars($linkVer, ENT_QUOTES, 'UTF-8') ?>"<?= $abrirNovaAba ? ' target="_blank" rel="noopener"' : '' ?>>
                          <?= htmlspecialchars($tituloExibicao, ENT_QUOTES, 'UTF-8') ?>
                        </a>
                        <span class="nmg-type<?= $tipoNoticia === '' ? ' is-empty' : '' ?>">
                          <?= htmlspecialchars($tipoNoticia !== '' ? noticiaRotuloTipo($tipoNoticia) : 'Sem tipo', ENT_QUOTES, 'UTF-8') ?>
                        </span>
                      </div>
                    </div>
                  </td>

                  <td class="nmg-cell-estado">
                    <span class="nmg-badge is-<?= htmlspecialchars($estadoNoticia, ENT_QUOTES, 'UTF-8') ?>">
                      <?= htmlspecialchars(noticiaRotuloEstado($estadoNoticia), ENT_QUOTES, 'UTF-8') ?>
                    </span>
                  </td>

                  <td class="nmg-cell-data">
                    <span class="nmg-mobile-label">Data</span>
                    <?= htmlspecialchars(fmtData($dataBase), ENT_QUOTES, 'UTF-8') ?>
                  </td>

                  <td class="nmg-cell-fotos">
                    <span class="nmg-mobile-label">Fotos</span>
                    <strong><?= $totalFotos ?></strong>/<?= (int) ONGHELP_NOTICIAS_FOTOS_MAX ?>
                  </td>

                  <td class="nmg-cell-acoes">
                    <div class="dropdown nmg-menu">
                      <button class="nmg-menu-btn" type="button" data-bs-toggle="dropdown"
                              aria-expanded="false"
                              aria-label="Ações da notícia <?= htmlspecialchars($tituloExibicao, ENT_QUOTES, 'UTF-8') ?>">
                        <i class="bi bi-three-dots" aria-hidden="true"></i>
                      </button>
                      <ul class="dropdown-menu dropdown-menu-end nmg-menu-list">
                        <li>
                          <a class="dropdown-item" href="<?= htmlspecialchars($linkVer, ENT_QUOTES, 'UTF-8') ?>"<?= $abrirNovaAba ? ' target="_blank" rel="noopener"' : '' ?>>
                            <i class="bi bi-eye" aria-hidden="true"></i> Ver
                          </a>
                        </li>

                        <?php if ($podeEditar): ?>
                          <li>
                            <a class="dropdown-item" href="noticia-editar.php?id=<?= $id ?>">
                              <i class="bi bi-pencil" aria-hidden="true"></i> Editar
                            </a>
                          </li>
                        <?php endif; ?>

                        <?php if ($podeDespublicar): ?>
                          <li>
                            <form method="post" action="noticias.php">
                              <?= csrfField() ?>
                              <input type="hidden" name="acao" value="despublicar">
                              <input type="hidden" name="noticia_id" value="<?= $id ?>">
                              <button type="submit" class="dropdown-item is-warning">
                                <i class="bi bi-eye-slash" aria-hidden="true"></i> Despublicar
                              </button>
                            </form>
                          </li>
                        <?php elseif ($podeRepublicar): ?>
                          <li>
                            <form method="post" action="noticias.php">
                              <?= csrfField() ?>
                              <input type="hidden" name="acao" value="publicar">
                              <input type="hidden" name="noticia_id" value="<?= $id ?>">
                              <button type="submit" class="dropdown-item is-success"<?= $podePublicar ? '' : ' disabled aria-disabled="true" title="Publicação bloqueada"' ?>>
                                <i class="bi bi-send" aria-hidden="true"></i> Publicar
                              </button>
                            </form>
                          </li>
                        <?php endif; ?>

                        <?php if ($podeExcluir): ?>
                          <li>
                            <button type="button" class="dropdown-item is-danger"
                                    data-nmg-excluir
                                    data-id="<?= $id ?>"
                                    data-titulo="<?= htmlspecialchars($tituloExibicao, ENT_QUOTES, 'UTF-8') ?>">
                              <i class="bi bi-trash3" aria-hidden="true"></i> Excluir
                            </button>
                          </li>
                        <?php endif; ?>
                      </ul>
                    </div>
                  </td>
                </tr>

                <?php if ($estadoNoticia === 'oculta'): ?>
                  <tr class="nmg-note-row">
                    <td colspan="5">
                      <div class="nmg-note">
                        <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                        <p>
                          Ocultada pela equipe em <strong><?= htmlspecialchars(fmtDataHora((string) ($noticia['ocultada_em'] ?? '')), ENT_QUOTES, 'UTF-8') ?></strong>.
                          Motivo: <?= htmlspecialchars($motivoOcultacao, ENT_QUOTES, 'UTF-8') ?>
                          Se acredita que foi um engano, fale com a equipe.
                        </p>
                        <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars($canalEquipe, ENT_QUOTES, 'UTF-8') ?>">
                          Fale conosco
                        </a>
                      </div>
                    </td>
                  </tr>
                <?php endif; ?>
              </tbody>
            <?php endforeach; ?>
          </table>
        </div>
      </section>
    <?php endif; ?>

  </div>
</section>

<div class="modal fade" id="nmgModalExcluir" tabindex="-1" aria-labelledby="nmgModalExcluirTitulo" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content nmg-modal">
      <form method="post" action="noticias.php" id="nmgFormExcluir">
        <?= csrfField() ?>
        <input type="hidden" name="acao" value="excluir">
        <input type="hidden" name="noticia_id" id="nmgExcluirId" value="">

        <div class="modal-header border-0">
          <h5 class="modal-title" id="nmgModalExcluirTitulo">Excluir notícia?</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body pt-0">
          <p class="nmg-modal-text" id="nmgExcluirTexto">Essa notícia será excluída permanentemente.</p>
          <p class="nmg-modal-warning">Essa ação não pode ser desfeita. As fotos também serão removidas.</p>
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-danger btn-sm rounded-pill px-3">Excluir</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
