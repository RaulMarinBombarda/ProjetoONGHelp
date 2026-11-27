<?php
// ============================================================
// tests/unidades-test.php — testes das funções, sem HTTP
// ============================================================
// confere as regras que não dependem de tela: dinheiro, CNPJ e
// PIX (BR Code e CRC), canais públicos, senhas, validação de
// movimentações e projetos, limpeza do HTML das notícias contra
// XSS, links, slugs, links de notificação, proxies confiáveis e a
// coerência da configuração (áreas, cores, categorias, ícones)
//
// uso: php tests/unidades-test.php
// não precisa do Apache, nem do banco: só lê os arquivos
// ============================================================

declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/suporte.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/core/paths.php';
require_once __DIR__ . '/../includes/core/helpers.php';
require_once __DIR__ . '/../includes/core/senha.php';
require_once __DIR__ . '/../includes/core/session.php';
require_once __DIR__ . '/../includes/modules/gestao.php';
require_once __DIR__ . '/../includes/modules/noticias.php';
require_once __DIR__ . '/../includes/modules/notificacoes.php';

/** igualdade estrita, mostrando o valor obtido quando falha */
function igual(string $nome, mixed $obtido, mixed $esperado): void
{
    t($nome, $obtido === $esperado, 'obtido ' . var_export($obtido, true) . ', esperado ' . var_export($esperado, true));
}

// ============================================================
secao('1. Escape e formatação');
// ============================================================
igual('e() escapa as aspas e os sinais de HTML', e('<a href="x">\'&'), '&lt;a href=&quot;x&quot;&gt;&#039;&amp;');
igual('e() de null vira texto vazio', e(null), '');
t('fmtDinheiro usa o padrão brasileiro', str_contains(fmtDinheiro(1234.5), '1.234,50'), fmtDinheiro(1234.5));
t('fmtDinheiro de valor negativo mantém o sinal', str_contains(fmtDinheiro(-10), '10,00') && str_contains(fmtDinheiro(-10), '-'), fmtDinheiro(-10));
igual('somenteDigitos tira máscara e letras', somenteDigitos('12.345.678/0001-95 abc'), '12345678000195');
igual('mascaraCnpj formata 14 dígitos', mascaraCnpj('12345678000195'), '12.345.678/0001-95');
igual('escurecerCor reduz cada canal', escurecerCor('#ffffff', 0.5), '#808080');
igual('escurecerCor com cor inválida usa a cor neutra', escurecerCor('azul'), '#3D4A5C');

// ============================================================
secao('2. Valores em dinheiro (valorParaDecimal)');
// ============================================================
$valores = [
    '1.234,56' => 1234.56, '1234,56' => 1234.56, 'R$ 1.000' => 1000.0, '1.000.000' => 1000000.0,
    '10.50' => 10.5, '10,5' => 10.5, '1.234' => 1234.0, '0,99' => 0.99, '' => 0.0, 'abc' => 0.0,
];
foreach ($valores as $texto => $esperado) {
    igual("valorParaDecimal('$texto')", valorParaDecimal((string) $texto), $esperado);
}
igual('sinal de menos é preservado', valorParaDecimal('-50,00'), -50.0);

// ============================================================
secao('3. CNPJ, PIX e BR Code');
// ============================================================
t('PIX igual ao CNPJ, com ou sem máscara', validarPixCnpj('12.345.678/0001-95', '12345678000195'));
t('PIX diferente do CNPJ é recusado', !validarPixCnpj('12345678000196', '12345678000195'));
t('PIX com 13 dígitos é recusado', !validarPixCnpj('1234567800019', '1234567800019'));
t('CNPJ precisa de 14 dígitos', validarCnpjFormato('12.345.678/0001-95') && !validarCnpjFormato('1234'));

