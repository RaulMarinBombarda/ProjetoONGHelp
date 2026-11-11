<?php
// ============================================================
// includes/pdf.php — Gerador de PDF em PHP puro
// Projeto ONGHelp · Projeto Integrador 2026
// ============================================================
//
// POR QUE ESTE ARQUIVO EXISTE:
//   O relatório anual precisa sair em PDF e o projeto não usa
//   Composer nem bibliotecas externas — a mesma premissa que
//   levou o relatório anterior a ser SpreadsheetML em vez de
//   .xlsx. Este arquivo escreve a estrutura do PDF na mão:
//   catálogo, árvore de páginas, fontes e streams de conteúdo.
//
//   A técnica é usar apenas as 14 fontes padrão do formato
//   (Helvetica aqui), com WinAnsiEncoding. Nenhuma fonte é
//   embutida no arquivo, o que mantém o PDF pequeno e o
//   resultado idêntico em qualquer leitor. Acentuação do
//   português é nativa do encoding — não há substituição.
//
// SUPORTA:
//   · A4 retrato, margens e quebra de página automática
//   · Helvetica normal e negrito
//   · Texto alinhado à esquerda, centro ou direita
//   · Retângulos preenchidos, linhas e barras
//   · Medição real da largura do texto (para alinhar números
//     e quebrar linha) a partir das larguras oficiais AFM
//   · Tabelas com cabeçalho repetido a cada página e altura
//     de linha variável (quebra de texto em até 3 linhas)
//   · Gráfico de barras agrupadas desenhado com retângulos
//   · Rodapé e numeração de página "Página X de Y"
//
// NÃO SUPORTA (e não é necessário aqui):
//   Imagens, gráficos vetoriais, PDF/A, assinatura digital,
//   compressão de stream e hyperlinks internos.
//
// USO:
//
//   $pdf = new PdfRelatorio();
//   $pdf->faixaTitulo('Relatório Anual', 'Nome da ONG');
//   $pdf->tabela($colunas, $linhas);
//   $pdf->enviar('relatorio.pdf');
//
// ============================================================

class PdfRelatorio
{
    // ── Geometria A4 (pontos) ─────────────────────────────────
    const LARGURA_PAGINA = 595;
    const ALTURA_PAGINA  = 842;
    const MARGEM         = 42;

    /** Altura reservada no rodapé (numeração + linha). */
    const RESERVA_RODAPE = 44;

    /** Larguras oficiais dos glifos, em milésimos de em (codes 32–126). */
    private static $larguraNormal = [
        278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,
        556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,
        1015,667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,
        667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,
        333,556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,
        556,556,333,500,278,556,500,722,500,500,500,334,260,334,584,
    ];

    private static $larguraNegrito = [
        278,333,474,556,556,889,722,238,333,333,389,584,278,333,278,278,
        556,556,556,556,556,556,556,556,556,556,333,333,584,584,584,611,
        975,722,722,722,722,667,611,778,722,278,556,722,611,833,722,778,
        667,778,722,667,611,722,667,944,667,667,611,333,278,333,584,556,
        333,556,611,556,611,556,333,611,611,278,278,556,278,889,611,611,
        611,611,389,556,333,611,556,778,556,556,500,389,280,389,584,
    ];

