<?php
// ============================================================
// config/app.php — configurações públicas do site
// ============================================================

/**
 * e-mail oficial da equipe ONGHelp (contato, privacidade, denúncias
 * e conta administradora), use sempre esta constante
 */
const ONGHELP_EMAIL_EQUIPE = 'equipeonghelp@gmail.com';

/** áreas de atuação aceitas (cadastro, perfil, filtros) */
const ONGHELP_AREAS = ['Educação', 'Saúde', 'Animais', 'Meio Ambiente', 'Assistência Social', 'Cultura', 'Esporte', 'Outra'];

/** estados brasileiros: sigla => nome */
const ONGHELP_ESTADOS = [
    'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas',
    'BA' => 'Bahia', 'CE' => 'Ceará', 'DF' => 'Distrito Federal',
    'ES' => 'Espírito Santo', 'GO' => 'Goiás', 'MA' => 'Maranhão',
    'MT' => 'Mato Grosso', 'MS' => 'Mato Grosso do Sul', 'MG' => 'Minas Gerais',
    'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná', 'PE' => 'Pernambuco',
    'PI' => 'Piauí', 'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte',
    'RS' => 'Rio Grande do Sul', 'RO' => 'Rondônia', 'RR' => 'Roraima',
    'SC' => 'Santa Catarina', 'SP' => 'São Paulo', 'SE' => 'Sergipe',
    'TO' => 'Tocantins',
];

/** tipos de canal público da ONG: valor => rótulo */
const ONGHELP_TIPOS_CANAL = [
    'site'      => 'Site oficial',
    'instagram' => 'Instagram',
    'facebook'  => 'Facebook',
    'telefone'  => 'Telefone / WhatsApp',
    'outro'     => 'Outro',
];

/**
 * único ponto de configuração dos WhatsApps da equipe, na ordem em que
 * aparecem como opção 1, 2 e 3, use apenas dígitos, com DDI e DDD
 * (formato E.164 sem o "+"), o primeiro é o contato principal
 */
const ONGHELP_WHATSAPPS_EQUIPE = [
    '5516997792777',
    '5516994301337',
    '5516994349888',
];

// mensagem pronta da página "Faça parte da equipe"
const ONGHELP_WHATSAPP_MENSAGEM_EQUIPE =
    'Olá! Quero fazer parte da equipe de moderação do ONGHelp. Nome: / Cidade:';

// mensagem pronta do rodapé e da página "Fale conosco"
const ONGHELP_WHATSAPP_MENSAGEM_CONTATO =
    'Olá! Vim pelo site do ONGHelp e quero falar com a equipe.';


/**
 * proxies reversos confiáveis para detectar HTTPS em produção
 *
 * o XAMPP local roda em HTTP e não precisa desta lista, atrás de um
 * proxy reverso (Nginx, Apache intermediário, balanceador), o PHP
 * costuma receber a requisição em HTTP mesmo quando o visitante está
 * em HTTPS, nesse caso, o cabeçalho X-Forwarded-Proto só pode ser
 * aceito quando a conexão imediata vem de um proxy conhecido
 *
 * lista separada por vírgula, com IPs ou faixas cidr, exemplo:
 *   '10.0.0.10, 172.16.0.0/12, 2001:db8::/32'
 *
 * também pode ser definida pela variável de ambiente
 * ONGHELP_TRUSTED_PROXIES, que tem prioridade sobre esta constante
 */
const ONGHELP_PROXIES_CONFIAVEIS = '';

// paleta da cor do banner do perfil público: valor gravado => nome,
// tons médios e escuros que funcionam com o texto escuro e o vidro claro
// do site, as 8 cores das áreas de atuação estão aqui, então a cor
// padrão de qualquer ONG também é uma cor da paleta
const ONGHELP_CORES_BANNER = [
    '#0D5C8F' => 'Azul',
    '#3949AB' => 'Índigo',
    '#00838F' => 'Petróleo',
    '#16A085' => 'Turquesa',
    '#00B37E' => 'Verde',
    '#2E7D32' => 'Verde-floresta',
    '#B7791F' => 'Mostarda',
    '#E8913A' => 'Laranja',
    '#D84315' => 'Laranja-queimado',
    '#C0392B' => 'Vermelho',
    '#C2185B' => 'Rosa',
    '#8E44AD' => 'Roxo',
    '#5E35B1' => 'Violeta',
    '#9A6B3F' => 'Marrom',
    '#5A6A80' => 'Cinza-azulado',
    '#37474F' => 'Grafite',
];

// ============================================================
// gestão financeira e projetos
// ============================================================

