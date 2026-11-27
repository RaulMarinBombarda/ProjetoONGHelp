<?php
// ============================================================
// includes/modules/admin-auth.php — autenticação, papéis e log da equipe
// ============================================================

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../core/rate-limit.php';

/**
 * permissões por papel, a lista é intencionalmente declarativa:
 * para acrescentar uma capacidade (por exemplo, moderar notícias),
 * basta criar a chave e incluí-la nos papéis que podem exercê-la
 */
function permissoesPorPapel(): array
{
    return [
        'administrador' => [
            'painel.acessar',
            'ong.analisar',
            'noticias.moderar',
            'logs.ver',
            'equipe.gerenciar',
            'avisos.enviar',
            'conta.propria',
        ],
        'moderador' => [
            'painel.acessar',
            'ong.analisar',
            'noticias.moderar',
            'logs.ver',
            'conta.propria',
        ],
    ];
}

function adminPode(array $admin, string $permissao): bool
{
    $papel = (string) ($admin['papel'] ?? '');
    $permissoes = permissoesPorPapel()[$papel] ?? [];

    return in_array($permissao, $permissoes, true);
}

/**
 * devolve a conta da sessão sem exigir estado ativo, o token de sessão
 * muda quando a senha é redefinida ou a conta é desativada, o que
 * invalida imediatamente todas as sessões abertas daquela conta
 */
function adminContaSessao(PDO $pdo): ?array
{
    $id = (int) ($_SESSION['admin_id'] ?? 0);
    if ($id <= 0) {
        return null;
    }

    $stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
    $stmt->execute([$id]);
    $admin = $stmt->fetch();

    if (!$admin) {
        return null;
    }

    $tokenBanco  = (string) ($admin['sessao_token'] ?? '');
    $tokenSessao = (string) ($_SESSION['admin_sessao_token'] ?? '');

    if ($tokenBanco === '' || $tokenSessao === '' || !hash_equals($tokenBanco, $tokenSessao)) {
        return null;
    }

    return $admin;
}

/**
 * exige sessão administrativa válida, conta ativa e token de sessão,
 * a conta é revalidada no banco em toda requisição
 */
function exigirAdminAtivo(PDO $pdo): array
{
    exigirLoginAdmin();

    $admin = adminContaSessao($pdo);

    if (!$admin) {
        encerrarLogin('admin');
        header('Location: login.php?expirada=1');
        exit;
    }

    if ($admin['estado'] === 'desativado') {
        encerrarLogin('admin');
        header('Location: login.php?encerrada=1');
        exit;
    }

    if ($admin['estado'] !== 'ativo') {
        if (!empty($_SESSION['admin_pendente'])) {
            header('Location: primeiro-acesso.php');
            exit;
        }

        encerrarLogin('admin');
        header('Location: login.php?expirada=1');
        exit;
    }

    $_SESSION['admin_nome'] = $admin['nome'];
    $_SESSION['admin_papel'] = $admin['papel'];
    unset($_SESSION['admin_pendente']);

    return $admin;
}

/**
 * exige a sessão criada no login com senha temporária e a conta ainda
 * no estado "aguardando primeiro acesso"
 */
function exigirPrimeiroAcesso(PDO $pdo): array
{
    exigirLoginAdmin();

    $admin = adminContaSessao($pdo);

    if (!$admin) {
        encerrarLogin('admin');
        header('Location: login.php?expirada=1');
        exit;
    }

    if ($admin['estado'] === 'desativado') {
        encerrarLogin('admin');
        header('Location: login.php?encerrada=1');
        exit;
    }

    if ($admin['estado'] === 'ativo') {
        header('Location: index.php');
        exit;
    }

    if ($admin['estado'] !== 'aguardando' || empty($_SESSION['admin_pendente'])) {
        encerrarLogin('admin');
        header('Location: login.php?expirada=1');
        exit;
    }

    $expiraEm = trim((string) ($admin['senha_temporaria_expira_em'] ?? ''));
    if ($expiraEm === '' || strtotime($expiraEm) === false || strtotime($expiraEm) <= time()) {
        encerrarLogin('admin');
        header('Location: login.php?temporaria_expirada=1');
        exit;
    }

    return $admin;
}

/**
 * exige uma capacidade específica, a negativa é uma página dentro do
 * casco do admin e fica registrada nos logs
 */
