<?php
// ============================================================
// includes/modules/noticias-fotos.php — fotos das notícias
// ============================================================
//
// cada foto é um único arquivo em uploads/noticias/,
// registrado em noticias_fotos.arquivo como "noticias/<nome>"
//
// o envio acontece pelo próprio formulário do editor (POST
// multipart), seguindo o mesmo padrão da foto de perfil:
//   1. confere o código de upload e o tamanho;
//   2. confere o tipo real do arquivo (finfo) e se é uma imagem;
//   3. com a extensão GD ativa, reduz para no máximo 1600 px de
//      largura e regrava o arquivo (o que descarta metadados);
//      sem GD, salva o original e remove os metadados EXIF de
//      JPEGs (que podem conter a localização GPS da foto)

require_once __DIR__ . '/../../config/app.php';

const NOTICIA_FOTO_LARGURA_MAX = 1600;

/** tipos aceitos => extensão gravada */
function noticiaFotoTiposAceitos(): array
{
    return [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
}

function noticiaFotosPasta(): string
{
    return ONGHELP_UPLOADS_DIR . '/' . ONGHELP_NOTICIAS_UPLOADS_PASTA;
}

/**
 * confere se o caminho salvo no banco é seguro (sem "..", sempre
 * dentro da pasta de notícias e com extensão de imagem)
 */
function noticiaFotoCaminhoValido(string $arquivo): bool
{
    return $arquivo !== ''
        && !str_contains($arquivo, '..')
        && preg_match('~^' . preg_quote(ONGHELP_NOTICIAS_UPLOADS_PASTA, '~')
            . '/[A-Za-z0-9/_-]+\.(?:jpe?g|png|webp)$~', $arquivo) === 1;
}

/**
 * URL pública da foto. $base é o prefixo até a raiz do site
 * ('' na raiz, '../' dentro de pages/ ou admin/)
 */
function noticiaFotoUrl(?string $arquivo, string $base = '../'): string
{
    $arquivo = (string) $arquivo;

    if (!noticiaFotoCaminhoValido($arquivo)) {
        return '';
    }

    return $base . ONGHELP_UPLOADS_URL . '/'
        . implode('/', array_map('rawurlencode', explode('/', $arquivo)));
}

/** acrescenta a URL pronta para uso na linha vinda do banco */
function noticiaFotoBloco(array $foto, string $base = '../'): array
{
    $foto['url'] = noticiaFotoUrl((string) ($foto['arquivo'] ?? ''), $base);

    return $foto;
}

/**
 * imagem ilustrativa N da área (assets/img/noticias/<area>-N.jpg),
 * no mesmo formato de um bloco de foto, não descreve nada da notícia,
 * por isso o texto alternativo fica vazio
 */
function noticiaImagemPadrao(?string $area, int $numero, string $base = '../'): array
{
    static $medidas = [];

    $numero = max(1, min(ONGHELP_NOTICIAS_IMAGENS_PADRAO, $numero));
    $arquivo = areaSlug($area) . '-' . $numero . '.jpg';

    if (!isset($medidas[$arquivo])) {
        $caminho = ONGHELP_ROOT . '/assets/img/noticias/' . $arquivo;
        $medidas[$arquivo] = is_file($caminho) ? (getimagesize($caminho) ?: [0, 0]) : null;
    }

    if ($medidas[$arquivo] === null) {
        return [];
    }

    return [
        'url' => $base . 'assets/img/noticias/' . $arquivo,
        'texto_alternativo' => '',
        'largura' => (int) $medidas[$arquivo][0],
        'altura' => (int) $medidas[$arquivo][1],
        'ilustrativa' => true,
    ];
}

/**
 * imagem ilustrativa de uma notícia sem fotos: a escolhida no editor
 * ou, sem escolha, uma das imagens da área variando pelo id (assim
 * notícias seguidas da mesma ONG não repetem a mesma imagem)
 */
function noticiaImagemPadraoDaNoticia(array $noticia, string $base = '../'): array
{
    $numero = (int) ($noticia['imagem_padrao'] ?? 0)
        ?: ((int) ($noticia['id'] ?? 0) % ONGHELP_NOTICIAS_IMAGENS_PADRAO) + 1;

    return noticiaImagemPadrao($noticia['ong_area'] ?? $noticia['area'] ?? null, $numero, $base);
}

/**
 * miniatura de listas (painel e Minhas notícias): a capa enviada ou,
 * sem foto, a imagem ilustrativa, a linha traz capa_miniatura
 */
function noticiaMiniaturaUrl(array $noticia, string $base = '../'): string
{
    $arquivo = (string) ($noticia['capa_miniatura'] ?? '');

    return $arquivo !== ''
        ? noticiaFotoUrl($arquivo, $base)
        : (string) (noticiaImagemPadraoDaNoticia($noticia, $base)['url'] ?? '');
}

/** fotos de uma notícia, na ordem do editor (a primeira é a capa) */
function noticiaFotosDaNoticia(PDO $pdo, int $noticiaId, string $base = '../'): array
{
    if ($noticiaId <= 0) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT * FROM noticias_fotos WHERE noticia_id = ? ORDER BY ordem, id'
    );
    $stmt->execute([$noticiaId]);

    return array_map(
        static fn (array $foto): array => noticiaFotoBloco($foto, $base),
        $stmt->fetchAll()
    );
}

