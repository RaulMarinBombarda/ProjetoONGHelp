<?php
// ============================================================
// pages/privacidade.php — Política de Privacidade do ONGHelp
// ============================================================
// o layout fica em includes/components/pagina-legal.php; aqui
// está só o conteúdo, tudo o que esta página afirma corresponde
// ao que o código faz (dados coletados, cookies, prazos e exclusão)

require_once __DIR__ . '/../includes/bootstrap.php';

$secoes = [];
$secao = function (string $id, string $titulo, string $html) use (&$secoes): void {
    $secoes[] = ['id' => $id, 'titulo' => $titulo, 'html' => $html];
};

$email = ONGHELP_EMAIL_EQUIPE;
$minutosSessao = intdiv(ONG_SESSAO_INATIVIDADE, 60);
$horasSessao = intdiv(ONG_SESSAO_ABSOLUTA, 3600);

$secao('controlador', 'Quem cuida dos seus dados', <<<HTML
<p>A Equipe ONGHelp é a <strong>controladora</strong> dos dados tratados nesta
plataforma, isto é, quem decide como e para que eles são usados (Lei Geral de
Proteção de Dados, Lei 13.709/2018, art. 5º, VI). A própria equipe também atua
como <strong>encarregada</strong> pelo tratamento (art. 41): é o canal entre
você, o ONGHelp e a Autoridade Nacional de Proteção de Dados (ANPD).</p>
<p>Para qualquer assunto sobre privacidade, escreva para
<a href="mailto:{$email}">{$email}</a> ou use a página
<a href="contato.php">Fale conosco</a>.</p>
<p>Nesta política, <em>dado pessoal</em> é qualquer informação que identifique
uma pessoa ou permita identificá-la, e <em>tratamento</em> é tudo o que se faz
com ela: coletar, guardar, exibir, corrigir ou apagar (art. 5º, I e X). Dados
apenas da organização, como CNPJ e movimentações financeiras, não são dados
pessoais, mas recebem o mesmo cuidado.</p>
HTML);

$secao('dados', 'Quais dados coletamos', <<<HTML
<div class="lg-tabela">
  <table>
    <thead><tr><th scope="col">De quem</th><th scope="col">Dados</th></tr></thead>
    <tbody>
      <tr>
        <th scope="row">Organizações</th>
        <td>Nome, CNPJ, e-mail de acesso, senha (guardada só como hash), área de
        atuação, cidade e estado, descrição, chave PIX (o próprio CNPJ), canal
        público, foto de perfil, cor do banner, nome e cargo do responsável
        legal e a declaração de autorização.</td>
      </tr>
      <tr>
        <th scope="row">Conteúdo das ONGs</th>
        <td>Movimentações financeiras com categoria e comprovante opcional,
        projetos com situação, período, meta e indicadores, notícias com textos,
        fotos, legendas e links, e os avisos da equipe no painel da ONG.</td>
      </tr>
      <tr>
        <th scope="row">Visitantes</th>
        <td>Nenhum cadastro é exigido. Ao <strong>denunciar</strong> uma notícia,
        guardamos o motivo, o detalhe escrito, o e-mail (se você informar) e uma
        impressão técnica gerada a partir do IP e do navegador, usada só para
        evitar denúncias repetidas. O IP em si não é armazenado.</td>
      </tr>
      <tr>
        <th scope="row">Equipe ONGHelp</th>
        <td>Nome, e-mail, papel, último acesso e o registro das ações no painel,
        com data e endereço IP.</td>
      </tr>
      <tr>
        <th scope="row">Segurança do login</th>
        <td>E-mail informado, IP e resultado de cada tentativa de login, usados
        para bloquear tentativas repetidas.</td>
      </tr>
    </tbody>
  </table>
</div>
HTML);

