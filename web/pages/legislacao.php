<?php
// ============================================================
// pages/legislacao.php — leis que regem as ONGs e a transparência
// ============================================================
// página com layout próprio, fora do componente das páginas legais
// (Termos e Privacidade), cada tema tem o cabeçalho numerado de um
// lado e o conteúdo do outro, cada lei vira um cartão de vidro com o
// que diz, a quem vale, como o ONGHelp ajuda (só quando ajuda de
// verdade) e o link para o texto compilado no Portal da Legislação
// do Planalto, os estilos ficam no bloco "legislação" do site.css
//
// todos os links foram abertos e conferidos em 15/11/2026, a Lei de
// Incentivo ao Esporte (Lei 11.438/2006) não aparece porque foi
// revogada pela LC 222/2025, que regula o incentivo sem data para acabar

require_once __DIR__ . '/../includes/bootstrap.php';

const PLANALTO = 'https://www.planalto.gov.br/ccivil_03/';

/** endereço completo do texto oficial, os caminhos curtos são do Planalto */
function urlLei(string $url): string
{
    return str_starts_with($url, 'http') ? $url : PLANALTO . $url;
}

/**
 * cartão de uma lei, os textos são fixos desta página (sem dados de
 * quem usa o site), mas passam por e() mesmo assim
 *
 * $lei = [icone, numero, nome, texto, url, ajuda (opcional), artigos (opcional)]
 */
function cartaoLei(array $lei): string
{
    $artigos = isset($lei['artigos']) ? '<p class="lei-artigos">' . e($lei['artigos']) . '</p>' : '';
    $ajuda = isset($lei['ajuda'])
        ? '<p class="lei-ajuda"><i class="bi bi-check2-circle" aria-hidden="true"></i><span><strong>No ONGHelp:</strong> ' . e($lei['ajuda']) . '</span></p>'
        : '';

    return '<article class="lei-card glass-1">'
        . '<div class="lei-card-topo">'
        . '<span class="lei-icone" aria-hidden="true"><i class="bi ' . e($lei['icone']) . '"></i></span>'
        . '<span class="lei-numero">' . e($lei['numero']) . '</span>'
        . '</div>'
        . '<h3>' . e($lei['nome']) . '</h3>'
        . $artigos
        . '<p class="lei-texto">' . e($lei['texto']) . '</p>'
        . $ajuda
        . '<a class="lei-link" href="' . e(urlLei($lei['url'])) . '" target="_blank" rel="noopener noreferrer">'
        . 'Ler o texto oficial <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>'
        . '<span class="visually-hidden"> de ' . e($lei['numero']) . ', abre em nova aba</span></a>'
        . '</article>';
}

/** grade de cartões de um tema */
function grupoLeis(array $leis): string
{
    return '<div class="lei-grade">' . implode('', array_map('cartaoLei', $leis)) . '</div>';
}

// cada tema: id (âncora), título, frase de abertura, cor de destaque
// (só nos ícones e no número, que são decorativos) e o HTML do corpo
$temas = [];
$tema = function (string $id, string $titulo, string $intro, string $cor, string $corpo) use (&$temas): void {
    $temas[] = ['id' => $id, 'titulo' => $titulo, 'intro' => $intro, 'cor' => $cor, 'corpo' => $corpo];
};

// ── 1. por que importa ──────────────────────────────────────
$tema('por-que', 'Por que isso importa', 'Ter CNPJ, receber doações e atender pessoas traz direitos e deveres.', '#446e87', <<<HTML
<div class="lei-bloco glass-1">
  <p>Uma ONG pode nascer de um grupo de vizinhos, mas, a partir do momento em
  que tem CNPJ, recebe doações e atende pessoas, passa a seguir um conjunto de
  leis. Algumas valem para toda organização, outras só para quem recebe dinheiro
  público, tem um título como OSCIP ou atua numa área específica.</p>
  <p>Esta página reúne as principais, explicadas em poucas palavras, com o link
  para o texto oficial. Ela serve para a ONG saber o que se espera dela e para
  quem doa entender o que pode cobrar.</p>
  <div class="lg-aviso">
    <i class="bi bi-info-circle" aria-hidden="true"></i>
    <p>Este é um guia de orientação, <strong>não substitui contador nem
    advogado</strong>. O ONGHelp ajuda a organizar e publicar as contas, mas o
    cumprimento de cada lei continua sendo responsabilidade da própria
    organização. Quem recebe verba pública também segue as regras do órgão que
    repassou o dinheiro e do Tribunal de Contas.</p>
  </div>
</div>
HTML);

