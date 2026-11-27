<?php
// ============================================================
// includes/components/noticias-ui.php — componentes visuais de Notícias
// ============================================================
//
// camada de apresentação compartilhada pelo hub de notícias, pela
// página da notícia, pela home e pelo perfil público, recebe as
// linhas já filtradas pela regra central de visibilidade
// (includes/modules/noticias.php)

require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../modules/noticias.php';

function noticiasMesesPt(): array
{
    return [
        1 => 'janeiro', 2 => 'fevereiro', 3 => 'março', 4 => 'abril',
        5 => 'maio', 6 => 'junho', 7 => 'julho', 8 => 'agosto',
        9 => 'setembro', 10 => 'outubro', 11 => 'novembro', 12 => 'dezembro',
    ];
}



/**
 * endereço público canônico: id + slug, o slug é decorativo para
 * leitura, mas evita que o mesmo conteúdo circule em duas URLs
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
 * data completa em português para o atributo title, por exemplo
 * "8 de novembro de 2026 às 15:45"
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
 * devolve texto relativo ("há 2 dias"), título completo e ISO para
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
 * elemento <time> com data relativa e data completa no title
 */
function noticiaTempoHtml(?string $data, string $classe = ''): string
{
    $info = noticiaDataRelativaInfo($data);

    if ($info['texto'] === '') {
        return '';
    }

    $classe = trim($classe);

    return '<time datetime="' . e($info['iso']) . '"'
        . ($classe !== '' ? ' class="' . e($classe) . '"' : '')
        . ' title="' . e($info['titulo']) . '">'
        . e($info['texto'])
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

    return '<span class="nt-chip' . ($extraClasse !== '' ? ' ' . e($extraClasse) : '') . '">'
        . '<i class="bi ' . e(noticiaTipoIcone($tipo)) . '" aria-hidden="true"></i>'
        . e(noticiaRotuloTipo($tipo))
        . '</span>';
}

/**
 * avatar da ONG com anel na cor da causa
 */