function exigirPermissao(PDO $pdo, string $permissao): array
{
    $admin = exigirAdminAtivo($pdo);

    if (!adminPode($admin, $permissao)) {
        negarPermissao($pdo, $admin, $permissao);
    }

    return $admin;
}

function negarPermissao(PDO $pdo, array $admin, string $permissao): void
{
    http_response_code(403);

    registrarLogAdmin(
        $pdo,
        'acesso_negado',
        'pagina',
        null,
        basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'painel')),
        'Permissão necessária: ' . $permissao . '.'
    );

    $admin_page_title    = 'Permissão negada';
    $admin_page_subtitle = 'Esta área não está disponível para o seu papel.';
    $admin_active        = '';

    include __DIR__ . '/../layout/admin-header.php';
    ?>
    <div class="admin-denied-wrap">
      <div class="admin-card admin-denied-panel">
        <span class="admin-denied-icon" aria-hidden="true"><i class="bi bi-lock-fill"></i></span>
        <h2 class="admin-denied-title">Você não tem permissão para acessar esta área</h2>
        <p class="admin-denied-text">Esta área é restrita ao administrador.</p>
        <a href="index.php" class="btn btn-primary">Voltar à visão geral</a>
      </div>
    </div>
    <?php
    include __DIR__ . '/../layout/admin-footer.php';
    exit;
}

/**
 * registra uma ação administrativa com autoria individual quando a
 * conta é pessoal, a conta compartilhada continua registrada como
 * "Equipe ONGHelp", preservando todo o histórico anterior
 */
function registrarLogAdmin(
    PDO $pdo,
    string $acao,
    ?string $alvoTipo = null,
    ?int $alvoId = null,
    ?string $alvoNome = null,
    string $detalhe = ''
): void {
    $stmt = $pdo->prepare(
        "INSERT INTO logs_admin (admin_id, acao, alvo_tipo, alvo_id, alvo_nome, detalhe, ip)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        (int) ($_SESSION['admin_id'] ?? 0) ?: null,
        $acao,
        $alvoTipo,
        $alvoId,
        $alvoNome,
        $detalhe !== '' ? $detalhe : null,
        ipCliente(),
    ]);
}

/**
 * definição única do checklist usado na tela e na validação do servidor
 */
function checklistBloqueantes(): array
{
    return [
        'cnpj_ativo'           => 'CNPJ ativo na Receita Federal',
        'razao_social'         => 'Razão social confere com o cadastro',
        'natureza_juridica'    => 'Natureza jurídica compatível (associação/fundação)',
        'cnae_area'            => 'CNAE compatível com a área de atuação',
        'cidade_uf'            => 'Cidade/UF conferem com o endereço da Receita',
        'canal_publico'        => 'Canal público localizado e ativo',
        'contato_confirmado'   => 'Contato feito pelo canal oficial',
        'responsavel_confere'  => 'Responsável legal confere com fontes públicas',
        'responsavel_autorizou' => 'Responsável autorizou o cadastro',
        'pix_titular'          => 'PIX CNPJ pertence à própria ONG',
        'nome_unico'           => 'Não há cadastro duplicado ou nome parecido suspeito',
    ];
}

function checklistAtencao(): array
{
    return [
        'email_generico'      => 'E-mail genérico (ex.: contato@, adm@)',
        'cnpj_recente'        => 'CNPJ aberto há menos de 1 ano',
        'endereco_divergente' => 'Endereço divergente do cadastro',
        'nome_parecido'       => 'Nome parecido com outra ONG já cadastrada',
        'canal_fraco'         => 'Canal público com pouca atividade',
        'sem_mapa_osc'        => 'Não localizada no Mapa das OSCs',
        'pix_recente'         => 'Chave PIX recente ou recém-alterada',
    ];
}

function checklistRotulo(string $chave): string
{
    return checklistBloqueantes()[$chave] ?? checklistAtencao()[$chave] ?? $chave;
}

/**
 * rótulos, ícones e cores das ações registradas em logs_admin
 */