// ── 2. existir e funcionar ──────────────────────────────────
$tema('existir', 'Existir e funcionar',
    'A base de tudo: o direito de se associar, a forma jurídica da organização e o trabalho de quem ajuda sem receber.',
    '#0D5C8F',
    grupoLeis([
        ['icone' => 'bi-bank', 'numero' => 'Constituição Federal', 'artigos' => 'art. 5º, XVII a XXI',
         'nome' => 'Liberdade de associação',
         'texto' => 'Garante a liberdade de criar associações para fins lícitos, sem depender de autorização do governo. Uma associação só pode ter as atividades suspensas por decisão judicial e só pode ser dissolvida à força por decisão judicial definitiva, e ninguém é obrigado a se associar ou a continuar associado.',
         'url' => 'constituicao/constituicao.htm'],
        ['icone' => 'bi-journal-bookmark', 'numero' => 'Lei 10.406/2002', 'artigos' => 'arts. 44, 53 a 69',
         'nome' => 'Código Civil',
         'texto' => 'Define as duas formas mais comuns de ONG: a associação, união de pessoas sem fins econômicos com um estatuto aprovado em assembleia, e a fundação, um patrimônio destinado a uma finalidade social e fiscalizado pelo Ministério Público.',
         'url' => 'leis/2002/l10406compilada.htm',
         'ajuda' => 'a verificação confere se a natureza jurídica no CNPJ é de associação ou fundação.'],
        ['icone' => 'bi-hand-thumbs-up', 'numero' => 'Lei 9.608/1998',
         'nome' => 'Lei do Voluntariado',
         'texto' => 'O serviço voluntário não é remunerado e não cria vínculo de emprego. Ele deve ser formalizado por um termo de adesão entre a organização e o voluntário, e as despesas que o voluntário tiver na atividade podem ser ressarcidas, desde que a organização as tenha autorizado.',
         'url' => 'leis/l9608.htm'],
        ['icone' => 'bi-shield-exclamation', 'numero' => 'Lei 12.846/2013',
         'nome' => 'Lei Anticorrupção',
         'texto' => 'Responsabiliza pessoas jurídicas, inclusive associações e fundações, por atos contra a administração pública, como oferecer vantagem indevida a agente público ou fraudar licitações e contratos. A organização responde mesmo que não se prove culpa dos seus dirigentes.',
         'url' => '_ato2011-2014/2013/lei/l12846.htm'],
    ])
);

// ── 3. títulos e certificações ──────────────────────────────
$tema('titulos', 'Títulos e certificações',
    'São opcionais: nenhuma ONG precisa deles para existir ou receber doações. Cada um dá acesso a parcerias ou benefícios específicos, em troca de regras mais rígidas.',
    '#a66a00',
    grupoLeis([
        ['icone' => 'bi-award', 'numero' => 'Lei 9.790/1999',
         'nome' => 'OSCIP',
         'texto' => 'Organização da Sociedade Civil de Interesse Público é uma qualificação dada pelo Ministério da Justiça a entidades sem fins lucrativos que cumprem requisitos no estatuto. Ela permite firmar termos de parceria com o poder público.',
         'url' => 'leis/l9790.htm'],
        ['icone' => 'bi-building-gear', 'numero' => 'Lei 9.637/1998',
         'nome' => 'Organização Social (OS)',
         'texto' => 'Qualificação dada pelo governo federal a entidades que atuam em ensino, pesquisa científica, desenvolvimento tecnológico, meio ambiente, cultura e saúde. A OS administra serviços públicos por meio de um contrato de gestão, com metas e prestação de contas. Estados e municípios têm leis próprias de OS.',
         'url' => 'leis/l9637.htm'],
        ['icone' => 'bi-patch-check', 'numero' => 'Lei Complementar 187/2021',
         'nome' => 'Certificação de entidade beneficente (CEBAS)',
         'texto' => 'Certifica entidades beneficentes de saúde, educação e assistência social e define os requisitos para a imunidade das contribuições para a seguridade social. Substituiu a antiga Lei 12.101/2009.',
         'url' => 'leis/lcp/lcp187.htm'],
        ['icone' => 'bi-piggy-bank', 'numero' => 'Lei 13.800/2019',
         'nome' => 'Fundos patrimoniais',
         'texto' => 'Regula os fundos patrimoniais (endowments): reservas permanentes formadas por doações, cujos rendimentos financiam causas de interesse público de forma duradoura, como educação, saúde, cultura e assistência social.',
         'url' => '_ato2019-2022/2019/lei/l13800.htm'],
    ])
);

