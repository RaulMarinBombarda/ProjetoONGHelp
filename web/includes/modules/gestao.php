<?php
// ============================================================
// includes/modules/gestao.php — movimentações, projetos e comprovantes
// ============================================================
//
// regras usadas pela página de gestão (pages/gestao.php) e pelas
// telas públicas que mostram categoria, projeto e comprovante
// (perfil público, portal de transparência, CSV e PDF)
//
// o comprovante é um único arquivo em uploads/comprovantes/,
// gravado em movimentacoes.comprovante como "comprovantes/<nome>",
// do mesmo jeito que as fotos das notícias
// ============================================================

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/noticias-fotos.php';

// ── categorias e situação ───────────────────────────────────

/** categorias do tipo ('receita' ou 'despesa'), valor => rótulo */
function categoriasMovimentacao(string $tipo): array
{
    return ONGHELP_CATEGORIAS_MOVIMENTACAO[$tipo] ?? [];
}

function rotuloCategoria(string $tipo, string $categoria): string
{
    return ONGHELP_CATEGORIAS_MOVIMENTACAO[$tipo][$categoria]
        ?? ($tipo === 'receita' ? 'Outras receitas' : 'Outras despesas');
}

function rotuloStatusProjeto(string $status): string
{
    return ONGHELP_STATUS_PROJETO[$status] ?? ONGHELP_STATUS_PROJETO['andamento'];
}

/** percentual da meta já alcançado (0 a 100), null quando não há meta */
function progressoProjeto(array $projeto): ?int
{
    $meta = (int) ($projeto['meta'] ?? 0);

    if ($meta <= 0) {
        return null;
    }

    return (int) min(100, round((int) $projeto['indicador'] / $meta * 100));
}

/** período do projeto em texto curto: "desde mar/2024" ou "mar/2025 a jun/2026" */
function periodoProjeto(array $projeto): string
{
    $mes = static function (?string $data): string {
        if (!$data) {
            return '';
        }
        $meses = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
        $momento = strtotime($data);

        return $meses[(int) date('n', $momento) - 1] . '/' . date('Y', $momento);
    };

    $inicio = $mes($projeto['data_inicio'] ?? null);
    $fim    = $mes($projeto['data_fim'] ?? null);

    if ($inicio !== '' && $fim !== '') {
        return $inicio . ' a ' . $fim;
    }
    if ($inicio !== '') {
        return (($projeto['status'] ?? '') === 'planejado' ? 'início previsto em ' : 'desde ') . $inicio;
    }
    if ($fim !== '') {
        return 'até ' . $fim;
    }

    return '';
}

// ── validação dos formulários ───────────────────────────────

/** confere uma data do formulário (aaaa-mm-dd), '' quando vazia ou inválida */
function dataDoFormulario(mixed $valor): string
{
    $valor = trim((string) $valor);
    $data  = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);

    return ($data && $data->format('Y-m-d') === $valor) ? $valor : '';
}

/**
 * lê e valida a movimentação enviada (inclusão e edição usam a mesma
 * regra), $idsProjetos são os projetos da própria ONG, devolve
 * ['erro' => '', 'dados' => [...]] ou ['erro' => 'mensagem']
 */
function movimentacaoDoPost(array $post, array $idsProjetos): array
{
    $tipo      = (string) ($post['tipo'] ?? '');
    $descricao = trim((string) ($post['descricao'] ?? ''));
    $valorTxt  = trim((string) ($post['valor'] ?? ''));
    // arredonda antes de validar, senão 0,004 passaria como maior que zero e
    // seria gravado como R$ 0,00
    $valor     = round(valorParaDecimal($valorTxt), 2);
    $dataMov   = trim((string) ($post['data_mov'] ?? ''));
    $categoria = trim((string) ($post['categoria'] ?? ''));
    $projeto   = (string) ($post['projeto_id'] ?? '');

    $erro = match (true) {
        !in_array($tipo, ['receita', 'despesa'], true) => 'Selecione o tipo (receita ou despesa).',
        $descricao === ''                              => 'Informe a descrição da movimentação.',
        mb_strlen($descricao) > 255                    => 'A descrição aceita no máximo 255 caracteres.',
        // só dígitos, ponto e vírgula (com R$ opcional), "1e5" não pode virar 15
        !preg_match('/^(R\$)?\s*\d[\d.,\s]*$/u', $valorTxt)
                                                       => 'Informe o valor só com números, como 1.250,00.',
        $valor <= 0                                    => 'Informe um valor maior que zero.',
        $valor > 9_999_999_999.99                      => 'O valor informado é alto demais.',
        dataDoFormulario($dataMov) === ''              => 'Informe uma data válida.',
        $dataMov > date('Y-m-d')                       => 'A data não pode estar no futuro.',
        $dataMov < '2000-01-01'                        => 'Informe uma data a partir de 2000.',
        // sem escolha a movimentação entra como "outras", que existe nos dois tipos
        $categoria !== '' && !isset(categoriasMovimentacao($tipo)[$categoria])
                                                       => 'Escolha uma categoria da lista de ' . ($tipo === 'receita' ? 'receitas.' : 'despesas.'),
        $projeto !== '' && !in_array((int) $projeto, $idsProjetos, true)
                                                       => 'Escolha um projeto da sua lista.',
        default                                        => '',
    };

    if ($erro !== '') {
        return ['erro' => $erro];
    }

    return ['erro' => '', 'dados' => [
        'tipo'       => $tipo,
        'categoria'  => $categoria !== '' ? $categoria : 'outra',
        'projeto_id' => $projeto !== '' ? (int) $projeto : null,
        'descricao'  => $descricao,
        'valor'      => round($valor, 2),
        'data_mov'   => $dataMov,
    ]];
}

