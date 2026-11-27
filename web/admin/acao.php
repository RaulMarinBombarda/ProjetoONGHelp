<?php
// ============================================================
// admin/acao.php — ações da Equipe ONGHelp (somente POST)
// ============================================================

require_once __DIR__ . '/../includes/modules/admin-auth.php';
require_once __DIR__ . '/../includes/modules/notificacoes.php';

$admin = exigirPermissao($pdo, 'ong.analisar');
csrfExigir();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: index.php');
    exit;
}

$id = isset($_POST['id']) && ctype_digit((string) $_POST['id']) ? (int) $_POST['id'] : 0;
if ($id <= 0) {
    flashSet('admin_erro', 'Cadastro inválido.');
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$id]);
$ong = $stmt->fetch();

if (!$ong) {
    flashSet('admin_erro', 'Cadastro não encontrado.');
    header('Location: index.php');
    exit;
}

$acao     = (string) ($_POST['acao'] ?? '');
$voltar   = 'verificar.php?id=' . $id;
$detalhe  = '';

/**
 * salva o checklist da análise no histórico
 */
function salvarVerificacao(
    PDO $pdo,
    int $usuarioId,
    int $adminId,
    array $checklist,
    string $observacoes,
    string $resultado
): void {
    $stmt = $pdo->prepare(
        "INSERT INTO verificacoes (usuario_id, admin_id, checklist_json, observacoes, resultado)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $usuarioId,
        $adminId,
        json_encode($checklist, JSON_UNESCAPED_UNICODE),
        $observacoes !== '' ? $observacoes : null,
        $resultado,
    ]);
}

/**
 * monta o JSON do checklist com todos os itens, marcados ou não
 */
function checklistDoPost(): array
{
    $enviado = $_POST['checklist'] ?? [];
    $json = ['bloqueantes' => [], 'atencao' => []];

    foreach (array_keys(checklistBloqueantes()) as $chave) {
        $json['bloqueantes'][$chave] = !empty($enviado['bloqueantes'][$chave]);
    }
    foreach (array_keys(checklistAtencao()) as $chave) {
        $json['atencao'][$chave] = !empty($enviado['atencao'][$chave]);
    }

    return $json;
}

// de quais status cada decisão pode partir, o painel só mostra o botão
// certo, mas um POST montado à mão não pode, por exemplo, "reativar" uma
// ONG pendente e aprová-la sem o checklist
$origensPermitidas = [
    'recusar'   => ['pendente', 'recusada', 'suspensa'],
    'suspender' => ['aprovada'],
    'reativar'  => ['suspensa'],
];
if (isset($origensPermitidas[$acao]) && !in_array($ong['status'], $origensPermitidas[$acao], true)) {
    flashSet('admin_erro', match ($acao) {
        'recusar'   => 'Uma ONG aprovada não é recusada: use Suspender, com o motivo.',
        'suspender' => 'Só uma ONG aprovada pode ser suspensa.',
        default     => 'Só um perfil suspenso pode ser reativado.',
    });
    header('Location: ' . $voltar);
    exit;
}

