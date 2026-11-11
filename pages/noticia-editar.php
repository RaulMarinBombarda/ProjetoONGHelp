<?php
// ============================================================
// pages/noticia-editar.php — Criar/editar notícia da ONG
// ============================================================
// Um único formulário (POST multipart) salva o texto, os links e
// as fotos. A autoria, as permissões, o CSRF e a validação ficam
// no servidor; o JavaScript só melhora a experiência (editor de
// texto, prévia das fotos escolhidas e checklist).

require_once '../includes/db.php';
require_once '../includes/helpers.php';
require_once '../includes/session.php';
require_once '../includes/csrf.php';
require_once '../includes/noticias.php';
require_once '../includes/noticias_ui.php';

exigirLoginOng();

$ongId = (int) $_SESSION['user_id'];
$metodo = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');

// Quando o envio passa do post_max_size, o PHP descarta o POST
// inteiro (inclusive o token CSRF). Avisa em vez de falhar.
if ($metodo === 'POST' && $_POST === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    $voltar = (int) ($_GET['id'] ?? 0);
    header('Location: noticia-editar.php?' . ($voltar > 0 ? 'id=' . $voltar . '&' : '') . 'envio_grande=1');
    exit;
}

csrfExigir();

$acao = $metodo === 'POST' ? trim((string) ($_POST['acao'] ?? '')) : '';
$noticiaId = (int) ($metodo === 'POST' ? ($_POST['noticia_id'] ?? 0) : ($_GET['id'] ?? 0));
$noticia = $noticiaId > 0 ? buscarNoticiaDaOng($pdo, $noticiaId, $ongId) : null;

if ($noticiaId > 0 && !$noticia) {
    header('Location: noticias.php?nao_encontrada=1');
    exit;
}

$somenteLeitura = $noticia !== null && (string) $noticia['estado'] === 'oculta';
$publicada = $noticia !== null && (string) $noticia['estado'] === 'publicada';

$stmtProjetos = $pdo->prepare('SELECT id, nome FROM projetos WHERE usuario_id = ? ORDER BY nome ASC');
$stmtProjetos->execute([$ongId]);
$projetos = $stmtProjetos->fetchAll();

$stmtMovimentacoes = $pdo->prepare(
    'SELECT id, tipo, descricao, valor, data_mov FROM movimentacoes
     WHERE usuario_id = ? ORDER BY data_mov DESC, id DESC LIMIT 20'
);
$stmtMovimentacoes->execute([$ongId]);
$movimentacoes = $stmtMovimentacoes->fetchAll();

// ── Valores do formulário ───────────────────────────────────
$valores = [
    'titulo' => '',
    'resumo' => '',
    'texto' => '',
    'tipo' => '',
    'projeto_id' => '',
    'movimentacao_id' => '',
    'evento_data' => '',
    'evento_local' => '',
    'direitos_imagem' => false,
];
$linksValores = [''];

if ($noticia) {
    $valores = [
        'titulo' => (string) $noticia['titulo'],
        'resumo' => (string) $noticia['resumo'],
        'texto' => sanitizarHtmlNoticia((string) $noticia['texto']),
        'tipo' => (string) $noticia['tipo'],
        'projeto_id' => (string) ($noticia['projeto_id'] ?? ''),
        'movimentacao_id' => (string) ($noticia['movimentacao_id'] ?? ''),
        'evento_data' => $noticia['evento_data'] !== null
            ? str_replace(' ', 'T', substr((string) $noticia['evento_data'], 0, 16))
            : '',
        'evento_local' => (string) ($noticia['evento_local'] ?? ''),
        'direitos_imagem' => !empty($noticia['direitos_imagem_aceito_em']),
    ];

    $stmtLinks = $pdo->prepare('SELECT url FROM noticias_links WHERE noticia_id = ? ORDER BY ordem, id');
    $stmtLinks->execute([$noticiaId]);
    $linksValores = $stmtLinks->fetchAll(PDO::FETCH_COLUMN) ?: [''];
}

$fotos = noticiaFotosDaNoticia($pdo, $noticiaId);
$erros = [];
$mensagemSucesso = '';

