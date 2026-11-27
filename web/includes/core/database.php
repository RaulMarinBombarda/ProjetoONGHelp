<?php
// ============================================================
// includes/core/database.php — conexão PDO com o banco MySQL
// ============================================================
//
// carregado pelo bootstrap.php, as credenciais ficam em
// config/database.php
// ============================================================

$dbConfig = require __DIR__ . "/../../config/database.php";

try {
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset={$dbConfig['charset']}",
        $dbConfig['user'],
        $dbConfig['pass'],
        [
            // lança exceção em qualquer erro SQL (facilita debug)
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            // retorna arrays associativos por padrão
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // desativa emulação de prepared statements (mais seguro)
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
    unset($dbConfig);
} catch (PDOException $e) {
    // em produção, logue o erro e exiba mensagem genérica ao usuário
    error_log('[ONGHelp] Erro de conexão: ' . $e->getMessage());
    die('<p style="font-family:sans-serif;color:#c0392b;padding:2rem;">
            Não foi possível conectar ao banco de dados. Tente novamente mais tarde.
         </p>');
}

// ============================================================
// erros de query que escapam das páginas
// ============================================================
// o try/catch acima cobre apenas a abertura da conexão, qualquer
// erro de SQL depois disso — violação de chave, valor inválido,
// coluna NOT NULL recebendo null — sobe como exceção sem tratamento
// e o visitante veria a tela de erro do PHP, com o stack trace e o
// caminho absoluto do servidor expostos na página
//
// este handler cobre essa segunda classe de falha em todas as
// páginas de uma vez: registra o detalhe real no error_log() e
// mostra ao visitante uma mensagem genérica, páginas que precisam
// tratar uma exceção específica usam try/catch local, que tem
// precedência sobre o handler global
//
// apenas erros não tratados chegam aqui, os exit() normais das
// páginas de exportação (CSV e PDF) não são afetados

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