$secao('finalidades', 'Para que usamos e com qual base legal', <<<HTML
<ul>
  <li><strong>Prestar o serviço</strong> (perfil, relatórios, notícias,
  divulgação do PIX): execução do serviço solicitado pela organização
  (LGPD, art. 7º, V).</li>
  <li><strong>Verificar cadastros, moderar conteúdo e proteger contas</strong>:
  legítimo interesse em manter a plataforma confiável e segura (art. 7º, IX).</li>
  <li><strong>Atender denúncias e pedidos de autoridades</strong>: cumprimento
  de obrigação legal, quando aplicável (art. 7º, II).</li>
  <li><strong>Guardar o histórico de decisões da equipe</strong>: exercício
  regular de direitos, caso uma decisão seja contestada (art. 7º, VI).</li>
  <li><strong>Avisar a ONG pelo painel</strong> (aprovação, moderação e avisos
  escritos pela equipe): execução do serviço (art. 7º, V).</li>
</ul>
<p>Os dados são usados só para essas finalidades, de forma adequada e limitada
ao necessário (art. 6º, I a III). Nenhuma decisão sobre o seu cadastro é
tomada de forma automatizada: toda análise é feita por uma pessoa da equipe,
com o motivo registrado (art. 20).</p>
<p>Não usamos dados para publicidade, não traçamos perfis de comportamento e
<strong>não vendemos nem alugamos dados</strong> a ninguém.</p>
HTML);

$secao('publico', 'O que fica público', <<<HTML
<p>O ONGHelp existe para dar transparência. Depois da aprovação, ficam visíveis
para qualquer pessoa:</p>
<ul>
  <li>nome, CNPJ, área, cidade/UF, descrição, foto e cor do banner do perfil;</li>
  <li>e-mail de contato e canal público informados pela ONG;</li>
  <li>chave PIX (CNPJ) e QR Code de doação;</li>
  <li>movimentações (com os comprovantes anexados), projetos, balancetes e
  notícias publicadas.</li>
</ul>
<div class="lg-aviso">
  <i class="bi bi-eye-slash" aria-hidden="true"></i>
  <p><strong>Nunca são públicos:</strong> a senha, o nome e o cargo do
  responsável legal, notícias em rascunho ou ocultadas, denúncias e os
  registros de segurança.</p>
</div>
HTML);

$secao('sensiveis', 'Dados sensíveis', <<<HTML
<p>O ONGHelp <strong>não pede dados sensíveis</strong>, como origem racial ou
étnica, religião, opinião política, filiação sindical, saúde, vida sexual,
dados genéticos ou biométricos (LGPD, art. 5º, II, e art. 11).</p>
<p>Muitas organizações atendem pessoas em situação de vulnerabilidade. Por isso
a ONG não deve publicar, em notícias, projetos ou comprovantes, informações
que revelem dados sensíveis de beneficiários. Conteúdo assim pode ser ocultado
pela moderação.</p>
HTML);

$secao('compartilhamento', 'Com quem compartilhamos', <<<HTML
<ul>
  <li><strong>Com ninguém para fins comerciais.</strong> Não há parceiros,
  anunciantes nem venda de bases de dados.</li>
  <li><strong>Com o público</strong>, apenas o que está em "O que fica público",
  porque essa é a finalidade da plataforma.</li>
  <li><strong>Com autoridades</strong>, só quando a lei obrigar: registros e
  dados pessoais são fornecidos mediante ordem judicial ou requisição de
  autoridade competente (Marco Civil da Internet, Lei 12.965/2014, arts. 10 e
  22).</li>
  <li><strong>Com quem hospeda o site</strong>, se ele for publicado em um
  provedor: a empresa de hospedagem atua como operadora, tratando os dados só
  conforme as instruções do ONGHelp (LGPD, art. 39).</li>
</ul>
<p>Os botões que abrem o WhatsApp ou o Gmail levam você para fora do ONGHelp.
A partir daí vale a política de privacidade desses serviços.</p>
HTML);

