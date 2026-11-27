<?php
// ============================================================
// pages/termos.php — Termos de Uso do ONGHelp
// ============================================================
// o layout fica em includes/components/pagina-legal.php; aqui
// está só o conteúdo, os limites citados vêm de config/app.php

require_once __DIR__ . '/../includes/bootstrap.php';

$secoes = [];
$secao = function (string $id, string $titulo, string $html) use (&$secoes): void {
    $secoes[] = ['id' => $id, 'titulo' => $titulo, 'html' => $html];
};

$email = ONGHELP_EMAIL_EQUIPE;
$publicacoesDia = ONGHELP_NOTICIAS_PUBLICACOES_DIA;

$secao('servico', 'O que é o ONGHelp', <<<HTML
<p>O ONGHelp é uma plataforma gratuita de gestão e transparência para
organizações da sociedade civil. Ele permite que cada ONG publique um perfil,
registre receitas, despesas e projetos, gere relatórios em PDF e CSV, publique
notícias e divulgue um canal de doação via PIX.</p>
<p>O ONGHelp é mantido pela Equipe ONGHelp como um projeto acadêmico de
Engenharia de Software. Ao usar o site, você concorda com estes termos e com a
<a href="privacidade.php">Política de Privacidade</a>.</p>
HTML);

$secao('quem-usa', 'Quem pode usar', <<<HTML
<ul>
  <li><strong>Visitantes</strong> podem consultar perfis, o Portal de
  Transparência e as notícias sem criar conta.</li>
  <li><strong>Organizações</strong> da sociedade civil, ou seja, associações e
  fundações privadas sem fins lucrativos (Código Civil, arts. 44, 53 e 62, e
  Lei 13.019/2014, art. 2º, I), podem se cadastrar com CNPJ ativo, por meio
  de uma pessoa com poderes para representá-las (o responsável legal ou alguém
  autorizado por ele).</li>
  <li><strong>Equipe ONGHelp</strong>: administrador e moderadores com contas
  individuais, que analisam cadastros e moderam conteúdo.</li>
</ul>
<p>Contas de ONG não podem ser criadas por menores de 18 anos, idade em que
a pessoa passa a responder sozinha pelos atos da vida civil (Código Civil,
art. 5º).</p>
HTML);

$secao('cadastro', 'Cadastro e verificação', <<<HTML
<p>Todo cadastro começa como <strong>aguardando análise</strong> e só aparece
publicamente depois de aprovado manualmente pela equipe. A análise confere o
CNPJ, a coerência dos dados, o canal público informado e a declaração do
responsável. O cadastro pode ser <strong>recusado</strong> (com motivo) ou,
depois de aprovado, <strong>suspenso</strong> se surgirem indícios de
informação falsa, uso indevido do nome de outra organização ou violação destes
termos.</p>
<div class="lg-aviso">
  <i class="bi bi-patch-check" aria-hidden="true"></i>
  <p>O selo <strong>“Verificada em dd/mm/aaaa”</strong> indica conferência
  cadastral naquela data. Ele <strong>não é auditoria financeira</strong> e não
  garante que os valores informados pela ONG estejam corretos.</p>
</div>
<p>Prestar informação falsa no cadastro, como CNPJ, responsável ou
autorização que não correspondem à realidade, leva à recusa ou à suspensão e
pode configurar crime de falsidade ideológica (Código Penal, art. 299).</p>
HTML);

$secao('responsabilidades', 'Responsabilidades da organização', <<<HTML
<p>A organização se compromete a:</p>
<ul>
  <li>informar dados verdadeiros e mantê-los atualizados;</li>
  <li>registrar movimentações e projetos de boa-fé. Esses dados são
  <strong>autodeclarados</strong>: o ONGHelp garante que fiquem públicos e
  rastreáveis, mas não os audita;</li>
  <li>anexar só comprovantes que possam ser públicos, cobrindo antes os dados
  pessoais de doadores, funcionários e beneficiários;</li>
  <li>manter a senha em sigilo e avisar a equipe se suspeitar de acesso
  indevido;</li>
  <li>usar a plataforma apenas para atividades lícitas e ligadas à sua
  finalidade social.</li>
</ul>
HTML);

