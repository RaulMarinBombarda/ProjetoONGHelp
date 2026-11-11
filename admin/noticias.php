<?php
// ============================================================
// admin/noticias.php — Moderação de notícias e denúncias
// ============================================================
// A página reúne a fila de denúncias, as publicações e as notícias
// ocultas. Toda decisão passa pelo servidor, exige a capacidade
// noticias.moderar e CSRF. Ocultar não apaga: texto, fotos e
// denúncias permanecem guardados como evidência.

require_once '../includes/admin_auth.php';
require_once '../includes/noticias.php';
require_once '../includes/noticias_fotos.php';

$admin = exigirPermissao($pdo, 'noticias.moderar');
csrfExigir();

// ------------------------------------------------------------
// Utilitários da tela
// ------------------------------------------------------------

function anmEsc(?string $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function anmTempoRelativo(string $data): string
{
    $timestamp = strtotime($data);

    if ($timestamp === false) {
        return '—';
    }

    $diff = max(0, time() - $timestamp);

    if ($diff < 60) {
        return 'agora';
    }

    if ($diff < 3600) {
        return 'há ' . (int) floor($diff / 60) . ' min';
    }

    if ($diff < 86400) {
        return 'há ' . (int) floor($diff / 3600) . ' h';
    }

    if ($diff < 7 * 86400) {
        return 'há ' . (int) floor($diff / 86400) . ' d';
    }

    return fmtData($data);
}

function anmEstadoCaso(int $pendentes, int $procedentes): array
{
    if ($pendentes > 0) {
        return ['chave' => 'pendente', 'rotulo' => 'Pendente', 'classe' => 'status-pendente'];
    }

    if ($procedentes > 0) {
        return ['chave' => 'procedente', 'rotulo' => 'Procedente', 'classe' => 'status-recusada'];
    }

    return ['chave' => 'improcedente', 'rotulo' => 'Improcedente', 'classe' => 'status-suspensa'];
}

function anmEstadoNoticia(string $estado): array
{
    return [
        'publicada' => ['rotulo' => 'Publicada', 'classe' => 'status-aprovada'],
        'oculta' => ['rotulo' => 'Oculta pela equipe', 'classe' => 'status-recusada'],
        'rascunho' => ['rotulo' => 'Rascunho', 'classe' => 'status-pendente'],
    ][$estado] ?? ['rotulo' => ucfirst($estado), 'classe' => 'status-suspensa'];
}

/**
 * Deixa o domínio visível depois de cada link do texto, para a equipe
 * conferir o destino sem sair da análise. O HTML já passou pela
 * sanitização central antes de entrar aqui.
 */
function anmHtmlComDominios(string $html): string
{
    if (trim($html) === '') {
        return '';
    }

    $doc = new DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);
    $carregou = $doc->loadHTML(
        '<?xml encoding="UTF-8"><div id="anm-raiz-texto">' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();

    if (!$carregou) {
        return $html;
    }

    $raiz = $doc->getElementsByTagName('div')->item(0);

    if (!$raiz) {
        return $html;
    }

    foreach ($doc->getElementsByTagName('a') as $link) {
        $host = parse_url((string) $link->getAttribute('href'), PHP_URL_HOST);

        if (!$host || mb_stripos($link->textContent, $host) !== false) {
            continue;
        }

        $dominio = $doc->createElement('span');
        $dominio->setAttribute('class', 'anm-link-domain');
        $dominio->appendChild($doc->createTextNode(' · ' . $host));
        $link->appendChild($dominio);
    }

    $saida = '';

    foreach ($raiz->childNodes as $filho) {
        $saida .= (string) $doc->saveHTML($filho);
    }

    return $saida;
}

// ------------------------------------------------------------
// Parâmetros da tela
// ------------------------------------------------------------

$abasPermitidas = ['denuncias', 'publicacoes', 'ocultas'];
$aba = (string) ($_GET['aba'] ?? 'denuncias');

if (!in_array($aba, $abasPermitidas, true)) {
    $aba = 'denuncias';
}

$busca = trim((string) ($_GET['busca'] ?? ''));
$busca = mb_substr($busca, 0, 120, 'UTF-8');

$motivoFiltro = (string) ($_GET['motivo'] ?? '');
if (!noticiaMotivoValido($motivoFiltro)) {
    $motivoFiltro = '';
}

$tiposNoticia = noticiasTipos();
$tipoFiltro = (string) ($_GET['tipo'] ?? '');
if ($tipoFiltro !== '' && !isset($tiposNoticia[$tipoFiltro])) {
    $tipoFiltro = '';
}

$itabsPermitidas = ['conteudo', 'denuncias', 'historico'];
$itab = (string) ($_GET['itab'] ?? 'conteudo');
if (!in_array($itab, $itabsPermitidas, true)) {
    $itab = 'conteudo';
}

$analisarId = isset($_GET['analisar']) && ctype_digit((string) $_GET['analisar'])
    ? (int) $_GET['analisar']
    : 0;

$decisaoAuto = (string) ($_GET['decisao'] ?? '');
if (!in_array($decisaoAuto, ['ocultar', 'bloquear', 'restaurar'], true)) {
    $decisaoAuto = '';
}

$parametrosPagina = ['aba' => $aba];

if ($busca !== '') {
    $parametrosPagina['busca'] = $busca;
}

if ($aba === 'denuncias' && $motivoFiltro !== '') {
    $parametrosPagina['motivo'] = $motivoFiltro;
}

if ($aba === 'publicacoes' && $tipoFiltro !== '') {
    $parametrosPagina['tipo'] = $tipoFiltro;
}

if ($analisarId > 0) {
    $parametrosPagina['analisar'] = $analisarId;
    $parametrosPagina['itab'] = $itab;
}

$urlPagina = static function (array $extra = []) use ($parametrosPagina): string {
    $query = array_filter(
        array_merge($parametrosPagina, $extra),
        static fn ($valor) => $valor !== '' && $valor !== null
    );

    return 'noticias.php' . ($query ? '?' . http_build_query($query) : '');
};

$urlAtual = $urlPagina();
$parametrosLista = $parametrosPagina;
unset($parametrosLista['analisar'], $parametrosLista['itab']);
$urlLista = 'noticias.php' . ($parametrosLista ? '?' . http_build_query($parametrosLista) : '');

// ------------------------------------------------------------
// Ações de moderação (sempre POST + CSRF)
// ------------------------------------------------------------

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $acao = trim((string) ($_POST['acao'] ?? ''));
    $noticiaId = (int) ($_POST['noticia_id'] ?? 0);

    $voltar = (string) ($_POST['voltar'] ?? 'noticias.php');
    if (!preg_match('/^noticias\.php(?:\?[^\s]*)?$/', $voltar)) {
        $voltar = 'noticias.php';
    }

    $redirecionar = static function (string $destino) use ($voltar): void {
        header('Location: ' . ($destino !== '' ? $destino : $voltar));
        exit;
    };

    $stmtNoticiaAcao = $pdo->prepare(
        'SELECT n.*, u.nome AS ong_nome, u.status AS ong_status
         FROM noticias n
         INNER JOIN usuarios u ON u.id = n.usuario_id
         WHERE n.id = ? LIMIT 1'
    );
    $stmtNoticiaAcao->execute([$noticiaId]);
    $noticiaAcao = $stmtNoticiaAcao->fetch();

    if (!$noticiaAcao) {
        flashSet('admin_erro', 'A notícia não foi encontrada.');
        $redirecionar($urlLista);
    }

    if (!in_array($acao, ['manter_publicada', 'ocultar', 'ocultar_bloquear', 'restaurar'], true)) {
        flashSet('admin_erro', 'Ação de moderação inválida.');
        $redirecionar($voltar);
    }

    try {
        if ($acao === 'manter_publicada') {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                "UPDATE noticias_denuncias
                 SET estado = 'improcedente', tratada_por = ?, tratada_em = NOW()
                 WHERE noticia_id = ? AND estado = 'pendente'"
            );
            $stmt->execute([(int) $admin['id'], $noticiaId]);
            $tratadas = $stmt->rowCount();

            if ($tratadas < 1) {
                $pdo->rollBack();
                flashSet('admin_aviso', 'Não havia denúncias pendentes nesta notícia.');
                $redirecionar($voltar);
            }

            registrarLogAdmin(
                $pdo,
                'denuncia_improcedente',
                'noticia',
                $noticiaId,
                (string) $noticiaAcao['titulo'],
                'ONG: ' . (string) $noticiaAcao['ong_nome'] . ' · '
                    . $tratadas . ' denúncia(s) pendente(s) marcada(s) como improcedente(s).'
            );

            $pdo->commit();
            flashSet('admin_sucesso', 'Denúncia(s) marcada(s) como improcedente(s). A notícia continua publicada.');
            $redirecionar($voltar);
        }

        if ($acao === 'ocultar') {
            $categoria = trim((string) ($_POST['categoria'] ?? ''));
            $mensagem = trim((string) ($_POST['mensagem'] ?? ''));

            if (!noticiaMotivoValido($categoria)) {
                flashSet('admin_erro', 'Selecione a categoria da ocultação.');
                $redirecionar($voltar . (str_contains($voltar, '?') ? '&' : '?') . 'decisao=ocultar');
            }

            $tamanhoMensagem = mb_strlen($mensagem, 'UTF-8');

            if ($tamanhoMensagem < 20) {
                flashSet('admin_erro', 'A mensagem para a ONG precisa ter pelo menos 20 caracteres.');
                $redirecionar($voltar . (str_contains($voltar, '?') ? '&' : '?') . 'decisao=ocultar');
            }

            $mensagem = mb_substr($mensagem, 0, 2000, 'UTF-8');

            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                "UPDATE noticias
                 SET estado = 'oculta',
                     ocultada_em = NOW(),
                     ocultada_por = ?,
                     ocultacao_categoria = ?,
                     ocultacao_mensagem = ?,
                     atualizado_em = NOW()
                 WHERE id = ?"
            );
            $stmt->execute([(int) $admin['id'], $categoria, $mensagem, $noticiaId]);

            $stmt = $pdo->prepare(
                "UPDATE noticias_denuncias
                 SET estado = 'procedente', tratada_por = ?, tratada_em = NOW()
                 WHERE noticia_id = ? AND estado = 'pendente'"
            );
            $stmt->execute([(int) $admin['id'], $noticiaId]);

            registrarLogAdmin(
                $pdo,
                'noticia_ocultada',
                'noticia',
                $noticiaId,
                (string) $noticiaAcao['titulo'],
                'ONG: ' . (string) $noticiaAcao['ong_nome']
                    . ' · Categoria: ' . noticiaRotuloMotivo($categoria)
                    . ' · Mensagem: ' . mb_substr($mensagem, 0, 300, 'UTF-8')
            );

            $pdo->commit();
            flashSet('admin_sucesso', 'Notícia ocultada. A ONG verá a mensagem em Minhas notícias.');
            $redirecionar($voltar);
        }

        if ($acao === 'ocultar_bloquear') {
            $entendo = (string) ($_POST['entendo'] ?? '');
            $motivo = trim((string) ($_POST['motivo'] ?? ''));

            if ($entendo !== '1') {
                flashSet('admin_erro', 'Confirme que entende o bloqueio de publicações da ONG.');
                $redirecionar($voltar . (str_contains($voltar, '?') ? '&' : '?') . 'decisao=bloquear');
            }

            if ($motivo === '') {
                flashSet('admin_erro', 'Informe o motivo do bloqueio.');
                $redirecionar($voltar . (str_contains($voltar, '?') ? '&' : '?') . 'decisao=bloquear');
            }

            $motivo = mb_substr($motivo, 0, 500, 'UTF-8');
            $mensagemOcultacao = mb_substr(
                'Publicações bloqueadas pela equipe: ' . $motivo,
                0,
                2000,
                'UTF-8'
            );

            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                "UPDATE noticias
                 SET estado = 'oculta',
                     ocultada_em = NOW(),
                     ocultada_por = ?,
                     ocultacao_categoria = 'outro',
                     ocultacao_mensagem = ?,
                     atualizado_em = NOW()
                 WHERE id = ?"
            );
            $stmt->execute([(int) $admin['id'], $mensagemOcultacao, $noticiaId]);

            $stmt = $pdo->prepare(
                "UPDATE noticias_denuncias
                 SET estado = 'procedente', tratada_por = ?, tratada_em = NOW()
                 WHERE noticia_id = ? AND estado = 'pendente'"
            );
            $stmt->execute([(int) $admin['id'], $noticiaId]);

            bloquearPublicacoesNoticias(
                $pdo,
                (int) $noticiaAcao['usuario_id'],
                $motivo,
                (int) $admin['id']
            );

            registrarLogAdmin(
                $pdo,
                'noticia_ocultada',
                'noticia',
                $noticiaId,
                (string) $noticiaAcao['titulo'],
                'ONG: ' . (string) $noticiaAcao['ong_nome']
                    . ' · Categoria: Outro · Publicações bloqueadas. Motivo do bloqueio: ' . $motivo
            );

            registrarLogAdmin(
                $pdo,
                'publicacoes_bloqueadas',
                'ong',
                (int) $noticiaAcao['usuario_id'],
                (string) $noticiaAcao['ong_nome'],
                'Notícia: ' . (string) $noticiaAcao['titulo'] . ' · Motivo: ' . $motivo
            );

            $pdo->commit();
            flashSet('admin_sucesso', 'Notícia ocultada e publicações da ONG bloqueadas.');
            $redirecionar($voltar);
        }

        if ($acao === 'restaurar') {
            if ((string) $noticiaAcao['estado'] !== 'oculta') {
                flashSet('admin_erro', 'Somente notícias ocultas podem ser restauradas.');
                $redirecionar($voltar);
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                "UPDATE noticias
                 SET estado = 'publicada',
                     ocultada_em = NULL,
                     ocultada_por = NULL,
                     ocultacao_categoria = NULL,
                     ocultacao_mensagem = NULL,
                     atualizado_em = NOW()
                 WHERE id = ? AND estado = 'oculta'"
            );
            $stmt->execute([$noticiaId]);

            if ($stmt->rowCount() < 1) {
                $pdo->rollBack();
                flashSet('admin_erro', 'Não foi possível restaurar a notícia.');
                $redirecionar($voltar);
            }

            registrarLogAdmin(
                $pdo,
                'noticia_restaurada',
                'noticia',
                $noticiaId,
                (string) $noticiaAcao['titulo'],
                'ONG: ' . (string) $noticiaAcao['ong_nome'] . '.'
            );

            $pdo->commit();
            flashSet('admin_sucesso', 'Notícia restaurada e publicada novamente.');
            $redirecionar($voltar);
        }
    } catch (Throwable $erro) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('[ONGHelp] Moderação de notícias: ' . $erro->getMessage());
        flashSet('admin_erro', 'Não foi possível concluir a ação. Tente novamente.');
        $redirecionar($voltar);
    }
}