function noticiaFotoApagarArquivo(string $arquivo): bool
{
    if (!noticiaFotoCaminhoValido($arquivo)) {
        return false;
    }

    $caminho = ONGHELP_UPLOADS_DIR . '/' . $arquivo;

    return is_file($caminho) && @unlink($caminho);
}

/**
 * remove os segmentos de metadados (EXIF/XMP em APP1 e IPTC em
 * APP13) de um JPEG, sem recomprimir a imagem
 */
function noticiaFotoLimparMetadadosJpeg(string $caminho): void
{
    $dados = @file_get_contents($caminho);

    if ($dados === false || strncmp($dados, "\xFF\xD8", 2) !== 0) {
        return;
    }

    $saida = "\xFF\xD8";
    $pos = 2;
    $tamanho = strlen($dados);

    while ($pos + 4 <= $tamanho && $dados[$pos] === "\xFF") {
        $marcador = ord($dados[$pos + 1]);

        // início dos dados da imagem: copia o restante como está
        if ($marcador === 0xDA) {
            break;
        }

        $comprimento = unpack('n', substr($dados, $pos + 2, 2))[1];
        $segmento = substr($dados, $pos, 2 + $comprimento);

        if ($marcador !== 0xE1 && $marcador !== 0xED) {
            $saida .= $segmento;
        }

        $pos += 2 + $comprimento;
    }

    file_put_contents($caminho, $saida . substr($dados, $pos));
}

/**
 * valida e grava uma foto enviada pelo formulário
 *
 * retorno: ok, erro, arquivo ("noticias/<nome>"), largura, altura
 */
