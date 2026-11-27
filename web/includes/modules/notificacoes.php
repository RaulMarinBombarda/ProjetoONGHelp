<?php
// ============================================================
// includes/modules/notificacoes.php — avisos no painel da ONG
// ============================================================
//
// há dois jeitos de um aviso chegar à ONG:
//
// 1. automático, gerado pelo próprio sistema quando a equipe aprova,
//    suspende ou reativa o cadastro, ajusta dados da ONG ou modera
//    notícias (chamadas a notificar() em admin/acao.php e afins)
// 2. escrito à mão pelo administrador em admin/avisos.php, para uma
//    ONG ou para todas as aprovadas, cada envio fica em avisos_equipe
//    e as cópias em notificacoes apontam para ele (aviso_id), assim o
//    painel mostra quantas ONGs já leram
//
// a ONG vê o sino no topo do site (includes/layout/header.php) e a
// lista completa em pages/notificacoes.php
//
// o link é guardado a partir da raiz do site (ex.: pages/gestao.php)
// e montado com url() na hora de exibir
// ============================================================

/** grava um aviso para a ONG, falha aqui nunca derruba a ação da equipe */
function notificar(PDO $pdo, int $usuarioId, string $tipo, string $titulo, string $mensagem = '', ?string $link = null): void
{
    try {
        $pdo->prepare(
            'INSERT INTO notificacoes (usuario_id, tipo, titulo, mensagem, link) VALUES (?, ?, ?, ?, ?)'
        )->execute([
            $usuarioId,
            mb_substr($tipo, 0, 40),
            mb_substr($titulo, 0, 120),
            mb_substr($mensagem, 0, 500),
            $link !== null && notificacaoLinkValido($link) ? $link : null,
        ]);
    } catch (Throwable $erro) {
        error_log('[ONGHelp] Notificação não gravada: ' . $erro->getMessage());
    }
}

/** só caminhos internos do site, nunca um endereço de fora */
function notificacaoLinkValido(string $link): bool
{
    return preg_match('~^(index\.php|pages/[a-z0-9-]+\.php)(\?[A-Za-z0-9_=&%-]*)?(#[A-Za-z0-9_-]*)?$~', $link) === 1;
}

function notificacoesNaoLidas(PDO $pdo, int $usuarioId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM notificacoes WHERE usuario_id = ? AND lida_em IS NULL');
    $stmt->execute([$usuarioId]);

    return (int) $stmt->fetchColumn();
}

/** mais recentes primeiro, $limite 0 traz todas */
function notificacoesDaOng(PDO $pdo, int $usuarioId, int $limite = 0): array
{
    $sql = 'SELECT * FROM notificacoes WHERE usuario_id = ? ORDER BY criado_em DESC, id DESC'
        . ($limite > 0 ? ' LIMIT ' . $limite : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$usuarioId]);

    return $stmt->fetchAll();
}

/** marca uma (com $id) ou todas como lidas, sempre só as da própria ONG */
function marcarNotificacoesLidas(PDO $pdo, int $usuarioId, ?int $id = null): void
{
    $sql = 'UPDATE notificacoes SET lida_em = NOW() WHERE usuario_id = ? AND lida_em IS NULL'
        . ($id !== null ? ' AND id = ?' : '');
    $pdo->prepare($sql)->execute($id !== null ? [$usuarioId, $id] : [$usuarioId]);
}

/** ícone de cada tipo de aviso (Bootstrap Icons) */
function notificacaoIcone(string $tipo): string
{
    return [
        'cadastro_aprovado'      => 'bi-patch-check-fill',
        'cadastro_reverificado'  => 'bi-patch-check',
        'cadastro_reativado'     => 'bi-arrow-counterclockwise',
        'cadastro_suspenso'      => 'bi-pause-circle',
        'cadastro_recusado'      => 'bi-x-circle',
        'dados_ajustados'        => 'bi-pencil-square',
        'noticia_ocultada'       => 'bi-eye-slash',
        'noticia_restaurada'     => 'bi-eye',
        'publicacoes_bloqueadas' => 'bi-lock',
        'publicacoes_liberadas'  => 'bi-unlock',
        'aviso_plataforma'       => 'bi-megaphone',
    ][$tipo] ?? 'bi-bell';
}

