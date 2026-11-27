<?php
// ============================================================
// includes/core/security-headers.php — headers básicos de segurança
// ============================================================

if (headers_sent()) {
    return;
}

// a versão do PHP não vai no header nem quando o php.ini deixa
// expose_php ligado (padrão do XAMPP)
header_remove('X-Powered-By');

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

// CSP: tudo é servido pelo próprio site (Bootstrap, ícones e fontes
// ficam em assets/libs/), não há script inline nem handler on*=,
// então script-src aceita só 'self', estilos inline continuam
// permitidos porque algumas páginas usam style="--var: ..." para as
// cores escolhidas pela ONG
header(
    "Content-Security-Policy: default-src 'self'; "
    . "script-src 'self'; "
    . "style-src 'self' 'unsafe-inline'; "
    . "font-src 'self'; "
    . "img-src 'self' data: blob:; "
    . "object-src 'none'; "
    . "base-uri 'self'; "
    . "form-action 'self'; "
    . "frame-ancestors 'none'"
);