// valor de conferência oficial do CRC-16/CCITT-FALSE
igual('CRC16 do BR Code bate com o valor de referência', pixCrc16('123456789'), '29B1');
igual('chave PIX de CNPJ perde a máscara', normalizarChavePix('12.345.678/0001-95'), '12345678000195');
igual('chave PIX de e-mail vira minúscula', normalizarChavePix('Contato@ONG.org'), 'contato@ong.org');
igual('chave PIX de telefone preserva o +', normalizarChavePix('+55 (16) 99999-0000'), '+5516999990000');
igual('nome do recebedor sem acento e com limite', pixTexto('Associação São João da Esperança', 25), 'Associacao Sao Joao da Es');
igual('nome vazio usa o padrão', pixTexto('   ', 25), 'ONG');

$payload = pixPayload('12.345.678/0001-95', 'Associação Ação', 'São Carlos');
t('BR Code começa pelo formato 01', str_starts_with($payload, '000201'));
t('BR Code leva a GUI do PIX e a chave', str_contains($payload, '0014br.gov.bcb.pix0114' . '12345678000195'));
t('BR Code estático não leva valor (campo 54)', !preg_match('/5303986(54)/', $payload));
igual('BR Code termina com o CRC do próprio conteúdo', substr($payload, -4), pixCrc16(substr($payload, 0, -4)));
// lê o TLV inteiro: cada tamanho declarado tem que bater com o conteúdo
$pos = 0;
$camposOk = true;
$ids = [];
while ($pos < strlen($payload)) {
    $id = substr($payload, $pos, 2);
    $tam = (int) substr($payload, $pos + 2, 2);
    $ids[] = $id;
    if ($pos + 4 + $tam > strlen($payload)) {
        $camposOk = false;
        break;
    }
    $pos += 4 + $tam;
}
t('todos os tamanhos do TLV conferem', $camposOk && $pos === strlen($payload));
igual('campos do BR Code na ordem do padrão', $ids, ['00', '26', '52', '53', '58', '59', '60', '62', '63']);
t('com valor, o campo 54 aparece formatado', str_contains(pixPayload('12345678000195', 'ONG', 'SP', 25.5), '540525.50'));
igual('chave longa demais não gera BR Code', pixPayload(str_repeat('a', 78), 'ONG', 'SP'), '');

// ============================================================
secao('4. Canal público da ONG');
// ============================================================
igual('site sem esquema ganha https', canalUrl('site', 'ong.org.br'), 'https://ong.org.br');
igual('site com javascript: não vira link', canalUrl('site', 'javascript:alert(1)'), '');
igual('instagram com @ vira link do perfil', canalUrl('instagram', '@ongteste'), 'https://instagram.com/ongteste');
igual('telefone vira tel: com DDI', canalUrl('telefone', '(16) 99999-0000'), 'tel:+16999990000');
igual('"outro" em texto livre não vira link', canalUrl('outro', 'Rua das Flores, 10'), '');
t('site sem domínio é recusado', !validarCanalPublico('site', 'nada'));
t('site com domínio é aceito', validarCanalPublico('site', 'nada.org'));
t('telefone curto é recusado', !validarCanalPublico('telefone', '1234'));
t('tipo de canal fora da lista é recusado', !validarCanalPublico('tiktok', '@ong'));
t('canal acima de 255 caracteres é recusado', !validarCanalPublico('outro', str_repeat('x', 256)));

// ============================================================
secao('5. Dados da ONG');
// ============================================================
$ongValida = ['nome' => 'ONG Boa', 'email' => 'ong@exemplo.org', 'area' => 'Cultura', 'estado' => 'SP', 'canal_tipo' => 'site', 'canal_valor' => 'ong.org'];
igual('cadastro válido não tem erro', validarDadosOng($ongValida), null);
igual('e-mail inválido', validarDadosOng(['email' => 'ong@'] + $ongValida)[0] ?? '', 'email');
igual('área fora da lista', validarDadosOng(['area' => 'Política'] + $ongValida)[0] ?? '', 'area');
igual('UF fora da lista', validarDadosOng(['estado' => 'XX'] + $ongValida)[0] ?? '', 'estado');
igual('nome acima de 160 caracteres', validarDadosOng(['nome' => str_repeat('n', 161)] + $ongValida)[0] ?? '', 'nome');
igual('canal inválido', validarDadosOng(['canal_valor' => 'nada'] + $ongValida)[0] ?? '', 'canal_valor');