// ── avisos escritos pela equipe (admin/avisos.php) ──────────

const AVISO_TITULO_MIN   = 5;
const AVISO_TITULO_MAX   = 120;
const AVISO_MENSAGEM_MIN = 10;
const AVISO_MENSAGEM_MAX = 500;

/**
 * páginas que o botão "Abrir" do aviso pode levar, a lista é fechada
 * para que nenhum aviso aponte para fora do site
 */
function avisoDestinosLink(): array
{
    return [
        ''                                              => 'Sem link, só o texto',
        'pages/gestao.php'                              => 'Gestão financeira',
        'pages/minhas-noticias.php'                     => 'Minhas notícias',
        'pages/perfil-privado.php'                      => 'Meu perfil, visão geral',
        'pages/perfil-privado.php?secao=editar-perfil'  => 'Meu perfil, editar dados',
        'pages/perfil-privado.php?secao=verificacao-e-pix' => 'Meu perfil, verificação e PIX',
        'pages/perfil-privado.php?secao=seguranca'      => 'Meu perfil, segurança e senha',
        'pages/termos.php'                              => 'Termos de Uso',
        'pages/privacidade.php'                         => 'Política de Privacidade',
    ];
}

/**
 * grava o aviso e entrega uma cópia para cada ONG aprovada, ou só para
 * a ONG escolhida, devolve quantas ONGs receberam (0 se nenhuma, e
 * nesse caso nada é gravado)
 *
 * só ONGs aprovadas recebem, porque pendentes, recusadas e suspensas
 * não conseguem entrar no painel para ler
 */
function enviarAvisoEquipe(PDO $pdo, int $adminId, ?int $usuarioId, string $titulo, string $mensagem, ?string $link): int
{
    $pdo->beginTransaction();

    try {
        $pdo->prepare(
            'INSERT INTO avisos_equipe (admin_id, destino, usuario_id, titulo, mensagem, link) VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$adminId, $usuarioId === null ? 'todas' : 'ong', $usuarioId, $titulo, $mensagem, $link]);
        $avisoId = (int) $pdo->lastInsertId();

        $sql = "INSERT INTO notificacoes (usuario_id, tipo, titulo, mensagem, link, aviso_id)
                SELECT id, 'aviso_plataforma', ?, ?, ?, ? FROM usuarios WHERE status = 'aprovada'"
            . ($usuarioId === null ? '' : ' AND id = ?');
        $parametros = [$titulo, $mensagem, $link, $avisoId];
        if ($usuarioId !== null) {
            $parametros[] = $usuarioId;
        }
        $insercao = $pdo->prepare($sql);
        $insercao->execute($parametros);
        $total = $insercao->rowCount();

        if ($total === 0) {
            $pdo->rollBack();
            return 0;
        }

        $pdo->prepare('UPDATE avisos_equipe SET destinatarios = ? WHERE id = ?')->execute([$total, $avisoId]);
        $pdo->commit();

        return $total;
    } catch (Throwable $erro) {
        $pdo->rollBack();
        throw $erro;
    }
}

/** últimos envios com autor, ONG de destino e quantas cópias já foram lidas */
function avisosEnviados(PDO $pdo, int $limite = 30): array
{
    return $pdo->query(
        'SELECT a.*, ad.nome AS admin_nome, u.nome AS ong_nome,
                (SELECT COUNT(*) FROM notificacoes n WHERE n.aviso_id = a.id AND n.lida_em IS NOT NULL) AS lidas
         FROM avisos_equipe a
         LEFT JOIN admins ad ON ad.id = a.admin_id
         LEFT JOIN usuarios u ON u.id = a.usuario_id
         ORDER BY a.criado_em DESC, a.id DESC
         LIMIT ' . max(1, $limite)
    )->fetchAll();
}

/** "agora", "há 5 min", "há 3 h", "ontem" ou a data */
function notificacaoQuando(string $dataHora): string
{
    $segundos = time() - strtotime($dataHora);

    return match (true) {
        $segundos < 60     => 'agora',
        $segundos < 3600   => 'há ' . intdiv($segundos, 60) . ' min',
        $segundos < 86400  => 'há ' . intdiv($segundos, 3600) . ' h',
        $segundos < 172800 => 'ontem',
        default            => fmtData($dataHora),
    };
}
