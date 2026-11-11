<?php
// ============================================================
// pages/noticia.php — Leitura pública de uma notícia
// ============================================================
//
// Endereço canônico: noticia.php?id=ID&slug=slug
// O slug é decorativo. Se faltar ou estiver diferente do salvo,
// a página responde 301 para o endereço canônico. Notícia
// inexistente, rascunho, oculta ou de ONG não aprovada responde 404
// (regra central em includes/noticias.php).

require_once '../includes/db.php';
require_once '../includes/helpers.php';
require_once '../includes/session.php';
require_once '../includes/csrf.php';
require_once '../includes/noticias.php';
require_once '../includes/noticias_ui.php';

/** Endereço absoluto a partir da pasta pages/ (canonical e Open Graph). */
function naUrlAbsoluta(string $relativo, bool $raiz = false): string
{
    $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
    $host = preg_replace('/[^a-z0-9.\-:\[\]]/i', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/pages/noticia.php'));
    $pasta = $raiz
        ? preg_replace('~/pages/[^/]+$~', '', $script)
        : preg_replace('~/[^/]+$~', '', $script);

    return ($https ? 'https' : 'http') . '://' . ($host !== '' ? $host : 'localhost')
        . $pasta . '/' . ltrim($relativo, '/');
}

function naIconeServico(string $servico): string
{
    return [
        'instagram' => 'bi-instagram',
        'facebook' => 'bi-facebook',
        'youtube' => 'bi-youtube',
        'tiktok' => 'bi-tiktok',
        'x' => 'bi-twitter-x',
    ][$servico] ?? 'bi-globe2';
}

// ------------------------------------------------------------
// 1. Notícia visível
// ------------------------------------------------------------
$idBruto = $_GET['id'] ?? null;
$idParam = is_string($idBruto) && ctype_digit($idBruto) ? (int) $idBruto : 0;
$slugBruto = $_GET['slug'] ?? '';
$slugParam = is_string($slugBruto) ? trim(mb_substr($slugBruto, 0, 160, 'UTF-8')) : '';
$noticia = buscarNoticiaPublica($pdo, $idParam > 0 ? (string) $idParam : $slugParam);

if (!$noticia) {
    http_response_code(404);

    $page_title = 'Notícia não encontrada';
    $page_robots = 'noindex, nofollow';
    $active_nav = 'noticias';
    include '../includes/header.php';
    ?>
    <section class="section nt-artigo-pagina">
      <div class="container">
        <div class="empty-state nt-vazio" role="status">
          <span class="empty-state-icon" aria-hidden="true"><i class="bi bi-newspaper"></i></span>
          <h1 class="h4">Esta notícia não está disponível.</h1>
          <p>Ela pode ter sido removida, ocultada pela equipe ou a organização não está mais ativa na plataforma.</p>
          <a href="noticias-publicas.php" class="btn btn-primary">
            <i class="bi bi-arrow-left" aria-hidden="true"></i> Ver notícias
          </a>
        </div>
      </div>
    </section>
    <?php
    include '../includes/footer.php';
    exit;
}

// ------------------------------------------------------------
// 2. Endereço canônico
// ------------------------------------------------------------
$noticiaId = (int) $noticia['id'];
$slugCanonico = trim((string) ($noticia['slug'] ?? ''));
$caminhoCanonico = 'noticia.php?id=' . $noticiaId
    . ($slugCanonico !== '' ? '&slug=' . rawurlencode($slugCanonico) : '');

if ($idParam !== $noticiaId || $slugParam !== $slugCanonico) {
    header('Location: ' . $caminhoCanonico, true, 301);
    exit;
}

$urlCanonica = naUrlAbsoluta($caminhoCanonico);

// ------------------------------------------------------------
// 3. Conteúdo
// ------------------------------------------------------------
$titulo = trim((string) $noticia['titulo']);
$resumo = trim((string) $noticia['resumo']);
$tipo = (string) $noticia['tipo'];
$texto = sanitizarHtmlNoticia((string) $noticia['texto']);
$minutosLeitura = max(1, noticiaTempoLeitura(noticiaTextoPlano($texto)));
$dataPublicacao = (string) ($noticia['publicada_em'] ?: $noticia['criado_em']);
$dataCompleta = noticiaDataCompletaPt($dataPublicacao);
$areaOng = trim((string) ($noticia['ong_area'] ?? ''));
$corCausa = noticiaCorDaCausa($noticia);
$localOng = implode(', ', array_filter([
    trim((string) ($noticia['ong_cidade'] ?? '')),
    trim((string) ($noticia['ong_estado'] ?? '')),
]));
$descricaoOng = trim((string) ($noticia['ong_descricao'] ?? ''));
$whatsappUrl = 'https://wa.me/?text=' . rawurlencode($titulo . ' — ' . $urlCanonica);

$fotos = noticiaFotosDaNoticia($pdo, $noticiaId);
$fotos = array_values(array_filter($fotos, static fn (array $foto): bool => $foto['url'] !== ''));
$capa = $fotos[0] ?? null;

$stmtLinks = $pdo->prepare(
    'SELECT url, dominio, servico FROM noticias_links WHERE noticia_id = ? ORDER BY ordem, id'
);
$stmtLinks->execute([$noticiaId]);
$links = $stmtLinks->fetchAll();

$projeto = null;
$movimentacao = null;

if (!empty($noticia['projeto_id'])) {
    $stmt = $pdo->prepare('SELECT nome, indicador, unidade FROM projetos WHERE id = ? AND usuario_id = ? LIMIT 1');
    $stmt->execute([(int) $noticia['projeto_id'], (int) $noticia['usuario_id']]);
    $projeto = $stmt->fetch() ?: null;
}

if (!empty($noticia['movimentacao_id'])) {
    $stmt = $pdo->prepare('SELECT tipo, descricao, valor, data_mov FROM movimentacoes WHERE id = ? AND usuario_id = ? LIMIT 1');
    $stmt->execute([(int) $noticia['movimentacao_id'], (int) $noticia['usuario_id']]);
    $movimentacao = $stmt->fetch() ?: null;
}

// ------------------------------------------------------------
// 4. Relacionadas: da mesma ONG e, se faltar, da mesma causa
// ------------------------------------------------------------
$colunasCard = "n.id, n.titulo, n.resumo, n.tipo, n.slug, n.publicada_em, n.criado_em,
                u.id AS ong_id, u.nome AS ong_nome, u.area AS ong_area,
                u.status AS ong_status, u.verificada_em AS ong_verificada_em, u.cidade AS ong_cidade,
                u.foto_perfil AS ong_foto_perfil";

$stmtMais = $pdo->prepare(
    "SELECT $colunasCard FROM noticias n INNER JOIN usuarios u ON u.id = n.usuario_id
     WHERE " . noticiasSqlVisivel('n', 'u') . " AND n.usuario_id = ? AND n.id <> ?
     ORDER BY COALESCE(n.publicada_em, n.criado_em) DESC, n.id DESC LIMIT 3"
);
$stmtMais->execute([(int) $noticia['usuario_id'], $noticiaId]);
$relacionadas = $stmtMais->fetchAll();
$tituloRelacionadas = 'Mais da ' . (string) $noticia['ong_nome'];
$linkRelacionadas = 'noticias-publicas.php?ong=' . (int) $noticia['usuario_id'] . '#lista';

if (count($relacionadas) < 3) {
    $excluir = array_merge([$noticiaId], array_map('intval', array_column($relacionadas, 'id')));
    $marcadores = implode(',', array_fill(0, count($excluir), '?'));
    $stmtVeja = $pdo->prepare(
        "SELECT $colunasCard FROM noticias n INNER JOIN usuarios u ON u.id = n.usuario_id
         WHERE " . noticiasSqlVisivel('n', 'u') . " AND n.id NOT IN ($marcadores)
         ORDER BY (u.area = ?) DESC, COALESCE(n.publicada_em, n.criado_em) DESC, n.id DESC
         LIMIT " . (3 - count($relacionadas))
    );
    $stmtVeja->execute(array_merge($excluir, [$areaOng]));
    $extras = $stmtVeja->fetchAll();

    if ($extras !== []) {
        $relacionadas = array_merge($relacionadas, $extras);
        $tituloRelacionadas = 'Continue lendo';
        $linkRelacionadas = 'noticias-publicas.php#lista';
    }
}

$stmtNum = $pdo->prepare(
    "SELECT (SELECT COUNT(*) FROM noticias n INNER JOIN usuarios u ON u.id = n.usuario_id
             WHERE " . noticiasSqlVisivel('n', 'u') . " AND n.usuario_id = ?),
            (SELECT COUNT(*) FROM projetos WHERE usuario_id = ?)"
);
$stmtNum->execute([(int) $noticia['usuario_id'], (int) $noticia['usuario_id']]);
[$totalNoticiasOng, $totalProjetosOng] = array_map('intval', $stmtNum->fetch(PDO::FETCH_NUM));

