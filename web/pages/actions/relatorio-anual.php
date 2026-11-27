<?php
// ============================================================
// pages/actions/relatorio-anual.php — relatório anual da ONG em PDF
// projeto ONGHelp · Projeto Integrador 2026
// ============================================================
//
// parâmetros GET:
//   ?ong=42    → ID da ONG (obrigatório)
//   ?ano=2026  → Ano de referência (opcional; padrão: ano atual)
//
// gera UM PDF A4 EM duas partes:
//   1. capa e balancete → identificação da ONG, quatro indicadores
//                         do ano e o balancete de 12 meses
//   2. movimentações, categorias e projetos → lista do ano com a
//                         categoria, total do ano, despesas por
//                         categoria e os projetos com situação e meta
//
// o relatório traz a identificação de quem prestou contas, os
// quatro números do ano, o balancete mensal e os lançamentos que
// os compõem, gráficos ficam no portal: no PDF, a tabela é o
// demonstrativo
//
// a tabela de movimentações pagina sozinha se o ano for muito
// cheio (o cabeçalho se repete a cada quebra), então uma ONG com
// mais de ~30 lançamentos no ano vai para uma terceira página
//
// os números vêm da mesma função do Portal de Transparência
// (balanceteDoAno em includes/core/helpers.php) e saem na mesma ordem
// do CSV (data_mov ASC, id ASC), tela, CSV e PDF precisam concordar
// porque o leitor pode cruzar os três
//
// o PDF é gerado por includes/modules/pdf.php, sem nenhuma biblioteca
// externa
// ============================================================

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/modules/pdf.php';
require_once __DIR__ . '/../../includes/modules/gestao.php';

// ─────────────────────────────────────────────────────────────
// 1. parâmetros e validação
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
// 2. dados
// ─────────────────────────────────────────────────────────────

$stmtOng = $pdo->prepare("SELECT * FROM usuarios WHERE id = ? AND status = 'aprovada'");
$stmtOng->execute([$ongId]);
$ong = $stmtOng->fetch();

if (!$ong) {
    http_response_code(404);
    exit('ONG não encontrada.');
}