// categoria das movimentações por tipo: valor gravado => rótulo,
// 'outra' existe nos dois tipos e é o padrão do banco
const ONGHELP_CATEGORIAS_MOVIMENTACAO = [
    'receita' => [
        'doacao'   => 'Doações e contribuições',
        'parceria' => 'Parcerias e patrocínios',
        'edital'   => 'Editais e convênios',
        'evento'   => 'Eventos e campanhas',
        'venda'    => 'Vendas e serviços',
        'outra'    => 'Outras receitas',
    ],
    'despesa' => [
        'alimentacao' => 'Alimentação',
        'pessoal'     => 'Pessoal e bolsas',
        'estrutura'   => 'Aluguel e contas',
        'material'    => 'Materiais e equipamentos',
        'saude'       => 'Saúde e cuidados',
        'transporte'  => 'Transporte',
        'servico'     => 'Serviços e manutenção',
        'outra'       => 'Outras despesas',
    ],
];

// situação do projeto: valor gravado => rótulo
const ONGHELP_STATUS_PROJETO = [
    'planejado' => 'Planejado',
    'andamento' => 'Em andamento',
    'concluido' => 'Concluído',
];

const ONGHELP_PROJETO_DESCRICAO_MAX = 600;

// comprovante opcional da movimentação (PDF ou foto), fica público
// no portal de transparência, por isso o aviso sobre dados pessoais
const ONGHELP_COMPROVANTE_MAX_BYTES = 5 * 1024 * 1024;
const ONGHELP_COMPROVANTES_PASTA = 'comprovantes';

// ============================================================
// notícias — limites, textos e regras centralizados
// ============================================================
//
// todo limite de conteúdo, foto e frequência da seção Notícias
// fica neste bloco, as telas e os endpoints devem importar estas
// constantes em vez de repetir números pelo código

const ONGHELP_NOTICIAS_TITULO_MIN = 10;
const ONGHELP_NOTICIAS_TITULO_MAX = 90;
const ONGHELP_NOTICIAS_RESUMO_MIN = 30;
const ONGHELP_NOTICIAS_RESUMO_MAX = 180;
const ONGHELP_NOTICIAS_TEXTO_MIN = 100;
const ONGHELP_NOTICIAS_TEXTO_MAX = 8000;
const ONGHELP_NOTICIAS_FOTOS_MAX = 10;
// imagens ilustrativas por área (assets/img/noticias/<area>-N.jpg),
// usadas quando a notícia não tem fotos próprias
const ONGHELP_NOTICIAS_IMAGENS_PADRAO = 3;
const ONGHELP_NOTICIAS_LINKS_MAX = 3;
const ONGHELP_NOTICIAS_PUBLICACOES_DIA = 3;
const ONGHELP_NOTICIAS_PALAVRAS_POR_MINUTO = 200;
const ONGHELP_NOTICIAS_SLUG_MAX = 120;
const ONGHELP_NOTICIAS_EVENTO_LOCAL_MIN = 3;
const ONGHELP_NOTICIAS_EVENTO_LOCAL_MAX = 200;

// fotos: um arquivo por foto, enviado pelo formulário do editor
const ONGHELP_NOTICIAS_FOTO_MAX_BYTES = 5 * 1024 * 1024;
const ONGHELP_NOTICIAS_FOTO_MAX_MEGAPIXELS = 24;
const ONGHELP_NOTICIAS_FOTO_ALT_MIN = 10;
const ONGHELP_NOTICIAS_FOTO_ALT_MAX = 300;
const ONGHELP_NOTICIAS_FOTO_LEGENDA_MAX = 140;
const ONGHELP_NOTICIAS_UPLOADS_PASTA = 'noticias';

// denúncias e moderação
const ONGHELP_NOTICIAS_DENUNCIA_DETALHE_MAX = 300;
const ONGHELP_NOTICIAS_OCULTACAO_MENSAGEM_MIN = 20;

// textos oficiais, não altere sem atualizar os testes e a interface
const ONGHELP_NOTICIAS_DIREITOS_TEXTO =
    'Tenho autorização de uso de imagem das pessoas retratadas e não publico imagens identificáveis de crianças sem autorização dos responsáveis.';

const ONGHELP_NOTICIAS_PIX_MENSAGEM =
    'Por segurança, notícias não podem conter chaves PIX nem pedidos de transferência. Para receber doações, use o botão Doar via PIX do seu perfil.';

const ONGHELP_NOTICIAS_INDISPONIVEL = 'Esta notícia não está disponível.';

// domínios de pagamento e encurtadores bloqueados em notícias,
// a comparação aceita o domínio e seus subdomínios
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