// ── 4. transparência e dinheiro público ─────────────────────
$tema('transparencia', 'Transparência e dinheiro público',
    'Quem recebe recursos públicos tem o dever de mostrar o que fez com eles. Estas são as leis que transformam transparência em obrigação.',
    '#16A085',
    grupoLeis([
        ['icone' => 'bi-diagram-3', 'numero' => 'Lei 13.019/2014', 'artigos' => 'art. 11',
         'nome' => 'Marco Regulatório das Organizações da Sociedade Civil (MROSC)',
         'texto' => 'Rege as parcerias entre ONGs e o poder público: termos de colaboração e de fomento, acordos de cooperação, chamamento público e prestação de contas. O art. 11 obriga a organização a divulgar na internet e na sede todas as parcerias firmadas com a administração pública.',
         'url' => '_ato2011-2014/2014/lei/l13019.htm',
         'ajuda' => 'o perfil público, os balancetes e os relatórios em PDF e CSV mostram receitas e despesas, a divulgação das parcerias pedida no art. 11 continua sendo da ONG.'],
        ['icone' => 'bi-file-earmark-ruled', 'numero' => 'Decreto 8.726/2016',
         'nome' => 'Regulamento federal do MROSC',
         'texto' => 'Detalha como funcionam as parcerias com o governo federal: plano de trabalho, liberação de recursos, monitoramento e prestação de contas. Estados e municípios podem ter regulamentos próprios.',
         'url' => '_ato2015-2018/2016/decreto/d8726.htm'],
        ['icone' => 'bi-eye', 'numero' => 'Lei 12.527/2011', 'artigos' => 'art. 2º',
         'nome' => 'Lei de Acesso à Informação (LAI)',
         'texto' => 'Garante a qualquer pessoa o acesso a informações públicas. Pelo art. 2º, entidades privadas sem fins lucrativos que recebem recursos públicos também precisam dar publicidade ao uso desses recursos.',
         'url' => '_ato2011-2014/2011/lei/l12527.htm',
         'ajuda' => 'o Portal de Transparência deixa as contas da ONG abertas a qualquer pessoa, sem cadastro.'],
        ['icone' => 'bi-graph-up', 'numero' => 'Lei Complementar 131/2009',
         'nome' => 'Lei da Transparência',
         'texto' => 'Alterou a Lei de Responsabilidade Fiscal para obrigar União, estados e municípios a publicar em tempo real, na internet, suas receitas e despesas. É nos portais de transparência do governo que aparecem os repasses feitos às ONGs.',
         'url' => 'leis/lcp/lcp131.htm'],
        ['icone' => 'bi-bookmark-star', 'numero' => 'Emenda Constitucional 86/2015',
         'nome' => 'Emendas impositivas',
         'texto' => 'Tornou obrigatória a execução das emendas individuais de parlamentares ao orçamento. Boa parte dos repasses a ONGs vem dessas emendas, que entram na prestação de contas como qualquer recurso público.',
         'url' => 'constituicao/emendas/emc/emc86.htm'],
    ])
);

