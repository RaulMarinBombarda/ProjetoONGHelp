<?php
// ============================================================
// includes/bootstrap.php — inicialização comum a todas as páginas
// ============================================================
//
// toda página de entrada (index.php, pages/, admin/) começa com:
//
//     require_once __DIR__ . '/../includes/bootstrap.php';
//
// ele carrega, nesta ordem: configurações, caminhos, headers de
// segurança, sessão, CSRF, funções compartilhadas e a conexão com
// o banco ($pdo), módulos específicos (notícias, painel admin,
// PDF) continuam sendo incluídos só pelas páginas que os usam
// ============================================================

date_default_timezone_set('America/Sao_Paulo');

// compacta o HTML gerado pelo PHP (gzip quando o navegador aceita),
// o XAMPP vem sem mod_deflate; assim páginas como a Transparência
// caem de ~340 KB para algumas dezenas de KB sem configurar o Apache
if (PHP_SAPI !== 'cli' && !headers_sent() && extension_loaded('zlib') && !ini_get('zlib.output_compression')) {
    ini_set('zlib.output_compression', '1');
}

// nenhuma página usa listas na URL; um ?busca[]=x quebraria o trim()
$_GET = array_filter($_GET, static fn ($v): bool => !is_array($v));

// no POST, só estes campos são listas de verdade (editor de notícias,
// inclusive os que o noticia-editor.js cria, e análise do cadastro),
// qualquer outro enviado como lista (nome[]=x)
// é descartado: viraria "Array" ou quebraria o trim() da página
$_POST = array_filter(
    $_POST,
    static fn ($v, $k): bool => !is_array($v) || in_array($k, ['links', 'fotos', 'fotos_novas', 'novas_alt', 'novas_legenda', 'pendentes', 'checklist'], true),
    ARRAY_FILTER_USE_BOTH
);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/core/paths.php';
require_once __DIR__ . '/core/security-headers.php';
require_once __DIR__ . '/core/session.php';

iniciarSessao();

require_once __DIR__ . '/core/csrf.php';
require_once __DIR__ . '/core/helpers.php';
require_once __DIR__ . '/core/senha.php';
require_once __DIR__ . '/core/database.php';