function rotulosAcaoAdmin(): array
{
    return [
        'login_admin'              => ['Login no painel', 'bi-box-arrow-in-right', 'text-primary'],
        'logout_admin'             => ['Saída do painel', 'bi-box-arrow-right', 'text-secondary'],
        'aprovar_ong'              => ['Cadastro aprovado', 'bi-patch-check', 'text-success'],
        'recusar_ong'              => ['Cadastro recusado', 'bi-x-octagon', 'text-danger'],
        'suspender_ong'            => ['Perfil suspenso', 'bi-pause-circle', 'text-warning'],
        'reativar_ong'             => ['Perfil reativado', 'bi-arrow-counterclockwise', 'text-success'],
        'marcar_email_enviado'     => ['E-mail marcado como enviado', 'bi-envelope-check', 'text-primary'],
        'alterar_pix'              => ['PIX alterado', 'bi-key', 'text-secondary'],
        'alterar_responsavel'      => ['Responsável alterado', 'bi-person-gear', 'text-secondary'],
        'conta_criada'             => ['Conta de moderador criada', 'bi-person-plus', 'text-primary'],
        'senha_redefinida'         => ['Senha redefinida', 'bi-key', 'text-secondary'],
        'conta_desativada'         => ['Conta desativada', 'bi-person-slash', 'text-danger'],
        'conta_reativada'          => ['Conta reativada', 'bi-person-check', 'text-success'],
        'senha_alterada'           => ['Senha alterada pela própria pessoa', 'bi-shield-lock', 'text-secondary'],
        'primeiro_acesso_concluido'=> ['Primeiro acesso concluído', 'bi-person-check', 'text-success'],
        'acesso_negado'            => ['Acesso negado', 'bi-shield-x', 'text-danger'],
        'denuncia_recebida'        => ['Denúncia recebida', 'bi-megaphone', 'text-warning'],
        'noticia_ocultada'         => ['Notícia ocultada', 'bi-eye-slash', 'text-danger'],
        'noticia_restaurada'       => ['Notícia restaurada', 'bi-arrow-counterclockwise', 'text-success'],
        'denuncia_improcedente'    => ['Denúncia improcedente', 'bi-check2-circle', 'text-secondary'],
        'publicacoes_bloqueadas'   => ['Publicações bloqueadas', 'bi-slash-circle', 'text-danger'],
        'publicacoes_liberadas'    => ['Publicações liberadas', 'bi-unlock', 'text-success'],
        'aviso_enviado'            => ['Aviso enviado às ONGs', 'bi-megaphone', 'text-primary'],
    ];
}

/**
 * categorias usadas nos filtros e nos chips do log, a categoria
 * "outros" é o fallback seguro para qualquer ação nova
 */
function categoriasLogAdmin(): array
{
    return [
        'decisoes' => [
            'rotulo' => 'Decisões sobre ONGs',
            'chip'   => 'Decisões',
            'icone'  => 'bi-patch-check',
            'classe' => 'is-decisao',
            'acoes'  => [
                'aprovar_ong',
                'recusar_ong',
                'suspender_ong',
                'reativar_ong',
                'marcar_email_enviado',
                'alterar_pix',
                'alterar_responsavel',
            ],
        ],
        'acessos' => [
            'rotulo' => 'Acessos',
            'chip'   => 'Acessos',
            'icone'  => 'bi-box-arrow-in-right',
            'classe' => 'is-acesso',
            'acoes'  => ['login_admin', 'logout_admin', 'acesso_negado'],
        ],
        'conta' => [
            'rotulo' => 'Conta e senha',
            'chip'   => 'Conta e senha',
            'icone'  => 'bi-shield-lock',
            'classe' => 'is-conta',
            'acoes'  => [
                'senha_redefinida',
                'conta_desativada',
                'conta_reativada',
                'senha_alterada',
                'primeiro_acesso_concluido',
            ],
        ],
        'equipe' => [
            'rotulo' => 'Equipe',
            'chip'   => 'Equipe',
            'icone'  => 'bi-people',
            'classe' => 'is-equipe',
            'acoes'  => ['conta_criada', 'aviso_enviado'],
        ],
        'noticias' => [
            'rotulo' => 'Notícias',
            'chip'   => 'Notícias',
            'icone'  => 'bi-newspaper',
            'classe' => 'is-noticia',
            'acoes'  => [
                'denuncia_recebida',
                'noticia_ocultada',
                'noticia_restaurada',
                'denuncia_improcedente',
                'publicacoes_bloqueadas',
                'publicacoes_liberadas',
            ],
        ],
        'outros' => [
            'rotulo' => 'Outros',
            'chip'   => 'Outros',
            'icone'  => 'bi-dot',
            'classe' => 'is-outro',
            'acoes'  => [],
        ],
    ];
}

