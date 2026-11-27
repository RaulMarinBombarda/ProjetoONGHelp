<?php
// ============================================================
// includes/core/paths.php — caminhos do disco e URLs do site
// ============================================================
//
// todo endereço interno do site passa por url() e todo arquivo
// de assets/ por asset(), assim o projeto funciona na raiz do
// domínio ou numa subpasta (ex.: localhost/ONGHelp/web) e as páginas
// podem ficar em qualquer nível de pasta sem "../" manual
// ============================================================

/** pasta raiz do projeto no disco */
const ONGHELP_ROOT = __DIR__ . '/../..';

/** pasta dos envios das ONGs no disco e na URL */
const ONGHELP_UPLOADS_DIR = ONGHELP_ROOT . '/uploads';
const ONGHELP_UPLOADS_URL = 'uploads';

/**
 * prefixo relativo da página atual até a raiz do site:
 * '' em index.php, '../' em pages/ e admin/, '../../' em pages/actions/.
 */
function baseUrl(): string
{
    static $base = null;

    if ($base !== null) {
        return $base;
    }

    $raiz   = strtr((string) realpath(ONGHELP_ROOT), DIRECTORY_SEPARATOR, '/');
    $script = strtr((string) realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')), DIRECTORY_SEPARATOR, '/');

    $profundidade = 0;
    if ($raiz !== '' && $script !== '' && str_starts_with($script, $raiz . '/')) {
        $profundidade = substr_count(substr($script, strlen($raiz) + 1), '/');
    }

    return $base = str_repeat('../', $profundidade);
}

/** URL relativa para um caminho a partir da raiz do site */
function url(string $caminho = ''): string
{
    return baseUrl() . ltrim($caminho, '/');
}

/**
 * URL de um arquivo em assets/ com versão automática: o parâmetro
 * ?v= muda sempre que o arquivo é alterado, então o navegador nunca
 * fica com CSS/JS antigo em cache
 */
function asset(string $caminho): string
{
    $caminho = ltrim($caminho, '/');
    $arquivo = ONGHELP_ROOT . '/assets/' . $caminho;
    $versao  = is_file($arquivo) ? '?v=' . filemtime($arquivo) : '';

    return url('assets/' . $caminho) . $versao;
}

/**
 * endereço absoluto (com esquema e host) de um caminho a partir da
 * raiz do site, usado em canonical, Open Graph e links enviados
 * para fora do site (ex.: mensagem com o endereço do painel)
 */
function urlAbsoluta(string $caminho = ''): string
{
    $esquema = function_exists('requisicaoSegura') && requisicaoSegura() ? 'https' : 'http';
    $host    = preg_replace('/[^a-z0-9.\-:\[\]]/i', '', (string) ($_SERVER['HTTP_HOST'] ?? ''));
    $pasta   = dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));

    // sobe da pasta da página atual até a raiz do site
    for ($i = substr_count(baseUrl(), '../'); $i > 0; $i--) {
        $pasta = dirname($pasta);
    }

    $pasta = rtrim(str_replace('\\', '/', $pasta), '/');

    return $esquema . '://' . ($host !== '' ? $host : 'localhost') . $pasta . '/' . ltrim($caminho, '/');
}

/**
 * endereço de uma listagem (ONGs, notícias, transparência) que mantém
 * os filtros da URL atual. $override troca valores; a página só entra
 * quando vem em $override, então mudar um filtro volta à página 1,
 * valores vazios, '0', a página 1 e os valores padrão saem da URL
 */
function urlListagem(string $pagina, array $filtros, array $override = [], array $padroes = []): string
{
    $params = [];
    foreach ($filtros as $chave) {
        $params[$chave] = (string) ($override[$chave] ?? $_GET[$chave] ?? '');
    }
    $params['pagina'] = (int) ($override['pagina'] ?? 0) > 1 ? (string) (int) $override['pagina'] : '';

    $params = array_filter(
        $params,
        static fn (string $v, string $k): bool => $v !== '' && $v !== '0' && $v !== ($padroes[$k] ?? null),
        ARRAY_FILTER_USE_BOTH
    );

    return $pagina . ($params ? '?' . http_build_query($params) : '') . '#lista';
}

/** redireciona para um caminho a partir da raiz do site e encerra */
function redirecionar(string $caminho, int $status = 302): never
{
    header('Location: ' . url($caminho), true, $status);
    exit;
}