/** lê e valida o projeto enviado, mesmo formato de retorno de movimentacaoDoPost() */
function projetoDoPost(array $post): array
{
    $nome      = trim((string) ($post['nome'] ?? ''));
    $descricao = trim((string) ($post['descricao'] ?? ''));
    $status    = (string) ($post['status'] ?? 'andamento');
    $indicador = trim((string) ($post['indicador'] ?? '0'));
    $meta      = trim((string) ($post['meta'] ?? ''));
    $unidade   = trim((string) ($post['unidade'] ?? ''));
    $inicioRaw = trim((string) ($post['data_inicio'] ?? ''));
    $fimRaw    = trim((string) ($post['data_fim'] ?? ''));
    $inicio    = dataDoFormulario($inicioRaw);
    $fim       = dataDoFormulario($fimRaw);
    $inteiro   = static fn (string $v): bool => ctype_digit($v) && (int) $v <= 4_000_000_000;

    $erro = match (true) {
        $nome === ''                                   => 'Informe o nome do projeto.',
        mb_strlen($nome) > 160                         => 'O nome do projeto aceita no máximo 160 caracteres.',
        mb_strlen($descricao) > ONGHELP_PROJETO_DESCRICAO_MAX
                                                       => 'A descrição do projeto aceita no máximo ' . ONGHELP_PROJETO_DESCRICAO_MAX . ' caracteres.',
        !isset(ONGHELP_STATUS_PROJETO[$status])        => 'Escolha a situação do projeto.',
        !$inteiro($indicador === '' ? '0' : $indicador) => 'O número do resultado deve ser um inteiro igual ou maior que zero.',
        $meta !== '' && (!$inteiro($meta) || (int) $meta < 1)
                                                       => 'A meta deve ser um número inteiro maior que zero, ou fique em branco.',
        $unidade === ''                                => 'Informe o que aparece junto do número (ex.: mudas plantadas).',
        mb_strlen($unidade) > 80                       => 'O texto junto do número aceita no máximo 80 caracteres.',
        $inicioRaw !== '' && ($inicio === '' || $inicio < '2000-01-01')
                                                       => 'Informe uma data de início válida, a partir de 2000.',
        $fimRaw !== '' && ($fim === '' || $fim < '2000-01-01')
                                                       => 'Informe uma data de término válida, a partir de 2000.',
        $inicio !== '' && $fim !== '' && $fim < $inicio => 'A data de término não pode ser antes do início.',
        $status === 'concluido' && $fim > date('Y-m-d') => 'Um projeto concluído não pode terminar no futuro.',
        default                                        => '',
    };

    if ($erro !== '') {
        return ['erro' => $erro];
    }

    return ['erro' => '', 'dados' => [
        'nome'        => $nome,
        'descricao'   => $descricao,
        'status'      => $status,
        'indicador'   => (int) ($indicador === '' ? '0' : $indicador),
        'meta'        => $meta !== '' ? (int) $meta : null,
        'unidade'     => $unidade,
        'data_inicio' => $inicio !== '' ? $inicio : null,
        'data_fim'    => $fim !== '' ? $fim : null,
    ]];
}

// ── comprovantes ────────────────────────────────────────────

/** tipos aceitos => extensão gravada */
function comprovanteTiposAceitos(): array
{
    return [
        'application/pdf' => 'pdf',
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/webp'      => 'webp',
    ];
}