$secao('cookies', 'Cookies e serviços de terceiros', <<<HTML
<p>Usamos um único cookie, <code>PHPSESSID</code>, essencial para manter você
conectado. Ele é apagado ao fechar o navegador, não pode ser lido por scripts
(<em>HttpOnly</em>) e não é enviado a outros sites (<em>SameSite</em>).</p>
<p>Não há ferramentas de análise, anúncios nem rastreadores. Fontes, ícones e
bibliotecas são servidos pelo próprio ONGHelp: ao navegar aqui, seu endereço IP
não é repassado a serviços externos.</p>
<p>Ao doar, o pagamento acontece no aplicativo do seu banco, fora do ONGHelp,
e segue a política de privacidade da sua instituição financeira.</p>
HTML);

$secao('fotos', 'Fotos e imagens', <<<HTML
<p>Fotos enviadas passam por tratamento antes de serem publicadas: os
metadados, como localização GPS e modelo do aparelho, são removidos (o mesmo
vale para comprovantes enviados como foto). A ONG
declara ter autorização das pessoas retratadas, e fotos de crianças e
adolescentes exigem consentimento dos responsáveis, sem exposição de dados que
permitam identificá-los.</p>
<p>Se você aparece em uma foto e não autorizou o uso, escreva para
<a href="mailto:{$email}">{$email}</a> ou use a denúncia da própria notícia.</p>
HTML);

$secao('retencao', 'Por quanto tempo guardamos', <<<HTML
<ul>
  <li><strong>Dados da organização:</strong> enquanto a conta existir. Ao
  excluir a conta, perfil, movimentações, projetos, notícias, avisos e todos
  os arquivos de foto e de comprovante são apagados de forma permanente.</li>
  <li><strong>Tentativas de login:</strong> apagadas automaticamente após
  24 horas.</li>
  <li><strong>Denúncias e registros de moderação:</strong> pelo tempo
  necessário para analisar o caso e manter o histórico das decisões.</li>
  <li><strong>Registros de ações da equipe:</strong> mantidos para segurança e
  prestação de contas.</li>
  <li><strong>Avisos no painel da ONG:</strong> enquanto a conta existir, e
  apagados junto com ela.</li>
</ul>
<p>Terminada a finalidade, os dados são eliminados. Só podem ser guardados
depois disso nos casos que a LGPD permite, como cumprimento de obrigação legal
(arts. 15 e 16).</p>
HTML);

$secao('seguranca', 'Como protegemos os dados', <<<HTML
<ul>
  <li>senhas guardadas apenas como hash, nunca em texto puro;</li>
  <li>proteção contra envio de formulários por outros sites (CSRF) e limite de
  tentativas de login;</li>
  <li>sessão encerrada após {$minutosSessao} minutos sem uso ou {$horasSessao}
  horas no total, com renovação periódica do identificador;</li>
  <li>cabeçalhos de segurança e política de conteúdo que só aceita arquivos do
  próprio site;</li>
  <li>pastas de envio que não executam programas e painel da equipe com
  permissões por papel e histórico de ações.</li>
</ul>
<p>Essas medidas seguem o dever de segurança da LGPD (art. 46) e os padrões
de segurança para guarda de dados do Decreto 8.771/2016, que regulamenta o
Marco Civil da Internet.</p>
<p>Nenhum sistema é infalível. Se identificarmos um incidente que possa trazer
risco ou dano relevante aos titulares, comunicaremos os afetados e a ANPD,
informando quais dados foram atingidos, os riscos e as medidas tomadas
(art. 48).</p>
HTML);

