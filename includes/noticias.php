<?php
// ============================================================
// includes/noticias.php — Regras centrais da seção Notícias
// ============================================================
//
// Esta camada concentra validação, sanitização, visibilidade,
// autoria, vínculos, bloqueios e limites de frequência. As telas
// das próximas partes devem usar estas funções em vez de repetir
// regras. Nenhuma função aqui imprime HTML ou toma decisões de
// interface: a resposta é sempre dado estruturado.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/noticias_fotos.php';

// ------------------------------------------------------------
// Catálogos
// ------------------------------------------------------------

function noticiasTipos(): array
{
    return [
        'acao_realizada' => 'Ação realizada',
        'projeto' => 'Projeto',
        'campanha' => 'Campanha',
        'evento' => 'Evento',
        'agradecimento' => 'Agradecimento',
        'prestacao_contas' => 'Prestação de contas',
    ];
}

function noticiasEstados(): array
{
    return [
        'rascunho' => 'Rascunho',
        'publicada' => 'Publicada',
        'oculta' => 'Oculta pela equipe',
    ];
}

function noticiasMotivosDenuncia(): array
{
    return [
        'conteudo_falso' => 'Conteúdo falso ou enganoso',
        'imagem_crianca_dado_pessoal' => 'Imagem de criança ou dado pessoal sem autorização',
        'pedido_dinheiro_suspeito' => 'Pedido de dinheiro suspeito ou golpe',
        'conteudo_ofensivo' => 'Conteúdo ofensivo',
        'outro' => 'Outro',
    ];
}

function noticiasServicosLink(): array
{
    return [
        'instagram' => 'Instagram',
        'facebook' => 'Facebook',
        'youtube' => 'YouTube',
        'tiktok' => 'TikTok',
        'x' => 'X',
        'site' => 'Site',
    ];
}

function noticiaRotuloTipo(string $tipo): string
{
    return noticiasTipos()[$tipo] ?? $tipo;
}

function noticiaRotuloEstado(string $estado): string
{
    return noticiasEstados()[$estado] ?? $estado;
}

function noticiaRotuloMotivo(string $motivo): string
{
    return noticiasMotivosDenuncia()[$motivo] ?? $motivo;
}

function noticiaMotivoValido(string $motivo): bool
{
    return isset(noticiasMotivosDenuncia()[$motivo]);
}

// ------------------------------------------------------------
// Texto: sanitização, contagem e tempo de leitura
// ------------------------------------------------------------

/**
 * Converte o texto salvo (HTML limitado) em texto puro. Serve para
 * contar caracteres/palavras e para a detecção anti-PIX.
 */
function noticiaTextoPlano(string $html): string
{
    $texto = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $texto = str_replace(["\xC2\xA0", "\r\n", "\r"], [' ', "\n", "\n"], $texto);
    $texto = preg_replace('/\s+/u', ' ', $texto) ?? $texto;

    return trim($texto);
}

function noticiaContarPalavras(string $textoPlano): int
{
    $textoPlano = trim($textoPlano);

    if ($textoPlano === '') {
        return 0;
    }

    $palavras = preg_split('/\s+/u', $textoPlano, -1, PREG_SPLIT_NO_EMPTY);

    return is_array($palavras) ? count($palavras) : 0;
}

/**
 * Tempo de leitura em minutos: palavras ÷ 200, arredondado para
 * cima e nunca menor que 1 quando há texto.
 */
function noticiaTempoLeitura(string $textoPlano): int
{
    $palavras = noticiaContarPalavras($textoPlano);

    if ($palavras <= 0) {
        return 0;
    }

    return max(1, (int) ceil($palavras / ONGHELP_NOTICIAS_PALAVRAS_POR_MINUTO));
}

function noticiaTempoLeituraHtml(string $html): int
{
    return noticiaTempoLeitura(noticiaTextoPlano($html));
}

/**
 * Sanitiza o texto da notícia com uma lista branca de tags.
 *
 * Permitido: parágrafos, subtítulo (h2), negrito, itálico, listas
 * ordenadas/não ordenadas e links http/https. Todo o resto é
 * removido ou desembrulhado. Atributos são descartados, exceto o
 * href de links, que ainda é validado. Nunca há HTML livre.
 */
function sanitizarHtmlNoticia(string $html): string
{
    $html = trim($html);

    if ($html === '') {
        return '';
    }

    // Texto sem tags: preserva parágrafos e quebras de linha.
    if (!preg_match('/<[a-z][\s\S]*>/i', $html)) {
        $texto = htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $paragrafos = preg_split('/\R{2,}/u', $texto) ?: [$texto];
        $saida = [];

        foreach ($paragrafos as $paragrafo) {
            $paragrafo = trim($paragrafo);

            if ($paragrafo !== '') {
                $saida[] = '<p>' . nl2br($paragrafo, false) . '</p>';
            }
        }

        return implode('', $saida);
    }

    $documento = new DOMDocument('1.0', 'UTF-8');
    $anterior = libxml_use_internal_errors(true);

    $carregou = $documento->loadHTML(
        '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8">'
        . '</head><body><div id="onghelp-root">' . $html . '</div></body></html>'
    );

    libxml_clear_errors();
    libxml_use_internal_errors($anterior);

    if (!$carregou) {
        $texto = htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<p>' . nl2br($texto, false) . '</p>';
    }

    $xpath = new DOMXPath($documento);
    $raiz = $xpath->query('//*[@id="onghelp-root"]')->item(0);

    if (!$raiz) {
        return '';
    }

    noticiasSanitizarNo($raiz);

    $saida = '';

    foreach ($raiz->childNodes as $filho) {
        $pedaco = $documento->saveHTML($filho);

        if (is_string($pedaco)) {
            $saida .= $pedaco;
        }
    }

    // Remove parágrafos vazios deixados pelo editor.
    $saida = preg_replace('~<p>\s*(?:<br\s*/?>\s*)*</p>~i', '', $saida) ?? $saida;

    return trim($saida);
}

/**
 * Percorre a árvore removendo tags/atributos fora da lista branca.
 * Função auxiliar de sanitizarHtmlNoticia().
 */
