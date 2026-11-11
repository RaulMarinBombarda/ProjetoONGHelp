<?php
// ============================================================
// includes/noticias_ui.php — Componentes visuais de Notícias
// ============================================================
//
// Camada de apresentação compartilhada pelo hub de notícias, pela
// página da notícia, pela home e pelo perfil público. Recebe as
// linhas já filtradas pela regra central de visibilidade
// (includes/noticias.php).

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/noticias.php';
require_once __DIR__ . '/noticias_fotos.php';

function noticiasMesesPt(): array
{
    return [
        1 => 'janeiro', 2 => 'fevereiro', 3 => 'março', 4 => 'abril',
        5 => 'maio', 6 => 'junho', 7 => 'julho', 8 => 'agosto',
        9 => 'setembro', 10 => 'outubro', 11 => 'novembro', 12 => 'dezembro',
    ];
}

function noticiasDiasPt(): array
{
    return [
        0 => 'domingo', 1 => 'segunda-feira', 2 => 'terça-feira', 3 => 'quarta-feira',
        4 => 'quinta-feira', 5 => 'sexta-feira', 6 => 'sábado',
    ];
}

function noticiaEsc(string $texto): string
{
    return htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
}

/**
 * Endereço público canônico: id + slug. O slug é decorativo para
 * leitura, mas evita que o mesmo conteúdo circule em duas URLs.
 */
function noticiaUrlPublica(array $noticia, string $base = ''): string
{
    $id = (int) ($noticia['id'] ?? 0);
    $slug = trim((string) ($noticia['slug'] ?? ''));
    $url = $base . 'noticia.php?id=' . $id;

    if ($slug !== '') {
        $url .= '&slug=' . rawurlencode($slug);
    }

    return $url;
}

/**
 * Data completa em português para o atributo title, por exemplo
 * "8 de novembro de 2026 às 15:45".
 */
function noticiaDataCompletaPt(string $data): string
{
    $ts = strtotime($data);

    if ($ts === false) {
        return '';
    }

    $meses = noticiasMesesPt();

    return (int) date('j', $ts) . ' de '
        . $meses[(int) date('n', $ts)] . ' de '
        . date('Y', $ts) . ' às '
        . date('H:i', $ts);
}

/**
 * Data civil em português, sem hora. Usada em registros que têm
 * apenas o dia (movimentações financeiras), evitando "às 00:00".
 */
function noticiaDataDiaPt(string $data): string
{
    $ts = strtotime($data);

    if ($ts === false) {
        return '';
    }

    $meses = noticiasMesesPt();

    return (int) date('j', $ts) . ' de '
        . $meses[(int) date('n', $ts)] . ' de '
        . date('Y', $ts);
}

/**
 * Devolve texto relativo ("há 2 dias"), título completo e ISO para
 * o elemento <time>.
 */
function noticiaDataRelativaInfo(?string $data): array
{
    $data = trim((string) $data);

    if ($data === '') {
        return ['texto' => '', 'titulo' => '', 'iso' => ''];
    }

    $ts = strtotime($data);

    if ($ts === false) {
        return ['texto' => '', 'titulo' => '', 'iso' => ''];
    }

    $agora = time();
    $diff = max(0, $agora - $ts);

    if ($diff < 60) {
        $texto = 'agora mesmo';
    } elseif ($diff < 3600) {
        $minutos = (int) floor($diff / 60);
        $texto = 'há ' . $minutos . ' min';
    } elseif ($diff < 86400) {
        $horas = (int) floor($diff / 3600);
        $texto = 'há ' . $horas . ' ' . ($horas === 1 ? 'hora' : 'horas');
    } elseif ($diff < 7 * 86400) {
        $dias = (int) floor($diff / 86400);
        $texto = 'há ' . $dias . ' ' . ($dias === 1 ? 'dia' : 'dias');
    } elseif ($diff < 30 * 86400) {
        $semanas = (int) floor($diff / (7 * 86400));
        $texto = 'há ' . $semanas . ' ' . ($semanas === 1 ? 'semana' : 'semanas');
    } elseif ($diff < 365 * 86400) {
        $meses = (int) floor($diff / (30 * 86400));
        $texto = 'há ' . $meses . ' ' . ($meses === 1 ? 'mês' : 'meses');
    } else {
        $anos = (int) floor($diff / (365 * 86400));
        $texto = 'há ' . $anos . ' ' . ($anos === 1 ? 'ano' : 'anos');
    }

    return [
        'texto' => $texto,
        'titulo' => noticiaDataCompletaPt($data),
        'iso' => date('Y-m-d\TH:i', $ts),
    ];
}