function noticiaAvatarOng(array $noticia, int $tamanho = 28, string $base = '../'): string
{
    $url = fotoPerfilUrl($noticia['ong_foto_perfil'] ?? null, $base, $noticia['ong_area'] ?? null);
    $cor = noticiaCorDaCausa($noticia);

    return '<span class="nt-avatar" style="--causa-cor:' . e($cor) . ';--tam:' . $tamanho . 'px;" aria-hidden="true">'
        . '<img src="' . e($url) . '" alt="" width="' . $tamanho . '" height="' . $tamanho . '" loading="lazy" decoding="async">'
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
 * todas as fotos das notícias informadas, agrupadas por notícia e
 * na ordem do editor (a primeira de cada grupo é a capa)
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
 * imagem de capa do card, sem foto própria, usa a imagem ilustrativa
 * da área; se nem ela existir, um fundo na cor da causa com o ícone
 */
function noticiaCapaHtml(array $noticia, ?array $foto, bool $carregarAgora = false, string $base = '../'): string
{
    if (empty($foto['url'])) {
        $foto = noticiaImagemPadraoDaNoticia($noticia, $base);
    }

    $url = (string) ($foto['url'] ?? '');

    if ($url === '') {
        return '<span class="nt-capa-vazia" style="--causa-cor:' . e(noticiaCorDaCausa($noticia)) . ';">'
            . '<i class="bi ' . e(noticiaTipoIcone((string) ($noticia['tipo'] ?? ''))) . '" aria-hidden="true"></i>'
            . '</span>';
    }

    $alt = trim((string) ($foto['texto_alternativo'] ?? ''));
    $largura = (int) ($foto['largura'] ?? 0);
    $altura = (int) ($foto['altura'] ?? 0);

    return '<img src="' . e($url) . '"'
        . ' alt="' . e($alt) . '"'
        . ($largura > 0 ? ' width="' . $largura . '"' : '')
        . ($altura > 0 ? ' height="' . $altura . '"' : '')
        . ' loading="' . ($carregarAgora ? 'eager' : 'lazy') . '"'
        . ' decoding="async">';
}

/**
 * card de notícia usado em todo o site (hub, home, perfil e
 * relacionadas), o card inteiro é um link
 *
 * variantes:
 *   padrao   — foto em cima, texto embaixo (grade);
 *   destaque — foto grande à esquerda, texto à direita;
 *   linha    — miniatura + título (lista de manchetes)
 *
 * $base:    prefixo até a raiz do site (para imagens)
 * $urlBase: prefixo até a pasta pages/ (para o link da notícia)
 */
function noticiaCard(
    array $noticia,
    ?array $foto,
    int $totalFotos = 0,
    string $variante = 'padrao',
    string $base = '../',
    string $urlBase = '',
    string $classeExtra = ''
): string {
    $variante = in_array($variante, ['padrao', 'destaque', 'linha'], true) ? $variante : 'padrao';
    $titulo = (string) ($noticia['titulo'] ?? '');
    $resumo = trim((string) ($noticia['resumo'] ?? ''));
    $tipo = (string) ($noticia['tipo'] ?? '');
    $link = noticiaUrlPublica($noticia, $urlBase);
    $data = noticiaTempoHtml(($noticia['publicada_em'] ?? null) ?: ($noticia['criado_em'] ?? null), 'nt-data');

    if ($variante === 'linha') {
        return '<a class="nt-linha" href="' . e($link) . '">'
            . '<span class="nt-linha-capa">' . noticiaCapaHtml($noticia, $foto, false, $base) . '</span>'
            . '<span class="nt-linha-corpo">'
                . '<span class="nt-linha-meta">' . e(noticiaRotuloTipo($tipo)) . ' · ' . $data . '</span>'
                . '<span class="nt-linha-titulo">' . e($titulo) . '</span>'
                . '<span class="nt-linha-ong">' . e((string) ($noticia['ong_nome'] ?? '')) . '</span>'
            . '</span>'
            . '</a>';
    }

    $contadorFotos = $totalFotos > 1
        ? '<span class="nt-card-fotos" aria-label="' . $totalFotos . ' fotos">'
            . '<i class="bi bi-images" aria-hidden="true"></i>' . $totalFotos
            . '</span>'
        : '';

    return '<a class="nt-card nt-card--' . $variante . ($classeExtra !== '' ? ' ' . $classeExtra : '') . '" href="' . e($link) . '">'
        . '<span class="nt-card-capa">'
            . noticiaCapaHtml($noticia, $foto, $variante === 'destaque', $base)
            . $contadorFotos
        . '</span>'
        . '<span class="nt-card-corpo">'
            . '<span class="nt-card-meta">' . noticiaChipTipoHtml($tipo) . $data . '</span>'
            . '<span class="nt-card-titulo">' . e($titulo) . '</span>'
            . ($resumo !== '' ? '<span class="nt-card-resumo">' . e($resumo) . '</span>' : '')
            . '<span class="nt-card-ong">'
                . noticiaAvatarOng($noticia, 28, $base)
                . '<span class="nt-card-ong-nome">' . e((string) ($noticia['ong_nome'] ?? '')) . '</span>'
                . noticiaSeloCompacto($noticia)
                . (trim((string) ($noticia['ong_cidade'] ?? '')) !== ''
                    ? '<span class="nt-card-cidade"><i class="bi bi-geo-alt" aria-hidden="true"></i>'
                        . e((string) $noticia['ong_cidade']) . '</span>'
                    : '')
            . '</span>'
        . '</span>'
        . '</a>';
}

/**
 * tamanhos do mosaico, em blocos de até 6 cards numa grade de 3
 * colunas: largo (2 colunas), alto (2 linhas), inteiro (3 colunas)
 * ou normal (''), cada combinação fecha as linhas sem deixar buraco,
 * inclusive no último bloco, que pode ter menos de 6 cards
 */
function noticiasMosaicoTamanhos(int $total): array
{
    $padroes = [
        1 => ['is-inteiro'],
        2 => ['is-largo', ''],
        3 => ['', '', ''],
        4 => ['is-largo', '', '', 'is-largo'],
        5 => ['is-largo', 'is-alto', '', '', 'is-inteiro'],
        6 => ['is-largo', 'is-alto', '', '', '', 'is-largo'],
    ];
    $tamanhos = [];

    for ($inicio = 0; $inicio < $total; $inicio += 6) {
        $tamanhos = array_merge($tamanhos, $padroes[min(6, $total - $inicio)]);
    }

    return $tamanhos;
}

/**
 * grade responsiva de cards (3 colunas no desktop, 2 no tablet,
 * 1 no celular), com $mosaico, os cards ganham tamanhos variados
 */
function noticiasGrade(
    array $noticias,
    array $fotosPorNoticia,
    string $base = '../',
    string $urlBase = '',
    string $classeExtra = '',
    bool $mosaico = false
): string {
    $classes = 'nt-grade' . ($mosaico ? ' nt-grade--mosaico' : '') . ($classeExtra !== '' ? ' ' . $classeExtra : '');
    $html = '<div class="' . e($classes) . '">';
    $tamanhos = $mosaico ? noticiasMosaicoTamanhos(count($noticias)) : [];

    foreach (array_values($noticias) as $indice => $noticia) {
        $id = (int) $noticia['id'];
        $fotos = $fotosPorNoticia[$id] ?? [];
        $html .= noticiaCard($noticia, $fotos[0] ?? null, count($fotos), 'padrao', $base, $urlBase, $tamanhos[$indice] ?? '');
    }

    return $html . '</div>';
}