function noticiasSanitizarNo(DOMNode $no, int $profundidade = 0): void
{
    if ($profundidade > 20) {
        if ($no->parentNode) {
            $no->parentNode->removeChild($no);
        }

        return;
    }

    $filhos = [];

    foreach ($no->childNodes as $filho) {
        $filhos[] = $filho;
    }

    foreach ($filhos as $filho) {
        if ($filho->nodeType === XML_TEXT_NODE) {
            continue;
        }

        if ($filho->nodeType === XML_COMMENT_NODE) {
            $no->removeChild($filho);
            continue;
        }

        if ($filho->nodeType !== XML_ELEMENT_NODE) {
            continue;
        }

        $tag = strtolower($filho->nodeName);

        if (in_array($tag, [
            'script', 'style', 'iframe', 'object', 'embed', 'svg', 'math',
            'template', 'form', 'input', 'button', 'textarea', 'select', 'option',
        ], true)) {
            $no->removeChild($filho);
            continue;
        }

        $mapa = ['b' => 'strong', 'i' => 'em', 'h3' => 'h2', 'h4' => 'h2'];

        if (isset($mapa[$tag])) {
            $novo = $filho->ownerDocument->createElement($mapa[$tag]);

            while ($filho->firstChild) {
                $novo->appendChild($filho->firstChild);
            }

            $no->replaceChild($novo, $filho);
            $filho = $novo;
            $tag = $mapa[$tag];
        }

        if (!in_array($tag, ['p', 'br', 'h2', 'strong', 'em', 'ul', 'ol', 'li', 'a'], true)) {
            // Desembrulha a tag, mantendo o conteúdo já sanitizado.
            noticiasSanitizarNo($filho, $profundidade + 1);

            if ($filho->parentNode !== $no) {
                continue;
            }

            while ($filho->firstChild) {
                $no->insertBefore($filho->firstChild, $filho);
            }

            $no->removeChild($filho);
            continue;
        }

        if ($tag === 'a') {
            $info = noticiaNormalizarLink($filho->getAttribute('href'));

            if (!$info['ok']) {
                noticiasSanitizarNo($filho, $profundidade + 1);

                if ($filho->parentNode !== $no) {
                    continue;
                }

                while ($filho->firstChild) {
                    $no->insertBefore($filho->firstChild, $filho);
                }

                $no->removeChild($filho);
                continue;
            }

            while ($filho->attributes->length > 0) {
                $filho->removeAttribute($filho->attributes->item(0)->nodeName);
            }

            $filho->setAttribute('href', $info['url']);
            $filho->setAttribute('target', '_blank');
            $filho->setAttribute('rel', 'noopener noreferrer nofollow');
        } else {
            while ($filho->attributes->length > 0) {
                $filho->removeAttribute($filho->attributes->item(0)->nodeName);
            }
        }

        if ($tag !== 'br') {
            noticiasSanitizarNo($filho, $profundidade + 1);
        }
    }
}

function noticiaValorTexto(mixed $valor): string
{
    return is_scalar($valor) ? (string) $valor : '';
}

function noticiaNormalizarEspacos(string $texto): string
{
    $texto = trim($texto);

    if ($texto === '') {
        return '';
    }

    return preg_replace('/\s+/u', ' ', $texto) ?? $texto;
}

// ------------------------------------------------------------
// Links
// ------------------------------------------------------------

/**
 * Valida e normaliza um link http/https. Devolve o domínio que
 * sempre será exibido e o serviço identificado pelo domínio.
 */
function noticiaNormalizarLink(string $url): array
{
    $url = trim($url);

    if ($url === '') {
        return ['ok' => false, 'erro' => 'Informe um link.'];
    }

    if (preg_match('/[\x00-\x20\x7F]/', $url)) {
        return ['ok' => false, 'erro' => 'O link contém caracteres inválidos.'];
    }

    if (mb_strlen($url, 'UTF-8') > 500) {
        return ['ok' => false, 'erro' => 'O link pode ter no máximo 500 caracteres.'];
    }

    if (!preg_match('~^[a-z][a-z0-9+.-]*://~i', $url)) {
        if (str_starts_with($url, '//')) {
            return ['ok' => false, 'erro' => 'Informe um link http ou https válido.'];
        }

        $url = 'https://' . ltrim($url, '/');
    }

    $partes = parse_url($url);

    if ($partes === false) {
        return ['ok' => false, 'erro' => 'Informe um link http ou https válido.'];
    }

    $esquema = strtolower((string) ($partes['scheme'] ?? ''));

    if (!in_array($esquema, ['http', 'https'], true)) {
        return ['ok' => false, 'erro' => 'Informe um link http ou https válido.'];
    }

    if (!empty($partes['user']) || !empty($partes['pass'])) {
        return ['ok' => false, 'erro' => 'O link não pode conter usuário e senha.'];
    }

    $host = strtolower(rtrim((string) ($partes['host'] ?? ''), '.'));

    if ($host === '') {
        return ['ok' => false, 'erro' => 'Informe um link http ou https válido.'];
    }

    if (str_starts_with($host, 'www.')) {
        $host = substr($host, 4);
    }

    $ehIp = filter_var($host, FILTER_VALIDATE_IP) !== false;

    if (!$ehIp) {
        if (filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
            return ['ok' => false, 'erro' => 'O domínio do link não é válido.'];
        }

        if (!str_contains($host, '.')) {
            return ['ok' => false, 'erro' => 'O domínio do link não é válido.'];
        }
    }

    return [
        'ok' => true,
        'erro' => '',
        'url' => $url,
        'host' => $host,
        'dominio' => $host,
        'servico' => noticiaServicoDoDominio($host),
    ];
}

function noticiaServicoDoDominio(string $host): string
{
    $mapa = [
        'instagram.com' => 'instagram',
        'facebook.com' => 'facebook',
        'fb.com' => 'facebook',
        'youtube.com' => 'youtube',
        'youtu.be' => 'youtube',
        'tiktok.com' => 'tiktok',
        'x.com' => 'x',
        'twitter.com' => 'x',
    ];

    foreach ($mapa as $dominio => $servico) {
        if (noticiaDominioCorresponde($host, $dominio)) {
            return $servico;
        }
    }

    return 'site';
}