// ── 5. doações e impostos ───────────────────────────────────
$tema('doacoes', 'Doações e impostos',
    'As regras que definem quando a ONG deixa de pagar impostos e quando quem doa pode abater a doação do imposto de renda.',
    '#007a55',
    grupoLeis([
        ['icone' => 'bi-shield-check', 'numero' => 'Constituição Federal', 'artigos' => 'art. 150, VI, c',
         'nome' => 'Imunidade tributária',
         'texto' => 'Proíbe cobrar impostos sobre o patrimônio, a renda e os serviços de instituições de educação e de assistência social sem fins lucrativos, desde que cumpram os requisitos da lei.',
         'url' => 'constituicao/constituicao.htm#art150'],
        ['icone' => 'bi-arrow-repeat', 'numero' => 'Emenda Constitucional 132/2023',
         'nome' => 'Reforma tributária',
         'texto' => 'Manteve a imunidade e a estendeu aos novos tributos sobre o consumo, o IBS e a CBS. Também determinou que doações a instituições sem fins lucrativos de relevância pública e social não paguem o imposto estadual sobre doações (ITCMD), nas condições definidas em lei complementar.',
         'url' => 'constituicao/emendas/emc/emc132.htm'],
        ['icone' => 'bi-receipt', 'numero' => 'Lei 9.532/1997', 'artigos' => 'arts. 12 e 15',
         'nome' => 'Requisitos da imunidade e da isenção',
         'texto' => 'Junto com o art. 14 do Código Tributário Nacional, lista o que a entidade precisa cumprir para manter a imunidade ou a isenção, como não distribuir o patrimônio ou a renda, aplicar os recursos integralmente nos seus objetivos, manter a contabilidade em dia e guardar por cinco anos os documentos que comprovam receitas e despesas.',
         'url' => 'leis/l9532.htm',
         'ajuda' => 'cada lançamento fica com categoria, data e comprovante, o que ajuda a manter a documentação organizada.'],
        ['icone' => 'bi-briefcase', 'numero' => 'Lei 9.249/1995', 'artigos' => 'art. 13, § 2º',
         'nome' => 'Doações de empresas',
         'texto' => 'Empresas tributadas pelo lucro real podem deduzir doações feitas a entidades sem fins lucrativos que atendam aos requisitos da lei, até o limite de 2% do lucro operacional.',
         'url' => 'leis/l9249.htm'],
        ['icone' => 'bi-balloon-heart', 'numero' => 'Lei 8.069/1990', 'artigos' => 'art. 260',
         'nome' => 'Fundos da Criança e do Adolescente',
         'texto' => 'Pessoas físicas e empresas podem destinar parte do imposto de renda devido aos Fundos dos Direitos da Criança e do Adolescente, geridos pelos conselhos municipais, estaduais e nacional, que financiam projetos de ONGs da área.',
         'url' => 'leis/l8069.htm'],
        ['icone' => 'bi-person-heart', 'numero' => 'Lei 12.213/2010',
         'nome' => 'Fundos da Pessoa Idosa',
         'texto' => 'Cria o Fundo Nacional do Idoso e permite deduzir do imposto de renda as doações feitas aos fundos do idoso nacional, estaduais e municipais.',
         'url' => '_ato2007-2010/2010/lei/l12213.htm'],
    ]) . <<<HTML
<div class="lg-aviso">
  <i class="bi bi-lightbulb" aria-hidden="true"></i>
  <p><strong>Doar diretamente para uma ONG, como pessoa física, não reduz o
  imposto de renda.</strong> O benefício vale para os fundos da criança e do
  idoso e para projetos aprovados nas leis de incentivo à cultura e ao esporte
  (veja "Leis por área de atuação"). Os limites mudam com as regras da Receita
  Federal, então confira no ano da declaração.</p>
</div>
HTML
);

// ── 6. pessoas atendidas ────────────────────────────────────
$tema('pessoas', 'Quem é atendido',
    'Muitas ONGs trabalham com públicos que a lei protege de forma especial. Estas regras valem para o atendimento e também para o que se publica sobre essas pessoas.',
    '#8E44AD',
    grupoLeis([
        ['icone' => 'bi-balloon', 'numero' => 'Lei 8.069/1990', 'artigos' => 'arts. 17 e 18',
         'nome' => 'Estatuto da Criança e do Adolescente (ECA)',
         'texto' => 'Protege a imagem, a identidade e a dignidade de crianças e adolescentes. Fotos e relatos só podem ser publicados com autorização dos responsáveis e sem expor dados que permitam identificá-los em situação de vulnerabilidade.',
         'url' => 'leis/l8069.htm',
         'ajuda' => 'qualquer pessoa pode denunciar uma notícia que exponha uma criança, e a moderação pode ocultá-la.'],
        ['icone' => 'bi-person-walking', 'numero' => 'Lei 10.741/2003',
         'nome' => 'Estatuto da Pessoa Idosa',
         'texto' => 'Garante os direitos das pessoas com 60 anos ou mais e define regras para as entidades que as atendem, que devem inscrever seus programas nos conselhos municipais da pessoa idosa.',
         'url' => 'leis/2003/l10.741.htm'],
        ['icone' => 'bi-universal-access', 'numero' => 'Lei 13.146/2015',
         'nome' => 'Lei Brasileira de Inclusão',
         'texto' => 'Estatuto da Pessoa com Deficiência: assegura acessibilidade, atendimento prioritário e igualdade de oportunidades, e orienta como serviços e comunicação devem chegar a todas as pessoas.',
         'url' => '_ato2015-2018/2015/lei/l13146.htm'],
        ['icone' => 'bi-house-heart', 'numero' => 'Lei 8.742/1993',
         'nome' => 'Lei Orgânica da Assistência Social (LOAS)',
         'texto' => 'Define o que é uma entidade de assistência social e exige a inscrição no Conselho Municipal de Assistência Social para funcionar. Também organiza o Sistema Único de Assistência Social (SUAS).',
         'url' => 'leis/l8742.htm'],
    ])
);