/** o caminho salvo no banco precisa estar dentro da pasta e ter extensão aceita */
function comprovanteCaminhoValido(string $arquivo): bool
{
    return !str_contains($arquivo, '..')
        && preg_match('~^' . preg_quote(ONGHELP_COMPROVANTES_PASTA, '~') . '/[A-Za-z0-9_-]+\.(?:pdf|jpg|png|webp)$~', $arquivo) === 1;
}

/** URL pública do comprovante, '' quando não há arquivo ou ele sumiu do disco */
function comprovanteUrl(?string $arquivo, string $base = '../'): string
{
    $arquivo = (string) $arquivo;

    if (!comprovanteCaminhoValido($arquivo) || !is_file(ONGHELP_UPLOADS_DIR . '/' . $arquivo)) {
        return '';
    }

    return $base . ONGHELP_UPLOADS_URL . '/' . $arquivo;
}

function comprovanteApagar(?string $arquivo): void
{
    $arquivo = (string) $arquivo;

    if (comprovanteCaminhoValido($arquivo) && is_file(ONGHELP_UPLOADS_DIR . '/' . $arquivo)) {
        @unlink(ONGHELP_UPLOADS_DIR . '/' . $arquivo);
    }
}

/**
 * valida e grava o comprovante enviado, $upload é uma entrada de
 * $_FILES, devolve ['ok' => true, 'arquivo' => 'comprovantes/<nome>']
 * ou ['ok' => false, 'erro' => 'mensagem'], sem arquivo devolve
 * ['ok' => true, 'arquivo' => null]
 */
function comprovanteSalvarUpload(?array $upload, int $ongId): array
{
    $codigo = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
    $limite = (int) round(ONGHELP_COMPROVANTE_MAX_BYTES / 1024 / 1024);

    if ($codigo === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'arquivo' => null];
    }
    if ($codigo === UPLOAD_ERR_INI_SIZE || $codigo === UPLOAD_ERR_FORM_SIZE
        || (int) ($upload['size'] ?? 0) > ONGHELP_COMPROVANTE_MAX_BYTES) {
        return ['ok' => false, 'erro' => "O comprovante passa de $limite MB."];
    }

    $tmp = (string) ($upload['tmp_name'] ?? '');

    if ($codigo !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) {
        return ['ok' => false, 'erro' => 'Não foi possível receber o comprovante. Tente novamente.'];
    }

    // o tipo vem do conteúdo do arquivo, não do nome nem do navegador
    $mime  = (string) (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $tipos = comprovanteTiposAceitos();
    $valido = match ($mime) {
        'application/pdf' => (string) file_get_contents($tmp, false, null, 0, 5) === '%PDF-',
        'image/jpeg', 'image/png', 'image/webp' => @getimagesize($tmp) !== false,
        default => false,
    };

    if (!isset($tipos[$mime]) || !$valido) {
        return ['ok' => false, 'erro' => 'O comprovante precisa ser um PDF ou uma imagem JPG, PNG ou WebP.'];
    }

    $pasta = ONGHELP_UPLOADS_DIR . '/' . ONGHELP_COMPROVANTES_PASTA;

    if (!is_dir($pasta) && !mkdir($pasta, 0775, true)) {
        return ['ok' => false, 'erro' => 'Não foi possível preparar a pasta de comprovantes. Tente novamente.'];
    }

    $nome = 'ong' . $ongId . '-' . bin2hex(random_bytes(8)) . '.' . $tipos[$mime];

    if (!move_uploaded_file($tmp, $pasta . '/' . $nome)) {
        return ['ok' => false, 'erro' => 'Não foi possível salvar o comprovante. Tente novamente.'];
    }

    // fotos de celular podem trazer a localização no EXIF
    if ($tipos[$mime] === 'jpg') {
        noticiaFotoLimparMetadadosJpeg($pasta . '/' . $nome);
    }

    return ['ok' => true, 'arquivo' => ONGHELP_COMPROVANTES_PASTA . '/' . $nome];
}

// ── consultas usadas por mais de uma página ─────────────────

/**
 * projetos de uma ONG com o total de despesas ligadas a cada um,
 * em andamento primeiro, depois planejados e concluídos
 */