$fotosRelacionadas = noticiasFotosPorNoticia($pdo, array_column($relacionadas, 'id'));

// ------------------------------------------------------------
// 5. SEO e compartilhamento
// ------------------------------------------------------------
$page_title = $titulo;
$page_description = $resumo;
$page_canonical = $urlCanonica;
$page_og_title = $titulo;
$page_og_type = 'article';
$page_published_time = $dataPublicacao !== '' ? date('c', strtotime($dataPublicacao)) : '';

if ($capa) {
    $page_og_image = naUrlAbsoluta(noticiaFotoUrl((string) $capa['arquivo'], ''), true);
    $page_og_image_width = (int) $capa['largura'];
    $page_og_image_height = (int) $capa['altura'];
    $page_og_image_alt = trim((string) $capa['texto_alternativo']) ?: $titulo;
}

$active_nav = 'noticias';
$page_script = 'noticia.js';
include '../includes/header.php';

// A sessão é iniciada pelo header; só então o flash pode ser lido.
$acabouDePublicar = (int) flashGet('noticia_publicada') === $noticiaId;
$denunciaSucesso = flashGet('denuncia_sucesso');
$denunciaErro = flashGet('denuncia_erro');
?>

<article class="section nt-artigo-pagina">
  <div class="container">
  <!-- ── CAPA IMERSIVA: foto inteira com o título em vidro ── -->
  <header class="nt-hero<?= $capa ? '' : ' is-sem-capa' ?>">
    <?php if ($capa): ?>
      <button type="button" class="nt-foto-btn nt-hero-foto" data-nt-foto="0" aria-label="Ampliar foto">
        <img src="<?= noticiaEsc($capa['url']) ?>"
             alt="<?= noticiaEsc((string) $capa['texto_alternativo']) ?>"
             width="<?= (int) $capa['largura'] ?>" height="<?= (int) $capa['altura'] ?>"
             fetchpriority="high">
      </button>
    <?php endif; ?>

    <a class="nt-voltar" href="noticias-publicas.php">
      <i class="bi bi-arrow-left" aria-hidden="true"></i> Notícias
    </a>

    <?php if ($capa && trim((string) $capa['legenda']) !== ''): ?>
      <p class="nt-hero-legenda"><i class="bi bi-camera" aria-hidden="true"></i> <?= noticiaEsc((string) $capa['legenda']) ?></p>
    <?php endif; ?>

    <div class="nt-hero-vidro">
      <p class="nt-artigo-meta">
        <?= noticiaChipTipoHtml($tipo) ?>
        <time datetime="<?= noticiaEsc(date('Y-m-d\TH:i', strtotime($dataPublicacao))) ?>"><?= noticiaEsc($dataCompleta) ?></time>
        <span aria-hidden="true">·</span>
        <span><i class="bi bi-clock" aria-hidden="true"></i> <?= $minutosLeitura ?> min de leitura</span>
      </p>

      <h1 class="nt-artigo-titulo"><?= noticiaEsc($titulo) ?></h1>

      <?php if ($resumo !== ''): ?>
        <p class="nt-artigo-resumo"><?= noticiaEsc($resumo) ?></p>
      <?php endif; ?>
    </div>
  </header>

  <div class="nt-artigo-grade">
  <div class="nt-artigo-centro">
    <div class="nt-folha">

      <div class="nt-artigo-autor">
        <a class="nt-autor" href="perfilpublico.php?id=<?= (int) $noticia['usuario_id'] ?>">
          <?= noticiaAvatarOng($noticia, 44) ?>
          <span>
            <span class="nt-autor-nome">
              <?= noticiaEsc((string) $noticia['ong_nome']) ?> <?= noticiaSeloCompacto($noticia) ?>
            </span>
            <span class="nt-autor-local">
              <?php if ($areaOng !== ''): ?>
                <span class="cause-dot" style="--causa-cor:<?= noticiaEsc($corCausa) ?>;" aria-hidden="true"></span>
                <?= noticiaEsc($areaOng) ?> ·
              <?php endif; ?>
              <?= noticiaEsc($localOng !== '' ? $localOng : 'Brasil') ?>
            </span>
          </span>
        </a>

        <div class="nt-artigo-acoes">
          <button type="button" class="nt-acao" data-nt-copiar="<?= noticiaEsc($urlCanonica) ?>">
            <i class="bi bi-link-45deg" aria-hidden="true"></i> <span>Copiar link</span>
          </button>
          <a class="nt-acao" href="<?= noticiaEsc($whatsappUrl) ?>" target="_blank" rel="noopener noreferrer">
            <i class="bi bi-whatsapp" aria-hidden="true"></i> WhatsApp
          </a>
          <button type="button" class="nt-acao nt-btn-icone"
                  data-bs-toggle="modal" data-bs-target="#modalDenuncia"
                  aria-label="Denunciar notícia" title="Denunciar notícia">
            <i class="bi bi-flag" aria-hidden="true"></i>
          </button>
        </div>
      </div>

      <?php if ($acabouDePublicar): ?>
        <div class="alert alert-success nt-publicada" role="status">
          <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
          <span><strong>Notícia publicada!</strong> Compartilhe o link com quem acompanha sua ONG.</span>
        </div>
      <?php endif; ?>

      <div class="nt-artigo-corpo">

        <?php if ($tipo === 'evento' && !empty($noticia['evento_data'])): ?>
          <aside class="nt-evento" aria-label="Informações do evento">
            <span class="nt-evento-icone" aria-hidden="true"><i class="bi bi-calendar-event"></i></span>
            <div>
              <p class="nt-evento-rotulo">Evento</p>
              <p class="nt-evento-quando"><?= noticiaEsc(noticiaDataCompletaPt((string) $noticia['evento_data'])) ?></p>
              <?php if (trim((string) ($noticia['evento_local'] ?? '')) !== ''): ?>
                <p class="nt-evento-local">
                  <i class="bi bi-geo-alt" aria-hidden="true"></i> <?= noticiaEsc((string) $noticia['evento_local']) ?>
                </p>
              <?php endif; ?>
            </div>
          </aside>
        <?php endif; ?>

        <div class="nt-texto">
          <?= $texto ?>
        </div>

        <!-- Galeria (as demais fotos) -->
        <?php if (count($fotos) > 1): ?>
          <section class="nt-galeria" aria-labelledby="ntGaleriaTitulo">
            <h2 class="nt-rotulo" id="ntGaleriaTitulo">
              <i class="bi bi-images" aria-hidden="true"></i> Fotos (<?= count($fotos) ?>)
            </h2>
            <div class="nt-galeria-grade">
              <?php foreach ($fotos as $indice => $foto): ?>
                <button type="button" class="nt-foto-btn" data-nt-foto="<?= $indice ?>"
                        aria-label="Ampliar foto <?= $indice + 1 ?>">
                  <img src="<?= noticiaEsc($foto['url']) ?>"
                       alt="<?= noticiaEsc((string) $foto['texto_alternativo']) ?>"
                       width="<?= (int) $foto['largura'] ?>" height="<?= (int) $foto['altura'] ?>"
                       loading="lazy" decoding="async">
                </button>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endif; ?>

        <?php if ($links): ?>
          <section class="nt-bloco" aria-labelledby="ntLinksTitulo">
            <h2 class="nt-rotulo" id="ntLinksTitulo">
              <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> Onde acompanhar
            </h2>
            <div class="nt-links">
              <?php foreach ($links as $link): ?>
                <a class="cause-chip" href="<?= noticiaEsc((string) $link['url']) ?>" target="_blank" rel="noopener noreferrer">
                  <i class="bi <?= noticiaEsc(naIconeServico((string) $link['servico'])) ?>" aria-hidden="true"></i>
                  <?= noticiaEsc((string) $link['dominio']) ?>
                  <span class="visually-hidden">(abre em nova aba)</span>
                </a>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endif; ?>

        <?php if ($projeto || $movimentacao): ?>
          <section class="nt-contas" aria-labelledby="ntContasTitulo">
            <h2 class="nt-rotulo" id="ntContasTitulo">
              <i class="bi bi-receipt" aria-hidden="true"></i> Nas contas da ONG
            </h2>

            <?php if ($movimentacao):
              $receita = (string) $movimentacao['tipo'] === 'receita';
            ?>
              <div class="nt-contas-linha">
                <span>
                  <strong><?= noticiaEsc((string) $movimentacao['descricao']) ?></strong>
                  <small><?= noticiaEsc(fmtData((string) $movimentacao['data_mov'])) ?> · <?= $receita ? 'Receita' : 'Despesa' ?></small>
                </span>
                <span class="nt-contas-valor <?= $receita ? 'is-receita' : 'is-despesa' ?>">
                  <?= $receita ? '+' : '−' ?> <?= noticiaEsc(fmtDinheiro(abs((float) $movimentacao['valor']))) ?>
                </span>
              </div>
            <?php endif; ?>

            <?php if ($projeto): ?>
              <div class="nt-contas-linha">
                <span>
                  <strong><?= noticiaEsc((string) $projeto['nome']) ?></strong>
                  <small>Resultado do projeto</small>
                </span>
                <span class="nt-contas-valor">
                  <?= number_format((float) $projeto['indicador'], 0, ',', '.') ?>
                  <small><?= noticiaEsc((string) $projeto['unidade']) ?></small>
                </span>
              </div>
            <?php endif; ?>

            <a class="link-arrow" href="transparencia.php?ong=<?= (int) $noticia['usuario_id'] ?>">
              Ver relatório completo <span aria-hidden="true">→</span>
            </a>
          </section>
        <?php endif; ?>

        <p class="nt-aviso">
          <i class="bi bi-info-circle" aria-hidden="true"></i>
          Conteúdo publicado pela própria ONG. O selo indica verificação cadastral, não auditoria financeira.
        </p>
      </div>
    </div><!-- /folha -->
  </div><!-- /centro -->

      <!-- ── LATERAL ──────────────────────────────────────── -->
      <aside class="nt-artigo-lateral" aria-label="Sobre a organização">
        <section class="glass-1 nt-ong-card">
          <div class="nt-ong-card-topo">
            <?= noticiaAvatarOng($noticia, 56) ?>
            <div>
              <p class="nt-ong-card-nome"><?= noticiaEsc((string) $noticia['ong_nome']) ?></p>
              <p class="nt-ong-card-local"><?= noticiaEsc($localOng !== '' ? $localOng : 'Brasil') ?></p>
            </div>
          </div>
          <?= badgeVerificado([
              'status' => $noticia['ong_status'] ?? '',
              'verificada_em' => $noticia['ong_verificada_em'] ?? null,
          ]) ?>
          <?php if ($descricaoOng !== ''): ?>
            <p class="nt-ong-card-desc"><?= noticiaEsc($descricaoOng) ?></p>
          <?php endif; ?>
          <button type="button" class="btn-pix is-largo"
                  data-bs-toggle="modal" data-bs-target="#modalDoacao<?= $noticiaId ?>">
            <i class="bi bi-qr-code" aria-hidden="true"></i> Doar via PIX
          </button>
          <a class="btn btn-outline-secondary w-100" href="perfilpublico.php?id=<?= (int) $noticia['usuario_id'] ?>">
            Ver perfil da ONG
          </a>
          <dl class="nt-ong-card-numeros">
            <div><dt>Notícias</dt><dd><?= $totalNoticiasOng ?></dd></div>
            <div><dt>Projetos</dt><dd><?= $totalProjetosOng ?></dd></div>
          </dl>
          <a class="btn-portal is-largo" href="transparencia.php?ong=<?= (int) $noticia['usuario_id'] ?>"><i class="bi bi-bar-chart-line" aria-hidden="true"></i> Ver no portal de transparência</a>
        </section>
      </aside>
    </div>

    <!-- ── RELACIONADAS ───────────────────────────────────── -->
    <?php if ($relacionadas): ?>
      <section class="nt-relacionadas" aria-labelledby="ntRelacionadasTitulo">
        <div class="nt-secao-head">
          <h2 class="nt-secao-titulo" id="ntRelacionadasTitulo"><?= noticiaEsc($tituloRelacionadas) ?></h2>
          <a class="link-arrow" href="<?= noticiaEsc($linkRelacionadas) ?>">Ver todas <span aria-hidden="true">→</span></a>
        </div>
        <?= noticiasGrade($relacionadas, $fotosRelacionadas) ?>
      </section>
    <?php endif; ?>

  </div>
