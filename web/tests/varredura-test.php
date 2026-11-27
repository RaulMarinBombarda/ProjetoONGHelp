<?php
// ============================================================
// tests/varredura-test.php — varredura do site inteiro pelo HTTP
// ============================================================
// percorre todas as telas seguindo os links, como visitante, ONG,
// administrador e moderador, e confere em cada uma:
//   - código HTTP, erros do PHP e links internos quebrados
//   - um único <h1>, <title>, lang, viewport, alt nas imagens, ids
//     únicos, <label for> e âncoras (#) que apontam para algo real
//   - nada de script inline nem atributo on* (a CSP bloquearia)
//   - todo formulário POST com token CSRF e todo target=_blank com
//     rel=noopener, CSS, JS e imagens referenciados existem
// e também: headers de segurança, pastas bloqueadas pelo .htaccess,
// scripts em uploads/ que não podem rodar, controle de acesso por
// papel, POST sem CSRF, parâmetros estranhos na URL e dados com HTML
// hostil gravados no banco (XSS) e fórmulas no CSV
//
// uso (Apache e MySQL ligados): php tests/varredura-test.php [base]
// cria uma ONG e um moderador "@teste-e2e.onghelp.test" e apaga
// tudo no final, os dados de demonstração não mudam
// ============================================================

declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/suporte.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/core/paths.php';

$BASE = rtrim($argv[1] ?? 'http://localhost/ONGHelp/web/', '/') . '/';
const DOMINIO_TESTE = '@teste-e2e.onghelp.test';
const ADMIN_EMAIL   = 'equipeonghelp@gmail.com';
const ADMIN_SENHA   = 'EquipeONGHelp@2026';
const SENHA_TESTE   = 'Varredura2026ok';
// marcador de XSS: se aparecer cru em alguma página, a saída não foi escapada
const XSS = '<x-xss data-v="1">\'"';

$db = require __DIR__ . '/../config/database.php';
$pdo = new PDO(
    "mysql:host={$db['host']};dbname={$db['name']};charset={$db['charset']}",
    $db['user'], $db['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

// ------------------------------------------------------------
// dados de teste (criados direto no banco) e limpeza
// ------------------------------------------------------------
$inicio = (string) $pdo->query('SELECT NOW()')->fetchColumn();
$sufixo = (string) random_int(100000, 999999);
$emailOng = "varredura.ong.$sufixo" . DOMINIO_TESTE;
$emailMod = "varredura.mod.$sufixo" . DOMINIO_TESTE;
$arquivoNoUpload = ONGHELP_UPLOADS_DIR . "/comprovantes/varredura-$sufixo.php";

$limpar = static function () use ($pdo, $inicio, $arquivoNoUpload): void {
    @unlink($arquivoNoUpload);
    $like = '%' . DOMINIO_TESTE;
    $ids = $pdo->prepare("SELECT id FROM usuarios WHERE email LIKE ? AND nome LIKE 'Varredura%'");
    $ids->execute([$like]);
    foreach ($ids->fetchAll(PDO::FETCH_COLUMN) as $id) {
        $pdo->prepare("DELETE FROM logs_admin WHERE alvo_tipo = 'usuario' AND alvo_id = ?")->execute([$id]);
        $pdo->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$id]);
    }
    $adm = $pdo->prepare("SELECT id FROM admins WHERE email LIKE ? AND nome LIKE 'Varredura%'");
    $adm->execute([$like]);
    foreach ($adm->fetchAll(PDO::FETCH_COLUMN) as $id) {
        $pdo->prepare('DELETE FROM logs_admin WHERE admin_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM admins WHERE id = ?')->execute([$id]);
    }
    // o login do administrador e as tentativas desta execução
    $idAdmin = (int) $pdo->query('SELECT id FROM admins WHERE email = ' . $pdo->quote(ADMIN_EMAIL))->fetchColumn();
    $pdo->prepare("DELETE FROM logs_admin WHERE criado_em >= ? AND admin_id = ? AND acao IN ('login_admin', 'logout_admin')")->execute([$inicio, $idAdmin]);
    $pdo->prepare('DELETE FROM tentativas_login WHERE criado_em >= ? AND (email = ? OR email LIKE ?)')->execute([$inicio, ADMIN_EMAIL, $like]);
};
$limpar();
register_shutdown_function($limpar);

// ONG aprovada com HTML hostil em todo campo de texto que aparece em público
$cnpj = '9' . str_pad($sufixo, 7, '0', STR_PAD_LEFT) . '000199';
$pdo->prepare(
    "INSERT INTO usuarios (nome, cnpj, email, senha, area, status, aprovado_em, verificada_em, responsavel_nome,
        responsavel_cargo, responsavel_autorizacao, canal_tipo, canal_valor, cidade, estado, descricao, pix_chave)
     VALUES (?, ?, ?, ?, 'Educação', 'aprovada', NOW(), NOW(), ?, 'Presidência', 1, 'instagram', ?, ?, 'SP', ?, ?)"
)->execute([
    'Varredura ' . XSS, $cnpj, $emailOng, password_hash(SENHA_TESTE, PASSWORD_DEFAULT),
    'Responsável ' . XSS, '@ong' . XSS, 'Cidade ' . XSS, "Descrição com HTML hostil\n" . XSS, $cnpj,
]);
$idOng = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO projetos (usuario_id, nome, descricao, status, indicador, meta, unidade, data_inicio)
               VALUES (?, ?, ?, 'andamento', 10, 100, ?, '2026-01-10')")
    ->execute([$idOng, 'Projeto ' . XSS, 'Descrição ' . XSS, 'mudas ' . XSS]);
