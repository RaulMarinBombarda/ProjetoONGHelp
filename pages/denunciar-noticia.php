<?php
// ============================================================
// pages/denunciar-noticia.php — Registro de denúncia pública
// ============================================================

require_once '../includes/db.php';
require_once '../includes/helpers.php';
require_once '../includes/session.php';
require_once '../includes/csrf.php';
require_once '../includes/noticias.php';
require_once '../includes/noticias_ui.php';

$metodo = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($metodo !== 'POST') {
    header('Location: noticias-publicas.php', true, 302);
    exit;
}

$noticiaId = isset($_POST['noticia_id']) && ctype_digit((string) $_POST['noticia_id'])
    ? (int) $_POST['noticia_id']
    : 0;
$noticia = $noticiaId > 0 ? buscarNoticiaPublica($pdo, (string) $noticiaId) : null;

if (!$noticia) {
    header('Location: noticia.php?id=' . $noticiaId, true, 302);
    exit;
}

$voltar = noticiaUrlPublica($noticia) . '#denunciar';

if (!csrfValidar()) {
    flashSet('denuncia_erro', 'Sua sessão expirou. Recarregue a página e tente novamente.');
    header('Location: ' . $voltar, true, 302);
    exit;
}

$motivos = noticiasMotivosDenuncia();
$motivo = trim((string) ($_POST['motivo'] ?? ''));

if (!isset($motivos[$motivo])) {
    flashSet('denuncia_erro', 'Selecione um motivo para a denúncia.');
    header('Location: ' . $voltar, true, 302);
    exit;
}

$detalhe = trim((string) ($_POST['detalhe'] ?? ''));
$detalhe = mb_substr($detalhe, 0, 300, 'UTF-8');
$email = trim((string) ($_POST['email'] ?? ''));

if ($email !== '') {
    if (mb_strlen($email, 'UTF-8') > 160 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        flashSet('denuncia_erro', 'Informe um e-mail válido ou deixe o campo em branco.');
        header('Location: ' . $voltar, true, 302);
        exit;
    }
}

$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$userAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
$hashDenunciante = noticiaHashDenunciante($ip, $userAgent);

if (noticiaDenunciaDuplicada($pdo, $noticiaId, $hashDenunciante)) {
    flashSet('denuncia_erro', 'Você já denunciou esta notícia.');
    header('Location: ' . $voltar, true, 302);
    exit;
}

$limite = noticiaDenunciasLimiteHora();

if (noticiaDenunciasUltimaHora($pdo, $hashDenunciante) >= $limite) {
    flashSet(
        'denuncia_erro',
        'Você atingiu o limite de ' . $limite . ' denúncias por hora. Tente novamente mais tarde.'
    );
    header('Location: ' . $voltar, true, 302);
    exit;
}

try {
    noticiaRegistrarDenuncia($pdo, $noticiaId, $motivo, $detalhe, $email, $hashDenunciante);
} catch (PDOException $erro) {
    flashSet('denuncia_erro', 'Não foi possível registrar sua denúncia agora. Tente novamente.');
    header('Location: ' . $voltar, true, 302);
    exit;
}

// Registro de moderação: a autoria é "Visitante", por isso admin_id
// fica nulo. O detalhe guarda a ONG e o motivo, mas nunca o e-mail do
// denunciante. Uma falha no log não desfaz a denúncia já aceita.
try {
    $stmtContexto = $pdo->prepare(
        'SELECT n.titulo, u.nome AS ong_nome
         FROM noticias n
         INNER JOIN usuarios u ON u.id = n.usuario_id
         WHERE n.id = ? LIMIT 1'
    );
    $stmtContexto->execute([$noticiaId]);
    $contexto = $stmtContexto->fetch() ?: ['titulo' => (string) $noticia['titulo'], 'ong_nome' => (string) $noticia['ong_nome']];

    $pdo->prepare(
        "INSERT INTO logs_admin (admin_id, acao, alvo_tipo, alvo_id, alvo_nome, detalhe, ip)
         VALUES (NULL, 'denuncia_recebida', 'noticia', ?, ?, ?, ?)"
    )->execute([
        $noticiaId,
        (string) $contexto['titulo'],
        'ONG: ' . (string) $contexto['ong_nome'] . ' · Motivo: ' . noticiaRotuloMotivo($motivo) . '.',
        $ip,
    ]);
} catch (Throwable $erroLog) {
    error_log('[ONGHelp] Log de denúncia recebida: ' . $erroLog->getMessage());
}

flashSet(
    'denuncia_sucesso',
    'Recebemos sua denúncia. Obrigado por ajudar a manter o ONGHelp confiável.'
);
header('Location: ' . $voltar, true, 303);
exit;