// ============================================================
secao('6. Senhas');
// ============================================================
$req = requisitosSenha('Horta2026verde', 'Horta2026verde', 'ong@exemplo.org', 8);
t('senha forte passa em todos os requisitos', senhaValida($req), json_encode($req));
t('senha comum é recusada mesmo com maiúsculas e símbolos', !requisitosSenha('Senha@123', 'Senha@123', 'a@b.com', 8)['nao_comum']);
t('senha de um caractere repetido é recusada', !requisitosSenha('11111111', '11111111', 'a@b.com', 8)['nao_comum']);
t('senha sem número é recusada', !requisitosSenha('somenteletras', 'somenteletras', 'a@b.com', 8)['letra_numero']);
t('senha curta é recusada', !requisitosSenha('Ab1', 'Ab1', 'a@b.com', 8)['minimo']);
t('senha com o e-mail é recusada', !requisitosSenha('xjoaosilvax1', 'xjoaosilvax1', 'joaosilva@ong.org', 8)['sem_email']);
t('parte do e-mail com menos de 3 letras não bloqueia', requisitosSenha('ana2026verde', 'ana2026verde', 'an@ong.org', 8)['sem_email']);
t('mais de 72 bytes é recusado (limite do bcrypt)', !requisitosSenha(str_repeat('ç', 37) . '1', str_repeat('ç', 37) . '1', 'a@b.com', 8)['maximo']);
t('confirmação diferente é recusada', !requisitosSenha('Horta2026verde', 'Horta2026Verde', 'a@b.com', 8)['iguais']);
igual('primeiro erro vem na ordem da lista', senhaPrimeiroErro(requisitosSenha('abc', 'x', 'a@b.com', 8), 8), 'A senha deve ter pelo menos 8 caracteres.');
t('equipe exige mínimo maior que ONG', SENHA_MINIMO_EQUIPE > SENHA_MINIMO_ONG);

// ============================================================
secao('7. Movimentações (movimentacaoDoPost)');
// ============================================================
$hoje = date('Y-m-d');
$movBase = ['tipo' => 'receita', 'descricao' => 'Doação', 'valor' => 'R$ 1.234,56', 'data_mov' => $hoje];
$mov = movimentacaoDoPost($movBase, [7]);
igual('movimentação válida não tem erro', $mov['erro'], '');
igual('valor convertido e arredondado', $mov['dados']['valor'] ?? null, 1234.56);
igual('sem categoria entra como "outra"', $mov['dados']['categoria'] ?? null, 'outra');
t('sem projeto fica sem vínculo', array_key_exists('projeto_id', $mov['dados']) && $mov['dados']['projeto_id'] === null);
$casos = [
    'valor que arredonda para zero' => ['valor' => '0,004'],
    'valor em notação científica'   => ['valor' => '1e5'],
    'valor negativo'                => ['valor' => '-10'],
    'valor zero'                    => ['valor' => '0,00'],
    'valor acima do limite'         => ['valor' => '10.000.000.000,00'],
    'tipo inválido'                 => ['tipo' => 'doacao'],
    'descrição só com espaços'      => ['descricao' => '   '],
    'descrição acima de 255'        => ['descricao' => str_repeat('d', 256)],
    'data futura'                   => ['data_mov' => date('Y-m-d', strtotime('+1 day'))],
    'data inexistente'              => ['data_mov' => '2026-02-30'],
    'data antes de 2000'            => ['data_mov' => '1999-12-31'],
    'categoria de despesa numa receita' => ['categoria' => 'alimentacao'],
    'projeto de outra ONG'          => ['projeto_id' => '999'],
];
foreach ($casos as $nome => $troca) {
    t("recusa: $nome", movimentacaoDoPost($troca + $movBase, [7])['erro'] !== '');
}
igual('categoria de receita válida é aceita', movimentacaoDoPost(['categoria' => array_key_first(categoriasMovimentacao('receita'))] + $movBase, [7])['erro'], '');
igual('projeto da própria ONG é aceito', movimentacaoDoPost(['projeto_id' => '7'] + $movBase, [7])['dados']['projeto_id'] ?? null, 7);
igual('limite máximo exato é aceito', movimentacaoDoPost(['valor' => '9.999.999.999,99'] + $movBase, [7])['erro'], '');