$idProjeto = (int) $pdo->lastInsertId();
$movs = $pdo->prepare('INSERT INTO movimentacoes (usuario_id, tipo, categoria, projeto_id, descricao, valor, data_mov) VALUES (?, ?, ?, ?, ?, ?, ?)');
$movs->execute([$idOng, 'receita', 'outra', null, '=HYPERLINK("https://evil.example","clique") ' . XSS, 500.00, date('Y-m-d')]);
$movs->execute([$idOng, 'despesa', 'outra', $idProjeto, '+SOMA(1;2) ' . XSS, 120.00, date('Y-m-d')]);
$movs->execute([$idOng, 'despesa', 'outra', null, '@cmd ' . XSS, 30.00, date('Y-m-d')]);
$pdo->prepare(
    "INSERT INTO noticias (usuario_id, titulo, resumo, texto, tipo, estado, slug, projeto_id, publicada_em, imagem_padrao)
     VALUES (?, ?, ?, ?, 'projeto', 'publicada', ?, ?, NOW(), 1)"
)->execute([
    $idOng, 'Notícia ' . XSS, 'Resumo ' . XSS,
    '<p>Texto</p><script>alert(1)</script><p><img src=x onerror=alert(1)><a href="javascript:alert(1)">link</a></p>' . XSS,
    "varredura-$sufixo", $idProjeto,
]);
$idNoticia = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO admins (nome, email, senha, papel, estado, sessao_token, conta_teste) VALUES (?, ?, ?, 'moderador', 'ativo', '', 1)")
    ->execute(['Varredura Moderador', $emailMod, password_hash(SENHA_TESTE, PASSWORD_DEFAULT)]);

// ------------------------------------------------------------
// ferramentas da varredura
// ------------------------------------------------------------

/** resolve um href relativo a partir do endereço da página */
function resolver(string $pagina, string $href): string
{
    if (preg_match('~^[a-z][a-z0-9+.-]*:~i', $href)) {
        return $href;
    }
    $p = parse_url($pagina);
    $raiz = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
    if (str_starts_with($href, '//')) {
        return $p['scheme'] . ':' . $href;
    }
    if (str_starts_with($href, '/')) {
        $caminho = $href;
    } elseif (str_starts_with($href, '?')) {
        $caminho = ($p['path'] ?? '/') . $href;
    } else {
        $dir = preg_replace('~/[^/]*$~', '/', $p['path'] ?? '/');
        $caminho = $dir . $href;
    }
    // resolve ./ e ../
    [$so, $resto] = array_pad(preg_split('~(?=[?#])~', $caminho, 2), 2, '');
    $partes = [];
    foreach (explode('/', $so) as $seg) {
        if ($seg === '..') {
            array_pop($partes);
        } elseif ($seg !== '.') {
            $partes[] = $seg;
        }
    }
    return $raiz . implode('/', $partes) . $resto;
}

/** html → DOMXPath (erros de parser ignorados, o HTML5 tem tags que o libxml não conhece) */
function xpathDe(string $html): DOMXPath
{
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    return new DOMXPath($doc);
}

/** junta problemas por regra: [regra => [url, ...]] */
$problemas = [];
function anotar(string $regra, string $onde): void
{
    global $problemas;
    $problemas[$regra][$onde] = true;
}