if ($metodo === 'GET') {
    $pendencias = $_SESSION['ned_pendencias'][$noticiaId] ?? [];
    unset($_SESSION['ned_pendencias'][$noticiaId]);

    if ($pendencias !== []) {
        $mensagemSucesso = 'Salvamos como rascunho. Para publicar, falta resolver o que está marcado abaixo.';
        $erros = $pendencias;
    } elseif (isset($_GET['salvo'])) {
        $mensagemSucesso = $publicada ? 'Alterações salvas.' : 'Rascunho salvo.';
    } elseif (isset($_GET['despublicada'])) {
        $mensagemSucesso = 'Notícia despublicada. Ela voltou a ser um rascunho.';
    } elseif (isset($_GET['envio_grande'])) {
        $erros['noticia'] = 'As fotos escolhidas passam do limite de envio. Envie menos fotos por vez: '
            . 'salve o rascunho e adicione as demais em seguida.';
    }
}

// ── Ações ───────────────────────────────────────────────────
if ($metodo === 'POST' && $somenteLeitura) {
    $erros['noticia'] = 'Notícia oculta pela equipe não pode ser alterada.';
} elseif ($metodo === 'POST' && in_array($acao, ['despublicar', 'excluir'], true)) {
    $resultado = !$noticia
        ? ['ok' => false, 'erro' => 'Notícia não encontrada para esta ONG.']
        : ($acao === 'despublicar'
            ? despublicarNoticiaDaOng($pdo, $noticiaId, $ongId)
            : excluirNoticiaDaOng($pdo, $noticiaId, $ongId));

    if ($resultado['ok']) {
        header('Location: ' . ($acao === 'despublicar'
            ? 'noticia-editar.php?id=' . $noticiaId . '&despublicada=1'
            : 'noticias.php?excluida=1'));
        exit;
    }

    $erros['noticia'] = $resultado['erro'];
} elseif ($metodo === 'POST' && in_array($acao, ['salvar', 'publicar'], true)) {
    $linksEntrada = is_array($_POST['links'] ?? null) ? $_POST['links'] : [];
    $linksEntrada = array_values(array_map(
        static fn (mixed $url): string => trim(is_scalar($url) ? (string) $url : ''),
        $linksEntrada
    ));

    // Fotos: as novas já vão para o disco aqui; o banco só muda
    // junto com a notícia, mais abaixo.
    $plano = noticiaFotosPlanoDoFormulario(
        $fotos, $_POST, $_FILES, $ongId, !$publicada,
        $_SESSION['ned_pendentes'][$noticiaId] ?? []
    );
    unset($_SESSION['ned_pendentes'][$noticiaId]);

    $entrada = [
        'titulo' => trim((string) ($_POST['titulo'] ?? '')),
        'resumo' => trim((string) ($_POST['resumo'] ?? '')),
        'texto' => (string) ($_POST['texto'] ?? ''),
        'tipo' => trim((string) ($_POST['tipo'] ?? '')),
        'projeto_id' => trim((string) ($_POST['projeto_id'] ?? '')),
        'movimentacao_id' => trim((string) ($_POST['movimentacao_id'] ?? '')),
        'evento_data' => trim((string) ($_POST['evento_data'] ?? '')),
        'evento_local' => trim((string) ($_POST['evento_local'] ?? '')),
        'direitos_imagem' => !empty($_POST['direitos_imagem']),
        'links' => $linksEntrada,
        'fotos_resumo' => $plano['resumo'],
    ];

    $publicando = $acao === 'publicar' || $publicada;
    $resultado = validarNoticia($pdo, $ongId, $entrada, $publicando, $noticiaId > 0 ? $noticiaId : null);
    $pendenciasPublicacao = [];

    // Tentou publicar e faltou algo: se o conteúdo vale como
    // rascunho, salva assim mesmo (com as fotos enviadas) e mostra
    // o que falta, em vez de perder o envio.
    if (!$resultado['ok'] && $acao === 'publicar' && !$publicada && $plano['erros'] === []) {
        $comoRascunho = validarNoticia($pdo, $ongId, $entrada, false, $noticiaId > 0 ? $noticiaId : null);

        if ($comoRascunho['ok']) {
            $pendenciasPublicacao = $resultado['erros'];
            $resultado = $comoRascunho;
            $publicando = false;
            $acao = 'salvar';
        }
    }

    $erros = array_merge($resultado['erros'], $plano['erros']);

    if ($erros === []) {
        $dados = $resultado['dados'];
        $agora = date('Y-m-d H:i:s');

        try {
            $pdo->beginTransaction();

            // O slug acompanha o título até a primeira publicação;
            // depois fica fixo para não quebrar links compartilhados.
            $slug = (string) ($noticia['slug'] ?? '');

            if (!$publicada) {
                $slug = gerarSlugNoticia(
                    $pdo,
                    $dados['titulo'] !== '' ? $dados['titulo'] : 'Rascunho ' . date('Ymd His'),
                    $noticiaId > 0 ? $noticiaId : null
                );
            }

            $direitosEm = $noticia['direitos_imagem_aceito_em'] ?? null;

            if ($dados['direitos_imagem'] && empty($direitosEm)) {
                $direitosEm = $agora;
            }

            $estado = $publicando ? 'publicada' : (string) ($noticia['estado'] ?? 'rascunho');
            $publicadaEm = $noticia['publicada_em'] ?? null;

            if ($publicando && empty($publicadaEm)) {
                $publicadaEm = $agora;
            }

            $colunas = [
                $dados['titulo'], $dados['resumo'], $dados['texto'], $dados['tipo'], $estado, $slug,
                $dados['projeto_id'], $dados['movimentacao_id'], $dados['evento_data'],
                $dados['evento_local'] !== '' ? $dados['evento_local'] : null,
                $direitosEm, $agora, $publicadaEm,
            ];

            if (!$noticia) {
                $pdo->prepare(
                    'INSERT INTO noticias
                        (titulo, resumo, texto, tipo, estado, slug, projeto_id, movimentacao_id,
                         evento_data, evento_local, direitos_imagem_aceito_em, atualizado_em,
                         publicada_em, usuario_id, criado_em)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                )->execute(array_merge($colunas, [$ongId, $agora]));
                $noticiaId = (int) $pdo->lastInsertId();
            } else {
                $pdo->prepare(
                    'UPDATE noticias
                     SET titulo = ?, resumo = ?, texto = ?, tipo = ?, estado = ?, slug = ?,
                         projeto_id = ?, movimentacao_id = ?, evento_data = ?, evento_local = ?,
                         direitos_imagem_aceito_em = ?, atualizado_em = ?, publicada_em = ?
                     WHERE id = ? AND usuario_id = ?'
                )->execute(array_merge($colunas, [$noticiaId, $ongId]));
            }

            $pdo->prepare('DELETE FROM noticias_links WHERE noticia_id = ?')->execute([$noticiaId]);
            $inserirLink = $pdo->prepare(
                'INSERT INTO noticias_links (noticia_id, ordem, url, dominio, servico, criado_em)
                 VALUES (?, ?, ?, ?, ?, NOW())'
            );

            foreach ($dados['links'] as $indice => $link) {
                $inserirLink->execute([$noticiaId, $indice + 1, $link['url'], $link['dominio'], $link['servico']]);
            }

            noticiaFotosAplicarPlano($pdo, $noticiaId, $plano);
            $pdo->commit();
            noticiaFotosApagarRemovidas($plano);

            if ($acao === 'publicar') {
                flashSet('noticia_publicada', (string) $noticiaId);
                header('Location: noticia.php?id=' . $noticiaId . '&slug=' . rawurlencode($slug));
                exit;
            }

            if ($pendenciasPublicacao !== []) {
                $_SESSION['ned_pendencias'][$noticiaId] = $pendenciasPublicacao;
            }

            header('Location: noticia-editar.php?id=' . $noticiaId . '&salvo=1');
            exit;
        } catch (Throwable $erro) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('ONGHelp: erro ao salvar notícia: ' . $erro->getMessage());
            $erros['noticia'] = 'Não foi possível salvar a notícia. Tente novamente.';
        }
    }

    // Algo deu errado: as fotos novas não ficam órfãs no disco e o
    // formulário volta com o que foi digitado.
    // As fotos novas que chegaram bem ficam guardadas na sessão e
    // voltam ao formulário: a ONG não precisa escolhê-las de novo.
    foreach ($plano['novas'] as $nova) {
        $_SESSION['ned_pendentes'][$noticiaId][bin2hex(random_bytes(6))] = $nova;
    }

    $valores = array_merge($valores, array_intersect_key($entrada, $valores));
    $valores['texto'] = (string) ($resultado['dados']['texto'] ?? $entrada['texto']);
    $linksValores = $linksEntrada ?: [''];

    foreach ($fotos as &$foto) {
        $id = (int) $foto['id'];

        if (isset($plano['manter'][$id])) {
            $foto['texto_alternativo'] = $plano['manter'][$id]['alt'];
            $foto['legenda'] = $plano['manter'][$id]['legenda'];
        }

        $foto['marcada_remover'] = !isset($plano['manter'][$id]);
    }
    unset($foto);

} elseif ($metodo === 'POST') {
    $erros['noticia'] = 'Ação inválida.';
}