    /**
     * Letras acentuadas (WinAnsi) dobradas para o equivalente ASCII,
     * só para reaproveitar a tabela de larguras acima.
     */
    private static $dobrar = [
        0xC0=>'A',0xC1=>'A',0xC2=>'A',0xC3=>'A',0xC4=>'A',0xC5=>'A',0xC7=>'C',
        0xC8=>'E',0xC9=>'E',0xCA=>'E',0xCB=>'E',
        0xCC=>'I',0xCD=>'I',0xCE=>'I',0xCF=>'I',
        0xD1=>'N',
        0xD2=>'O',0xD3=>'O',0xD4=>'O',0xD5=>'O',0xD6=>'O',0xD8=>'O',
        0xD9=>'U',0xDA=>'U',0xDB=>'U',0xDC=>'U',0xDD=>'Y',0xDF=>'B',
        0xE0=>'a',0xE1=>'a',0xE2=>'a',0xE3=>'a',0xE4=>'a',0xE5=>'a',0xE7=>'c',
        0xE8=>'e',0xE9=>'e',0xEA=>'e',0xEB=>'e',
        0xEC=>'i',0xED=>'i',0xEE=>'i',0xEF=>'i',
        0xF1=>'n',
        0xF2=>'o',0xF3=>'o',0xF4=>'o',0xF5=>'o',0xF6=>'o',0xF8=>'o',
        0xF9=>'u',0xFA=>'u',0xFB=>'u',0xFC=>'u',0xFD=>'y',0xFF=>'y',
    ];

    /** Traços e aspas tipográficas com largura própria. */
    private static $larguraEspecial = [0x91=>333, 0x92=>333, 0x93=>556, 0x94=>556,
                                       0x95=>1000, 0x96=>556, 0x97=>1000, 0x85=>1000];

    // ── Estado ────────────────────────────────────────────────
    private $conteudo = '';   // stream da página em construção
    private $paginas  = [];   // streams já finalizados
    private $y        = 0;    // cursor vertical medido a partir do topo
    private $rodape   = '';   // texto do rodapé (preenchido em renderizar)

    /** Colunas da tabela em andamento, para repetir o cabeçalho. */
    private $tabelaColunas = [];
    private $tabelaAltura  = 18;

    public function __construct()
    {
        $this->iniciarPagina();
    }

    // =========================================================
    // BAIXO NÍVEL — primitivas de desenho
    // =========================================================

