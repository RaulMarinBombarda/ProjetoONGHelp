<?php
// ============================================================
// pages/equipe.php — página pública para novas pessoas moderadoras
// ============================================================
// página não indexada, com contato único por WhatsApp (configurado
// em config/app.php), os números do topo vêm do banco

require_once __DIR__ . '/../includes/bootstrap.php';
// só para contar os itens do checklist real do painel, sem exigir login
require_once __DIR__ . '/../includes/modules/admin-auth.php';

$page_title  = 'Faça parte da equipe';
$page_robots = 'noindex, nofollow';
$active_nav  = '';

$whatsappUrl = whatsappEquipeUrl();
$regras      = regrasUsoEquipe();

$numeros = $pdo->query(
    "SELECT
        (SELECT COUNT(*) FROM usuarios WHERE status = 'aprovada') AS verificadas,
        (SELECT COUNT(*) FROM usuarios WHERE status = 'pendente') AS pendentes,
        (SELECT COUNT(*) FROM noticias WHERE estado = 'publicada') AS noticias"
)->fetch();

$totalBloqueantes = count(checklistBloqueantes());
$totalAtencao     = count(checklistAtencao());

$tarefas = [
    ['bi-building-check', 'Analisar cadastros', 'Conferir CNPJ, canal público, responsável legal e chave PIX de cada ONG antes de ela aparecer no site.'],
    ['bi-check2-circle', 'Decidir com critério', 'Aprovar, recusar ou suspender, sempre registrando o motivo. Na dúvida, a regra é recusar e explicar.'],
    ['bi-newspaper', 'Moderar notícias', 'Analisar denúncias e notícias publicadas pelas ONGs, ocultando o que viola os Termos de Uso.'],
    ['bi-shield-lock', 'Proteger dados', 'Tratar como confidencial tudo o que é visto no painel, seguindo a LGPD.'],
];

$ferramentas = [
    ['bi-list-check', 'Checklist de verificação', $totalBloqueantes . ' verificações obrigatórias, como CNPJ ativo e PIX da própria ONG, e ' . $totalAtencao . ' sinais de atenção.'],
    ['bi-box-arrow-up-right', 'Fontes de conferência', 'Atalhos para consultar CNPJ e canais públicos da organização.'],
    ['bi-clock-history', 'Histórico completo', 'Toda decisão fica registrada com autor, data e motivo.'],
    ['bi-person-badge', 'Conta individual', 'Login próprio, senha de no mínimo ' . SENHA_MINIMO_EQUIPE . ' caracteres e permissões de moderador.'],
];

$perfil = [
    'Pessoas de confiança',
    'Atenção a detalhes',
    'Disponibilidade combinada',
    'Sigilo com dados de terceiros',
    'Boa escrita para explicar decisões',
    'Interesse pelo terceiro setor',
];

$perguntas = [
    ['Preciso ter experiência?', 'Não. Explicamos os critérios na conversa inicial, e o painel guia cada análise com um checklist. O mais importante é atenção e responsabilidade.'],
    ['Quanto tempo vou dedicar?', 'Combinamos uma disponibilidade que caiba na sua rotina. Cada análise de cadastro leva poucos minutos e pode ser feita de qualquer lugar.'],
    ['É voluntário ou remunerado?', 'Os termos de participação são definidos na conversa, de acordo com o momento do projeto.'],
    ['Posso analisar uma ONG da qual participo?', 'Não. Se tiver vínculo com alguma organização, avise a equipe: ela fica fora das suas análises para evitar conflito de interesse.'],
    ['Meus dados vão aparecer no site?', 'Não. Seu nome aparece só no histórico interno do painel. O site público mostra apenas que o cadastro foi verificado pela Equipe ONGHelp.'],
    ['Preciso instalar algum programa?', 'Não. O painel funciona no navegador, no computador ou no celular, com o login e a senha da sua conta individual.'],
    ['E se eu me enganar em uma análise?', 'Nenhuma decisão é definitiva: uma ONG aprovada pode ser suspensa e reativada depois, sempre com o motivo registrado no histórico.'],
];

include __DIR__ . '/../includes/layout/header.php';
?>