// ── Dados de apresentação ───────────────────────────────────
$elegibilidade = ongPodePublicarNoticias($pdo, $ongId);
$podePublicar = (bool) $elegibilidade['pode'];
$tipos = noticiasTipos();
$linksValores = array_slice(array_values($linksValores), 0, ONGHELP_NOTICIAS_LINKS_MAX) ?: [''];
$errosGerais = array_intersect_key($erros, array_flip(['noticia', 'ong', 'limite', 'vinculo', 'pix']));
$rotuloEstado = $noticia ? noticiaRotuloEstado((string) $noticia['estado']) : 'Novo rascunho';
$desabilitado = $somenteLeitura ? 'disabled' : '';

/** Classe is-invalid + mensagem de erro de um campo. */
function nedErro(array $erros, string $campo): string
{
    return isset($erros[$campo])
        ? '<p class="ned-erro"><i class="bi bi-exclamation-circle" aria-hidden="true"></i> '
            . noticiaEsc($erros[$campo]) . '</p>'
        : '';
}

$page_title = $noticia ? 'Editar notícia' : 'Nova notícia';
$active_nav = '';
$page_script = 'noticia-editor.js';
include '../includes/header.php';
?>

<section class="section ned-pagina">
  <div class="container">

    <header class="ned-head">
      <div>
        <a class="nt-voltar" href="noticias.php"><i class="bi bi-arrow-left" aria-hidden="true"></i> Minhas notícias</a>
        <h1 class="ned-titulo">
          <?= $noticia ? 'Editar notícia' : 'Nova notícia' ?>
          <span class="ned-estado is-<?= noticiaEsc((string) ($noticia['estado'] ?? 'rascunho')) ?>"><?= noticiaEsc($rotuloEstado) ?></span>
        </h1>
      </div>
      <?php if ($publicada): ?>
        <a class="btn btn-outline-secondary btn-sm" href="<?= noticiaEsc(noticiaUrlPublica($noticia)) ?>" target="_blank" rel="noopener">
          <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> Ver notícia publicada
        </a>
      <?php endif; ?>
    </header>

    <?php if ($mensagemSucesso !== ''): ?>
      <div class="alert alert-success" role="status">
        <i class="bi bi-check-circle-fill" aria-hidden="true"></i> <?= noticiaEsc($mensagemSucesso) ?>
      </div>
    <?php endif; ?>

    <?php if ($somenteLeitura): ?>
      <div class="alert alert-warning" role="alert">
        <i class="bi bi-eye-slash" aria-hidden="true"></i>
        <strong>Esta notícia foi oculta pela equipe.</strong>
        <?= noticiaEsc((string) ($noticia['ocultacao_mensagem'] ?: 'O conteúdo fica preservado e não pode ser editado nem excluído pela ONG.')) ?>
      </div>
    <?php elseif (!$podePublicar): ?>
      <div class="alert alert-warning" role="alert">
        <i class="bi bi-exclamation-triangle" aria-hidden="true"></i> <?= noticiaEsc($elegibilidade['motivo']) ?>
      </div>
    <?php endif; ?>

    <?php if ($errosGerais): ?>
      <div class="alert alert-danger" role="alert">
        <strong>Não foi possível concluir.</strong>
        <?php foreach (array_unique($errosGerais) as $mensagem): ?>
          <div><?= noticiaEsc($mensagem) ?></div>
        <?php endforeach; ?>
      </div>
    <?php elseif ($erros !== []): ?>
      <div class="alert alert-danger" role="alert">
        <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
        Revise os campos marcados abaixo.
      </div>
    <?php endif; ?>

    <form method="post" action="noticia-editar.php<?= $noticiaId > 0 ? '?id=' . $noticiaId : '' ?>"
          enctype="multipart/form-data" id="nedForm" class="ned-layout" novalidate
          data-alt-min="<?= ONGHELP_NOTICIAS_FOTO_ALT_MIN ?>"
          data-fotos-max="<?= ONGHELP_NOTICIAS_FOTOS_MAX ?>"
          data-titulo-min="<?= ONGHELP_NOTICIAS_TITULO_MIN ?>"
          data-resumo-min="<?= ONGHELP_NOTICIAS_RESUMO_MIN ?>"
          data-texto-min="<?= ONGHELP_NOTICIAS_TEXTO_MIN ?>">
      <?= csrfField() ?>
      <input type="hidden" name="noticia_id" value="<?= (int) $noticiaId ?>">

      <div class="ned-principal">

        <!-- 1. Conteúdo -->
        <fieldset class="glass-1 ned-painel" <?= $desabilitado ?>>
          <legend class="ned-painel-titulo"><span>1</span> Conteúdo</legend>

          <div class="ned-campo">
            <label for="nedTitulo" class="form-label">Título <span class="required">*</span></label>
            <input type="text" name="titulo" id="nedTitulo"
                   class="form-control form-control-lg<?= isset($erros['titulo']) ? ' is-invalid' : '' ?>"
                   maxlength="<?= ONGHELP_NOTICIAS_TITULO_MAX ?>" data-contador
                   value="<?= noticiaEsc($valores['titulo']) ?>"
                   placeholder="Ex.: Mutirão planta 850 mudas nativas">
            <?= nedErro($erros, 'titulo') ?>
          </div>

          <div class="ned-campo">
            <label for="nedResumo" class="form-label">Resumo <span class="required">*</span></label>
            <textarea name="resumo" id="nedResumo" rows="2" data-contador
                      class="form-control<?= isset($erros['resumo']) ? ' is-invalid' : '' ?>"
                      maxlength="<?= ONGHELP_NOTICIAS_RESUMO_MAX ?>"
                      placeholder="Uma ou duas frases sobre o que aconteceu."><?= noticiaEsc($valores['resumo']) ?></textarea>
            <div class="form-text">Aparece nos cards e quando o link é compartilhado.</div>
            <?= nedErro($erros, 'resumo') ?>
          </div>

          <fieldset class="ned-campo">
            <legend class="form-label">Tipo <span class="required">*</span></legend>
            <div class="ned-tipos">
              <?php foreach ($tipos as $valorTipo => $rotuloTipo): ?>
                <label class="ned-tipo">
                  <input type="radio" name="tipo" value="<?= noticiaEsc($valorTipo) ?>" <?= $valores['tipo'] === $valorTipo ? 'checked' : '' ?>>
                  <span><i class="bi <?= noticiaEsc(noticiaTipoIcone($valorTipo)) ?>" aria-hidden="true"></i> <?= noticiaEsc($rotuloTipo) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
            <?= nedErro($erros, 'tipo') ?>
          </fieldset>

          <div class="ned-campo">
            <label for="nedTexto" class="form-label">Texto <span class="required">*</span></label>
            <div class="ned-editor-caixa<?= isset($erros['texto']) || isset($erros['pix']) ? ' is-invalid' : '' ?>">
              <div class="ned-ferramentas" role="toolbar" aria-label="Formatação" hidden>
                <button type="button" data-cmd="h2" title="Subtítulo">H2</button>
                <button type="button" data-cmd="bold" title="Negrito" aria-label="Negrito"><i class="bi bi-type-bold" aria-hidden="true"></i></button>
                <button type="button" data-cmd="italic" title="Itálico" aria-label="Itálico"><i class="bi bi-type-italic" aria-hidden="true"></i></button>
                <button type="button" data-cmd="list" title="Lista" aria-label="Lista"><i class="bi bi-list-ul" aria-hidden="true"></i></button>
                <button type="button" data-cmd="link" title="Link" aria-label="Link"><i class="bi bi-link-45deg" aria-hidden="true"></i></button>
              </div>
              <!-- Sem JavaScript, o texto é editado direto neste campo (HTML simples). -->
              <textarea name="texto" id="nedTexto" class="form-control ned-texto-bruto" rows="10"
                        maxlength="<?= ONGHELP_NOTICIAS_TEXTO_MAX * 2 ?>"><?= noticiaEsc($valores['texto']) ?></textarea>
              <div class="ned-editor nt-texto" id="nedEditor" contenteditable="<?= $somenteLeitura ? 'false' : 'true' ?>"
                   role="textbox" aria-multiline="true" aria-label="Texto da notícia"
                   data-placeholder="Conte os detalhes: o que aconteceu, quem participou, resultados e próximos passos." hidden></div>
            </div>
            <div class="form-text" id="nedTextoInfo">Mínimo de <?= ONGHELP_NOTICIAS_TEXTO_MIN ?> caracteres.</div>
            <?= nedErro($erros, 'texto') ?>
            <?= nedErro($erros, 'pix') ?>
          </div>
        </fieldset>

        <!-- 2. Fotos -->
        <fieldset class="glass-1 ned-painel" <?= $desabilitado ?>>
          <legend class="ned-painel-titulo"><span>2</span> Fotos</legend>
          <p class="ned-ajuda">
            Até <?= ONGHELP_NOTICIAS_FOTOS_MAX ?> fotos (JPG, PNG ou WebP, até 5 MB cada).
            A foto marcada como <strong>capa</strong> aparece nos cards.
            Descreva cada foto para quem usa leitor de tela.
          </p>

          <div class="ned-fotos" id="nedFotos">
            <?php foreach ($fotos as $indice => $foto):
              $id = (int) $foto['id'];
              $marcadaRemover = !empty($foto['marcada_remover']);
            ?>
              <div class="ned-foto<?= $marcadaRemover ? ' is-removida' : '' ?>">
                <div class="ned-foto-img">
                  <img src="<?= noticiaEsc($foto['url']) ?>" alt="" loading="lazy">
                  <label class="ned-foto-capa">
                    <input type="radio" name="capa" value="<?= $id ?>" <?= $indice === 0 ? 'checked' : '' ?>> Capa
                  </label>
                </div>
                <label class="visually-hidden" for="nedAlt<?= $id ?>">Descrição da foto <?= $indice + 1 ?></label>
                <textarea id="nedAlt<?= $id ?>" name="fotos[<?= $id ?>][alt]" rows="2" class="form-control form-control-sm" data-alt
                          maxlength="<?= ONGHELP_NOTICIAS_FOTO_ALT_MAX ?>"
                          placeholder="Descrição (o que aparece na foto)"><?= noticiaEsc((string) $foto['texto_alternativo']) ?></textarea>
                <label class="visually-hidden" for="nedLegenda<?= $id ?>">Legenda da foto <?= $indice + 1 ?></label>
                <input type="text" id="nedLegenda<?= $id ?>" name="fotos[<?= $id ?>][legenda]" class="form-control form-control-sm"
                       maxlength="<?= ONGHELP_NOTICIAS_FOTO_LEGENDA_MAX ?>" placeholder="Legenda (opcional)"
                       value="<?= noticiaEsc((string) ($foto['legenda'] ?? '')) ?>">
                <label class="ned-foto-remover">
                  <input type="checkbox" name="fotos[<?= $id ?>][remover]" value="1" data-remover <?= $marcadaRemover ? 'checked' : '' ?>>
                  <i class="bi bi-trash" aria-hidden="true"></i> Remover
                </label>
              </div>
            <?php endforeach; ?>
          </div>

          <?php $pendentes = $_SESSION['ned_pendentes'][$noticiaId] ?? []; ?>
          <?php if ($pendentes): ?>
            <p class="ned-ajuda"><i class="bi bi-clock-history" aria-hidden="true"></i>
              Estas fotos já foram enviadas e entram na notícia quando ela for salva.</p>
            <div class="ned-fotos">
              <?php foreach ($pendentes as $token => $pendente): ?>
                <div class="ned-foto is-nova">
                  <div class="ned-foto-img">
                    <img src="<?= noticiaEsc(noticiaFotoUrl((string) $pendente['arquivo'])) ?>" alt="">
                    <label class="ned-foto-capa">
                      <input type="radio" name="capa" value="pend-<?= noticiaEsc($token) ?>"
                             <?= ($_POST['capa'] ?? '') === $pendente['chave'] || ($fotos === [] && $token === array_key_first($pendentes)) ? 'checked' : '' ?>> Capa
                    </label>
                  </div>
                  <textarea name="pendentes[<?= noticiaEsc($token) ?>][alt]" rows="2" class="form-control form-control-sm" data-alt
                            maxlength="<?= ONGHELP_NOTICIAS_FOTO_ALT_MAX ?>" aria-label="Descrição da foto"
                            placeholder="Descrição (o que aparece na foto)"><?= noticiaEsc((string) $pendente['alt']) ?></textarea>
                  <input type="text" name="pendentes[<?= noticiaEsc($token) ?>][legenda]" class="form-control form-control-sm"
                         maxlength="<?= ONGHELP_NOTICIAS_FOTO_LEGENDA_MAX ?>" aria-label="Legenda da foto"
                         placeholder="Legenda (opcional)" value="<?= noticiaEsc((string) $pendente['legenda']) ?>">
                  <label class="ned-foto-remover">
                    <input type="checkbox" name="pendentes[<?= noticiaEsc($token) ?>][remover]" value="1" data-remover>
                    <i class="bi bi-trash" aria-hidden="true"></i> Remover
                  </label>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <?php if ($publicada): ?>
            <p class="ned-ajuda"><i class="bi bi-info-circle" aria-hidden="true"></i>
              Para adicionar fotos, despublique a notícia. Você ainda pode trocar a capa, editar descrições e remover fotos.</p>
          <?php elseif (!$somenteLeitura): ?>
            <label class="ned-enviar" id="nedEnviar">
              <input type="file" name="fotos_novas[]" id="nedArquivos" accept="image/jpeg,image/png,image/webp" multiple>
              <i class="bi bi-cloud-arrow-up" aria-hidden="true"></i>
              <span><strong>Escolher fotos</strong> ou arraste para cá</span>
            </label>
            <div class="ned-fotos" id="nedFotosNovas" aria-live="polite"></div>
          <?php endif; ?>
          <?= nedErro($erros, 'fotos') ?>
          <?= nedErro($erros, 'fotos_novas') ?>
        </fieldset>

        <!-- 3. Detalhes -->
        <fieldset class="glass-1 ned-painel" <?= $desabilitado ?>>
          <legend class="ned-painel-titulo"><span>3</span> Detalhes <small>(opcional)</small></legend>

          <div class="ned-grade-2">
            <div class="ned-campo">
              <label for="nedProjeto" class="form-label">Projeto relacionado</label>
              <select name="projeto_id" id="nedProjeto" class="form-select<?= isset($erros['projeto']) ? ' is-invalid' : '' ?>">
                <option value="">Nenhum</option>
                <?php foreach ($projetos as $projeto): ?>
                  <option value="<?= (int) $projeto['id'] ?>" <?= $valores['projeto_id'] === (string) $projeto['id'] ? 'selected' : '' ?>>
                    <?= noticiaEsc((string) $projeto['nome']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <?= nedErro($erros, 'projeto') ?>
            </div>

            <div class="ned-campo">
              <label for="nedMovimentacao" class="form-label">Movimentação relacionada</label>
              <select name="movimentacao_id" id="nedMovimentacao" class="form-select<?= isset($erros['movimentacao']) ? ' is-invalid' : '' ?>">
                <option value="">Nenhuma</option>
                <?php foreach ($movimentacoes as $mov): ?>
                  <option value="<?= (int) $mov['id'] ?>" <?= $valores['movimentacao_id'] === (string) $mov['id'] ? 'selected' : '' ?>>
                    <?= noticiaEsc(fmtData((string) $mov['data_mov']) . ' · ' . $mov['descricao'] . ' · '
                        . ((string) $mov['tipo'] === 'receita' ? '+ ' : '− ') . fmtDinheiro((float) $mov['valor'])) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <?= nedErro($erros, 'movimentacao') ?>
            </div>
          </div>
          <p class="ned-ajuda">Vincular um projeto ou uma movimentação mostra o resultado no portal de transparência junto da notícia.</p>

          <div class="ned-grade-2" id="nedEvento" <?= $valores['tipo'] === 'evento' ? '' : 'hidden' ?>>
            <div class="ned-campo">
              <label for="nedEventoData" class="form-label">Data e hora do evento</label>
              <input type="datetime-local" name="evento_data" id="nedEventoData"
                     class="form-control<?= isset($erros['evento_data']) ? ' is-invalid' : '' ?>"
                     value="<?= noticiaEsc($valores['evento_data']) ?>">
              <?= nedErro($erros, 'evento_data') ?>
            </div>
            <div class="ned-campo">
              <label for="nedEventoLocal" class="form-label">Local do evento</label>
              <input type="text" name="evento_local" id="nedEventoLocal"
                     class="form-control<?= isset($erros['evento_local']) ? ' is-invalid' : '' ?>"
                     maxlength="<?= ONGHELP_NOTICIAS_EVENTO_LOCAL_MAX ?>"
                     value="<?= noticiaEsc($valores['evento_local']) ?>"
                     placeholder="Ex.: Centro Esportivo Municipal">
              <?= nedErro($erros, 'evento_local') ?>
            </div>
          </div>

          <div class="ned-campo">
            <span class="form-label d-block">Onde acompanhar</span>
            <div class="ned-links" id="nedLinks">
              <?php foreach ($linksValores as $indice => $url): ?>
                <input type="url" name="links[]" class="form-control" maxlength="500" inputmode="url"
                       aria-label="Link <?= $indice + 1 ?>"
                       value="<?= noticiaEsc((string) $url) ?>" placeholder="https://instagram.com/suaong">
              <?php endforeach; ?>
            </div>
            <?php if (!$somenteLeitura): ?>
              <button type="button" class="btn btn-link btn-sm px-0" id="nedMaisLink"
                      data-max="<?= ONGHELP_NOTICIAS_LINKS_MAX ?>">
                <i class="bi bi-plus-lg" aria-hidden="true"></i> Adicionar link
              </button>
            <?php endif; ?>
            <?= nedErro($erros, 'links') ?>
            <?= nedErro($erros, 'vinculo') ?>
          </div>
        </fieldset>
      </div>

      <!-- Lateral: confirmação, checklist e ações -->
      <aside class="ned-lateral">
        <div class="glass-1 ned-painel ned-acoes">
          <h2 class="ned-painel-titulo"><span>4</span> Publicar</h2>

          <label class="form-check ned-direitos">
            <input class="form-check-input" type="checkbox" name="direitos_imagem" id="nedDireitos" value="1"
                   <?= $valores['direitos_imagem'] ? 'checked' : '' ?> <?= $desabilitado ?>>
            <span class="form-check-label"><?= noticiaEsc(ONGHELP_NOTICIAS_DIREITOS_TEXTO) ?></span>
          </label>
          <?= nedErro($erros, 'direitos_imagem') ?>

          <ul class="ned-checklist" id="nedChecklist" aria-label="Antes de publicar">
            <li data-check="titulo">Título e resumo</li>
            <li data-check="tipo">Tipo escolhido</li>
            <li data-check="texto">Texto com o mínimo de caracteres</li>
            <li data-check="fotos">Ao menos 1 foto com descrição</li>
            <li data-check="direitos">Autorização de imagem</li>
          </ul>

          <?php if (!$somenteLeitura): ?>
            <div class="ned-botoes">
              <?php if ($publicada): ?>
                <button type="submit" name="acao" value="salvar" class="btn btn-primary">Salvar alterações</button>
                <button type="submit" name="acao" value="despublicar" class="btn btn-outline-secondary" formnovalidate>Despublicar</button>
              <?php else: ?>
                <button type="submit" name="acao" value="publicar" class="btn btn-primary" <?= $podePublicar ? '' : 'disabled' ?>>
                  <i class="bi bi-send" aria-hidden="true"></i> Publicar
                </button>
                <button type="submit" name="acao" value="salvar" class="btn btn-outline-secondary">Salvar rascunho</button>
              <?php endif; ?>
              <?php if ($noticia): ?>
                <button type="submit" name="acao" value="excluir" class="btn btn-link text-danger" formnovalidate
                        data-confirmar="Excluir esta notícia e as fotos dela? Essa ação não pode ser desfeita.">
                  <i class="bi bi-trash" aria-hidden="true"></i> Excluir notícia
                </button>
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <p class="ned-nota">
            <i class="bi bi-shield-check" aria-hidden="true"></i>
            Não coloque chaves PIX no texto: as doações usam o botão <strong>Doar via PIX</strong> do seu perfil.
            <a href="termos.php" target="_blank" rel="noopener">Regras de publicação</a>
          </p>
        </div>
      </aside>
    </form>
  </div>
</section>

<template id="nedModeloFoto">
  <div class="ned-foto is-nova">
    <div class="ned-foto-img">
      <img src="" alt="">
      <label class="ned-foto-capa"><input type="radio" name="capa" value=""> Capa</label>
    </div>
    <textarea rows="2" class="form-control form-control-sm" data-alt maxlength="<?= ONGHELP_NOTICIAS_FOTO_ALT_MAX ?>"
              placeholder="Descrição (o que aparece na foto)" aria-label="Descrição da foto"></textarea>
    <input type="text" class="form-control form-control-sm" maxlength="<?= ONGHELP_NOTICIAS_FOTO_LEGENDA_MAX ?>"
           placeholder="Legenda (opcional)" aria-label="Legenda da foto">
    <button type="button" class="ned-foto-remover" data-tirar>
      <i class="bi bi-x-lg" aria-hidden="true"></i> Tirar
    </button>
  </div>
</template>

<?php include '../includes/footer.php'; ?>