// ── 7. dados, imagem e internet ─────────────────────────────
$tema('dados', 'Dados, imagem e internet',
    'Toda ONG lida com dados de doadores, voluntários e atendidos. Estas leis definem como guardá-los, o que pode ser publicado e as regras da vida na internet.',
    '#C2185B',
    grupoLeis([
        ['icone' => 'bi-database-lock', 'numero' => 'Lei 13.709/2018',
         'nome' => 'Lei Geral de Proteção de Dados (LGPD)',
         'texto' => 'Regula o uso de dados pessoais: só para finalidades claras, com base legal e segurança. Dados sensíveis, como saúde e religião, têm proteção reforçada, e dados de crianças e adolescentes devem ser tratados sempre no melhor interesse deles (art. 14).',
         'url' => '_ato2015-2018/2018/lei/l13709.htm',
         'ajuda' => 'as fotos perdem os metadados ao serem enviadas e os dados do responsável legal nunca ficam públicos.'],
        ['icone' => 'bi-globe2', 'numero' => 'Lei 12.965/2014',
         'nome' => 'Marco Civil da Internet',
         'texto' => 'Estabelece direitos e deveres no uso da internet no Brasil: privacidade, liberdade de expressão e as regras de responsabilidade dos sites pelo conteúdo publicado por terceiros. Em 2025, o STF ampliou essa responsabilidade ao julgar o art. 19: em regra, o site responde se não remover conteúdo ilícito depois de ser avisado.',
         'url' => '_ato2011-2014/2014/lei/l12965.htm',
         'ajuda' => 'os Termos de Uso e a Política de Privacidade seguem o Marco Civil e a LGPD.'],
        ['icone' => 'bi-shield-lock', 'numero' => 'Decreto 8.771/2016',
         'nome' => 'Regulamento do Marco Civil',
         'texto' => 'Define padrões de segurança para a guarda de registros e dados pessoais na internet, como controle de acesso e registro de quem acessou cada informação.',
         'url' => '_ato2015-2018/2016/decreto/d8771.htm'],
        ['icone' => 'bi-phone', 'numero' => 'Lei 15.211/2025',
         'nome' => 'ECA Digital',
         'texto' => 'Estatuto Digital da Criança e do Adolescente, em vigor desde março de 2026: define deveres de proteção para produtos e serviços digitais voltados a crianças e adolescentes ou que eles provavelmente usem, como verificação de idade, ferramentas de supervisão dos responsáveis e configurações mais protetivas por padrão.',
         'url' => '_ato2023-2026/2025/lei/l15211.htm'],
        ['icone' => 'bi-c-circle', 'numero' => 'Lei 9.610/1998',
         'nome' => 'Lei de Direitos Autorais',
         'texto' => 'Textos, fotos e vídeos pertencem a quem os criou. A ONG só deve publicar material de terceiros com autorização do autor ou dentro dos limites que a lei permite.',
         'url' => 'leis/l9610.htm'],
    ])
);

// ── 8. por área de atuação ──────────────────────────────────
$leisPorArea = [
    'Animais' => [
        ['Lei 9.605/1998, art. 32', 'Crime de maus-tratos a animais. Com a Lei 14.064/2020, a pena para maus-tratos a cães e gatos passou a ser de reclusão de dois a cinco anos.', 'leis/l9605.htm'],
        ['Lei 14.228/2021', 'Proíbe que órgãos de controle de zoonoses e canis públicos eliminem cães e gatos, salvo eutanásia em casos de males, doenças graves ou enfermidades infectocontagiosas incuráveis.', '_ato2019-2022/2021/lei/l14228.htm'],
    ],
    'Assistência Social' => [
        ['Lei 8.742/1993 (LOAS)', 'Define as entidades de assistência social e exige inscrição no conselho municipal.', 'leis/l8742.htm'],
        ['LC 187/2021 (CEBAS)', 'Certificação de entidade beneficente e imunidade das contribuições sociais.', 'leis/lcp/lcp187.htm'],
    ],
    'Cultura' => [
        ['Lei 8.313/1991 (Rouanet)', 'Doações e patrocínios a projetos culturais aprovados podem ser deduzidos do imposto de renda.', 'leis/l8313cons.htm'],
        ['Lei 14.399/2022 (Aldir Blanc)', 'Política Nacional Aldir Blanc: repasses da União a estados e municípios para fomento à cultura.', '_ato2019-2022/2022/lei/l14399.htm'],
    ],
    'Educação' => [
        ['Lei 9.394/1996 (LDB)', 'Diretrizes e bases da educação nacional, referência para projetos de reforço escolar e educação não formal.', 'leis/l9394.htm'],
        ['Lei 9.795/1999', 'Política Nacional de Educação Ambiental, que também alcança a educação feita fora da escola.', 'leis/l9795.htm'],
    ],
    'Esporte' => [
        ['Lei 14.597/2023', 'Lei Geral do Esporte: organiza o sistema esportivo e reconhece o esporte como direito social.', '_ato2023-2026/2023/lei/l14597.htm'],
        ['LC 222/2025', 'Substituiu a antiga Lei de Incentivo ao Esporte e regula o incentivo sem data para acabar: pessoas físicas e empresas do lucro real podem deduzir do imposto de renda doações e patrocínios a projetos esportivos aprovados.', 'leis/lcp/lcp222.htm'],
    ],
    'Meio Ambiente' => [
        ['Lei 6.938/1981', 'Política Nacional do Meio Ambiente, base de toda a legislação ambiental.', 'leis/l6938.htm'],
        ['Lei 9.605/1998', 'Lei de Crimes Ambientais: sanções penais e administrativas para condutas que agridem o meio ambiente.', 'leis/l9605.htm'],
    ],
    'Saúde' => [
        ['Lei 8.080/1990', 'Lei Orgânica da Saúde: organiza o SUS e dá preferência a entidades filantrópicas e sem fins lucrativos na participação complementar ao sistema.', 'leis/l8080.htm'],
    ],
    'Outra' => [
        ['Leis gerais', 'Constituição, Código Civil, MROSC, LGPD e as regras de doação desta página valem para qualquer área.', '#existir'],
    ],
];

