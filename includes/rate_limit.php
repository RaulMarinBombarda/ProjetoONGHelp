<?php
// ============================================================
// includes/rate_limit.php — Limite de tentativas de login
// ============================================================

function ipCliente(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

function limparTentativasAntigas(PDO $pdo): void
{
    // Limpeza oportunista: 1 em cada 20 chamadas.
    if (random_int(1, 20) !== 1) {
        return;
    }

    $pdo->exec("DELETE FROM tentativas_login WHERE criado_em < (NOW() - INTERVAL 24 HOUR)");
}

function loginBloqueado(PDO $pdo, string $escopo, string $email, string $ip): bool
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM tentativas_login
         WHERE escopo = ? AND email = ? AND ip = ? AND sucesso = 0
           AND criado_em > (NOW() - INTERVAL 15 MINUTE)"
    );
    $stmt->execute([$escopo, $email, $ip]);
    $porEmail = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM tentativas_login
         WHERE escopo = ? AND ip = ? AND sucesso = 0
           AND criado_em > (NOW() - INTERVAL 1 HOUR)"
    );
    $stmt->execute([$escopo, $ip]);
    $porIp = (int) $stmt->fetchColumn();

    return $porEmail >= 5 || $porIp >= 20;
}

function registrarTentativa(PDO $pdo, string $escopo, string $email, string $ip, bool $sucesso): void
{
    limparTentativasAntigas($pdo);

    if ($sucesso) {
        // Um login bem-sucedido zera o histórico de falhas daquele
        // e-mail/IP, para não punir quem apenas errou a senha antes.
        $stmt = $pdo->prepare(
            "DELETE FROM tentativas_login
             WHERE escopo = ? AND email = ? AND ip = ? AND sucesso = 0"
        );
        $stmt->execute([$escopo, $email, $ip]);
    }

    $stmt = $pdo->prepare(
        "INSERT INTO tentativas_login (escopo, email, ip, sucesso) VALUES (?, ?, ?, ?)"
    );
    $stmt->execute([$escopo, $email, $ip, $sucesso ? 1 : 0]);
}