switch ($acao) {
    case 'aprovar':
        $checklist = checklistDoPost();

        $faltando = [];
        foreach ($checklist['bloqueantes'] as $chave => $marcado) {
            if (!$marcado) {
                $faltando[] = checklistRotulo($chave);
            }
        }

        if ($faltando) {
            flashSet(
                'admin_erro',
                'Aprovação bloqueada: marque todos os itens obrigatórios. Faltam: ' . implode('; ', $faltando) . '.'
            );
            header('Location: ' . $voltar);
            exit;
        }

        $observacoes = trim($_POST['observacoes'] ?? '');

        $pdo->prepare(
            "UPDATE usuarios
             SET status = 'aprovada',
                 aprovado_em = COALESCE(aprovado_em, NOW()),
                 verificada_em = NOW(),
                 verificado_por = ?,
                 motivo_recusa = NULL
             WHERE id = ?"
        )->execute([(int) $admin['id'], $id]);

        salvarVerificacao($pdo, $id, (int) $admin['id'], $checklist, $observacoes, 'aprovado');

        $detalhe = 'Cadastro aprovado e verificado.';
        registrarLogAdmin($pdo, 'aprovar_ong', 'usuario', $id, $ong['nome'], $detalhe);

        // aprovar de novo uma ONG já aprovada é uma reverificação
        if ($ong['status'] === 'aprovada') {
            notificar($pdo, $id, 'cadastro_reverificado', 'Cadastro verificado novamente',
                'A Equipe ONGHelp conferiu os dados da sua ONG outra vez. O selo do perfil público mostra a nova data.',
                'pages/perfil-publico.php?id=' . $id);
        } else {
            notificar($pdo, $id, 'cadastro_aprovado', 'Cadastro aprovado',
                'A Equipe ONGHelp conferiu os dados da sua ONG. O perfil público já está no ar com o selo de verificação.',
                'pages/perfil-privado.php');
        }
        flashSet('admin_sucesso', 'Cadastro de ' . $ong['nome'] . ' aprovado. O perfil já está público com o selo de verificação.');
        break;

    case 'recusar':
        $motivo = trim($_POST['motivo'] ?? '');
        if ($motivo === '') {
            flashSet('admin_erro', 'Informe o motivo da recusa.');
            header('Location: ' . $voltar);
            exit;
        }

        $checklist = checklistDoPost();

        $pdo->prepare(
            "UPDATE usuarios
             SET status = 'recusada',
                 verificada_em = NULL,
                 verificado_por = ?,
                 motivo_recusa = ?
             WHERE id = ?"
        )->execute([(int) $admin['id'], $motivo, $id]);

        salvarVerificacao($pdo, $id, (int) $admin['id'], $checklist, $motivo, 'recusado');

        registrarLogAdmin($pdo, 'recusar_ong', 'usuario', $id, $ong['nome'], $motivo);
        notificar($pdo, $id, 'cadastro_recusado', 'Cadastro recusado', 'Motivo informado pela equipe: ' . $motivo);
        flashSet('admin_sucesso', 'Cadastro de ' . $ong['nome'] . ' recusado. O motivo foi registrado.');
        break;

    case 'suspender':
        $motivo = trim($_POST['motivo'] ?? '');
        if ($motivo === '') {
            flashSet('admin_erro', 'Informe o motivo da suspensão.');
            header('Location: ' . $voltar);
            exit;
        }

        $checklist = checklistDoPost();

        $pdo->prepare(
            "UPDATE usuarios
             SET status = 'suspensa',
                 verificada_em = NULL,
                 verificado_por = ?,
                 motivo_recusa = ?
             WHERE id = ?"
        )->execute([(int) $admin['id'], $motivo, $id]);

        salvarVerificacao($pdo, $id, (int) $admin['id'], $checklist, $motivo, 'suspenso');

        registrarLogAdmin($pdo, 'suspender_ong', 'usuario', $id, $ong['nome'], $motivo);
        // a ONG suspensa não entra, o aviso fica no histórico para quando voltar
        notificar($pdo, $id, 'cadastro_suspenso', 'Perfil suspenso', 'Motivo informado pela equipe: ' . $motivo);
        flashSet('admin_sucesso', 'Perfil de ' . $ong['nome'] . ' suspenso. Ele saiu do ar imediatamente.');
        break;

    case 'reativar':
        $pdo->prepare(
            "UPDATE usuarios
             SET status = 'aprovada',
                 verificada_em = NOW(),
                 verificado_por = ?,
                 motivo_recusa = NULL
             WHERE id = ?"
        )->execute([(int) $admin['id'], $id]);

        registrarLogAdmin($pdo, 'reativar_ong', 'usuario', $id, $ong['nome'], 'Perfil reativado com nova verificação.');
        notificar($pdo, $id, 'cadastro_reativado', 'Perfil reativado',
            'A Equipe ONGHelp reativou o perfil da sua ONG, que voltou a aparecer no site com uma nova data de verificação.',
            'pages/perfil-publico.php?id=' . $id);
        flashSet('admin_sucesso', 'Perfil de ' . $ong['nome'] . ' reativado com nova data de verificação.');
        break;

    case 'marcar_email_enviado':
        $pdo->prepare(
            "UPDATE usuarios SET email_aprovacao_enviado_em = NOW() WHERE id = ?"
        )->execute([$id]);

        registrarLogAdmin($pdo, 'marcar_email_enviado', 'usuario', $id, $ong['nome'], 'E-mail de aprovação enviado manualmente.');
        flashSet('admin_sucesso', 'E-mail de aprovação marcado como enviado.');
        break;

    case 'alterar_pix':
        $pix = trim($_POST['pix_chave'] ?? '');

        if (!validarPixCnpj($pix, $ong['cnpj'])) {
            flashSet('admin_erro', 'A chave PIX precisa ser exatamente o CNPJ da ONG (14 dígitos).');
            header('Location: ' . $voltar);
            exit;
        }

        $pixDigitos = somenteDigitos($pix);
        $pdo->prepare(
            "UPDATE usuarios
             SET pix_chave = ?,
                 verificada_em = NOW(),
                 verificado_por = ?
             WHERE id = ?"
        )->execute([$pixDigitos, (int) $admin['id'], $id]);

        registrarLogAdmin($pdo, 'alterar_pix', 'usuario', $id, $ong['nome'], 'PIX ajustado para o CNPJ ' . mascaraCnpj($ong['cnpj']) . '.');
        notificar($pdo, $id, 'dados_ajustados', 'Chave PIX ajustada pela equipe',
            'A chave PIX da sua ONG agora é o CNPJ ' . mascaraCnpj($ong['cnpj']) . ', como pedem as regras da plataforma.',
            'pages/perfil-privado.php?secao=verificacao-e-pix');
        flashSet('admin_sucesso', 'Chave PIX atualizada para o CNPJ da ONG.');
        break;

    case 'alterar_responsavel':
        $nome  = trim($_POST['responsavel_nome'] ?? '');
        $cargo = trim($_POST['responsavel_cargo'] ?? '');

        if ($nome === '' || $cargo === '') {
            flashSet('admin_erro', 'Informe o nome e o cargo do responsável legal.');
            header('Location: ' . $voltar);
            exit;
        }

        $pdo->prepare(
            "UPDATE usuarios
             SET responsavel_nome = ?,
                 responsavel_cargo = ?,
                 verificada_em = NOW(),
                 verificado_por = ?
             WHERE id = ?"
        )->execute([$nome, $cargo, (int) $admin['id'], $id]);

        registrarLogAdmin($pdo, 'alterar_responsavel', 'usuario', $id, $ong['nome'], 'Responsável: ' . $nome . ' (' . $cargo . ').');
        notificar($pdo, $id, 'dados_ajustados', 'Responsável legal atualizado pela equipe',
            'O responsável legal da sua ONG agora é ' . $nome . ' (' . $cargo . ').',
            'pages/perfil-privado.php?secao=verificacao-e-pix');
        flashSet('admin_sucesso', 'Responsável legal atualizado.');
        break;

    default:
        flashSet('admin_erro', 'Ação desconhecida.');
        header('Location: index.php');
        exit;
}

header('Location: ' . $voltar);
exit;