$coresArea = coresArea();
$htmlAreas = '<div class="lei-areas">';
foreach ($leisPorArea as $area => $leis) {
    $htmlAreas .= '<article class="lei-area glass-1" style="--causa-cor:' . e($coresArea[$area][0] ?? '#5A6A80') . ';">'
        . '<h3><img src="' . e(iconeArea($area, '../')) . '" alt="" width="40" height="40"> ' . e($area) . '</h3><ul>';
    foreach ($leis as [$numero, $texto, $url]) {
        $externo = !str_starts_with($url, '#');
        $href = $externo ? PLANALTO . $url : $url;
        $htmlAreas .= '<li><a href="' . e($href) . '"' . ($externo ? ' target="_blank" rel="noopener noreferrer"' : '') . '>'
            . e($numero) . ($externo ? ' <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i><span class="visually-hidden">, abre em nova aba</span>' : '') . '</a>'
            . '<span>' . e($texto) . '</span></li>';
    }
    $htmlAreas .= '</ul></article>';
}
$htmlAreas .= '</div>';
$tema('areas', 'Leis por área de atuação',
    'Além das leis gerais, cada causa tem normas próprias. As cores são as mesmas usadas para as áreas em todo o ONGHelp.',
    '#E8913A', $htmlAreas);

// ── 9. quem doa ─────────────────────────────────────────────
$receitaCnpj = 'https://solucoes.receita.fazenda.gov.br/servicos/cnpjreva/cnpjreva_solicitacao.asp';
$mapaOsc     = 'https://mapaosc.ipea.gov.br/';
$codigoPenal = PLANALTO . 'decreto-lei/del2848compilado.htm';
$anpd        = 'https://www.gov.br/anpd/pt-br';
$tema('quem-doa', 'Para quem doa',
    'Doar é um ato de confiança. Antes de transferir, vale conferir alguns pontos, que qualquer pessoa pode checar em poucos minutos.',
    '#1d4052', <<<HTML
<div class="lei-doador">
  <ol class="lei-checklist">
    <li><i class="bi bi-1-circle" aria-hidden="true"></i><span><strong>O CNPJ existe e está ativo.</strong>
    Consulte no <a href="{$receitaCnpj}" target="_blank" rel="noopener noreferrer">comprovante de inscrição da Receita Federal</a>.
    No ONGHelp, isso é conferido na verificação de cada cadastro.</span></li>
    <li><i class="bi bi-2-circle" aria-hidden="true"></i><span><strong>A organização aparece em fontes públicas.</strong>
    O <a href="{$mapaOsc}" target="_blank" rel="noopener noreferrer">Mapa das Organizações da Sociedade Civil</a>, do Ipea,
    reúne dados oficiais das OSCs do país.</span></li>
    <li><i class="bi bi-3-circle" aria-hidden="true"></i><span><strong>O nome do recebedor do PIX é o da ONG.</strong>
    No ONGHelp a chave PIX é sempre o próprio CNPJ, verificado pela equipe. Desconfie de chaves de pessoa física.</span></li>
    <li><i class="bi bi-4-circle" aria-hidden="true"></i><span><strong>As contas estão abertas.</strong>
    Organizações sérias mostram de onde vem e para onde vai o dinheiro. No ONGHelp, isso está no
    <a href="transparencia.php">Portal de Transparência</a>.</span></li>
  </ol>
  <p>Pedir dinheiro em nome de uma ONG falsa é crime de estelionato
  (<a href="{$codigoPenal}" target="_blank" rel="noopener noreferrer">Código Penal, art. 171</a>). Se você
  desconfiar de uma organização no ONGHelp, fale com a equipe pelo
  <a href="contato.php">Fale conosco</a>. Problemas com seus dados pessoais podem ser levados à
  <a href="{$anpd}" target="_blank" rel="noopener noreferrer">Agência Nacional de Proteção de Dados (ANPD)</a>.</p>
</div>
HTML);