function noticiaDominioCorresponde(string $host, string $dominio): bool
{
    $host = strtolower(trim($host));
    $dominio = strtolower(trim($dominio));

    return $host === $dominio || str_ends_with($host, '.' . $dominio);
}

function noticiaDominioEmLista(string $host, array $lista): bool
{
    foreach ($lista as $dominio) {
        if (noticiaDominioCorresponde($host, (string) $dominio)) {
            return true;
        }
    }

    return false;
}

function noticiaLinkEhPagamentoOuEncurtador(string $url): bool
{
    $info = noticiaNormalizarLink($url);

    if (!$info['ok']) {
        return false;
    }

    return noticiaDominioEmLista($info['host'], ONGHELP_NOTICIAS_DOMINIOS_PAGAMENTO)
        || noticiaDominioEmLista($info['host'], ONGHELP_NOTICIAS_DOMINIOS_ENCURTADORES);
}

function noticiaLinksDoHtml(string $html): array
{
    if ($html === '' || !preg_match_all('/<a\b[^>]*\bhref\s*=\s*(["\'])(.*?)\1/is', $html, $achados)) {
        return [];
    }

    return array_map(
        static fn (string $url): string => html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        $achados[2]
    );
}

// ------------------------------------------------------------
// Anti-PIX
// ------------------------------------------------------------

/**
 * Detecta chave PIX perto de palavra de doação/transferência, ou
 * link de pagamento/encurtador. A regra vale para título, resumo,
 * texto e links.
 */
function noticiaDetectarPix(string $titulo, string $resumo, string $texto, array $links = []): bool
{
    if (!mb_check_encoding($titulo, 'UTF-8')) {
        $titulo = mb_scrub($titulo, 'UTF-8');
    }

    if (!mb_check_encoding($resumo, 'UTF-8')) {
        $resumo = mb_scrub($resumo, 'UTF-8');
    }

    if (!mb_check_encoding($texto, 'UTF-8')) {
        $texto = mb_scrub($texto, 'UTF-8');
    }

    $textoPlano = noticiaTextoPlano($texto);
    $partes = [$titulo, $resumo, $textoPlano];
    $urls = noticiaLinksDoHtml($texto);

    foreach ($links as $link) {
        $url = is_array($link)
            ? noticiaValorTexto($link['url'] ?? '')
            : noticiaValorTexto($link);
        $url = trim($url);

        if ($url !== '') {
            $urls[] = $url;
            $partes[] = rawurldecode($url);
        }
    }

    // Também detecta links digitados diretamente no texto.
    $textoParaUrls = implode(' ', [$titulo, $resumo, $textoPlano]);

    if (preg_match_all('~https?://[^\s<>"\']+~iu', $textoParaUrls, $achados)) {
        foreach ($achados[0] as $url) {
            $urls[] = $url;
            $partes[] = rawurldecode($url);
        }
    }

    $textoCompleto = implode("\n", array_filter(
        $partes,
        static fn (string $parte): bool => trim($parte) !== ''
    ));

    if (noticiaTextoTemChavePixPertoDeDoacao($textoCompleto)) {
        return true;
    }

    foreach (array_unique($urls) as $url) {
        if (noticiaLinkEhPagamentoOuEncurtador($url)) {
            return true;
        }
    }

    return false;
}

function noticiaTextoTemChavePixPertoDeDoacao(string $texto): bool
{
    $texto = trim($texto);

    if ($texto === '') {
        return false;
    }

    // Entrada malformada (por exemplo, Latin-1 no lugar de UTF-8)
    // faria as expressões com /u falharem e liberaria o texto. Troca
    // os bytes inválidos antes de procurar as chaves.
    if (!mb_check_encoding($texto, 'UTF-8')) {
        $texto = mb_scrub($texto, 'UTF-8');
    }

    $palavras = '/(?<![\p{L}])(?:pix|doe|doar|doa[cç][aã]o|doacao|transfira|transfer[eê]ncia|transferencia|dep[oó]sito|deposito|conta|chave|ted|doc)(?![\p{L}])/iu';

    if (!preg_match_all($palavras, $texto, $ocorrencias, PREG_OFFSET_CAPTURE) || empty($ocorrencias[0])) {
        return false;
    }

    $padroes = [
        // CPF
        '/(?<!\d)\d{3}\.?\d{3}\.?\d{3}-?\d{2}(?!\d)/',
        // CNPJ
        '/(?<!\d)\d{2}\.?\d{3}\.?\d{3}\/?\d{4}-?\d{2}(?!\d)/',
        // E-mail
        '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i',
        // Chave aleatória em UUID
        '/(?<![0-9a-f])[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}(?![0-9a-f])/i',
        // Chave aleatória em hexadecimal
        '/(?<![0-9a-f])[0-9a-f]{32}(?![0-9a-f])/i',
        // Telefone com DDD (formato comum de chave PIX)
        '/(?:\+?55[\s.\-]?)?\(?\d{2}\)?[\s.\-]?9?\d{4}[\s.\-]?\d{4}(?!\d)/',
    ];

    $limite = 160;

    foreach ($padroes as $padrao) {
        if (!preg_match_all($padrao, $texto, $chaves, PREG_OFFSET_CAPTURE) || empty($chaves[0])) {
            continue;
        }

        foreach ($chaves[0] as $chave) {
            $posicaoChave = $chave[1];

            foreach ($ocorrencias[0] as $palavra) {
                if (abs($posicaoChave - $palavra[1]) <= $limite) {
                    return true;
                }
            }
        }
    }

    return false;
}

// ------------------------------------------------------------
// Visibilidade pública e autoria
// ------------------------------------------------------------

/**
 * REGRA CENTRAL DE VISIBILIDADE PÚBLICA.
 *
 * Toda consulta pública de notícias deve usar este fragmento SQL.
 * Só aparece notícia PUBLICADA de ONG APROVADA. Rascunho, oculta,
 * ONG pendente/recusada/suspensa não aparecem. A condição é
 * dinâmica: se a ONG for reativada, as notícias voltam.
 */
