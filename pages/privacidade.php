<?php
// ============================================================
// pages/privacidade.php — Política de Privacidade do ONGHelp
// Projeto ONGHelp · Projeto Integrador 2026
// ============================================================

$page_title = 'Política de Privacidade';
$active_nav = '';
include '../includes/header.php';
?>

<section class="legal-page">
  <div class="container">

    <div class="page-header">
      <span class="badge-secao">Legal</span>
      <h1 class="page-title">Política de Privacidade</h1>
    </div>

    <div class="card">
      <p>
        Esta política descreve como o ONGHelp trata os dados das organizações
        cadastradas e das pessoas que as representam.
      </p>

      <h6>1. Dados coletados</h6>
      <p>
        Coletamos apenas os dados necessários ao funcionamento da plataforma:
        nome da organização, CNPJ, e-mail de contato, área de atuação, cidade,
        estado, descrição, chave PIX (sempre o CNPJ), nome e cargo do responsável
        legal, declaração de autorização e canal público (site, rede social ou
        telefone). Quando a ONG publica notícias, também são armazenados o
        conteúdo enviado, as fotos, legendas, links, a declaração de direitos
        de imagem, as datas de criação/publicação e eventuais registros de
        ocultação feitos pela equipe.
      </p>

      <h6>2. Dados públicos</h6>
      <p>
        Após a aprovação, tornam-se públicos o nome da organização, CNPJ, área,
        cidade/UF, descrição, projetos, movimentações financeiras, chave PIX e o
        canal público informado. O <strong>nome e o cargo do responsável legal
        não são exibidos publicamente</strong>: ficam visíveis apenas no painel
        restrito da Equipe ONGHelp.
      </p>

      <h6>3. Notícias, fotos e conteúdo enviado</h6>
      <p>
        As notícias publicadas pela ONG — incluindo textos, fotos, legendas,
        links e vínculos com projetos ou movimentações — ficam visíveis
        publicamente enquanto estiverem no estado “publicada”. A ONG é
        responsável pelo conteúdo e declara ter autorização de uso de imagem.
        Fotos de crianças e adolescentes só devem ser enviadas com o
        consentimento dos responsáveis legais e sem exposição de dados
        pessoais.
      </p>
      <p>
        Notícias ocultadas pela equipe deixam de ser exibidas publicamente, mas
        permanecem armazenadas com a data e o motivo da ocultação para
        segurança, histórico e atendimento de denúncias. A ONG pode contestar
        a decisão pelos canais oficiais.
      </p>
      <p>
        Denúncias sobre notícias podem ser enviadas à Equipe ONGHelp. Para
        analisar o relato, registramos o motivo, o detalhe informado, a data e
        o endereço IP de origem. Esses dados são usados apenas para moderação
        e segurança da plataforma.
      </p>

      <h6>4. Verificação e selo</h6>
      <p>
        A verificação é manual e registra a data de aprovação e de verificação.
        O selo indica conferência cadastral, não auditoria financeira. Os dados
        financeiros são autodeclarados pela própria ONG.
      </p>

      <h6>5. Senha e segurança</h6>
      <p>
        As senhas são armazenadas com hash e nunca são expostas. O acesso usa
        proteção CSRF, limite de tentativas de login, timeout de sessão e
        cabeçalhos de segurança. Ações administrativas são registradas em log
        com data, ação, alvo e IP.
      </p>

      <h6>6. Exclusão de dados</h6>
      <p>
        Ao encerrar a conta, todos os dados da organização são removidos
        permanentemente da plataforma (LGPD — direito ao apagamento). Registros
        de log administrativo podem permanecer pelo tempo necessário à segurança
        e à prestação de contas.
      </p>

      <h6>7. Contato</h6>
      <p>
        Em caso de dúvidas sobre esta política ou para solicitar correção de
        dados, entre em contato com a Equipe ONGHelp pelo e-mail
        <a href="mailto:equipe.onghelp@gmail.com">equipe.onghelp@gmail.com</a>.
      </p>
    </div>

  </div>
</section>

<?php include '../includes/footer.php'; ?>