function categoriaLogAdmin(string $acao): string
{
    foreach (categoriasLogAdmin() as $chave => $categoria) {
        if (in_array($acao, $categoria['acoes'], true)) {
            return $chave;
        }
    }

    return 'outros';
}

function categoriaLogInfo(string $acao): array
{
    return categoriasLogAdmin()[categoriaLogAdmin($acao)];
}

/**
 * frase legível do evento, sem HTML, a saída deve ser escapada
 */
function resumoLogAdmin(array $log): string
{
    $ator = trim((string) ($log['admin_nome'] ?? '')) ?: 'Equipe ONGHelp';
    $alvo = trim((string) ($log['alvo_nome'] ?? '')) ?: 'conta';
    $acao = (string) ($log['acao'] ?? '');

    return match ($acao) {
        'login_admin'               => $ator . ' entrou no painel',
        'logout_admin'              => $ator . ' saiu do painel',
        'aprovar_ong'               => $ator . ' aprovou a ONG ' . $alvo,
        'recusar_ong'               => $ator . ' recusou a ONG ' . $alvo,
        'suspender_ong'             => $ator . ' suspendeu a ONG ' . $alvo,
        'reativar_ong'              => $ator . ' reativou a ONG ' . $alvo,
        'marcar_email_enviado'      => $ator . ' marcou o e-mail de aprovação de ' . $alvo . ' como enviado',
        'alterar_pix'               => $ator . ' alterou a chave PIX de ' . $alvo,
        'alterar_responsavel'       => $ator . ' alterou o responsável legal de ' . $alvo,
        'conta_criada'              => $ator . ' criou a conta de ' . $alvo,
        'senha_redefinida'          => $ator . ' redefiniu a senha de ' . $alvo,
        'conta_desativada'          => $ator . ' desativou a conta de ' . $alvo,
        'conta_reativada'           => $ator . ' reativou a conta de ' . $alvo,
        'senha_alterada'            => $ator . ' alterou a própria senha',
        'primeiro_acesso_concluido' => $ator . ' concluiu o primeiro acesso e aceitou as regras',
        'acesso_negado'             => $ator . ' tentou acessar uma área restrita',
        'denuncia_recebida'         => 'Visitante denunciou a notícia ' . $alvo,
        'noticia_ocultada'          => $ator . ' ocultou a notícia ' . $alvo,
        'noticia_restaurada'        => $ator . ' restaurou a notícia ' . $alvo,
        'denuncia_improcedente'     => $ator . ' marcou denúncia como improcedente em ' . $alvo,
        'publicacoes_bloqueadas'    => $ator . ' bloqueou as publicações da ONG ' . $alvo,
        'publicacoes_liberadas'     => $ator . ' liberou as publicações da ONG ' . $alvo,
        'aviso_enviado'             => $ator . ' enviou um aviso para ' . $alvo,
        default                     => $ator . ' registrou ' . str_replace('_', ' ', $acao)
            . ($log['alvo_nome'] ? ' em ' . $alvo : ''),
    };
}

/**
 * frase da linha do tempo de uma pessoa, sem repetir o nome dela,
 * a saída deve ser escapada
 */