// ============================================================
secao('8. Projetos (projetoDoPost)');
// ============================================================
$projBase = ['nome' => 'Horta', 'status' => 'andamento', 'indicador' => '10', 'meta' => '100', 'unidade' => 'mudas', 'data_inicio' => '2025-03-01', 'data_fim' => ''];
igual('projeto válido não tem erro', projetoDoPost($projBase)['erro'], '');
foreach ([
    'término antes do início'        => ['data_fim' => '2025-01-01'],
    'concluído terminando no futuro' => ['status' => 'concluido', 'data_fim' => date('Y-m-d', strtotime('+10 days'))],
    'meta zero'                      => ['meta' => '0'],
    'indicador negativo'             => ['indicador' => '-1'],
    'indicador decimal'              => ['indicador' => '1.5'],
    'situação fora da lista'         => ['status' => 'cancelado'],
    'sem unidade'                    => ['unidade' => ''],
    'nome vazio'                     => ['nome' => ''],
] as $nome => $troca) {
    t("recusa: $nome", projetoDoPost($troca + $projBase)['erro'] !== '');
}
igual('progresso da meta em porcentagem', progressoProjeto(['indicador' => 25, 'meta' => 100]), 25);
igual('progresso passa da meta mas para em 100', progressoProjeto(['indicador' => 300, 'meta' => 100]), 100);
igual('sem meta não há progresso', progressoProjeto(['indicador' => 5, 'meta' => null]), null);

// ============================================================
secao('9. Balancete');
// ============================================================
$b = balanceteDoAno(['receita' => [1 => 100.0, 2 => 50.0], 'despesa' => [1 => 30.0, 3 => 40.0]]);
igual('total de receitas', $b['total_receita'], 150.0);
igual('total de despesas', $b['total_despesa'], 70.0);
igual('saldo do ano', $b['total_saldo'], 80.0);
igual('saldo acumulado mês a mês (março)', $b['linhas'][3]['acumulado'], 80.0);
t('mês sem lançamento fica marcado', $b['linhas'][4]['semMov'] && !$b['linhas'][1]['semMov']);
igual('acumulado final igual ao saldo do ano', $b['acumulado_final'], $b['total_saldo']);

// ============================================================
secao('10. Comprovantes');
// ============================================================
t('caminho com ../ é recusado', !comprovanteCaminhoValido('comprovantes/../config/app.php'));
t('extensão fora da lista é recusada', !comprovanteCaminhoValido('comprovantes/abc.php'));
t('arquivo vazio é recusado', !comprovanteCaminhoValido(''));

// ============================================================
secao('11. Notícias: HTML seguro');
// ============================================================
$ataques = [
    'script'                 => '<p>oi</p><script>alert(1)</script>',
    'img com onerror'        => '<p><img src=x onerror="alert(1)">oi</p>',
    'link javascript:'       => '<p><a href="javascript:alert(1)">clique</a></p>',
    'link javascript com entidade' => '<p><a href="java&#115;cript:alert(1)">clique</a></p>',
    'link data:'             => '<p><a href="data:text/html;base64,PHNjcmlwdD4=">x</a></p>',
    'svg com onload'         => '<svg onload="alert(1)"><circle/></svg><p>x</p>',
    'iframe'                 => '<iframe src="https://evil.example"></iframe><p>x</p>',
    'style inline'           => '<p style="background:url(javascript:alert(1))">x</p>',
    'atributo on* em tag permitida' => '<strong onclick="alert(1)">x</strong>',
    'script dentro de tag desconhecida' => '<div><span><script>alert(1)</script></span></div>',
    'form e input'           => '<form action="https://evil.example"><input name="senha"></form>',
    'math'                   => '<math><mtext><script>alert(1)</script></mtext></math>',
    'comentário condicional' => '<!--[if IE]><script>alert(1)</script><![endif]--><p>x</p>',
    'meta refresh'           => '<meta http-equiv="refresh" content="0;url=https://evil.example"><p>x</p>',
    'object e embed'         => '<object data="x.swf"></object><embed src="x.swf"><p>x</p>',
];
foreach ($ataques as $nome => $html) {
    $limpo = strtolower(sanitizarHtmlNoticia($html));
    $perigoso = preg_match('~<(script|iframe|svg|math|object|embed|form|input|meta|style|img)\b|\son[a-z]+\s*=|javascript:|data:text|style=~', $limpo);
    t("XSS removido: $nome", $perigoso === 0, $limpo);
}
igual('tags permitidas passam intactas', sanitizarHtmlNoticia('<p>Olá <strong>forte</strong> e <em>itálico</em></p>'), '<p>Olá <strong>forte</strong> e <em>itálico</em></p>');
igual('<b> e <i> viram <strong> e <em>', sanitizarHtmlNoticia('<p><b>a</b><i>b</i></p>'), '<p><strong>a</strong><em>b</em></p>');
$link = sanitizarHtmlNoticia('<p><a href="exemplo.org" class="x" title="t">site</a></p>');
t('link válido ganha https, nova aba e rel seguro, sem outros atributos', str_contains($link, 'href="https://exemplo.org"')
    && str_contains($link, 'rel="noopener noreferrer nofollow"') && str_contains($link, 'target="_blank"') && !str_contains($link, 'class='), $link);
