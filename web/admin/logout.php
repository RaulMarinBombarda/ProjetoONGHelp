<?php
// ============================================================
// admin/logout.php — encerra a sessão da equipe (POST + CSRF)
// ============================================================

require_once __DIR__ . '/../includes/modules/admin-auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    // sair nunca deve acontecer por GET: links e prefetch de
    // navegador não podem encerrar a sessão de ninguém
    if (!empty($_SESSION['admin_id'])) {
        header('Location: index.php');
    } else {
        header('Location: login.php');
    }
    exit;
}

csrfExigir();

if (!empty($_SESSION['admin_id'])) {
    registrarLogAdmin(
        $pdo,
        'logout_admin',
        'admin',
        (int) $_SESSION['admin_id'],
        $_SESSION['admin_nome'] ?? 'Equipe ONGHelp'
    );
}

encerrarLogin('admin');

header('Location: login.php?saiu=1');
exit;