    /**
     * Converte #RRGGBB na notação de cor do PDF.
     *
     * IMPORTANTE: os operadores `rg` / `RG` do PDF recebem valores
     * NORMALIZADOS de 0.0 a 1.0 — não bytes de 0 a 255. Passar
     * inteiros faz o MuPDF saturar o componente em 1.0 e o
     * resultado fica branco. Por isso a divisão por 255 aqui.
     */
    public static function rgb(string $hex): string
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
            $hex = '030f18';
        }
        return sprintf('%.3F %.3F %.3F',
            hexdec(substr($hex, 0, 2)) / 255,
            hexdec(substr($hex, 2, 2)) / 255,
            hexdec(substr($hex, 4, 2)) / 255
        );
    }

    /**
     * Converte UTF-8 para WinAnsi (CP1252), que é o encoding das
     * fontes padrão do PDF. Preserva toda a acentuação do português.
     */
    public static function cp1252(string $texto): string
    {
        if ($texto === '') return '';
        if (function_exists('mb_convert_encoding')) {
            return (string) mb_convert_encoding($texto, 'Windows-1252', 'UTF-8');
        }
        if (function_exists('iconv')) {
            $r = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $texto);
            if ($r !== false) return $r;
        }
        return preg_replace('/[^\x20-\x7E]/', '?', $texto);
    }

    /** Largura do texto já em CP1252, em pontos. */
    public function larguraTexto(string $texto, float $tamanho, bool $negrito = false): float
    {
        $tabela = $negrito ? self::$larguraNegrito : self::$larguraNormal;
        $soma   = 0;
        $len    = strlen($texto);

        for ($i = 0; $i < $len; $i++) {
            $b = ord($texto[$i]);
            if ($b >= 32 && $b <= 126) {
                $soma += $tabela[$b - 32];
            } elseif (isset(self::$larguraEspecial[$b])) {
                $soma += self::$larguraEspecial[$b];
            } elseif (isset(self::$dobrar[$b])) {
                $c = ord(self::$dobrar[$b]);
                $soma += ($c >= 32 && $c <= 126) ? $tabela[$c - 32] : 556;
            } else {
                $soma += 556; // fallback para glifos fora da tabela
            }
        }
        return $soma * $tamanho / 1000;
    }

    /** Escapa os caracteres especiais de uma string literal PDF. */
    private function escapar(string $t): string
    {
        return str_replace(
            ['\\', '(', ')', "\r", "\n", "\t"],
            ['\\\\', '\\(', '\\)', ' ', ' ', ' '],
            $t
        );
    }

    /**
     * Desenha um texto. $yTop é a distância do topo da página até a
     * linha de base — o modelo mental do layout, já que o PDF cresce
     * para cima.
     *
     * O alinhamento 'C' e 'D' é medido dentro de uma CAIXA que começa
     * em $x, e não da página inteira. Sem isso, centralizar o rótulo de
     * uma coluna de tabela jogaria o texto para o meio da folha. Quando
     * $larguraCaixa é omitida, a caixa vai de $x até a margem direita.
     */
    public function texto(string $txt, float $x, float $yTop,
                          float $tamanho = 10, bool $negrito = false,
                          string $cor = '#030f18', string $alinha = 'E',
                          float $larguraCaixa = null): void
    {
        $t = self::cp1252($txt);
        if ($t === '') return;

        $largura = $this->larguraTexto($t, $tamanho, $negrito);
        $caixa   = $larguraCaixa ?? (self::LARGURA_PAGINA - self::MARGEM - $x);

        if ($alinha === 'C') {
            $x += ($caixa - $largura) / 2;
        } elseif ($alinha === 'D') {
            $x += ($caixa - $largura);
        }

        // Trava a escrita dentro da área útil
        if ($x < self::MARGEM) $x = self::MARGEM;
        $max = self::LARGURA_PAGINA - self::MARGEM;
        if ($x + $largura > $max) $x = max(self::MARGEM, $max - $largura);

        $this->conteudo .= sprintf(
            "BT /%s %.2F Tf %s rg 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n",
            $negrito ? 'F2' : 'F1',
            $tamanho,
            self::rgb($cor),
            $x,
            self::ALTURA_PAGINA - $yTop,
            $this->escapar($t)
        );
    }

    /** Retângulo preenchido, opcionalmente com borda. */
    public function retangulo(float $x, float $yTop, float $w, float $h,
                              string $preenchimento, string $borda = null,
                              float $espessura = 0.6): void
    {
        $y = self::ALTURA_PAGINA - $yTop - $h;

        if ($preenchimento !== '') {
            $this->conteudo .= sprintf("q %s rg %.2F %.2F %.2F %.2F re f Q\n",
                self::rgb($preenchimento), $x, $y, $w, $h);
        }
        if ($borda !== null && $borda !== '') {
            $this->conteudo .= sprintf("q %s RG %.2F w %.2F %.2F %.2F %.2F re S Q\n",
                self::rgb($borda), $espessura, $x, $y, $w, $h);
        }
    }

    /** Linha horizontal fina. */
    public function linha(float $x1, float $x2, float $yTop,
                          string $cor = '#72a3bf', float $espessura = 0.6): void
    {
        $y = self::ALTURA_PAGINA - $yTop;
        $this->conteudo .= sprintf("q %s RG %.2F w %.2F %.2F m %.2F %.2F l S Q\n",
            self::rgb($cor), $espessura, $x1, $y, $x2, $y);
    }

    // =========================================================
    // PÁGINAS
    // =========================================================

    private function iniciarPagina(): void
    {
        $this->conteudo = '';
        $this->y = 0;
    }

    private function finalizarPagina(): void
    {
        // Não fecha uma página que não recebeu nada — isso acontece
        // quando tituloPagina() é a primeira chamada e dispara uma
        // quebra sobre a página ainda sem conteúdo.
        if ($this->conteudo !== '') {
            // Fundo branco para dar contraste às faixas e cartões.
            $fundo = sprintf(
                "q %s rg 0 0 %.2F %.2F re f Q\n",
                self::rgb('#ffffff'),
                self::LARGURA_PAGINA,
                self::ALTURA_PAGINA
            );
            $this->paginas[] = $fundo . $this->conteudo;
        }
    }

    /** Force uma quebra de página. */
    public function novaPagina(): void
    {
        $this->finalizarPagina();
        $this->iniciarPagina();
        $this->y = self::MARGEM;

        // Se a quebra veio de dentro de uma tabela longa, redesenha o cabeçalho
        if (!empty($this->tabelaColunas)) {
            $this->y += 6;
            $this->cabecalhoTabela($this->tabelaColunas);
            $this->y += 4;
        }
    }

    /** Garante que restem $altura pontos livres; quebra a página se não. */
    public function garantirEspaco(float $altura): void
    {
        $limite = self::ALTURA_PAGINA - self::RESERVA_RODAPE;
        if ($this->y + $altura > $limite) {
            $this->novaPagina();
        }
    }

    /** Define o texto do rodapé (aparece à esquerda, em todas as páginas). */
    public function definirRodape(string $texto): void
    {
        $this->rodape = $texto;
    }

    /** Posição vertical atual do cursor, em pontos a partir do topo. */
    public function posicao(): float
    {
        return $this->y;
    }

    /** Desce o cursor em $delta pontos. */
    public function avancar(float $delta): void
    {
        $this->y += $delta;
    }

    /** Move o cursor para uma posição absoluta. */
    public function saltarPara(float $y): void
    {
        $this->y = $y;
    }

    // =========================================================
    // BLOCOS DE LAYOUT
    // =========================================================

    /**
     * Faixa colorida no topo da primeira página, com o título do
     * relatório e o nome da organização. Sem filete de destaque:
     * a faixa já é o elemento visual da capa.
     */
    public function faixaTitulo(string $titulo, string $subtitulo,
                                string $cor = '#446e87', string $tag = ''): void
    {
        $alt = 116;
        $this->retangulo(0, 0, self::LARGURA_PAGINA, $alt, $cor);

        $this->texto($tag, self::MARGEM, 44, 8, true, '#e0e8e6');
        $this->texto($titulo, self::MARGEM, 68, 20, true, '#e0e8e6');
        $this->texto($subtitulo, self::MARGEM, 95, 11, false, '#e0e8e6');

        $this->y = $alt + 26;
    }

    /** Título de página para as seções que começam em página nova. */
    public function tituloPagina(string $titulo, string $subtitulo = ''): void
    {
        $this->novaPagina();
        $this->y = self::MARGEM;

        $this->texto($titulo, self::MARGEM, $this->y + 16, 17, true, '#030f18');
        $this->y += 26;

        if ($subtitulo !== '') {
            $this->texto($subtitulo, self::MARGEM, $this->y + 4, 9.5, false, '#1d4052');
            $this->y += 16;
        }
        $this->y += 6;
    }

    /**
     * Cartão de dado único: rótulo pequeno em cima, valor grande
     * embaixo.
     *
     * A presença do cartão vem do contorno, não de uma barra
     * colorida: preenchimento Frost e traço de 0,9pt dão o
     * contorno sem depender de tinta clara, que some no papel
     * impresso. A cor fica no valor, que é onde a leitura acontece.
     */
    public function cartao(string $rotulo, string $valor, string $cor,
                           float $x, float $yTop, float $w, float $h = 62): void
    {
        $this->retangulo($x, $yTop, $w, $h, '#e0e8e6', '#72a3bf', 0.9);

        $this->texto(strtoupper($rotulo), $x + 10, $yTop + 22, 7, true, '#1d4052');

        // Reduz o corpo da fonte se o valor não couber na largura
        $tamanho = 15;
        while ($tamanho > 9 && $this->larguraTexto(self::cp1252($valor), $tamanho, true) > $w - 20) {
            $tamanho -= 0.5;
        }
        $this->texto($valor, $x + 10, $yTop + 44, $tamanho, true, $cor);
    }

    /**
     * Linha de rótulo + valor, com separador pontilhado.
     * $corRotulo e $corValor permitem destacar a linha de total.
     */
    public function itemResumo(string $rotulo, string $valor,
                               string $corValor = '#030f18',
                               bool $destacar = false): void
    {
        $this->garantirEspaco(22);
        $alt = 20;

        if ($destacar) {
            $this->retangulo(self::MARGEM, $this->y, self::LARGURA_PAGINA - 2 * self::MARGEM, $alt, '#e0e8e6');
        }

        $this->texto($rotulo, self::MARGEM + 8, $this->y + 14, 9.5, $destacar, $destacar ? '#446e87' : '#1d4052');
        $this->texto($valor, self::MARGEM, $this->y + 14, 9.5, true, $corValor, 'D');

        $this->y += $alt;
        if (!$destacar) {
            $this->linha(self::MARGEM, self::LARGURA_PAGINA - self::MARGEM, $this->y, '#72a3bf', 0.5);
        }
    }

    /**
     * Parágrafo com quebra automática de linha.
     */
    public function paragrafo(string $txt, float $tamanho = 9,
                               string $cor = '#1d4052', float $x = null,
                               float $largura = null, float $entrelinha = 12): void
    {
        $x      = $x ?? self::MARGEM;
        $largura = $largura ?? (self::LARGURA_PAGINA - 2 * self::MARGEM);

        $this->garantirEspaco($entrelinha * 2);

        $linhas = $this->quebrarTexto($txt, $tamanho, $largura);
        foreach ($linhas as $l) {
            $this->garantirEspaco($entrelinha);
            $this->texto($l, $x, $this->y + $tamanho, $tamanho, false, $cor);
            $this->y += $entrelinha;
        }
    }

    /** Divide o texto em linhas que cabem em $largura. */
    public function quebrarTexto(string $txt, float $tamanho, float $largura,
                                 bool $negrito = false, int $maxLinhas = 0): array
    {
        $txt = trim(preg_replace('/\s+/u', ' ', $txt));
        if ($txt === '') return [];

        $linhas  = [];
        $atual   = '';

        foreach (explode(' ', $txt) as $palavra) {
            $teste = ($atual === '') ? $palavra : $atual . ' ' . $palavra;
            if ($this->larguraTexto(self::cp1252($teste), $tamanho, $negrito) <= $largura) {
                $atual = $teste;
            } else {
                if ($atual !== '') $linhas[] = $atual;
                $atual = $palavra;
                if ($maxLinhas > 0 && count($linhas) >= $maxLinhas) break;
            }
        }
        if ($atual !== '' && ($maxLinhas === 0 || count($linhas) < $maxLinhas)) {
            $linhas[] = $atual;
        }

        // Last word doesn't fit even on a line of its own → truncate
        if (count($linhas) === 1 && $this->larguraTexto(self::cp1252($linhas[0]), $tamanho, $negrito) > $largura) {
            $linhas = [$this->truncar($linhas[0], $tamanho, $largura, $negrito)];
        }
        return $linhas;
    }

    /** Corta o texto com reticências até caber na largura. */
    private function truncar(string $t, float $tamanho, float $largura, bool $negrito = false): string
    {
        while (mb_strlen($t) > 1 &&
               $this->larguraTexto(self::cp1252($t . '...'), $tamanho, $negrito) > $largura) {
            $t = mb_substr($t, 0, -1);
        }
        return rtrim($t) . '...';
    }

    // =========================================================
    // TABELAS
    // =========================================================

    /**
     * Desenha uma tabela com quebra automática de página e repetição
     * do cabeçalho.
     *
     * @param array $colunas [['titulo'=>..,'w'=>..,'al'=>'E|C|D'], ...]
     * @param array $linhas  [['c'=>[...], 'est'=>'normal|zebra|total|vazio',
     *                       'cores'=>[indiceDaColuna => '#hex']], ...]
     * @param array $opcoes  ['altura'=>18, 'quebra'=>3, 'corCol'=>[...]]
     */
    public function tabela(array $colunas, array $linhas, array $opcoes = []): void
    {
        $alturaLinha = $opcoes['altura'] ?? 17;
        $maxLinhas   = $opcoes['quebra'] ?? 1;
        $coresCol    = $opcoes['corCol']  ?? [];

        $this->tabelaColunas = $colunas;
        $this->tabelaAltura  = $alturaLinha;

        $this->garantirEspaco($alturaLinha * 3);
        $this->cabecalhoTabela($colunas);
        $this->y += 3;

        $larguraUtil = self::LARGURA_PAGINA - 2 * self::MARGEM;

        foreach ($linhas as $linha) {
            $celulas = $linha['c'] ?? [];
            $estilo  = $linha['est'] ?? 'normal';

            // Calcula a altura necessária (quebra de texto na 1ª coluna larga)
            $altura = $alturaLinha;
            $quebra = [];
            foreach ($colunas as $i => $col) {
                $wTexto = $col['w'] - 10;
                if (!empty($col['quebra'])) {
                    $partes = $this->quebrarTexto((string) ($celulas[$i] ?? ''),
                                                 $opcoes['tamanho'] ?? 8.5,
                                                 $wTexto, false, $maxLinhas);
                    $quebra[$i] = $partes;
                    $altura = max($altura, count($partes) * 10.5 + 7);
                } else {
                    $quebra[$i] = null;
                }
            }

            $this->garantirEspaco($altura);

            $x = self::MARGEM;

            // Fundo da linha
            $fundo = null;
            if ($estilo === 'zebra')       $fundo = '#f2f7f6';
            elseif ($estilo === 'total')   $fundo = '#e0e8e6';
            elseif ($estilo === 'vazio')   $fundo = '#fafcfb';
            if ($fundo) {
                $this->retangulo(self::MARGEM, $this->y, $larguraUtil, $altura, $fundo);
            }

            $negrito = ($estilo === 'total');
            $coresLinha = $linha['cores'] ?? [];

            foreach ($colunas as $i => $col) {
                $corTexto = $coresLinha[$i] ?? $coresCol[$i] ?? ($negrito ? '#1d4052' : '#030f18');
                $alinha   = $col['al'] ?? 'E';
                $tamanho  = $opcoes['tamanho'] ?? 8.5;

                if ($quebra[$i] !== null) {
                    // Texto com quebra: desloca um pouco para cima
                    $linhasTxt = $quebra[$i];
                    $baseY = $this->y + ($altura - (count($linhasTxt) - 1) * 10.5) / 2 + $tamanho;
                    foreach ($linhasTxt as $k => $tl) {
                        $this->texto($tl, $x + 5, $baseY + $k * 10.5, $tamanho, $negrito, $corTexto, $alinha, $col['w'] - 10);
                    }
                } else {
                    $this->texto((string) ($celulas[$i] ?? ''), $x + 5, $this->y + $altura / 2 + $tamanho / 2,
                                 $tamanho, $negrito, $corTexto, $alinha, $col['w'] - 10);
                }

                $x += $col['w'];
            }

            // Filete inferior
            $this->linha(self::MARGEM, self::MARGEM + $larguraUtil, $this->y + $altura,
                         $estilo === 'total' ? '#446e87' : '#72a3bf',
                         $estilo === 'total' ? 1.1 : 0.5);

            $this->y += $altura;
        }

        $this->tabelaColunas = [];
        $this->y += 4;
    }

    /** Faixa escura do cabeçalho da tabela. */
    private function cabecalhoTabela(array $colunas): void
    {
        $alt = 21;
        $x   = self::MARGEM;

        $this->retangulo(self::MARGEM, $this->y,
                         self::LARGURA_PAGINA - 2 * self::MARGEM, $alt, '#446e87');

        foreach ($colunas as $col) {
            $this->texto($col['titulo'], $x + 5, $this->y + 14, 7.6, true, '#e0e8e6',
                         $col['al'] ?? 'E', $col['w'] - 10);
            $x += $col['w'];
        }
        $this->y += $alt;
    }

    // =========================================================
    // SAÍDA
    // =========================================================

    /** Fecha a última página, aplica o rodapé e monta o arquivo. */
    public function montar(): string
    {
        $this->finalizarPagina();
        $total = count($this->paginas);

        // Aplica o rodapé em todas as páginas.
        // Atenção: aqui as coordenadas são do PDF puro (y cresce para
        // cima), então o rodapé fica perto de y = 0, não de y = 842.
        foreach ($this->paginas as $i => &$stream) {
            $yRodape = 30;
            $yFilete = 38;

            // Filete acima do rodapé
            $stream .= sprintf("q %s RG 0.6 w %.2F %.2F m %.2F %.2F l S Q\n",
                self::rgb('#72a3bf'),
                self::MARGEM, $yFilete,
                self::LARGURA_PAGINA - self::MARGEM, $yFilete);

            // Texto do rodapé, à esquerda
            $stream .= sprintf("BT /F1 7.5 Tf %s rg 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n",
                self::rgb('#1d4052'), self::MARGEM, $yRodape,
                $this->escapar(self::cp1252($this->rodape)));

            // Número da página, alinhado à direita
            $pag  = 'Página ' . ($i + 1) . ' de ' . $total;
            $xPag = self::LARGURA_PAGINA - self::MARGEM
                  - $this->larguraTexto(self::cp1252($pag), 7.5, false);
            $stream .= sprintf("BT /F1 7.5 Tf %s rg 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n",
                self::rgb('#1d4052'), $xPag, $yRodape, $this->escapar(self::cp1252($pag)));
        }
        unset($stream);

        // ── Montagem dos objetos ──
        $objetos   = [];
        $objPagina = [];
        $objConteudo = [];
        $num = 6; // 1..5 reservados para catálogo, páginas e fontes

        foreach ($this->paginas as $i => $conteudo) {
            $objPagina[$i]   = $num++;
            $objConteudo[$i] = $num++;
        }

        $objetos[1] = '<< /Type /Catalog /Pages 2 0 R >>';

        $kids = array_map(fn($n) => $n . ' 0 R', $objPagina);
        $objetos[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . ' >>';

        $objetos[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objetos[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $objetos[5] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Oblique /Encoding /WinAnsiEncoding >>';

        $recursos = '<< /Font << /F1 3 0 R /F2 4 0 R /F3 5 0 R >> >>';

        foreach ($this->paginas as $i => $conteudo) {
            $objetos[$objPagina[$i]] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %d %d] /Resources %s /Contents %d 0 R >>',
                self::LARGURA_PAGINA, self::ALTURA_PAGINA, $recursos, $objConteudo[$i]
            );
            $objetos[$objConteudo[$i]] = sprintf("<< /Length %d >>\nstream\n%s\nendstream",
                                                 strlen($conteudo), $conteudo);
        }

        // ── Serialização com tabela xref ──
        $pdf     = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];

        ksort($objetos);
        foreach ($objetos as $n => $corpo) {
            $offsets[$n] = strlen($pdf);
            $pdf .= $n . " 0 obj\n" . $corpo . "\nendobj\n";
        }

        $totalObj = count($objetos) + 1;
        $xrefPos  = strlen($pdf);

        $pdf .= "xref\n0 " . $totalObj . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($n = 1; $n < $totalObj; $n++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$n] ?? 0);
        }

        $pdf .= "trailer\n<< /Size " . $totalObj . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n" . $xrefPos . "\n%%EOF\n";

        return $pdf;
    }

    /** Envia o PDF para download e encerra o script. */
    public function enviar(string $nomeArquivo): void
    {
        $conteudo = $this->montar();

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');
        header('Content-Length: ' . strlen($conteudo));
        header('Cache-Control: no-cache, no-store');
        header('Pragma: no-cache');

        echo $conteudo;
        exit;
    }
}
