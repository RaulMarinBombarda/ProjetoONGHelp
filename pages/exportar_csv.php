<?php
// ============================================================
// pages/exportar_csv.php — Exporta movimentações da ONG em CSV
// Projeto ONGHelp · Projeto Integrador 2026
// ============================================================
//
// Parâmetros GET:
//   ong  → ID da ONG (inteiro, obrigatório)
//   ano  → Ano a filtrar (inteiro, opcional; padrão: ano atual)
//   ini  → Data inicial do período (Y-m-d, opcional)
//   fim  → Data final do período (Y-m-d, opcional)
//
// Com ini E fim válidos, exporta apenas o intervalo informado
// (modo período). Sem eles, mantém o comportamento por ano.
//
// ORDEM DAS LINHAS — do mais ANTIGO para o mais novo:
//   O CSV é um documento de prestação de contas, não um feed.
//   A ordem é crescente por dois motivos:
//     1. Bate com o relatório anual (relatorio_anual.php), que
//        exporta o MESMO conjunto de dados em ordem crescente.
//        Os dois botões ficam lado a lado no rodapé do modal
//        do Portal de Transparência — se divergissem, quem
//        cruzasse os dois arquivos leria a mesma movimentação
//        em posições conflitantes.
//     2. No modo período, o formulário pede "data inicial" e
//        "data final". Abrir o arquivo de trás para frente
//        contradiz o intervalo que o próprio usuário informou.
//
//   Já as tabelas na tela (transparencia.php, perfilpublico.php,
//   gestao.php) mantêm DESC de propósito: os rótulos delas são
//   "Últimas movimentações" e "Movimentações recentes", onde a
//   recência é justamente o que se quer ver primeiro.
//
//   O desempate é id ASC: dois lançamentos na mesma data saem na
//   ordem em que foram cadastrados, que é o que um livro-razão
//   espera. Com id DESC eles sairiam invertidos sem motivo.
//
// Retorna um arquivo CSV para download.
// Não exibe nenhum HTML — apenas headers + dados.
// ============================================================

require_once '../includes/db.php';

// ── Valida parâmetros ────────────────────────────────────────
$ongId = isset($_GET['ong']) && ctype_digit($_GET['ong']) ? (int) $_GET['ong'] : 0;
$ano   = isset($_GET['ano']) && ctype_digit($_GET['ano']) ? (int) $_GET['ano'] : (int) date('Y');

if ($ongId <= 0) {
    http_response_code(400);
    exit('Parâmetro inválido.');
}

// ── Período opcional (data inicial e final) ──────────────────
$ini     = trim($_GET['ini'] ?? '');
$fim     = trim($_GET['fim'] ?? '');
$periodo = false;

if ($ini !== '' || $fim !== '') {
    $dIni = DateTime::createFromFormat('Y-m-d', $ini);
    $dFim = DateTime::createFromFormat('Y-m-d', $fim);

    if (!$dIni || !$dFim
        || $dIni->format('Y-m-d') !== $ini
        || $dFim->format('Y-m-d') !== $fim
        || $ini > $fim) {
        http_response_code(400);
        exit('Período inválido. Use o formato AAAA-MM-DD e data inicial anterior à final.');
    }
    $periodo = true;
}

// ── Verifica se a ONG existe e está ativa ────────────────────
$stmtOng = $pdo->prepare("SELECT nome FROM usuarios WHERE id = ? AND status = 'aprovada'");
$stmtOng->execute([$ongId]);
$ong = $stmtOng->fetch();

if (!$ong) {
    http_response_code(404);
    exit('ONG não encontrada.');
}

// ── Busca as movimentações do recorte solicitado ──────────────
if ($periodo) {
    $stmt = $pdo->prepare("
        SELECT data_mov, descricao, tipo, valor
        FROM   movimentacoes
        WHERE  usuario_id = ?
          AND  data_mov >= ?
          AND  data_mov <= ?
        ORDER  BY data_mov ASC, id ASC
    ");
    $stmt->execute([$ongId, $ini, $fim]);
    $rotuloPeriodo = $ini . '-a-' . $fim;
} else {
    $stmt = $pdo->prepare("
        SELECT data_mov, descricao, tipo, valor
        FROM   movimentacoes
        WHERE  usuario_id = ?
          AND  YEAR(data_mov) = ?
        ORDER  BY data_mov ASC, id ASC
    ");
    $stmt->execute([$ongId, $ano]);
    $rotuloPeriodo = (string) $ano;
}
$movs = $stmt->fetchAll();

// ── Monta nome do arquivo seguro ─────────────────────────────
// Remove caracteres especiais do nome da ONG para o filename
$nomeSeguro = preg_replace('/[^a-z0-9]+/i', '-', $ong['nome']);
$nomeSeguro = trim($nomeSeguro, '-');
$filename   = "transparencia-{$nomeSeguro}-{$rotuloPeriodo}.csv";

// ── Envia headers de download ─────────────────────────────────
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store');

// BOM UTF-8 para o Excel abrir acentuação corretamente
echo "\xEF\xBB\xBF";

// ── Escreve CSV ──────────────────────────────────────────────
$out = fopen('php://output', 'w');

// Cabeçalho
fputcsv($out, ['Data', 'Descrição', 'Tipo', 'Valor (R$)'], ';');

foreach ($movs as $mov) {
    fputcsv($out, [
        date('d/m/Y', strtotime($mov['data_mov'])),
        $mov['descricao'],
        ucfirst($mov['tipo']),
        number_format((float) $mov['valor'], 2, ',', '.'),
    ], ';');
}

fclose($out);
exit;