function noticiasSqlVisivel(string $aliasNoticia = 'n', string $aliasOng = 'u'): string
{
    $noticia = preg_replace('/[^a-zA-Z0-9_]/', '', $aliasNoticia) ?: 'n';
    $ong = preg_replace('/[^a-zA-Z0-9_]/', '', $aliasOng) ?: 'u';

    return "{$noticia}.estado = 'publicada' AND {$ong}.status = 'aprovada'";
}

/**
 * Mesma regra central, para linhas já carregadas.
 * Espera as chaves estado e ong_status (ou usuario_status/status).
 */
function noticiaVisivelPublicamente(array $noticia): bool
{
    $estado = (string) ($noticia['estado'] ?? '');
    $statusOng = (string) (
        $noticia['ong_status']
        ?? $noticia['usuario_status']
        ?? $noticia['status']
        ?? ''
    );

    return $estado === 'publicada' && $statusOng === 'aprovada';
}

/**
 * Busca uma notícia pública por id ou slug usando a regra central.
 * Devolve null para inexistente, rascunho, oculta ou ONG não aprovada.
 */
function buscarNoticiaPublica(PDO $pdo, string $identificador): ?array
{
    $identificador = trim($identificador);

    if ($identificador === '') {
        return null;
    }

    $sql = "SELECT n.*,
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
            FROM noticias n
            INNER JOIN usuarios u ON u.id = n.usuario_id
            WHERE " . noticiasSqlVisivel('n', 'u') . "
              AND (n.id = ? OR n.slug = ?)
            LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$identificador, $identificador]);
    $noticia = $stmt->fetch();

    return $noticia ?: null;
}

/**
 * Busca uma notícia garantindo que ela pertence à ONG informada.
 * Use em toda ação de edição/exclusão para impedir acesso por id
 * de outra ONG.
 */