igual('texto sem tags vira parágrafos escapados', sanitizarHtmlNoticia("linha <1>\n\nsegundo & fim"), '<p>linha &lt;1&gt;</p><p>segundo &amp; fim</p>');
igual('parágrafos vazios do editor somem', sanitizarHtmlNoticia('<p>a</p><p><br></p><p> </p>'), '<p>a</p>');
igual('HTML vazio continua vazio', sanitizarHtmlNoticia('   '), '');

// ============================================================
secao('12. Notícias: links, slug e PIX');
// ============================================================
$l = noticiaNormalizarLink('exemplo.org/pagina');
t('link sem esquema ganha https', $l['ok'] && $l['url'] === 'https://exemplo.org/pagina');
igual('www. sai do domínio exibido', noticiaNormalizarLink('https://www.exemplo.org')['dominio'] ?? '', 'exemplo.org');
foreach ([
    'javascript:'          => 'javascript:alert(1)',
    'ftp'                  => 'ftp://exemplo.org/arquivo',
    'usuário e senha'      => 'https://user:senha@exemplo.org',
    'começa com //'        => '//exemplo.org',
    'sem ponto no domínio' => 'https://localhost/admin',
    'espaço no meio'       => 'https://exem plo.org',
    'acima de 500 caracteres' => 'https://exemplo.org/' . str_repeat('a', 500),
    'caractere de controle' => "https://exemplo.org/\x07",
] as $nome => $url) {
    t("link recusado: $nome", !noticiaNormalizarLink($url)['ok']);
}
igual('slug sem acento, minúsculo e com hífens', noticiaSlugBase('Olá, Mundo! Ação de 2026'), 'ola-mundo-acao-de-2026');
igual('slug de título só com símbolos', noticiaSlugBase('!!!'), 'noticia');
t('slug tem no máximo 100 caracteres', mb_strlen(noticiaSlugBase(str_repeat('palavra ', 40))) <= 100);
t('chave PIX perto de pedido de doação é detectada', noticiaTextoTemChavePixPertoDeDoacao('Faça sua doação pelo PIX 12.345.678/0001-95, obrigado!'));
t('texto comum de prestação de contas não é bloqueado', !noticiaTextoTemChavePixPertoDeDoacao('Prestação de contas de março: compramos 40 cestas básicas.'));
t('tempo de leitura nunca é zero', noticiaTempoLeitura('curto') >= 1);
igual('contagem de palavras', noticiaContarPalavras('um dois  três'), 3);