function projetosDaOng(PDO $pdo, int $ongId): array
{
    $stmt = $pdo->prepare("
        SELECT p.*,
               COALESCE((SELECT SUM(m.valor) FROM movimentacoes m
                         WHERE m.projeto_id = p.id AND m.tipo = 'despesa'), 0) AS total_aplicado
        FROM   projetos p
        WHERE  p.usuario_id = ?
        ORDER  BY FIELD(p.status, 'andamento', 'planejado', 'concluido'), p.nome ASC
    ");
    $stmt->execute([$ongId]);

    return $stmt->fetchAll();
}

/**
 * linha de detalhes de uma movimentação nas listas públicas:
 * categoria, projeto e link do comprovante, já escapada para HTML
 */
function detalhesMovimentacaoHtml(array $mov, string $base = '../'): string
{
    $partes = [e(rotuloCategoria((string) $mov['tipo'], (string) ($mov['categoria'] ?? 'outra')))];

    if (!empty($mov['projeto_nome'])) {
        $partes[] = e((string) $mov['projeto_nome']);
    }

    $url = comprovanteUrl($mov['comprovante'] ?? null, $base);
    if ($url !== '') {
        $partes[] = '<a class="mov-comprovante" href="' . e($url) . '" target="_blank" rel="noopener">'
            . '<i class="bi bi-paperclip" aria-hidden="true"></i>Comprovante</a>';
    }

    return implode(' · ', $partes);
}

// ── componentes das telas públicas ──────────────────────────

/**
 * item do feed de movimentações (perfil público e portal de
 * transparência), a linha de baixo traz data, tipo, categoria,
 * projeto e o link do comprovante quando houver
 */
function feedMovimentacaoHtml(array $mov, string $base = '../'): string
{
    $receita = $mov['tipo'] === 'receita';

    return '<div class="tr-feed-item">'
        . '<span class="tr-feed-icon ' . ($receita ? 'is-receita' : 'is-despesa') . '" aria-hidden="true">'
        . '<i class="bi ' . ($receita ? 'bi-arrow-down-left' : 'bi-arrow-up-right') . '"></i></span>'
        . '<div class="tr-feed-main">'
        . '<div class="tr-feed-desc">' . e($mov['descricao']) . '</div>'
        . '<div class="tr-feed-meta">' . fmtData((string) $mov['data_mov']) . ' · ' . ($receita ? 'Receita' : 'Despesa')
        . ' · ' . detalhesMovimentacaoHtml($mov, $base) . '</div>'
        . '</div>'
        . '<span class="tr-feed-value ' . ($receita ? 'is-pos' : 'is-neg') . '">'
        . ($receita ? '+ ' : '− ') . fmtDinheiro((float) $mov['valor']) . '</span>'
        . '</div>';
}

/**
 * cartão de projeto do perfil público, $completo acrescenta a
 * descrição e o total aplicado (aba Projetos), sem ele o cartão
 * fica no formato curto dos resultados em destaque
 */
function cartaoProjetoHtml(array $proj, bool $completo = true): string
{
    $progresso = progressoProjeto($proj);
    $periodo   = periodoProjeto($proj);

    $html = '<div class="perfil-projeto">'
        . '<div class="perfil-projeto-topo">'
        . '<span class="status-projeto is-' . e($proj['status']) . '">' . e(rotuloStatusProjeto($proj['status'])) . '</span>'
        . ($periodo !== '' ? '<span class="perfil-projeto-periodo">' . e($periodo) . '</span>' : '')
        . '</div>'
        . '<div class="perfil-projeto-numero">' . number_format((float) $proj['indicador'], 0, ',', '.') . '</div>'
        . '<div class="perfil-projeto-desc">' . e($proj['unidade']) . '</div>';

    if ($progresso !== null) {
        $html .= '<div class="meta-projeto">'
            . '<span class="meta-projeto-barra" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . $progresso . '"'
            . ' aria-label="' . $progresso . '% da meta" style="--progresso:' . $progresso . '%;"><span></span></span>'
            . '<span class="meta-projeto-texto">' . $progresso . '% da meta de ' . number_format((float) $proj['meta'], 0, ',', '.') . '</span>'
            . '</div>';
    }

    $html .= '<div class="perfil-projeto-nome">' . e($proj['nome']) . '</div>';

    if ($completo && trim((string) ($proj['descricao'] ?? '')) !== '') {
        $html .= '<p class="perfil-projeto-texto">' . e($proj['descricao']) . '</p>';
    }
    if ($completo && (float) ($proj['total_aplicado'] ?? 0) > 0) {
        $html .= '<p class="perfil-projeto-aplicado"><i class="bi bi-wallet2" aria-hidden="true"></i>'
            . fmtDinheiro((float) $proj['total_aplicado']) . ' em despesas ligadas ao projeto</p>';
    }

    return $html . '</div>';
}
