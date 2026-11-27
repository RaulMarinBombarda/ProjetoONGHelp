<?php
// ============================================================
// tests/e2e-test.php — teste de ponta a ponta pelo HTTP
// ============================================================
// percorre o site como visitante, ONG, administrador e moderador,
// do jeito que um navegador faria (cookies, CSRF, redirects)
//
// uso (com o Apache e o MySQL do XAMPP ligados):
//   php tests/e2e-test.php [http://localhost/ONGHelp/web/]
//
// tudo o que o teste cria usa e-mails "@teste-e2e.onghelp.test" e é
// apagado no final, mesmo se algum teste falhar, os dados de
// demonstração não são alterados
// ============================================================

declare(strict_types=1);

// mesmo fuso do site (bootstrap.php): o PHP do XAMPP vem com Europe/Berlin,
// e à noite o "hoje" do teste viraria o dia seguinte
date_default_timezone_set('America/Sao_Paulo');

$BASE = rtrim($argv[1] ?? 'http://localhost/ONGHelp/web/', '/') . '/';
const E2E_DOMINIO = '@teste-e2e.onghelp.test';
const ADMIN_EMAIL = 'equipeonghelp@gmail.com';
const ADMIN_SENHA = 'EquipeONGHelp@2026';
const SENHA_ONG   = 'SenhaTeste123';

