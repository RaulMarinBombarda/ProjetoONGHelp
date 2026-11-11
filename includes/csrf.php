<?php
// ============================================================
// includes/csrf.php — Tokens CSRF para formulários POST
// ============================================================

function csrfToken(): string
{
    iniciarSessao();

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function csrfValidar(): bool
{
    iniciarSessao();

    $enviado = $_POST['_csrf'] ?? '';
    $sessao  = $_SESSION['csrf_token'] ?? '';

    return is_string($enviado) && $sessao !== '' && hash_equals($sessao, $enviado);
}

function csrfExigir(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !csrfValidar()) {
        http_response_code(403);
        die('Sessão inválida ou expirada. Recarregue a página e tente novamente.');
    }
}