// ── 10. perguntas frequentes ────────────────────────────────
$perguntas = [
    ['Minha ONG não recebe dinheiro público. Essas leis valem para ela?', 'Parte delas, sim. A Constituição, o Código Civil, a LGPD, o ECA e a Lei do Voluntariado valem para toda organização. Já o MROSC, a Lei de Acesso à Informação e as regras de prestação de contas ao governo passam a valer quando a ONG firma parceria ou recebe recursos públicos.'],
    ['Preciso ser OSCIP para receber doações?', 'Não. Qualquer associação ou fundação regular pode receber doações. Títulos como OSCIP, OS e CEBAS são opcionais e servem para parcerias e benefícios específicos.'],
    ['Quem doa pelo PIX do ONGHelp pode abater do imposto de renda?', 'Não. Doação direta de pessoa física para uma ONG não é dedutível. O abatimento vale para os fundos da criança e do idoso e para projetos aprovados nas leis de incentivo à cultura e ao esporte.'],
    ['Posso publicar fotos das pessoas atendidas?', 'Só com autorização delas. Fotos de crianças e adolescentes precisam da autorização dos responsáveis e não podem expor dados que os identifiquem. Informações de saúde, religião ou outros dados sensíveis dos atendidos não devem ser publicadas.'],
    ['Voluntário precisa de contrato?', 'Precisa de um termo de adesão, que registra a atividade e deixa claro que não há vínculo de emprego nem salário. Despesas do voluntário na atividade podem ser ressarcidas, desde que autorizadas pela organização.'],
    ['O ONGHelp garante que uma ONG cumpre todas essas leis?', 'Não. O selo de verificação confirma dados cadastrais, como CNPJ, responsável e chave PIX, na data indicada. O cumprimento das leis e a veracidade dos valores publicados são responsabilidade de cada organização.'],
];
$htmlFaq = '<div class="eq-faq lei-faq">';
foreach ($perguntas as [$pergunta, $resposta]) {
    $htmlFaq .= '<details class="eq-faq-item"><summary>' . e($pergunta) . '<i class="bi bi-plus-lg" aria-hidden="true"></i></summary><p>' . e($resposta) . '</p></details>';
}
$htmlFaq .= '</div>';
$tema('perguntas', 'Perguntas frequentes', 'As dúvidas que mais aparecem entre ONGs e doadores.', '#0D5C8F', $htmlFaq);

// ── 11. fontes ──────────────────────────────────────────────
$tema('fontes', 'Fontes e atualização', 'De onde vêm os textos e como avisar se algo mudou.', '#446e87', <<<HTML
<div class="lei-bloco glass-1">
  <p>Todos os links levam ao texto compilado no Portal da Legislação do Governo
  Federal (planalto.gov.br), que já incorpora as alterações posteriores de cada
  lei. Os links foram conferidos em 15 de novembro de 2026.</p>
  <p>Leis mudam: a antiga Lei de Incentivo ao Esporte (Lei 11.438/2006), por
  exemplo, foi revogada e substituída pela Lei Complementar 222/2025. Se você
  encontrar algo desatualizado, avise a equipe pelo <a href="contato.php">Fale conosco</a>.</p>
  <p>As regras do próprio ONGHelp estão nos <a href="termos.php">Termos de Uso</a>
  e na <a href="privacidade.php">Política de Privacidade</a>.</p>
</div>
HTML);

// números do topo: normas diferentes com link oficial (sem contar a
// mesma lei citada em dois lugares), áreas e minutos de leitura
$normas = [];
foreach ($temas as $t) {
    preg_match_all('~href="https://www\.planalto\.gov\.br/ccivil_03/([^"#]+)~', $t['corpo'], $m);
    $normas += array_flip($m[1]);
}
$totalPalavras  = str_word_count(strip_tags(implode(' ', array_column($temas, 'corpo'))));
$minutosLeitura = max(1, (int) round($totalPalavras / 200));

$resumo = [
    ['icone' => 'bi-people', 'titulo' => 'Associar-se é um direito', 'texto' => 'A Constituição garante criar uma ONG sem pedir autorização ao governo.'],
    ['icone' => 'bi-eye', 'titulo' => 'Transparência é lei', 'texto' => 'Quem recebe dinheiro público precisa mostrar na internet o que fez com ele.'],
    ['icone' => 'bi-receipt', 'titulo' => 'Imposto tem regra', 'texto' => 'Doar direto a uma ONG não abate o IR. Fundos e projetos incentivados, sim.'],
    ['icone' => 'bi-shield-lock', 'titulo' => 'Quem é atendido é protegido', 'texto' => 'ECA e LGPD limitam o que se publica sobre crianças e dados pessoais.'],
];