function noticiaFotoSalvarUpload(array $upload, int $ongId): array
{
    $falha = static fn (string $mensagem): array => ['ok' => false, 'erro' => $mensagem];
    $codigo = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
    $nomeOriginal = trim((string) ($upload['name'] ?? 'foto'));
    $rotulo = '"' . mb_strimwidth($nomeOriginal, 0, 40, '…', 'UTF-8') . '"';
    $limiteMb = (int) round(ONGHELP_NOTICIAS_FOTO_MAX_BYTES / 1024 / 1024);

    if ($codigo === UPLOAD_ERR_INI_SIZE || $codigo === UPLOAD_ERR_FORM_SIZE) {
        return $falha("A foto $rotulo passa de $limiteMb MB.");
    }

    if ($codigo !== UPLOAD_ERR_OK) {
        return $falha("Não foi possível receber a foto $rotulo. Tente novamente.");
    }

    $tmp = (string) ($upload['tmp_name'] ?? '');

    if (!is_uploaded_file($tmp)) {
        return $falha("Não foi possível validar a foto $rotulo.");
    }

    if ((int) ($upload['size'] ?? 0) > ONGHELP_NOTICIAS_FOTO_MAX_BYTES) {
        return $falha("A foto $rotulo passa de $limiteMb MB.");
    }

    $tipos = noticiaFotoTiposAceitos();
    $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $info = @getimagesize($tmp);

    if (!isset($tipos[$mime]) || $info === false) {
        return $falha("A foto $rotulo precisa ser uma imagem JPG, PNG ou WebP.");
    }

    [$largura, $altura] = [(int) $info[0], (int) $info[1]];

    if ($largura < 200 || $altura < 150) {
        return $falha("A foto $rotulo é pequena demais (mínimo de 200 × 150 pixels).");
    }

    if ($largura * $altura > ONGHELP_NOTICIAS_FOTO_MAX_MEGAPIXELS * 1000000) {
        return $falha("A foto $rotulo tem resolução muito alta. Reduza o tamanho e envie novamente.");
    }

    $pasta = noticiaFotosPasta();

    if (!is_dir($pasta) && !mkdir($pasta, 0775, true)) {
        return $falha('Não foi possível preparar a pasta de fotos. Tente novamente.');
    }

    $nome = 'ong' . $ongId . '-' . bin2hex(random_bytes(8));
    $gd = function_exists('imagecreatefromstring') && function_exists('imagejpeg');

    if ($gd) {
        $imagem = @imagecreatefromstring((string) file_get_contents($tmp));

        if ($imagem === false) {
            return $falha("Não foi possível ler a foto $rotulo.");
        }

        if ($largura > NOTICIA_FOTO_LARGURA_MAX) {
            $novaAltura = (int) round($altura * NOTICIA_FOTO_LARGURA_MAX / $largura);
            $reduzida = imagescale($imagem, NOTICIA_FOTO_LARGURA_MAX, $novaAltura);

            if ($reduzida !== false) {
                $imagem = $reduzida;
                [$largura, $altura] = [NOTICIA_FOTO_LARGURA_MAX, $novaAltura];
            }
        }

        // PNG e WebP podem ter transparência: mantém o formato
        $extensao = $mime === 'image/jpeg' ? 'jpg' : $tipos[$mime];

        if ($extensao !== 'jpg') {
            imagealphablending($imagem, false);
            imagesavealpha($imagem, true);
        }
        $destino = $pasta . '/' . $nome . '.' . $extensao;

        $gravou = match ($extensao) {
            'png' => imagepng($imagem, $destino, 6),
            'webp' => function_exists('imagewebp') && imagewebp($imagem, $destino, 84),
            default => imagejpeg($imagem, $destino, 84),
        };

        if (!$gravou) {
            return $falha("Não foi possível salvar a foto $rotulo.");
        }
    } else {
        $extensao = $tipos[$mime];
        $destino = $pasta . '/' . $nome . '.' . $extensao;

        if (!move_uploaded_file($tmp, $destino)) {
            return $falha("Não foi possível salvar a foto $rotulo.");
        }

        if ($extensao === 'jpg') {
            noticiaFotoLimparMetadadosJpeg($destino);
        }
    }

    return [
        'ok' => true,
        'erro' => '',
        'arquivo' => ONGHELP_NOTICIAS_UPLOADS_PASTA . '/' . $nome . '.' . $extensao,
        'largura' => $largura,
        'altura' => $altura,
    ];
}

/**
 * reorganiza $_FILES['campo'] (formato de múltiplos arquivos) em
 * uma lista de uploads, ignorando entradas vazias
 */