/** confere uma página HTML e devolve os links internos dela */
function inspecionar(string $url, array $r, string $base): array
{
    $curto = substr($url, strlen($base));
    if ($r['code'] >= 500) {
        anotar('nenhuma página responde com erro 5xx', "$curto ({$r['code']})");
    }
    if (!semErroPhp($r)) {
        anotar('nenhuma página mostra erro do PHP', $curto);
    }
    if (!str_contains($r['type'], 'text/html') || $r['code'] !== 200) {
        return [];
    }
    if (str_contains($r['body'], XSS) || stripos($r['body'], '<x-xss') !== false) {
        anotar('texto gravado com HTML aparece escapado (sem XSS)', $curto);
    }

    $x = xpathDe($r['body']);
    $h1 = $x->query('//h1')->length;
    if ($h1 !== 1) {
        anotar('cada página tem exatamente um <h1>', "$curto ($h1)");
    }
    if (trim((string) ($x->query('//title')->item(0)?->textContent ?? '')) === '') {
        anotar('cada página tem <title>', $curto);
    }
    if (!preg_match('~<html[^>]+lang="pt-BR"~i', $r['body'])) {
        anotar('cada página declara lang="pt-BR"', $curto);
    }
    if ($x->query('//meta[@name="viewport"]')->length === 0) {
        anotar('cada página tem meta viewport', $curto);
    }
    if ($x->query('//main')->length !== 1 || $x->query('//main[@id="conteudo"]')->length !== 1) {
        anotar('cada página tem um único <main id="conteudo">', $curto . ' (' . $x->query('//main')->length . ')');
    }
    if ($x->query('//nav[not(ancestor::main)]')->length > 0 && $x->query('//a[@href="#conteudo"]')->length === 0) {
        anotar('páginas com menu fora do <main> têm o link "Pular para o conteúdo"', $curto);
    }
    foreach ($x->query('//img[not(@alt)]') as $img) {
        anotar('toda imagem tem atributo alt', $curto . ' ' . $img->getAttribute('src'));
    }
    $ids = [];
    foreach ($x->query('//*[@id]') as $el) {
        $id = $el->getAttribute('id');
        if (isset($ids[$id])) {
            anotar('ids não se repetem na página', "$curto #$id");
        }
        $ids[$id] = true;
    }
    foreach ($x->query('//label[@for]') as $label) {
        if (!isset($ids[$label->getAttribute('for')])) {
            anotar('todo <label for> aponta para um campo que existe', $curto . ' for=' . $label->getAttribute('for'));
        }
    }
    foreach ($x->query('//*[@aria-labelledby or @aria-describedby or @aria-controls]') as $el) {
        foreach (['aria-labelledby', 'aria-describedby', 'aria-controls'] as $attr) {
            foreach (preg_split('/\s+/', trim($el->getAttribute($attr))) as $ref) {
                if ($ref !== '' && !isset($ids[$ref])) {
                    anotar('aria-labelledby/describedby/controls apontam para ids que existem', "$curto $attr=$ref");
                }
            }
        }
    }
    foreach ($x->query('//script[not(@src)]') as $s) {
        if (!in_array(strtolower($s->getAttribute('type')), ['application/json', 'application/ld+json'], true)) {
            anotar('sem script inline (a CSP só aceita arquivos do site)', $curto);
        }
    }
    foreach ($x->query('//@*[starts-with(name(), "on")]') as $attr) {
        anotar('sem atributos on* no HTML (a CSP bloquearia)', "$curto {$attr->nodeName}");
    }
    foreach ($x->query('//a[@target="_blank"]') as $a) {
        if (!str_contains($a->getAttribute('rel'), 'noopener')) {
            anotar('todo link em nova aba usa rel="noopener"', $curto . ' ' . $a->getAttribute('href'));
        }
    }
    foreach ($x->query('//form') as $form) {
        if (strtolower($form->getAttribute('method')) === 'post' && $x->query('.//input[@name="_csrf"]', $form)->length === 0) {
            anotar('todo formulário POST tem token CSRF', $curto . ' → ' . $form->getAttribute('action'));
        }
    }
    foreach ($x->query('//button[not(normalize-space()) and not(@aria-label) and not(@title)]') as $b) {
        if ($x->query('.//*[contains(@class,"visually-hidden")]', $b)->length === 0) {
            anotar('todo botão tem nome acessível', $curto . ' ' . $b->getAttribute('class'));
        }
    }
    // links duplicados de propósito (miniatura ao lado do título) saem da
    // leitura com aria-hidden e tabindex=-1, então não precisam de nome
    foreach ($x->query('//a[@href and not(normalize-space()) and not(@aria-label) and not(@title) and not(.//img[@alt!=""]) and not(@aria-hidden="true")]') as $a) {
        if ($x->query('.//*[contains(@class,"visually-hidden")]', $a)->length === 0) {
            anotar('todo link tem texto ou nome acessível', $curto . ' ' . $a->getAttribute('href'));
        }
    }

    // âncoras da própria página
    foreach ($x->query('//a[starts-with(@href, "#")]') as $a) {
        $alvo = substr($a->getAttribute('href'), 1);
        if ($alvo !== '' && !isset($ids[$alvo]) && $x->query('//*[@name="' . $alvo . '"]')->length === 0) {
            anotar('toda âncora (#) leva a um id que existe', "$curto #$alvo");
        }
    }

    // links e arquivos para seguir
    $saida = ['links' => [], 'assets' => []];
    foreach ($x->query('//a[@href]') as $a) {
        $abs = resolver($url, $a->getAttribute('href'));
        if (str_starts_with($abs, $base)) {
            $saida['links'][] = $abs;
        }
    }
    foreach ($x->query('//link[@rel="stylesheet" or contains(@rel,"icon") or @rel="manifest"]/@href | //script/@src | //img[not(ancestor::template)]/@src | //source[not(ancestor::template)]/@srcset') as $at) {
        $abs = resolver($url, trim(explode(' ', $at->nodeValue)[0]));
        if (str_starts_with($abs, $base)) {
            $saida['assets'][] = $abs;
        }
    }
    return $saida;
}

/**
 * percorre o site a partir das páginas iniciais, no máximo $limite
 * requisições e poucas variações de query string por arquivo
 */
function varrer(Navegador $nav, array $inicio, string $base, int $limite, array $evitar = []): array
{
    $fila = array_map(static fn ($c) => $base . $c, $inicio);
    $vistos = [];
    $porArquivo = [];
    $assets = [];
    $visitadas = 0;
    while ($fila && $visitadas < $limite) {
        $url = array_shift($fila);
        $url = preg_replace('~#.*$~', '', $url);
        if (isset($vistos[$url])) {
            continue;
        }
        $arquivo = preg_replace('~\?.*$~', '', $url);
        foreach ($evitar as $padrao) {
            if (preg_match($padrao, $url)) {
                continue 2;
            }
        }
        if (($porArquivo[$arquivo] ?? 0) >= 6 || !preg_match('~(\.php|/)(\?.*)?$~', $url)) {
            continue;
        }
        $vistos[$url] = true;
        $porArquivo[$arquivo] = ($porArquivo[$arquivo] ?? 0) + 1;
        $r = $nav->req('GET', $url);
        $visitadas++;
        if ($r['code'] === 404 && !preg_match('~[?&](id|ong)=~', $url)) {
            anotar('nenhum link interno leva a uma página inexistente', substr($url, strlen($base)));
        }
        $achados = inspecionar($url, $r, $base);
        foreach ($achados['links'] ?? [] as $l) {
            $fila[] = $l;
        }
        foreach ($achados['assets'] ?? [] as $a) {
            $assets[$a] ??= substr($url, strlen($base));
        }
    }
    return ['paginas' => array_keys($vistos), 'assets' => $assets];
}