$db = require __DIR__ . '/../config/database.php';
$pdo = new PDO(
    "mysql:host={$db['host']};dbname={$db['name']};charset={$db['charset']}",
    $db['user'], $db['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

// cliente HTTP (Navegador), placar e utilitários comuns aos testes
require_once __DIR__ . '/suporte.php';

function limpar(PDO $pdo, string $desde = '', int $noticiaDenunciada = 0): void
{
    $like = '%' . E2E_DOMINIO;
    // registros que o próprio teste gera em dados reais: logs com o nome
    // de teste, a denúncia de teste numa notícia de demonstração e os
    // logins do admin feitos pelo teste
    $pdo->exec("DELETE FROM logs_admin WHERE alvo_nome LIKE '%Teste E2E%' OR alvo_nome LIKE '%teste E2E%' OR detalhe LIKE '%Teste E2E%'");
    // avisos de teste enviados a todas as ONGs também chegam às ONGs reais
    $pdo->exec("DELETE n FROM notificacoes n JOIN avisos_equipe a ON a.id = n.aviso_id WHERE a.titulo LIKE '%Teste E2E%'");
    $pdo->exec("DELETE FROM avisos_equipe WHERE titulo LIKE '%Teste E2E%'");
    if ($desde !== '') {
        $adm = (int) $pdo->query("SELECT id FROM admins WHERE email = " . $pdo->quote(ADMIN_EMAIL))->fetchColumn();
        $pdo->prepare("DELETE FROM logs_admin WHERE criado_em >= ? AND ((acao = 'login_admin' AND alvo_id = ?) OR (acao = 'denuncia_recebida' AND alvo_id = ?))")
            ->execute([$desde, $adm, $noticiaDenunciada]);
        $pdo->prepare("DELETE FROM tentativas_login WHERE criado_em >= ? AND email = ?")->execute([$desde, ADMIN_EMAIL]);
    }
    $ids = $pdo->prepare("SELECT id FROM usuarios WHERE email LIKE ?");
    $ids->execute([$like]);
    $ids = array_map('intval', $ids->fetchAll(PDO::FETCH_COLUMN));

    if ($ids) {
        $lista = implode(',', $ids);
        // arquivos de fotos (notícias e perfil) saem do disco antes das linhas
        $arquivos = $pdo->query(
            "SELECT f.arquivo FROM noticias_fotos f JOIN noticias n ON n.id = f.noticia_id WHERE n.usuario_id IN ($lista)"
        )->fetchAll(PDO::FETCH_COLUMN);
        foreach ($arquivos as $a) {
            @unlink(ONGHELP_UPLOADS_DIR . '/' . ONGHELP_NOTICIAS_UPLOADS_PASTA . '/' . basename((string) $a));
        }
        foreach ($pdo->query("SELECT foto_perfil FROM usuarios WHERE id IN ($lista) AND foto_perfil IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN) as $a) {
            @unlink(ONGHELP_UPLOADS_DIR . '/perfis/' . basename((string) $a));
        }
        foreach ($pdo->query("SELECT comprovante FROM movimentacoes WHERE usuario_id IN ($lista) AND comprovante IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN) as $a) {
            @unlink(ONGHELP_UPLOADS_DIR . '/comprovantes/' . basename((string) $a));
        }
        $pdo->exec("DELETE FROM logs_admin WHERE alvo_tipo = 'usuario' AND alvo_id IN ($lista)");
        $pdo->exec("DELETE FROM usuarios WHERE id IN ($lista)");
    }
    $pdo->prepare("DELETE FROM noticias_denuncias WHERE email_denunciante LIKE ?")->execute([$like]);
    $pdo->prepare("DELETE FROM tentativas_login WHERE email LIKE ?")->execute([$like]);
    $adm = $pdo->prepare("SELECT id FROM admins WHERE email LIKE ?");
    $adm->execute([$like]);
    foreach ($adm->fetchAll(PDO::FETCH_COLUMN) as $admId) {
        $pdo->prepare("DELETE FROM logs_admin WHERE admin_id = ? OR (alvo_tipo = 'admin' AND alvo_id = ?)")->execute([$admId, $admId]);
        $pdo->prepare("DELETE FROM admins WHERE id = ?")->execute([$admId]);
    }
}

require_once __DIR__ . '/../config/app.php';
if (!defined('ONGHELP_UPLOADS_DIR')) {
    require_once __DIR__ . '/../includes/core/paths.php';
}

$inicio = (string) $pdo->query('SELECT NOW()')->fetchColumn();
$noticiaDenunciada = 0;
limpar($pdo);   // sobra de uma execução interrompida
register_shutdown_function(static function () use ($pdo, $inicio, &$noticiaDenunciada): void {
    limpar($pdo, $inicio, $noticiaDenunciada);
});

$sufixo = (string) random_int(100000, 999999);
$ongA = ['email' => "e2e.ong.a.$sufixo" . E2E_DOMINIO, 'cnpj' => cnpjValido((int) $sufixo)];
$ongB = ['email' => "e2e.ong.b.$sufixo" . E2E_DOMINIO, 'cnpj' => cnpjValido((int) $sufixo + 7)];
$ongC = ['email' => "e2e.ong.c.$sufixo" . E2E_DOMINIO, 'cnpj' => cnpjValido((int) $sufixo + 13)];

function dadosCadastro(array $ong, array $troca = []): array
{
    return array_merge([
        'nome' => 'ONG Teste E2E ' . substr($ong['email'], 8, 1),
        'cnpj' => $ong['cnpj'],
        'email' => $ong['email'],
        'senha' => SENHA_ONG,
        'senha_confirm' => SENHA_ONG,
        'area' => 'Cultura',
        'cidade' => 'Matão',
        'estado' => 'SP',
        'responsavel_nome' => 'Pessoa Responsável',
        'responsavel_cargo' => 'Presidência',
        'canal_tipo' => 'instagram',
        'canal_valor' => '@ongteste',
        'responsavel_autorizacao' => '1',
        'termos' => '1',
    ], $troca);
}

function idOng(PDO $pdo, string $email): int
{
    $s = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
    $s->execute([$email]);
    return (int) $s->fetchColumn();
}

$visitante = new Navegador($BASE);

// ============================================================
secao('1. Páginas públicas');
// ============================================================
foreach ([
    'index.php', 'pages/ongs.php', 'pages/transparencia.php', 'pages/noticias.php',
    'pages/equipe.php', 'pages/termos.php', 'pages/privacidade.php', 'pages/login.php',
    'pages/cadastro.php', 'pages/contato.php', 'pages/legislacao.php', 'admin/login.php',
] as $p) {
    $r = $visitante->get($p);
    t("$p abre (200, sem erro PHP)", $r['code'] === 200 && semErroPhp($r), "código {$r['code']}");
    // a navbar do site (fora do painel) tem Legislação logo depois de Notícias, e o rodapé também
    // login e cadastro usam o layout próprio de autenticação, sem navbar nem rodapé
    if (!str_starts_with($p, 'admin/') && !in_array($p, ['pages/login.php', 'pages/cadastro.php'], true)) {
        $pilula = preg_match('~<ul class="navbar-nav site-nav-pill">(.*?)</ul>~s', $r['body'], $m) ? $m[1] : '';
        $posNoticias = strpos($pilula, 'pages/noticias.php');
        $posLegislacao = strpos($pilula, 'pages/legislacao.php');
        t("$p: navbar mostra Legislação depois de Notícias", $posNoticias !== false && $posLegislacao !== false && $posLegislacao > $posNoticias);
        t("$p: rodapé tem o link da Legislação", preg_match('~<footer.*pages/legislacao\.php.*</footer>~s', $r['body']) === 1);
    }
}

// página de legislação
$r = $visitante->get('pages/legislacao.php');
t('legislação fica marcada como página atual na navbar', preg_match('~site-nav-link is-active" href="[^"]*legislacao\.php" aria-current="page"~', $r['body']) === 1);
t('só a legislação fica marcada como atual', substr_count($r['body'], 'aria-current="page"') === 1);
// só o conteúdo da página: o rodapé tem os WhatsApps da equipe, que não são fontes de lei
preg_match('~<main\b.*?</main>~s', $r['body'], $conteudo);
preg_match_all('~<a [^>]*href="(https?://[^"]+)"[^>]*>~', $conteudo[0] ?? '', $externos, PREG_SET_ORDER);
$oficiais = array_filter($externos, static fn (array $a): bool => preg_match('~^https://(www\.planalto\.gov\.br|www\.gov\.br|mapaosc\.ipea\.gov\.br|solucoes\.receita\.fazenda\.gov\.br)/~', $a[1]) === 1);
t('legislação tem mais de 30 links para textos oficiais', count($oficiais) > 30, count($oficiais) . ' links');
t('todo link externo da legislação vai para fonte oficial', count($oficiais) === count(array_filter($externos, static fn (array $a): bool => !str_contains($a[1], 'localhost'))),
    implode(', ', array_diff(array_column($externos, 1), array_column($oficiais, 1))));
t('links oficiais abrem em nova aba com noopener', !array_filter($oficiais, static fn (array $a): bool => !str_contains($a[0], 'target="_blank"') || !str_contains($a[0], 'noopener')));
t('lei revogada (11.438/2006) não aparece como link', !str_contains($r['body'], 'l11438'));
preg_match_all('~<nav class="lei-indice[^"]*".*?</nav>~s', $r['body'], $sumario);
preg_match_all('~href="#([a-z-]+)"~', $sumario[0][0] ?? '', $ancoras);
$semAlvo = array_filter($ancoras[1], static fn (string $id): bool => !str_contains($r['body'], 'id="' . $id . '"'));
t('cada item do sumário leva a uma seção que existe', count($ancoras[1]) >= 10 && !$semAlvo, implode(', ', $semAlvo));

$demo = $pdo->query("SELECT id FROM usuarios WHERE status = 'aprovada' ORDER BY id LIMIT 1")->fetchColumn();
$r = $visitante->get("pages/perfil-publico.php?id=$demo");
t('perfil público de ONG aprovada abre', $r['code'] === 200 && semErroPhp($r));
foreach (['projetos', 'movimentacoes', 'noticias'] as $aba) {
    $r = $visitante->get("pages/perfil-publico.php?id=$demo&aba=$aba");
    t("perfil público: aba $aba", $r['code'] === 200 && semErroPhp($r));
}
foreach (['0', '-1', 'abc', '999999999'] as $idRuim) {
    $r = $visitante->get("pages/perfil-publico.php?id=$idRuim");
    t("perfil público com id '$idRuim' responde 404", $r['code'] === 404 && semErroPhp($r), "código {$r['code']}");
}

// ============================================================
secao('2. Filtros, ordenação e paginação');
// ============================================================
$casos = [
    'pages/ongs.php?area=Cultura', 'pages/ongs.php?area=NaoExiste', 'pages/ongs.php?ordem=hack',
    'pages/ongs.php?ordem=arrecadacao', 'pages/ongs.php?ordem=recentes', 'pages/ongs.php?pagina=9999',
    'pages/ongs.php?pagina=-5', 'pages/ongs.php?uf=ZZ', 'pages/ongs.php?cidade=Matão',
    'pages/transparencia.php?ano=1999', 'pages/transparencia.php?ano=abc', 'pages/transparencia.php?ano=2026&ordem=saldo',
    'pages/transparencia.php?ordem=hack', 'pages/transparencia.php?categoria=NaoExiste', 'pages/transparencia.php?pagina=9999',
    'pages/transparencia.php?ong=999999', 'pages/transparencia.php?ong=abc',
    'pages/noticias.php?tipo=hack', 'pages/noticias.php?tipo=evento', 'pages/noticias.php?pagina=9999',
    'pages/noticias.php?ong=999999', 'pages/noticias.php?area=Saúde',
    'pages/ongs.php?busca[]=x', 'pages/transparencia.php?ano[]=1', 'pages/noticias.php?tipo[]=x',
];
foreach ($casos as $p) {
    $r = $visitante->get($p);
    t("$p responde sem erro", in_array($r['code'], [200, 404], true) && semErroPhp($r), "código {$r['code']}");
}

$xss = '<script>alert(1)</script>';
foreach (['pages/ongs.php?busca=', 'pages/transparencia.php?busca=', 'pages/noticias.php?busca='] as $p) {
    $r = $visitante->get($p . urlencode($xss));
    t("$p não reflete <script> sem escapar", !tem($r, $xss));
}

$r = $visitante->get('pages/ongs.php?area=Cultura&pagina=2');
t('link de filtro no diretório volta para a página 1', !preg_match('/href="ongs\.php\?[^"]*area=Animais[^"]*pagina=/', $r['body']));

// ============================================================
secao('3. Notícia pública e denúncia');
// ============================================================
$noticia = $pdo->query("SELECT id, slug FROM noticias WHERE estado = 'publicada' ORDER BY id LIMIT 1")->fetch();
$r = $visitante->get("pages/noticia.php?id={$noticia['id']}&slug={$noticia['slug']}");
t('notícia publicada abre', $r['code'] === 200 && semErroPhp($r));
$r = $visitante->get("pages/noticia.php?id={$noticia['id']}&slug=slug-errado");
t('slug errado redireciona para o slug certo (ou abre)', in_array($r['code'], [200, 301, 302], true));
$r = $visitante->get('pages/noticia.php?id=999999');
t('notícia inexistente responde 404', $r['code'] === 404 && semErroPhp($r), "código {$r['code']}");

$paginaNoticia = "pages/noticia.php?id={$noticia['id']}&slug={$noticia['slug']}";
$noticiaDenunciada = (int) $noticia['id'];
$emailDen = "e2e.denuncia.$sufixo" . E2E_DOMINIO;
$r = $visitante->enviar('pages/actions/denunciar-noticia.php', ['noticia_id' => $noticia['id'], 'motivo' => ''], $paginaNoticia);
$f = $visitante->abrir($r['loc'] ?: $paginaNoticia);
t('denúncia sem motivo é recusada', tem($f, 'Selecione um motivo'));
$r = $visitante->enviar('pages/actions/denunciar-noticia.php', ['noticia_id' => $noticia['id'], 'motivo' => 'outro', 'email' => 'nao-e-email'], $paginaNoticia);
$f = $visitante->abrir($r['loc'] ?: $paginaNoticia);
t('denúncia com e-mail inválido é recusada', tem($f, 'e-mail válido'));
$r = $visitante->enviar('pages/actions/denunciar-noticia.php', ['noticia_id' => $noticia['id'], 'motivo' => 'outro', 'detalhe' => 'Teste automatizado E2E', 'email' => $emailDen], $paginaNoticia);
$qtd = $pdo->prepare("SELECT COUNT(*) FROM noticias_denuncias WHERE email_denunciante = ?");
$qtd->execute([$emailDen]);
t('denúncia válida é registrada', (int) $qtd->fetchColumn() === 1);
$r = $visitante->enviar('pages/actions/denunciar-noticia.php', ['noticia_id' => $noticia['id'], 'motivo' => 'outro', 'email' => $emailDen], $paginaNoticia);
$f = $visitante->abrir($r['loc'] ?: $paginaNoticia);
t('denúncia repetida é barrada', tem($f, 'já denunciou'));
$r = $visitante->req('POST', 'pages/actions/denunciar-noticia.php', ['noticia_id' => $noticia['id'], 'motivo' => 'outro']);
t('denúncia sem token CSRF não é registrada', $r['code'] === 302 || $r['code'] === 403);

// ============================================================
secao('4. Controle de acesso sem login');
// ============================================================
foreach (['pages/gestao.php', 'pages/perfil-privado.php', 'pages/minhas-noticias.php', 'pages/noticia-editar.php', 'pages/excluir-conta.php'] as $p) {
    $r = $visitante->get($p);
    t("$p sem login redireciona ao login", $r['code'] === 302 && str_contains($r['loc'], 'login.php'), "código {$r['code']} → {$r['loc']}");
}
foreach (['admin/index.php', 'admin/noticias.php', 'admin/logs.php', 'admin/equipe.php', 'admin/minha-conta.php', 'admin/verificar.php?id=1'] as $p) {
    $r = $visitante->get($p);
    t("$p sem login redireciona ao login do admin", $r['code'] === 302 && str_contains($r['loc'], 'admin/login.php'), "código {$r['code']} → {$r['loc']}");
}
$r = $visitante->req('POST', 'pages/gestao.php', ['acao' => 'add_mov']);
t('POST na gestão sem login não grava', $r['code'] === 302 || $r['code'] === 403);
foreach (['includes/core/helpers.php', 'config/database.php', 'database/onghelp.sql', 'README.md', '.gitignore'] as $p) {
    $r = $visitante->get($p);
    t("arquivo interno $p não é servido", $r['code'] === 403 || $r['code'] === 404 || trim($r['body']) === '', "código {$r['code']}");
}

// ============================================================
secao('5. Cadastro de ONG');
// ============================================================
$cad = new Navegador($BASE);
$validacoes = [
    'campo obrigatório vazio' => [['nome' => ''], 'Preencha todos os campos'],
    'CNPJ curto' => [['cnpj' => '123'], 'CNPJ com 14 dígitos'],
    'e-mail inválido' => [['email' => 'abc'], 'e-mail válido'],
    'área fora da lista' => [['area' => 'Hackers'], 'área de atuação válida'],
    'estado fora da lista' => [['estado' => 'XX'], 'estado da lista'],
    'canal site inválido' => [['canal_tipo' => 'site', 'canal_valor' => 'não é site'], 'canal público válido'],
    'canal tipo inválido' => [['canal_tipo' => 'tiktok'], 'canal público válido'],
    'telefone curto' => [['canal_tipo' => 'telefone', 'canal_valor' => '1234'], 'canal público válido'],
    'nome longo demais' => [['nome' => str_repeat('a', 161)], 'no máximo 160'],
    'senha curta' => [['senha' => '1234567', 'senha_confirm' => '1234567'], 'pelo menos 8'],
    'senhas diferentes' => [['senha_confirm' => 'OutraSenha123'], 'não coincidem'],
    'senha sem números' => [['senha' => 'SomenteLetras', 'senha_confirm' => 'SomenteLetras'], 'letras e números'],
    'senha comum' => [['senha' => 'Senha@123', 'senha_confirm' => 'Senha@123'], 'muito comum'],
    'senha com o e-mail' => [['senha' => 'X' . explode('@', $ongA['email'])[0] . '9', 'senha_confirm' => 'X' . explode('@', $ongA['email'])[0] . '9'], 'conter o seu e-mail'],
    'sem autorização' => [['responsavel_autorizacao' => null], 'responde legalmente'],
    'sem termos' => [['termos' => null], 'Termos de Uso'],
];
foreach ($validacoes as $nome => [$troca, $esperado]) {
    $d = array_filter(dadosCadastro($ongA, $troca), static fn ($v) => $v !== null);
    $r = $cad->enviar('pages/cadastro.php', $d);
    t("cadastro recusa: $nome", $r['code'] === 200 && tem($r, $esperado) && idOng($pdo, $ongA['email']) === 0, "código {$r['code']}");
}
$r = $cad->enviar('pages/cadastro.php', dadosCadastro($ongA, ['estado' => 'XX']));
t('erro de estado abre direto na etapa 2', tem($r, 'data-initial-step="2"'));
$r = $cad->enviar('pages/cadastro.php', dadosCadastro($ongA, ['termos' => '1', 'canal_valor' => '']));
t('erro de canal abre direto na etapa 3', tem($r, 'data-initial-step="3"'));
$r = $cad->req('POST', 'pages/cadastro.php', dadosCadastro($ongA));
t('cadastro sem token CSRF é barrado (403)', $r['code'] === 403 && idOng($pdo, $ongA['email']) === 0);
$r = $cad->get('pages/cadastro.php');
t('cadastro mostra a lista de requisitos da senha', tem($r, 'data-check="letra_numero"') && tem($r, 'data-check="comum"') && tem($r, 'data-senhas-comuns'));

$r = $cad->enviar('pages/cadastro.php', dadosCadastro($ongA, ['email' => strtoupper($ongA['email']), 'cnpj' => substr($ongA['cnpj'], 0, 2) . '.' . substr($ongA['cnpj'], 2, 3) . '.' . substr($ongA['cnpj'], 5, 3) . '/' . substr($ongA['cnpj'], 8, 4) . '-' . substr($ongA['cnpj'], 12)]));
t('cadastro válido redireciona para a confirmação', $r['code'] === 302 && str_contains($r['loc'], 'enviado=1'), "código {$r['code']}");
$linha = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
$linha->execute([$ongA['email']]);
$linha = $linha->fetch();
t('e-mail gravado em minúsculas', $linha !== false);
t('CNPJ gravado só com dígitos', ($linha['cnpj'] ?? '') === $ongA['cnpj']);
t('chave PIX = CNPJ', ($linha['pix_chave'] ?? '') === $ongA['cnpj']);
t('cadastro nasce pendente', ($linha['status'] ?? '') === 'pendente');
$r = $cad->get('pages/cadastro.php?enviado=1');
t('tela de confirmação aparece sem o formulário', tem($r, 'Cadastro enviado!') && !tem($r, 'data-cadastro-form'));
$r = $cad->enviar('pages/cadastro.php', dadosCadastro($ongA));
t('mesmo e-mail é recusado', tem($r, 'Já existe uma conta'));
$r = $cad->enviar('pages/cadastro.php', dadosCadastro($ongA, ['email' => 'outro.' . $ongA['email']]));
t('mesmo CNPJ é recusado', tem($r, 'Já existe uma conta'));

foreach ([$ongB, $ongC] as $o) {
    $cad->enviar('pages/cadastro.php', dadosCadastro($o));
}
t('ONGs B e C cadastradas', idOng($pdo, $ongB['email']) > 0 && idOng($pdo, $ongC['email']) > 0);
$idA = idOng($pdo, $ongA['email']);
$idB = idOng($pdo, $ongB['email']);
$idC = idOng($pdo, $ongC['email']);

// ============================================================
secao('6. Login de ONG e estados da conta');
// ============================================================
$ong = new Navegador($BASE);
$r = $ong->enviar('pages/login.php', ['email' => $ongA['email'], 'senha' => SENHA_ONG]);
t('ONG pendente não entra e recebe aviso de análise', $r['code'] === 200 && tem($r, 'em análise'));
$r = $visitante->get("pages/perfil-publico.php?id=$idA");
t('perfil de ONG pendente não é público (404)', $r['code'] === 404);
$r = $visitante->get("pages/transparencia.php?ong=$idA");
t('ONG pendente não aparece no portal', !tem($r, 'ONG Teste E2E'));
$r = $visitante->get("pages/actions/relatorio-anual.php?ong=$idA&ano=2026");
t('PDF de ONG pendente não é gerado', $r['code'] >= 400 && !str_starts_with($r['body'], '%PDF'));
$r = $visitante->get("pages/actions/exportar-csv.php?ong=$idA&ano=2026");
t('CSV de ONG pendente não é gerado', $r['code'] >= 400);

// ============================================================
secao('7. Admin: login, aprovação, recusa e suspensão');
// ============================================================
$adm = new Navegador($BASE);
$r = $adm->enviar('admin/login.php', ['email' => ADMIN_EMAIL, 'senha' => 'senha-errada']);
t('admin com senha errada não entra', $r['code'] === 200 && !str_contains($r['loc'], 'index.php'));
$r = $adm->enviar('admin/login.php', ['email' => ADMIN_EMAIL, 'senha' => ADMIN_SENHA]);
t('admin entra', $r['code'] === 302 && str_contains($r['loc'], 'admin/index.php'), "código {$r['code']} → {$r['loc']}");
foreach (['admin/index.php', 'admin/noticias.php', 'admin/logs.php', 'admin/equipe.php', 'admin/minha-conta.php', "admin/verificar.php?id=$idA"] as $p) {
    $r = $adm->get($p);
    t("$p abre para o admin", $r['code'] === 200 && semErroPhp($r), "código {$r['code']}");
}
foreach (['admin/index.php?status=hack', 'admin/index.php?busca=' . urlencode($xss), 'admin/logs.php?categoria=hack&pagina=999', 'admin/noticias.php?aba=hack', 'admin/verificar.php?id=999999'] as $p) {
    $r = $adm->get($p);
    t("$p responde sem erro", in_array($r['code'], [200, 302, 404], true) && semErroPhp($r) && !tem($r, $xss), "código {$r['code']}");
}

$checklist = [];
foreach (['cnpj_ativo', 'razao_social', 'natureza_juridica', 'cnae_area', 'cidade_uf', 'canal_publico', 'contato_confirmado', 'responsavel_confere', 'responsavel_autorizou', 'pix_titular', 'nome_unico'] as $item) {
    $checklist['bloqueantes'][$item] = '1';
}
$checklistIncompleto = $checklist;
unset($checklistIncompleto['bloqueantes']['pix_titular']);

$r = $adm->enviar('admin/acao.php', ['acao' => 'aprovar', 'id' => $idA, 'checklist' => $checklistIncompleto], "admin/verificar.php?id=$idA");
$f = $adm->abrir($r['loc']);
$st = fn (int $id): string => (string) $pdo->query("SELECT status FROM usuarios WHERE id = $id")->fetchColumn();
t('aprovação com checklist incompleto é bloqueada', $st($idA) === 'pendente' && tem($f, 'Aprovação bloqueada'));
$r = $adm->enviar('admin/acao.php', ['acao' => 'recusar', 'id' => $idC, 'motivo' => ''], "admin/verificar.php?id=$idC");
t('recusa sem motivo é bloqueada', $st($idC) === 'pendente');
$r = $adm->enviar('admin/acao.php', ['acao' => 'aprovar', 'id' => $idA, 'checklist' => $checklist], "admin/verificar.php?id=$idA");
t('aprovação com checklist completo', $st($idA) === 'aprovada');
$adm->enviar('admin/acao.php', ['acao' => 'aprovar', 'id' => $idB, 'checklist' => $checklist], "admin/verificar.php?id=$idB");
t('ONG B aprovada', $st($idB) === 'aprovada');
$adm->enviar('admin/acao.php', ['acao' => 'recusar', 'id' => $idC, 'motivo' => 'Teste E2E: canal não confere'], "admin/verificar.php?id=$idC");
t('ONG C recusada com motivo', $st($idC) === 'recusada');
$r = $adm->enviar('admin/acao.php', ['acao' => 'hack', 'id' => $idA], "admin/verificar.php?id=$idA");
t('ação desconhecida no admin não quebra', in_array($r['code'], [302, 400], true) && semErroPhp($r));
$r = $adm->req('POST', 'admin/acao.php', ['acao' => 'suspender', 'id' => $idA, 'motivo' => 'x']);
t('ação do admin sem CSRF é barrada', $st($idA) === 'aprovada');
$log = (int) $pdo->query("SELECT COUNT(*) FROM logs_admin WHERE acao = 'aprovar_ong' AND alvo_id = $idA")->fetchColumn();
t('aprovação fica registrada nos logs', $log === 1);
$avisos = fn (int $id): array => $pdo->query("SELECT tipo FROM notificacoes WHERE usuario_id = $id")->fetchAll(PDO::FETCH_COLUMN);
t('aprovação gera aviso para a ONG', in_array('cadastro_aprovado', $avisos($idA), true));
t('recusa também fica registrada para a ONG', in_array('cadastro_recusado', $avisos($idC), true));

$r = $ong->enviar('pages/login.php', ['email' => $ongC['email'], 'senha' => SENHA_ONG]);
t('ONG recusada não entra e recebe aviso', tem($r, 'não foi aprovado'));
$r = $visitante->get("pages/perfil-publico.php?id=$idA");
t('perfil de ONG aprovada fica público', $r['code'] === 200 && tem($r, 'ONG Teste E2E'));

// ============================================================
secao('8. Login de ONG: erros e bloqueio por tentativas');
// ============================================================
$bruto = new Navegador($BASE);
$r = $bruto->enviar('pages/login.php', ['email' => '', 'senha' => '']);
t('login vazio mostra erro', $r['code'] === 200 && tem($r, 'login-note'));
$r = $bruto->enviar('pages/login.php', ['email' => $ongB['email'], 'senha' => 'errada']);
t('senha errada não entra', $r['code'] === 200 && tem($r, 'login-note'));
for ($i = 0; $i < 6; $i++) {
    $r = $bruto->enviar('pages/login.php', ['email' => $ongB['email'], 'senha' => 'errada' . $i]);
}
$r = $bruto->enviar('pages/login.php', ['email' => $ongB['email'], 'senha' => SENHA_ONG]);
t('após várias tentativas erradas o login fica bloqueado', $r['code'] === 200 && !str_contains($r['loc'], 'perfil-privado'));
$pdo->prepare("DELETE FROM tentativas_login WHERE email = ?")->execute([$ongB['email']]);

$r = $ong->enviar('pages/login.php', ['email' => strtoupper($ongA['email']), 'senha' => SENHA_ONG]);
t('ONG aprovada entra (e-mail em maiúsculas também)', $r['code'] === 302 && str_contains($r['loc'], 'perfil-privado'), "código {$r['code']} → {$r['loc']}");
$r = $ong->get('pages/login.php');
t('ONG logada que abre o login é mandada para o painel', $r['code'] === 302);
$r = $ong->get('admin/index.php');
t('sessão de ONG não abre o admin', $r['code'] === 302 && str_contains($r['loc'], 'admin/login.php'));

// ============================================================
secao('9. Gestão financeira e projetos');
// ============================================================
$movs = fn (): int => (int) $pdo->query("SELECT COUNT(*) FROM movimentacoes WHERE usuario_id = $idA")->fetchColumn();
$hoje = date('Y-m-d');
$casosMov = [
    'tipo inválido' => [['tipo' => 'hack'], 'Selecione o tipo'],
    'descrição vazia' => [['descricao' => ''], 'Informe a descrição'],
    'descrição longa' => [['descricao' => str_repeat('d', 256)], 'no máximo 255'],
    'valor zero' => [['valor' => '0'], 'maior que zero'],
    'valor negativo' => [['valor' => '-50'], 'só com números'],
    'valor com letras' => [['valor' => 'abc'], 'só com números'],
    'valor gigante' => [['valor' => '99999999999999'], 'alto demais'],
    'data inválida' => [['data_mov' => '2026-02-31'], 'data válida'],
    'data com texto' => [['data_mov' => 'ontem'], 'data válida'],
    'data futura' => [['data_mov' => date('Y-m-d', strtotime('+3 days'))], 'futuro'],
    'data antiga' => [['data_mov' => '1990-01-01'], 'a partir de 2000'],
    // arredondado vira R$ 0,00, antes passava como maior que zero
    'valor que arredonda para zero' => [['valor' => '0,004'], 'maior que zero'],
    // as letras eram descartadas e "1e5" virava R$ 15,00
    'valor em notação científica' => [['valor' => '1e5'], 'só com números'],
    'valor negativo com centavos' => [['valor' => '-50,00'], 'só com números'],
];
foreach ($casosMov as $nome => [$troca, $esperado]) {
    $antes = $movs();
    $r = $ong->enviar('pages/gestao.php', array_merge(['acao' => 'add_mov', 'tipo' => 'receita', 'descricao' => 'Doação teste', 'valor' => '150,50', 'data_mov' => $hoje], $troca));
    $f = $ong->abrir($r['loc']);
    t("movimentação recusada: $nome", $movs() === $antes && tem($f, $esperado), "redirect {$r['code']}");
}
$r = $ong->enviar('pages/gestao.php', ['acao' => 'add_mov', 'tipo' => 'receita', 'descricao' => 'Doação teste E2E', 'valor' => 'R$ 1.234,56', 'data_mov' => $hoje]);
t('receita válida redireciona (POST/redirect/GET)', $r['code'] === 302);
$f = $ong->abrir($r['loc']);
t('receita gravada com valor correto', (float) $pdo->query("SELECT valor FROM movimentacoes WHERE usuario_id = $idA ORDER BY id DESC LIMIT 1")->fetchColumn() === 1234.56);
t('mensagem de sucesso aparece', tem($f, 'cadastrada com sucesso'));
$f2 = $ong->get('pages/gestao.php');
t('recarregar a página não repete a mensagem nem o lançamento', !tem($f2, 'cadastrada com sucesso') && $movs() === 1);
$ong->enviar('pages/gestao.php', ['acao' => 'add_mov', 'tipo' => 'despesa', 'descricao' => 'Material E2E', 'valor' => '234.56', 'data_mov' => $hoje]);
$f = $ong->get('pages/gestao.php');
t('saldo do painel = receitas − despesas', tem($f, '1.000,00'));
$r = $ong->get('pages/gestao.php?tipo=despesa');
t('filtro de despesas mostra só despesas', tem($r, 'Material E2E') && !tem($r, 'Doação teste E2E'));
$r = $ong->get('pages/gestao.php?tipo=hack');
t('filtro inválido cai em "todas"', $r['code'] === 200 && tem($r, 'Material E2E') && tem($r, 'Doação teste E2E'));

$movDemo = (int) $pdo->query("SELECT id FROM movimentacoes WHERE usuario_id <> $idA ORDER BY id LIMIT 1")->fetchColumn();
$r = $ong->enviar('pages/gestao.php', ['acao' => 'del_mov', 'mov_id' => $movDemo]);
$f = $ong->abrir($r['loc']);
t('ONG não apaga movimentação de outra ONG', (int) $pdo->query("SELECT COUNT(*) FROM movimentacoes WHERE id = $movDemo")->fetchColumn() === 1 && tem($f, 'não encontrada'));
t('"não encontrada" aparece como erro, não como sucesso', tem($f, 'alert-danger'));
$minha = (int) $pdo->query("SELECT id FROM movimentacoes WHERE usuario_id = $idA AND tipo = 'despesa'")->fetchColumn();
$ong->enviar('pages/gestao.php', ['acao' => 'del_mov', 'mov_id' => $minha]);
t('ONG apaga a própria movimentação', $movs() === 1);

$projs = fn (): int => (int) $pdo->query("SELECT COUNT(*) FROM projetos WHERE usuario_id = $idA")->fetchColumn();
foreach ([
    'nome vazio' => [['nome' => ''], 'nome do projeto'],
    'nome longo' => [['nome' => str_repeat('p', 161)], 'no máximo 160'],
    'número negativo' => [['indicador' => '-3'], 'inteiro'],
    'número com letras' => [['indicador' => '12abc'], 'inteiro'],
    'unidade vazia' => [['unidade' => ''], 'junto do número'],
] as $nome => [$troca, $esperado]) {
    $r = $ong->enviar('pages/gestao.php', array_merge(['acao' => 'add_proj', 'nome' => 'Projeto E2E', 'indicador' => '42', 'unidade' => 'oficinas'], $troca));
    $f = $ong->abrir($r['loc']);
    t("projeto recusado: $nome", $projs() === 0 && tem($f, $esperado));
}
$ong->enviar('pages/gestao.php', ['acao' => 'add_proj', 'nome' => 'Projeto E2E', 'indicador' => '42', 'unidade' => 'oficinas']);
t('projeto válido gravado', $projs() === 1);
$projDemo = (int) $pdo->query("SELECT id FROM projetos WHERE usuario_id <> $idA ORDER BY id LIMIT 1")->fetchColumn();
$ong->enviar('pages/gestao.php', ['acao' => 'del_proj', 'proj_id' => $projDemo]);
t('ONG não apaga projeto de outra ONG', (int) $pdo->query("SELECT COUNT(*) FROM projetos WHERE id = $projDemo")->fetchColumn() === 1);
$r = $ong->enviar('pages/gestao.php', ['acao' => 'hack']);
t('ação desconhecida na gestão não quebra', $r['code'] === 302);

// categorias, vínculo com projeto, edição e comprovantes
$ultimaMov = fn (): array => $pdo->query("SELECT * FROM movimentacoes WHERE usuario_id = $idA ORDER BY id DESC LIMIT 1")->fetch() ?: [];
$projA = (int) $pdo->query("SELECT id FROM projetos WHERE usuario_id = $idA ORDER BY id DESC LIMIT 1")->fetchColumn();
$movBase = ['acao' => 'add_mov', 'tipo' => 'despesa', 'descricao' => 'Lanche E2E', 'valor' => '80,00', 'data_mov' => $hoje];

$antes = $movs();
$r = $ong->enviar('pages/gestao.php', array_merge($movBase, ['tipo' => 'receita', 'categoria' => 'alimentacao']));
$f = $ong->abrir($r['loc']);
t('categoria de despesa numa receita é recusada', $movs() === $antes && tem($f, 'lista de receitas'));
$r = $ong->enviar('pages/gestao.php', array_merge($movBase, ['projeto_id' => $projDemo]));
$f = $ong->abrir($r['loc']);
t('projeto de outra ONG é recusado na movimentação', $movs() === $antes && tem($f, 'projeto da sua lista'));
$ong->enviar('pages/gestao.php', array_merge($movBase, ['categoria' => 'alimentacao', 'projeto_id' => $projA]));
$m = $ultimaMov();
t('despesa gravada com categoria e projeto', ($m['categoria'] ?? '') === 'alimentacao' && (int) ($m['projeto_id'] ?? 0) === $projA);
$ong->enviar('pages/gestao.php', array_merge($movBase, ['descricao' => 'Sem categoria E2E']));
t('sem categoria a movimentação entra como "outra"', ($ultimaMov()['categoria'] ?? '') === 'outra');
$f = $ong->get('pages/gestao.php');
t('gestão mostra categoria e projeto embaixo da descrição', tem($f, 'Alimentação · Projeto E2E') && tem($f, 'em despesas ligadas'));

$movEdit = (int) $m['id'];
$r = $ong->get("pages/gestao.php?editar_mov=$movEdit");
t('link de edição abre o modal preenchido', tem($r, 'id="modalEditarMov"') && tem($r, 'value="Lanche E2E"') && tem($r, 'value="80,00"'));
$r = $ong->get("pages/gestao.php?editar_mov=$movDemo");
t('modal de edição não abre movimentação de outra ONG', !tem($r, 'id="modalEditarMov"'));
$edit = ['acao' => 'edit_mov', 'mov_id' => $movEdit, 'tipo' => 'despesa', 'categoria' => 'material', 'projeto_id' => '',
    'descricao' => 'Lanche E2E editado', 'valor' => '95,50', 'data_mov' => $hoje];
$r = $ong->enviar('pages/gestao.php', $edit);
$f = $ong->abrir($r['loc']);
$m = $pdo->query("SELECT * FROM movimentacoes WHERE id = $movEdit")->fetch();
t('edição salva descrição, valor e categoria e tira o projeto', $m['descricao'] === 'Lanche E2E editado' && (float) $m['valor'] === 95.5
    && $m['categoria'] === 'material' && $m['projeto_id'] === null && $m['atualizado_em'] !== null);
t('edição mostra a mensagem de sucesso', tem($f, 'Movimentação atualizada'));
$r = $ong->enviar('pages/gestao.php', array_merge($edit, ['valor' => '0']));
t('edição com erro volta com o modal aberto e não grava', str_contains($r['loc'], 'editar_mov=' . $movEdit)
    && (float) $pdo->query("SELECT valor FROM movimentacoes WHERE id = $movEdit")->fetchColumn() === 95.5);
$ong->enviar('pages/gestao.php', array_merge($edit, ['mov_id' => $movDemo, 'descricao' => 'invasão E2E']));
t('ONG não edita movimentação de outra ONG', $pdo->query("SELECT descricao FROM movimentacoes WHERE id = $movDemo")->fetchColumn() !== 'invasão E2E');

$pdfTeste = tempnam(sys_get_temp_dir(), 'e2e') . '.pdf';
file_put_contents($pdfTeste, "%PDF-1.4\n1 0 obj << >> endobj\ntrailer << >>\n%%EOF\n");
$falsoPdf = tempnam(sys_get_temp_dir(), 'e2e') . '.pdf';
file_put_contents($falsoPdf, 'texto qualquer, não sou PDF');
$phpDisfarcado = tempnam(sys_get_temp_dir(), 'e2e') . '.php';
file_put_contents($phpDisfarcado, '<?php echo "invadido"; ?>');
foreach (['texto com extensão .pdf' => $falsoPdf, 'script PHP' => $phpDisfarcado] as $nome => $arquivo) {
    $antes = $movs();
    $r = $ong->enviar('pages/gestao.php', $movBase, null, ['comprovante' => $arquivo]);
    $f = $ong->abrir($r['loc']);
    t("comprovante recusado: $nome", $movs() === $antes && tem($f, 'PDF ou uma imagem'));
}
$ong->enviar('pages/gestao.php', array_merge($movBase, ['descricao' => 'Com comprovante E2E']), null, ['comprovante' => $pdfTeste]);
$m = $ultimaMov();
$movComp = (int) $m['id'];
$arquivoComprovante = ONGHELP_UPLOADS_DIR . '/' . (string) ($m['comprovante'] ?? '');
t('comprovante PDF gravado com nome aleatório da ONG', preg_match('~^comprovantes/ong' . $idA . '-[a-f0-9]{16}\.pdf$~', (string) ($m['comprovante'] ?? '')) === 1
    && is_file($arquivoComprovante));
$r = $visitante->get('uploads/' . $m['comprovante']);
t('comprovante abre pelo endereço público', $r['code'] === 200 && str_contains($r['type'], 'pdf'));
$r = $visitante->get("pages/perfil-publico.php?id=$idA&aba=movimentacoes");
t('perfil público mostra o link do comprovante', tem($r, 'class="mov-comprovante"'));
$editComp = ['acao' => 'edit_mov', 'mov_id' => $movComp, 'tipo' => 'despesa', 'categoria' => 'alimentacao',
    'descricao' => 'Com comprovante E2E', 'valor' => '80,00', 'data_mov' => $hoje];
$ong->enviar('pages/gestao.php', $editComp + ['remover_comprovante' => '1']);
// o PHP guarda o resultado de is_file(), sem limpar ele responde o que viu antes
clearstatcache();
t('remover o comprovante na edição apaga o arquivo', $pdo->query("SELECT comprovante FROM movimentacoes WHERE id = $movComp")->fetchColumn() === null
    && !is_file($arquivoComprovante));
$ong->enviar('pages/gestao.php', $editComp, null, ['comprovante' => $pdfTeste]);
$arquivoComprovante = ONGHELP_UPLOADS_DIR . '/' . (string) $pdo->query("SELECT comprovante FROM movimentacoes WHERE id = $movComp")->fetchColumn();
t('comprovante enviado na edição', is_file($arquivoComprovante));
$ong->enviar('pages/gestao.php', ['acao' => 'del_mov', 'mov_id' => $movComp]);
clearstatcache();
t('excluir a movimentação apaga o comprovante do disco', !is_file($arquivoComprovante));
// este fica para o teste de exclusão da conta
$ong->enviar('pages/gestao.php', array_merge($movBase, ['descricao' => 'Comprovante que fica E2E']), null, ['comprovante' => $pdfTeste]);
foreach ([$pdfTeste, $falsoPdf, $phpDisfarcado] as $arquivo) {
    @unlink($arquivo);
}

$projOk = ['acao' => 'edit_proj', 'proj_id' => $projA, 'nome' => 'Projeto E2E', 'descricao' => 'Oficinas de teste automatizado.',
    'status' => 'andamento', 'indicador' => '42', 'meta' => '84', 'unidade' => 'oficinas', 'data_inicio' => '2025-03-01', 'data_fim' => ''];
foreach ([
    'meta zero' => [['meta' => '0'], 'A meta deve'],
    'término antes do início' => [['data_fim' => '2025-01-01'], 'antes do início'],
    'concluído no futuro' => [['status' => 'concluido', 'data_fim' => date('Y-m-d', strtotime('+10 days'))], 'não pode terminar no futuro'],
    'situação inválida' => [['status' => 'hack'], 'situação do projeto'],
    'descrição longa' => [['descricao' => str_repeat('d', 601)], 'no máximo 600'],
] as $nome => [$troca, $esperado]) {
    $r = $ong->enviar('pages/gestao.php', array_merge($projOk, $troca));
    $f = $ong->abrir($r['loc']);
    t("edição de projeto recusada: $nome", tem($f, $esperado) && str_contains($r['loc'], 'editar_proj=' . $projA));
}
$ong->enviar('pages/gestao.php', $projOk);
$p = $pdo->query("SELECT * FROM projetos WHERE id = $projA")->fetch();
t('edição de projeto salva descrição, meta e início', $p['descricao'] === 'Oficinas de teste automatizado.' && (int) $p['meta'] === 84
    && $p['data_inicio'] === '2025-03-01' && $p['atualizado_em'] !== null);
$r = $visitante->get("pages/perfil-publico.php?id=$idA&aba=projetos");
t('perfil público mostra situação, descrição e meta do projeto', tem($r, 'Em andamento') && tem($r, 'Oficinas de teste automatizado.') && tem($r, '50% da meta de 84'));
$ong->enviar('pages/gestao.php', array_merge($projOk, ['proj_id' => $projDemo, 'nome' => 'invasão E2E']));
t('ONG não edita projeto de outra ONG', $pdo->query("SELECT nome FROM projetos WHERE id = $projDemo")->fetchColumn() !== 'invasão E2E');
$ong->enviar('pages/gestao.php', ['acao' => 'add_proj', 'nome' => 'Projeto temporário E2E', 'indicador' => '1', 'unidade' => 'teste']);
$projTemp = (int) $pdo->query("SELECT id FROM projetos WHERE usuario_id = $idA ORDER BY id DESC LIMIT 1")->fetchColumn();
$ong->enviar('pages/gestao.php', array_merge($movBase, ['descricao' => 'Ligada ao projeto temporário E2E', 'projeto_id' => $projTemp]));
$movLigada = (int) $ultimaMov()['id'];
$ong->enviar('pages/gestao.php', ['acao' => 'del_proj', 'proj_id' => $projTemp]);
t('excluir o projeto mantém a movimentação ligada, só sem o vínculo',
    $pdo->query("SELECT COUNT(*) FROM movimentacoes WHERE id = $movLigada AND projeto_id IS NULL")->fetchColumn() == 1);

// ============================================================
secao('10. Portal de transparência e exportações');
// ============================================================
$r = $visitante->get('pages/transparencia.php?ano=' . date('Y') . '&busca=' . urlencode('ONG Teste E2E A'));
t('ONG aprovada aparece no portal com a receita lançada', tem($r, 'ONG Teste E2E') && tem($r, '1.234,56'));
t('relatório do portal mostra as despesas por categoria', tem($r, 'Para onde foi o dinheiro') && tem($r, 'Materiais e equipamentos'));
$r = $visitante->get("pages/actions/relatorio-anual.php?ong=$idA&ano=" . date('Y'));
t('PDF anual gerado', $r['code'] === 200 && str_starts_with($r['body'], '%PDF') && str_contains($r['type'], 'pdf'));
$r = $visitante->get("pages/actions/exportar-csv.php?ong=$idA&ano=" . date('Y'));
t('CSV gerado com a movimentação', $r['code'] === 200 && str_contains($r['type'], 'csv') && str_contains($r['body'], 'Doação teste E2E'));
foreach (["ong=$idA&ano=1999", "ong=$idA&ano=abc"] as $q) {
    $r = $visitante->get("pages/actions/exportar-csv.php?$q");
    t("CSV com ano inválido ($q) cai no ano atual", $r['code'] === 200 && str_contains($r['body'], 'Doação teste E2E'));
    $r = $visitante->get("pages/actions/relatorio-anual.php?$q");
    t("PDF com ano inválido ($q) cai no ano atual", $r['code'] === 200 && str_starts_with($r['body'], '%PDF'));
}
foreach (['ong=abc&ano=2026', 'ong=999999&ano=2026', ''] as $q) {
    $r = $visitante->get("pages/actions/exportar-csv.php?$q");
    t("CSV com parâmetros ruins ($q) responde erro limpo", $r['code'] >= 400 && $r['code'] < 500 && semErroPhp($r), "código {$r['code']}");
    $r = $visitante->get("pages/actions/relatorio-anual.php?$q");
    t("PDF com parâmetros ruins ($q) responde erro limpo", $r['code'] >= 400 && $r['code'] < 500 && semErroPhp($r), "código {$r['code']}");
}
$ong->enviar('pages/gestao.php', ['acao' => 'add_mov', 'tipo' => 'receita', 'descricao' => '=HYPERLINK("http://x")', 'valor' => '1', 'data_mov' => $hoje]);
$r = $visitante->get("pages/actions/exportar-csv.php?ong=$idA&ano=" . date('Y'));
t('CSV neutraliza célula que começa com "=" (apóstrofo na frente)', str_contains($r['body'], "'=HYPERLINK") && !preg_match('/(^|;|")=HYPERLINK/m', $r['body']));
t('CSV traz categoria, projeto e comprovante', str_contains($r['body'], 'Categoria;Projeto') && str_contains($r['body'], 'Comprovante'));

// ============================================================
secao('11. Perfil privado');
// ============================================================
$pp = 'pages/perfil-privado.php';
$r = $ong->get($pp);
t('perfil privado abre', $r['code'] === 200 && semErroPhp($r));
foreach (['editar-perfil', 'foto-e-aparencia', 'verificacao-e-pix', 'seguranca', 'conta', 'relatorio-anual', 'hack'] as $secao) {
    $r = $ong->get("$pp?secao=$secao");
    t("perfil privado: seção $secao", $r['code'] === 200 && semErroPhp($r));
}
$base = ['acao' => 'editar', 'nome' => 'ONG Teste E2E A', 'email' => $ongA['email'], 'area' => 'Cultura', 'descricao' => 'Descrição E2E',
    'cidade' => 'Matão', 'estado' => 'SP', 'canal_tipo' => 'instagram', 'canal_valor' => '@ongteste'];
foreach ([
    'nome vazio' => [['nome' => ''], 'obrigatórios'],
    'e-mail inválido' => [['email' => 'x@'], 'e-mail válido'],
    'área inválida' => [['area' => 'Hackers'], 'área de atuação válida'],
    'estado inválido' => [['estado' => 'ZZ'], 'estado da lista'],
    'canal inválido' => [['canal_tipo' => 'site', 'canal_valor' => 'nada'], 'canal público válido'],
    'descrição longa' => [['descricao' => str_repeat('x', 1001)], 'no máximo 1000'],
    'e-mail de outra conta' => [['email' => $ongB['email']], 'já está sendo usado'],
] as $nome => [$troca, $esperado]) {
    $r = $ong->enviar($pp, array_merge($base, $troca));
    $r = $r['loc'] ? $ong->abrir($r['loc']) : $r;
    t("edição recusada: $nome", tem($r, $esperado));
}
t('área continua válida no banco', $pdo->query("SELECT area FROM usuarios WHERE id = $idA")->fetchColumn() === 'Cultura');
$r = $ong->enviar($pp, array_merge($base, ['nome' => 'ONG Teste E2E A Editada', 'descricao' => '<b>negrito</b> & "aspas"']));
$r = $r['loc'] ? $ong->abrir($r['loc']) : $r;
t('edição válida salva', $pdo->query("SELECT nome FROM usuarios WHERE id = $idA")->fetchColumn() === 'ONG Teste E2E A Editada');
$r = $visitante->get("pages/perfil-publico.php?id=$idA");
t('descrição com HTML é escapada no perfil público', !tem($r, '<b>negrito</b>') && tem($r, '&lt;b&gt;negrito'));

foreach ([
    'senha atual errada' => [['senha_atual' => 'errada'], 'senha atual não confere'],
    'nova senha curta' => [['senha' => 'curta', 'senha_confirm' => 'curta'], 'pelo menos 8'],
    'confirmação diferente' => [['senha_confirm' => 'Diferente123'], 'não coincidem'],
    'nova senha igual à atual' => [['senha' => SENHA_ONG, 'senha_confirm' => SENHA_ONG], 'diferente da senha atual'],
    'nova senha comum' => [['senha' => 'Senha@123', 'senha_confirm' => 'Senha@123'], 'muito comum'],
] as $nome => [$troca, $esperado]) {
    $r = $ong->enviar($pp, array_merge(['acao' => 'senha', 'senha_atual' => SENHA_ONG, 'senha' => 'NovaSenha123', 'senha_confirm' => 'NovaSenha123'], $troca), "$pp?secao=seguranca");
    $r = $r['loc'] ? $ong->abrir($r['loc']) : $r;
    t("troca de senha recusada: $nome", tem($r, $esperado));
}
$ong->enviar($pp, ['acao' => 'senha', 'senha_atual' => SENHA_ONG, 'senha' => ' NovaSenha123 ', 'senha_confirm' => ' NovaSenha123 '], "$pp?secao=seguranca");
$hash = (string) $pdo->query("SELECT senha FROM usuarios WHERE id = $idA")->fetchColumn();
t('nova senha salva exatamente como digitada (com espaços)', password_verify(' NovaSenha123 ', $hash) && !password_verify('NovaSenha123', $hash));
$ong->enviar($pp, ['acao' => 'senha', 'senha_atual' => ' NovaSenha123 ', 'senha' => SENHA_ONG, 'senha_confirm' => SENHA_ONG], "$pp?secao=seguranca");
t('senha volta à original', password_verify(SENHA_ONG, (string) $pdo->query("SELECT senha FROM usuarios WHERE id = $idA")->fetchColumn()));

// cor do banner: só a paleta de config/app.php, sem cor livre nem cor de fundo
$corTema = fn (): ?string => $pdo->query("SELECT cor_tema FROM usuarios WHERE id = $idA")->fetchColumn() ?: null;
foreach (['texto' => 'vermelho', 'cor fora da paleta' => '#123ABC', 'branco' => '#FFFFFF'] as $nome => $cor) {
    $r = $ong->enviar($pp, ['acao' => 'tema_salvar', 'cor_tema' => $cor]);
    $r = $r['loc'] ? $ong->abrir($r['loc']) : $r;
    t("cor do banner recusada: $nome", tem($r, 'cores da paleta') && $corTema() === null);
}
$r = $ong->get("$pp?secao=foto-e-aparencia");
t('aparência mostra a paleta inteira e nenhum campo de cor livre', substr_count($r['body'], 'btn-cor-tema') === count(ONGHELP_CORES_BANNER)
    && !tem($r, 'type="color"') && !tem($r, 'cor_fundo'));
$ong->enviar($pp, ['acao' => 'tema_salvar', 'cor_tema' => '#3949ab']);
t('cor da paleta salva em maiúsculas', $corTema() === '#3949AB');
$r = $visitante->get("pages/perfil-publico.php?id=$idA");
t('perfil público usa a cor do banner e a cor da área no anel', tem($r, '--ong-banner:#3949AB') && tem($r, '--ong-causa:' . '#C2185B'));
$ong->enviar($pp, ['acao' => 'tema_salvar', 'cor_tema' => '#C2185B']);
t('escolher a cor da própria área volta a seguir a área', $corTema() === null);
$ong->enviar($pp, ['acao' => 'tema_salvar', 'cor_tema' => '#3949AB']);
$ong->enviar($pp, ['acao' => 'tema_reset']);
t('voltar à cor da área limpa a escolha', $corTema() === null);

$falsa = tempnam(sys_get_temp_dir(), 'e2e') . '.jpg';
file_put_contents($falsa, '<?php echo "não sou imagem"; ?>');
$r = $ong->enviar($pp, ['acao' => 'foto_salvar'], "$pp?secao=foto-e-aparencia", ['foto' => $falsa]);
$r = $r['loc'] ? $ong->abrir($r['loc']) : $r;
t('arquivo falso como foto é recusado', tem($r, 'JPG, PNG ou WebP válida') || tem($r, 'Selecione uma imagem'));
$foto = glob(__DIR__ . '/../assets/img/noticias/*.jpg')[0] ?? '';
$campoFoto = preg_match('/<input[^>]+type="file"[^>]+name="([^"]+)"/', $ong->get("$pp?secao=foto-e-aparencia")['body'], $mm) ? $mm[1] : 'foto';
if ($foto !== '' && filesize($foto) <= 2 * 1024 * 1024) {
    $ong->enviar($pp, ['acao' => 'foto_salvar'], "$pp?secao=foto-e-aparencia", [$campoFoto => $foto]);
    t('foto de perfil válida salva', (string) $pdo->query("SELECT foto_perfil FROM usuarios WHERE id = $idA")->fetchColumn() !== '');
    $ong->enviar($pp, ['acao' => 'foto_remover'], "$pp?secao=foto-e-aparencia");
    t('foto de perfil removida', $pdo->query("SELECT foto_perfil FROM usuarios WHERE id = $idA")->fetchColumn() === null);
}
@unlink($falsa);

// ============================================================
secao('12. Notícias da ONG');
// ============================================================
$ed = 'pages/noticia-editar.php';
$texto = str_repeat('Texto da notícia de teste automatizado com conteúdo suficiente. ', 3);
$baseNot = ['acao' => 'salvar', 'tipo' => 'acao_realizada', 'titulo' => 'Notícia de teste E2E válida', 'resumo' => 'Resumo da notícia de teste automatizado com tamanho ok.', 'texto' => "<p>$texto</p>"];
$nots = fn (): int => (int) $pdo->query("SELECT COUNT(*) FROM noticias WHERE usuario_id = $idA")->fetchColumn();
$r = $ong->get($ed);
t('editor de notícia abre', $r['code'] === 200 && semErroPhp($r));
foreach ([
    'título curto' => ['titulo' => 'Curto'],
    'resumo curto' => ['resumo' => 'Pouco'],
    'texto curto' => ['texto' => '<p>pouco</p>'],
    'tipo inválido' => ['tipo' => 'hack'],
    'evento sem data' => ['tipo' => 'evento'],
] as $nome => $troca) {
    $r = $ong->enviar($ed, array_merge($baseNot, ['acao' => 'publicar'], $troca));
    $pub = (int) $pdo->query("SELECT COUNT(*) FROM noticias WHERE usuario_id = $idA AND estado = 'publicada'")->fetchColumn();
    t("notícia não publica com problema: $nome", $pub === 0 && semErroPhp($r), "código {$r['code']}");
}
$r = $ong->enviar($ed, array_merge($baseNot, ['texto' => "<p>$texto</p><script>alert(1)</script><img src=x onerror=alert(1)>"]));
t('rascunho é salvo (rascunho aceita conteúdo incompleto; as regras valem ao publicar)', $nots() >= 1, "código {$r['code']}");
$notId = (int) $pdo->query("SELECT MAX(id) FROM noticias WHERE usuario_id = $idA")->fetchColumn();
$html = (string) $pdo->query("SELECT texto FROM noticias WHERE id = $notId")->fetchColumn();
t('HTML perigoso é removido do texto', !str_contains($html, '<script') && !str_contains($html, 'onerror'));
$r = $visitante->get("pages/noticia.php?id=$notId");
t('rascunho não é público', $r['code'] === 404);
$r = $ong->enviar('pages/minhas-noticias.php', ['acao' => 'publicar', 'noticia_id' => $notId]);
$estado = fn (): string => (string) $pdo->query("SELECT estado FROM noticias WHERE id = $notId")->fetchColumn();
t('publicar sem foto é permitido (usa a imagem ilustrativa da área)', $estado() === 'publicada');
// volta a rascunho para o teste seguinte adicionar fotos
$ong->enviar($ed, ['acao' => 'despublicar', 'noticia_id' => $notId], "$ed?id=$notId");
t('notícia despublicada volta a rascunho', $estado() === 'rascunho');

$fotoNot = glob(__DIR__ . '/../assets/img/noticias/*.jpg')[0] ?? '';
$r = $ong->enviar("$ed?id=$notId", array_merge($baseNot, [
    'acao' => 'publicar', 'noticia_id' => $notId, 'direitos_imagem' => '1', 'capa' => 'nova-0',
    'novas_alt' => ['0' => 'Foto de teste automatizado do evento'],
]), "$ed?id=$notId", ['fotos_novas[0]' => $fotoNot]);
t('publicar com foto e direitos de imagem', $estado() === 'publicada', "código {$r['code']} → {$r['loc']}");
$slug = (string) $pdo->query("SELECT slug FROM noticias WHERE id = $notId")->fetchColumn();
$r = $visitante->get("pages/noticia.php?id=$notId&slug=$slug");
t('notícia publicada fica pública', $r['code'] === 200 && tem($r, 'Notícia de teste E2E'));

$alheia = (int) $pdo->query("SELECT id FROM noticias WHERE usuario_id <> $idA ORDER BY id LIMIT 1")->fetchColumn();
$r = $ong->get("$ed?id=$alheia");
t('ONG não abre editor de notícia de outra ONG', $r['code'] === 302 || $r['code'] === 404);
$antes = (string) $pdo->query("SELECT estado FROM noticias WHERE id = $alheia")->fetchColumn();
$ong->enviar('pages/minhas-noticias.php', ['acao' => 'excluir', 'noticia_id' => $alheia]);
$ong->enviar('pages/minhas-noticias.php', ['acao' => 'despublicar', 'noticia_id' => $alheia]);
t('ONG não exclui nem despublica notícia de outra ONG', (string) $pdo->query("SELECT estado FROM noticias WHERE id = $alheia")->fetchColumn() === $antes);

$ong->enviar('pages/minhas-noticias.php', ['acao' => 'despublicar', 'noticia_id' => $notId]);
t('despublicar a própria notícia', $estado() !== 'publicada');
$ong->enviar('pages/minhas-noticias.php', ['acao' => 'publicar', 'noticia_id' => $notId]);
t('republicar a própria notícia', $estado() === 'publicada');

// ============================================================
secao('12b. Imagens ilustrativas e mosaico');
// ============================================================
// a ONG B (área Cultura) publica notícias sem foto e com foto
$ongBNav = new Navegador($BASE);
$ongBNav->enviar('pages/login.php', ['email' => $ongB['email'], 'senha' => SENHA_ONG]);
$r = $ongBNav->get($ed);
t('editor oferece as 3 imagens ilustrativas da área da ONG',
    substr_count($r['body'], 'name="imagem_padrao"') === 3 && tem($r, 'img/noticias/cultura-1.jpg') && tem($r, 'img/noticias/cultura-3.jpg'));
t('nenhuma imagem vem marcada (sem escolha, o site alterna)', !preg_match('/name="imagem_padrao"[^>]*checked/', $r['body']));

$baseB = ['acao' => 'salvar', 'tipo' => 'projeto', 'titulo' => 'Notícia E2E da ONG B sem foto', 'resumo' => 'Resumo da notícia de teste da ONG B com tamanho ok.', 'texto' => "<p>$texto</p>"];
$ongBNav->enviar($ed, array_merge($baseB, ['imagem_padrao' => '3']));
$notB = (int) $pdo->query("SELECT MAX(id) FROM noticias WHERE usuario_id = $idB")->fetchColumn();
$img = fn (): ?int => ($v = $pdo->query("SELECT imagem_padrao FROM noticias WHERE id = $notB")->fetchColumn()) === null ? null : (int) $v;
t('rascunho guarda a imagem escolhida', $notB > 0 && $img() === 3);
$r = $ongBNav->get("$ed?id=$notB");
t('editor reabre com a imagem escolhida marcada', (bool) preg_match('/name="imagem_padrao" value="3"\s+checked/', $r['body']));

foreach (['0', '4', 'abc', '-1', '"><script>alert(1)</script>'] as $invalido) {
    $r = $ongBNav->enviar("$ed?id=$notB", array_merge($baseB, ['noticia_id' => $notB, 'imagem_padrao' => $invalido]), "$ed?id=$notB");
    t('valor inválido de imagem vira "sem escolha": ' . substr($invalido, 0, 12), $img() === null && semErroPhp($r) && !tem($r, '<script>alert(1)'));
}
$r = $ongBNav->enviar("$ed?id=$notB", array_merge($baseB, ['noticia_id' => $notB, 'imagem_padrao' => ['2']]), "$ed?id=$notB");
t('imagem enviada como lista não quebra o editor', semErroPhp($r) && $img() === null);

$ongBNav->enviar("$ed?id=$notB", array_merge($baseB, ['acao' => 'publicar', 'noticia_id' => $notB, 'imagem_padrao' => '2']), "$ed?id=$notB");
$estB = fn (int $id): string => (string) $pdo->query("SELECT estado FROM noticias WHERE id = $id")->fetchColumn();
t('publica sem foto e sem declarar direitos de imagem', $estB($notB) === 'publicada'
    && $pdo->query("SELECT direitos_imagem_aceito_em FROM noticias WHERE id = $notB")->fetchColumn() === null);

$slugB = (string) $pdo->query("SELECT slug FROM noticias WHERE id = $notB")->fetchColumn();
$r = $visitante->get("pages/noticia.php?id=$notB&slug=$slugB");
t('página da notícia mostra a imagem escolhida e o aviso', tem($r, 'img/noticias/cultura-2.jpg') && tem($r, 'Imagem ilustrativa'));
t('imagem ilustrativa não abre o visualizador de fotos', !tem($r, 'id="ntVisualizador"') && !tem($r, 'data-nt-foto="0"'));
t('og:image aponta para a imagem ilustrativa (URL absoluta)', (bool) preg_match('~property="og:image" content="https?://[^"]+/assets/img/noticias/cultura-2\.jpg~', $r['body']));

// o card de cada lista precisa mostrar a mesma imagem da página
$cardDe = static function (array $r, int $id): string {
    return preg_match('~<a class="nt-card[^"]*" href="[^"]*id=' . $id . '[^"]*">.*?</a>~s', $r['body'], $m) ? $m[0] : '';
};
foreach (['hub de notícias' => 'pages/noticias.php', 'filtro por ONG' => "pages/noticias.php?ong=$idB", 'filtro pela causa' => 'pages/noticias.php?area=Cultura', 'perfil público' => "pages/perfil-publico.php?id=$idB&aba=noticias", 'home' => 'index.php'] as $onde => $pagina) {
    $r = $visitante->get($pagina);
    $card = $cardDe($r, $notB);
    t("card no(a) $onde usa a imagem escolhida", $card !== '' && str_contains($card, 'cultura-2.jpg') && semErroPhp($r), $card === '' ? 'card não encontrado' : '');
}
$r = $ongBNav->get('pages/minhas-noticias.php');
t('miniatura em Minhas notícias usa a imagem escolhida', tem($r, 'img/noticias/cultura-2.jpg'));
$r = $adm->get('admin/noticias.php?aba=publicacoes');
t('miniatura no painel de moderação usa a imagem escolhida', tem($r, 'img/noticias/cultura-2.jpg'));

$ongBNav->enviar("$ed?id=$notB", array_merge($baseB, ['noticia_id' => $notB, 'imagem_padrao' => '1']), "$ed?id=$notB");
$r = $visitante->get("pages/noticia.php?id=$notB&slug=$slugB");
t('trocar a imagem de notícia já publicada vale na hora', $img() === 1 && $estB($notB) === 'publicada' && tem($r, 'cultura-1.jpg'));
$pdo->exec("UPDATE noticias SET imagem_padrao = NULL WHERE id = $notB");
$r = $visitante->get("pages/noticia.php?id=$notB&slug=$slugB");
t('sem escolha, usa a imagem automática da área', tem($r, 'img/noticias/cultura-' . ($notB % 3 + 1) . '.jpg'));

// com foto própria: direitos de imagem voltam a ser exigidos
$fotoB = glob(__DIR__ . '/../assets/img/noticias/*.jpg')[1] ?? '';
$baseFoto = array_merge($baseB, ['acao' => 'publicar', 'titulo' => 'Notícia E2E da ONG B com foto', 'capa' => 'nova-0', 'novas_alt' => ['0' => 'Foto de teste automatizado da ONG B']]);
$ongBNav->enviar($ed, $baseFoto, $ed, ['fotos_novas[0]' => $fotoB]);
$notF = (int) $pdo->query("SELECT MAX(id) FROM noticias WHERE usuario_id = $idB")->fetchColumn();
t('com foto e sem direitos de imagem, não publica', $notF > $notB && $estB($notF) === 'rascunho');
$r = $ongBNav->enviar("$ed?id=$notF", array_merge($baseFoto, ['noticia_id' => $notF, 'direitos_imagem' => '1']), "$ed?id=$notF");
t('com foto e direitos de imagem, publica', $estB($notF) === 'publicada', "código {$r['code']}");
$slugF = (string) $pdo->query("SELECT slug FROM noticias WHERE id = $notF")->fetchColumn();
$r = $visitante->get("pages/noticia.php?id=$notF&slug=$slugF");
t('notícia com foto mostra a foto, sem aviso de ilustrativa', tem($r, 'uploads/noticias/') && !tem($r, 'Imagem ilustrativa'));
$r = $visitante->get("pages/noticias.php?ong=$idB");
$card = $cardDe($r, $notF);
t('card da notícia com foto usa a foto enviada', str_contains($card, 'uploads/noticias/') && !str_contains($card, 'img/noticias/'));
$fotoId = (int) $pdo->query("SELECT id FROM noticias_fotos WHERE noticia_id = $notF")->fetchColumn();
$ongBNav->enviar("$ed?id=$notF", array_merge($baseFoto, ['acao' => 'salvar', 'noticia_id' => $notF, 'fotos' => [$fotoId => ['alt' => 'Foto de teste automatizado da ONG B', 'remover' => '1']]]), "$ed?id=$notF");
$r = $visitante->get("pages/noticia.php?id=$notF&slug=$slugF");
t('removida a única foto, a notícia volta a usar a ilustrativa', (int) $pdo->query("SELECT COUNT(*) FROM noticias_fotos WHERE noticia_id = $notF")->fetchColumn() === 0 && tem($r, 'Imagem ilustrativa'));

// mosaico: os cards de cada página fecham as linhas sem buracos
$semBuraco = static function (string $html): ?bool {
    if (!preg_match('~<div class="nt-grade nt-grade--mosaico">(.*?)</div>\s*(?:<nav|</section|<div class="nt-pag)~s', $html, $m)) {
        return null;
    }
    preg_match_all('~<a class="nt-card nt-card--padrao( is-[a-z]+)?"~', $m[1], $c);
    $cel = 0;
    foreach ($c[1] as $tam) {
        $cel += ['' => 1, ' is-largo' => 2, ' is-alto' => 2, ' is-inteiro' => 3][$tam];
    }
    return $c[1] !== [] && $cel % 3 === 0;
};
foreach (['página 1' => 'pages/noticias.php', 'página 2' => 'pages/noticias.php?pagina=2', 'última página' => 'pages/noticias.php?pagina=99', 'causa Outra' => 'pages/noticias.php?area=Outra', 'causa Cultura' => 'pages/noticias.php?area=Cultura', 'uma ONG' => "pages/noticias.php?ong=$idB", 'busca' => 'pages/noticias.php?busca=mutir%C3%A3o'] as $onde => $pagina) {
    $r = $visitante->get($pagina);
    $ok = $semBuraco($r['body']);
    t("mosaico sem buracos ($onde)", $ok === true && semErroPhp($r), $ok === null ? "grade não encontrada" : "");
}

// área "Outra" nos filtros, com ONGs e cor própria
$r = $visitante->get('pages/noticias.php');
$posOutra = strpos($r['body'], 'area=Outra');
$posSaude = strpos($r['body'], 'area=Sa%C3%BAde');
t('filtro "Outra" aparece nas notícias, depois das outras causas', $posOutra !== false && $posSaude !== false && $posOutra > $posSaude);
$r = $visitante->get('pages/noticias.php?area=Outra');
t('filtro "Outra" mostra só notícias dessa causa', tem($r, 'Coletivo Bairro Vivo') && !tem($r, 'Patas Unidas'));
$r = $visitante->get('pages/ongs.php?area=Outra');
t('diretório filtrado por "Outra" lista as ONGs dessa área', tem($r, 'Coletivo Bairro Vivo') && tem($r, 'Instituto Rede Voluntária') && !tem($r, 'Patas Unidas'));
t('"Outra" tem cor própria (não o cinza neutro)', tem($r, '#9A6B3F') || tem($r, '#9a6b3f'));
$r = $visitante->get('pages/transparencia.php');
t('portal de transparência lista "Outra" como categoria', tem($r, 'Outra') && semErroPhp($r));

// ============================================================
secao('13. Moderação de notícias pelo admin');
// ============================================================
$r = $adm->enviar('admin/noticias.php', ['acao' => 'ocultar', 'noticia_id' => $notId, 'categoria' => 'outro', 'mensagem' => 'curta'], 'admin/noticias.php');
t('ocultar com mensagem curta é recusado', $estado() === 'publicada');
$adm->enviar('admin/noticias.php', ['acao' => 'ocultar', 'noticia_id' => $notId, 'categoria' => 'hack', 'mensagem' => str_repeat('m', 30)], 'admin/noticias.php');
t('ocultar com categoria inválida é recusado', $estado() === 'publicada');
$adm->enviar('admin/noticias.php', ['acao' => 'ocultar', 'noticia_id' => $notId, 'categoria' => 'outro', 'mensagem' => 'Teste automatizado: conteúdo ocultado.'], 'admin/noticias.php');
t('admin oculta notícia', $estado() === 'oculta');
$r = $visitante->get("pages/noticia.php?id=$notId&slug=$slug");
t('notícia oculta deixa de ser pública', $r['code'] === 404);
$r = $ong->get('pages/minhas-noticias.php');
t('ONG vê a mensagem da ocultação', tem($r, 'conteúdo ocultado'));
$ong->enviar('pages/minhas-noticias.php', ['acao' => 'publicar', 'noticia_id' => $notId]);
t('ONG não republica notícia oculta pela equipe', $estado() === 'oculta');
$adm->enviar('admin/noticias.php', ['acao' => 'restaurar', 'noticia_id' => $notId], 'admin/noticias.php');
t('admin restaura notícia', $estado() !== 'oculta');

$r = $adm->enviar('admin/noticias.php', ['acao' => 'ocultar_bloquear', 'noticia_id' => $notId, 'categoria' => 'outro', 'mensagem' => 'Teste automatizado: bloqueio.', 'motivo' => 'Teste E2E', 'entendo' => ''], 'admin/noticias.php');
t('bloqueio sem confirmação é recusado', (int) $pdo->query("SELECT COUNT(*) FROM noticias_bloqueios WHERE usuario_id = $idA AND liberado_em IS NULL")->fetchColumn() === 0);
$adm->enviar('admin/noticias.php', ['acao' => 'ocultar_bloquear', 'noticia_id' => $notId, 'categoria' => 'outro', 'mensagem' => 'Teste automatizado: bloqueio.', 'motivo' => 'Teste E2E', 'entendo' => '1'], 'admin/noticias.php');
t('admin oculta e bloqueia publicações', (int) $pdo->query("SELECT COUNT(*) FROM noticias_bloqueios WHERE usuario_id = $idA AND liberado_em IS NULL")->fetchColumn() === 1);
$antes = $nots();
$ong->enviar($ed, array_merge($baseNot, ['acao' => 'publicar', 'titulo' => 'Outra notícia de teste E2E']));
t('ONG bloqueada não publica nova notícia', (int) $pdo->query("SELECT COUNT(*) FROM noticias WHERE usuario_id = $idA AND estado = 'publicada'")->fetchColumn() === 0);

// ============================================================
secao('13b. Notificações da ONG');
// navbar da ONG logada: seis itens, Legislação antes de Gestão, e a classe que reorganiza o desktop
$r = $ong->get('pages/legislacao.php');
$pilula = preg_match('~<ul class="navbar-nav site-nav-pill">(.*?)</ul>~s', $r['body'], $m) ? $m[1] : '';
t('ONG logada vê seis itens na navbar', substr_count($pilula, 'class="site-nav-link') === 6);
t('Legislação vem antes de Gestão para a ONG logada', strpos($pilula, 'legislacao.php') !== false && strpos($pilula, 'legislacao.php') < strpos($pilula, 'gestao.php'));
t('navbar da ONG logada tem a classe is-logado', str_contains($r['body'], 'site-navbar sticky-top is-logado'));
t('navbar do visitante não tem a classe is-logado', !str_contains($visitante->get('index.php')['body'], 'is-logado'));
// ============================================================
t('ocultação, restauração e bloqueio geram avisos', !array_diff(['noticia_ocultada', 'noticia_restaurada', 'publicacoes_bloqueadas'], $avisos($idA)));
$r = $ong->get('pages/gestao.php');
t('sino mostra a contagem de avisos novos', tem($r, 'site-nav-sino-num') && tem($r, 'Notícia ocultada pela moderação'));
$r = $ong->get('pages/notificacoes.php');
t('página de notificações lista os avisos', $r['code'] === 200 && semErroPhp($r) && tem($r, 'Notícia ocultada pela moderação'));
$idAviso = (int) $pdo->query("SELECT id FROM notificacoes WHERE usuario_id = $idA AND tipo = 'noticia_ocultada' LIMIT 1")->fetchColumn();
$r = $ong->enviar('pages/notificacoes.php', ['acao' => 'abrir', 'id' => $idAviso]);
t('abrir um aviso marca como lido e leva ao link dele', str_contains($r['loc'], 'minhas-noticias.php')
    && $pdo->query("SELECT lida_em FROM notificacoes WHERE id = $idAviso")->fetchColumn() !== null);
$avisoOutra = (int) $pdo->query("SELECT id FROM notificacoes WHERE usuario_id <> $idA AND lida_em IS NULL LIMIT 1")->fetchColumn();
$ong->enviar('pages/notificacoes.php', ['acao' => 'abrir', 'id' => $avisoOutra]);
t('ONG não marca aviso de outra ONG', $pdo->query("SELECT lida_em FROM notificacoes WHERE id = $avisoOutra")->fetchColumn() === null);
$ong->enviar('pages/notificacoes.php', ['acao' => 'ler_todas']);
t('marcar todas como lidas apaga a contagem do sino', (int) $pdo->query("SELECT COUNT(*) FROM notificacoes WHERE usuario_id = $idA AND lida_em IS NULL")->fetchColumn() === 0
    && !tem($ong->get('pages/gestao.php'), 'site-nav-sino-num'));
$r = $visitante->get('pages/notificacoes.php');
t('visitante não abre notificações', $r['code'] === 302 && str_contains($r['loc'], 'login'));

// avisos escritos pelo administrador (admin/avisos.php)
$r = $adm->get('admin/avisos.php');
t('admin abre a página de avisos às ONGs', $r['code'] === 200 && semErroPhp($r) && tem($r, 'Avisos enviados'));
$r = $adm->get("admin/verificar.php?id=$idA");
t('análise de ONG aprovada tem atalho para enviar aviso', tem($r, "avisos.php?ong=$idA"));
$avisosTeste = fn (): int => (int) $pdo->query("SELECT COUNT(*) FROM avisos_equipe WHERE titulo LIKE '%Teste E2E%'")->fetchColumn();
$adm->enviar('admin/avisos.php', ['acao' => 'enviar', 'destino' => 'ong', 'ong_id' => $idA, 'titulo' => 'Oi', 'mensagem' => 'Mensagem do Teste E2E', 'link' => ''], 'admin/avisos.php');
t('aviso com título curto é recusado', $avisosTeste() === 0);
$adm->enviar('admin/avisos.php', ['acao' => 'enviar', 'destino' => 'ong', 'ong_id' => $idA, 'titulo' => 'Aviso Teste E2E', 'mensagem' => 'Mensagem do Teste E2E', 'link' => 'https://exemplo.com'], 'admin/avisos.php');
t('aviso com link de fora é recusado', $avisosTeste() === 0);
$adm->enviar('admin/avisos.php', ['acao' => 'enviar', 'destino' => 'ong', 'ong_id' => $idC, 'titulo' => 'Aviso Teste E2E', 'mensagem' => 'Mensagem do Teste E2E', 'link' => ''], 'admin/avisos.php');
t('aviso para ONG não aprovada é recusado', $avisosTeste() === 0);
$r = $adm->enviar('admin/avisos.php', ['acao' => 'enviar', 'destino' => 'ong', 'ong_id' => $idA, 'titulo' => 'Aviso individual Teste E2E', 'mensagem' => 'Mensagem só para a ONG A do Teste E2E', 'link' => 'pages/gestao.php'], 'admin/avisos.php');
$copias = $pdo->query("SELECT n.usuario_id FROM notificacoes n JOIN avisos_equipe a ON a.id = n.aviso_id WHERE a.titulo = 'Aviso individual Teste E2E'")->fetchAll(PDO::FETCH_COLUMN);
t('aviso para uma ONG chega só a ela', $r['code'] === 302 && array_map('intval', $copias) === [$idA]);
$aprovadas = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE status = 'aprovada'")->fetchColumn();
$adm->enviar('admin/avisos.php', ['acao' => 'enviar', 'destino' => 'todas', 'titulo' => 'Aviso geral Teste E2E', 'mensagem' => 'Mensagem para todas as ONGs do Teste E2E', 'link' => ''], 'admin/avisos.php');
$geral = $pdo->query("SELECT destinatarios, (SELECT COUNT(*) FROM notificacoes n WHERE n.aviso_id = a.id) AS copias FROM avisos_equipe a WHERE titulo = 'Aviso geral Teste E2E'")->fetch();
t('aviso para todas chega a cada ONG aprovada', $geral && (int) $geral['copias'] === $aprovadas && (int) $geral['destinatarios'] === $aprovadas, $geral ? "{$geral['copias']} de $aprovadas" : 'não gravado');
t('envio de aviso fica nos logs', (int) $pdo->query("SELECT COUNT(*) FROM logs_admin WHERE acao = 'aviso_enviado' AND detalhe LIKE '%Teste E2E%'")->fetchColumn() === 2);
$r = $ong->get('pages/notificacoes.php');
t('ONG vê o aviso da equipe nas notificações', tem($r, 'Aviso individual Teste E2E') && tem($r, 'Aviso geral Teste E2E'));
$idAvisoA = (int) $pdo->query("SELECT n.id FROM notificacoes n JOIN avisos_equipe a ON a.id = n.aviso_id WHERE a.titulo = 'Aviso individual Teste E2E'")->fetchColumn();
$ong->enviar('pages/notificacoes.php', ['acao' => 'abrir', 'id' => $idAvisoA]);
$r = $adm->get('admin/avisos.php');
t('histórico mostra quantas ONGs leram', tem($r, 'Aviso individual Teste E2E') && preg_match('~<strong>1</strong> de 1 leu~', $r['body']) === 1);

// ============================================================
secao('14. Suspensão e reativação');
// ============================================================
$adm->enviar('admin/acao.php', ['acao' => 'suspender', 'id' => $idB, 'motivo' => ''], "admin/verificar.php?id=$idB");
t('suspender sem motivo é recusado', $st($idB) === 'aprovada');
$adm->enviar('admin/acao.php', ['acao' => 'suspender', 'id' => $idB, 'motivo' => 'Teste E2E'], "admin/verificar.php?id=$idB");
t('admin suspende ONG', $st($idB) === 'suspensa');
$r = $visitante->get("pages/perfil-publico.php?id=$idB");
t('ONG suspensa sai do ar', $r['code'] === 404);
$ongB2 = new Navegador($BASE);
$r = $ongB2->enviar('pages/login.php', ['email' => $ongB['email'], 'senha' => SENHA_ONG]);
t('ONG suspensa não entra e recebe aviso', tem($r, 'suspensa'));
$adm->enviar('admin/acao.php', ['acao' => 'reativar', 'id' => $idB], "admin/verificar.php?id=$idB");
t('admin reativa ONG', $st($idB) === 'aprovada');
$r = $ongB2->enviar('pages/login.php', ['email' => $ongB['email'], 'senha' => SENHA_ONG]);
t('ONG reativada volta a entrar', $r['code'] === 302);
t('suspensão e reativação geram avisos', !array_diff(['cadastro_suspenso', 'cadastro_reativado'], $avisos($idB)));

// cada decisão só parte do status certo, mesmo com POST montado à mão
$adm->enviar('admin/acao.php', ['acao' => 'reativar', 'id' => $idC], "admin/verificar.php?id=$idC");
t('reativar uma ONG recusada não a aprova sem checklist', $st($idC) === 'recusada', $st($idC));
$adm->enviar('admin/acao.php', ['acao' => 'suspender', 'id' => $idC, 'motivo' => 'Teste E2E'], "admin/verificar.php?id=$idC");
t('suspender só vale para ONG aprovada', $st($idC) === 'recusada', $st($idC));
$adm->enviar('admin/acao.php', ['acao' => 'recusar', 'id' => $idB, 'motivo' => 'Teste E2E'], "admin/verificar.php?id=$idB");
t('ONG aprovada não é recusada (o caminho é suspender)', $st($idB) === 'aprovada', $st($idB));
$adm->enviar('admin/acao.php', ['acao' => 'reativar', 'id' => $idB], "admin/verificar.php?id=$idB");
t('reativar ONG já aprovada não mexe no cadastro', $st($idB) === 'aprovada');
$r = $adm->get("admin/verificar.php?id=$idB");
t('painel da ONG aprovada oferece Suspender e não Recusar', tem($r, 'value="suspender"') && !tem($r, 'value="recusar"'));
$r = $adm->get("admin/verificar.php?id=$idC");
t('painel da ONG recusada oferece Recusar e não Suspender', tem($r, 'value="recusar"') && !tem($r, 'value="suspender"'));
t('botão Aprovar funciona sem JavaScript (não vem desabilitado do servidor)', preg_match('~id="btnAprovar"[^>]*disabled~', $r['body']) === 0);

// sessão de ONG suspensa durante o uso deve cair
$adm->enviar('admin/acao.php', ['acao' => 'suspender', 'id' => $idB, 'motivo' => 'Teste E2E 2'], "admin/verificar.php?id=$idB");
$r = $ongB2->get('pages/gestao.php');
t('sessão aberta de ONG suspensa é encerrada', $r['code'] === 302 && str_contains($r['loc'], 'login'), "código {$r['code']} → {$r['loc']}");

// ============================================================
secao('15. Equipe: moderador e permissões');
// ============================================================
$emailMod = "e2e.mod.$sufixo" . E2E_DOMINIO;
$adm->enviar('admin/equipe.php', ['acao' => 'criar_moderador', 'nome' => 'Mo', 'email' => $emailMod], 'admin/equipe.php');
t('moderador com nome curto é recusado', (int) $pdo->query("SELECT COUNT(*) FROM admins WHERE email = " . $pdo->quote($emailMod))->fetchColumn() === 0);
$r = $adm->enviar('admin/equipe.php', ['acao' => 'criar_moderador', 'nome' => 'Moderação Teste E2E', 'email' => $emailMod], 'admin/equipe.php');
$pagina = $adm->abrir($r['loc']);
t('moderador criado', (int) $pdo->query("SELECT COUNT(*) FROM admins WHERE email = " . $pdo->quote($emailMod))->fetchColumn() === 1);
$senhaTemp = preg_match('/id="credencialSenha">([^<]+)</', $pagina['body'], $mm) ? html_entity_decode($mm[1]) : '';
t('senha temporária mostrada uma única vez', $senhaTemp !== '' && !tem($adm->get('admin/equipe.php'), $senhaTemp));
$r = $adm->enviar('admin/equipe.php', ['acao' => 'criar_moderador', 'nome' => 'Moderação Teste E2E', 'email' => $emailMod], 'admin/equipe.php');
t('e-mail de moderador repetido é recusado', (int) $pdo->query("SELECT COUNT(*) FROM admins WHERE email = " . $pdo->quote($emailMod))->fetchColumn() === 1);

$mod = new Navegador($BASE);
$r = $mod->enviar('admin/login.php', ['email' => $emailMod, 'senha' => $senhaTemp]);
t('moderador entra com a senha temporária e vai ao primeiro acesso', $r['code'] === 302 && str_contains($r['loc'], 'primeiro-acesso'), "→ {$r['loc']}");
$r = $mod->get('admin/index.php');
t('antes do primeiro acesso o painel fica bloqueado', $r['code'] === 302 && str_contains($r['loc'], 'primeiro-acesso'));
$r = $mod->enviar('admin/primeiro-acesso.php', ['senha' => 'fraca', 'confirmacao' => 'fraca', 'aceite_regras' => '1']);
t('primeiro acesso recusa senha fraca', $r['code'] === 200 || str_contains($r['loc'], 'primeiro-acesso'));
$r = $mod->enviar('admin/primeiro-acesso.php', ['senha' => 'ModeracaoSemNumero', 'confirmacao' => 'ModeracaoSemNumero', 'aceite_regras' => '1']);
t('primeiro acesso recusa senha sem números', $r['code'] === 200 && tem($r, 'letras e números'));
$r = $mod->enviar('admin/primeiro-acesso.php', ['senha' => 'Moderacao@E2E2026', 'confirmacao' => 'Moderacao@E2E2026']);
t('primeiro acesso exige aceitar as regras', $r['code'] === 200 || str_contains($r['loc'], 'primeiro-acesso'));
$r = $mod->enviar('admin/primeiro-acesso.php', ['senha' => 'Moderacao@E2E2026', 'confirmacao' => 'Moderacao@E2E2026', 'aceite_regras' => '1']);
t('primeiro acesso concluído', $r['code'] === 302 && !str_contains($r['loc'], 'primeiro-acesso'), "→ {$r['loc']}");
$r = $mod->get('admin/index.php');
t('moderador vê o painel', $r['code'] === 200);
$r = $mod->get('admin/equipe.php');
t('moderador não gerencia a equipe', $r['code'] === 403 || ($r['code'] === 200 && tem($r, 'não está disponível')) || $r['code'] === 302);
$r = $mod->enviar('admin/equipe.php', ['acao' => 'criar_moderador', 'nome' => 'Intruso Teste', 'email' => "e2e.intruso.$sufixo" . E2E_DOMINIO], 'admin/index.php');
t('moderador não cria contas da equipe', (int) $pdo->query("SELECT COUNT(*) FROM admins WHERE email LIKE 'e2e.intruso.%'")->fetchColumn() === 0);
$r = $mod->get('admin/avisos.php');
t('moderador não abre os avisos às ONGs', $r['code'] === 403 || ($r['code'] === 200 && tem($r, 'não está disponível')) || $r['code'] === 302);
$mod->enviar('admin/avisos.php', ['acao' => 'enviar', 'destino' => 'todas', 'titulo' => 'Intruso Teste E2E', 'mensagem' => 'Mensagem de intruso do Teste E2E', 'link' => ''], 'admin/index.php');
t('moderador não envia aviso', (int) $pdo->query("SELECT COUNT(*) FROM avisos_equipe WHERE titulo = 'Intruso Teste E2E'")->fetchColumn() === 0);
t('menu do moderador não mostra os avisos', !tem($mod->get('admin/index.php'), 'href="avisos.php"'));

$idMod = (int) $pdo->query("SELECT id FROM admins WHERE email = " . $pdo->quote($emailMod))->fetchColumn();
$adm->enviar('admin/equipe.php', ['acao' => 'desativar', 'id' => $idMod], 'admin/equipe.php');
$r = $mod->get('admin/index.php');
t('moderador desativado perde a sessão', $r['code'] === 302 && str_contains($r['loc'], 'login'), "código {$r['code']} → {$r['loc']}");
$r = (new Navegador($BASE))->enviar('admin/login.php', ['email' => $emailMod, 'senha' => 'Moderacao@E2E2026']);
t('moderador desativado não entra', !str_contains($r['loc'], 'admin/index.php'));

// ============================================================
secao('16. Logout e exclusão de conta');
// ============================================================
$r = $ong->req('GET', 'pages/actions/logout.php');
$r = $ong->get('pages/gestao.php');
t('logout por GET não derruba a sessão (exige POST + CSRF)', $r['code'] === 200);
$ong->enviar('pages/actions/logout.php', [], 'pages/gestao.php');
$r = $ong->get('pages/gestao.php');
t('logout por POST encerra a sessão', $r['code'] === 302 && str_contains($r['loc'], 'login'));

$ong->enviar('pages/login.php', ['email' => $ongA['email'], 'senha' => SENHA_ONG]);
$r = $ong->enviar('pages/excluir-conta.php', []);
t('excluir conta sem confirmar não exclui', idOng($pdo, $ongA['email']) === $idA);
$fotosNoDisco = $pdo->query("SELECT f.arquivo FROM noticias_fotos f JOIN noticias n ON n.id = f.noticia_id WHERE n.usuario_id = $idA")->fetchAll(PDO::FETCH_COLUMN);
$comprovantesNoDisco = $pdo->query("SELECT comprovante FROM movimentacoes WHERE usuario_id = $idA AND comprovante IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
$r = $ong->enviar('pages/excluir-conta.php', ['confirmar' => '1']);
t('excluir conta com confirmação apaga a ONG', idOng($pdo, $ongA['email']) === 0, "código {$r['code']} → {$r['loc']}");
t('notícias e movimentações da ONG excluída somem', (int) $pdo->query("SELECT COUNT(*) FROM movimentacoes WHERE usuario_id = $idA")->fetchColumn() === 0);
$sobrou = array_filter($fotosNoDisco, static fn ($a) => is_file(__DIR__ . '/../uploads/noticias/' . basename((string) $a)));
t('fotos das notícias da conta excluída saem do disco', $sobrou === [], count($sobrou) . ' arquivo(s) sobrando');
t('comprovantes da conta excluída saem do disco', $comprovantesNoDisco !== []
    && array_filter($comprovantesNoDisco, static fn ($a) => is_file(__DIR__ . '/../uploads/' . $a)) === []);
$r = $ong->get('pages/gestao.php');
t('sessão da conta excluída é encerrada', $r['code'] === 302);

// ------------------------------------------------------------
echo "\n" . str_repeat('=', 60) . "\n";
echo "Testes: $total | Falhas: " . count($falhas) . "\n";
foreach ($falhas as $f) {
    echo "  - $f\n";
}
exit($falhas ? 1 : 0);
