<?php
// ============================================================
// tests/noticias_parte1_teste.php — Teste da fundação de Notícias
// ============================================================
//
// Execução:
//   C:\xampp\php\php.exe tests\noticias_parte1_teste.php
//
// O teste roda dentro de uma transação e desfaz tudo ao final.
// Requer o banco importado de sql/onghelp.sql.

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/noticias.php';

$total = 0;
$falhas = 0;

function teste(string $nome, bool $ok, string $detalhe = ''): void
{
    global $total, $falhas;

    $total++;

    if ($ok) {
        echo "PASS  {$nome}\n";
        return;
    }

    $falhas++;
    echo "FAIL  {$nome}" . ($detalhe !== '' ? " — {$detalhe}" : '') . "\n";
}

function erroContem(array $resultado, string $campo, string $trecho): bool
{
    return isset($resultado['erros'][$campo])
        && str_contains((string) $resultado['erros'][$campo], $trecho);
}

function criarOng(PDO $pdo, string $nome, string $status): int
{
    $sufixo = bin2hex(random_bytes(6));
    $cnpj = '9' . str_pad((string) random_int(0, 9999999999999), 13, '0', STR_PAD_LEFT);

    $stmt = $pdo->prepare(
        'INSERT INTO usuarios (nome, cnpj, email, senha, area, status, pix_chave)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $nome,
        $cnpj,
        'teste-noticias-' . $sufixo . '@onghelp.test',
        password_hash('Teste@123', PASSWORD_DEFAULT),
        'Teste',
        $status,
        '',
    ]);

    return (int) $pdo->lastInsertId();
}

function criarProjeto(PDO $pdo, int $ongId, string $nome): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO projetos (usuario_id, nome, indicador, unidade) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$ongId, $nome, 10, 'pessoas']);

    return (int) $pdo->lastInsertId();
}

function criarMovimentacao(PDO $pdo, int $ongId, string $descricao): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO movimentacoes (usuario_id, tipo, descricao, valor, data_mov)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$ongId, 'despesa', $descricao, 100.00, date('Y-m-d')]);

    return (int) $pdo->lastInsertId();
}

function criarLinkNoticia(PDO $pdo, int $noticiaId, string $url): int
{
    $info = noticiaNormalizarLink($url);

    if (!$info['ok']) {
        throw new RuntimeException('Link de teste inválido: ' . $info['erro']);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO noticias_links (noticia_id, ordem, url, dominio, servico)
         VALUES (?, 1, ?, ?, ?)'
    );
    $stmt->execute([$noticiaId, $info['url'], $info['dominio'], $info['servico']]);

    return (int) $pdo->lastInsertId();
}

function criarNoticia(
    PDO $pdo,
    int $ongId,
    string $estado,
    string $titulo,
    string $resumo,
    string $texto,
    string $tipo = 'acao_realizada',
    bool $comFoto = true,
    ?string $publicadaEm = null
): int {
    $slug = 'teste-noticias-' . bin2hex(random_bytes(8));
    $aceite = $estado === 'publicada' ? date('Y-m-d H:i:s') : null;

    $stmt = $pdo->prepare(
        'INSERT INTO noticias
            (usuario_id, titulo, resumo, texto, tipo, estado, slug, publicada_em, direitos_imagem_aceito_em)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $ongId,
        $titulo,
        $resumo,
        $texto,
        $tipo,
        $estado,
        $slug,
        $publicadaEm ?? ($estado === 'publicada' ? date('Y-m-d H:i:s') : null),
        $aceite,
    ]);

    $noticiaId = (int) $pdo->lastInsertId();

    if ($comFoto) {
        $stmt = $pdo->prepare(
            'INSERT INTO noticias_fotos
                (noticia_id, ordem, arquivo, largura, altura, texto_alternativo)
             VALUES (?, 1, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $noticiaId,
            'noticias/teste-' . bin2hex(random_bytes(6)) . '.jpg',
            1200,
            800,
            'Descrição de teste da foto',
        ]);
    }

    return $noticiaId;
}

$pdo->beginTransaction();