/**
 * Elemento <time> com data relativa e data completa no title.
 */
function noticiaTempoHtml(?string $data, string $classe = ''): string
{
    $info = noticiaDataRelativaInfo($data);

    if ($info['texto'] === '') {
        return '';
    }

    $classe = trim($classe);

    return '<time datetime="' . noticiaEsc($info['iso']) . '"'
        . ($classe !== '' ? ' class="' . noticiaEsc($classe) . '"' : '')
        . ' title="' . noticiaEsc($info['titulo']) . '">'
        . noticiaEsc($info['texto'])
        . '</time>';
}

function noticiaCorDaCausa(array $noticia): string
{
    $cores = coresArea();
    $area = (string) ($noticia['ong_area'] ?? '');

    return $cores[$area][0] ?? '#5A6A80';
}

function noticiaTipoIcone(string $tipo): string
{
    return [
        'acao_realizada' => 'bi-people',
        'projeto' => 'bi-diagram-3',
        'campanha' => 'bi-megaphone',
        'evento' => 'bi-calendar-event',
        'agradecimento' => 'bi-heart',
        'prestacao_contas' => 'bi-file-earmark-text',
    ][$tipo] ?? 'bi-newspaper';
}

function noticiaChipTipoHtml(string $tipo, string $extraClasse = ''): string
{
    if ($tipo === '') {
        return '';
    }

    $extraClasse = trim($extraClasse);

    return '<span class="nt-chip' . ($extraClasse !== '' ? ' ' . noticiaEsc($extraClasse) : '') . '">'
        . '<i class="bi ' . noticiaEsc(noticiaTipoIcone($tipo)) . '" aria-hidden="true"></i>'
        . noticiaEsc(noticiaRotuloTipo($tipo))
        . '</span>';
}

/**
 * Avatar da ONG com anel na cor da causa.
 */
function noticiaAvatarOng(array $noticia, int $tamanho = 28, string $base = '../'): string
{
    $url = fotoPerfilUrl($noticia['ong_foto_perfil'] ?? null, $base, $noticia['ong_area'] ?? null);
    $cor = noticiaCorDaCausa($noticia);

    return '<span class="nt-avatar" style="--causa-cor:' . noticiaEsc($cor) . ';--tam:' . $tamanho . 'px;" aria-hidden="true">'
        . '<img src="' . noticiaEsc($url) . '" alt="" width="' . $tamanho . '" height="' . $tamanho . '" loading="lazy" decoding="async">'
        . '</span>';
}

function noticiaSeloCompacto(array $noticia): string
{
    return badgeVerificado([
        'status' => $noticia['ong_status'] ?? '',
        'verificada_em' => $noticia['ong_verificada_em'] ?? null,
    ], true);
}

/**
 * Todas as fotos das notícias informadas, agrupadas por notícia e
 * na ordem do editor (a primeira de cada grupo é a capa).
 */
function noticiasFotosPorNoticia(PDO $pdo, array $ids, string $base = '../'): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

    if ($ids === []) {
        return [];
    }

    $marcadores = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT * FROM noticias_fotos
         WHERE noticia_id IN ($marcadores)
         ORDER BY noticia_id, ordem, id"
    );
    $stmt->execute($ids);

    $mapa = [];

    foreach ($stmt->fetchAll() as $foto) {
        $mapa[(int) $foto['noticia_id']][] = noticiaFotoBloco($foto, $base);
    }

    return $mapa;
}

/**
 * Imagem de capa do card. Sem foto (ou arquivo inválido), mostra
 * um fundo na cor da causa com o ícone do tipo da notícia.
 */
function noticiaCapaHtml(array $noticia, ?array $foto, bool $carregarAgora = false): string
{
    $url = (string) ($foto['url'] ?? '');

    if ($url === '') {
        return '<span class="nt-capa-vazia" style="--causa-cor:' . noticiaEsc(noticiaCorDaCausa($noticia)) . ';">'
            . '<i class="bi ' . noticiaEsc(noticiaTipoIcone((string) ($noticia['tipo'] ?? ''))) . '" aria-hidden="true"></i>'
            . '</span>';
    }

    $alt = trim((string) ($foto['texto_alternativo'] ?? ''));
    $largura = (int) ($foto['largura'] ?? 0);
    $altura = (int) ($foto['altura'] ?? 0);

    return '<img src="' . noticiaEsc($url) . '"'
        . ' alt="' . noticiaEsc($alt) . '"'
        . ($largura > 0 ? ' width="' . $largura . '"' : '')
        . ($altura > 0 ? ' height="' . $altura . '"' : '')
        . ' loading="' . ($carregarAgora ? 'eager' : 'lazy') . '"'
        . ' decoding="async">';
}