// ------------------------------------------------------------
// KPIs
// ------------------------------------------------------------

$kpiPendentes = (int) $pdo
    ->query("SELECT COUNT(*) FROM noticias_denuncias WHERE estado = 'pendente'")
    ->fetchColumn();

$kpiPublicadas7 = (int) $pdo
    ->query("SELECT COUNT(*) FROM noticias
             WHERE estado = 'publicada'
               AND publicada_em >= NOW() - INTERVAL 7 DAY")
    ->fetchColumn();

$kpiOcultas = (int) $pdo
    ->query("SELECT COUNT(*) FROM noticias WHERE estado = 'oculta'")
    ->fetchColumn();

$kpiBloqueadas = (int) $pdo
    ->query('SELECT COUNT(DISTINCT usuario_id) FROM noticias_bloqueios WHERE ativo = 1')
    ->fetchColumn();

// ------------------------------------------------------------
// Listas por aba
// ------------------------------------------------------------

$denuncias = [];
$publicacoes = [];
$ocultas = [];

if ($aba === 'denuncias') {
    $where = ['1 = 1'];
    $params = [];

    if ($busca !== '') {
        $where[] = '(n.titulo LIKE ? OR u.nome LIKE ?)';
        $params[] = '%' . $busca . '%';
        $params[] = '%' . $busca . '%';
    }

    if ($motivoFiltro !== '') {
        $where[] = 'EXISTS (
            SELECT 1 FROM noticias_denuncias dm
            WHERE dm.noticia_id = n.id AND dm.motivo = ?
        )';
        $params[] = $motivoFiltro;
    }

    $stmt = $pdo->prepare(
        "SELECT n.id, n.titulo, n.estado, n.slug, n.usuario_id,
                n.publicada_em, n.criado_em, n.atualizado_em,
                u.nome AS ong_nome, u.foto_perfil, u.area,
                u.status AS ong_status, u.verificada_em AS ong_verificada_em,
                x.total, x.pendentes, x.procedentes, x.ultima,
                (SELECT f.arquivo FROM noticias_fotos f
                  WHERE f.noticia_id = n.id ORDER BY f.ordem, f.id LIMIT 1) AS capa_miniatura,
                (SELECT dx.motivo FROM noticias_denuncias dx
                  WHERE dx.noticia_id = n.id
                  GROUP BY dx.motivo
                  ORDER BY COUNT(*) DESC, MAX(dx.criado_em) DESC
                  LIMIT 1) AS motivo_frequente
         FROM noticias n
         INNER JOIN usuarios u ON u.id = n.usuario_id
         INNER JOIN (
             SELECT noticia_id, COUNT(*) AS total,
                    SUM(estado = 'pendente') AS pendentes,
                    SUM(estado = 'procedente') AS procedentes,
                    MAX(criado_em) AS ultima
             FROM noticias_denuncias
             GROUP BY noticia_id
         ) x ON x.noticia_id = n.id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY x.pendentes DESC, x.total DESC, x.ultima DESC, n.id DESC
         LIMIT 100"
    );
    $stmt->execute($params);
    $denuncias = $stmt->fetchAll();
}

