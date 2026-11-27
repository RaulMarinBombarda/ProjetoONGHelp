<?php
// ============================================================
// includes/core/helpers.php — funções compartilhadas
// projeto ONGHelp · Projeto Integrador 2026
// ============================================================

/** escapa texto para HTML (conteúdo e atributos) */
function e(mixed $texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

function fmtDinheiro(float $v, bool $compacto = false): string
{
    if ($compacto) {
        if ($v >= 1_000_000) return 'R$ ' . number_format($v / 1_000_000, 1, ',', '.') . 'M';
        if ($v >= 1_000)     return 'R$ ' . number_format($v / 1_000, 0, ',', '.') . 'k';
        return 'R$ ' . number_format($v, 0, ',', '.');
    }
    return 'R$ ' . number_format($v, 2, ',', '.');
}

/**
 * valor monetário para células de tabela: zero vira um traço discreto
 */
function fmtDinheiroTabela(float $v): string
{
    if (abs($v) < 0.005) {
        return '<span class="zero">—</span>';
    }
    return fmtDinheiro($v);
}

/**
 * saldo monetário com sinal para tabelas; zero vira traço discreto
 */
function fmtSaldoTabela(float $v): string
{
    if (abs($v) < 0.005) {
        return '<span class="zero">—</span>';
    }
    return ($v > 0 ? '+ ' : '- ') . fmtDinheiro(abs($v));
}

function fmtData(string $data): string
{
    if ($data === '' || $data === null) return '—';
    return date('d/m/Y', strtotime($data));
}

function fmtDataHora(string $data): string
{
    if ($data === '' || $data === null) return '—';
    return date('d/m/Y H:i', strtotime($data));
}

function statusLabel(string $status): string
{
    return [
        'pendente' => 'Pendente',
        'aprovada' => 'Aprovada',
        'recusada' => 'Recusada',
        'suspensa' => 'Suspensa',
    ][$status] ?? 'Desconhecido';
}

function statusClass(string $status): string
{
    return in_array($status, ['pendente', 'aprovada', 'recusada', 'suspensa'], true)
        ? 'status-' . $status
        : 'status-pendente';
}

/**
 * valida apenas o formato do CNPJ: 14 dígitos,
 * a existência e a titularidade são conferidas manualmente pela
 * Equipe ONGHelp no painel administrativo
 */
function validarCnpjFormato(string $cnpj): bool
{
    return strlen(somenteDigitos($cnpj)) === 14;
}

/**
 * a chave PIX da ONG precisa ser o próprio CNPJ, sem máscara
 */
function validarPixCnpj(string $pix, string $cnpj): bool
{
    $pix  = somenteDigitos($pix);
    $cnpj = somenteDigitos($cnpj);

    return strlen($pix) === 14 && $pix === $cnpj;
}

function canalUrl(string $tipo, string $valor): string
{
    $valor = trim($valor);

    if ($valor === '') {
        return '';
    }

    if ($tipo === 'telefone') {
        $digitos = ltrim(somenteDigitos($valor), '0');
        return $digitos !== '' ? 'tel:+' . $digitos : '';
    }

    if ($tipo === 'site') {
        if (!preg_match('#^https?://#i', $valor)) {
            $valor = 'https://' . $valor;
        }
    } elseif ($tipo === 'instagram') {
        if (!preg_match('#^https?://#i', $valor)) {
            $valor = 'https://instagram.com/' . ltrim($valor, '@');
        }
    } elseif ($tipo === 'facebook') {
        if (!preg_match('#^https?://#i', $valor)) {
            $valor = 'https://facebook.com/' . ltrim($valor, '@');
        }
    } elseif (!preg_match('#^https?://#i', $valor)) {
        // "outro" e tipos desconhecidos podem ser texto livre; só
        // viram link quando o esquema é http(s)
        return '';
    }

    return (preg_match('#^https?://#i', $valor) && filter_var($valor, FILTER_VALIDATE_URL))
        ? $valor
        : '';
}

function validarCanalPublico(string $tipo, string $valor): bool
{
    if (!isset(ONGHELP_TIPOS_CANAL[$tipo])) {
        return false;
    }

    $valor = trim($valor);
    if ($valor === '' || strlen($valor) > 255) {
        return false;
    }

    if ($tipo === 'site') {
        $url = preg_match('#^https?://#i', $valor) ? $valor : 'https://' . $valor;
        // o endereço precisa ter domínio ("nada" não é site; "nada.org" é)
        $host = (string) parse_url($url, PHP_URL_HOST);
        return (bool) filter_var($url, FILTER_VALIDATE_URL) && str_contains($host, '.');
    }

    if ($tipo === 'telefone') {
        return strlen(somenteDigitos($valor)) >= 10;
    }

    if ($tipo === 'instagram' || $tipo === 'facebook') {
        return strlen($valor) >= 2;
    }

    return true;
}


/**
 * selo público com a data da última verificação
 * $compacto = true devolve só o ícone com title, para cards
 */
function badgeVerificado(array $ong, bool $compacto = false): string
{
    if (($ong['status'] ?? '') !== 'aprovada' || empty($ong['verificada_em'])) {
        return '';
    }

    $data = fmtData((string) $ong['verificada_em']);
    $iso  = date('Y-m-d', strtotime((string) $ong['verificada_em']));

    if ($compacto) {
        // role=img: sem ele, o aria-label num <i> é ignorado pelos leitores de tela
        return '<i class="bi bi-patch-check-fill ong-card-verif" role="img" '
            . 'title="Cadastro verificado em ' . htmlspecialchars($data, ENT_QUOTES, 'UTF-8') . '" '
            . 'aria-label="Cadastro verificado em ' . htmlspecialchars($data, ENT_QUOTES, 'UTF-8') . '"></i>';
    }

    return '<span class="badge-verificado">'
        . '<i class="bi bi-patch-check-fill"></i> Verificada em '
        . '<time datetime="' . htmlspecialchars($iso, ENT_QUOTES, 'UTF-8') . '">'
        . htmlspecialchars($data, ENT_QUOTES, 'UTF-8')
        . '</time></span>';
}

function flashSet(string $tipo, string $mensagem): void
{
    $_SESSION['flash'][$tipo] = $mensagem;
}

function flashGet(string $tipo): string
{
    $mensagem = $_SESSION['flash'][$tipo] ?? '';
    unset($_SESSION['flash'][$tipo]);
    return (string) $mensagem;
}

/**
 * converte o valor digitado no formulário para float
 *
 * o campo de movimentação não tem garantia de formato: a máscara
 * sugere "1.200,00", mas nada impede que entre "1200.50" — quem
 * digita com ponto decimal, ou em teclado em modo inglês, faz isso
 * sem pensar, a versão anterior removia todo ponto e trocava vírgula
 * por ponto, o que transformava "1200.50" em 120.050,00: um erro de
 * 100× num valor que vai para o relatório público de transparência
 *
 * as regras, em ordem:
 *   · vírgula presente  → padrão brasileiro, ponto é milhar
 *   · mais de um ponto  → separadores de milhar
 *   · um ponto, 3 casas → separador de milhar ("1.200" são 1200)
 *   · um ponto, 1 ou 2 casas → decimal ("1200.50", "1.5")
 *
 * o sinal é lido antes da limpeza: "-500" precisa continuar
 * negativo, senão "-500" viraria +500 e passaria pela validação de
 * "valor maior que zero"
 */
function valorParaDecimal(string $v): float
{
    $v = trim($v);
    if ($v === '') return 0.0;

    $sinal = 1.0;
    if (preg_match('/^([+-])/', $v, $s)) {
        $sinal = ($s[1] === '-') ? -1.0 : 1.0;
        $v = substr($v, 1);
    }

    // fora dígito, vírgula e ponto não interessa (R$, espaços, letras)
    $v = preg_replace('/[^0-9.,]/', '', $v) ?? '';
    if ($v === '') return 0.0;

    if (strpos($v, ',') !== false) {
        $v = str_replace('.', '', $v);
        $v = str_replace(',', '.', $v);
        return $sinal * (float) $v;
    }

    if (substr_count($v, '.') > 1) {
        return $sinal * (float) str_replace('.', '', $v);
    }

    $partes = explode('.', $v);
    if (count($partes) === 2 && strlen($partes[1]) === 3) {
        return $sinal * (float) ($partes[0] . $partes[1]);
    }

    return $sinal * (float) $v;
}

/**
 * remove tudo que não for dígito de uma string
 */
function somenteDigitos(string $v): string
{
    return preg_replace('/\D/', '', $v) ?? '';
}

/**
 * formata um CNPJ de 14 dígitos no padrão 00.000.000/0001-00
 *
 * tolera um valor já mascarado e o devolve como está: os registros
 * de demonstração do onghelp.sql foram gravados com pontuação e
 * continuam aparecendo igual, sem precisar mexer no banco
 */
function mascaraCnpj(string $cnpj): string
{
    $cnpj = trim($cnpj);

    if (preg_match('/\D/', $cnpj)) return $cnpj;
    if (strlen($cnpj) !== 14) return $cnpj;

    return substr($cnpj, 0, 2) . '.' . substr($cnpj, 2, 3) . '.'
         . substr($cnpj, 5, 3) . '/' . substr($cnpj, 8, 4) . '-'
         . substr($cnpj, 12, 2);
}

/**
 * procura o primeiro arquivo existente entre as extensões suportadas
 *
 * isso permite trocar um placeholder SVG por um PNG, WebP ou JPG
 * usando o mesmo nome-base, sem alterar nenhum template
 */
function arquivoImagemDisponivel(string $pasta, string $nomeBase): ?string
{
    foreach (['png', 'webp', 'jpg', 'jpeg', 'svg'] as $extensao) {
        $arquivo = $nomeBase . '.' . $extensao;
        if (is_file(rtrim($pasta, '/\\') . DIRECTORY_SEPARATOR . $arquivo)) {
            return $arquivo;
        }
    }

    return null;
}

/**
 * nome de arquivo da área de atuação ("Meio Ambiente" → meio-ambiente),
 * usado nos ícones e nas imagens ilustrativas das notícias
 */
function areaSlug(?string $area): string
{
    $mapa = [
        'Educação'           => 'educacao',
        'Saúde'              => 'saude',
        'Animais'            => 'animais',
        'Meio Ambiente'      => 'meio-ambiente',
        'Assistência Social' => 'assistencia-social',
        'Cultura'            => 'cultura',
        'Esporte'            => 'esporte',
        'Outra'              => 'outra',
    ];

    return $mapa[trim((string) $area)] ?? 'outra';
}

/**
 * caminho público do ícone da área de atuação
 *
 * os arquivos ficam em assets/img/areas/ e podem ser trocados
 * sem mexer no código: basta manter o mesmo nome-base
 */
function iconeArea(string $area, string $base = ''): string
{
    $slug  = areaSlug($area);
    $pasta = ONGHELP_ROOT . '/assets/img/areas';

    $arquivo = arquivoImagemDisponivel($pasta, $slug) ?? 'outra.png';
    return $base . 'assets/img/areas/' . $arquivo;
}

/**
 * caminho público da foto de perfil da ONG
 *
 * a coluna usuarios.foto_perfil guarda apenas o nome do arquivo,
 * se a foto não existir mais no disco, usa o ícone da área de
 * atuação informada, sem área, devolve o avatar genérico
 * $base é o prefixo relativo da página ('../' em pages/, '' na raiz)
 */
function fotoPerfilUrl(?string $arquivo, string $base = '', ?string $area = null): string
{
    $arquivo = basename(trim((string) $arquivo));
    $pasta   = ONGHELP_UPLOADS_DIR . '/perfis/';

    if ($arquivo !== '' && is_file($pasta . $arquivo)) {
        return $base . ONGHELP_UPLOADS_URL . '/perfis/' . rawurlencode($arquivo);
    }

    if ($area !== null && trim($area) !== '') {
        return iconeArea($area, $base);
    }

    $padrao = arquivoImagemDisponivel(ONGHELP_ROOT . '/assets/img', 'avatar-ong')
        ?? 'avatar-ong.png';

    return $base . 'assets/img/' . $padrao;
}

/** a cor do banner só pode ser uma das cores da paleta (config/app.php) */
function corBannerValida(string $cor): bool
{
    return isset(ONGHELP_CORES_BANNER[strtoupper(trim($cor))]);
}

function escurecerCor(string $hex, float $fator = 0.8): string
{
    $hex = ltrim(trim($hex), '#');
    if (strlen($hex) !== 6 || !ctype_xdigit($hex)) return '#3D4A5C';
    $r = max(0, min(255, (int) round(hexdec(substr($hex, 0, 2)) * $fator)));
    $g = max(0, min(255, (int) round(hexdec(substr($hex, 2, 2)) * $fator)));
    $b = max(0, min(255, (int) round(hexdec(substr($hex, 4, 2)) * $fator)));
    return sprintf('#%02x%02x%02x', $r, $g, $b);
}

/**
 * paleta padrão por área de atuação
 *
 * antes o mesmo array era repetido em ongs.php, perfil-publico.php e
 * transparencia.php, a fonte única evita que as três telas divirjam
 *
 * estas cores são intencionais: cada causa tem identidade própria e
 * não deve ser reduzida à paleta institucional
 */
function coresArea(): array
{
    return [
        'Educação'           => ['#0D5C8F', '#0a4a73'],
        'Saúde'              => ['#C0392B', '#922B21'],
        'Animais'            => ['#E8913A', '#d45610'],
        'Meio Ambiente'      => ['#00B37E', '#00855D'],
        'Assistência Social' => ['#8E44AD', '#6C3483'],
        'Cultura'            => ['#C2185B', '#8E1143'],
        'Esporte'            => ['#16A085', '#0E6655'],
        'Outra'              => ['#9A6B3F', '#74502E'],
    ];
}

/**
 * cor do banner do perfil da ONG: a escolhida na paleta (cor_tema) ou,
 * sem escolha, a cor da área, retorna [cor, cor escura] para o degradê
 *
 * só o banner e os detalhes do perfil usam esta cor, o que representa a
 * causa (anel da foto, bolinha e cartões das listagens) usa corCausa()
 */
function temaOng(array $ong, ?array $coresArea = null): array
{
    $escolhida = strtoupper(trim($ong['cor_tema'] ?? ''));
    if ($escolhida !== '' && corBannerValida($escolhida)) {
        return [$escolhida, escurecerCor($escolhida)];
    }

    $coresArea ??= coresArea();
    return $coresArea[$ong['area'] ?? ''] ?? ['#5A6A80', '#3D4A5C'];
}

/** cor da área de atuação da ONG, a mesma para todas as ONGs da causa */
function corCausa(array $ong, ?array $coresArea = null): string
{
    $coresArea ??= coresArea();
    return $coresArea[$ong['area'] ?? ''][0] ?? '#5A6A80';
}

/**
 * balancete de 12 meses (jan→dez) a partir dos totais mensais de uma ONG
 *
 * $mensal = ['receita' => [mes => valor], 'despesa' => [mes => valor]]
 *
 * meses sem movimentação entram como 0 e o saldo acumulado é somado
 * mês a mês dentro do ano, usado pelo Portal de Transparência e pelo
 * relatório anual em PDF — mesma fonte para tela e exportação
 */
function balanceteDoAno(array $mensal): array
{
    $linhas       = [];
    $totalReceita = 0.0;
    $totalDespesa = 0.0;
    $acumulado    = 0.0;

    for ($mes = 1; $mes <= 12; $mes++) {
        $receita = (float) ($mensal['receita'][$mes] ?? 0.0);
        $despesa = (float) ($mensal['despesa'][$mes] ?? 0.0);
        $saldo       = $receita - $despesa;
        $acumulado  += $saldo;
        $totalReceita += $receita;
        $totalDespesa += $despesa;

        $linhas[$mes] = [
            'receita'   => $receita,
            'despesa'   => $despesa,
            'saldo'     => $saldo,
            'acumulado' => $acumulado,
            'semMov'    => ($receita == 0.0 && $despesa == 0.0),
        ];
    }

    return [
        'linhas'          => $linhas,
        'total_receita'   => $totalReceita,
        'total_despesa'   => $totalDespesa,
        'total_saldo'     => $totalReceita - $totalDespesa,
        'acumulado_final' => $acumulado,
    ];
}

/**
 * normaliza a chave PIX para uso no payload BR Code
 *
 * a chave pode ser e-mail, telefone, CPF, CNPJ ou chave aleatória,
 * telefones e documentos formatados perdem a máscara; o sinal de
 * mais do telefone é preservado, chaves aleatórias são mantidas como
 * estão, porque são sensíveis a maiúsculas/minúsculas
 */
function normalizarChavePix(string $chave): string
{
    $chave = trim($chave);
    if ($chave === '') return '';

    // e-mail PIX é case-insensitive; o banco costuma exibir minúsculo
    if (strpos($chave, '@') !== false) {
        return strtolower($chave);
    }

    // telefone/CPF/CNPJ formatados: preserva o "+" e os dígitos
    if (preg_match('/^[0-9\s().\-\/+]+$/', $chave)) {
        $temMais = str_starts_with($chave, '+');
        $digitos = somenteDigitos($chave);
        return $temMais ? '+' . $digitos : $digitos;
    }

    return $chave;
}

/**
 * prepara nome/cidade para o payload: remove acentos, caracteres
 * de controle e limita o tamanho em bytes, o padrão BR Code usa
 * ASCII para esses campos e tem limites de 25 e 15 caracteres
 */
function pixTexto(string $texto, int $limite, string $padrao = 'ONG'): string
{
    $texto = trim(preg_replace('/\s+/', ' ', $texto) ?? '');
    $texto = strtr($texto, [
        'á'=>'a','à'=>'a','â'=>'a','ã'=>'a','ä'=>'a',
        'Á'=>'A','À'=>'A','Â'=>'A','Ã'=>'A','Ä'=>'A',
        'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
        'É'=>'E','È'=>'E','Ê'=>'E','Ë'=>'E',
        'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
        'Í'=>'I','Ì'=>'I','Î'=>'I','Ï'=>'I',
        'ó'=>'o','ò'=>'o','ô'=>'o','õ'=>'o','ö'=>'o',
        'Ó'=>'O','Ò'=>'O','Ô'=>'O','Õ'=>'O','Ö'=>'O',
        'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u',
        'Ú'=>'U','Ù'=>'U','Û'=>'U','Ü'=>'U',
        'ç'=>'c','Ç'=>'C','ñ'=>'n','Ñ'=>'N',
    ]);
    $texto = preg_replace('/[^\x20-\x7E]/', '', $texto) ?? '';
    $texto = trim(preg_replace('/\s+/', ' ', $texto) ?? '');

    if ($texto === '') $texto = $padrao;

    return substr($texto, 0, $limite);
}

/**
 * monta um campo TLV do BR Code: id + tamanho (2 dígitos) + valor
 */
function pixCampo(string $id, string $valor): string
{
    return $id . str_pad((string) strlen($valor), 2, '0', STR_PAD_LEFT) . $valor;
}

/**
 * CRC16-CCITT (polinômio 0x1021, inicial 0xFFFF), exigido no
 * campo 63 do BR Code, retorna quatro dígitos hexadecimais
 */
function pixCrc16(string $payload): string
{
    $crc = 0xFFFF;
    $len = strlen($payload);

    for ($i = 0; $i < $len; $i++) {
        $crc ^= ord($payload[$i]) << 8;

        for ($bit = 0; $bit < 8; $bit++) {
            $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
            $crc &= 0xFFFF;
        }
    }

    return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
}

/**
 * gera o payload de um QR Code PIX estático (BR Code)
 *
 * sem valor embutido, o doador informa quanto quer doar no app do
 * banco, o QR não processa pagamento nem confirma recebimento: é
 * apenas a chave da ONG codificada no padrão EMVCo
 */
function pixPayload(string $chave, string $nome, string $cidade, ?float $valor = null, string $txid = '***'): string
{
    $chave = normalizarChavePix($chave);

    // o campo 26 tem limite de 99 bytes; com a GUI e a chave, 77
    // é o maior tamanho de chave que cabe sem estourar o TLV
    if ($chave === '' || strlen($chave) > 77) return '';

    $nome   = pixTexto($nome, 25, 'ONG');
    $cidade = pixTexto($cidade, 15, 'BRASIL');
    $txid   = preg_replace('/[^A-Za-z0-9]/', '', $txid) ?? '';
    $txid   = $txid !== '' ? substr($txid, 0, 25) : '***';

    $conta = pixCampo('00', 'br.gov.bcb.pix') . pixCampo('01', $chave);

    $payload = pixCampo('00', '01')
        . pixCampo('26', $conta)
        . pixCampo('52', '0000')
        . pixCampo('53', '986');

    if ($valor !== null && $valor > 0) {
        $payload .= pixCampo('54', number_format($valor, 2, '.', ''));
    }

    $payload .= pixCampo('58', 'BR')
        . pixCampo('59', $nome)
        . pixCampo('60', $cidade)
        . pixCampo('62', pixCampo('05', $txid));

    $payload .= '6304';

    return $payload . pixCrc16($payload);
}

/* ------------------------------------------------------------------
 * qrDemonstracao() removida: gerava um QR Code ilustrativo, que nao
 * pode ser escaneado, e nao tinha chamador, o caminho real de QR Code
 * agora tem duas origens: a imagem oficial enviada pela ONG ou o
 * BR Code estático gerado por pixPayload() a partir da chave
 * ---------------------------------------------------------------- */

/**
 * regras de uso exibidas no primeiro acesso e na página pública de
 * recrutamento, uma única fonte evita divergência de texto entre as
 * duas telas
 *
 * @return string[]
 */
function regrasUsoEquipe(): array
{
    return [
        'Uso exclusivo para analisar e moderar cadastros do ONGHelp.',
        'Os dados das ONGs e dos responsáveis são confidenciais e protegidos pela LGPD; não copie, não compartilhe, não use fora do painel.',
        'Não analise ONG com a qual você tenha vínculo; avise a equipe.',
        'Toda ação fica registrada em seu nome.',
        'A conta é pessoal e intransferível; nunca compartilhe sua senha.',
        'Em caso de dúvida ou suspeita, recuse com motivo ou fale com a equipe.',
    ];
}

/**
 * valida os dados da ONG comuns ao cadastro e à edição do perfil,
 * devolve [campo, mensagem] do primeiro problema, ou null se está tudo
 * certo, campos obrigatórios são conferidos por cada página
 */
function validarDadosOng(array $d): ?array
{
    // limites iguais aos das colunas do banco
    $limites = [
        'nome' => ['Nome da ONG', 160], 'email' => ['E-mail', 160], 'cidade' => ['Cidade', 100],
        'canal_valor' => ['Endereço do canal', 255], 'responsavel_nome' => ['Responsável legal', 160],
        'responsavel_cargo' => ['Cargo', 80], 'descricao' => ['Descrição', 1000],
    ];
    foreach ($limites as $campo => [$rotulo, $max]) {
        if (isset($d[$campo]) && mb_strlen($d[$campo]) > $max) {
            return [$campo, 'O campo ' . $rotulo . ' aceita no máximo ' . $max . ' caracteres.'];
        }
    }

    if (!filter_var($d['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
        return ['email', 'Informe um e-mail válido.'];
    }
    if (!in_array($d['area'] ?? '', ONGHELP_AREAS, true)) {
        return ['area', 'Selecione uma área de atuação válida.'];
    }
    if (($d['estado'] ?? '') !== '' && !isset(ONGHELP_ESTADOS[$d['estado']])) {
        return ['estado', 'Selecione um estado da lista.'];
    }
    if (!validarCanalPublico($d['canal_tipo'] ?? '', $d['canal_valor'] ?? '')) {
        return ['canal_valor', 'Informe um canal público válido (site, rede social ou telefone).'];
    }
    return null;
}

// ── WhatsApps da equipe (números em config/app.php) ────────
// sem número informado, as funções usam o contato principal

/** todos os contatos, na ordem das opções, com número, exibição e link */
function whatsappsEquipe(string $mensagem = ONGHELP_WHATSAPP_MENSAGEM_EQUIPE): array
{
    $contatos = [];
    foreach (ONGHELP_WHATSAPPS_EQUIPE as $numero) {
        $contatos[] = [
            'e164'     => whatsappEquipeNumeroE164($numero),
            'exibicao' => whatsappEquipeExibicao($numero),
            // sem o +55, para listas compactas como a do rodapé
            'curto'    => strlen($numero) === 13
                ? '(' . substr($numero, 2, 2) . ') ' . substr($numero, 4, 5) . '-' . substr($numero, 9)
                : whatsappEquipeExibicao($numero),
            'url'      => whatsappEquipeUrl($mensagem, $numero),
        ];
    }
    return $contatos;
}

function whatsappEquipeNumeroE164(?string $numero = null): string
{
    return '+' . ($numero ?? ONGHELP_WHATSAPPS_EQUIPE[0]);
}

function whatsappEquipeExibicao(?string $numero = null): string
{
    $numero ??= ONGHELP_WHATSAPPS_EQUIPE[0];

    if (strlen($numero) === 13) {
        return '+' . substr($numero, 0, 2)
            . ' ' . substr($numero, 2, 2)
            . ' ' . substr($numero, 4, 5)
            . '-' . substr($numero, 9);
    }

    return '+' . $numero;
}

function whatsappEquipeUrl(string $mensagem = ONGHELP_WHATSAPP_MENSAGEM_EQUIPE, ?string $numero = null): string
{
    return 'https://wa.me/' . ($numero ?? ONGHELP_WHATSAPPS_EQUIPE[0]) . '?text=' . rawurlencode($mensagem);
}
