<?php
// ============================================================
// pages/relatorio_anual.php — Relatório anual da ONG em PDF
// Projeto ONGHelp · Projeto Integrador 2026
// ============================================================
//
// PARÂMETROS GET:
//   ?ong=42    → ID da ONG (obrigatório)
//   ?ano=2026  → Ano de referência (opcional; padrão: ano atual)
//
// GERA UM PDF A4 DE DUAS PÁGINAS:
//   1. Capa e balancete → identificação da ONG, quatro indicadores
//                         do ano e o balancete de 12 meses
//   2. Movimentações e projetos → lista do ano, total do ano e os
//                         projetos com seus indicadores
//
// A escolha por duas páginas é deliberada. Um relatório anual não
// precisa ser um documento: precisa da identificação de quem
// prestou contas, dos quatro números do ano, do balancete mensal e
// das lançamentos que os compõem. Gráfico, seções separadas e
// caixa de legendas sao refinamento de relatório — nao sao o
// demonstrativo.
//
// A tabela de movimentações pagina sozinha se o ano for muito
// cheio (o cabeçalho se repete a cada quebra), então uma ONG com
// mais de ~30 lançamentos no ano vai para uma terceira página.
//
// Os números vêm da MESMA função do Portal de Transparência
// (balanceteDoAno em includes/helpers.php) e saem na MESMA ordem
// do CSV (data_mov ASC, id ASC). Tela, CSV e PDF precisam concordar
// porque o leitor pode cruzar os três.
//
// O PDF é gerado por includes/pdf.php, sem nenhuma biblioteca
// externa.
// ============================================================

require_once '../includes/db.php';
require_once '../includes/helpers.php';
require_once '../includes/pdf.php';

// ─────────────────────────────────────────────────────────────
// 1. PARÂMETROS E VALIDAÇÃO
// ─────────────────────────────────────────────────────────────

$ongId = isset($_GET['ong']) && ctype_digit($_GET['ong']) ? (int) $_GET['ong'] : 0;
$ano   = isset($_GET['ano']) && ctype_digit($_GET['ano']) ? (int) $_GET['ano'] : (int) date('Y');

if ($ongId <= 0) {
    http_response_code(400);
    exit('Parâmetro inválido.');
}
if ($ano < 2000 || $ano > 2100) {
    $ano = (int) date('Y');
}

// ─────────────────────────────────────────────────────────────
// 2. DADOS
// ─────────────────────────────────────────────────────────────

$stmtOng = $pdo->prepare("SELECT * FROM usuarios WHERE id = ? AND status = 'aprovada'");
$stmtOng->execute([$ongId]);
$ong = $stmtOng->fetch();

if (!$ong) {
    http_response_code(404);
    exit('ONG não encontrada.');
}

