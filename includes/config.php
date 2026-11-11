<?php
// ============================================================
// includes/config.php — Configurações públicas do site
// ============================================================

/**
 * Único ponto de configuração do WhatsApp da equipe.
 * Use apenas dígitos, com DDI e DDD (formato E.164 sem o "+").
 */
const ONGHELP_WHATSAPP_EQUIPE = '5516997792777';

const ONGHELP_WHATSAPP_MENSAGEM_EQUIPE =
    'Olá! Quero fazer parte da equipe de moderação do ONGHelp. Nome: / Cidade:';

function whatsappEquipeNumeroE164(): string
{
    return '+' . ONGHELP_WHATSAPP_EQUIPE;
}

function whatsappEquipeExibicao(): string
{
    $numero = ONGHELP_WHATSAPP_EQUIPE;

    if (strlen($numero) === 13) {
        return '+' . substr($numero, 0, 2)
            . ' ' . substr($numero, 2, 2)
            . ' ' . substr($numero, 4, 5)
            . '-' . substr($numero, 9);
    }

    return '+' . $numero;
}

function whatsappEquipeUrl(string $mensagem = ONGHELP_WHATSAPP_MENSAGEM_EQUIPE): string
{
    return 'https://wa.me/' . ONGHELP_WHATSAPP_EQUIPE . '?text=' . rawurlencode($mensagem);
}

/**
 * Proxies reversos confiáveis para detectar HTTPS em produção.
 *
 * O XAMPP local roda em HTTP e não precisa desta lista. Atrás de um
 * proxy reverso (Nginx, Apache intermediário, balanceador), o PHP
 * costuma receber a requisição em HTTP mesmo quando o visitante está
 * em HTTPS. Nesse caso, o cabeçalho X-Forwarded-Proto só pode ser
 * aceito quando a conexão imediata vem de um proxy conhecido.
 *
 * Lista separada por vírgula, com IPs ou faixas CIDR. Exemplo:
 *   '10.0.0.10, 172.16.0.0/12, 2001:db8::/32'
 *
 * Também pode ser definida pela variável de ambiente
 * ONGHELP_TRUSTED_PROXIES, que tem prioridade sobre esta constante.
 */
const ONGHELP_PROXIES_CONFIAVEIS = '';

// ============================================================
// Notícias — limites, textos e regras centralizados
// ============================================================
//
// Todo limite de conteúdo, foto e frequência da seção Notícias
// fica neste bloco. As telas e os endpoints devem importar estas
// constantes em vez de repetir números pelo código.

const ONGHELP_NOTICIAS_TITULO_MIN = 10;
const ONGHELP_NOTICIAS_TITULO_MAX = 90;
const ONGHELP_NOTICIAS_RESUMO_MIN = 30;
const ONGHELP_NOTICIAS_RESUMO_MAX = 180;
const ONGHELP_NOTICIAS_TEXTO_MIN = 100;
const ONGHELP_NOTICIAS_TEXTO_MAX = 8000;
const ONGHELP_NOTICIAS_FOTOS_MIN_PUBLICAR = 1;
const ONGHELP_NOTICIAS_FOTOS_MAX = 10;
const ONGHELP_NOTICIAS_LINKS_MAX = 3;
const ONGHELP_NOTICIAS_PUBLICACOES_DIA = 3;
const ONGHELP_NOTICIAS_PALAVRAS_POR_MINUTO = 200;
const ONGHELP_NOTICIAS_SLUG_MAX = 120;
const ONGHELP_NOTICIAS_EVENTO_LOCAL_MIN = 3;
const ONGHELP_NOTICIAS_EVENTO_LOCAL_MAX = 200;

// Fotos: um arquivo por foto, enviado pelo formulário do editor.
const ONGHELP_NOTICIAS_FOTO_MAX_BYTES = 5 * 1024 * 1024;
const ONGHELP_NOTICIAS_FOTO_MAX_MEGAPIXELS = 24;
const ONGHELP_NOTICIAS_FOTO_ALT_MIN = 10;
const ONGHELP_NOTICIAS_FOTO_ALT_MAX = 300;
const ONGHELP_NOTICIAS_FOTO_LEGENDA_MAX = 140;
const ONGHELP_NOTICIAS_UPLOADS_PASTA = 'noticias';

// Denúncias e moderação.
const ONGHELP_NOTICIAS_DENUNCIA_DETALHE_MAX = 300;
const ONGHELP_NOTICIAS_OCULTACAO_MENSAGEM_MIN = 20;

// Textos oficiais. Não altere sem atualizar os testes e a interface.
const ONGHELP_NOTICIAS_DIREITOS_TEXTO =
    'Tenho autorização de uso de imagem das pessoas retratadas e não publico imagens identificáveis de crianças sem autorização dos responsáveis.';

const ONGHELP_NOTICIAS_PIX_MENSAGEM =
    'Por segurança, notícias não podem conter chaves PIX nem pedidos de transferência. Para receber doações, use o botão Doar via PIX do seu perfil.';

const ONGHELP_NOTICIAS_INDISPONIVEL = 'Esta notícia não está disponível.';

// Domínios de pagamento e encurtadores bloqueados em notícias.
// A comparação aceita o domínio e seus subdomínios.
const ONGHELP_NOTICIAS_DOMINIOS_PAGAMENTO = [
    'pagseguro.uol.com.br',
    'pag.ae',
    'mercadopago.com.br',
    'mercadopago.com',
    'picpay.com',
    'picpay.me',
    'paypal.com',
    'paypal.me',
    'hotmart.com',
    'kiwify.com.br',
    'eduzz.com',
    'moip.com.br',
    'cielo.com.br',
    'stone.com.br',
    'infinitepay.io',
    'getnet.com.br',
    'pagarme.com.br',
];

const ONGHELP_NOTICIAS_DOMINIOS_ENCURTADORES = [
    'bit.ly',
    'bit.do',
    'tinyurl.com',
    'tiny.cc',
    'goo.gl',
    'cutt.ly',
    't.co',
    'is.gd',
    'v.gd',
    'ow.ly',
    'rebrand.ly',
    'encurtador.com.br',
    'encurta.net',
    'shorturl.at',
    'rb.gy',
    's.id',
    'shorte.st',
    'clck.ru',
    'u.to',
    'shrtco.de',
];
