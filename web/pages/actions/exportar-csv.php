<?php
// ============================================================
// pages/actions/exportar-csv.php — exporta movimentações da ONG em CSV
// projeto ONGHelp · Projeto Integrador 2026
// ============================================================
//
// parâmetros GET:
//   ong  → ID da ONG (inteiro, obrigatório)
//   ano  → Ano a filtrar (inteiro, opcional; padrão: ano atual)
//   ini  → Data inicial do período (Y-m-d, opcional)
//   fim  → Data final do período (Y-m-d, opcional)
//
// com ini E fim válidos, exporta apenas o intervalo informado
// (modo período), sem eles, mantém o comportamento por ano
//
// ordem DAS linhas — do mais antigo para o mais novo:
//   o CSV é um documento de prestação de contas, não um feed,
//   a ordem é crescente por dois motivos:
//     1. Bate com o relatório anual (relatorio-anual.php), que
//        exporta o mesmo conjunto de dados em ordem crescente,
//        os dois botões ficam lado a lado no rodapé do modal
//        do Portal de Transparência — se divergissem, quem
//        cruzasse os dois arquivos leria a mesma movimentação
//        em posições conflitantes
//     2. no modo período, o formulário pede "data inicial" e
//        "data final", abrir o arquivo de trás para frente
//        contradiz o intervalo que o próprio usuário informou
//
//   já as tabelas na tela (transparencia.php, perfil-publico.php,
//   gestao.php) mantêm DESC de propósito: os rótulos delas são
//   "Últimas movimentações" e "Movimentações recentes", onde a
//   recência é justamente o que se quer ver primeiro
//
//   o desempate é id ASC: dois lançamentos na mesma data saem na
//   ordem em que foram cadastrados, que é o que um livro-razão
//   espera, com id DESC eles sairiam invertidos sem motivo
//
// colunas: data, descrição, tipo, categoria, projeto, valor e o
// endereço do comprovante (quando a ONG anexou um)
//
// retorna um arquivo CSV para download,
// não exibe nenhum HTML — apenas headers + dados
// ============================================================

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/modules/gestao.php';

// ── valida parâmetros ────────────────────────────────────────
$ongId = isset($_GET['ong']) && ctype_digit($_GET['ong']) ? (int) $_GET['ong'] : 0;
$ano   = isset($_GET['ano']) && ctype_digit($_GET['ano']) ? (int) $_GET['ano'] : (int) date('Y');

if ($ongId <= 0) {
    http_response_code(400);
    exit('Parâmetro inválido.');
}
// ano fora do intervalo cai no ano atual, como no relatório em PDF
if ($ano < 2000 || $ano > 2100) {
    $ano = (int) date('Y');
}

// ── período opcional (data inicial e final) ──────────────────
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

// ── verifica se a ONG existe e está ativa ────────────────────
$stmtOng = $pdo->prepare("SELECT nome FROM usuarios WHERE id = ? AND status = 'aprovada'");
$stmtOng->execute([$ongId]);
$ong = $stmtOng->fetch();

if (!$ong) {
    http_response_code(404);
    exit('ONG não encontrada.');
}

// ── busca as movimentações do recorte solicitado ──────────────
if ($periodo) {
    $stmt = $pdo->prepare("
        SELECT m.data_mov, m.descricao, m.tipo, m.valor, m.categoria, m.comprovante, p.nome AS projeto_nome
        FROM   movimentacoes m
        LEFT   JOIN projetos p ON p.id = m.projeto_id
        WHERE  m.usuario_id = ?
          AND  m.data_mov >= ?
          AND  m.data_mov <= ?
        ORDER  BY m.data_mov ASC, m.id ASC
    ");
    $stmt->execute([$ongId, $ini, $fim]);
    $rotuloPeriodo = $ini . '-a-' . $fim;
} else {
    $stmt = $pdo->prepare("
        SELECT m.data_mov, m.descricao, m.tipo, m.valor, m.categoria, m.comprovante, p.nome AS projeto_nome
        FROM   movimentacoes m
        LEFT   JOIN projetos p ON p.id = m.projeto_id
        WHERE  m.usuario_id = ?
          AND  m.data_mov BETWEEN ? AND ?
        ORDER  BY m.data_mov ASC, m.id ASC
    ");
    $stmt->execute([$ongId, "$ano-01-01", "$ano-12-31"]);
    $rotuloPeriodo = (string) $ano;
}
$movs = $stmt->fetchAll();

// ── monta nome do arquivo seguro ─────────────────────────────
// remove caracteres especiais do nome da ONG para o filename
$nomeSeguro = preg_replace('/[^a-z0-9]+/i', '-', $ong['nome']);
$nomeSeguro = trim($nomeSeguro, '-');
$filename   = "transparencia-{$nomeSeguro}-{$rotuloPeriodo}.csv";

// ── envia headers de download ─────────────────────────────────
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store');

// BOM UTF-8 para o Excel abrir acentuação corretamente
echo "\xEF\xBB\xBF";

// ── escreve CSV ──────────────────────────────────────────────
$out = fopen('php://output', 'w');

// texto digitado pela ONG que começa com = + - @ vira fórmula no Excel e
// no LibreOffice, mesmo entre aspas, o apóstrofo na frente desliga isso
function celulaCsv(string $texto): string
{
    return preg_match('/^[=+\-@\t\r]/', $texto) ? "'" . $texto : $texto;
}

// cabeçalho
fputcsv($out, ['Data', 'Descrição', 'Tipo', 'Categoria', 'Projeto', 'Valor (R$)', 'Comprovante'], ';');

foreach ($movs as $mov) {
    $comprovante = comprovanteUrl($mov['comprovante'] ?? null, '');

    fputcsv($out, [
        date('d/m/Y', strtotime($mov['data_mov'])),
        celulaCsv($mov['descricao']),
        ucfirst($mov['tipo']),
        rotuloCategoria($mov['tipo'], (string) $mov['categoria']),
        celulaCsv((string) ($mov['projeto_nome'] ?? '')),
        number_format((float) $mov['valor'], 2, ',', '.'),
        $comprovante !== '' ? urlAbsoluta($comprovante) : '',
    ], ';');
}

fclose($out);
exit;