<div class="page-main equipe-publica">

  <!-- ── hero ── -->
  <section class="equipe-hero" aria-labelledby="equipeTitulo">
    <div class="container">
      <div class="row align-items-center g-4 g-lg-5">
        <div class="col-lg-7">
          <span class="badge-secao"><i class="bi bi-people" aria-hidden="true"></i> Equipe ONGHelp</span>

          <h1 id="equipeTitulo" class="equipe-hero-titulo">
            Ajude a manter a <span class="eq-destaque">transparência</span> de pé
          </h1>

          <p class="equipe-hero-texto">
            Toda ONG que aparece no ONGHelp passou pela análise de uma pessoa da
            equipe. Moderadores conferem cadastros, avaliam denúncias e garantem
            que quem doa encontre organizações reais.
          </p>

          <ul class="eq-numeros" aria-label="O ONGHelp em números">
            <li><strong><?= (int) $numeros['verificadas'] ?></strong><span>ONGs verificadas</span></li>
            <li><strong><?= (int) $numeros['pendentes'] ?></strong><span>cadastros aguardando análise</span></li>
            <li><strong><?= (int) $numeros['noticias'] ?></strong><span>notícias publicadas</span></li>
          </ul>
        </div>

        <div class="col-lg-5">
          <aside class="equipe-contato-card glass-1" aria-labelledby="equipeContatoTitulo">
            <span class="equipe-contato-icone" aria-hidden="true"><i class="bi bi-whatsapp"></i></span>
            <h2 id="equipeContatoTitulo">Fale com a equipe</h2>
            <p>Mande seu nome e sua cidade pelo WhatsApp para qualquer um dos números. A gente responde e marca uma conversa rápida.</p>

            <?php
            $contatosMensagem = ONGHELP_WHATSAPP_MENSAGEM_EQUIPE;
            include __DIR__ . '/../includes/components/contatos-whatsapp.php';
            ?>
          </aside>
        </div>
      </div>
    </div>
  </section>

  <!-- ── o que você vai fazer ── -->
  <section class="section" aria-labelledby="equipeTarefasTitulo">
    <div class="container">
      <div class="eq-cabeca">
        <h2 id="equipeTarefasTitulo">O que você vai fazer</h2>
        <p>Moderação é o que transforma o ONGHelp em um lugar confiável para doar.</p>
      </div>

      <div class="eq-cards">
        <?php foreach ($tarefas as [$icone, $titulo, $texto]): ?>
          <article class="eq-card">
            <span class="eq-card-icone" aria-hidden="true"><i class="bi <?= $icone ?>"></i></span>
            <h3><?= $titulo ?></h3>
            <p><?= $texto ?></p>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- ── ferramentas + perfil ── -->
  <section class="section section-tight" aria-labelledby="equipeFerramentasTitulo">
    <div class="container">
      <div class="eq-painel glass-1">
        <div class="eq-painel-ferramentas">
          <h2 id="equipeFerramentasTitulo" class="equipe-bloco-titulo">Você não trabalha no escuro</h2>
          <p class="eq-painel-intro">O painel da equipe foi feito para que cada decisão seja rápida, justa e rastreável.</p>
          <ul class="eq-ferramentas">
            <?php foreach ($ferramentas as [$icone, $titulo, $texto]): ?>
              <li>
                <i class="bi <?= $icone ?>" aria-hidden="true"></i>
                <div><strong><?= $titulo ?></strong><span><?= $texto ?></span></div>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>

        <div class="eq-painel-perfil">
          <h2 class="equipe-bloco-titulo">Quem procuramos</h2>
          <ul class="eq-chips">
            <?php foreach ($perfil as $item): ?>
              <li><i class="bi bi-check2" aria-hidden="true"></i> <?= $item ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    </div>
  </section>

  <!-- ── como funciona ── -->
  <section class="section section-tight" id="como-funciona" aria-labelledby="equipeComoTitulo">
    <div class="container">
      <div class="eq-cabeca">
        <h2 id="equipeComoTitulo">Como funciona</h2>
        <p>Do primeiro contato à primeira análise, em quatro passos.</p>
      </div>

      <ol class="equipe-como">
        <li class="equipe-como-passo">
          <span class="equipe-como-numero" aria-hidden="true">1</span>
          <h3>Você chama a equipe no WhatsApp</h3>
          <p>Conte seu nome e sua cidade para começarmos a conversa.</p>
        </li>
        <li class="equipe-como-passo">
          <span class="equipe-como-numero" aria-hidden="true">2</span>
          <h3>Fazemos uma conversa rápida</h3>
          <p>Alinhamos disponibilidade, expectativas e as regras de sigilo.</p>
        </li>
        <li class="equipe-como-passo">
          <span class="equipe-como-numero" aria-hidden="true">3</span>
          <h3>Você envia as informações pedidas</h3>
          <p>Somente os dados listados abaixo, sem documentos sensíveis.</p>
        </li>
        <li class="equipe-como-passo">
          <span class="equipe-como-numero" aria-hidden="true">4</span>
          <h3>Criamos sua conta individual</h3>
          <p>No primeiro acesso você define sua senha e aceita as regras do painel.</p>
        </li>
      </ol>
    </div>
  </section>

  <!-- ── o que vamos pedir ── -->
  <section class="section" aria-labelledby="equipePedirTitulo">
    <div class="container">
      <div class="equipe-pedir glass-1">
        <div>
          <h2 id="equipePedirTitulo" class="equipe-bloco-titulo">O que vamos pedir</h2>
          <ul class="equipe-lista-pedir">
            <li><i class="bi bi-dot" aria-hidden="true"></i> Nome completo</li>
            <li><i class="bi bi-dot" aria-hidden="true"></i> E-mail que será seu login</li>
            <li><i class="bi bi-dot" aria-hidden="true"></i> Cidade</li>
            <li><i class="bi bi-dot" aria-hidden="true"></i> Motivação</li>
            <li><i class="bi bi-dot" aria-hidden="true"></i> Disponibilidade</li>
            <li><i class="bi bi-dot" aria-hidden="true"></i> Se tem vínculo com alguma ONG</li>
          </ul>
        </div>

        <div class="equipe-nota-info">
          <i class="bi bi-shield-check" aria-hidden="true"></i>
          <p>Não pedimos CPF, RG nem dados bancários.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- ── regras ── -->
  <section class="section section-tight" aria-labelledby="equipeRegrasTitulo">
    <div class="container">
      <div class="equipe-regras glass-1">
        <h2 id="equipeRegrasTitulo" class="equipe-bloco-titulo">Regras e compromissos</h2>
        <ol class="equipe-lista-regras">
          <?php foreach ($regras as $indice => $regra): ?>
            <li>
              <span class="equipe-regra-numero" aria-hidden="true"><?= str_pad((string) ($indice + 1), 2, '0', STR_PAD_LEFT) ?></span>
              <span><?= htmlspecialchars($regra) ?></span>
            </li>
          <?php endforeach; ?>
        </ol>
      </div>
    </div>
  </section>

  <!-- ── perguntas frequentes ── -->
  <section class="section section-tight" aria-labelledby="equipeFaqTitulo">
    <div class="container">
      <div class="eq-cabeca eq-cabeca-centro">
        <h2 id="equipeFaqTitulo">Perguntas frequentes</h2>
        <p>O que costumam perguntar antes de entrar para a equipe.</p>
      </div>

      <div class="eq-faq">
        <?php foreach ($perguntas as [$pergunta, $resposta]): ?>
          <details class="eq-faq-item">
            <summary><?= $pergunta ?><i class="bi bi-plus-lg" aria-hidden="true"></i></summary>
            <p><?= $resposta ?></p>
          </details>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- ── fechamento ── -->
  <section class="section equipe-fechamento-section">
    <div class="container">
      <div class="equipe-fechamento">
        <div class="equipe-fechamento-texto">
          <h2>Pronto para conversar?</h2>
          <p>Chame a equipe no WhatsApp ou escreva para <a href="mailto:<?= ONGHELP_EMAIL_EQUIPE ?>"><?= ONGHELP_EMAIL_EQUIPE ?></a>.</p>
        </div>

        <a class="equipe-btn-claro"
           href="<?= htmlspecialchars($whatsappUrl, ENT_QUOTES, 'UTF-8') ?>"
           target="_blank" rel="noopener noreferrer">
          <i class="bi bi-whatsapp" aria-hidden="true"></i>
          Falar no WhatsApp
        </a>
      </div>
    </div>
  </section>

</div>

<?php include __DIR__ . '/../includes/layout/footer.php'; ?>