// movimentações do ano em ordem cronológica (igual ao CSV)
$stmtMov = $pdo->prepare("
    SELECT tipo, categoria, descricao, valor, data_mov
    FROM   movimentacoes
    WHERE  usuario_id = ?
      AND  data_mov BETWEEN ? AND ?
    ORDER  BY data_mov ASC, id ASC
");
$stmtMov->execute([$ongId, "$ano-01-01", "$ano-12-31"]);
$movs = $stmtMov->fetchAll();

// totais mensais → balancete (mesma fonte da tela)
$mensal = [];
foreach ($movs as $mv) {
    $mes = (int) date('n', strtotime($mv['data_mov']));
    $mensal[$mv['tipo']][$mes] = ($mensal[$mv['tipo']][$mes] ?? 0.0) + (float) $mv['valor'];
}
$balancete = balanceteDoAno($mensal);

// despesas do ano por categoria, da maior para a menor (igual ao portal)
$despesasPorCategoria = [];
foreach ($movs as $mv) {
    if ($mv['tipo'] === 'despesa') {
        $despesasPorCategoria[$mv['categoria']] = ($despesasPorCategoria[$mv['categoria']] ?? 0.0) + (float) $mv['valor'];
    }
}
arsort($despesasPorCategoria);

// em andamento primeiro, como no perfil público
$projetos = projetosDaOng($pdo, $ongId);
$projetosEmAndamento = count(array_filter($projetos, static fn (array $p): bool => $p['status'] === 'andamento'));

$mesesNome = [
    1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
    5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
    9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
];

// ─────────────────────────────────────────────────────────────
// 3. paleta — as mesmas cores do design system (assets/css/base.css)
// ─────────────────────────────────────────────────────────────

$AZUL   = '#446e87';
$VERDE  = '#00915E';
$VERMEL = '#C0392B';
$BORDA  = '#72a3bf';
$TEXTO  = '#030f18';
$SUAVE  = '#1d4052';
$FUNDO  = '#e0e8e6';

// ─────────────────────────────────────────────────────────────
// 4. montagem DO documento
// ─────────────────────────────────────────────────────────────

$pdf = new PdfRelatorio();

$localOng = trim(($ong['cidade'] ?: 'Brasil') . ($ong['estado'] ? '/' . $ong['estado'] : ''));
$saldo    = $balancete['total_saldo'];
$larguraUtil = PdfRelatorio::LARGURA_PAGINA - 2 * PdfRelatorio::MARGEM;

$pdf->definirRodape('ONGHelp · Relatório Anual · ' . $ong['nome']);

/** título de bloco dentro da página corrente (não força quebra) */
$bloco = function (string $txt) use ($pdf) {
    $pdf->tituloSecao($txt);
};

/* ============================================================
   página 1 — capa e balancete
   ============================================================ */

$pdf->faixaTitulo(
    'Relatório Anual de Transparência',
    $ong['nome'],
    $AZUL,
    'PORTAL DE TRANSPARÊNCIA · ' . $ano
);

// ── identificação da ONG ──
$alturaIdent = 98;
$pdf->retangulo(PdfRelatorio::MARGEM, $pdf->posicao(), $larguraUtil, $alturaIdent, '#e0e8e6', $BORDA, 0.7);

$identEsq = [
    'CNPJ'            => mascaraCnpj($ong['cnpj']),
    'Localização'     => $localOng,
    'Área de atuação' => $ong['area'],
];
$identDir = [
    'Ano de referência' => (string) $ano,
    'Emitido em'        => date('d/m/Y') . ' às ' . date('H:i'),
    'Projetos ativos'   => $projetosEmAndamento . ' em andamento, ' . count($projetos) . ' no total',
];

foreach ([PdfRelatorio::MARGEM + 16, PdfRelatorio::MARGEM + 290] as $i => $xCol) {
    $y = $pdf->posicao() + 20;
    foreach ([$identEsq, $identDir][$i] as $rotulo => $valor) {
        $pdf->texto(mb_strtoupper($rotulo, 'UTF-8'), $xCol, $y, 6.6, true, '#446e87');
        $pdf->texto($valor, $xCol, $y + 12, 9.5, true, $TEXTO);
        $y += 27;
    }
}

$pdf->avancar($alturaIdent + 20);

// ── quatro indicadores do ano ──
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

// ── balancete mensal ──
$bloco('Balancete de ' . $ano);

$colunasBalancete = [
    ['titulo' => 'Mês',             'w' => 90,  'al' => 'E'],
    ['titulo' => 'Receitas',        'w' => 105, 'al' => 'D'],
    ['titulo' => 'Despesas',        'w' => 105, 'al' => 'D'],
    ['titulo' => 'Saldo do mês',    'w' => 105, 'al' => 'D'],
    ['titulo' => 'Saldo acumulado', 'w' => 106, 'al' => 'D'],
];

$linhasBalancete = [];
$valorOuTraco = static fn (float $v): string => $v == 0.0 ? '—' : fmtDinheiro($v);
foreach ($balancete['linhas'] as $mes => $lb) {
    $linhasBalancete[] = [
        'c' => [
            $mesesNome[$mes],
            $valorOuTraco((float) $lb['receita']),
            $valorOuTraco((float) $lb['despesa']),
            $lb['saldo'] == 0.0 ? '—' : (($lb['saldo'] > 0 ? '+ ' : '- ') . fmtDinheiro(abs($lb['saldo']))),
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

// ── nota de leitura ──
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
   página 2 — movimentações e projetos
   ============================================================ */

$pdf->tituloPagina('Movimentações de ' . $ano);

$colunasMovs = [
    ['titulo' => 'Data',      'w' => 62,  'al' => 'C'],
    ['titulo' => 'Descrição', 'w' => 170, 'al' => 'E', 'quebra' => true],
    ['titulo' => 'Categoria', 'w' => 112, 'al' => 'E', 'quebra' => true],
    ['titulo' => 'Tipo',      'w' => 55,  'al' => 'C'],
    ['titulo' => 'Valor',     'w' => 112, 'al' => 'D'],
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
            rotuloCategoria($mv['tipo'], (string) $mv['categoria']),
            $ehReceita ? 'Receita' : 'Despesa',
            ($ehReceita ? '+ ' : '- ') . fmtDinheiro((float) $mv['valor']),
        ],
        'est'   => $zebra ? 'zebra' : 'normal',
        'cores' => [4 => $ehReceita ? $VERDE : $VERMEL],
    ];
}

// os totais fecham a leitura do ano
$linhasMovs[] = [
    'c'    => ['Total de receitas', '', '', '', fmtDinheiro($balancete['total_receita'])],
    'est'  => 'total',
    'cores' => [4 => $VERDE],
];
$linhasMovs[] = [
    'c'    => ['Total de despesas', '', '', '', fmtDinheiro($balancete['total_despesa'])],
    'est'  => 'total',
    'cores' => [4 => $VERMEL],
];

$pdf->tabela($colunasMovs, $linhasMovs, [
    'altura' => 17,
    'quebra' => 2,
    'corCol' => [1 => $TEXTO, 2 => $SUAVE, 3 => $SUAVE],
]);

// ── para onde foi o dinheiro ──
if ($despesasPorCategoria) {
    $pdf->avancar(6);
    $bloco('Despesas de ' . $ano . ' por categoria');

    $linhasCategorias = [];
    $zebra = false;
    foreach ($despesasPorCategoria as $categoria => $total) {
        $zebra = !$zebra;
        $linhasCategorias[] = [
            'c'   => [
                rotuloCategoria('despesa', (string) $categoria),
                fmtDinheiro($total),
                $balancete['total_despesa'] > 0 ? round($total / $balancete['total_despesa'] * 100) . '%' : '—',
            ],
            'est' => $zebra ? 'zebra' : 'normal',
        ];
    }

    $pdf->tabela([
        ['titulo' => 'Categoria',          'w' => 291, 'al' => 'E'],
        ['titulo' => 'Valor',              'w' => 120, 'al' => 'D'],
        ['titulo' => 'Parte das despesas', 'w' => 100, 'al' => 'D'],
    ], $linhasCategorias, [
        'altura' => 17,
        'corCol' => [1 => $VERMEL],
    ]);
}

// ── projetos ──
$pdf->avancar(6);
$bloco('Projetos');

if (empty($projetos)) {
    $pdf->garantirEspaco(40);
    $pdf->texto('Nenhum projeto cadastrado.', PdfRelatorio::MARGEM, $pdf->posicao() + 10,
                9.5, false, $SUAVE, 'C', $larguraUtil);
} else {
    $colunasProj = [
        ['titulo' => 'Projeto',           'w' => 168, 'al' => 'E', 'quebra' => true],
        ['titulo' => 'Situação',          'w' => 78,  'al' => 'E'],
        ['titulo' => 'Indicador',         'w' => 70,  'al' => 'D'],
        ['titulo' => 'Meta',              'w' => 70,  'al' => 'D'],
        ['titulo' => 'Unidade de medida', 'w' => 125, 'al' => 'E', 'quebra' => true],
    ];

    $linhasProj = [];
    $zebra = false;
    foreach ($projetos as $proj) {
        $zebra = !$zebra;
        $linhasProj[] = [
            'c' => [
                $proj['nome'],
                rotuloStatusProjeto($proj['status']),
                number_format((int) $proj['indicador'], 0, ',', '.'),
                $proj['meta'] !== null ? number_format((int) $proj['meta'], 0, ',', '.') : '—',
                $proj['unidade'],
            ],
            'est' => $zebra ? 'zebra' : 'normal',
        ];
    }
    $linhasProj[] = [
        'c'   => [count($projetos) . ' projeto' . (count($projetos) === 1 ? '' : 's'), '', '', '', ''],
        'est' => 'total',
    ];

    $pdf->tabela($colunasProj, $linhasProj, [
        'altura' => 19,
        'quebra' => 2,
        'corCol' => [2 => $AZUL],
    ]);
}

/* ============================================================
   5 — envio
   ============================================================ */

$nomeSeguro = preg_replace('/[^a-z0-9]+/i', '-', $ong['nome']);
$nomeSeguro = trim($nomeSeguro, '-');
$filename   = "relatorio-anual-{$nomeSeguro}-{$ano}.pdf";

$pdf->enviar($filename);
