<?php
// ============================================================
// pages/perfilpublico.php — Perfil público de uma ONG
// Projeto ONGHelp · Projeto Integrador 2026
// ============================================================
//
// PARÂMETRO GET:
//   ?id=42 → ID da ONG a exibir
//
// Mostra todas as informações públicas da organização:
// identidade, descrição, contato, projetos e movimentações.
// ============================================================

require_once '../includes/db.php';
require_once '../includes/helpers.php';
require_once '../includes/noticias.php';
require_once '../includes/noticias_ui.php';

// ── Sanitização do parâmetro ────────────────────────────────
$id = isset($_GET['id']) && ctype_digit($_GET['id']) ? (int) $_GET['id'] : 0;

// ── Busca a ONG (somente ativa) ─────────────────────────────
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ? AND status = 'aprovada'");
$stmt->execute([$id]);
$ong = $stmt->fetch();

// Perfil de ONG inexistente, pendente, recusada ou suspensa responde 404:
// o recurso não está público e não deve ser indexado como página válida.
if (!$ong) {
    http_response_code(404);
}

$projetos = [];
$movs     = [];
$noticiasOng = [];
$fotosNoticiasOng = [];
$totais   = ['receita' => 0.0, 'despesa' => 0.0];

if ($ong) {
    // Projetos da ONG (apenas as colunas exibidas)
    $projs = $pdo->prepare("
        SELECT nome, indicador, unidade
        FROM   projetos
        WHERE  usuario_id = ?
        ORDER  BY nome ASC
    ");
    $projs->execute([$id]);
    $projetos = $projs->fetchAll();

    // Últimas 10 movimentações (apenas as colunas exibidas)
    $mv = $pdo->prepare("
        SELECT data_mov, descricao, tipo, valor
        FROM   movimentacoes
        WHERE  usuario_id = ?
        ORDER  BY data_mov DESC, id DESC
        LIMIT  10
    ");
    $mv->execute([$id]);
    $movs = $mv->fetchAll();

    // Totais por tipo (receitas e despesas de todo o histórico)
    $tt = $pdo->prepare("
        SELECT tipo, COALESCE(SUM(valor), 0) AS total
        FROM   movimentacoes
        WHERE  usuario_id = ?
        GROUP  BY tipo
    ");
    $tt->execute([$id]);
    foreach ($tt->fetchAll() as $r) {
        $totais[$r['tipo']] = (float) $r['total'];
    }

    // Notícias visíveis da ONG, sempre pela regra central.
    $nt = $pdo->prepare("
        SELECT n.*,
               u.nome AS ong_nome,
               u.status AS ong_status,
               u.area AS ong_area,
               u.cidade AS ong_cidade,
               u.estado AS ong_estado,
               u.descricao AS ong_descricao,
               u.verificada_em AS ong_verificada_em,
               u.pix_chave AS ong_pix_chave,
               u.foto_perfil AS ong_foto_perfil,
               u.cor_tema AS ong_cor_tema,
               u.cor_fundo AS ong_cor_fundo
        FROM   noticias n
        INNER JOIN usuarios u ON u.id = n.usuario_id
        WHERE  n.usuario_id = ?
          AND  " . noticiasSqlVisivel('n', 'u') . "
        ORDER  BY COALESCE(n.publicada_em, n.criado_em) DESC, n.id DESC
    ");
    $nt->execute([$id]);
    $noticiasOng = $nt->fetchAll();

    $fotosNoticiasOng = noticiasFotosPorNoticia(
        $pdo,
        array_map(static fn (array $noticia): int => (int) $noticia['id'], $noticiasOng),
        '../'
    );
}

$saldo = $totais['receita'] - $totais['despesa'];

// ── Paleta por área (banner e selo do perfil) ────────────────
$coresArea = coresArea();

// Tema visual do perfil: cor de banner e mancha de fundo.
// A cor de banner aparece somente na capa, na aba ativa e nos
// números dos projetos; a cor de fundo vira apenas a mancha.
$cor1    = '#5A6A80';
$cor2    = '#3D4A5C';
$corFundo = '';
$iniciaisOng = '';
$temFotoOng = false;

if ($ong) {
    [$cor1, $cor2] = temaOng($ong, $coresArea);
    $cor2 = escurecerCor($cor1, 0.82);
    $corFundo = fundoOng($ong);

    $arquivoFoto = basename(trim((string) ($ong['foto_perfil'] ?? '')));
    $temFotoOng = $arquivoFoto !== ''
        && is_file(__DIR__ . '/../assets/img/uploads/perfis/' . $arquivoFoto);

    $partesNome = preg_split('/\s+/u', trim((string) $ong['nome'])) ?: [];
    $primeiraParte = $partesNome[0] ?? '';
    $ultimaParte = count($partesNome) > 1 ? $partesNome[count($partesNome) - 1] : '';
    $iniciaisOng = mb_strtoupper(
        mb_substr($primeiraParte, 0, 1) . mb_substr($ultimaParte, 0, 1)
    );
}

$page_title = $ong ? $ong['nome'] : 'ONG não encontrada';
$active_nav = 'ongs';
include '../includes/header.php';
?>

<section class="profile-page perfil-publico"
         style="--ong-banner:<?= htmlspecialchars($cor1) ?>;--ong-banner-dark:<?= htmlspecialchars($cor2) ?>;--ong-fundo:<?= $corFundo !== '' ? htmlspecialchars($corFundo) : 'transparent' ?>;">
  <div class="container">

    <a href="ongs.php" class="perfil-voltar"><span aria-hidden="true">&larr;</span> Voltar para ONGs</a>

    <?php if (!$ong): ?>

      <!-- ONG não encontrada -->
      <div class="ong-vazio mt-4">
        <div class="ong-vazio-icone"><i class="bi bi-search" aria-hidden="true"></i></div>
        <h5>ONG não encontrada</h5>
        <p class="text-muted mb-4">
          Esta organização não existe ou não está mais ativa na plataforma.
        </p>
        <a href="ongs.php" class="btn btn-primary rounded-pill px-4 btn-sm">
          <i class="bi bi-arrow-left me-1"></i> Ver todas as ONGs
        </a>
      </div>

    <?php else: ?>

    <!-- ── CABEÇALHO DE PERFIL ── -->
    <div class="perfil-cabecalho">
      <div class="perfil-capa" aria-hidden="true"></div>

      <div class="perfil-cabecalho-corpo">
        <div class="perfil-avatar-box">
          <?php // Foto da ONG ou, sem foto, a ilustração da causa (igual ao diretório). ?>
          <img src="<?= htmlspecialchars(fotoPerfilUrl($ong['foto_perfil'] ?? null, '../', $ong['area'] ?? null), ENT_QUOTES, 'UTF-8') ?>"
               alt="<?= $temFotoOng ? 'Foto de perfil de ' . htmlspecialchars($ong['nome']) : '' ?>"
               class="perfil-avatar-img">
        </div>

        <div class="perfil-identidade">
          <div class="perfil-identidade-topo">
            <h1 class="perfil-nome">
              <span><?= htmlspecialchars($ong['nome']) ?></span>
              <?= badgeVerificado($ong) ?>
            </h1>

            <div class="perfil-acoes">
              <?php if (!empty($ong['pix_chave'])): ?>
                <button type="button" class="btn-pix"
                        data-bs-toggle="modal" data-bs-target="#modalDoacao<?= $ong['id'] ?>">
                  <i class="bi bi-qr-code" aria-hidden="true"></i> Doar via PIX
                </button>
              <?php endif; ?>
              <a href="transparencia.php?ong=<?= $ong['id'] ?>" class="btn-portal">
                <i class="bi bi-bar-chart-line" aria-hidden="true"></i> Ver no portal de transparência
              </a>
            </div>
          </div>

          <p class="perfil-meta">
            <span class="perfil-meta-item">
              <i class="bi bi-tag" aria-hidden="true"></i>
              <?= htmlspecialchars($ong['area']) ?>
            </span>
            <span class="perfil-meta-divisor" aria-hidden="true">·</span>
            <span class="perfil-meta-item">
              <i class="bi bi-geo-alt" aria-hidden="true"></i>
              <?= htmlspecialchars($ong['cidade'] ?: 'Brasil') ?><?= $ong['estado'] ? ', ' . htmlspecialchars($ong['estado']) : '' ?>
            </span>
            <span class="perfil-meta-divisor" aria-hidden="true">·</span>
            <span class="perfil-meta-item">
              <i class="bi bi-calendar3" aria-hidden="true"></i>
              Membro desde <?= date('d/m/Y', strtotime($ong['criado_em'])) ?>
            </span>
          </p>

          <?php if (!empty($ong['descricao'])): ?>
            <p class="perfil-bio"><?= htmlspecialchars($ong['descricao']) ?></p>
          <?php else: ?>
            <p class="perfil-bio perfil-bio-vazia">Esta organização ainda não publicou sua descrição.</p>
          <?php endif; ?>

          <div class="perfil-contadores">
            <div class="perfil-contador">
              <span class="perfil-contador-valor is-receita"><?= fmtDinheiro($totais['receita'], true) ?></span>
              <span class="perfil-contador-label">Receitas</span>
            </div>
            <div class="perfil-contador">
              <span class="perfil-contador-valor is-despesa"><?= fmtDinheiro($totais['despesa'], true) ?></span>
              <span class="perfil-contador-label">Despesas</span>
            </div>
            <div class="perfil-contador">
              <span class="perfil-contador-valor <?= $saldo >= 0 ? 'is-positivo' : 'is-negativo' ?>">
                <?= ($saldo >= 0 ? '+ ' : '- ') . fmtDinheiro(abs($saldo), true) ?>
              </span>
              <span class="perfil-contador-label">Saldo</span>
            </div>
            <div class="perfil-contador">
              <span class="perfil-contador-valor"><?= count($projetos) ?></span>
              <span class="perfil-contador-label">Projetos</span>
            </div>
          </div>

          <p class="perfil-nota">
            <i class="bi bi-info-circle" aria-hidden="true"></i>
            Os dados institucionais e financeiros são autodeclarados pela ONG. O selo indica
            verificação cadastral, não auditoria financeira.
          </p>
        </div>
      </div>
    </div>

    <!-- ── ABAS ── -->
    <div class="perfil-abas" role="tablist" aria-label="Seções do perfil">
      <button type="button" class="perfil-aba is-active" id="perfil-aba-visao-geral"
              role="tab" aria-selected="true" aria-controls="perfil-painel-visao-geral"
              tabindex="0" data-profile-tab="visao-geral">Visão geral</button>
      <button type="button" class="perfil-aba" id="perfil-aba-projetos"
              role="tab" aria-selected="false" aria-controls="perfil-painel-projetos"
              tabindex="-1" data-profile-tab="projetos">Projetos</button>
      <button type="button" class="perfil-aba" id="perfil-aba-movimentacoes"
              role="tab" aria-selected="false" aria-controls="perfil-painel-movimentacoes"
              tabindex="-1" data-profile-tab="movimentacoes">Movimentações</button>
      <button type="button" class="perfil-aba" id="perfil-aba-noticias"
              role="tab" aria-selected="false" aria-controls="perfil-painel-noticias"
              tabindex="-1" data-profile-tab="noticias">Notícias</button>
    </div>

    <!-- ── CORPO ── -->
    <div class="perfil-corpo">
      <div class="perfil-conteudo">

        <!-- VISÃO GERAL -->
        <div class="perfil-aba-painel" id="perfil-painel-visao-geral"
             role="tabpanel" aria-labelledby="perfil-aba-visao-geral"
             data-profile-panel="visao-geral">

          <section class="perfil-painel">
            <h2 class="perfil-painel-titulo">Sobre</h2>
            <?php if (!empty($ong['descricao'])): ?>
              <p class="perfil-painel-texto"><?= htmlspecialchars($ong['descricao']) ?></p>
            <?php else: ?>
              <p class="perfil-painel-texto perfil-bio-vazia">Esta organização ainda não publicou sua descrição.</p>
            <?php endif; ?>
          </section>

          <section class="perfil-painel">
            <h2 class="perfil-painel-titulo">Resultados em destaque</h2>
            <?php if (empty($projetos)): ?>
              <p class="perfil-vazio">Esta organização ainda não cadastrou projetos</p>
            <?php else: ?>
              <div class="perfil-projetos-grid">
                <?php foreach (array_slice($projetos, 0, 2) as $proj): ?>
                  <div class="perfil-projeto">
                    <div class="perfil-projeto-numero"><?= number_format($proj['indicador'], 0, ',', '.') ?></div>
                    <div class="perfil-projeto-desc"><?= htmlspecialchars($proj['unidade']) ?></div>
                    <div class="perfil-projeto-nome"><?= htmlspecialchars($proj['nome']) ?></div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </section>

          <?php if ($noticiasOng): ?>
            <section class="perfil-painel">
              <div class="perfil-painel-cabecalho">
                <h2 class="perfil-painel-titulo">Últimas notícias</h2>
                <a href="#noticias" class="perfil-link" data-profile-jump="noticias">
                  Ver todas <span aria-hidden="true">→</span>
                </a>
              </div>
              <?= noticiasGrade(array_slice($noticiasOng, 0, 2), $fotosNoticiasOng, '../', '', 'nt-grade--2') ?>
            </section>
          <?php endif; ?>

          <section class="perfil-painel">
            <div class="perfil-painel-cabecalho">
              <h2 class="perfil-painel-titulo">Últimas movimentações</h2>
              <?php if (!empty($movs)): ?>
                <a href="#movimentacoes" class="perfil-link" data-profile-jump="movimentacoes">
                  Ver todas <span aria-hidden="true">→</span>
                </a>
              <?php endif; ?>
            </div>

            <?php if (empty($movs)): ?>
              <p class="perfil-vazio">Nenhuma movimentação cadastrada ainda.</p>
            <?php else: ?>
              <div class="tr-feed">
                <?php foreach (array_slice($movs, 0, 5) as $mov):
                  $ehReceita = $mov['tipo'] === 'receita';
                ?>
                  <div class="tr-feed-item">
                    <span class="tr-feed-icon <?= $ehReceita ? 'is-receita' : 'is-despesa' ?>" aria-hidden="true">
                      <i class="bi bi-arrow-<?= $ehReceita ? 'down' : 'up' ?>-circle-fill"></i>
                    </span>
                    <div class="tr-feed-main">
                      <div class="tr-feed-desc"><?= htmlspecialchars($mov['descricao']) ?></div>
                      <div class="tr-feed-meta">
                        <?= date('d/m/Y', strtotime($mov['data_mov'])) ?> · <?= $ehReceita ? 'Receita' : 'Despesa' ?>
                      </div>
                    </div>
                    <span class="tr-feed-value <?= $ehReceita ? 'is-pos' : 'is-neg' ?>">
                      <?= ($ehReceita ? '+ ' : '- ') . fmtDinheiro((float) $mov['valor']) ?>
                    </span>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </section>
        </div>

        <!-- PROJETOS -->
        <div class="perfil-aba-painel" id="perfil-painel-projetos"
             role="tabpanel" aria-labelledby="perfil-aba-projetos"
             data-profile-panel="projetos" hidden>
          <section class="perfil-painel">
            <h2 class="perfil-painel-titulo">Projetos e metas</h2>
            <?php if (empty($projetos)): ?>
              <p class="perfil-vazio">Esta organização ainda não cadastrou projetos</p>
            <?php else: ?>
              <div class="perfil-projetos-grid">
                <?php foreach ($projetos as $proj): ?>
                  <div class="perfil-projeto">
                    <div class="perfil-projeto-numero"><?= number_format($proj['indicador'], 0, ',', '.') ?></div>
                    <div class="perfil-projeto-desc"><?= htmlspecialchars($proj['unidade']) ?></div>
                    <div class="perfil-projeto-nome"><?= htmlspecialchars($proj['nome']) ?></div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </section>
        </div>

        <!-- MOVIMENTAÇÕES -->
        <div class="perfil-aba-painel" id="perfil-painel-movimentacoes"
             role="tabpanel" aria-labelledby="perfil-aba-movimentacoes"
             data-profile-panel="movimentacoes" hidden>
          <section class="perfil-painel">
            <h2 class="perfil-painel-titulo">Últimas movimentações</h2>
            <?php if (empty($movs)): ?>
              <p class="perfil-vazio">Nenhuma movimentação cadastrada ainda.</p>
            <?php else: ?>
              <div class="tr-feed">
                <?php foreach ($movs as $mov):
                  $ehReceita = $mov['tipo'] === 'receita';
                ?>
                  <div class="tr-feed-item">
                    <span class="tr-feed-icon <?= $ehReceita ? 'is-receita' : 'is-despesa' ?>" aria-hidden="true">
                      <i class="bi bi-arrow-<?= $ehReceita ? 'down' : 'up' ?>-circle-fill"></i>
                    </span>
                    <div class="tr-feed-main">
                      <div class="tr-feed-desc"><?= htmlspecialchars($mov['descricao']) ?></div>
                      <div class="tr-feed-meta">
                        <?= date('d/m/Y', strtotime($mov['data_mov'])) ?> · <?= $ehReceita ? 'Receita' : 'Despesa' ?>
                      </div>
                    </div>
                    <span class="tr-feed-value <?= $ehReceita ? 'is-pos' : 'is-neg' ?>">
                      <?= ($ehReceita ? '+ ' : '- ') . fmtDinheiro((float) $mov['valor']) ?>
                    </span>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </section>
        </div>

        <!-- NOTÍCIAS -->
        <div class="perfil-aba-painel" id="perfil-painel-noticias"
             role="tabpanel" aria-labelledby="perfil-aba-noticias"
             data-profile-panel="noticias" hidden>
          <section class="perfil-painel">
            <div class="perfil-painel-cabecalho">
              <h2 class="perfil-painel-titulo">
                Notícias
                <span class="perfil-noticias-total" aria-label="<?= count($noticiasOng) ?> notícias publicadas">
                  <?= count($noticiasOng) ?>
                </span>
              </h2>
            </div>

            <?php if (empty($noticiasOng)): ?>
              <p class="perfil-vazio">Esta organização ainda não publicou notícias</p>
            <?php else: ?>
              <?= noticiasGrade($noticiasOng, $fotosNoticiasOng, '../', '', 'nt-grade--2') ?>
            <?php endif; ?>
          </section>
        </div>

      </div>

      <!-- LATERAL -->
      <aside class="perfil-lateral">
        <section class="perfil-painel">
          <h2 class="perfil-painel-titulo">Dados institucionais</h2>
          <dl class="perfil-dados">
            <div class="perfil-dado">
              <dt>CNPJ</dt>
              <dd><?= htmlspecialchars(mascaraCnpj($ong['cnpj'])) ?></dd>
            </div>
            <div class="perfil-dado">
              <dt>E-mail de contato</dt>
              <dd><a href="mailto:<?= htmlspecialchars($ong['email']) ?>"><?= htmlspecialchars($ong['email']) ?></a></dd>
            </div>
            <div class="perfil-dado">
              <dt>Localização</dt>
              <dd><?= htmlspecialchars($ong['cidade'] ?: 'Brasil') ?><?= $ong['estado'] ? ' · ' . htmlspecialchars($ong['estado']) : '' ?></dd>
            </div>
            <div class="perfil-dado">
              <dt>Área de atuação</dt>
              <dd><?= htmlspecialchars($ong['area']) ?></dd>
            </div>
            <?php if (!empty($ong['canal_valor'])): ?>
              <?php $linkCanalPerfil = canalUrl((string) $ong['canal_tipo'], (string) $ong['canal_valor']); ?>
              <div class="perfil-dado">
                <dt>Canal público</dt>
                <dd>
                  <?php if ($linkCanalPerfil !== ''): ?>
                    <a href="<?= htmlspecialchars($linkCanalPerfil) ?>"
                       target="_blank" rel="noopener noreferrer">
                      <i class="bi bi-link-45deg" aria-hidden="true"></i> <?= htmlspecialchars($ong['canal_valor']) ?>
                    </a>
                  <?php else: ?>
                    <span><i class="bi bi-link-45deg" aria-hidden="true"></i> <?= htmlspecialchars($ong['canal_valor']) ?></span>
                  <?php endif; ?>
                </dd>
              </div>
            <?php endif; ?>
            <div class="perfil-dado">
              <dt>Membro desde</dt>
              <dd><?= date('d/m/Y', strtotime($ong['criado_em'])) ?></dd>
            </div>
          </dl>
        </section>

        <?php if (!empty($ong['pix_chave'])): ?>
          <section class="perfil-painel">
            <h2 class="perfil-painel-titulo">Doação via PIX</h2>
            <p class="perfil-painel-texto">
              A doação é feita diretamente à ONG, no app do seu banco. O ONGHelp apenas divulga o canal
              e não processa, custodia nem concilia pagamentos.
            </p>

            <label class="perfil-pix-label" for="perfilPixChave">Chave PIX</label>
            <div class="perfil-pix-campo">
              <input type="text" id="perfilPixChave" readonly value="<?= htmlspecialchars($ong['pix_chave']) ?>">
              <button type="button" class="btn btn-outline-secondary btn-sm" data-copiar-pix="perfilPixChave">
                <i class="bi bi-clipboard" aria-hidden="true"></i> Copiar
              </button>
            </div>

            <button type="button" class="btn-pix is-largo mt-3"
                    data-bs-toggle="modal" data-bs-target="#modalDoacao<?= $ong['id'] ?>">
              <i class="bi bi-qr-code" aria-hidden="true"></i> Doar via PIX
            </button>
          </section>
        <?php endif; ?>
      </aside>
    </div>

    <?php endif; ?>

  </div>
</section>

<div class="perfil-toast" id="perfilToast" role="status" aria-live="polite">Chave PIX copiada</div>

<?php if ($ong && !empty($ong['pix_chave'])): ?>
<?php
    // Modal de divulgação de doação via PIX — só quando há canal cadastrado
    $modalUid = $ong['id'];
    $modalOng = ['id' => $ong['id'], 'nome' => $ong['nome'], 'pix_chave' => $ong['pix_chave'] ?? '', 'cidade' => $ong['cidade'] ?? ''];
    include '../includes/modal_doacao.php';
?>
<?php endif; ?>

<?php
$page_script = 'perfilpublico.js';
include '../includes/footer.php';
?>