function resumoAtividadeAdmin(array $log): string
{
    $alvo = trim((string) ($log['alvo_nome'] ?? '')) ?: 'conta';
    $acao = (string) ($log['acao'] ?? '');

    return match ($acao) {
        'login_admin'               => 'Entrou no painel',
        'logout_admin'              => 'Saiu do painel',
        'aprovar_ong'               => 'Aprovou a ONG ' . $alvo,
        'recusar_ong'               => 'Recusou a ONG ' . $alvo,
        'suspender_ong'             => 'Suspendeu a ONG ' . $alvo,
        'reativar_ong'              => 'Reativou a ONG ' . $alvo,
        'marcar_email_enviado'      => 'Marcou o e-mail de aprovação de ' . $alvo . ' como enviado',
        'alterar_pix'               => 'Alterou a chave PIX de ' . $alvo,
        'alterar_responsavel'       => 'Alterou o responsável legal de ' . $alvo,
        'conta_criada'              => 'Conta de moderação criada',
        'senha_redefinida'          => 'Senha redefinida pelo administrador',
        'conta_desativada'          => 'Conta desativada',
        'conta_reativada'           => 'Conta reativada com nova senha temporária',
        'senha_alterada'            => 'Alterou a própria senha',
        'primeiro_acesso_concluido' => 'Concluiu o primeiro acesso e aceitou as regras',
        'acesso_negado'             => 'Teve acesso negado a uma área restrita',
        'denuncia_recebida'         => 'Denúncia recebida em ' . $alvo,
        'noticia_ocultada'          => 'Ocultou a notícia ' . $alvo,
        'noticia_restaurada'        => 'Restaurou a notícia ' . $alvo,
        'denuncia_improcedente'     => 'Marcou denúncia como improcedente em ' . $alvo,
        'publicacoes_bloqueadas'    => 'Bloqueou as publicações da ONG ' . $alvo,
        'publicacoes_liberadas'     => 'Liberou as publicações da ONG ' . $alvo,
        'aviso_enviado'             => 'Enviou um aviso para ' . $alvo,
        default                     => str_replace('_', ' ', $acao)
            . ($log['alvo_nome'] ? ' · ' . $alvo : ''),
    };
}

/**
 * segmento visual do drawer de atividade: decisões ou conta
 */
function segmentoAtividadeAdmin(string $acao): string
{
    $categoria = categoriaLogAdmin($acao);

    if ($categoria === 'decisoes') {
        return 'decisoes';
    }

    if ($categoria === 'noticias') {
        return 'noticias';
    }

    return 'conta';
}

function adminPapelLabel(string $papel): string
{
    return $papel === 'administrador' ? 'Administrador' : 'Moderador';
}

function adminEstadoLabel(string $estado): string
{
    return [
        'ativo'       => 'Ativo',
        'aguardando'  => 'Aguardando primeiro acesso',
        'desativado'  => 'Desativado',
    ][$estado] ?? 'Desconhecido';
}

function adminIniciais(string $nome): string
{
    $nome = trim(preg_replace('/\s+/', ' ', $nome) ?? '');
    if ($nome === '') {
        return '?';
    }

    $partes = explode(' ', $nome);
    if (count($partes) === 1) {
        return mb_strtoupper(mb_substr($partes[0], 0, 2), 'UTF-8');
    }

    return mb_strtoupper(
        mb_substr($partes[0], 0, 1, 'UTF-8') . mb_substr($partes[1], 0, 1, 'UTF-8'),
        'UTF-8'
    );
}

function gerarSenhaTemporaria(): string
{
    $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $corpo = '';

    for ($i = 0; $i < 10; $i++) {
        $corpo .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
    }

    // o prefixo garante maiúscula, minúscula e símbolo; o dígito final
    // garante que a senha sempre cumpra a política mínima
    return 'ONGHelp@' . $corpo . (string) random_int(2, 9);
}

// lista de requisitos da troca de senha da equipe (minha conta e
// senha do administrador): chave do servidor => data-check do senha.js
const SENHA_ITENS_TROCA_EQUIPE = [
    'minimo'          => 'minimo',
    'letra_numero'    => 'letra_numero',
    'diferente_atual' => 'atual',
    'sem_email'       => 'email',
    'nao_comum'       => 'comum',
    'iguais'          => 'iguais',
];

/**
 * regras de senha da equipe: as mesmas das ONGs (includes/core/senha.php)
 * com mínimo maior e mais duas, diferente da temporária no primeiro
 * acesso e diferente da atual na troca
 */
function requisitosSenhaEquipe(
    string $senha,
    string $confirmacao,
    string $email,
    ?string $hashTemporaria = null,
    ?string $hashAtual = null
): array {
    $requisitos = requisitosSenha($senha, $confirmacao, $email, SENHA_MINIMO_EQUIPE);

    if ($hashTemporaria !== null) {
        $requisitos['diferente_temporaria'] = $senha !== '' && !password_verify($senha, $hashTemporaria);
    }

    if ($hashAtual !== null) {
        $requisitos['diferente_atual'] = $senha !== '' && !password_verify($senha, $hashAtual);
    }

    return $requisitos;
}

function urlPainelAdmin(): string
{
    return urlAbsoluta('admin/login.php');
}