/** GET com os headers da resposta */
function cabecalhos(string $url): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 30]);
    $resp = (string) curl_exec($ch);
    $tam = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    $h = [];
    foreach (explode("\r\n", substr($resp, 0, $tam)) as $linha) {
        if (str_contains($linha, ':')) {
            [$k, $v] = explode(':', $linha, 2);
            $h[strtolower(trim($k))] = trim($v);
        }
    }
    return ['code' => $code, 'headers' => $h, 'body' => substr($resp, $tam)];
}

function entrarOng(Navegador $n, string $email, string $senha): bool
{
    $r = $n->enviar('pages/login.php', ['email' => $email, 'senha' => $senha]);
    return $r['code'] === 302 && !str_contains($r['loc'], 'login');
}

function entrarAdmin(Navegador $n, string $email, string $senha): bool
{
    $r = $n->enviar('admin/login.php', ['email' => $email, 'senha' => $senha]);
    return $r['code'] === 302 && !str_contains($r['loc'], 'login');
}

$evitarSempre = ['~/pages/actions/logout\.php~', '~/admin/logout\.php~'];

// ============================================================
secao('1. Varredura como visitante');
// ============================================================
$visitante = new Navegador($BASE);
$inicioPublico = [
    '', 'index.php', 'pages/ongs.php', 'pages/transparencia.php', 'pages/transparencia.php?ong=' . $idOng,
    'pages/noticias.php', 'pages/perfil-publico.php?id=' . $idOng, 'pages/noticia.php?id=' . $idNoticia,
    'pages/legislacao.php', 'pages/contato.php', 'pages/termos.php', 'pages/privacidade.php', 'pages/equipe.php',
    'pages/login.php', 'pages/cadastro.php', 'admin/login.php',
];
foreach (['projetos', 'movimentacoes', 'noticias'] as $aba) {
    $inicioPublico[] = "pages/perfil-publico.php?id=$idOng&aba=$aba";
}
$publico = varrer($visitante, $inicioPublico, $BASE, 260, array_merge($evitarSempre, ['~/pages/actions/~']));
t('a varredura pública visitou páginas suficientes', count($publico['paginas']) >= 40, count($publico['paginas']) . ' páginas');

// ============================================================
secao('2. Varredura como ONG logada');
// ============================================================
$ong = new Navegador($BASE);
t('ONG de teste entra', entrarOng($ong, $emailOng, SENHA_TESTE));
$privadas = ['pages/perfil-privado.php', 'pages/gestao.php', 'pages/minhas-noticias.php', 'pages/noticia-editar.php',
    'pages/notificacoes.php', 'pages/excluir-conta.php', 'pages/gestao.php?editar_mov=0', "pages/noticia-editar.php?id=$idNoticia"];
foreach (['editar-perfil', 'verificacao-e-pix', 'seguranca', 'relatorios'] as $secaoPerfil) {
    $privadas[] = "pages/perfil-privado.php?secao=$secaoPerfil";
}
$daOng = varrer($ong, $privadas, $BASE, 120, array_merge($evitarSempre, ['~/admin/~', '~/pages/actions/~']));
t('a varredura da ONG visitou as telas privadas', count($daOng['paginas']) >= 10, count($daOng['paginas']) . ' páginas');

// ============================================================
secao('3. Varredura como administrador e moderador');
// ============================================================
$adm = new Navegador($BASE);
t('administrador entra', entrarAdmin($adm, ADMIN_EMAIL, ADMIN_SENHA));
$telasAdmin = ['admin/index.php', 'admin/noticias.php', 'admin/avisos.php', 'admin/equipe.php', 'admin/logs.php',
    'admin/minha-conta.php', "admin/verificar.php?id=$idOng", "admin/noticias.php?noticia=$idNoticia"];
$doAdmin = varrer($adm, $telasAdmin, $BASE, 160, array_merge($evitarSempre, ['~/pages/~', '~/index\.php$~', '~ONGHelp/$~']));
t('a varredura do painel visitou as telas da equipe', count($doAdmin['paginas']) >= 10, count($doAdmin['paginas']) . ' páginas');

$mod = new Navegador($BASE);
t('moderador entra', entrarAdmin($mod, $emailMod, SENHA_TESTE));
foreach (['admin/index.php', 'admin/noticias.php', 'admin/logs.php', 'admin/minha-conta.php', "admin/verificar.php?id=$idOng"] as $tela) {
    $r = $mod->get($tela);
    t("moderador abre $tela", $r['code'] === 200 && semErroPhp($r), "código {$r['code']}");
}
foreach (['admin/equipe.php', 'admin/avisos.php'] as $tela) {
    $r = $mod->get($tela);
    t("moderador não abre $tela (só administrador)", $r['code'] === 403 && !tem($r, 'name="acao"'), "código {$r['code']}");
}
$r = $mod->enviar('admin/avisos.php', ['destino' => 'todas', 'titulo' => 'Teste E2E varredura', 'mensagem' => str_repeat('Mensagem de teste ', 5)], 'admin/index.php');
t('moderador não envia aviso nem com POST montado à mão', $r['code'] === 403
    && (int) $pdo->query("SELECT COUNT(*) FROM avisos_equipe WHERE titulo = 'Teste E2E varredura'")->fetchColumn() === 0);