$secao('direitos', 'Seus direitos', <<<HTML
<p>Pela LGPD (art. 18), você pode pedir a qualquer momento, sem custo:</p>
<ul>
  <li>confirmação de que tratamos dados seus e acesso a eles (I e II);</li>
  <li>correção de dados incompletos, inexatos ou desatualizados (III);</li>
  <li>anonimização, bloqueio ou eliminação de dados desnecessários, excessivos
  ou tratados em desacordo com a lei (IV);</li>
  <li>portabilidade (V): a ONG pode baixar seus relatórios em PDF e CSV no
  painel;</li>
  <li>eliminação dos dados (VI), inclusive pela própria conta em
  <em>Meu perfil → Conta</em>;</li>
  <li>informações sobre com quem os dados foram compartilhados (VII);</li>
  <li>informações sobre a possibilidade de não dar consentimento e as
  consequências disso, e revogação do consentimento, quando ele for a base
  do tratamento (VIII e IX).</li>
</ul>
<p>Envie o pedido para <a href="mailto:{$email}">{$email}</a>, a partir do
e-mail cadastrado, para confirmarmos que é você. A confirmação e o acesso em
formato simplificado são imediatos, e a declaração completa sai em até
15 dias (art. 19). Se não for atendido, você pode peticionar à ANPD
(art. 18, § 1º).</p>
HTML);

$secao('criancas', 'Crianças e adolescentes', <<<HTML
<p>O ONGHelp não é destinado a menores de 18 anos para criação de conta. Muitas
organizações atendem crianças: por isso exigimos autorização dos responsáveis
para qualquer imagem publicada e removemos conteúdo que exponha dados de
menores assim que tomamos conhecimento.</p>
<p>Dados de crianças e adolescentes só podem ser tratados no seu melhor
interesse e, no caso de crianças, com consentimento de um dos pais ou
responsável (LGPD, art. 14). A imagem e a identidade deles são protegidas pelo
Estatuto da Criança e do Adolescente (Lei 8.069/1990, art. 17).</p>
HTML);

$secao('alteracoes', 'Alterações nesta política', <<<HTML
<p>Esta política pode mudar quando o ONGHelp ganhar novos recursos. A data no
topo da página indica a versão em vigor, e mudanças importantes são avisadas
às ONGs pelo sino de notificações do painel. Leia também os
<a href="termos.php">Termos de Uso</a>.</p>
<p>Esta política segue a legislação brasileira, em especial a Lei Geral de
Proteção de Dados (Lei 13.709/2018), o Marco Civil da Internet
(Lei 12.965/2014) e seu regulamento (Decreto 8.771/2016) e o Estatuto da
Criança e do Adolescente (Lei 8.069/1990).</p>
HTML);

$legal = [
    'badge'      => 'Privacidade e LGPD',
    'titulo'     => 'Política de',
    'destaque'   => 'Privacidade',
    'subtitulo'  => 'Quais dados o ONGHelp coleta, o que fica público, por quanto tempo guardamos e como exercer seus direitos pela LGPD.',
    'atualizado' => '15 de novembro de 2026',
    'outro'      => ['url' => 'termos.php', 'rotulo' => 'Ler os Termos de Uso'],
    'resumo'     => [
        ['icone' => 'bi-database-lock', 'titulo' => 'Só o necessário', 'texto' => 'Coletamos o que o serviço precisa. Visitantes não precisam de cadastro.'],
        ['icone' => 'bi-cookie', 'titulo' => 'Um cookie, sem rastreio', 'texto' => 'Só o cookie de sessão. Sem analytics, anúncios ou serviços de terceiros.'],
        ['icone' => 'bi-shop', 'titulo' => 'Nunca vendemos dados', 'texto' => 'Nada é vendido ou repassado. O que é público serve à transparência das ONGs.'],
        ['icone' => 'bi-trash3', 'titulo' => 'Exclusão de verdade', 'texto' => 'Ao excluir a conta, dados e fotos são apagados de forma permanente.'],
    ],
    'secoes'     => $secoes,
];

$page_title = 'Política de Privacidade';
$page_description = 'Política de Privacidade do ONGHelp: dados coletados, bases legais, compartilhamento, cookies, retenção, segurança e direitos pela LGPD.';
$active_nav = '';
include __DIR__ . '/../includes/layout/header.php';
include __DIR__ . '/../includes/components/pagina-legal.php';
include __DIR__ . '/../includes/layout/footer.php';