function buscarNoticiaDaOng(PDO $pdo, int $noticiaId, int $ongId): ?array
{
    if ($noticiaId <= 0 || $ongId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM noticias WHERE id = ? AND usuario_id = ? LIMIT 1');
    $stmt->execute([$noticiaId, $ongId]);
    $noticia = $stmt->fetch();

    return $noticia ?: null;
}

function noticiaPertenceOng(array $noticia, int $ongId): bool
{
    return (int) ($noticia['usuario_id'] ?? 0) === $ongId;
}

function noticiaPodeSerEditada(array $noticia): bool
{
    return (string) ($noticia['estado'] ?? '') !== 'oculta';
}

function noticiaPodeSerExcluida(array $noticia): bool
{
    return (string) ($noticia['estado'] ?? '') !== 'oculta';
}

function noticiaPodeSerDespublicada(array $noticia): bool
{
    return (string) ($noticia['estado'] ?? '') === 'publicada';
}

function noticiaPodeSerPublicada(array $noticia): bool
{
    return (string) ($noticia['estado'] ?? '') === 'rascunho';
}

/**
 * Publica um rascunho da própria ONG.
 * Revalida o conteúdo salvo (texto, vínculos, fotos, direitos e
 * anti-PIX) antes de mudar o estado, para que um rascunho antigo
 * não contorne regras novas.
 */
function publicarNoticiaDaOng(PDO $pdo, int $noticiaId, int $ongId): array
{
    $noticia = buscarNoticiaDaOng($pdo, $noticiaId, $ongId);

    if (!$noticia) {
        return ['ok' => false, 'erro' => 'Notícia não encontrada para esta ONG.'];
    }

    if (!noticiaPodeSerEditada($noticia)) {
        return ['ok' => false, 'erro' => 'Notícia oculta pela equipe não pode ser alterada.'];
    }

    if (!noticiaPodeSerPublicada($noticia)) {
        return ['ok' => false, 'erro' => 'Somente rascunhos podem ser publicados.'];
    }

    $stmtLinks = $pdo->prepare(
        'SELECT url FROM noticias_links WHERE noticia_id = ? ORDER BY ordem, id'
    );
    $stmtLinks->execute([$noticiaId]);
    $links = $stmtLinks->fetchAll(PDO::FETCH_COLUMN);

    $dados = [
        'titulo' => (string) $noticia['titulo'],
        'resumo' => (string) $noticia['resumo'],
        'texto' => (string) $noticia['texto'],
        'tipo' => (string) $noticia['tipo'],
        'projeto_id' => $noticia['projeto_id'] ?? null,
        'movimentacao_id' => $noticia['movimentacao_id'] ?? null,
        'evento_data' => $noticia['evento_data'] ?? '',
        'evento_local' => $noticia['evento_local'] ?? '',
        'links' => $links,
        'direitos_imagem' => !empty($noticia['direitos_imagem_aceito_em']),
    ];

    $validacao = validarNoticia($pdo, $ongId, $dados, true, $noticiaId);

    if (!$validacao['ok']) {
        $erros = $validacao['erros'];
        $primeiro = $erros ? (string) reset($erros) : 'Não foi possível publicar a notícia.';

        return ['ok' => false, 'erro' => $primeiro];
    }

    $stmt = $pdo->prepare(
        "UPDATE noticias
         SET estado = 'publicada',
             publicada_em = COALESCE(publicada_em, NOW()),
             atualizado_em = NOW()
         WHERE id = ? AND usuario_id = ? AND estado = 'rascunho'"
    );
    $stmt->execute([$noticiaId, $ongId]);

    if ($stmt->rowCount() < 1) {
        return ['ok' => false, 'erro' => 'Não foi possível publicar a notícia.'];
    }

    return ['ok' => true, 'erro' => ''];
}

/**
 * Despublica uma notícia da própria ONG, voltando ao estado rascunho.
 * O campo publicada_em é preservado de propósito: ele mantém o
 * histórico e impede que despublicar burle o limite diário.
 */
function despublicarNoticiaDaOng(PDO $pdo, int $noticiaId, int $ongId): array
{
    $noticia = buscarNoticiaDaOng($pdo, $noticiaId, $ongId);

    if (!$noticia) {
        return ['ok' => false, 'erro' => 'Notícia não encontrada para esta ONG.'];
    }

    if (!noticiaPodeSerEditada($noticia)) {
        return ['ok' => false, 'erro' => 'Notícia oculta pela equipe não pode ser alterada.'];
    }

    if (!noticiaPodeSerDespublicada($noticia)) {
        return ['ok' => false, 'erro' => 'Somente notícias publicadas podem ser despublicadas.'];
    }

    $stmt = $pdo->prepare(
        "UPDATE noticias
         SET estado = 'rascunho', atualizado_em = NOW()
         WHERE id = ? AND usuario_id = ?"
    );
    $stmt->execute([$noticiaId, $ongId]);

    return ['ok' => true, 'erro' => ''];
}

/**
 * Exclui uma notícia da própria ONG. As fotos e os links saem por
 * cascata. Notícia oculta pela equipe não pode ser excluída: ela
 * fica guardada como evidência.
 */
function excluirNoticiaDaOng(PDO $pdo, int $noticiaId, int $ongId): array
{
    $noticia = buscarNoticiaDaOng($pdo, $noticiaId, $ongId);

    if (!$noticia) {
        return ['ok' => false, 'erro' => 'Notícia não encontrada para esta ONG.'];
    }

    if (!noticiaPodeSerExcluida($noticia)) {
        return ['ok' => false, 'erro' => 'Notícia oculta pela equipe não pode ser excluída.'];
    }

    // Os arquivos precisam ser lidos antes do DELETE em cascata.
    $fotos = noticiaFotosDaNoticia($pdo, $noticiaId);

    $stmt = $pdo->prepare('DELETE FROM noticias WHERE id = ? AND usuario_id = ?');
    $stmt->execute([$noticiaId, $ongId]);

    foreach ($fotos as $foto) {
        noticiaFotoApagarArquivo((string) $foto['arquivo']);
    }

    return ['ok' => true, 'erro' => ''];
}

// ------------------------------------------------------------
// Elegibilidade da ONG e bloqueio de publicações
// ------------------------------------------------------------

/**
 * Devolve o bloqueio ativo de publicação da ONG, se existir.
 */
function bloqueioPublicacaoNoticias(PDO $pdo, int $ongId): ?array
{
    if ($ongId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT b.*, a.nome AS criado_por_nome
         FROM noticias_bloqueios b
         LEFT JOIN admins a ON a.id = b.criado_por
         WHERE b.usuario_id = ? AND b.ativo = 1
         ORDER BY b.criado_em DESC, b.id DESC
         LIMIT 1'
    );
    $stmt->execute([$ongId]);
    $bloqueio = $stmt->fetch();

    return $bloqueio ?: null;
}

/**
 * Confere status da ONG e bloqueio de publicação.
 * Retorno: ['pode' => bool, 'motivo' => string].
 */
function ongPodePublicarNoticias(PDO $pdo, int $ongId): array
{
    if ($ongId <= 0) {
        return ['pode' => false, 'motivo' => 'ONG não encontrada.'];
    }

    $stmt = $pdo->prepare('SELECT status FROM usuarios WHERE id = ? LIMIT 1');
    $stmt->execute([$ongId]);
    $status = $stmt->fetchColumn();

    if ($status === false) {
        return ['pode' => false, 'motivo' => 'ONG não encontrada.'];
    }

    if ($status !== 'aprovada') {
        $mensagens = [
            'pendente' => 'Sua ONG ainda está em análise e não pode publicar notícias.',
            'recusada' => 'Sua ONG não foi aprovada e não pode publicar notícias.',
            'suspensa' => 'Sua ONG está suspensa e não pode publicar notícias.',
        ];

        return [
            'pode' => false,
            'motivo' => $mensagens[$status] ?? 'Sua ONG não pode publicar notícias no momento.',
        ];
    }

    $bloqueio = bloqueioPublicacaoNoticias($pdo, $ongId);

    if ($bloqueio) {
        return [
            'pode' => false,
            'motivo' => 'As publicações da sua ONG estão bloqueadas pela equipe. Motivo: '
                . (string) $bloqueio['motivo'],
        ];
    }

    return ['pode' => true, 'motivo' => ''];
}

function bloquearPublicacoesNoticias(PDO $pdo, int $ongId, string $motivo, int $adminId): int
{
    $motivo = trim($motivo);

    if ($ongId <= 0 || $motivo === '') {
        throw new InvalidArgumentException('ONG e motivo são obrigatórios para bloquear publicações.');
    }

    $motivo = mb_substr($motivo, 0, 500, 'UTF-8');
    $propriaTransacao = !$pdo->inTransaction();

    if ($propriaTransacao) {
        $pdo->beginTransaction();
    }

    try {
        $pdo->prepare(
            'UPDATE noticias_bloqueios
             SET ativo = 0, liberado_por = ?, liberado_em = NOW()
             WHERE usuario_id = ? AND ativo = 1'
        )->execute([$adminId > 0 ? $adminId : null, $ongId]);

        $stmt = $pdo->prepare(
            'INSERT INTO noticias_bloqueios (usuario_id, ativo, motivo, criado_por, criado_em)
             VALUES (?, 1, ?, ?, NOW())'
        );
        $stmt->execute([$ongId, $motivo, $adminId > 0 ? $adminId : null]);
        $id = (int) $pdo->lastInsertId();

        if ($propriaTransacao) {
            $pdo->commit();
        }

        return $id;
    } catch (Throwable $erro) {
        if ($propriaTransacao && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $erro;
    }
}

function liberarPublicacoesNoticias(PDO $pdo, int $ongId, int $adminId): int
{
    if ($ongId <= 0) {
        return 0;
    }

    $stmt = $pdo->prepare(
        'UPDATE noticias_bloqueios
         SET ativo = 0, liberado_por = ?, liberado_em = NOW()
         WHERE usuario_id = ? AND ativo = 1'
    );
    $stmt->execute([$adminId > 0 ? $adminId : null, $ongId]);

    return $stmt->rowCount();
}

// ------------------------------------------------------------
// Limites de frequência
// ------------------------------------------------------------

function noticiasPublicadasHoje(
    PDO $pdo,
    int $ongId,
    ?int $ignorarNoticiaId = null
): int {
    if ($ongId <= 0) {
        return 0;
    }

    $sql = 'SELECT COUNT(*)
            FROM noticias
            WHERE usuario_id = ?
              AND publicada_em IS NOT NULL
              AND publicada_em >= CURDATE()
              AND publicada_em < CURDATE() + INTERVAL 1 DAY';
    $parametros = [$ongId];

    if ($ignorarNoticiaId !== null && $ignorarNoticiaId > 0) {
        $sql .= ' AND id <> ?';
        $parametros[] = $ignorarNoticiaId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($parametros);

    return (int) $stmt->fetchColumn();
}

function limitePublicacoesNoticiasAtingido(
    PDO $pdo,
    int $ongId,
    ?int $ignorarNoticiaId = null
): bool {
    return noticiasPublicadasHoje($pdo, $ongId, $ignorarNoticiaId)
        >= ONGHELP_NOTICIAS_PUBLICACOES_DIA;
}

function noticiaMensagemLimiteDiario(): string
{
    return 'Sua ONG já publicou o limite de ' . ONGHELP_NOTICIAS_PUBLICACOES_DIA
        . ' notícias hoje. Tente novamente amanhã.';
}

// ------------------------------------------------------------
// Datas, slug e vínculos
// ------------------------------------------------------------

function noticiaNormalizarDataHora(string $valor): ?string
{
    $valor = trim($valor);

    if ($valor === '') {
        return null;
    }

    $formatos = [
        'Y-m-d\TH:i:s',
        'Y-m-d\TH:i',
        'Y-m-d H:i:s',
        'Y-m-d H:i',
        'd/m/Y H:i:s',
        'd/m/Y H:i',
    ];

    foreach ($formatos as $formato) {
        $data = DateTimeImmutable::createFromFormat('!' . $formato, $valor);
        $erros = DateTimeImmutable::getLastErrors();

        if ($data instanceof DateTimeImmutable
            && ($erros === false || ($erros['warning_count'] === 0 && $erros['error_count'] === 0))
        ) {
            return $data->format('Y-m-d H:i:s');
        }
    }

    return null;
}

function noticiaSlugBase(string $titulo): string
{
    $slug = mb_strtolower(trim($titulo), 'UTF-8');

    $slug = strtr($slug, [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c', 'ñ' => 'n', 'ý' => 'y', 'ÿ' => 'y',
    ]);

    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');

    if ($slug === '') {
        $slug = 'noticia';
    }

    return mb_substr($slug, 0, 100, 'UTF-8');
}

/**
 * Gera um slug único. O sufixo -2, -3... resolve colisões.
 */
function gerarSlugNoticia(PDO $pdo, string $titulo, ?int $ignorarId = null): string
{
    $base = noticiaSlugBase($titulo);
    $candidato = $base;
    $sufixo = 2;
    $ignorarId = $ignorarId ?? 0;

    while (true) {
        $stmt = $pdo->prepare('SELECT 1 FROM noticias WHERE slug = ? AND id <> ? LIMIT 1');
        $stmt->execute([$candidato, $ignorarId]);

        if ($stmt->fetchColumn() === false) {
            return $candidato;
        }

        $acrescimo = '-' . $sufixo;
        $limiteBase = ONGHELP_NOTICIAS_SLUG_MAX - mb_strlen($acrescimo, 'UTF-8');
        $candidato = mb_substr($base, 0, max(1, $limiteBase), 'UTF-8') . $acrescimo;
        $sufixo++;
    }
}

function noticiaIdOpcional(mixed $valor): ?int
{
    if ($valor === null) {
        return null;
    }

    $texto = trim(noticiaValorTexto($valor));

    if ($texto === '' || $texto === '0') {
        return null;
    }

    if (!ctype_digit($texto) || (int) $texto <= 0) {
        return null;
    }

    return (int) $texto;
}

// ------------------------------------------------------------
// Validação principal
// ------------------------------------------------------------

/**
 * Valida e normaliza os dados de uma notícia.
 *
 * $publicando = false permite salvar rascunho incompleto, mas ainda
 * aplica limites máximos, sanitização, vínculos e formato. Ao
 * publicar, exige todos os campos, ao menos uma foto, aceite de
 * direitos de imagem, ONG aprovada/sem bloqueio e respeita o limite
 * diário. A mensagem anti-PIX só bloqueia a publicação.
 *
 * Retorno:
 *   ok, erros (campo => mensagem), dados normalizados, palavras,
 *   tempo_leitura, tem_pix e noticia_atual.
 */
function validarNoticia(
    PDO $pdo,
    int $ongId,
    array $dados,
    bool $publicando = false,
    ?int $noticiaId = null
): array {
    $erros = [];
    $noticiaAtual = null;

    if ($noticiaId !== null && $noticiaId > 0) {
        $noticiaAtual = buscarNoticiaDaOng($pdo, $noticiaId, $ongId);

        if (!$noticiaAtual) {
            $erros['noticia'] = 'Notícia não encontrada para esta ONG.';
        } elseif ((string) $noticiaAtual['estado'] === 'oculta') {
            $erros['noticia'] = 'Notícia oculta pela equipe não pode ser alterada.';
        }
    }

    $titulo = noticiaNormalizarEspacos(noticiaValorTexto($dados['titulo'] ?? ''));
    $resumo = noticiaNormalizarEspacos(noticiaValorTexto($dados['resumo'] ?? ''));
    $texto = sanitizarHtmlNoticia(noticiaValorTexto($dados['texto'] ?? ''));
    $textoPlano = noticiaTextoPlano($texto);
    $tipo = trim(noticiaValorTexto($dados['tipo'] ?? ''));

    $tipos = noticiasTipos();

    if ($tipo !== '' && !isset($tipos[$tipo])) {
        $erros['tipo'] = 'Escolha um tipo de notícia válido.';
    }

    if ($publicando && $tipo === '') {
        $erros['tipo'] = 'Escolha um tipo de notícia.';
    }

    $tituloLen = mb_strlen($titulo, 'UTF-8');
    $resumoLen = mb_strlen($resumo, 'UTF-8');
    $textoLen = mb_strlen($textoPlano, 'UTF-8');

    if ($publicando) {
        if ($tituloLen < ONGHELP_NOTICIAS_TITULO_MIN || $tituloLen > ONGHELP_NOTICIAS_TITULO_MAX) {
            $erros['titulo'] = 'O título deve ter entre ' . ONGHELP_NOTICIAS_TITULO_MIN
                . ' e ' . ONGHELP_NOTICIAS_TITULO_MAX . ' caracteres.';
        }

        if ($resumoLen < ONGHELP_NOTICIAS_RESUMO_MIN || $resumoLen > ONGHELP_NOTICIAS_RESUMO_MAX) {
            $erros['resumo'] = 'O resumo deve ter entre ' . ONGHELP_NOTICIAS_RESUMO_MIN
                . ' e ' . ONGHELP_NOTICIAS_RESUMO_MAX . ' caracteres.';
        }

        if ($textoLen < ONGHELP_NOTICIAS_TEXTO_MIN || $textoLen > ONGHELP_NOTICIAS_TEXTO_MAX) {
            $erros['texto'] = 'O texto deve ter entre ' . ONGHELP_NOTICIAS_TEXTO_MIN
                . ' e ' . number_format(ONGHELP_NOTICIAS_TEXTO_MAX, 0, ',', '.') . ' caracteres.';
        }
    } else {
        if ($tituloLen > ONGHELP_NOTICIAS_TITULO_MAX) {
            $erros['titulo'] = 'O título pode ter no máximo ' . ONGHELP_NOTICIAS_TITULO_MAX . ' caracteres.';
        }

        if ($resumoLen > ONGHELP_NOTICIAS_RESUMO_MAX) {
            $erros['resumo'] = 'O resumo pode ter no máximo ' . ONGHELP_NOTICIAS_RESUMO_MAX . ' caracteres.';
        }

        if ($textoLen > ONGHELP_NOTICIAS_TEXTO_MAX) {
            $erros['texto'] = 'O texto pode ter no máximo '
                . number_format(ONGHELP_NOTICIAS_TEXTO_MAX, 0, ',', '.') . ' caracteres.';
        }
    }

    $eventoDataBruta = trim(noticiaValorTexto($dados['evento_data'] ?? ''));
    $eventoData = noticiaNormalizarDataHora($eventoDataBruta);
    $eventoLocal = noticiaNormalizarEspacos(noticiaValorTexto($dados['evento_local'] ?? ''));

    if ($eventoDataBruta !== '' && $eventoData === null) {
        $erros['evento_data'] = 'Informe uma data e hora válidas para o evento.';
    }

    if ($tipo === 'evento') {
        if ($publicando && $eventoData === null) {
            $erros['evento_data'] = 'Informe a data e o horário do evento.';
        }

        $eventoLocalLen = mb_strlen($eventoLocal, 'UTF-8');

        if ($publicando && $eventoLocalLen < ONGHELP_NOTICIAS_EVENTO_LOCAL_MIN) {
            $erros['evento_local'] = 'Informe o local do evento.';
        }

        if ($eventoLocalLen > ONGHELP_NOTICIAS_EVENTO_LOCAL_MAX) {
            $erros['evento_local'] = 'O local do evento pode ter no máximo '
                . ONGHELP_NOTICIAS_EVENTO_LOCAL_MAX . ' caracteres.';
        }
    } else {
        $eventoData = null;
        $eventoLocal = '';
    }

    $projetoBruto = trim(noticiaValorTexto($dados['projeto_id'] ?? ''));
    $movimentacaoBruta = trim(noticiaValorTexto($dados['movimentacao_id'] ?? ''));
    $projetoId = noticiaIdOpcional($projetoBruto);
    $movimentacaoId = noticiaIdOpcional($movimentacaoBruta);

    if ($projetoBruto !== '' && $projetoId === null) {
        $erros['projeto'] = 'O projeto selecionado é inválido.';
    }

    if ($movimentacaoBruta !== '' && $movimentacaoId === null) {
        $erros['movimentacao'] = 'A movimentação selecionada é inválida.';
    }

    if ($projetoId !== null && $movimentacaoId !== null) {
        $erros['vinculo'] = 'Escolha um projeto OU uma movimentação, não os dois.';
    }

    if ($projetoId !== null) {
        $stmt = $pdo->prepare('SELECT 1 FROM projetos WHERE id = ? AND usuario_id = ? LIMIT 1');
        $stmt->execute([$projetoId, $ongId]);

        if ($stmt->fetchColumn() === false) {
            $erros['projeto'] = 'O projeto selecionado não pertence à sua ONG.';
        }
    }

    if ($movimentacaoId !== null) {
        $stmt = $pdo->prepare('SELECT 1 FROM movimentacoes WHERE id = ? AND usuario_id = ? LIMIT 1');
        $stmt->execute([$movimentacaoId, $ongId]);

        if ($stmt->fetchColumn() === false) {
            $erros['movimentacao'] = 'A movimentação selecionada não pertence à sua ONG.';
        }
    }

    $linksEntrada = $dados['links'] ?? [];

    if (!is_array($linksEntrada)) {
        $linksEntrada = [];
    }

    $links = [];
    $urlsVistas = [];

    foreach ($linksEntrada as $item) {
        $url = is_array($item)
            ? noticiaValorTexto($item['url'] ?? '')
            : noticiaValorTexto($item);

        if (trim($url) === '') {
            continue;
        }

        $info = noticiaNormalizarLink($url);

        if (!$info['ok']) {
            if (!isset($erros['links'])) {
                $erros['links'] = $info['erro'];
            }

            continue;
        }

        if (isset($urlsVistas[$info['url']])) {
            continue;
        }

        $urlsVistas[$info['url']] = true;
        $links[] = $info;
    }

    if (count($links) > ONGHELP_NOTICIAS_LINKS_MAX) {
        $erros['links'] = 'Adicione no máximo ' . ONGHELP_NOTICIAS_LINKS_MAX . ' links.';
    }

    // O editor informa as fotos como ficarão depois de salvar
    // (existentes mantidas + novas enviadas). Sem essa informação,
    // vale o que está gravado no banco.
    if (isset($dados['fotos_resumo']) && is_array($dados['fotos_resumo'])) {
        $fotosCount = (int) ($dados['fotos_resumo']['total'] ?? 0);
        $fotosSemAlt = (int) ($dados['fotos_resumo']['sem_alt'] ?? 0);
    } else {
        $fotosCount = 0;
        $fotosSemAlt = 0;

        if ($noticiaId !== null && $noticiaId > 0 && $noticiaAtual) {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) AS total,
                        COALESCE(SUM(CHAR_LENGTH(TRIM(texto_alternativo)) < ?), 0) AS sem_alt
                 FROM noticias_fotos
                 WHERE noticia_id = ?'
            );
            $stmt->execute([ONGHELP_NOTICIAS_FOTO_ALT_MIN, $noticiaId]);
            $contagem = $stmt->fetch();
            $fotosCount = (int) ($contagem['total'] ?? 0);
            $fotosSemAlt = (int) ($contagem['sem_alt'] ?? 0);
        }
    }

    if ($fotosCount > ONGHELP_NOTICIAS_FOTOS_MAX) {
        $erros['fotos'] = 'A notícia pode ter no máximo ' . ONGHELP_NOTICIAS_FOTOS_MAX . ' fotos.';
    } elseif ($publicando && $fotosCount < ONGHELP_NOTICIAS_FOTOS_MIN_PUBLICAR) {
        $erros['fotos'] = 'Envie ao menos ' . ONGHELP_NOTICIAS_FOTOS_MIN_PUBLICAR
            . ' foto para publicar a notícia.';
    } elseif ($publicando && $fotosSemAlt > 0) {
        $erros['fotos'] = 'Todas as fotos precisam de uma descrição com pelo menos '
            . ONGHELP_NOTICIAS_FOTO_ALT_MIN . ' caracteres para publicar.';
    }

    $direitos = !empty($dados['direitos_imagem']);
    $aceiteAnterior = (string) ($noticiaAtual['direitos_imagem_aceito_em'] ?? '');

    if ($publicando && !$direitos && $aceiteAnterior === '') {
        $erros['direitos_imagem'] = 'Confirme a autorização de uso de imagem para publicar.';
    }

    $urlsParaPix = array_map(static fn (array $link): string => $link['url'], $links);
    $temPix = noticiaDetectarPix($titulo, $resumo, $texto, $urlsParaPix);

    if ($publicando && $temPix) {
        $erros['pix'] = ONGHELP_NOTICIAS_PIX_MENSAGEM;
    }

    if ($publicando) {
        $elegibilidade = ongPodePublicarNoticias($pdo, $ongId);

        if (!$elegibilidade['pode']) {
            $erros['ong'] = $elegibilidade['motivo'];
        }

        $estadoAtual = (string) ($noticiaAtual['estado'] ?? '');

        if ($estadoAtual !== 'publicada'
            && limitePublicacoesNoticiasAtingido($pdo, $ongId, $noticiaId)
        ) {
            $erros['limite'] = noticiaMensagemLimiteDiario();
        }
    }

    $palavras = noticiaContarPalavras($textoPlano);

    return [
        'ok' => $erros === [],
        'erros' => $erros,
        'dados' => [
            'titulo' => $titulo,
            'resumo' => $resumo,
            'texto' => $texto,
            'texto_plano' => $textoPlano,
            'tipo' => $tipo,
            'projeto_id' => $projetoId,
            'movimentacao_id' => $movimentacaoId,
            'evento_data' => $eventoData,
            'evento_local' => $eventoLocal,
            'links' => $links,
            'direitos_imagem' => $direitos,
        ],
        'palavras' => $palavras,
        'tempo_leitura' => noticiaTempoLeitura($textoPlano),
        'tem_pix' => $temPix,
        'noticia_atual' => $noticiaAtual,
    ];
}

// ------------------------------------------------------------
// Denúncias (a gravação e a interface ficam para a parte 8)
// ------------------------------------------------------------

function noticiaHashDenunciante(string $ip, string $userAgent): string
{
    return hash('sha256', trim($ip) . '|' . trim($userAgent));
}

function noticiaDenunciaDuplicada(
    PDO $pdo,
    int $noticiaId,
    string $hashDenunciante,
    bool $somentePendentes = false
): bool {
    if ($noticiaId <= 0 || $hashDenunciante === '') {
        return false;
    }

    $sql = 'SELECT 1 FROM noticias_denuncias WHERE noticia_id = ? AND hash_denunciante = ?';

    if ($somentePendentes) {
        $sql .= " AND estado = 'pendente'";
    }

    $sql .= ' LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$noticiaId, $hashDenunciante]);

    return $stmt->fetchColumn() !== false;
}

function noticiaDenunciasLimiteHora(): int
{
    return 3;
}

function noticiaDenunciasUltimaHora(PDO $pdo, string $hashDenunciante): int
{
    if ($hashDenunciante === '') {
        return 0;
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM noticias_denuncias
         WHERE hash_denunciante = ?
           AND criado_em >= NOW() - INTERVAL 1 HOUR'
    );
    $stmt->execute([$hashDenunciante]);

    return (int) $stmt->fetchColumn();
}

function noticiaRegistrarDenuncia(
    PDO $pdo,
    int $noticiaId,
    string $motivo,
    string $detalhe,
    string $email,
    string $hashDenunciante
): int {
    $stmt = $pdo->prepare(
        'INSERT INTO noticias_denuncias
            (noticia_id, motivo, detalhe, email_denunciante, hash_denunciante)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $noticiaId,
        $motivo,
        $detalhe !== '' ? $detalhe : null,
        $email !== '' ? $email : null,
        $hashDenunciante,
    ]);

    return (int) $pdo->lastInsertId();
}