$page_title       = 'Legislação';
$page_description = 'As leis do terceiro setor explicadas: Código Civil, MROSC, Lei de Acesso à Informação, LGPD, ECA, imunidade tributária, doações dedutíveis e leis por área de atuação, com links oficiais.';
$active_nav       = 'legislacao';
include __DIR__ . '/../includes/layout/header.php';
?>

<div class="lei-page">
  <div class="container">

    <!-- ── topo: título e resumo em vidro ── -->
    <header class="lei-hero">
      <div>
        <span class="badge-secao"><i class="bi bi-bank" aria-hidden="true"></i> Legislação</span>
        <h1 class="lg-titulo">As leis do <span class="lg-titulo-destaque">terceiro setor</span></h1>
        <p class="lg-subtitulo">O que a lei brasileira diz sobre ONGs, transparência, doações e proteção de quem é atendido, explicado em poucas palavras e com o link para o texto oficial.</p>
        <ul class="lg-meta">
          <li><i class="bi bi-calendar3" aria-hidden="true"></i> Atualizado em 15 de novembro de 2026</li>
          <li><i class="bi bi-clock" aria-hidden="true"></i> Cerca de <?= $minutosLeitura ?> min de leitura</li>
          <li><a href="transparencia.php"><i class="bi bi-arrow-right-short" aria-hidden="true"></i>Ver o Portal de Transparência</a></li>
        </ul>
        <ul class="eq-numeros" aria-label="Esta página em números">
          <li><strong><?= count($normas) ?></strong><span>normas com link oficial</span></li>
          <li><strong><?= count($leisPorArea) ?></strong><span>áreas de atuação</span></li>
          <li><strong><?= count($perguntas) ?></strong><span>perguntas respondidas</span></li>
        </ul>
      </div>

      <section class="lei-resumo glass-2" aria-labelledby="leiResumoTitulo">
        <h2 class="lg-resumo-titulo" id="leiResumoTitulo">Em resumo</h2>
        <ul>
          <?php foreach ($resumo as $item): ?>
            <li>
              <span class="lg-resumo-icone" aria-hidden="true"><i class="bi <?= e($item['icone']) ?>"></i></span>
              <div>
                <h3><?= e($item['titulo']) ?></h3>
                <p><?= e($item['texto']) ?></p>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
        <p class="lg-resumo-nota">O resumo ajuda na leitura, mas o texto completo abaixo é o que vale.</p>
      </section>
    </header>

    <!-- ── índice em pílulas ── -->
    <nav class="lei-indice glass-1" aria-label="Sumário">
      <p class="lei-indice-titulo">Nesta página</p>
      <ol>
        <?php foreach ($temas as $i => $t): ?>
          <li><a href="#<?= e($t['id']) ?>"><span><?= $i + 1 ?></span><?= e($t['titulo']) ?></a></li>
        <?php endforeach; ?>
      </ol>
    </nav>

    <!-- ── temas: cabeçalho numerado ao lado do conteúdo ── -->
    <?php foreach ($temas as $i => $t): ?>
      <section class="lei-tema" id="<?= e($t['id']) ?>" aria-labelledby="<?= e($t['id']) ?>-titulo" style="--lei-cor:<?= e($t['cor']) ?>;">
        <header class="lei-tema-cabeca">
          <span class="lei-tema-num" aria-hidden="true"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
          <h2 id="<?= e($t['id']) ?>-titulo"><?= e($t['titulo']) ?></h2>
          <p><?= e($t['intro']) ?></p>
        </header>
        <div class="lei-tema-corpo"><?= $t['corpo'] ?></div>
      </section>
    <?php endforeach; ?>

    <!-- contato -->
    <aside class="lg-contato lei-contato">
      <span class="lg-contato-icone" aria-hidden="true"><i class="bi bi-envelope-paper-heart"></i></span>
      <div>
        <h2>Ficou alguma dúvida?</h2>
        <p>Viu algo desatualizado ou quer sugerir uma lei? Fale com a Equipe ONGHelp.</p>
      </div>
      <a class="btn btn-primary rounded-pill" href="contato.php">
        <i class="bi bi-chat-heart" aria-hidden="true"></i> Fale conosco
      </a>
    </aside>

  </div>
</div>

<?php include __DIR__ . '/../includes/layout/footer.php'; ?>