/**
 * Card de notícia usado em todo o site (hub, home, perfil e
 * relacionadas). O card inteiro é um link.
 *
 * Variantes:
 *   padrao   — foto em cima, texto embaixo (grade);
 *   destaque — foto grande à esquerda, texto à direita;
 *   linha    — miniatura + título (lista de manchetes).
 *
 * $base:    prefixo até a raiz do site (para imagens).
 * $urlBase: prefixo até a pasta pages/ (para o link da notícia).
 */
function noticiaCard(
    array $noticia,
    ?array $foto,
    int $totalFotos = 0,
    string $variante = 'padrao',
    string $base = '../',
    string $urlBase = ''
): string {
    $variante = in_array($variante, ['padrao', 'destaque', 'linha'], true) ? $variante : 'padrao';
    $titulo = (string) ($noticia['titulo'] ?? '');
    $resumo = trim((string) ($noticia['resumo'] ?? ''));
    $tipo = (string) ($noticia['tipo'] ?? '');
    $link = noticiaUrlPublica($noticia, $urlBase);
    $data = noticiaTempoHtml($noticia['publicada_em'] ?: ($noticia['criado_em'] ?? null), 'nt-data');

    if ($variante === 'linha') {
        return '<a class="nt-linha" href="' . noticiaEsc($link) . '">'
            . '<span class="nt-linha-capa">' . noticiaCapaHtml($noticia, $foto) . '</span>'
            . '<span class="nt-linha-corpo">'
                . '<span class="nt-linha-meta">' . noticiaEsc(noticiaRotuloTipo($tipo)) . ' · ' . $data . '</span>'
                . '<span class="nt-linha-titulo">' . noticiaEsc($titulo) . '</span>'
                . '<span class="nt-linha-ong">' . noticiaEsc((string) ($noticia['ong_nome'] ?? '')) . '</span>'
            . '</span>'
            . '</a>';
    }

    $contadorFotos = $totalFotos > 1
        ? '<span class="nt-card-fotos" aria-label="' . $totalFotos . ' fotos">'
            . '<i class="bi bi-images" aria-hidden="true"></i>' . $totalFotos
            . '</span>'
        : '';

    return '<a class="nt-card nt-card--' . $variante . '" href="' . noticiaEsc($link) . '">'
        . '<span class="nt-card-capa">'
            . noticiaCapaHtml($noticia, $foto, $variante === 'destaque')
            . $contadorFotos
        . '</span>'
        . '<span class="nt-card-corpo">'
            . '<span class="nt-card-meta">' . noticiaChipTipoHtml($tipo) . $data . '</span>'
            . '<span class="nt-card-titulo">' . noticiaEsc($titulo) . '</span>'
            . ($resumo !== '' ? '<span class="nt-card-resumo">' . noticiaEsc($resumo) . '</span>' : '')
            . '<span class="nt-card-ong">'
                . noticiaAvatarOng($noticia, 28, $base)
                . '<span class="nt-card-ong-nome">' . noticiaEsc((string) ($noticia['ong_nome'] ?? '')) . '</span>'
                . noticiaSeloCompacto($noticia)
                . (trim((string) ($noticia['ong_cidade'] ?? '')) !== ''
                    ? '<span class="nt-card-cidade"><i class="bi bi-geo-alt" aria-hidden="true"></i>'
                        . noticiaEsc((string) $noticia['ong_cidade']) . '</span>'
                    : '')
            . '</span>'
        . '</span>'
        . '</a>';
}

/**
 * Grade responsiva de cards (3 colunas no desktop, 2 no tablet,
 * 1 no celular).
 */
function noticiasGrade(
    array $noticias,
    array $fotosPorNoticia,
    string $base = '../',
    string $urlBase = '',
    string $classeExtra = ''
): string {
    $html = '<div class="nt-grade' . ($classeExtra !== '' ? ' ' . noticiaEsc($classeExtra) : '') . '">';

    foreach ($noticias as $noticia) {
        $id = (int) $noticia['id'];
        $fotos = $fotosPorNoticia[$id] ?? [];
        $html .= noticiaCard($noticia, $fotos[0] ?? null, count($fotos), 'padrao', $base, $urlBase);
    }

    return $html . '</div>';
}