if ($aba === 'publicacoes') {
    $where = ["n.estado = 'publicada'"];
    $params = [];

    if ($busca !== '') {
        $where[] = '(n.titulo LIKE ? OR u.nome LIKE ?)';
        $params[] = '%' . $busca . '%';
        $params[] = '%' . $busca . '%';
    }

    if ($tipoFiltro !== '') {
        $where[] = 'n.tipo = ?';
        $params[] = $tipoFiltro;
    }

    $stmt = $pdo->prepare(
        "SELECT n.id, n.titulo, n.tipo, n.slug, n.usuario_id,
                n.publicada_em, n.criado_em,
                u.nome AS ong_nome, u.foto_perfil, u.area,
                u.status AS ong_status, u.verificada_em AS ong_verificada_em,
                (SELECT f.arquivo FROM noticias_fotos f
                  WHERE f.noticia_id = n.id ORDER BY f.ordem, f.id LIMIT 1) AS capa_miniatura,
                (SELECT COUNT(*) FROM noticias_denuncias d
                  WHERE d.noticia_id = n.id) AS total_denuncias
         FROM noticias n
         INNER JOIN usuarios u ON u.id = n.usuario_id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY n.publicada_em DESC, n.id DESC
         LIMIT 100"
    );
    $stmt->execute($params);
    $publicacoes = $stmt->fetchAll();
}

if ($aba === 'ocultas') {
    $where = ["n.estado = 'oculta'"];
    $params = [];

    if ($busca !== '') {
        $where[] = '(n.titulo LIKE ? OR u.nome LIKE ?)';
        $params[] = '%' . $busca . '%';
        $params[] = '%' . $busca . '%';
    }

    $stmt = $pdo->prepare(
        "SELECT n.id, n.titulo, n.tipo, n.slug, n.usuario_id,
                n.publicada_em, n.ocultada_em,
                n.ocultacao_categoria, n.ocultacao_mensagem,
                u.nome AS ong_nome, u.foto_perfil, u.area,
                u.status AS ong_status, u.verificada_em AS ong_verificada_em,
                a.nome AS ocultada_por_nome,
                (SELECT f.arquivo FROM noticias_fotos f
                  WHERE f.noticia_id = n.id ORDER BY f.ordem, f.id LIMIT 1) AS capa_miniatura,
                (SELECT COUNT(*) FROM noticias_denuncias d
                  WHERE d.noticia_id = n.id) AS total_denuncias
         FROM noticias n
         INNER JOIN usuarios u ON u.id = n.usuario_id
         LEFT JOIN admins a ON a.id = n.ocultada_por
         WHERE " . implode(' AND ', $where) . "
         ORDER BY n.ocultada_em DESC, n.id DESC
         LIMIT 100"
    );
    $stmt->execute($params);
    $ocultas = $stmt->fetchAll();
}

// ------------------------------------------------------------
// Drawer de análise
// ------------------------------------------------------------

$noticiaAberta = null;
$fotosAberta = [];
$linksAberta = [];
$denunciasAberta = [];
$historicoAberta = [];
$projetoAberta = null;
$movimentacaoAberta = null;
$textoAberta = '';
$textoAbertaComDominios = '';
$totalPendentesAberta = 0;

if ($analisarId > 0) {
    $stmt = $pdo->prepare(
        "SELECT n.*, u.nome AS ong_nome, u.foto_perfil, u.area,
                u.status AS ong_status, u.verificada_em AS ong_verificada_em,
                u.cidade AS ong_cidade, u.estado AS ong_estado,
                a.nome AS ocultada_por_nome
         FROM noticias n
         INNER JOIN usuarios u ON u.id = n.usuario_id
         LEFT JOIN admins a ON a.id = n.ocultada_por
         WHERE n.id = ? LIMIT 1"
    );
    $stmt->execute([$analisarId]);
    $noticiaAberta = $stmt->fetch() ?: null;

    if (!$noticiaAberta) {
        flashSet('admin_erro', 'A notícia não foi encontrada.');
        header('Location: ' . $urlLista);
        exit;
    }

    $stmt = $pdo->prepare(
        'SELECT * FROM noticias_fotos WHERE noticia_id = ? ORDER BY ordem, id'
    );
    $stmt->execute([$analisarId]);
    foreach ($stmt->fetchAll() as $foto) {
        $fotosAberta[] = noticiaFotoBloco($foto, '../');
    }

    $stmt = $pdo->prepare(
        'SELECT url, dominio, servico FROM noticias_links
         WHERE noticia_id = ? ORDER BY ordem, id'
    );
    $stmt->execute([$analisarId]);
    $linksAberta = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        "SELECT d.*, a.nome AS tratada_por_nome
         FROM noticias_denuncias d
         LEFT JOIN admins a ON a.id = d.tratada_por
         WHERE d.noticia_id = ?
         ORDER BY CASE d.estado WHEN 'pendente' THEN 0 WHEN 'procedente' THEN 1 ELSE 2 END,
                  d.criado_em DESC, d.id DESC"
    );
    $stmt->execute([$analisarId]);
    $denunciasAberta = $stmt->fetchAll();

    foreach ($denunciasAberta as $denuncia) {
        if ((string) $denuncia['estado'] === 'pendente') {
            $totalPendentesAberta++;
        }
    }

    $stmt = $pdo->prepare(
        "SELECT l.*, a.nome AS admin_nome, a.papel AS admin_papel
         FROM logs_admin l
         LEFT JOIN admins a ON a.id = l.admin_id
         WHERE l.alvo_tipo = 'noticia' AND l.alvo_id = ?
         ORDER BY l.criado_em DESC, l.id DESC
         LIMIT 40"
    );
    $stmt->execute([$analisarId]);
    $historicoAberta = $stmt->fetchAll();

    if (!empty($noticiaAberta['projeto_id'])) {
        $stmt = $pdo->prepare(
            'SELECT nome, indicador, unidade FROM projetos
             WHERE id = ? AND usuario_id = ? LIMIT 1'
        );
        $stmt->execute([
            (int) $noticiaAberta['projeto_id'],
            (int) $noticiaAberta['usuario_id'],
        ]);
        $projetoAberta = $stmt->fetch() ?: null;
    }

    if (!empty($noticiaAberta['movimentacao_id'])) {
        $stmt = $pdo->prepare(
            'SELECT tipo, descricao, valor, data_mov FROM movimentacoes
             WHERE id = ? AND usuario_id = ? LIMIT 1'
        );
        $stmt->execute([
            (int) $noticiaAberta['movimentacao_id'],
            (int) $noticiaAberta['usuario_id'],
        ]);
        $movimentacaoAberta = $stmt->fetch() ?: null;
    }

    $textoAberta = sanitizarHtmlNoticia((string) $noticiaAberta['texto']);
    $textoAbertaComDominios = anmHtmlComDominios($textoAberta);

    // O modal automático só abre se a decisão fizer sentido no estado atual.
    if ($decisaoAuto === 'restaurar' && (string) $noticiaAberta['estado'] !== 'oculta') {
        $decisaoAuto = '';
    } elseif (in_array($decisaoAuto, ['ocultar', 'bloquear'], true)
        && (string) $noticiaAberta['estado'] === 'oculta') {
        $decisaoAuto = '';
    }
}