$secao('pix', 'Doações via PIX', <<<HTML
<p><strong>O ONGHelp não recebe, guarda nem repassa dinheiro.</strong> A
doação é feita pelo aplicativo do banco de quem doa, diretamente para a conta
da organização. Por isso o ONGHelp não emite recibo, não faz estornos e não
responde pelo destino dos valores doados.</p>
<ul>
  <li>A chave PIX de cada ONG é <strong>obrigatoriamente o próprio CNPJ</strong>,
  conferido na verificação.</li>
  <li>Depois da aprovação, a ONG não pode alterar a chave sozinha; correções
  passam pela equipe e ficam registradas.</li>
  <li>O QR Code exibido é sempre gerado pelo site a partir dessa chave
  verificada; não é aceito envio de imagem de QR Code.</li>
</ul>
<p>Antes de doar, confira no app do banco se o nome do recebedor corresponde à
organização.</p>
HTML);

$secao('noticias', 'Notícias publicadas pelas ONGs', <<<HTML
<p>Cada organização é a única responsável pelo que publica: títulos, textos,
fotos, legendas, links e vínculos com projetos ou movimentações. Não há revisão
prévia; a moderação acontece depois, por análise da equipe ou por denúncia.</p>
<ul>
  <li><strong>Direito de imagem:</strong> ao publicar fotos, a ONG declara ter
  autorização das pessoas retratadas (Constituição Federal, art. 5º, X, e
  Código Civil, art. 20). Imagens de crianças e adolescentes exigem
  autorização dos responsáveis e não podem expor dados que os identifiquem
  (Estatuto da Criança e do Adolescente, Lei 8.069/1990, arts. 17 e 18).</li>
  <li><strong>Direito autoral:</strong> textos e fotos de terceiros só podem
  ser publicados com autorização do autor ou dentro dos limites da Lei de
  Direitos Autorais (Lei 9.610/1998).</li>
  <li><strong>Sem pedidos de pagamento:</strong> notícias não podem conter chave
  PIX, dados bancários, boletos, links de pagamento ou encurtadores de link. O
  canal oficial de doação é o botão “Doar via PIX” do perfil.</li>
  <li><strong>Limite de publicação:</strong> até {$publicacoesDia} notícias
  publicadas por dia por organização.</li>
</ul>
HTML);

$secao('condutas', 'Condutas proibidas', <<<HTML
<p>Não é permitido usar o ONGHelp para:</p>
<ul>
  <li>se passar por outra organização ou pessoa;</li>
  <li>publicar conteúdo falso, enganoso, discriminatório, violento, ofensivo ou
  que viole direitos de terceiros;</li>
  <li>arrecadar dinheiro fora do canal PIX verificado da própria ONG;</li>
  <li>tentar burlar a segurança, acessar contas de terceiros ou sobrecarregar o
  site com acessos automatizados;</li>
  <li>coletar dados das organizações para envio de propaganda não solicitada.</li>
</ul>
HTML);

$secao('moderacao', 'Moderação e denúncias', <<<HTML
<p>Qualquer pessoa pode denunciar uma notícia pelo botão de denúncia da própria
página. A equipe analisa cada caso e pode:</p>
<ul>
  <li>manter o conteúdo, se não houver violação;</li>
  <li><strong>ocultar</strong> a notícia, informando o motivo à ONG;</li>
  <li><strong>bloquear novas publicações</strong> da organização até que a
  situação seja revista e o bloqueio, liberado pela equipe;</li>
  <li><strong>suspender</strong> o perfil em casos graves ou repetidos.</li>
</ul>
<p>Toda decisão fica registrada com data, autor e motivo. A organização pode
contestar pelo e-mail <a href="mailto:{$email}">{$email}</a> ou pela página
<a href="contato.php">Fale conosco</a>.</p>
<p>O ONGHelp é um provedor de aplicação: não é o autor do que as organizações
publicam. A moderação acontece por iniciativa própria e por denúncia, e o
ONGHelp também cumpre ordens judiciais de remoção de conteúdo (Marco Civil da
Internet, art. 19). Imagens íntimas divulgadas sem autorização são removidas
depois da notificação da pessoa afetada (art. 21).</p>
HTML);

$secao('comunicacoes', 'Avisos e comunicações', <<<HTML
<p>A equipe fala com a organização pelo <strong>sino de notificações</strong>
do painel. Alguns avisos saem automaticamente, como aprovação, suspensão,
reativação e decisões de moderação, e outros são escritos pelo administrador,
para uma ONG ou para todas, como novidades da plataforma e pedidos de
atualização de dados.</p>
<ul>
  <li>Os avisos só levam a páginas do próprio ONGHelp, nunca a sites de fora.</li>
  <li>O ONGHelp não envia propaganda e <strong>nunca pede a sua senha</strong>,
  nem por aviso, nem por e-mail, nem por WhatsApp.</li>
  <li>Os canais oficiais de contato estão na página
  <a href="contato.php">Fale conosco</a>.</li>
</ul>
HTML);