// ============================================================
secao('13. Links das notificações');
// ============================================================
foreach (['pages/gestao.php', 'index.php', 'pages/perfil-privado.php?secao=verificacao-e-pix', 'pages/noticia.php?id=5#topo'] as $ok) {
    t("link interno aceito: $ok", notificacaoLinkValido($ok));
}
foreach ([
    'https://evil.example', '//evil.example', 'javascript:alert(1)', 'pages/../config/app.php',
    'pages/x.php?a=<b>', '/pages/gestao.php', 'admin/index.php', "pages/gestao.php\r\nSet-Cookie: x=1",
    'pages/Gestao.PHP', 'uploads/comprovantes/x.php',
] as $ruim) {
    t('link recusado: ' . addcslashes($ruim, "\r\n"), !notificacaoLinkValido($ruim));
}
foreach (avisoDestinosLink() as $destino => $rotulo) {
    t("destino de aviso é link interno válido: " . ($destino ?: '(sem link)'), $destino === '' || notificacaoLinkValido($destino));
}

// ============================================================
secao('14. Proxies confiáveis (HTTPS atrás de proxy)');
// ============================================================
t('IP exato na lista', ipEmListaConfiavel('10.0.0.5', '10.0.0.5'));
t('IP dentro da faixa /8', ipEmListaConfiavel('10.20.30.40', '10.0.0.0/8'));
t('IP fora da faixa', !ipEmListaConfiavel('11.0.0.1', '10.0.0.0/8'));
t('faixa /25 corta no bit certo', ipEmListaConfiavel('192.168.1.100', '192.168.1.0/25') && !ipEmListaConfiavel('192.168.1.200', '192.168.1.0/25'));
t('IPv6 dentro da faixa', ipEmListaConfiavel('2001:db8::1', '2001:db8::/32'));
t('IPv4 contra faixa IPv6 não confere', !ipEmListaConfiavel('10.0.0.1', '2001:db8::/32'));
t('máscara inválida é ignorada', !ipEmListaConfiavel('10.0.0.1', '10.0.0.0/33'));
t('lista vazia não confia em ninguém', !ipEmListaConfiavel('10.0.0.1', ''));
t('vários itens separados por vírgula', ipEmListaConfiavel('172.16.0.9', '10.0.0.0/8, 172.16.0.0/12'));

// ============================================================
secao('15. Configuração coerente');
// ============================================================
$cores = coresArea();
foreach (ONGHELP_AREAS as $area) {
    t("área $area tem cor própria", isset($cores[$area]));
    t("área $area tem ícone em assets/img/areas", arquivoImagemDisponivel(ONGHELP_ROOT . '/assets/img/areas', areaSlug($area)) !== null);
}
foreach (['receita', 'despesa'] as $tipo) {
    t("categorias de $tipo têm a opção \"outra\"", isset(categoriasMovimentacao($tipo)['outra']));
}
t('nenhuma categoria passa de 30 caracteres (coluna do banco)', !array_filter(
    array_merge(array_keys(categoriasMovimentacao('receita')), array_keys(categoriasMovimentacao('despesa'))),
    static fn (string $c): bool => strlen($c) > 30
));
foreach (array_keys(ONGHELP_CORES_BANNER) as $cor) {
    t("cor do banner $cor é um hexadecimal de 6 dígitos", preg_match('/^#[0-9A-F]{6}$/', $cor) === 1);
}
t('a paleta do banner tem 16 cores', count(ONGHELP_CORES_BANNER) === 16);
t('situações de projeto batem com o ENUM do banco', array_keys(ONGHELP_STATUS_PROJETO) === ['planejado', 'andamento', 'concluido']);
t('cada WhatsApp da equipe gera link wa.me', !array_filter(whatsappsEquipe(), static fn (array $c): bool => !str_starts_with((string) ($c['url'] ?? ''), 'https://wa.me/')));

// ============================================================
secao('16. Listagens e endereços');
// ============================================================
$_GET = ['busca' => 'horta', 'uf' => '', 'pagina' => '3'];
igual('urlListagem mantém filtros, tira vazios e leva à lista', urlListagem('ongs.php', ['busca', 'uf']), 'ongs.php?busca=horta#lista');
igual('urlListagem troca de página', urlListagem('ongs.php', ['busca'], ['pagina' => 2]), 'ongs.php?busca=horta&pagina=2#lista');
igual('urlListagem omite o valor padrão', urlListagem('ongs.php', ['ordem'], ['ordem' => 'nome'], ['ordem' => 'nome']), 'ongs.php#lista');
$_GET = [];

placarFinal();