// ============================================================
secao('4. Problemas encontrados nas telas');
// ============================================================
$regras = [
    'nenhuma página responde com erro 5xx', 'nenhuma página mostra erro do PHP', 'nenhum link interno leva a uma página inexistente',
    'texto gravado com HTML aparece escapado (sem XSS)', 'cada página tem exatamente um <h1>', 'cada página tem <title>',
    'cada página declara lang="pt-BR"', 'cada página tem meta viewport',
    'cada página tem um único <main id="conteudo">', 'páginas com menu fora do <main> têm o link "Pular para o conteúdo"', 'toda imagem tem atributo alt', 'ids não se repetem na página',
    'todo <label for> aponta para um campo que existe', 'aria-labelledby/describedby/controls apontam para ids que existem',
    'sem script inline (a CSP só aceita arquivos do site)', 'sem atributos on* no HTML (a CSP bloquearia)',
    'todo link em nova aba usa rel="noopener"', 'todo formulário POST tem token CSRF', 'todo botão tem nome acessível',
    'todo link tem texto ou nome acessível', 'toda âncora (#) leva a um id que existe',
];
foreach ($regras as $regra) {
    $onde = array_keys($problemas[$regra] ?? []);
    t($regra, $onde === [], count($onde) . ': ' . implode(' | ', array_slice($onde, 0, 6)));
}

// ============================================================
secao('5. Arquivos estáticos referenciados');
// ============================================================
$assets = $publico['assets'] + $daOng['assets'] + $doAdmin['assets'];
$quebrados = [];
foreach ($assets as $a => $origem) {
    $r = $visitante->req('GET', $a);
    if ($r['code'] !== 200) {
        $quebrados[] = substr($a, strlen($BASE)) . " ({$r['code']}, em $origem)";
    }
}
t('CSS, JS e imagens referenciados existem', $quebrados === [], implode(' | ', array_slice($quebrados, 0, 8)));
t('foram conferidos arquivos suficientes', count($assets) >= 20, count($assets) . ' arquivos');
foreach (['assets/css/base.css' => 'text/css', 'assets/js/core/ui.js' => 'javascript'] as $arquivo => $tipo) {
    $h = cabecalhos($BASE . $arquivo);
    t("$arquivo sai com o tipo certo", str_contains($h['headers']['content-type'] ?? '', $tipo), $h['headers']['content-type'] ?? '');
}

// ============================================================
secao('6. Headers de segurança');
// ============================================================
foreach (['index.php', 'pages/login.php', 'admin/login.php', 'pages/noticia.php?id=' . $idNoticia] as $pagina) {
    $h = cabecalhos($BASE . $pagina)['headers'];
    $csp = $h['content-security-policy'] ?? '';
    t("$pagina: CSP sem unsafe-inline nos scripts", str_contains($csp, "script-src 'self'") && !preg_match("~script-src[^;]*unsafe~", $csp), $csp);
    t("$pagina: frame-ancestors none e X-Frame-Options DENY", str_contains($csp, "frame-ancestors 'none'") && ($h['x-frame-options'] ?? '') === 'DENY');
    t("$pagina: nosniff", ($h['x-content-type-options'] ?? '') === 'nosniff');
    t("$pagina: Referrer-Policy", ($h['referrer-policy'] ?? '') !== '');
    t("$pagina: não expõe a versão do PHP", !isset($h['x-powered-by']), $h['x-powered-by'] ?? '');
}
$h = cabecalhos($BASE . 'pages/login.php')['headers'];
t('cookie de sessão é HttpOnly e SameSite', preg_match('~httponly~i', $h['set-cookie'] ?? '') === 1 && preg_match('~samesite=lax~i', $h['set-cookie'] ?? '') === 1, $h['set-cookie'] ?? '(sem cookie)');

// ============================================================
secao('7. Pastas e arquivos que o navegador não pode abrir');
// ============================================================
foreach ([
    'config/app.php', 'config/database.php', 'includes/bootstrap.php', 'includes/core/helpers.php',
    'database/onghelp.sql', 'database/remover-contas-teste.sql', 'docs/ONGHelp_Documentacao.docx', 'docs/ONGHelp_Fluxo.drawio',
    'tests/e2e-test.php', 'tests/suporte.php', 'README.md', '.git/config', '.htaccess', '.gitignore', 'uploads/.htaccess',
    'includes/', 'config/', 'uploads/', 'uploads/comprovantes/', 'assets/', 'assets/css/',
] as $caminho) {
    $r = $visitante->get($caminho);
    t("bloqueado: $caminho", in_array($r['code'], [403, 404], true), "código {$r['code']}");
}
// um script que tenha ido parar em uploads/ não pode rodar
file_put_contents($arquivoNoUpload, '<?php echo "EXECUTOU-" . (7 * 6);');
$r = $visitante->get("uploads/comprovantes/varredura-$sufixo.php");
t('PHP dentro de uploads/ não é executado', !str_contains($r['body'], 'EXECUTOU-42') && $r['code'] === 403, "código {$r['code']}");
@unlink($arquivoNoUpload);