try {
    // --------------------------------------------------------
    // Fixtures
    // --------------------------------------------------------
    $ongA = criarOng($pdo, 'ONG Teste A', 'aprovada');
    $ongB = criarOng($pdo, 'ONG Teste B', 'aprovada');
    $ongSuspensa = criarOng($pdo, 'ONG Teste Suspensa', 'suspensa');
    $ongLimite = criarOng($pdo, 'ONG Teste Limite', 'aprovada');

    $projetoA = criarProjeto($pdo, $ongA, 'Projeto da ONG A');
    $projetoB = criarProjeto($pdo, $ongB, 'Projeto da ONG B');
    $movimentacaoA = criarMovimentacao($pdo, $ongA, 'Movimentação da ONG A');
    $movimentacaoB = criarMovimentacao($pdo, $ongB, 'Movimentação da ONG B');

    $noticiaDraft = criarNoticia(
        $pdo,
        $ongA,
        'rascunho',
        'Rascunho de teste',
        'Resumo do rascunho de teste com tamanho suficiente.',
        '<p>' . str_repeat('Texto do rascunho de teste. ', 6) . '</p>'
    );

    $dadosValidos = [
        'titulo' => 'Título de teste válido',
        'resumo' => 'Resumo de teste com tamanho suficiente para validar.',
        'texto' => '<p>' . str_repeat('Texto válido para o teste da notícia. ', 5) . '</p>',
        'tipo' => 'acao_realizada',
        'projeto_id' => '',
        'movimentacao_id' => '',
        'evento_data' => '',
        'evento_local' => '',
        'links' => [],
        'direitos_imagem' => true,
    ];

    // --------------------------------------------------------
    // 1. Limites de tamanho
    // --------------------------------------------------------
    $resultado = validarNoticia($pdo, $ongA, $dadosValidos, true, $noticiaDraft);
    teste('notícia válida passa na publicação', $resultado['ok'], implode(' | ', $resultado['erros']));

    $curto = $dadosValidos;
    $curto['titulo'] = str_repeat('a', ONGHELP_NOTICIAS_TITULO_MIN - 1);
    $resultado = validarNoticia($pdo, $ongA, $curto, true, $noticiaDraft);
    teste('título abaixo do mínimo é recusado', erroContem($resultado, 'titulo', 'entre'));

    $longo = $dadosValidos;
    $longo['titulo'] = str_repeat('a', ONGHELP_NOTICIAS_TITULO_MAX + 1);
    $resultado = validarNoticia($pdo, $ongA, $longo, true, $noticiaDraft);
    teste('título acima do máximo é recusado', erroContem($resultado, 'titulo', 'entre'));

    $curto = $dadosValidos;
    $curto['resumo'] = str_repeat('a', ONGHELP_NOTICIAS_RESUMO_MIN - 1);
    $resultado = validarNoticia($pdo, $ongA, $curto, true, $noticiaDraft);
    teste('resumo abaixo do mínimo é recusado', erroContem($resultado, 'resumo', 'entre'));

    $longo = $dadosValidos;
    $longo['resumo'] = str_repeat('a', ONGHELP_NOTICIAS_RESUMO_MAX + 1);
    $resultado = validarNoticia($pdo, $ongA, $longo, true, $noticiaDraft);
    teste('resumo acima do máximo é recusado', erroContem($resultado, 'resumo', 'entre'));

    $curto = $dadosValidos;
    $curto['texto'] = '<p>' . str_repeat('a', ONGHELP_NOTICIAS_TEXTO_MIN - 1) . '</p>';
    $resultado = validarNoticia($pdo, $ongA, $curto, true, $noticiaDraft);
    teste('texto abaixo do mínimo é recusado', erroContem($resultado, 'texto', 'entre'));

    $longo = $dadosValidos;
    $longo['texto'] = '<p>' . str_repeat('a', ONGHELP_NOTICIAS_TEXTO_MAX + 1) . '</p>';
    $resultado = validarNoticia($pdo, $ongA, $longo, true, $noticiaDraft);
    teste('texto acima do máximo é recusado', erroContem($resultado, 'texto', 'entre'));

    // --------------------------------------------------------
    // 2. Tipo obrigatório e Evento
    // --------------------------------------------------------
    $semTipo = $dadosValidos;
    $semTipo['tipo'] = '';
    $resultado = validarNoticia($pdo, $ongA, $semTipo, true, $noticiaDraft);
    teste('tipo vazio é recusado ao publicar', erroContem($resultado, 'tipo', 'tipo'));

    $tipoInvalido = $dadosValidos;
    $tipoInvalido['tipo'] = 'tipo_inexistente';
    $resultado = validarNoticia($pdo, $ongA, $tipoInvalido, true, $noticiaDraft);
    teste('tipo inválido é recusado', erroContem($resultado, 'tipo', 'válido'));

    $evento = $dadosValidos;
    $evento['tipo'] = 'evento';
    $resultado = validarNoticia($pdo, $ongA, $evento, true, $noticiaDraft);
    teste('evento sem data e local é recusado', isset($resultado['erros']['evento_data'], $resultado['erros']['evento_local']));

    $evento['evento_data'] = '2026-12-01T18:30';
    $evento['evento_local'] = 'Salão comunitário';
    $resultado = validarNoticia($pdo, $ongA, $evento, true, $noticiaDraft);
    teste('evento com data e local passa', $resultado['ok'], implode(' | ', $resultado['erros']));

    // --------------------------------------------------------
    // 3. Formatação segura
    // --------------------------------------------------------
    $sujo = '<p style="color:red">Olá <strong>mundo</strong>'
        . '<script>alert(1)</script>'
        . '<a href="javascript:alert(1)">clique</a>'
        . '<img src="x" onerror="alert(1)">'
        . '<em>itálico</em></p>';

    $limpo = sanitizarHtmlNoticia($sujo);

    teste('sanitização mantém negrito e itálico', str_contains($limpo, '<strong>mundo</strong>') && str_contains($limpo, '<em>itálico</em>'));
    teste('sanitização remove script', !str_contains($limpo, '<script'));
    teste('sanitização remove javascript:', !str_contains($limpo, 'javascript:'));
    teste('sanitização remove img/onerror', !str_contains($limpo, '<img') && !str_contains($limpo, 'onerror'));
    teste('sanitização remove atributo style', !str_contains($limpo, 'style='));

    $linkSeguro = sanitizarHtmlNoticia('<p><a href="https://instagram.com/ong">Instagram</a></p>');
    teste('link http/https recebe target e rel seguros', str_contains($linkSeguro, 'href="https://instagram.com/ong"')
        && str_contains($linkSeguro, 'target="_blank"')
        && str_contains($linkSeguro, 'rel="noopener noreferrer nofollow"'));

    // --------------------------------------------------------
    // 4. Anti-PIX
    // --------------------------------------------------------
    teste(
        'chave PIX perto de doação é detectada',
        noticiaDetectarPix(
            'Doação via PIX',
            'Ajude nossa ONG',
            '<p>Chave PIX: 123.456.789-00</p>',
            []
        )
    );

    $comPix = $dadosValidos;
    $comPix['titulo'] = 'Doação via PIX';
    $comPix['texto'] = '<p>Chave PIX: 123.456.789-00. '
        . str_repeat('Ajude nossa causa com uma doação. ', 4)
        . '</p>';
    $resultado = validarNoticia($pdo, $ongA, $comPix, true, $noticiaDraft);
    teste(
        'publicação com PIX mostra a mensagem oficial',
        ($resultado['erros']['pix'] ?? '') === ONGHELP_NOTICIAS_PIX_MENSAGEM
    );

    teste(
        'link de pagamento/encurtador é bloqueado',
        noticiaDetectarPix('Campanha', 'Resumo', '<p>Texto</p>', ['https://bit.ly/abc'])
    );

    teste(
        'link normal não é bloqueado',
        !noticiaDetectarPix('Notícia', 'Resumo', '<p>Plantamos 100 mudas no bairro.</p>', ['https://instagram.com/ong'])
    );

    // --------------------------------------------------------
    // 5. Vínculo com projeto/movimentação de outra ONG
    // --------------------------------------------------------
    $vinculo = $dadosValidos;
    $vinculo['projeto_id'] = (string) $projetoB;
    $resultado = validarNoticia($pdo, $ongA, $vinculo, true, $noticiaDraft);
    teste('projeto de outra ONG é recusado', erroContem($resultado, 'projeto', 'não pertence'));

    $vinculo = $dadosValidos;
    $vinculo['movimentacao_id'] = (string) $movimentacaoB;
    $resultado = validarNoticia($pdo, $ongA, $vinculo, true, $noticiaDraft);
    teste('movimentação de outra ONG é recusada', erroContem($resultado, 'movimentacao', 'não pertence'));

    $vinculo = $dadosValidos;
    $vinculo['projeto_id'] = (string) $projetoA;
    $resultado = validarNoticia($pdo, $ongA, $vinculo, true, $noticiaDraft);
    teste('projeto da própria ONG é aceito', !isset($resultado['erros']['projeto']));

    $vinculo = $dadosValidos;
    $vinculo['projeto_id'] = (string) $projetoA;
    $vinculo['movimentacao_id'] = (string) $movimentacaoA;
    $resultado = validarNoticia($pdo, $ongA, $vinculo, true, $noticiaDraft);
    teste('projeto e movimentação ao mesmo tempo são recusados', erroContem($resultado, 'vinculo', 'OU'));

    // --------------------------------------------------------
    // 6. Direitos de imagem, links e fotos
    // --------------------------------------------------------
    $semDireitos = $dadosValidos;
    unset($semDireitos['direitos_imagem']);
    $resultado = validarNoticia($pdo, $ongA, $semDireitos, true, $noticiaDraft);
    teste('aceite de direitos de imagem é obrigatório', erroContem($resultado, 'direitos_imagem', 'autorização'));

    $quatroLinks = $dadosValidos;
    $quatroLinks['links'] = [
        'https://instagram.com/ong',
        'https://facebook.com/ong',
        'https://youtube.com/@ong',
        'https://ong.example.org',
    ];
    $resultado = validarNoticia($pdo, $ongA, $quatroLinks, true, $noticiaDraft);
    teste('mais de 3 links é recusado', erroContem($resultado, 'links', 'máximo'));

    $semFoto = criarNoticia(
        $pdo,
        $ongA,
        'rascunho',
        'Rascunho sem foto',
        'Resumo do rascunho sem foto com tamanho suficiente.',
        '<p>' . str_repeat('Texto do rascunho sem foto. ', 6) . '</p>',
        'acao_realizada',
        false
    );
    $resultado = validarNoticia($pdo, $ongA, $dadosValidos, true, $semFoto);
    teste('publicação sem foto é recusada', erroContem($resultado, 'fotos', 'ao menos'));

    // --------------------------------------------------------
    // 7. Visibilidade central com ONG suspensa
    // --------------------------------------------------------
    $publicadaSuspensa = criarNoticia(
        $pdo,
        $ongSuspensa,
        'publicada',
        'Notícia de ONG suspensa',
        'Resumo da notícia de ONG suspensa com tamanho suficiente.',
        '<p>' . str_repeat('Texto da notícia de ONG suspensa. ', 6) . '</p>'
    );

    teste(
        'notícia de ONG suspensa não é visível',
        buscarNoticiaPublica($pdo, (string) $publicadaSuspensa) === null
    );

    $pdo->prepare("UPDATE usuarios SET status = 'aprovada' WHERE id = ?")->execute([$ongSuspensa]);
    teste(
        'notícia volta ao aprovar a ONG',
        buscarNoticiaPublica($pdo, (string) $publicadaSuspensa) !== null
    );

    $pdo->prepare("UPDATE usuarios SET status = 'suspensa' WHERE id = ?")->execute([$ongSuspensa]);
    teste(
        'notícia some ao suspender novamente',
        buscarNoticiaPublica($pdo, (string) $publicadaSuspensa) === null
    );

    $rascunho = criarNoticia(
        $pdo,
        $ongA,
        'rascunho',
        'Rascunho invisível',
        'Resumo do rascunho invisível com tamanho suficiente.',
        '<p>' . str_repeat('Texto do rascunho invisível. ', 6) . '</p>'
    );
    teste('rascunho não é visível publicamente', buscarNoticiaPublica($pdo, (string) $rascunho) === null);

    $oculta = criarNoticia(
        $pdo,
        $ongA,
        'oculta',
        'Notícia oculta',
        'Resumo da notícia oculta com tamanho suficiente.',
        '<p>' . str_repeat('Texto da notícia oculta. ', 6) . '</p>'
    );
    teste('notícia oculta não é visível publicamente', buscarNoticiaPublica($pdo, (string) $oculta) === null);

    $publicadaA = criarNoticia(
        $pdo,
        $ongA,
        'publicada',
        'Notícia publicada da ONG A',
        'Resumo da notícia publicada da ONG A com tamanho suficiente.',
        '<p>' . str_repeat('Texto da notícia publicada da ONG A. ', 6) . '</p>'
    );
    teste('notícia publicada de ONG aprovada é visível', buscarNoticiaPublica($pdo, (string) $publicadaA) !== null);

    $pdo->prepare("UPDATE usuarios SET status = 'suspensa' WHERE id = ?")->execute([$ongA]);
    teste('notícia some ao suspender a ONG', buscarNoticiaPublica($pdo, (string) $publicadaA) === null);
    $pdo->prepare("UPDATE usuarios SET status = 'aprovada' WHERE id = ?")->execute([$ongA]);

    // --------------------------------------------------------
    // 7b. Autoria, despublicação e exclusão
    // --------------------------------------------------------
    teste(
        'notícia de outra ONG não é encontrada por autoria',
        buscarNoticiaDaOng($pdo, $publicadaA, $ongB) === null
    );

    $despublicar = criarNoticia(
        $pdo,
        $ongA,
        'publicada',
        'Notícia para despublicar',
        'Resumo da notícia para despublicar com tamanho suficiente.',
        '<p>' . str_repeat('Texto da notícia para despublicar. ', 6) . '</p>'
    );

    $resultado = despublicarNoticiaDaOng($pdo, $despublicar, $ongA);
    $aposDespublicar = buscarNoticiaDaOng($pdo, $despublicar, $ongA);
    teste(
        'ONG despublica a própria notícia e ela volta a rascunho',
        $resultado['ok'] && ($aposDespublicar['estado'] ?? '') === 'rascunho'
    );

    $ocultaComMidia = criarNoticia(
        $pdo,
        $ongA,
        'oculta',
        'Notícia oculta com mídia',
        'Resumo da notícia oculta com mídia e tamanho suficiente.',
        '<p>' . str_repeat('Texto da notícia oculta com mídia. ', 6) . '</p>'
    );
    criarLinkNoticia($pdo, $ocultaComMidia, 'https://instagram.com/ong');

    $resultado = excluirNoticiaDaOng($pdo, $ocultaComMidia, $ongA);
    teste('notícia oculta não pode ser excluída', !$resultado['ok']);

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM noticias_fotos WHERE noticia_id = ?');
    $stmt->execute([$ocultaComMidia]);
    $fotosOculta = (int) $stmt->fetchColumn();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM noticias_links WHERE noticia_id = ?');
    $stmt->execute([$ocultaComMidia]);
    $linksOculta = (int) $stmt->fetchColumn();
    teste('fotos e links da notícia oculta permanecem', $fotosOculta === 1 && $linksOculta === 1);

    $resultado = despublicarNoticiaDaOng($pdo, $ocultaComMidia, $ongA);
    teste('notícia oculta não pode ser despublicada', !$resultado['ok']);

    $resultado = validarNoticia($pdo, $ongA, $dadosValidos, true, $ocultaComMidia);
    teste('notícia oculta não pode ser editada', erroContem($resultado, 'noticia', 'oculta'));

    $excluir = criarNoticia(
        $pdo,
        $ongA,
        'publicada',
        'Notícia para excluir',
        'Resumo da notícia para excluir com tamanho suficiente.',
        '<p>' . str_repeat('Texto da notícia para excluir. ', 6) . '</p>'
    );
    criarLinkNoticia($pdo, $excluir, 'https://instagram.com/ong');

    $resultado = excluirNoticiaDaOng($pdo, $excluir, $ongA);
    teste('ONG exclui a própria notícia', $resultado['ok'] && buscarNoticiaDaOng($pdo, $excluir, $ongA) === null);

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM noticias_fotos WHERE noticia_id = ?');
    $stmt->execute([$excluir]);
    $fotosExcluir = (int) $stmt->fetchColumn();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM noticias_links WHERE noticia_id = ?');
    $stmt->execute([$excluir]);
    $linksExcluir = (int) $stmt->fetchColumn();
    teste('exclusão remove fotos e links por cascata', $fotosExcluir === 0 && $linksExcluir === 0);

    // --------------------------------------------------------
    // 8. Bloqueio de publicação da ONG
    // --------------------------------------------------------
    bloquearPublicacoesNoticias($pdo, $ongA, 'Teste de bloqueio de publicações', 1);
    $elegibilidade = ongPodePublicarNoticias($pdo, $ongA);
    teste('ONG bloqueada não pode publicar', !$elegibilidade['pode']);

    $resultado = validarNoticia($pdo, $ongA, $dadosValidos, true, $noticiaDraft);
    teste('validação recusa publicação de ONG bloqueada', erroContem($resultado, 'ong', 'bloqueadas'));

    liberarPublicacoesNoticias($pdo, $ongA, 1);
    $elegibilidade = ongPodePublicarNoticias($pdo, $ongA);
    teste('ONG liberada volta a poder publicar', $elegibilidade['pode']);

    // --------------------------------------------------------
    // 9. Limite diário de publicações
    // --------------------------------------------------------
    for ($i = 1; $i <= ONGHELP_NOTICIAS_PUBLICACOES_DIA; $i++) {
        criarNoticia(
            $pdo,
            $ongLimite,
            'publicada',
            'Notícia publicada ' . $i,
            'Resumo da notícia publicada número ' . $i . ' com tamanho suficiente.',
            '<p>' . str_repeat('Texto da notícia publicada número ' . $i . '. ', 6) . '</p>'
        );
    }

    $draftLimite = criarNoticia(
        $pdo,
        $ongLimite,
        'rascunho',
        'Rascunho no limite',
        'Resumo do rascunho no limite com tamanho suficiente.',
        '<p>' . str_repeat('Texto do rascunho no limite. ', 6) . '</p>'
    );

    $resultado = validarNoticia($pdo, $ongLimite, $dadosValidos, true, $draftLimite);
    teste('limite de 3 publicações por dia é aplicado', erroContem($resultado, 'limite', 'limite'));

    $ongRepublicar = criarOng($pdo, 'ONG Teste Republicar', 'aprovada');

    for ($i = 1; $i <= 2; $i++) {
        criarNoticia(
            $pdo,
            $ongRepublicar,
            'publicada',
            'Notícia publicada ' . $i,
            'Resumo da notícia publicada número ' . $i . ' com tamanho suficiente.',
            '<p>' . str_repeat('Texto da notícia publicada número ' . $i . '. ', 6) . '</p>'
        );
    }

    $republicar = criarNoticia(
        $pdo,
        $ongRepublicar,
        'publicada',
        'Notícia já publicada hoje',
        'Resumo da notícia já publicada hoje com tamanho suficiente.',
        '<p>' . str_repeat('Texto da notícia já publicada hoje. ', 6) . '</p>'
    );
    $pdo->prepare("UPDATE noticias SET estado = 'rascunho' WHERE id = ?")->execute([$republicar]);

    $resultado = validarNoticia($pdo, $ongRepublicar, $dadosValidos, true, $republicar);
    teste(
        'republicar a mesma notícia não conta duas vezes no limite diário',
        $resultado['ok'],
        implode(' | ', $resultado['erros'])
    );

    // --------------------------------------------------------
    // 10. Tempo de leitura e links
    // --------------------------------------------------------
    teste('200 palavras = 1 minuto de leitura', noticiaTempoLeitura(trim(str_repeat('palavra ', 200))) === 1);
    teste('201 palavras = 2 minutos de leitura', noticiaTempoLeitura(trim(str_repeat('palavra ', 201))) === 2);

    $link = noticiaNormalizarLink('instagram.com/ong');
    teste('domínio é identificado e exibido', $link['ok'] && $link['dominio'] === 'instagram.com' && $link['servico'] === 'instagram');

    $link = noticiaNormalizarLink('https://www.youtube.com/@ong');
    teste('YouTube é identificado pelo domínio', $link['ok'] && $link['servico'] === 'youtube');

    $link = noticiaNormalizarLink('javascript:alert(1)');
    teste('link javascript é recusado', !$link['ok']);
} catch (Throwable $erro) {
    $falhas++;
    echo 'ERRO  ' . $erro->getMessage() . "\n";
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

echo "\n";
echo "Testes: {$total} | Falhas: {$falhas}\n";
exit($falhas === 0 ? 0 : 1);
