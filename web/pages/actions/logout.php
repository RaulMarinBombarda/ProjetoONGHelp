<?php
// ============================================================
// pages/actions/logout.php — encerra a sessão da ONG (POST + CSRF)
// ============================================================
// só aceita POST com token: um link ou imagem de outro site não
// consegue deslogar a ONG sem que ela clique em "Sair"

require_once __DIR__ . '/../../includes/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrfValidar()) {
    redirecionar(isset($_SESSION['user_id']) ? 'pages/perfil-privado.php' : 'pages/login.php');
}

encerrarLogin('user');

redirecionar('pages/login.php?saiu=1');