$itensAba = match ($aba) {
    'publicacoes' => $publicacoes,
    'ocultas' => $ocultas,
    default => $denuncias,
};

$rotulosAcao = rotulosAcaoAdmin();
$nomesMotivos = noticiasMotivosDenuncia();
$estadoAberto = $noticiaAberta
    ? anmEstadoNoticia((string) $noticiaAberta['estado'])
    : ['rotulo' => '', 'classe' => ''];
$tituloExibicao = $noticiaAberta
    ? trim((string) $noticiaAberta['titulo'])
    : '';

if ($tituloExibicao === '') {
    $tituloExibicao = 'Notícia sem título';
}

$admin_page_title = 'Notícias';
$admin_page_subtitle = 'Denúncias e publicações das ONGs.';
$admin_active = 'noticias';
$admin_script = 'admin-noticias.js';

include '../includes/admin_header.php';
?>

<div class="admin-noticias">

  <div class="admin-kpis">
    <div class="admin-kpi admin-kpi-pendente <?= $kpiPendentes > 0 ? 'admin-kpi-alerta' : '' ?>">
      <span class="admin-kpi-icon"><i class="bi bi-megaphone" aria-hidden="true"></i></span>
      <span class="admin-kpi-texto">
        <span class="admin-kpi-valor"><?= $kpiPendentes ?></span>
        <span class="admin-kpi-rotulo">Denúncias pendentes</span>
      </span>
    </div>

    <div class="admin-kpi admin-kpi-aprovada">
      <span class="admin-kpi-icon"><i class="bi bi-send-check" aria-hidden="true"></i></span>
      <span class="admin-kpi-texto">
        <span class="admin-kpi-valor"><?= $kpiPublicadas7 ?></span>
        <span class="admin-kpi-rotulo">Publicadas nos últimos 7 dias</span>
      </span>
    </div>

    <div class="admin-kpi admin-kpi-suspensa">
      <span class="admin-kpi-icon"><i class="bi bi-eye-slash" aria-hidden="true"></i></span>
      <span class="admin-kpi-texto">
        <span class="admin-kpi-valor"><?= $kpiOcultas ?></span>
        <span class="admin-kpi-rotulo">Ocultas</span>
      </span>
    </div>

    <div class="admin-kpi anm-kpi-bloqueio">
      <span class="admin-kpi-icon"><i class="bi bi-slash-circle" aria-hidden="true"></i></span>
      <span class="admin-kpi-texto">
        <span class="admin-kpi-valor"><?= $kpiBloqueadas ?></span>
        <span class="admin-kpi-rotulo">ONGs com publicação bloqueada</span>
      </span>
    </div>
  </div>

  <nav class="admin-segmentado anm-segmentado" aria-label="Seções de notícias">
    <a href="<?= anmEsc($urlPagina(['aba' => 'denuncias', 'tipo' => null, 'analisar' => null, 'itab' => null])) ?>"
       class="admin-segmento <?= $aba === 'denuncias' ? 'is-active' : '' ?>"
       <?= $aba === 'denuncias' ? 'aria-current="page"' : '' ?>>Denúncias</a>
    <a href="<?= anmEsc($urlPagina(['aba' => 'publicacoes', 'motivo' => null, 'analisar' => null, 'itab' => null])) ?>"
       class="admin-segmento <?= $aba === 'publicacoes' ? 'is-active' : '' ?>"
       <?= $aba === 'publicacoes' ? 'aria-current="page"' : '' ?>>Publicações</a>
    <a href="<?= anmEsc($urlPagina(['aba' => 'ocultas', 'motivo' => null, 'tipo' => null, 'analisar' => null, 'itab' => null])) ?>"
       class="admin-segmento <?= $aba === 'ocultas' ? 'is-active' : '' ?>"
       <?= $aba === 'ocultas' ? 'aria-current="page"' : '' ?>>Ocultas</a>
  </nav>

  <section class="admin-card anm-filtros-card" aria-label="Filtros de notícias">
    <form method="GET" action="noticias.php" class="anm-filtros" role="search">
      <input type="hidden" name="aba" value="<?= anmEsc($aba) ?>">

      <div class="anm-campo anm-campo-busca">
        <label class="admin-label" for="anmBusca">Buscar</label>
        <div class="input-group admin-busca">
          <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
          <input type="search" name="busca" id="anmBusca" class="form-control"
                 placeholder="Título da notícia ou nome da ONG"
                 value="<?= anmEsc($busca) ?>" aria-label="Buscar por título ou ONG">
          <button type="submit" class="btn btn-primary">Buscar</button>
        </div>
      </div>

      <?php if ($aba === 'denuncias'): ?>
        <div class="anm-campo">
          <label class="admin-label" for="anmMotivo">Motivo</label>
          <select name="motivo" id="anmMotivo" class="form-select">
            <option value="">Todos os motivos</option>
            <?php foreach ($nomesMotivos as $chave => $rotulo): ?>
              <option value="<?= anmEsc($chave) ?>" <?= $motivoFiltro === $chave ? 'selected' : '' ?>>
                <?= anmEsc($rotulo) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php elseif ($aba === 'publicacoes'): ?>
        <div class="anm-campo">
          <label class="admin-label" for="anmTipo">Tipo</label>
          <select name="tipo" id="anmTipo" class="form-select">
            <option value="">Todos os tipos</option>
            <?php foreach ($tiposNoticia as $chave => $rotulo): ?>
              <option value="<?= anmEsc($chave) ?>" <?= $tipoFiltro === $chave ? 'selected' : '' ?>>
                <?= anmEsc($rotulo) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>

      <div class="anm-filtros-acoes">
        <button type="submit" class="btn btn-primary anm-filtrar">
          <i class="bi bi-funnel" aria-hidden="true"></i> Filtrar
        </button>
        <?php if ($busca !== '' || $motivoFiltro !== '' || $tipoFiltro !== ''): ?>
          <a href="<?= anmEsc($urlPagina(['busca' => null, 'motivo' => null, 'tipo' => null])) ?>"
             class="admin-link-terciario anm-limpar">Limpar</a>
        <?php endif; ?>
      </div>
    </form>
  </section>

  <section class="admin-card anm-lista-card">
    <div class="anm-lista-head">
      <div>
        <h2 class="admin-card-title">
          <?= $aba === 'denuncias' ? 'Fila de denúncias' : ($aba === 'publicacoes' ? 'Publicações' : 'Notícias ocultas') ?>
        </h2>
        <p class="admin-card-subtitle">
          <?= $aba === 'denuncias'
              ? 'Casos abertos pela comunidade. Os pendentes aparecem primeiro.'
              : ($aba === 'publicacoes'
                  ? 'Tudo o que está no ar. Abra a análise para ocultar uma notícia.'
                  : 'Conteúdo preservado como evidência. Restaurar volta a publicar.') ?>
        </p>
      </div>
      <span class="admin-count" aria-label="<?= count($itensAba) ?> item(ns)"><?= count($itensAba) ?></span>
    </div>

    <?php if ($aba === 'denuncias'): ?>
      <?php if (empty($denuncias)): ?>
        <div class="admin-vazio">
          <i class="bi bi-megaphone" aria-hidden="true"></i>
          <strong><?= $busca !== '' || $motivoFiltro !== '' ? 'Nenhuma denúncia encontrada' : 'Nenhuma denúncia pendente — tudo em dia' ?></strong>
          <span><?= $busca !== '' || $motivoFiltro !== '' ? 'Ajuste a busca ou o motivo para ver outros casos.' : 'Novas denúncias da comunidade aparecem aqui.' ?></span>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table admin-table anm-table align-middle mb-0">
            <thead>
              <tr>
                <th>Notícia</th>
                <th>Motivo</th>
                <th>Denúncias</th>
                <th>Estado</th>
                <th class="text-end">Ação</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($denuncias as $item): ?>
                <?php
                $caso = anmEstadoCaso((int) $item['pendentes'], (int) $item['procedentes']);
                $pendente = $caso['chave'] === 'pendente';
                $capa = noticiaFotoUrl((string) ($item['capa_miniatura'] ?? ''), '../');
                $linkAnalisar = $urlPagina([
                    'analisar' => (int) $item['id'],
                    'itab' => 'denuncias',
                    'aba' => 'denuncias',
                ]);
                ?>
                <tr class="anm-row <?= $pendente ? 'is-pendente' : '' ?>">
                  <td>
                    <div class="anm-news">
                      <a class="anm-thumb" href="<?= anmEsc($linkAnalisar) ?>" tabindex="-1" aria-hidden="true">
                        <?php if ($capa !== ''): ?>
                          <img src="<?= anmEsc($capa) ?>" alt="" loading="lazy">
                        <?php else: ?>
                          <span class="anm-thumb-vazia"><i class="bi bi-image"></i></span>
                        <?php endif; ?>
                      </a>
                      <div class="anm-news-copy">
                        <a class="anm-title" href="<?= anmEsc($linkAnalisar) ?>"><?= anmEsc($item['titulo']) ?></a>
                        <span class="anm-ong">
                          <i class="bi bi-building" aria-hidden="true"></i>
                          <?= anmEsc($item['ong_nome']) ?>
                        </span>
                      </div>
                    </div>
                  </td>
                  <td>
                    <span class="anm-motivo-chip">
                      <?= anmEsc(noticiaRotuloMotivo((string) ($item['motivo_frequente'] ?? ''))) ?>
                    </span>
                  </td>
                  <td>
                    <span class="anm-denuncias">
                      <strong><?= (int) $item['total'] ?></strong>
                      <small>última <?= anmEsc(anmTempoRelativo((string) $item['ultima'])) ?></small>
                    </span>
                  </td>
                  <td>
                    <span class="admin-status <?= anmEsc($caso['classe']) ?>"><?= anmEsc($caso['rotulo']) ?></span>
                  </td>
                  <td class="text-end">
                    <a href="<?= anmEsc($linkAnalisar) ?>"
                       class="btn btn-sm <?= $pendente ? 'btn-primary' : 'btn-outline-secondary' ?>">
                      Analisar
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($aba === 'publicacoes'): ?>
      <?php if (empty($publicacoes)): ?>
        <div class="admin-vazio">
          <i class="bi bi-newspaper" aria-hidden="true"></i>
          <strong><?= $busca !== '' || $tipoFiltro !== '' ? 'Nenhuma publicação encontrada' : 'Nenhuma notícia publicada' ?></strong>
          <span><?= $busca !== '' || $tipoFiltro !== '' ? 'Ajuste a busca ou o tipo para ver outras publicações.' : 'As notícias publicadas pelas ONGs aparecem aqui.' ?></span>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table admin-table anm-table align-middle mb-0">
            <thead>
              <tr>
                <th>Notícia</th>
                <th>ONG</th>
                <th>Publicada em</th>
                <th>Denúncias</th>
                <th>Estado</th>
                <th class="text-end"><span class="visually-hidden">Ações</span></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($publicacoes as $item): ?>
                <?php
                $capa = noticiaFotoUrl((string) ($item['capa_miniatura'] ?? ''), '../');
                $linkVer = $urlPagina([
                    'analisar' => (int) $item['id'],
                    'itab' => 'conteudo',
                    'aba' => 'publicacoes',
                ]);
                $linkOcultar = $urlPagina([
                    'analisar' => (int) $item['id'],
                    'itab' => 'denuncias',
                    'aba' => 'publicacoes',
                    'decisao' => 'ocultar',
                ]);
                $avatar = fotoPerfilUrl(
                    (string) ($item['foto_perfil'] ?? ''),
                    '../',
                    (string) ($item['area'] ?? '')
                );
                ?>
                <tr class="anm-row">
                  <td>
                    <div class="anm-news">
                      <a class="anm-thumb" href="<?= anmEsc($linkVer) ?>" tabindex="-1" aria-hidden="true">
                        <?php if ($capa !== ''): ?>
                          <img src="<?= anmEsc($capa) ?>" alt="" loading="lazy">
                        <?php else: ?>
                          <span class="anm-thumb-vazia"><i class="bi bi-image"></i></span>
                        <?php endif; ?>
                      </a>
                      <div class="anm-news-copy">
                        <a class="anm-title" href="<?= anmEsc($linkVer) ?>"><?= anmEsc($item['titulo']) ?></a>
                        <span class="anm-tipo"><?= anmEsc(noticiaRotuloTipo((string) $item['tipo'])) ?></span>
                      </div>
                    </div>
                  </td>
                  <td>
                    <div class="anm-ong-celula">
                      <img src="<?= anmEsc($avatar) ?>" alt="" width="32" height="32" loading="lazy">
                      <span><?= anmEsc($item['ong_nome']) ?></span>
                    </div>
                  </td>
                  <td class="anm-data"><?= anmEsc(fmtData((string) ($item['publicada_em'] ?: $item['criado_em']))) ?></td>
                  <td>
                    <span class="anm-numero">(<?= (int) $item['total_denuncias'] ?>)</span>
                  </td>
                  <td><span class="admin-status status-aprovada">Publicada</span></td>
                  <td class="text-end">
                    <div class="dropdown anm-menu">
                      <button class="anm-menu-btn" type="button" data-bs-toggle="dropdown"
                              aria-expanded="false"
                              aria-label="Ações da notícia <?= anmEsc($item['titulo']) ?>">
                        <i class="bi bi-three-dots" aria-hidden="true"></i>
                      </button>
                      <ul class="dropdown-menu dropdown-menu-end admin-row-menu">
                        <li>
                          <a class="dropdown-item" href="<?= anmEsc($linkVer) ?>">
                            <i class="bi bi-eye" aria-hidden="true"></i> Ver
                          </a>
                        </li>
                        <li>
                          <a class="dropdown-item is-danger" href="<?= anmEsc($linkOcultar) ?>">
                            <i class="bi bi-eye-slash" aria-hidden="true"></i> Ocultar
                          </a>
                        </li>
                      </ul>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($aba === 'ocultas'): ?>
      <?php if (empty($ocultas)): ?>
        <div class="admin-vazio">
          <i class="bi bi-eye-slash" aria-hidden="true"></i>
          <strong><?= $busca !== '' ? 'Nenhuma notícia oculta encontrada' : 'Nenhuma notícia oculta — tudo em dia' ?></strong>
          <span><?= $busca !== '' ? 'Tente outro título ou nome de ONG.' : 'Conteúdos ocultados pela equipe aparecem aqui para revisão.' ?></span>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table admin-table anm-table align-middle mb-0">
            <thead>
              <tr>
                <th>Notícia</th>
                <th>ONG</th>
                <th>Ocultada por</th>
                <th>Em</th>
                <th>Motivo</th>
                <th class="text-end"><span class="visually-hidden">Ações</span></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($ocultas as $item): ?>
                <?php
                $capa = noticiaFotoUrl((string) ($item['capa_miniatura'] ?? ''), '../');
                $linkVer = $urlPagina([
                    'analisar' => (int) $item['id'],
                    'itab' => 'conteudo',
                    'aba' => 'ocultas',
                ]);
                $linkRestaurar = $urlPagina([
                    'analisar' => (int) $item['id'],
                    'itab' => 'historico',
                    'aba' => 'ocultas',
                    'decisao' => 'restaurar',
                ]);
                $avatar = fotoPerfilUrl(
                    (string) ($item['foto_perfil'] ?? ''),
                    '../',
                    (string) ($item['area'] ?? '')
                );
                $motivoOcultacao = noticiaRotuloMotivo((string) ($item['ocultacao_categoria'] ?? ''));
                ?>
                <tr class="anm-row">
                  <td>
                    <div class="anm-news">
                      <a class="anm-thumb is-oculta" href="<?= anmEsc($linkVer) ?>" tabindex="-1" aria-hidden="true">
                        <?php if ($capa !== ''): ?>
                          <img src="<?= anmEsc($capa) ?>" alt="" loading="lazy">
                        <?php else: ?>
                          <span class="anm-thumb-vazia"><i class="bi bi-image"></i></span>
                        <?php endif; ?>
                      </a>
                      <div class="anm-news-copy">
                        <a class="anm-title" href="<?= anmEsc($linkVer) ?>"><?= anmEsc($item['titulo']) ?></a>
                        <span class="anm-tipo"><?= anmEsc(noticiaRotuloTipo((string) $item['tipo'])) ?></span>
                      </div>
                    </div>
                  </td>
                  <td>
                    <div class="anm-ong-celula">
                      <img src="<?= anmEsc($avatar) ?>" alt="" width="32" height="32" loading="lazy">
                      <span><?= anmEsc($item['ong_nome']) ?></span>
                    </div>
                  </td>
                  <td class="anm-data"><?= anmEsc($item['ocultada_por_nome'] ?: 'Equipe ONGHelp') ?></td>
                  <td class="anm-data"><?= anmEsc(fmtDataHora((string) ($item['ocultada_em'] ?? ''))) ?></td>
                  <td>
                    <span class="anm-motivo-chip" title="<?= anmEsc((string) ($item['ocultacao_mensagem'] ?? '')) ?>">
                      <?= anmEsc($motivoOcultacao !== '' ? $motivoOcultacao : 'Outro') ?>
                    </span>
                  </td>
                  <td class="text-end">
                    <div class="dropdown anm-menu">
                      <button class="anm-menu-btn" type="button" data-bs-toggle="dropdown"
                              aria-expanded="false"
                              aria-label="Ações da notícia <?= anmEsc($item['titulo']) ?>">
                        <i class="bi bi-three-dots" aria-hidden="true"></i>
                      </button>
                      <ul class="dropdown-menu dropdown-menu-end admin-row-menu">
                        <li>
                          <a class="dropdown-item" href="<?= anmEsc($linkVer) ?>">
                            <i class="bi bi-eye" aria-hidden="true"></i> Ver
                          </a>
                        </li>
                        <li>
                          <a class="dropdown-item" href="<?= anmEsc($linkRestaurar) ?>">
                            <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Restaurar
                          </a>
                        </li>
                      </ul>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </section>