</article>

<!-- Visualizador de fotos -->
<?php if ($fotos): ?>
  <dialog class="nt-visualizador" id="ntVisualizador" aria-label="Fotos da notícia">
    <button type="button" class="nt-vis-fechar" data-nt-fechar aria-label="Fechar">
      <i class="bi bi-x-lg" aria-hidden="true"></i>
    </button>
    <?php if (count($fotos) > 1): ?>
      <button type="button" class="nt-vis-nav is-anterior" data-nt-anterior aria-label="Foto anterior">
        <i class="bi bi-chevron-left" aria-hidden="true"></i>
      </button>
      <button type="button" class="nt-vis-nav is-proxima" data-nt-proxima aria-label="Próxima foto">
        <i class="bi bi-chevron-right" aria-hidden="true"></i>
      </button>
    <?php endif; ?>
    <figure>
      <img id="ntVisImagem" src="" alt="">
      <figcaption id="ntVisLegenda"></figcaption>
    </figure>
  </dialog>
  <script type="application/json" id="ntFotos"><?= json_encode(array_map(
      static fn (array $foto): array => [
          'src' => $foto['url'],
          'alt' => (string) $foto['texto_alternativo'],
          'legenda' => (string) ($foto['legenda'] ?? ''),
      ],
      $fotos
  ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php endif; ?>

<!-- Denúncia -->
<div class="modal fade modal-denuncia" id="modalDenuncia" tabindex="-1"
     aria-labelledby="modalDenunciaTitulo" aria-hidden="true"
     <?= ($denunciaSucesso !== '' || $denunciaErro !== '') ? 'data-nt-abrir' : '' ?>>
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title" id="modalDenunciaTitulo">Denunciar notícia</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <?php if ($denunciaSucesso !== ''): ?>
          <div class="alert alert-success" role="status"><?= noticiaEsc($denunciaSucesso) ?></div>
          <button type="button" class="btn btn-primary w-100" data-bs-dismiss="modal">Fechar</button>
        <?php else: ?>
          <?php if ($denunciaErro !== ''): ?>
            <div class="alert alert-warning" role="alert"><?= noticiaEsc($denunciaErro) ?></div>
          <?php endif; ?>

          <form action="denunciar-noticia.php" method="post">
            <?= csrfField() ?>
            <input type="hidden" name="noticia_id" value="<?= $noticiaId ?>">

            <fieldset class="mb-3">
              <legend class="form-label">Motivo da denúncia</legend>
              <?php foreach (noticiasMotivosDenuncia() as $chave => $rotulo): ?>
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="motivo"
                         id="motivo-<?= noticiaEsc($chave) ?>" value="<?= noticiaEsc($chave) ?>" required>
                  <label class="form-check-label" for="motivo-<?= noticiaEsc($chave) ?>"><?= noticiaEsc($rotulo) ?></label>
                </div>
              <?php endforeach; ?>
            </fieldset>

            <div class="mb-3">
              <label for="denunciaDetalhe" class="form-label">Detalhes (opcional)</label>
              <textarea class="form-control" id="denunciaDetalhe" name="detalhe" rows="3"
                        maxlength="<?= ONGHELP_NOTICIAS_DENUNCIA_DETALHE_MAX ?>"></textarea>
            </div>

            <div class="mb-3">
              <label for="denunciaEmail" class="form-label">Seu e-mail (opcional)</label>
              <input type="email" class="form-control" id="denunciaEmail" name="email" maxlength="160" autocomplete="email">
              <div class="form-text">Só para retorno da equipe. Não é mostrado à ONG.</div>
            </div>

            <button type="submit" class="btn btn-primary w-100">
              <i class="bi bi-flag" aria-hidden="true"></i> Enviar denúncia
            </button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php
$modalUid = $noticiaId;
$modalOng = [
    'nome' => (string) $noticia['ong_nome'],
    'pix_chave' => (string) ($noticia['ong_pix_chave'] ?? ''),
    'cidade' => (string) ($noticia['ong_cidade'] ?? ''),
];
include '../includes/modal_doacao.php';

include '../includes/footer.php';