function noticiaFotosNormalizarUploads(?array $campo): array
{
    if (!$campo || !is_array($campo['name'] ?? null)) {
        return [];
    }

    $lista = [];

    foreach ($campo['name'] as $indice => $nome) {
        if ((int) ($campo['error'][$indice] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $lista[] = [
            'indice' => $indice,
            'name' => (string) $nome,
            'type' => (string) ($campo['type'][$indice] ?? ''),
            'tmp_name' => (string) ($campo['tmp_name'][$indice] ?? ''),
            'error' => (int) ($campo['error'][$indice] ?? UPLOAD_ERR_NO_FILE),
            'size' => (int) ($campo['size'][$indice] ?? 0),
        ];
    }

    return $lista;
}

function noticiaFotoTextoCurto(mixed $valor, int $maximo): string
{
    $texto = trim(preg_replace('/\s+/u', ' ', is_scalar($valor) ? (string) $valor : '') ?? '');

    return mb_substr($texto, 0, $maximo, 'UTF-8');
}

/**
 * lê do POST o que deve acontecer com as fotos da notícia e já
 * grava em disco as fotos novas, nada é escrito no banco aqui:
 * o editor aplica o plano com noticiaFotosAplicarPlano() dentro
 * da mesma transação que salva a notícia
 *
 * campos esperados:
 *   fotos[ID][alt], fotos[ID][legenda], fotos[ID][remover]
 *   capa              ID da foto existente ou "nova-N"
 *   fotos_novas[]     arquivos (multipart)
 *   novas_alt[N], novas_legenda[N]
 *   pendentes[token][alt|legenda|remover]  fotos de uma tentativa
 *                     anterior, guardadas na sessão
 *
 * retorno: erros, manter (id => alt/legenda), remover (linhas),
 * novas (arquivo, largura, altura, alt, legenda), capa, resumo
 */
function noticiaFotosPlanoDoFormulario(
    array $fotosAtuais,
    array $post,
    array $files,
    int $ongId,
    bool $podeEnviar,
    array $pendentes = []
): array {
    $erros = [];
    $manter = [];
    $remover = [];
    $novas = [];
    $entrada = is_array($post['fotos'] ?? null) ? $post['fotos'] : [];

    foreach ($fotosAtuais as $foto) {
        $id = (int) $foto['id'];
        $campos = is_array($entrada[$id] ?? null) ? $entrada[$id] : [];

        if (!empty($campos['remover'])) {
            $remover[] = $foto;
            continue;
        }

        $manter[$id] = [
            'alt' => array_key_exists('alt', $campos)
                ? noticiaFotoTextoCurto($campos['alt'], ONGHELP_NOTICIAS_FOTO_ALT_MAX)
                : (string) $foto['texto_alternativo'],
            'legenda' => array_key_exists('legenda', $campos)
                ? noticiaFotoTextoCurto($campos['legenda'], ONGHELP_NOTICIAS_FOTO_LEGENDA_MAX)
                : (string) ($foto['legenda'] ?? ''),
        ];
    }

    // fotos já enviadas numa tentativa anterior que não foi salva
    $entradaPendentes = is_array($post['pendentes'] ?? null) ? $post['pendentes'] : [];

    foreach ($pendentes as $token => $pendente) {
        $campos = is_array($entradaPendentes[$token] ?? null) ? $entradaPendentes[$token] : null;

        if ($campos === null || !empty($campos['remover'])) {
            noticiaFotoApagarArquivo((string) $pendente['arquivo']);
            continue;
        }

        $novas[] = [
            'chave' => 'pend-' . $token,
            'arquivo' => $pendente['arquivo'],
            'largura' => $pendente['largura'],
            'altura' => $pendente['altura'],
            'alt' => noticiaFotoTextoCurto($campos['alt'] ?? '', ONGHELP_NOTICIAS_FOTO_ALT_MAX),
            'legenda' => noticiaFotoTextoCurto($campos['legenda'] ?? '', ONGHELP_NOTICIAS_FOTO_LEGENDA_MAX),
        ];
    }

    $uploads = noticiaFotosNormalizarUploads($files['fotos_novas'] ?? null);

    if ($uploads !== [] && !$podeEnviar) {
        $erros['fotos'] = 'Despublique a notícia para adicionar novas fotos.';
        $uploads = [];
    }

    $vagas = ONGHELP_NOTICIAS_FOTOS_MAX - count($manter) - count($novas);

    if (count($uploads) > $vagas) {
        $erros['fotos'] = 'A notícia pode ter no máximo ' . ONGHELP_NOTICIAS_FOTOS_MAX
            . ' fotos. Remova alguma antes de enviar novas.';
        $uploads = [];
    }

    $altsNovas = is_array($post['novas_alt'] ?? null) ? $post['novas_alt'] : [];
    $legendasNovas = is_array($post['novas_legenda'] ?? null) ? $post['novas_legenda'] : [];
    $errosEnvio = [];

    foreach ($uploads as $upload) {
        $resultado = noticiaFotoSalvarUpload($upload, $ongId);

        if (!$resultado['ok']) {
            $errosEnvio[] = $resultado['erro'];
            continue;
        }

        $indice = $upload['indice'];
        $novas[] = [
            'chave' => 'nova-' . $indice,
            'arquivo' => $resultado['arquivo'],
            'largura' => $resultado['largura'],
            'altura' => $resultado['altura'],
            'alt' => noticiaFotoTextoCurto($altsNovas[$indice] ?? '', ONGHELP_NOTICIAS_FOTO_ALT_MAX),
            'legenda' => noticiaFotoTextoCurto($legendasNovas[$indice] ?? '', ONGHELP_NOTICIAS_FOTO_LEGENDA_MAX),
        ];
    }

    if ($errosEnvio !== []) {
        $erros['fotos'] = implode(' ', $errosEnvio);
    }

    $semAlt = 0;

    foreach (array_merge(array_column($manter, 'alt'), array_column($novas, 'alt')) as $alt) {
        if (mb_strlen($alt, 'UTF-8') < ONGHELP_NOTICIAS_FOTO_ALT_MIN) {
            $semAlt++;
        }
    }

    return [
        'erros' => $erros,
        'manter' => $manter,
        'remover' => $remover,
        'novas' => $novas,
        'capa' => trim((string) ($post['capa'] ?? '')),
        'resumo' => [
            'total' => count($manter) + count($novas),
            'sem_alt' => $semAlt,
        ],
    ];
}

/**
 * grava o plano no banco, deve rodar dentro da transação que salva
 * a notícia, os arquivos removidos só são apagados do disco depois
 * do commit, com noticiaFotosApagarRemovidas()
 */
function noticiaFotosAplicarPlano(PDO $pdo, int $noticiaId, array $plano): void
{
    $remover = $pdo->prepare('DELETE FROM noticias_fotos WHERE id = ? AND noticia_id = ?');
    foreach ($plano['remover'] as $foto) {
        $remover->execute([(int) $foto['id'], $noticiaId]);
    }

    $atualizar = $pdo->prepare(
        'UPDATE noticias_fotos SET texto_alternativo = ?, legenda = ? WHERE id = ? AND noticia_id = ?'
    );

    foreach ($plano['manter'] as $id => $campos) {
        $atualizar->execute([
            $campos['alt'],
            $campos['legenda'] !== '' ? $campos['legenda'] : null,
            (int) $id,
            $noticiaId,
        ]);
    }

    $inserir = $pdo->prepare(
        'INSERT INTO noticias_fotos
            (noticia_id, ordem, arquivo, largura, altura, texto_alternativo, legenda, criado_em)
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    $idsNovas = [];

    foreach ($plano['novas'] as $nova) {
        $inserir->execute([
            $noticiaId,
            ONGHELP_NOTICIAS_FOTOS_MAX,
            $nova['arquivo'],
            $nova['largura'],
            $nova['altura'],
            $nova['alt'],
            $nova['legenda'] !== '' ? $nova['legenda'] : null,
        ]);
        $idsNovas[$nova['chave']] = (int) $pdo->lastInsertId();
    }

    // ordem final: existentes na ordem atual, depois as novas; a
    // capa escolhida vai para o primeiro lugar
    $ordem = array_merge(array_map('intval', array_keys($plano['manter'])), array_values($idsNovas));
    $capa = $plano['capa'];
    $capaId = $idsNovas[$capa] ?? (ctype_digit($capa) ? (int) $capa : 0);

    if ($capaId > 0 && in_array($capaId, $ordem, true)) {
        $ordem = array_merge([$capaId], array_values(array_diff($ordem, [$capaId])));
    }

    $reordenar = $pdo->prepare('UPDATE noticias_fotos SET ordem = ? WHERE id = ? AND noticia_id = ?');

    foreach ($ordem as $posicao => $id) {
        $reordenar->execute([$posicao + 1, $id, $noticiaId]);
    }
}

function noticiaFotosApagarRemovidas(array $plano): void
{
    foreach ($plano['remover'] as $foto) {
        noticiaFotoApagarArquivo((string) $foto['arquivo']);
    }
}