// ============================================================
secao('8. Controle de acesso');
// ============================================================
foreach (['pages/perfil-privado.php', 'pages/gestao.php', 'pages/minhas-noticias.php', 'pages/noticia-editar.php',
    'pages/notificacoes.php', 'pages/excluir-conta.php'] as $tela) {
    $r = (new Navegador($BASE))->get($tela);
    t("sem login, $tela leva ao login da ONG", $r['code'] === 302 && str_contains($r['loc'], 'login.php'), "{$r['code']} → {$r['loc']}");
}
foreach (['admin/index.php', 'admin/verificar.php?id=1', 'admin/noticias.php', 'admin/avisos.php', 'admin/equipe.php',
    'admin/logs.php', 'admin/minha-conta.php', 'admin/primeiro-acesso.php'] as $tela) {
    $r = (new Navegador($BASE))->get($tela);
    t("sem login, $tela leva ao login da equipe", $r['code'] === 302 && str_contains($r['loc'], 'login'), "{$r['code']} → {$r['loc']}");
    $r = $ong->get($tela);
    t("ONG logada não entra em $tela", $r['code'] === 302 && str_contains($r['loc'], 'login'), "{$r['code']} → {$r['loc']}");
}
// limite de tentativas: trocar maiúsculas no e-mail não abre mais chances
$emailLimite = "varredura.limite.$sufixo" . DOMINIO_TESTE;
$atacante = new Navegador($BASE);
foreach (['', 'V', 'VA', 'VAR', 'VARR'] as $i => $prefixoMaiusculo) {
    $variante = $prefixoMaiusculo . substr($emailLimite, strlen($prefixoMaiusculo));
    $atacante->enviar('pages/login.php', ['email' => $variante, 'senha' => "errada$i"]);
}
$r = $atacante->enviar('pages/login.php', ['email' => strtoupper($emailLimite), 'senha' => 'errada9']);
t('5 erros com o mesmo e-mail em maiúsculas diferentes bloqueiam o login', tem($r, 'Muitas tentativas'));
$r = (new Navegador($BASE))->enviar('pages/login.php', ['email' => strtoupper($emailOng), 'senha' => SENHA_TESTE]);
t('ONG entra digitando o e-mail em maiúsculas', $r['code'] === 302 && !str_contains($r['loc'], 'login'), "{$r['code']} → {$r['loc']}");

$r = $adm->get('pages/gestao.php');
t('login da equipe não abre a gestão de uma ONG', $r['code'] === 302 && str_contains($r['loc'], 'login'), "{$r['code']} → {$r['loc']}");

