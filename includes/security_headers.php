<?php
// ============================================================
// includes/security_headers.php — Headers básicos de segurança
// ============================================================

if (headers_sent()) {
    return;
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

// CSP compatível com os recursos atuais: Bootstrap e ícones via
// jsDelivr, Google Fonts e os estilos/scripts inline já existentes.
// 'unsafe-inline' é necessário enquanto o projeto não separar todo
// o CSS/JS inline em arquivos próprios.
header(
    "Content-Security-Policy: default-src 'self'; "
    . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; "
    . "font-src https://fonts.gstatic.com https://cdn.jsdelivr.net; "
    . "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; "
    . "img-src 'self' data: blob:;"
);
