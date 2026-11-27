<?php
// ============================================================
// includes/core/senha.php — regras de senha das ONGs e da equipe
// ============================================================
//
// as mesmas regras valem no servidor e na checagem ao vivo do
// navegador (assets/js/core/senha.js), que recebe a lista de senhas
// comuns pelo atributo data-senhas-comuns do formulário
//
// só senhas novas passam por aqui (cadastro, troca de senha e
// primeiro acesso da equipe), quem já tem conta continua entrando
// com a senha que tem
// ============================================================

const SENHA_MINIMO_ONG    = 8;
const SENHA_MINIMO_EQUIPE = 10;

// o password_hash() usa bcrypt, que só lê os primeiros 72 bytes,
// acima disso dois finais diferentes dariam a mesma senha
const SENHA_MAXIMO_BYTES = 72;

/**
 * senhas fáceis de adivinhar que passariam nas outras regras,
 * comparadas já normalizadas (minúsculas, só letras e números)
 */
function senhasComuns(): array
{
    return [
        'senha123', 'senha1234', 'senha12345', 'senha123456', 'minhasenha1', 'minhasenha123',
        'password1', 'password12', 'password123', 'passw0rd', 'abc12345', 'abcd1234', 'abc123456',
        'a1234567', 'a12345678', '1234abcd', '12345abc', 'qwerty123', 'qwerty12', 'q1w2e3r4',
        'q1w2e3r4t5', '1q2w3e4r', 'asdf1234', 'zxcv1234', 'admin123', 'admin1234', 'teste123',
        'teste1234', 'mudar123', 'mudar1234', 'brasil123', 'brasil2026', 'iloveyou1', 'welcome1',
        'onghelp1', 'onghelp123', 'onghelp2026', 'ong12345', 'ong123456', 'doacao123', 'projeto123',
    ];
}

/** minúsculas e só letras e números, igual à função normalizar() do senha.js */
function senhaNormalizada(string $senha): string
{
    return preg_replace('/[^\p{L}\p{N}]/u', '', mb_strtolower($senha, 'UTF-8')) ?? '';
}

/**
 * confere a senha nova item por item, a chave de cada item é a mesma
 * do atributo data-check da lista de requisitos na tela
 */
function requisitosSenha(string $senha, string $confirmacao, string $email, int $minimo): array
{
    $email = mb_strtolower(trim($email), 'UTF-8');
    $local = explode('@', $email)[0] ?? '';
    $senhaMinuscula = mb_strtolower($senha, 'UTF-8');

    // a parte antes do @ só conta com 3 letras ou mais, senão "ana@"
    // impediria qualquer senha com "ana"
    $semEmail = $senha !== '' && ($email === ''
        || (!str_contains($senhaMinuscula, $email)
            && (mb_strlen($local, 'UTF-8') < 3 || !str_contains($senhaMinuscula, $local))));

    return [
        'minimo'       => mb_strlen($senha, 'UTF-8') >= $minimo,
        'maximo'       => strlen($senha) <= SENHA_MAXIMO_BYTES,
        'letra_numero' => preg_match('/\p{L}/u', $senha) === 1 && preg_match('/\d/', $senha) === 1,
        'sem_email'    => $semEmail,
        'nao_comum'    => $senha !== ''
            && !in_array(senhaNormalizada($senha), senhasComuns(), true)
            && preg_match('/^(.)\1*$/u', $senha) !== 1,
        'iguais'       => $senha !== '' && $senha === $confirmacao,
    ];
}

function senhaValida(array $requisitos): bool
{
    return !in_array(false, $requisitos, true);
}

/** textos da lista de requisitos mostrada embaixo do campo */
function senhaRotulos(int $minimo): array
{
    return [
        'minimo'               => 'Mínimo de ' . $minimo . ' caracteres',
        'letra_numero'         => 'Letras e números',
        'sem_email'            => 'Não contém o seu e-mail',
        'nao_comum'            => 'Não é uma senha comum',
        'iguais'               => 'As duas senhas são iguais',
        'diferente_atual'      => 'Diferente da senha atual',
        'diferente_temporaria' => 'Diferente da senha temporária',
    ];
}

/** mensagem do primeiro requisito que falhou, para os avisos de erro */
function senhaPrimeiroErro(array $requisitos, int $minimo): string
{
    $mensagens = [
        'minimo'               => 'A senha deve ter pelo menos ' . $minimo . ' caracteres.',
        'maximo'               => 'A senha pode ter no máximo ' . SENHA_MAXIMO_BYTES . ' caracteres.',
        'letra_numero'         => 'A senha precisa ter letras e números.',
        'sem_email'            => 'A senha não pode conter o seu e-mail.',
        'nao_comum'            => 'Essa senha é muito comum. Escolha outra mais difícil de adivinhar.',
        'iguais'               => 'As senhas não coincidem.',
        'diferente_atual'      => 'A nova senha precisa ser diferente da senha atual.',
        'diferente_temporaria' => 'A nova senha precisa ser diferente da senha temporária.',
    ];

    foreach ($requisitos as $chave => $ok) {
        if (!$ok) {
            return $mensagens[$chave] ?? 'Revise os requisitos da senha.';
        }
    }

    return '';
}

/**
 * atributos do formulário lidos pelo senha.js, $minimo e a lista de
 * senhas comuns saem daqui para o navegador usar as mesmas regras
 */
function senhaAtributosFormulario(int $minimo, string $email = ''): string
{
    $atributos = ' data-password-form data-min="' . $minimo . '"'
        . ' data-senhas-comuns="' . htmlspecialchars(json_encode(senhasComuns()), ENT_QUOTES, 'UTF-8') . '"';

    if ($email !== '') {
        $atributos .= ' data-email="' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '"';
    }

    return $atributos;
}

/**
 * lista de requisitos marcada pelo senha.js enquanto a pessoa digita
 *
 * $itens liga a chave do servidor ao data-check do navegador
 * (ex.: 'sem_email' => 'email'), $estado vem de requisitosSenha()
 * quando a página volta de um envio, e $classes troca o visual
 * conforme a tela (cadastro, painel da ONG ou painel da equipe)
 */
function senhaChecklist(array $itens, int $minimo, array $estado = [], array $classes = []): string
{
    $classeLista = $classes['lista'] ?? 'auth-senha-checklist';
    $classeItem  = $classes['item'] ?? 'auth-senha-item';
    $rotulos     = senhaRotulos($minimo);

    $html = '<ul class="' . htmlspecialchars($classeLista, ENT_QUOTES, 'UTF-8') . '" aria-label="Requisitos da senha">';

    foreach ($itens as $chave => $check) {
        $ok = !empty($estado[$chave]);
        $html .= '<li class="' . trim($classeItem . ($ok ? ' is-ok' : '')) . '" data-check="' . $check . '">'
            . '<i class="bi ' . ($ok ? 'bi-check-circle-fill' : 'bi-circle') . '" aria-hidden="true"></i>'
            . '<span>' . htmlspecialchars($rotulos[$chave] ?? $chave, ENT_QUOTES, 'UTF-8') . '</span></li>';
    }

    return $html . '</ul>';
}