// ONG tentando mexer no que é de outra: pega um registro de uma ONG de demonstração
$outra = $pdo->query("SELECT m.id AS mov, m.usuario_id, (SELECT id FROM projetos WHERE usuario_id = m.usuario_id LIMIT 1) AS proj,
                             (SELECT id FROM noticias WHERE usuario_id = m.usuario_id LIMIT 1) AS noticia
                      FROM movimentacoes m JOIN usuarios u ON u.id = m.usuario_id
                      WHERE u.status = 'aprovada' AND u.id <> $idOng LIMIT 1")->fetch();
$antes = $pdo->query("SELECT CONCAT_WS('|', descricao, valor) FROM movimentacoes WHERE id = {$outra['mov']}")->fetchColumn();
$ong->enviar('pages/gestao.php', ['acao' => 'edit_mov', 'mov_id' => $outra['mov'], 'tipo' => 'receita', 'descricao' => 'invadido', 'valor' => '1,00', 'data_mov' => date('Y-m-d')], 'pages/gestao.php');
$ong->enviar('pages/gestao.php', ['acao' => 'del_mov', 'mov_id' => $outra['mov']], 'pages/gestao.php');
t('ONG não edita nem apaga movimentação de outra', $pdo->query("SELECT CONCAT_WS('|', descricao, valor) FROM movimentacoes WHERE id = {$outra['mov']}")->fetchColumn() === $antes);
if ($outra['proj']) {
    $ong->enviar('pages/gestao.php', ['acao' => 'del_proj', 'proj_id' => $outra['proj']], 'pages/gestao.php');
    t('ONG não apaga projeto de outra', (int) $pdo->query("SELECT COUNT(*) FROM projetos WHERE id = {$outra['proj']}")->fetchColumn() === 1);
    $ong->enviar('pages/gestao.php', ['acao' => 'add_mov', 'tipo' => 'despesa', 'descricao' => 'liga projeto alheio', 'valor' => '1,00', 'data_mov' => date('Y-m-d'), 'projeto_id' => $outra['proj']], 'pages/gestao.php');
    t('ONG não liga lançamento a projeto de outra', (int) $pdo->query("SELECT COUNT(*) FROM movimentacoes WHERE usuario_id = $idOng AND projeto_id = {$outra['proj']}")->fetchColumn() === 0);
}
if ($outra['noticia']) {
    $r = $ong->get("pages/noticia-editar.php?id={$outra['noticia']}");
    t('ONG não abre o editor da notícia de outra', $r['code'] !== 200 || !tem($r, 'name="titulo"') || tem($r, 'não encontrada'), "código {$r['code']}");
    foreach (['excluir', 'despublicar', 'publicar'] as $acaoNoticia) {
        $ong->enviar('pages/minhas-noticias.php', ['acao' => $acaoNoticia, 'id' => $outra['noticia'], 'noticia_id' => $outra['noticia']], 'pages/minhas-noticias.php');
    }
    t('ONG não exclui nem despublica notícia de outra', (int) $pdo->query("SELECT COUNT(*) FROM noticias WHERE id = {$outra['noticia']}")->fetchColumn() === 1);
}

// ============================================================
secao('9. POST sem token CSRF é recusado');
// ============================================================
$semToken = [
    ['pages/gestao.php', $ong, ['acao' => 'add_mov', 'tipo' => 'receita', 'descricao' => 'csrf', 'valor' => '5', 'data_mov' => date('Y-m-d')]],
    ['pages/perfil-privado.php', $ong, ['acao' => 'editar', 'nome' => 'csrf']],
    ['pages/notificacoes.php', $ong, ['acao' => 'ler_todas']],
    ['pages/minhas-noticias.php', $ong, ['acao' => 'excluir', 'id' => $idNoticia]],
    ['pages/noticia-editar.php', $ong, ['acao' => 'salvar', 'titulo' => 'csrf']],
    ['pages/excluir-conta.php', $ong, ['confirmar' => '1']],
    ['pages/actions/logout.php', $ong, []],
    ['pages/actions/denunciar-noticia.php', $visitante, ['noticia_id' => $idNoticia, 'motivo' => 'outro']],
    ['admin/acao.php', $adm, ['acao' => 'suspender', 'id' => $idOng, 'motivo' => 'csrf']],
    ['admin/noticias.php', $adm, ['acao' => 'ocultar', 'noticia_id' => $idNoticia]],
    ['admin/avisos.php', $adm, ['destino' => 'todas', 'titulo' => 'csrf']],
    ['admin/equipe.php', $adm, ['acao' => 'criar']],
    ['admin/minha-conta.php', $adm, ['acao' => 'senha']],
    ['pages/login.php', new Navegador($BASE), ['email' => $emailOng, 'senha' => SENHA_TESTE]],
    ['admin/login.php', new Navegador($BASE), ['email' => ADMIN_EMAIL, 'senha' => ADMIN_SENHA]],
    ['pages/cadastro.php', new Navegador($BASE), ['nome' => 'csrf']],
];
// logout e denúncia não mostram a tela de erro: voltam para a página com
// um aviso, sem fazer nada, por isso ali se confere o efeito, não o código
$voltamSemEfeito = ['pages/actions/logout.php', 'pages/actions/denunciar-noticia.php'];
$denunciasAntes = (int) $pdo->query("SELECT COUNT(*) FROM noticias_denuncias WHERE noticia_id = $idNoticia")->fetchColumn();
foreach ($semToken as [$caminho, $quem, $dados]) {
    $quem->get($caminho);   // abre a sessão, como um navegador real
    $r = $quem->req('POST', $caminho, $dados + ['_csrf' => 'token-falso']);
    if (in_array($caminho, $voltamSemEfeito, true)) {
        t("POST com token falso em $caminho só volta, sem efeito", $r['code'] === 302, "código {$r['code']}");
    } else {
        t("POST com token falso em $caminho é recusado", $r['code'] === 403, "código {$r['code']}");
    }
}
t('denúncia com token falso não é registrada', (int) $pdo->query("SELECT COUNT(*) FROM noticias_denuncias WHERE noticia_id = $idNoticia")->fetchColumn() === $denunciasAntes);
t('nada mudou com os POSTs sem token', (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE id = $idOng AND status = 'aprovada'")->fetchColumn() === 1
    && (int) $pdo->query("SELECT COUNT(*) FROM noticias WHERE id = $idNoticia AND estado = 'publicada'")->fetchColumn() === 1
    && (int) $pdo->query("SELECT COUNT(*) FROM movimentacoes WHERE usuario_id = $idOng AND descricao = 'csrf'")->fetchColumn() === 0);
$r = $ong->get('pages/gestao.php');
t('a ONG continua logada depois do logout sem token', $r['code'] === 200);

// ============================================================
secao('10. Parâmetros estranhos na URL');
// ============================================================
$estranhos = [
    'lista'      => ['busca[]' => 'x', 'id[]' => '1', 'ong[]' => '1', 'pagina[]' => '2'],
    'gigante'    => ['id' => '99999999999999999999', 'ong' => '99999999999999999999', 'pagina' => '99999999', 'ano' => '99999'],
    'negativo'   => ['id' => '-1', 'ong' => '-5', 'pagina' => '-3', 'ano' => '-2026'],
    'texto'      => ['id' => "1' OR '1'='1", 'ong' => 'abc', 'pagina' => 'x', 'ordem' => 'nome; DROP TABLE usuarios', 'area' => XSS, 'busca' => XSS, 'cidade' => XSS, 'uf' => XSS, 'aba' => XSS, 'secao' => XSS],
    'longo'      => ['busca' => str_repeat('á', 3000)],
    'nulo'       => ['busca' => "a\0b", 'id' => "1\0"],
];
$paginasComParametro = ['index.php', 'pages/ongs.php', 'pages/transparencia.php', 'pages/noticias.php', 'pages/noticia.php',
    'pages/perfil-publico.php', 'pages/actions/exportar-csv.php', 'pages/actions/relatorio-anual.php', 'pages/legislacao.php'];
foreach ($paginasComParametro as $pagina) {
    $ruins = [];
    foreach ($estranhos as $nome => $params) {
        $r = $visitante->get($pagina . '?' . http_build_query($params));
        if ($r['code'] >= 500 || !semErroPhp($r) || stripos($r['body'], '<x-xss') !== false) {
            $ruins[] = "$nome ({$r['code']})";
        }
    }
    t("$pagina aguenta parâmetros estranhos sem erro nem XSS", $ruins === [], implode(', ', $ruins));
}
foreach (['pages/gestao.php', 'pages/perfil-privado.php', 'pages/noticia-editar.php', 'pages/minhas-noticias.php'] as $pagina) {
    $ruins = [];
    foreach ($estranhos as $nome => $params) {
        $r = $ong->get($pagina . '?' . http_build_query($params + ['editar_mov' => $params['id'] ?? '', 'editar_proj' => $params['id'] ?? '', 'tipo' => $params['ordem'] ?? 'x']));
        if ($r['code'] >= 500 || !semErroPhp($r) || stripos($r['body'], '<x-xss') !== false) {
            $ruins[] = "$nome ({$r['code']})";
        }
    }
    t("$pagina (ONG) aguenta parâmetros estranhos", $ruins === [], implode(', ', $ruins));
}
foreach (['admin/index.php', 'admin/noticias.php', 'admin/logs.php', 'admin/verificar.php', 'admin/equipe.php', 'admin/avisos.php'] as $pagina) {
    $ruins = [];
    foreach ($estranhos as $nome => $params) {
        $r = $adm->get($pagina . '?' . http_build_query($params + ['filtro' => $params['ordem'] ?? '', 'categoria' => $params['busca'] ?? '', 'pessoa' => $params['id'] ?? '', 'de' => $params['ano'] ?? '', 'ate' => $params['ano'] ?? '', 'noticia' => $params['id'] ?? '', 'status' => $params['area'] ?? '']));
        if ($r['code'] >= 500 || !semErroPhp($r) || stripos($r['body'], '<x-xss') !== false) {
            $ruins[] = "$nome ({$r['code']})";
        }
    }
    t("$pagina (equipe) aguenta parâmetros estranhos", $ruins === [], implode(', ', $ruins));
}

// ============================================================
secao('11. Exportações com dados hostis');
// ============================================================
$r = $visitante->get("pages/actions/exportar-csv.php?ong=$idOng&ano=" . date('Y'));
t('CSV sai como arquivo para download', str_contains($r['type'], 'text/csv') && $r['code'] === 200, $r['type']);
$linhas = array_map('str_getcsv', preg_split('/\R/', trim(preg_replace('/^\xEF\xBB\xBF/', '', $r['body']))));
$celulas = array_merge(...$linhas);
$perigosas = array_filter($celulas, static fn ($c) => preg_match('/^[=+\-@\t\r]/', (string) $c) === 1);
t('nenhuma célula do CSV começa com = + - @ (Excel não executa fórmula)', $perigosas === [], implode(' | ', $perigosas));
t('o CSV traz as descrições, protegidas por apóstrofo', str_contains($r['body'], "'=HYPERLINK") && str_contains($r['body'], "'+SOMA") && str_contains($r['body'], "'@cmd"));
$r = $visitante->get("pages/actions/relatorio-anual.php?ong=$idOng&ano=" . date('Y'));
t('relatório anual sai em PDF válido mesmo com texto hostil', $r['code'] === 200 && str_starts_with($r['body'], '%PDF-') && str_contains(substr($r['body'], -64), '%%EOF'), "código {$r['code']} {$r['type']}");
$pdo->exec("UPDATE usuarios SET status = 'suspensa' WHERE id = $idOng");
$r = $visitante->get("pages/actions/exportar-csv.php?ong=$idOng&ano=" . date('Y'));
t('ONG suspensa não exporta CSV', $r['code'] === 404 || !str_contains($r['body'], 'HYPERLINK'), "código {$r['code']}");
$r = $visitante->get("pages/actions/relatorio-anual.php?ong=$idOng&ano=" . date('Y'));
t('ONG suspensa não gera relatório anual', !str_starts_with($r['body'], '%PDF-'), "código {$r['code']}");
foreach (['pages/perfil-publico.php?id=' . $idOng, 'pages/transparencia.php?ong=' . $idOng, 'pages/noticia.php?id=' . $idNoticia] as $p) {
    $r = $visitante->get($p);
    t("ONG suspensa some de $p", $r['code'] === 404 || !str_contains($r['body'], 'Varredura'), "código {$r['code']}");
}
$r = $visitante->get('pages/ongs.php?busca=Varredura');
t('ONG suspensa some da busca', !str_contains($r['body'], 'Varredura &lt;x-xss'));
$pdo->exec("UPDATE usuarios SET status = 'aprovada' WHERE id = $idOng");

// ============================================================
secao('12. Página de erro e métodos');
// ============================================================
$r = $visitante->get('pages/nao-existe.php');
t('página inexistente responde 404', $r['code'] === 404, "código {$r['code']}");
t('o 404 não mostra a versão do servidor', !preg_match('~Apache/\d|PHP/\d|OpenSSL~', $r['body']));
$r = $visitante->req('POST', 'index.php', ['x' => '1']);
t('POST numa página só de leitura não quebra', $r['code'] < 500 && semErroPhp($r), "código {$r['code']}");

placarFinal();