// Movimentações do ano em ordem cronológica (igual ao CSV)
$stmtMov = $pdo->prepare("
    SELECT tipo, descricao, valor, data_mov
    FROM   movimentacoes
    WHERE  usuario_id = ?
      AND  YEAR(data_mov) = ?
    ORDER  BY data_mov ASC, id ASC
");
$stmtMov->execute([$ongId, $ano]);
$movs = $stmtMov->fetchAll();

// Totais mensais → balancete (mesma fonte da tela)
$mensal = [];
foreach ($movs as $mv) {
    $mes = (int) date('n', strtotime($mv['data_mov']));
    $mensal[$mv['tipo']][$mes] = ($mensal[$mv['tipo']][$mes] ?? 0.0) + (float) $mv['valor'];
}
$balancete = balanceteDoAno($mensal);

$stmtProj = $pdo->prepare("
    SELECT nome, indicador, unidade
    FROM   projetos
    WHERE  usuario_id = ?
    ORDER  BY nome ASC
");
$stmtProj->execute([$ongId]);
$projetos = $stmtProj->fetchAll();

$mesesNome = [
    1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
    5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
    9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
];

// ─────────────────────────────────────────────────────────────
// 3. PALETA — as mesmas cores do design system (global.css)
// ─────────────────────────────────────────────────────────────

$AZUL   = '#446e87';
$VERDE  = '#00915E';
$VERMEL = '#C0392B';
$BORDA  = '#72a3bf';
$CONTORNO = '#72a3bf';
$TEXTO  = '#030f18';
$SUAVE  = '#1d4052';
$FUNDO  = '#e0e8e6';

// ─────────────────────────────────────────────────────────────
// 4. MONTAGEM DO DOCUMENTO
// ─────────────────────────────────────────────────────────────

$pdf = new PdfRelatorio();

$localOng = trim(($ong['cidade'] ?: 'Brasil') . ($ong['estado'] ? '/' . $ong['estado'] : ''));
$saldo    = $balancete['total_saldo'];
$larguraUtil = PdfRelatorio::LARGURA_PAGINA - 2 * PdfRelatorio::MARGEM;

$pdf->definirRodape('ONGHelp · Relatório Anual · ' . $ong['nome']);

/** Título de bloco dentro da página corrente (não força quebra). */
$bloco = function (string $txt) use ($pdf, $TEXTO) {
    $pdf->garantirEspaco(30);
    $pdf->texto($txt, PdfRelatorio::MARGEM, $pdf->posicao() + 11, 11, true, $TEXTO);
    $pdf->avancar(24);
};

/* ============================================================
   PÁGINA 1 — CAPA E BALANCETE
   ============================================================ */

$pdf->faixaTitulo(
    'Relatório Anual de Transparência',
    $ong['nome'],
    $AZUL,
    'PORTAL DE TRANSPARÊNCIA · ' . $ano
);

// ── Identificação da ONG ──
$alturaIdent = 84;
$pdf->retangulo(PdfRelatorio::MARGEM, $pdf->posicao(), $larguraUtil, $alturaIdent, '#e0e8e6', $BORDA, 0.7);

$identEsq = [
    'CNPJ'            => mascaraCnpj($ong['cnpj']),
    'Localização'     => $localOng,
    'Área de atuação' => $ong['area'],
];
$identDir = [
    'Ano de referência' => (string) $ano,
    'Emitido em'        => date('d/m/Y') . ' às ' . date('H:i'),
    'Projetos ativos'   => count($projetos) . ' cadastrado' . (count($projetos) === 1 ? '' : 's'),
];

foreach ([PdfRelatorio::MARGEM + 16, PdfRelatorio::MARGEM + 290] as $i => $xCol) {
    $y = $pdf->posicao() + 20;
    foreach ([$identEsq, $identDir][$i] as $rotulo => $valor) {
        $pdf->texto(strtoupper($rotulo), $xCol, $y, 6.8, true, '#1d4052');
        $pdf->texto($valor, $xCol, $y + 13, 9.5, true, $TEXTO);
        $y += 24;
    }
}

$pdf->avancar($alturaIdent + 20);

// ── Quatro indicadores do ano ──
$gapCartoes     = 10;
$larguraCartoes = ($larguraUtil - $gapCartoes * 3) / 4;
$xCartao        = PdfRelatorio::MARGEM;
$yCartao        = $pdf->posicao();

$pdf->cartao('Total de receitas', fmtDinheiro($balancete['total_receita']), $VERDE, $xCartao, $yCartao, $larguraCartoes);
$xCartao += $larguraCartoes + $gapCartoes;
$pdf->cartao('Total de despesas', fmtDinheiro($balancete['total_despesa']), $VERMEL, $xCartao, $yCartao, $larguraCartoes);
$xCartao += $larguraCartoes + $gapCartoes;
$pdf->cartao('Saldo do ano', ($saldo >= 0 ? '+ ' : '- ') . fmtDinheiro(abs($saldo)),
            $saldo >= 0 ? $VERDE : $VERMEL, $xCartao, $yCartao, $larguraCartoes);
$xCartao += $larguraCartoes + $gapCartoes;
$pdf->cartao('Movimentações', number_format(count($movs), 0, ',', '.'), $AZUL, $xCartao, $yCartao, $larguraCartoes);

$pdf->avancar(62 + 24);

// ── Balancete mensal ──
$bloco('Balancete de ' . $ano);

$colunasBalancete = [
    ['titulo' => 'Mês',             'w' => 90,  'al' => 'E'],
    ['titulo' => 'Receitas',        'w' => 105, 'al' => 'D'],
    ['titulo' => 'Despesas',        'w' => 105, 'al' => 'D'],
    ['titulo' => 'Saldo do mês',    'w' => 105, 'al' => 'D'],
    ['titulo' => 'Saldo acumulado', 'w' => 106, 'al' => 'D'],
];

$linhasBalancete = [];
foreach ($balancete['linhas'] as $mes => $lb) {
    $linhasBalancete[] = [
        'c' => [
            $mesesNome[$mes],
            fmtDinheiro($lb['receita']),
            fmtDinheiro($lb['despesa']),
            ($lb['saldo'] > 0 ? '+ ' : ($lb['saldo'] < 0 ? '- ' : '')) . fmtDinheiro(abs($lb['saldo'])),
            ($lb['acumulado'] > 0 ? '+ ' : ($lb['acumulado'] < 0 ? '- ' : '')) . fmtDinheiro(abs($lb['acumulado'])),
        ],
        'est' => $lb['semMov'] ? 'vazio' : 'normal',
    ];
}
$linhasBalancete[] = [
    'c' => [
        'Total do ano',
        fmtDinheiro($balancete['total_receita']),
        fmtDinheiro($balancete['total_despesa']),
        ($balancete['total_saldo'] >= 0 ? '+ ' : '- ') . fmtDinheiro(abs($balancete['total_saldo'])),
        ($balancete['acumulado_final'] >= 0 ? '+ ' : '- ') . fmtDinheiro(abs($balancete['acumulado_final'])),
    ],
    'est' => 'total',
];

$pdf->tabela($colunasBalancete, $linhasBalancete, [
    'altura' => 19,
    'corCol' => [1 => $VERDE, 2 => $VERMEL, 3 => $TEXTO, 4 => $TEXTO],
]);

// ── Nota de leitura ──
$pdf->avancar(14);
$pdf->garantirEspaco(58);
$pdf->retangulo(PdfRelatorio::MARGEM, $pdf->posicao(), $larguraUtil, 46, $FUNDO, $BORDA, 0.6);
$pdf->texto('Observações', PdfRelatorio::MARGEM + 14, $pdf->posicao() + 16, 8, true, $TEXTO);
$pdf->avancar(22);
$pdf->paragrafo(
    'Os valores são declarados pela própria organização no Portal de Transparência do ONGHelp; '
    . 'a plataforma não audita as movimentações. O saldo acumulado começa em janeiro de ' . $ano
    . ' e soma mês a mês.',
    7.8, $SUAVE, PdfRelatorio::MARGEM + 14, $larguraUtil - 28, 10
);

/* ============================================================
   PÁGINA 2 — MOVIMENTAÇÕES E PROJETOS
   ============================================================ */

$pdf->tituloPagina('Movimentações de ' . $ano);

$colunasMovs = [
    ['titulo' => 'Data',      'w' => 68,  'al' => 'C'],
    ['titulo' => 'Descrição', 'w' => 245, 'al' => 'E', 'quebra' => true],
    ['titulo' => 'Tipo',      'w' => 62,  'al' => 'C'],
    ['titulo' => 'Valor',     'w' => 136, 'al' => 'D'],
];

$linhasMovs = [];
$zebra      = false;
foreach ($movs as $mv) {
    $ehReceita = ($mv['tipo'] === 'receita');
    $zebra     = !$zebra;

    $linhasMovs[] = [
        'c' => [
            date('d/m/Y', strtotime($mv['data_mov'])),
            $mv['descricao'],
            $ehReceita ? 'Receita' : 'Despesa',
            ($ehReceita ? '+ ' : '- ') . fmtDinheiro((float) $mv['valor']),
        ],
        'est'   => $zebra ? 'zebra' : 'normal',
        'cores' => [3 => $ehReceita ? $VERDE : $VERMEL],
    ];
}

// Os totais fecham a leitura do ano
$linhasMovs[] = [
    'c'    => ['Total de receitas', '', '', fmtDinheiro($balancete['total_receita'])],
    'est'  => 'total',
    'cores' => [3 => $VERDE],
];
$linhasMovs[] = [
    'c'    => ['Total de despesas', '', '', fmtDinheiro($balancete['total_despesa'])],
    'est'  => 'total',
    'cores' => [3 => $VERMEL],
];

$pdf->tabela($colunasMovs, $linhasMovs, [
    'altura' => 17,
    'quebra' => 2,
    'corCol' => [1 => $TEXTO, 2 => $SUAVE],
]);

// ── Projetos ──
$pdf->avancar(6);
$bloco('Projetos');

if (empty($projetos)) {
    $pdf->garantirEspaco(40);
    $pdf->texto('Nenhum projeto cadastrado.', PdfRelatorio::MARGEM, $pdf->posicao() + 10,
                9.5, false, $SUAVE, 'C', $larguraUtil);
} else {
    $colunasProj = [
        ['titulo' => 'Projeto',           'w' => 235, 'al' => 'E', 'quebra' => true],
        ['titulo' => 'Indicador',         'w' => 110, 'al' => 'D'],
        ['titulo' => 'Unidade de medida', 'w' => 166, 'al' => 'E'],
    ];

    $linhasProj = [];
    $zebra = false;
    foreach ($projetos as $proj) {
        $zebra = !$zebra;
        $linhasProj[] = [
            'c' => [
                $proj['nome'],
                number_format((int) $proj['indicador'], 0, ',', '.'),
                $proj['unidade'],
            ],
            'est' => $zebra ? 'zebra' : 'normal',
        ];
    }
    $linhasProj[] = [
        'c'   => [count($projetos) . ' projeto' . (count($projetos) === 1 ? '' : 's'), '', ''],
        'est' => 'total',
    ];

    $pdf->tabela($colunasProj, $linhasProj, [
        'altura' => 19,
        'quebra' => 2,
        'corCol' => [1 => $AZUL],
    ]);
}

/* ============================================================
   5 — ENVIO
   ============================================================ */

$nomeSeguro = preg_replace('/[^a-z0-9]+/i', '-', $ong['nome']);
$nomeSeguro = trim($nomeSeguro, '-');
$filename   = "relatorio-anual-{$nomeSeguro}-{$ano}.pdf";

$pdf->enviar($filename);
