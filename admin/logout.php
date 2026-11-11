<?php
// ============================================================
// admin/logout.php — Encerra a sessão da equipe (POST + CSRF)
// ============================================================

require_once '../includes/admin_auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    // Sair nunca deve acontecer por GET: links e prefetch de
    // navegador não podem encerrar a sessão de ninguém.
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

destruirSessao();

header('Location: login.php?saiu=1');
exit;
