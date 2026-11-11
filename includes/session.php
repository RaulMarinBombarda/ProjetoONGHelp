<?php
// ============================================================
// includes/session.php — Sessão, timeout e proteção de páginas
// ============================================================

require_once __DIR__ . '/config.php';

const ONG_SESSAO_INATIVIDADE = 1800;   // 30 minutos
const ONG_SESSAO_ABSOLUTA    = 28800;  // 8 horas
const ADMIN_SESSAO_INATIVIDADE = 1200; // 20 minutos
const ADMIN_SESSAO_ABSOLUTA    = 14400; // 4 horas
const SESSAO_REGENERAR_A_CADA  = 900;  // 15 minutos

/**
 * Lista efetiva de proxies confiáveis. A variável de ambiente tem
 * prioridade para permitir configuração de produção sem editar código.
 */
function proxiesConfiaveis(): string
{
    $env = getenv('ONGHELP_TRUSTED_PROXIES');

    if (is_string($env) && trim($env) !== '') {
        return trim($env);
    }

    return ONGHELP_PROXIES_CONFIAVEIS;
}

/**
 * Compara um IP com uma lista de IPs e faixas CIDR (IPv4 ou IPv6).
 */
function ipEmListaConfiavel(string $ip, string $lista): bool
{
    $ip = trim($ip);

    if ($ip === '' || $lista === '') {
        return false;
    }

    foreach (explode(',', $lista) as $entrada) {
        $entrada = trim($entrada);

        if ($entrada === '') {
            continue;
        }

        if (!str_contains($entrada, '/')) {
            if (hash_equals($entrada, $ip)) {
                return true;
            }
            continue;
        }

        [$rede, $bits] = array_pad(explode('/', $entrada, 2), 2, '');

        if ($bits === '' || !ctype_digit($bits)) {
            continue;
        }

        $ipBin   = @inet_pton($ip);
        $redeBin = @inet_pton($rede);

        if ($ipBin === false || $redeBin === false || strlen($ipBin) !== strlen($redeBin)) {
            continue;
        }

        $bits = (int) $bits;
        $max  = strlen($ipBin) * 8;

        if ($bits < 0 || $bits > $max) {
            continue;
        }

        $bytes = intdiv($bits, 8);
        $sobra = $bits % 8;

        if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($redeBin, 0, $bytes)) {
            continue;
        }

        if ($sobra === 0) {
            return true;
        }

        $mascara = (0xFF << (8 - $sobra)) & 0xFF;

        if ((ord($ipBin[$bytes]) & $mascara) === (ord($redeBin[$bytes]) & $mascara)) {
            return true;
        }
    }

    return false;
}

/**
 * Decide se a requisição chegou por HTTPS.
 *
 * Além de $_SERVER['HTTPS'] e da porta 443, aceita X-Forwarded-Proto
 * somente quando o IP imediato está na lista de proxies confiáveis.
 * Sem essa checagem, qualquer cliente poderia forjar o cabeçalho e
 * fazer o PHP marcar o cookie como Secure em uma conexão HTTP.
 */
function requisicaoSegura(): bool
{
    $https = $_SERVER['HTTPS'] ?? '';

    if ($https !== '' && strtolower((string) $https) !== 'off') {
        return true;
    }

    if ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443') {
        return true;
    }

    $proxies = proxiesConfiaveis();

    if ($proxies === '') {
        return false;
    }

    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

    if (!ipEmListaConfiavel($ip, $proxies)) {
        return false;
    }

    $proto = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));

    return $proto === 'https';
}

function iniciarSessao(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $https = requisicaoSegura();

    // Rejeita IDs de sessão inventados e impede que o PHP aceite o
    // identificador vindo da URL (session fixation).
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function destruirSessao(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $_SESSION = [];

    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );

    session_destroy();
}

function sessaoExpirada(string $prefixo, int $inatividade, int $absoluta): bool
{
    $agora = time();
    $ultima = (int) ($_SESSION[$prefixo . '_last_activity'] ?? 0);
    $criada = (int) ($_SESSION[$prefixo . '_created_at'] ?? 0);

    if ($ultima <= 0 || $criada <= 0) {
        return true;
    }

    return ($agora - $ultima) > $inatividade || ($agora - $criada) > $absoluta;
}

function renovarIdSessao(string $prefixo): void
{
    $ultima = (int) ($_SESSION[$prefixo . '_last_regen'] ?? 0);
    if ($ultima <= 0 || (time() - $ultima) > SESSAO_REGENERAR_A_CADA) {
        session_regenerate_id(true);
        $_SESSION[$prefixo . '_last_regen'] = time();
    }
}

function exigirLoginOng(): void
{
    iniciarSessao();

    if (empty($_SESSION['user_id']) || sessaoExpirada('user', ONG_SESSAO_INATIVIDADE, ONG_SESSAO_ABSOLUTA)) {
        destruirSessao();
        header('Location: login.php?expirada=1');
        exit;
    }

    $_SESSION['user_last_activity'] = time();
    renovarIdSessao('user');
}

function exigirLoginAdmin(): void
{
    iniciarSessao();

    if (empty($_SESSION['admin_id']) || sessaoExpirada('admin', ADMIN_SESSAO_INATIVIDADE, ADMIN_SESSAO_ABSOLUTA)) {
        destruirSessao();
        header('Location: login.php?expirada=1');
        exit;
    }

    $_SESSION['admin_last_activity'] = time();
    renovarIdSessao('admin');
}

function marcarLoginOng(int $id, string $nome, string $area, ?string $foto = null): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $id;
    $_SESSION['user_nome'] = $nome;
    $_SESSION['user_area'] = $area;
    $_SESSION['user_foto'] = $foto;
    $_SESSION['user_created_at'] = time();
    $_SESSION['user_last_activity'] = time();
    $_SESSION['user_last_regen'] = time();
}

function marcarLoginAdmin(
    int $id,
    string $nome,
    string $papel = 'moderador',
    string $sessaoToken = '',
    bool $pendente = false
): void {
    session_regenerate_id(true);
    $_SESSION['admin_id'] = $id;
    $_SESSION['admin_nome'] = $nome;
    $_SESSION['admin_papel'] = $papel;
    $_SESSION['admin_sessao_token'] = $sessaoToken;
    $_SESSION['admin_pendente'] = $pendente;
    $_SESSION['admin_created_at'] = time();
    $_SESSION['admin_last_activity'] = time();
    $_SESSION['admin_last_regen'] = time();
}
