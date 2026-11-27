<?php
// ============================================================
// tests/suporte.php — peças comuns aos testes
// ============================================================
// cliente HTTP com cookies (Navegador), placar (secao, t) e
// utilitários usados por e2e-test.php e varredura-test.php,
// unidades-test.php usa só o placar
// ============================================================

declare(strict_types=1);

// ------------------------------------------------------------
// cliente HTTP com cookies próprios (cada um é um "navegador")
// ------------------------------------------------------------
final class Navegador
{
    private string $jar;

    public function __construct(private string $base)
    {
        $this->jar = tempnam(sys_get_temp_dir(), 'e2e');
    }

    public function __destruct()
    {
        @unlink($this->jar);
    }

    /** @return array{code:int, body:string, loc:string, type:string} */
    public function req(string $metodo, string $caminho, array $dados = [], array $arquivos = []): array
    {
        $ch = curl_init(str_starts_with($caminho, 'http') ? $caminho : $this->base . $caminho);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEJAR      => $this->jar,
            CURLOPT_COOKIEFILE     => $this->jar,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => 30,
        ]);
        if ($metodo === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($arquivos) {
                $campos = $this->achatar($dados);
                foreach ($arquivos as $nome => $caminhoArquivo) {
                    $campos[$nome] = new CURLFile($caminhoArquivo);
                }
                curl_setopt($ch, CURLOPT_POSTFIELDS, $campos);
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($dados));
            }
        }
        $body = (string) curl_exec($ch);
        $r = [
            'code' => (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE),
            'body' => $body,
            'loc'  => (string) (curl_getinfo($ch, CURLINFO_REDIRECT_URL) ?: ''),
            'type' => (string) (curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: ''),
        ];
        curl_close($ch);
        return $r;
    }

    /** transforma ['a' => ['b' => 1]] em ['a[b]' => 1] para multipart */
    private function achatar(array $dados, string $prefixo = ''): array
    {
        $saida = [];
        foreach ($dados as $k => $v) {
            $chave = $prefixo === '' ? (string) $k : $prefixo . '[' . $k . ']';
            if (is_array($v)) {
                $saida += $this->achatar($v, $chave);
            } else {
                $saida[$chave] = (string) $v;
            }
        }
        return $saida;
    }

    public function get(string $caminho): array
    {
        return $this->req('GET', $caminho);
    }

    /** GET seguindo redirects (até 5), como o navegador faz */
    public function abrir(string $caminho): array
    {
        $r = $this->get($caminho);
        for ($i = 0; $i < 5 && $r['loc'] !== ''; $i++) {
            $r = $this->get($r['loc']);
        }
        return $r;
    }

    public function token(string $caminho): string
    {
        preg_match('/name="_csrf" value="([^"]+)"/', $this->get($caminho)['body'], $m);
        return $m[1] ?? '';
    }

    /** POST com o token CSRF tirado da página do formulário */
    public function enviar(string $acao, array $dados, ?string $paginaDoForm = null, array $arquivos = []): array
    {
        $dados['_csrf'] = $this->token($paginaDoForm ?? $acao);
        return $this->req('POST', $acao, $dados, $arquivos);
    }
}

// ------------------------------------------------------------
// placar
// ------------------------------------------------------------
$total = 0;
$falhas = [];
$secaoAtual = '';

function secao(string $nome): void
{
    global $secaoAtual;
    $secaoAtual = $nome;
    echo "\n== $nome\n";
}

function t(string $nome, bool $ok, string $detalhe = ''): void
{
    global $total, $falhas, $secaoAtual;
    $total++;
    if ($ok) {
        echo "  ok   $nome\n";
        return;
    }
    $falhas[] = "[$secaoAtual] $nome" . ($detalhe !== '' ? " — $detalhe" : '');
    echo "  FALHA $nome" . ($detalhe !== '' ? " — $detalhe" : '') . "\n";
}

function tem(array $r, string $texto): bool
{
    return str_contains($r['body'], $texto);
}

function semErroPhp(array $r): bool
{
    return !preg_match('/(Fatal error|Warning:|Notice:|Deprecated:|Uncaught)/', $r['body']);
}

function cnpjValido(int $semente): string
{
    $base = str_pad((string) ($semente % 100000000), 8, '0', STR_PAD_LEFT) . '0001';
    foreach ([[5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2], [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]] as $pesos) {
        $soma = 0;
        foreach ($pesos as $i => $p) {
            $soma += (int) $base[$i] * $p;
        }
        $resto = $soma % 11;
        $base .= $resto < 2 ? '0' : (string) (11 - $resto);
    }
    return $base;
}

/** imprime o resumo e encerra com código 1 se algo falhou */
function placarFinal(): never
{
    global $total, $falhas;
    echo "\n" . str_repeat('=', 60) . "\n";
    echo "Testes: $total | Falhas: " . count($falhas) . "\n";
    foreach ($falhas as $f) {
        echo "  - $f\n";
    }
    exit($falhas ? 1 : 0);
}