</div>

<?php if ($noticiaAberta): ?>
  <?php
  $avatarAberto = fotoPerfilUrl(
      (string) ($noticiaAberta['foto_perfil'] ?? ''),
      '../',
      (string) ($noticiaAberta['area'] ?? '')
  );
  $seloAberto = badgeVerificado([
      'status' => (string) ($noticiaAberta['ong_status'] ?? ''),
      'verificada_em' => $noticiaAberta['ong_verificada_em'] ?? null,
  ], true);
  $dataAberta = (string) (
      $noticiaAberta['publicada_em']
      ?: ($noticiaAberta['criado_em'] ?? '')
  );
  $localOng = trim((string) ($noticiaAberta['ong_cidade'] ?? ''));
  if (trim((string) ($noticiaAberta['ong_estado'] ?? '')) !== '') {
      $localOng .= ($localOng !== '' ? ', ' : '') . (string) $noticiaAberta['ong_estado'];
  }
  $temContasAberta = $projetoAberta !== null || $movimentacaoAberta !== null;
  $rotulosDenuncia = [
      'pendente' => ['rotulo' => 'Pendente', 'classe' => 'status-pendente'],
      'improcedente' => ['rotulo' => 'Improcedente', 'classe' => 'status-suspensa'],
      'procedente' => ['rotulo' => 'Procedente', 'classe' => 'status-recusada'],
  ];
  ?>

  <div class="offcanvas offcanvas-start anm-drawer" tabindex="-1" id="anmDrawer"
       aria-labelledby="anmDrawerLabel"
       data-anm-auto-modal="<?= anmEsc($decisaoAuto) ?>">
    <div class="offcanvas-header anm-drawer-head">
      <div class="anm-drawer-head-main">
        <span class="anm-drawer-kicker">Análise da notícia</span>
        <h2 class="offcanvas-title anm-drawer-title" id="anmDrawerLabel"><?= anmEsc($tituloExibicao) ?></h2>

        <div class="anm-drawer-org">
          <img src="<?= anmEsc($avatarAberto) ?>" alt="" width="32" height="32" loading="lazy">
          <span class="anm-drawer-org-nome">
            <?= anmEsc($noticiaAberta['ong_nome']) ?>
            <?= $seloAberto ?>
          </span>
          <?php if ($localOng !== ''): ?>
            <span class="anm-drawer-org-local"><?= anmEsc($localOng) ?></span>
          <?php endif; ?>
          <span class="anm-drawer-org-data"><?= anmEsc(fmtDataHora($dataAberta)) ?></span>
        </div>
      </div>

      <div class="anm-drawer-head-side">
        <span class="admin-status <?= anmEsc($estadoAberto['classe']) ?>"><?= anmEsc($estadoAberto['rotulo']) ?></span>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fechar análise"></button>
      </div>
    </div>

    <div class="anm-drawer-body">
      <nav class="admin-segmentado anm-itsection" aria-label="Seções da análise">
        <a href="<?= anmEsc($urlPagina(['analisar' => (int) $noticiaAberta['id'], 'itab' => 'conteudo'])) ?>"
           class="admin-segmento <?= $itab === 'conteudo' ? 'is-active' : '' ?>"
           <?= $itab === 'conteudo' ? 'aria-current="page"' : '' ?>>Conteúdo</a>
        <a href="<?= anmEsc($urlPagina(['analisar' => (int) $noticiaAberta['id'], 'itab' => 'denuncias'])) ?>"
           class="admin-segmento <?= $itab === 'denuncias' ? 'is-active' : '' ?>"
           <?= $itab === 'denuncias' ? 'aria-current="page"' : '' ?>>
          Denúncias<?= $totalPendentesAberta > 0 ? ' (' . $totalPendentesAberta . ')' : '' ?>
        </a>
        <a href="<?= anmEsc($urlPagina(['analisar' => (int) $noticiaAberta['id'], 'itab' => 'historico'])) ?>"
           class="admin-segmento <?= $itab === 'historico' ? 'is-active' : '' ?>"
           <?= $itab === 'historico' ? 'aria-current="page"' : '' ?>>Histórico</a>
      </nav>

      <?php if ($itab === 'conteudo'): ?>
        <div class="anm-gallery" data-anm-gallery>
          <?php if ($fotosAberta): ?>
            <div class="anm-gallery-stage">
              <?php foreach ($fotosAberta as $indice => $foto): ?>
                <?php
                $srcFoto = (string) $foto['url'];
                $legendaFoto = trim((string) ($foto['legenda'] ?? ''));
                $altFoto = trim((string) ($foto['texto_alternativo'] ?? ''));
                if ($altFoto === '') {
                    $altFoto = $tituloExibicao;
                }
                ?>
                <figure class="anm-gallery-slide <?= $indice === 0 ? 'is-active' : '' ?>" data-anm-slide>
                  <img src="<?= anmEsc($srcFoto) ?>" alt="<?= anmEsc($altFoto) ?>"
                       <?= $indice === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?>>
                  <?php if ($legendaFoto !== ''): ?>
                    <figcaption><?= anmEsc($legendaFoto) ?></figcaption>
                  <?php endif; ?>
                </figure>
              <?php endforeach; ?>

              <?php if (count($fotosAberta) > 1): ?>
                <button type="button" class="anm-gallery-nav is-prev" data-anm-prev aria-label="Foto anterior">
                  <i class="bi bi-chevron-left" aria-hidden="true"></i>
                </button>
                <button type="button" class="anm-gallery-nav is-next" data-anm-next aria-label="Próxima foto">
                  <i class="bi bi-chevron-right" aria-hidden="true"></i>
                </button>
              <?php endif; ?>

              <span class="anm-gallery-count" data-anm-count>1 / <?= count($fotosAberta) ?></span>
            </div>

            <?php if (count($fotosAberta) > 1): ?>
              <div class="anm-gallery-thumbs" aria-label="Miniaturas das fotos">
                <?php foreach ($fotosAberta as $indice => $foto): ?>
                  <button type="button" class="anm-gallery-thumb <?= $indice === 0 ? 'is-active' : '' ?>"
                          data-anm-thumb="<?= $indice ?>"
                          aria-label="Ver foto <?= $indice + 1 ?>"
                          aria-pressed="<?= $indice === 0 ? 'true' : 'false' ?>">
                    <img src="<?= anmEsc((string) $foto['url']) ?>" alt="" loading="lazy">
                  </button>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          <?php else: ?>
            <div class="anm-gallery-vazia">
              <i class="bi bi-images" aria-hidden="true"></i>
              <span>Esta notícia não tem fotos.</span>
            </div>
          <?php endif; ?>
        </div>

        <div class="anm-resumo"><?= anmEsc((string) $noticiaAberta['resumo']) ?></div>
        <div class="anm-prose"><?= $textoAbertaComDominios ?></div>

        <?php if ($linksAberta): ?>
          <section class="anm-secao" aria-labelledby="anmOndeAcompanhar">
            <h3 class="anm-secao-titulo" id="anmOndeAcompanhar">
              <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> Onde acompanhar
            </h3>
            <div class="anm-links">
              <?php foreach ($linksAberta as $link): ?>
                <a class="anm-link-pill" href="<?= anmEsc((string) $link['url']) ?>"
                   target="_blank" rel="noopener noreferrer">
                  <i class="bi bi-globe2" aria-hidden="true"></i>
                  <span><?= anmEsc((string) $link['dominio']) ?></span>
                </a>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endif; ?>

        <?php if ($temContasAberta): ?>
          <section class="anm-secao" aria-labelledby="anmNasContas">
            <h3 class="anm-secao-titulo" id="anmNasContas">
              <i class="bi bi-receipt" aria-hidden="true"></i> Nas contas
            </h3>
            <div class="anm-accounts">
              <?php if ($movimentacaoAberta): ?>
                <?php
                $ehReceita = (string) $movimentacaoAberta['tipo'] === 'receita';
                $valorMov = (float) $movimentacaoAberta['valor'];
                ?>
                <div class="anm-account-row">
                  <span class="anm-account-icon <?= $ehReceita ? 'is-pos' : 'is-neg' ?>">
                    <i class="bi <?= $ehReceita ? 'bi-arrow-down-left' : 'bi-arrow-up-right' ?>" aria-hidden="true"></i>
                  </span>
                  <span class="anm-account-copy">
                    <strong><?= anmEsc((string) $movimentacaoAberta['descricao']) ?></strong>
                    <small><?= anmEsc(fmtData((string) $movimentacaoAberta['data_mov'])) ?> · <?= $ehReceita ? 'Receita' : 'Despesa' ?></small>
                  </span>
                  <span class="anm-account-value <?= $ehReceita ? 'is-pos' : 'is-neg' ?>">
                    <?= $ehReceita ? '+' : '−' ?> <?= anmEsc(fmtDinheiro(abs($valorMov))) ?>
                  </span>
                </div>
              <?php endif; ?>

              <?php if ($projetoAberta): ?>
                <div class="anm-account-project">
                  <strong><?= anmEsc(number_format((float) $projetoAberta['indicador'], 0, ',', '.')) ?></strong>
                  <span><?= anmEsc((string) $projetoAberta['unidade']) ?> · <?= anmEsc((string) $projetoAberta['nome']) ?></span>
                </div>
              <?php endif; ?>

              <a class="anm-account-link" href="../pages/transparencia.php?ong=<?= (int) $noticiaAberta['usuario_id'] ?>"
                 target="_blank" rel="noopener">
                Ver relatório da ONG <span aria-hidden="true">→</span>
              </a>
            </div>
          </section>
        <?php endif; ?>
      <?php endif; ?>

      <?php if ($itab === 'denuncias'): ?>
        <p class="anm-nota-equipe">
          <i class="bi bi-shield-lock" aria-hidden="true"></i>
          O e-mail do denunciante é visível apenas para a equipe e nunca é repassado à ONG.
        </p>

        <?php if (empty($denunciasAberta)): ?>
          <div class="admin-vazio admin-vazio-sm">
            <i class="bi bi-megaphone" aria-hidden="true"></i>
            <strong>Nenhuma denúncia registrada</strong>
            <span>Esta notícia não recebeu denúncias da comunidade.</span>
          </div>
        <?php else: ?>
          <div class="anm-denuncias-lista">
            <?php foreach ($denunciasAberta as $denuncia): ?>
              <?php
              $estadoDenuncia = $rotulosDenuncia[(string) $denuncia['estado']]
                  ?? ['rotulo' => ucfirst((string) $denuncia['estado']), 'classe' => 'status-suspensa'];
              $emailDenunciante = trim((string) ($denuncia['email_denunciante'] ?? ''));
              ?>
              <article class="anm-denuncia">
                <div class="anm-denuncia-head">
                  <span class="anm-motivo-chip"><?= anmEsc(noticiaRotuloMotivo((string) $denuncia['motivo'])) ?></span>
                  <span class="admin-status <?= anmEsc($estadoDenuncia['classe']) ?>"><?= anmEsc($estadoDenuncia['rotulo']) ?></span>
                  <time class="anm-denuncia-data" datetime="<?= anmEsc(date('c', strtotime((string) $denuncia['criado_em']))) ?>">
                    <?= anmEsc(fmtDataHora((string) $denuncia['criado_em'])) ?>
                  </time>
                </div>

                <?php if (trim((string) ($denuncia['detalhe'] ?? '')) !== ''): ?>
                  <p class="anm-denuncia-detalhe"><?= anmEsc((string) $denuncia['detalhe']) ?></p>
                <?php else: ?>
                  <p class="anm-denuncia-detalhe is-vazio">Sem detalhe adicional.</p>
                <?php endif; ?>

                <div class="anm-denuncia-meta">
                  <span>
                    <i class="bi bi-envelope" aria-hidden="true"></i>
                    <?php if ($emailDenunciante !== ''): ?>
                      <a href="mailto:<?= anmEsc($emailDenunciante) ?>"><?= anmEsc($emailDenunciante) ?></a>
                    <?php else: ?>
                      E-mail não informado
                    <?php endif; ?>
                  </span>

                  <?php if (!empty($denuncia['tratada_em'])): ?>
                    <span>
                      <i class="bi bi-check2-circle" aria-hidden="true"></i>
                      Tratada por <?= anmEsc($denuncia['tratada_por_nome'] ?: 'Equipe ONGHelp') ?>
                      em <?= anmEsc(fmtDataHora((string) $denuncia['tratada_em'])) ?>
                    </span>
                  <?php endif; ?>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>

      <?php if ($itab === 'historico'): ?>
        <?php if (empty($historicoAberta)): ?>
          <div class="admin-vazio admin-vazio-sm">
            <i class="bi bi-clock-history" aria-hidden="true"></i>
            <strong>Nenhum evento de moderação</strong>
            <span>O histórico começa quando a equipe trata a notícia ou recebe uma denúncia.</span>
          </div>
        <?php else: ?>
          <ol class="anm-historico">
            <?php foreach ($historicoAberta as $evento): ?>
              <?php
              $categoriaEvento = categoriaLogInfo((string) $evento['acao']);
              ?>
              <li>
                <span class="admin-log-icone <?= anmEsc($categoriaEvento['classe']) ?>" aria-hidden="true">
                  <i class="bi <?= anmEsc($categoriaEvento['icone']) ?>"></i>
                </span>
                <div class="anm-historico-copy">
                  <p class="anm-historico-frase"><?= anmEsc(resumoLogAdmin($evento)) ?></p>
                  <span class="anm-historico-meta">
                    <?= anmEsc(fmtDataHora((string) $evento['criado_em'])) ?>
                  </span>
                  <?php if (trim((string) ($evento['detalhe'] ?? '')) !== ''): ?>
                    <span class="anm-historico-detalhe"><?= anmEsc((string) $evento['detalhe']) ?></span>
                  <?php endif; ?>
                </div>
              </li>
            <?php endforeach; ?>
          </ol>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <div class="anm-drawer-foot">
      <?php if ((string) $noticiaAberta['estado'] !== 'oculta'): ?>
        <form method="POST" action="noticias.php" class="anm-foot-form">
          <?= csrfField() ?>
          <input type="hidden" name="acao" value="manter_publicada">
          <input type="hidden" name="noticia_id" value="<?= (int) $noticiaAberta['id'] ?>">
          <input type="hidden" name="voltar" value="<?= anmEsc($urlAtual) ?>">
          <button type="submit" class="btn btn-outline-secondary">Manter publicada</button>
        </form>

        <button type="button" class="btn btn-outline-danger"
                data-bs-toggle="modal" data-bs-target="#anmModalOcultar">
          Ocultar notícia
        </button>

        <button type="button" class="btn btn-danger"
                data-bs-toggle="modal" data-bs-target="#anmModalBloquear">
          Ocultar e bloquear publicações da ONG
        </button>
      <?php else: ?>
        <button type="button" class="btn btn-primary"
                data-bs-toggle="modal" data-bs-target="#anmModalRestaurar">
          Restaurar notícia
        </button>
      <?php endif; ?>
    </div>
  </div>

  <?php if ((string) $noticiaAberta['estado'] !== 'oculta'): ?>
    <div class="modal fade" id="anmModalOcultar" tabindex="-1" aria-labelledby="anmModalOcultarTitulo" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered admin-modal-dialog">
        <div class="modal-content admin-modal">
          <form method="POST" action="noticias.php" data-anm-form-ocultar>
            <?= csrfField() ?>
            <input type="hidden" name="acao" value="ocultar">
            <input type="hidden" name="noticia_id" value="<?= (int) $noticiaAberta['id'] ?>">
            <input type="hidden" name="voltar" value="<?= anmEsc($urlAtual) ?>">

            <div class="modal-header">
              <div>
                <span class="admin-modal-eyebrow">Moderação</span>
                <h2 class="modal-title admin-modal-titulo" id="anmModalOcultarTitulo">Ocultar notícia</h2>
              </div>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>

            <div class="modal-body">
              <p class="admin-modal-texto">
                A notícia sai do ar, mas o texto, as fotos e as denúncias continuam guardados como evidência.
              </p>

              <div class="mb-3">
                <label class="admin-label" for="anmCategoriaOcultar">Categoria</label>
                <select name="categoria" id="anmCategoriaOcultar" class="form-select" required>
                  <option value="">Selecione o motivo</option>
                  <?php foreach ($nomesMotivos as $chave => $rotulo): ?>
                    <option value="<?= anmEsc($chave) ?>"><?= anmEsc($rotulo) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="mb-2">
                <label class="admin-label" for="anmMensagemOcultar">Mensagem para a ONG</label>
                <textarea name="mensagem" id="anmMensagemOcultar" class="form-control" rows="4"
                          minlength="20" maxlength="2000" required
                          data-anm-mensagem
                          placeholder="Explique o motivo da ocultação com pelo menos 20 caracteres."></textarea>
                <div class="anm-contador-linha">
                  <span class="anm-contador" data-anm-contador>0/20</span>
                </div>
              </div>

              <p class="admin-modal-aviso">
                <i class="bi bi-info-circle" aria-hidden="true"></i>
                A ONG verá esta mensagem.
              </p>
            </div>

            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
              <button type="submit" class="btn btn-danger" data-anm-submit-ocultar>Ocultar notícia</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="modal fade" id="anmModalBloquear" tabindex="-1" aria-labelledby="anmModalBloquearTitulo" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered admin-modal-dialog">
        <div class="modal-content admin-modal">
          <form method="POST" action="noticias.php">
            <?= csrfField() ?>
            <input type="hidden" name="acao" value="ocultar_bloquear">
            <input type="hidden" name="noticia_id" value="<?= (int) $noticiaAberta['id'] ?>">
            <input type="hidden" name="voltar" value="<?= anmEsc($urlAtual) ?>">

            <div class="modal-header">
              <div>
                <span class="admin-modal-eyebrow">Moderação</span>
                <h2 class="modal-title admin-modal-titulo" id="anmModalBloquearTitulo">
                  Ocultar e bloquear publicações
                </h2>
              </div>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>

            <div class="modal-body">
              <p class="admin-modal-texto">
                A notícia será ocultada e a ONG não poderá publicar novas notícias até a equipe liberar.
                O conteúdo existente não é apagado.
              </p>

              <div class="mb-3">
                <label class="admin-label" for="anmMotivoBloquear">Motivo do bloqueio</label>
                <textarea name="motivo" id="anmMotivoBloquear" class="form-control" rows="3"
                          maxlength="500" required
                          placeholder="Descreva o motivo que a ONG verá ao tentar publicar."></textarea>
              </div>

              <label class="anm-check" for="anmEntendoBloqueio">
                <input type="checkbox" name="entendo" id="anmEntendoBloqueio" value="1" required>
                <span>Entendo que a ONG não poderá publicar até ser liberada</span>
              </label>

              <p class="admin-modal-aviso is-perigo">
                <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                Use o bloqueio quando houver risco de novas publicações graves.
              </p>
            </div>

            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
              <button type="submit" class="btn btn-danger">Ocultar e bloquear</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  <?php else: ?>
    <div class="modal fade" id="anmModalRestaurar" tabindex="-1" aria-labelledby="anmModalRestaurarTitulo" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered admin-modal-dialog admin-modal-dialog-sm">
        <div class="modal-content admin-modal">
          <form method="POST" action="noticias.php">
            <?= csrfField() ?>
            <input type="hidden" name="acao" value="restaurar">
            <input type="hidden" name="noticia_id" value="<?= (int) $noticiaAberta['id'] ?>">
            <input type="hidden" name="voltar" value="<?= anmEsc($urlAtual) ?>">

            <div class="modal-header admin-modal-header-centro">
              <span class="admin-credencial-check" aria-hidden="true"><i class="bi bi-arrow-counterclockwise"></i></span>
              <h2 class="modal-title admin-modal-titulo" id="anmModalRestaurarTitulo">Restaurar notícia</h2>
            </div>

            <div class="modal-body">
              <p class="admin-modal-texto text-center mb-0">
                A notícia volta a ficar publicada. O histórico de moderação e as denúncias são preservados.
              </p>
            </div>

            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
              <button type="submit" class="btn btn-primary">Restaurar notícia</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  <?php endif; ?>
<?php endif; ?>

<?php include '../includes/admin_footer.php'; ?>
