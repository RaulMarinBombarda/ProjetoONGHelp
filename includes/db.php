<?php
// ============================================================
// includes/db.php — Conexão PDO com o banco MySQL
// Projeto ONGHelp · Projeto Integrador 2026
// ============================================================
//
// Ajuste $host, $user e $pass conforme seu ambiente.
// Em produção, use variáveis de ambiente ou um arquivo .env
// fora da raiz pública — NUNCA exponha credenciais no Git.
// ============================================================

// ============================================================
// Fuso horário e segurança de base
// ============================================================

date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/security_headers.php';
require_once __DIR__ . '/session.php';

iniciarSessao();

$host   = 'localhost';
$dbname = 'onghelp';
$user   = 'root';      // altere se necessário
$pass   = '';          // altere se necessário

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $user,
        $pass,
        [
            // Lança exceção em qualquer erro SQL (facilita debug)
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            // Retorna arrays associativos por padrão
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Desativa emulação de prepared statements (mais seguro)
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    // Em produção, logue o erro e exiba mensagem genérica ao usuário
    error_log('[ONGHelp] Erro de conexão: ' . $e->getMessage());
    die('<p style="font-family:sans-serif;color:#c0392b;padding:2rem;">
            Não foi possível conectar ao banco de dados. Tente novamente mais tarde.
         </p>');
}

// ============================================================
// Erros de query que escapam das páginas
// ============================================================
// O try/catch acima cobre apenas a abertura da conexão. Qualquer
// erro de SQL depois disso — violação de chave, valor inválido,
// coluna NOT NULL recebendo null — sobe como exceção sem tratamento
// e o visitante veria a tela de erro do PHP, com o stack trace e o
// caminho absoluto do servidor expostos na página.
//
// Este handler cobre essa segunda classe de falha em todas as
// páginas de uma vez: registra o detalhe real no error_log() e
// mostra ao visitante uma mensagem genérica. Páginas que precisam
// tratar uma exceção específica usam try/catch local, que tem
// precedência sobre o handler global.
//
// Apenas erros não tratados chegam aqui. Os exit() normais das
// páginas de exportação (CSV e PDF) não são afetados.

set_exception_handler(function (Throwable $e) {
    error_log('[ONGHelp] Erro não tratado: ' . $e->getMessage()
        . ' — em ' . $e->getFile() . ':' . $e->getLine());

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }

    die('<p style="font-family:sans-serif;color:#c0392b;padding:2rem;">
            Não foi possível concluir a operação agora. Tente novamente em alguns instantes.
         </p>');
});