$secao('encerramento', 'Encerramento da conta', <<<HTML
<p>A organização pode excluir a conta a qualquer momento em
<em>Meu perfil → Conta</em>. A exclusão é <strong>permanente</strong>: remove
perfil, movimentações, projetos, notícias e fotos, e não pode ser desfeita.
Recomendamos baixar o relatório em PDF ou CSV antes.</p>
<p>A equipe pode suspender ou encerrar contas que violem estes termos, com
registro do motivo.</p>
HTML);

$secao('responsabilidade', 'Limites de responsabilidade', <<<HTML
<p>O ONGHelp é oferecido gratuitamente e “no estado em que se encontra”.
Trabalhamos para manter o site disponível e seguro, mas não garantimos
funcionamento ininterrupto. O ONGHelp não responde por informações declaradas
pelas organizações, por doações feitas a elas nem por conteúdo de sites
externos indicados em perfis e notícias.</p>
HTML);

$secao('propriedade', 'Conteúdo e propriedade intelectual', <<<HTML
<p>Textos, fotos e dados enviados continuam pertencendo à organização. Ao
publicá-los, ela autoriza o ONGHelp a exibi-los no site enquanto a conta
existir. O nome, a marca e o código do ONGHelp pertencem à Equipe ONGHelp. O site
usa bibliotecas e fontes de código aberto (Bootstrap, Bootstrap Icons,
qrcodejs, Poppins e Inter), cada uma sob sua própria licença.</p>
HTML);

$secao('alteracoes', 'Alterações, legislação e foro', <<<HTML
<p>Estes termos podem ser atualizados conforme a plataforma evolui. A data no
topo da página indica a versão em vigor, e mudanças importantes são avisadas
às organizações pelo sino de notificações do painel.</p>
<p>Estes termos seguem a legislação brasileira, em especial:</p>
<ul>
  <li>Marco Civil da Internet (Lei 12.965/2014) e seu regulamento
  (Decreto 8.771/2016);</li>
  <li>Lei Geral de Proteção de Dados (Lei 13.709/2018);</li>
  <li>Código Civil (Lei 10.406/2002) e Marco Regulatório das Organizações da
  Sociedade Civil (Lei 13.019/2014);</li>
  <li>Estatuto da Criança e do Adolescente (Lei 8.069/1990) e Lei de Direitos
  Autorais (Lei 9.610/1998).</li>
</ul>
<p>Antes de qualquer medida judicial, procure a equipe pelo
<a href="contato.php">Fale conosco</a>: a maior parte das situações se resolve
por conversa. Não havendo acordo, fica eleito o foro da comarca de
Taquaritinga/SP, sem prejuízo do direito de quem for consumidor de propor a
ação no próprio domicílio (Código de Defesa do Consumidor, art. 101, I).</p>
HTML);

$legal = [
    'badge'      => 'Termos de Uso',
    'titulo'     => 'Termos de Uso',
    'destaque'   => 'do ONGHelp',
    'subtitulo'  => 'As regras para usar a plataforma: o que o ONGHelp oferece, o que cada organização assume e como funcionam verificação, doações e moderação.',
    'atualizado' => '15 de novembro de 2026',
    'outro'      => ['url' => 'privacidade.php', 'rotulo' => 'Ler a Política de Privacidade'],
    'resumo'     => [
        ['icone' => 'bi-gift', 'titulo' => 'Gratuito e sem intermediar dinheiro', 'texto' => 'A doação vai direto do banco de quem doa para a ONG. O ONGHelp não toca nos valores.'],
        ['icone' => 'bi-patch-check', 'titulo' => 'Verificação manual', 'texto' => 'Todo cadastro é conferido pela equipe antes de aparecer. O selo não é auditoria financeira.'],
        ['icone' => 'bi-pencil-square', 'titulo' => 'A ONG responde pelo que publica', 'texto' => 'Dados financeiros, notícias e fotos são de responsabilidade da própria organização.'],
        ['icone' => 'bi-flag', 'titulo' => 'Moderação transparente', 'texto' => 'Conteúdo irregular pode ser ocultado, sempre com motivo registrado e direito de contestação.'],
    ],
    'secoes'     => $secoes,
];

$page_title = 'Termos de Uso';
$page_description = 'Termos de Uso do ONGHelp: cadastro e verificação de ONGs, doações via PIX, notícias, moderação, avisos, responsabilidades e legislação aplicável.';
$active_nav = '';
include __DIR__ . '/../includes/layout/header.php';
include __DIR__ . '/../includes/components/pagina-legal.php';
include __DIR__ . '/../includes/layout/footer.php';
