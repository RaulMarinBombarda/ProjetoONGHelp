<?php
// ============================================================
// pages/termos.php — Termos de Uso do ONGHelp
// Projeto ONGHelp · Projeto Integrador 2026
// ============================================================

$page_title = 'Termos de Uso';
$active_nav = '';
include '../includes/header.php';
?>

<section class="legal-page">
  <div class="container">

    <div class="page-header">
      <span class="badge-secao">Legal</span>
      <h1 class="page-title">Termos de Uso</h1>
    </div>

    <div class="card">
      <p>
        Ao utilizar a plataforma ONGHelp, você concorda com as condições abaixo.
      </p>

      <h6>1. Do serviço</h6>
      <p>
        O ONGHelp é uma plataforma gratuita de gestão e transparência para
        organizações sem fins lucrativos. O uso é permitido apenas para fins
        lícitos e relacionados à atividade da organização cadastrada. A plataforma
        não processa, custodia nem concilia doações: o PIX é feito diretamente
        entre o doador e a ONG, no aplicativo do banco.
      </p>

      <h6>2. Do cadastro e da verificação</h6>
      <p>
        O cadastro exige dados verdadeiros (nome, CNPJ, e-mail, responsável legal,
        cargo e canal público). Todo cadastro nasce como <strong>pendente</strong>
        e só fica público após análise manual da Equipe ONGHelp. A aprovação pode
        ser recusada ou suspensa se houver informação falsa, CNPJ incompatível,
        risco de impersonação ou uso inadequado.
      </p>
      <p>
        O selo “Verificada em dd/mm/aaaa” indica que o cadastro foi conferido
        manualmente. Ele <strong>não é auditoria financeira</strong> e não garante
        que os valores declarados pela ONG sejam exatos.
      </p>

      <h6>3. Da responsabilidade pelos dados</h6>
      <p>
        As informações inseridas (movimentações, projetos e descrições) são de
        responsabilidade da própria organização e são autodeclaradas. O ONGHelp
        não audita as movimentações, mas garante que o que for registrado fique
        público e rastreável.
      </p>

      <h6>4. Do PIX</h6>
      <p>
        A chave PIX cadastrada deve ser obrigatoriamente o CNPJ da própria
        organização, sem máscara, e precisa ser igual ao CNPJ cadastrado. Depois
        da aprovação, a chave fica bloqueada para alteração pela ONG; correções
        passam pela Equipe ONGHelp. O QR Code exibido é sempre gerado a partir
        dessa chave verificada — não há upload de imagem de QR Code.
      </p>

      <h6>5. Das notícias publicadas pela ONG</h6>
      <p>
        A organização é a única responsável pelo conteúdo que publica na área
        de notícias: títulos, resumos, textos, fotos, legendas, links, datas e
        vínculos com projetos ou movimentações. O ONGHelp não faz revisão
        prévia nem auditoria desse material. A equipe pode ocultar ou remover
        uma notícia que viole estes termos, com registro da data e do motivo,
        e a ONG pode contestar pelo e-mail da equipe.
      </p>
      <p>
        Ao publicar imagens, a ONG declara ter autorização de uso de imagem de
        todas as pessoas retratadas. Fotos de crianças e adolescentes só podem
        ser publicadas com o consentimento dos responsáveis legais e sem
        exposição de dados pessoais. A ONG responde pela licença, pela
        veracidade e pelo uso adequado de cada arquivo enviado.
      </p>
      <p>
        É proibido usar as notícias para pedir dinheiro por PIX, transferência
        bancária, boleto ou link de pagamento, bem como divulgar chaves PIX ou
        dados bancários no conteúdo. O ONGHelp não processa, custodia nem
        intermedeia doações. Conteúdo com pedido de transferência ou chave PIX
        será recusado ou removido.
      </p>
      <p>
        Qualquer pessoa pode denunciar uma notícia à Equipe ONGHelp pelos
        canais oficiais. A denúncia é analisada e pode resultar em ocultação
        preventiva, remoção definitiva ou manutenção do conteúdo, com
        comunicação à ONG quando cabível.
      </p>

      <h6>6. Do encerramento</h6>
      <p>
        A organização pode encerrar sua conta a qualquer momento. Todos os
        dados vinculados são removidos permanentemente. A Equipe ONGHelp também
        pode suspender um perfil preventivamente, com registro do motivo.
      </p>

      <h6>7. Das alterações</h6>
      <p>
        Estes termos podem ser atualizados conforme a evolução da plataforma.
        O uso contínuo após alterações representa aceitação das novas condições.
      </p>
    </div>

  </div>
</section>

<?php include '../includes/footer.php'; ?>
