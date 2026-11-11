# ONGHelp

**Plataforma gratuita de gestão e transparência para ONGs brasileiras.**

Cadastro institucional, perfil público, portal de transparência com balancetes
mensais, relatórios anuais exportáveis e divulgação de doações via PIX — em um
único lugar, sem depender de software pago.

Projeto Integrador de Engenharia de Software (2026)
Daniel Sydinei Sampaio Garcia · Estevão Gabriel Feitosa Brito · Raul Marin Bombarda
Orientação: Prof. Rodrigo Sartor · Disciplina: Prof. Luciano Barros

---

## Índice

1. [O problema que o projeto atende](#1-o-problema-que-o-projeto-atende)
2. [Como executar](#2-como-executar)
3. [Estrutura de arquivos](#3-estrutura-de-arquivos)
4. [Banco de dados](#4-banco-de-dados)
5. [Funcionalidades](#5-funcionalidades)
6. [Decisões técnicas](#6-decisões-técnicas)
7. [Segurança](#7-segurança)
8. [Problemas conhecidos](#8-problemas-conhecidos)
9. [Aderência ao escopo](#9-aderência-ao-escopo)
10. [Roadmap](#10-roadmap)
11. [Tecnologias](#11-tecnologias)

---

## 1. O problema que o projeto atende

O terceiro setor brasileiro é enorme — o IPCA registrou mais de 897 mil
organizações da sociedade civil ativas em 2024 — mas a maioria das ONGs de
pequeno e médio porte ainda trabalha com planilhas e ferramentas soltas para
controlar dinheiro, projetos e comunicação com doadores. Isso gera três
problemas concretos:

- **Prestação de contas desorganizada.** Não há portal público onde o doador
  veja para onde foi o dinheiro.
- **Captação frágil.** Sem um canal próprio de divulgação, a ONG depende de
  plataformas de terceiros que cobram taxa e intermediam a relação com o doador.
- **Baixa visibilidade.** Sem presença digital qualificada, é difícil conquistar
  parceiros e manter doadores recorrentes.

O ONGHelp é a resposta a isso: uma plataforma única, gratuita e sem cadastro
técnico complicado, onde a ONG publica seus dados e o público confere.

---

## 2. Como executar

Pré-requisitos: **XAMPP** (ou qualquer pilha PHP + MySQL/MariaDB), com PHP 8.0 ou
superior. O projeto é autocontido: código, imagens de demonstração e banco
viajam juntos na mesma pasta (dá para levar num pendrive).

1. Copie a pasta `ONGHelp` para `C:\xampp\htdocs\`.
2. Inicie **Apache** e **MySQL** no painel do XAMPP.
3. Importe o banco — um único arquivo, `sql/onghelp.sql`:
   - **phpMyAdmin:** aba **Importar** → escolha `sql/onghelp.sql` → **Importar**
     (não precisa criar o banco antes; o arquivo cria o `onghelp` sozinho).
   - **ou pelo terminal:** `C:\xampp\mysql\bin\mysql -u root < sql\onghelp.sql`
4. Acesse `http://localhost/ONGHelp/`.

Reimportar o arquivo restaura o banco ao estado de demonstração (as tabelas são
recriadas). Se o MySQL do computador tiver senha, ajuste `$user` e `$pass` em
`includes/db.php`.

### Atualizando o arquivo do banco

Depois de mudar dados ou estrutura, gere o arquivo de novo para levar a versão
atual:

```bash
C:\xampp\mysql\bin\mysqldump -u root --databases onghelp --routines --default-character-set=utf8mb4 > sql\onghelp.sql
```

O `--databases` é o que faz o arquivo criar e selecionar o banco sozinho na
importação.

### Sobre as credenciais do banco

O `includes/db.php` traz `root` com senha vazia, que é o padrão do XAMPP. Em
produção isso deve virar variável de ambiente ou um `.env` fora da raiz
pública — credenciais nunca devem ir para o Git.

### Contas de demonstração

O banco traz 30 ONGs fictícias (28 aprovadas e 2 aguardando análise), com
projetos, notícias e movimentações mensais de 2025 a 2026. Todas usam a senha
**`senha123`**. Exemplo: `patasunidas.ong@gmail.com`.

O painel da Equipe ONGHelp fica em `/admin/`:

A conta **Equipe ONGHelp** é única e compartilhada por até três pessoas de
confiança; ela não pode ser desativada nem ter o papel alterado. As três contas
de moderação são fictícias (`conta_teste = 1`) e existem só para demonstrar a
autoria individual, o primeiro acesso e os limites de permissão.

> **Atenção — contas de teste:** elas usam uma senha pública. Antes de publicar
> o site de verdade, remova-as com `sql/remover_contas_teste.sql` (veja abaixo)
> ou troque as senhas pela tela **Equipe**.

#### Removendo as contas de teste

Antes de apresentar ou implantar, troque a senha da conta administradora em
**Minha conta** e execute o script de limpeza:

```bash
mysqldump -u root onghelp > backup_onghelp_antes_da_limpeza.sql
mysql -u root onghelp < sql/remover_contas_teste.sql
```

O script remove apenas contas com `conta_teste = 1` e `papel = 'moderador'`,
além dos logs e das tentativas de login gerados por elas. A FK
`verificacoes.admin_id` é `ON DELETE SET NULL`: decisões antigas de ONGs reais
continuam no histórico, apenas sem o autor fictício. Depois da limpeza,
`SELECT id, nome, email, conta_teste FROM admins;` deve mostrar somente a conta
**Equipe ONGHelp**, com `conta_teste = 0`. A senha temporária aparece uma única
vez, na criação ou na redefinição; se for perdida, use **Redefinir senha** para
gerar outra.

Contas de moderação não são cadastradas pelo público: o administrador cria cada
conta na página **Equipe**. A pessoa recebe uma senha temporária válida por
**48 horas**, entra uma única vez e define a própria senha. A senha temporária é
guardada apenas como hash e aparece em texto puro **uma única vez**, logo depois
da criação, para ser entregue por um canal seguro. A tela de primeiro acesso
explica as regras de conduta e só libera o painel depois da troca.

O administrador pode analisar ONGs, consultar logs e gerenciar a equipe; o
moderador pode analisar ONGs e consultar logs, mas não vê a página Equipe nem
altera contas. Toda permissão é revalidada no servidor em cada requisição. O
histórico antigo da conta compartilhada continua aparecendo como **Equipe
ONGHelp**; as ações novas registram a pessoa autora.

A página pública **`/pages/equipe.php`** apresenta o voluntariado de moderação.
Ela é `noindex, nofollow`, não aparece na navegação principal e leva ao WhatsApp
**+55 16 99779-2777** com a mensagem inicial já preenchida. O número e a
mensagem ficam em um único lugar, `includes/config.php`.

Criar uma conta nova de ONG pela tela de cadastro também funciona: ela nasce
**pendente**, não entra no site e só pode ser aprovada pelo painel admin. Esse é
o fluxo que um usuário novo realmente percorreria.

---

## 3. Estrutura de arquivos

```
onghelp/
├── index.php                  Home: estatísticas reais do banco + carrossel de ONGs
├── README.md                  Este arquivo
├── docs/
│   ├── README.md              Índice da documentação
│   ├── PLANO_VERIFICACAO.md   Plano de verificação e administração
│   ├── ONGHelp_Documentacao.docx  Documentação acadêmica do projeto
│   └── ONGHelp_Sprints.docx   Registro das sprints
│
├── assets/
│   ├── css/
│   │   └── global.css         Design system próprio (≈13.180 linhas, inclui painel, equipe e página pública)
│   ├── img/
│   │   ├── brand/             Logos, favicons e ícones da marca
│   │   ├── areas/             Capas por área de atuação (JPG)
│   │   ├── hero/              Imagem principal da home
│   │   ├── icons/
│   │   │   ├── areas/         Ícones das áreas de atuação (SVG)
│   │   │   └── ui/            Ícones de interface (SVG)
│   │   ├── defaults/          Fotos padrão (avatar-ong.svg)
│   │   └── uploads/
│   │       ├── noticias/      Fotos das notícias + CREDITOS.md das fotos demo (gravável)
│   │       └── perfis/        Fotos enviadas pelas ONGs (gravável)
│   └── js/
│       ├── admin-equipe.js    Modais, cópia e descarte da senha temporária
│       ├── admin-senha.js     Checklist ao vivo de senha (Web Crypto)
│       ├── noticias-lista.js  Confirmação de exclusão em Minhas notícias
│       ├── noticia-editor.js  Editor de texto, prévia das fotos e checklist
│       ├── noticia.js         Página da notícia: copiar link e visualizador de fotos
│       ├── qrcode.min.js      qrcodejs local — desenha o BR Code no navegador
│       └── qrcode.LICENSE.txt licença MIT do qrcodejs
│
├── includes/
│   ├── config.php             Fonte única do WhatsApp e da mensagem da equipe
│   ├── db.php                 Conexão PDO + timezone + headers + sessão
│   ├── security_headers.php   CSP, X-Frame-Options, nosniff, Referrer-Policy
│   ├── session.php            Timeout, rotação, token de sessão e guards de ONG/admin
│   ├── csrf.php               Tokens e validação de formulários POST
│   ├── rate_limit.php         Limite de tentativas de login
│   ├── admin_auth.php         Papéis, permissões, primeiro acesso, checklist e logs
│   ├── admin_header.php       Layout, navegação por papel e pílula do usuário
│   ├── admin_footer.php       Fechamento do layout, logout POST e scripts
│   ├── header.php             <head>, navbar, modal de sair, robots configurável
│   ├── footer.php             Rodapé, links institucionais, Bootstrap JS
│   ├── helpers.php            Funções compartilhadas (formatação, status, PIX, cores)
│   ├── noticias.php           Regras e helpers da área de notícias (parte 1)
│   ├── noticias_fotos.php     Upload, validação e URLs das fotos de notícias
│   ├── noticias_ui.php        Card de notícia e componentes visuais compartilhados
│   ├── pdf.php                Gerador de PDF em PHP puro (sem bibliotecas)
│   └── modal_doacao.php       Componente de divulgação de doação via PIX
│
├── admin/
│   ├── login.php              Acesso individual da equipe (admin ou moderador)
│   ├── primeiro_acesso.php    Troca obrigatória da senha temporária e aceite das regras
│   ├── index.php              Dashboard, resumo por status e fila de pendentes
│   ├── verificar.php          Detalhe do cadastro, checklist e fontes de conferência
│   ├── acao.php               Aprovar, recusar, suspender, reativar, corrigir PIX/responsável
│   ├── equipe.php             Gestão de moderadores (somente administrador)
│   ├── minha_conta.php        Perfil pessoal, permissões e troca de senha
│   ├── logs.php               Histórico com filtros, autoria e linha do tempo
│   └── logout.php             Encerra a sessão (somente POST + CSRF)
│
├── pages/
│   ├── cadastro.php           Criação de conta da ONG (nasce pendente)
│   ├── login.php              Autenticação (e-mail + senha) com status e rate limit
│   ├── logout.php             Encerra a sessão
│   ├── perfilprivado.php      Painel da ONG logada (perfil, tema, verificação e relatório anual)
│   ├── perfilpublico.php      Perfil público de uma ONG (?id=N)
│   ├── gestao.php             CRUD de movimentações e de projetos
│   ├── noticias.php           Minhas notícias: KPIs, estados e ações da ONG
│   ├── noticia-editar.php     Criação/edição de notícia, fotos e publicação
│   ├── transparencia.php      Portal público com filtros, modais e balancetes
│   ├── ongs.php               Listagem pública com filtros, chips e paginação
│   ├── relatorio_anual.php    Exporta o relatório anual em PDF (4 seções)
│   ├── exportar_csv.php       Exporta CSV por ano ou por período
│   ├── equipe.php             Página pública de voluntariado (noindex, WhatsApp)
│   ├── desativar.php          Exclusão permanente da conta
│   ├── termos.php             Termos de uso
│   └── privacidade.php        Política de privacidade
│
└── sql/
    ├── onghelp.sql               Banco completo: cria o banco, as tabelas e os dados de demonstração
    └── remover_contas_teste.sql  Opcional: remove as contas fictícias de moderação
```

As fotos enviadas pelas ONGs ficam em `assets/img/uploads/perfis/`, e as fotos
de notícias em `assets/img/uploads/noticias/`; as duas pastas precisam ter
permissão de escrita para o usuário do Apache. Os ícones em
`assets/img/icons/` e o avatar em `assets/img/defaults/` são placeholders:
podem ser substituídos mantendo o mesmo nome-base, sem alterar o código. O
sistema procura PNG, WebP, JPG, JPEG e SVG, nessa ordem.

O PHP é **imperativo por design**: cada página lê o `$_GET`/`$_POST`, monta a
consulta e renderiza o HTML no servidor. Não há MVC, roteador ou autoload — a
complexidade se resolve em funções pequenas e em componentes incluídos.

---

## 4. Banco de dados

MariaDB 10.4 / MySQL 8, InnoDB, `utf8mb4`. Sete tabelas: `usuarios`,
`movimentacoes`, `projetos`, `admins`, `verificacoes`, `logs_admin` e
`tentativas_login`.

O `sql/onghelp.sql` já contém a estrutura final e todos os dados; não há
migrações a aplicar. As migrações antigas continuam no histórico do git.

### `usuarios` — as ONGs

| Campo | Tipo | Observação |
|---|---|---|
| `id` | int PK | auto-increment |
| `nome` | varchar(160) | razão social |
| `cnpj` | varchar(20) | **UNIQUE** — normalizado para 14 dígitos; a existência e a titularidade são conferidas manualmente na aprovação |
| `email` | varchar(160) | **UNIQUE** — login da plataforma |
| `senha` | varchar(255) | `password_hash()` com `PASSWORD_DEFAULT` |
| `area` | varchar(60) | Educação, Saúde, Animais, Meio Ambiente, Assistência Social, Cultura, Esporte, Outra |
| `foto_perfil` | varchar(255) | nome do arquivo em `assets/img/uploads/perfis/`; `NULL` usa o avatar padrão |
| `status` | enum | `pendente`, `aprovada`, `recusada`, `suspensa`; só `aprovada` aparece no site |
| `aprovado_em` | datetime | data da primeira aprovação |
| `verificada_em` | datetime | data da última verificação; usada no selo |
| `verificado_por` | int FK | admin responsável pela última decisão |
| `motivo_recusa` | text | motivo de recusa ou suspensão, visível no painel |
| `responsavel_nome` | varchar(160) | responsável legal/dirigente máximo — **privado** |
| `responsavel_cargo` | varchar(80) | cargo do responsável — **privado** |
| `responsavel_autorizacao` | tinyint(1) | declaração de autorização aceita no cadastro |
| `canal_tipo` | enum | `site`, `instagram`, `facebook`, `telefone`, `outro` |
| `canal_valor` | varchar(255) | endereço, @ ou telefone do canal público |
| `email_aprovacao_enviado_em` | datetime | marca o envio manual do e-mail de aprovação |
| `cidade` | varchar(100) | `NOT NULL DEFAULT 'Brasil'` |
| `estado` | char(2) | sigla UF, opcional |
| `descricao` | text | missão da organização, exibida publicamente |
| `pix_chave` | varchar(20) | **sempre o CNPJ sem máscara**; bloqueada após a aprovação |
| `cor_tema` | varchar(7) | cor do banner em formato `#RRGGBB`, opcional |
| `cor_fundo` | varchar(7) | cor dos cards e do fundo nas páginas públicas, opcional |
| `criado_em` | datetime | usado em "membro desde" e na ordenação da home |

### `movimentacoes` — receitas e despesas

| Campo | Tipo | Observação |
|---|---|---|
| `id` | int PK | |
| `usuario_id` | int FK | → `usuarios.id` com `ON DELETE CASCADE` |
| `tipo` | enum | `'receita'` ou `'despesa'` |
| `descricao` | varchar(255) | texto livre |
| `valor` | decimal(12,2) | sempre positivo; o sinal vem do `tipo` |
| `data_mov` | date | competência da movimentação |
| `criado_em` | datetime | registro |

### `projetos` — projetos e indicadores

| Campo | Tipo | Observação |
|---|---|---|
| `id` | int PK | |
| `usuario_id` | int FK | → `usuarios.id` com `ON DELETE CASCADE` |
| `nome` | varchar(160) | |
| `indicador` | int unsigned | número do resultado alcançado |
| `unidade` | varchar(80) | o que o número representa ("mudas plantadas") |

O modelo de projeto é propositalmente enxuto: **um número + uma unidade + um
nome**. Ele existe para dar substância ao perfil público ("850 mudas plantadas"),
não para ser um gerenciador de projetos completo.

### `admins` — administrador compartilhado e moderadores

| Campo | Tipo | Observação |
|---|---|---|
| `id` | int PK | |
| `nome` | varchar(160) | nome exibido no painel e nos logs |
| `email` | varchar(160) | **UNIQUE** — login individual |
| `senha` | varchar(255) | hash bcrypt; a senha nunca é enviada por e-mail |
| `papel` | enum | `administrador` ou `moderador` |
| `estado` | enum | `ativo`, `aguardando` ou `desativado` |
| `senha_temporaria_expira_em` | datetime | validade de 48 horas no primeiro acesso |
| `sessao_token` | varchar(64) | invalida sessões quando a senha ou o estado mudam |
| `ultimo_acesso_em` | datetime | último login concluído |
| `criado_por` | int | administrador que criou a conta de moderação |
| `conta_teste` | tinyint(1) | marca dados fictícios de demonstração |
| `criado_em` | datetime | |

Existe **exatamente uma** conta com `papel = 'administrador'`: a conta
compartilhada **Equipe ONGHelp**. Ela não pode ser desativada, excluída nem ter
o papel alterado. As demais contas são moderadores individuais, com autoria
própria nos logs. Não existe cadastro público de administrador; a página
`/pages/equipe.php` é apenas um contato de voluntariado.

Estados possíveis:

- **Ativo** — pode entrar no painel conforme o papel.
- **Aguardando primeiro acesso** — só entra para trocar a senha temporária,
  válida por 48 horas. Depois do prazo, precisa de uma nova senha gerada pelo
  administrador.
- **Desativado** — login bloqueado; a conta e todo o histórico permanecem no
  banco e podem ser reativados com nova senha temporária.

O sistema não exclui moderadores pela interface. A remoção das três contas
fictícias de teste é a única exceção documentada, feita por SQL em uma
implantação real.

### Permissões

As permissões ficam declaradas em `permissoesPorPapel()` e são verificadas no
servidor em cada página e ação:

| Permissão | Administrador | Moderador |
|---|:---:|:---:|
| `painel.acessar` | ✅ | ✅ |
| `ong.analisar` | ✅ | ✅ |
| `logs.ver` | ✅ | ✅ |
| `equipe.gerenciar` | ✅ | — |
| `conta.propria` | ✅ | ✅ |

Para criar uma capacidade futura — por exemplo, moderação de notícias — basta
acrescentar a chave em `permissoesPorPapel()` e usá-la com
`exigirPermissao($pdo, 'noticias.moderar')`. A página de negativa registra a
tentativa em `logs_admin`.

### `verificacoes` — histórico de análises

| Campo | Tipo | Observação |
|---|---|---|
| `id` | int PK | |
| `usuario_id` | int FK | → `usuarios.id` com `ON DELETE CASCADE` |
| `admin_id` | int FK | → `admins.id` com `ON DELETE SET NULL` |
| `checklist_json` | JSON | bloqueantes e sinais de atenção marcados |
| `observacoes` | text | observações ou motivo de recusa/suspensão |
| `resultado` | enum | `aprovado`, `recusado`, `suspenso` |
| `criado_em` | datetime | |

### `logs_admin` — auditoria

Registra `login_admin`, `logout_admin`, `aprovar_ong`, `recusar_ong`,
`suspender_ong`, `reativar_ong`, `marcar_email_enviado`, `alterar_pix`,
`alterar_responsavel`, `conta_criada`, `senha_redefinida`, `conta_desativada`,
`conta_reativada`, `senha_alterada`, `primeiro_acesso_concluido` e
`acesso_negado`, com alvo, detalhe, IP e data. Cada linha guarda o `admin_id`
da pessoa autora. O histórico anterior às contas individuais continua com
autoria **Equipe ONGHelp**, sem reescrever nem apagar registros.

### `tentativas_login` — rate limiting

Registra escopo (`ong`/`admin`), e-mail, IP, sucesso e data. Cinco falhas por
e-mail/IP em 15 minutos ou vinte por IP em uma hora bloqueiam a tentativa.

### Doações

Não existe tabela `doacoes`. A doação via PIX é um canal de divulgação: a ONG
exibe a chave, o doador paga diretamente a ela e a ONG registra o valor como
uma receita em `movimentacoes`. O portal de transparência reflete essa receita
como qualquer outra entrada. Se quiser, a descrição da receita identifica a
campanha ou o projeto; não há histórico separado por doação.

A chave PIX é obrigatória e **é sempre o CNPJ da própria ONG, sem máscara**.
O QR Code é sempre gerado a partir dessa chave verificada, como BR Code
estático sem valor embutido — o doador digita quanto quer doar no app do banco.
Não existe upload de imagem de QR Code. A plataforma não processa, custodia nem
concilia pagamentos.

### Integridade

O `CASCADE` nas duas tabelas filhas é o que torna a exclusão de conta uma
operação de uma linha só: um `DELETE FROM usuarios WHERE id = ?` limpa
movimentações e projetos junto. O `onghelp.sql` cria as tabelas na ordem
correta (pai primeiro) justamente para evitar o erro 150 na importação.

### Dados de demonstração

- **8 ONGs** fictícias (ids 24 a 31), todas em São Paulo, cobrindo as 8 áreas, com CNPJs de demonstração
- **147 movimentações** distribuídas entre 2024, 2025 e 2026
- **16 projetos** com indicadores coerentes com a área de cada ONG
- Todas as ONGs estão `aprovada`, com `aprovado_em` e `verificada_em` preenchidos,
  para que o site já abra com o selo e o portal populado
- As 8 contas compartilham o mesmo hash de senha (`senha123`) e o mesmo `criado_em` — veja
  [Problemas conhecidos](#8-problemas-conhecidos)
- A tabela `admins` do dump principal tem 1 linha: a conta compartilhada
  **Equipe ONGHelp** (administrador). As três contas fictícias são opcionais e
  ficam em `sql/contas_teste.sql`, fora do dump e da migração
- As três contas de teste compartilham o hash de `Moderador@2026`; a conta
  administradora usa `EquipeONGHelp@2026`, descrita na seção 2
- O `AUTO_INCREMENT` de `admins` começa em 2 no dump principal. O script
  `sql/contas_teste.sql` usa os próximos ids e `sql/remover_contas_teste.sql`
  não reaproveita ids removidos

#### Notícias de demonstração

O dump traz **30 ONGs fictícias** (28 aprovadas e 2 em análise) em 7 causas e
9 cidades do interior paulista, com projetos e movimentações financeiras, e
**28 notícias publicadas** com fotos reais de bancos de imagem com licença
livre (CC0, domínio público e Creative Commons; as 18 fotos se repetem entre
notícias do mesmo tema). Todas as ONGs usam e-mail Gmail fictício e a senha
`senha123` (ex.: `patasunidas.ong@gmail.com`). As notícias estão marcadas com
`noticias.demonstracao = 1`. Os arquivos ficam
em `assets/img/uploads/noticias/demo-*.jpg` e os créditos (autor, licença e
origem de cada foto) em `assets/img/uploads/noticias/CREDITOS.md`.

Não é preciso gerar nada depois de importar o dump: as fotos já estão no
repositório e as notícias já vêm no `sql/onghelp.sql`.

Para removê-las, `DELETE FROM noticias WHERE demonstracao = 1;` apaga as
notícias e, por cascata, as fotos e os links; os arquivos `demo-*.jpg` podem
ser apagados à mão.

### Notícias da ONG

**Como publicar.** Entre com a conta da ONG, abra o menu da conta e escolha
“Minhas notícias” (ou use “Publicar notícia” no cabeçalho de Gestão). O botão
“+ Publicar notícia” abre o editor. Preencha título, resumo, texto e tipo;
envie ao menos uma foto e marque a declaração de direitos de imagem. O botão
“Salvar rascunho” mantém a notícia fora do ar; “Publicar” valida tudo e coloca
no estado publicada. Na lista, o menu “⋯” permite ver, editar, despublicar
(volta a rascunho) ou excluir. Excluir pede confirmação e também apaga as fotos.

**Limites e regras.** Título 10–90 caracteres, resumo 30–180, texto
100–8.000, até 3 links e 1–10 fotos por notícia. Cada foto pode ter até 5 MB
e 24 megapixels e precisa de uma descrição (texto alternativo, mínimo 10
caracteres). A ONG pode publicar no máximo 3 notícias por dia. Se faltar algo
ao clicar em Publicar, a notícia é salva como rascunho (com as fotos) e o
editor mostra o que falta. Só ONGs aprovadas publicam:
cadastro pendente, recusado, suspenso ou com bloqueio ativo recebe a faixa de
aviso e os botões de publicar desabilitados. Título, resumo, texto e links não
podem conter chave PIX nem pedido de transferência; o conteúdo é revalidado na
publicação.

**Como as fotos são enviadas.** O editor é um formulário comum (POST
multipart): texto, links e fotos são salvos juntos, sem API separada. Cada foto
vira **um único arquivo** em `assets/img/uploads/noticias/` com nome aleatório
(`ong<ID>-<16 hex>.jpg|png|webp`) e uma linha em `noticias_fotos` (`arquivo`,
dimensões, descrição, legenda e ordem — a primeira é a capa). O servidor
confere o tipo real do arquivo (`finfo`) e se é uma imagem válida. Com a
extensão GD ativa, a foto é reduzida para no máximo 1600 px de largura e
regravada; sem GD, é salva como veio e os metadados EXIF dos JPEGs (que podem
conter a localização GPS) são removidos. Fotos removidas no editor e notícias
excluídas apagam os arquivos do disco. A pasta tem `.htaccess` que bloqueia
listagem e execução de scripts e precisa de permissão de escrita para o
usuário do Apache. O envio inteiro precisa caber no `post_max_size` do PHP
(40 MB no XAMPP); o editor avisa antes de passar desse limite.

#### Moderação de notícias

**Fluxo de denúncia e ocultação.**

1. O visitante abre uma notícia publicada e usa o formulário de denúncia. O
   motivo é obrigatório; detalhe e e-mail são opcionais. O servidor guarda
   apenas um hash de IP + navegador para impedir denúncias repetidas e nunca
   exibe o e-mail do denunciante para a ONG.
2. A denúncia nasce `pendente` e aparece em `admin/noticias.php`, na aba
   **Denúncias**, agrupada por notícia. A página exige a permissão
   `noticias.moderar` e CSRF em todas as decisões.
3. A equipe pode **manter publicada** (marca as pendências como improcedentes),
   **ocultar** (categoria + mensagem de pelo menos 20 caracteres) ou **ocultar e
   bloquear** as publicações da ONG (confirmação explícita + motivo).
4. Ocultar muda a notícia para `oculta` e marca as denúncias pendentes como
   procedentes, mas **não apaga** texto, fotos nem denúncias: tudo continua
   guardado como evidência. A ONG vê a mensagem em **Minhas notícias** e a
   notícia some das páginas públicas.
5. **Restaurar** volta a notícia para `publicada` e limpa os campos de
   ocultação, preservando denúncias e histórico. Uma nova denúncia em notícia
   já tratada reabre o caso como `pendente`.
6. O bloqueio de publicações também pode ser aplicado ou liberado no painel
   **Publicações** de `admin/verificar.php?id=N`, com modal de confirmação e
   motivo obrigatório ao bloquear. A ONG bloqueada vê o motivo e não consegue
   publicar até a liberação.

**Permissões.** `noticias.moderar` controla a fila, o drawer e as decisões de
notícias; `ong.analisar` controla a análise da ONG e o painel de publicações.
Administrador e moderador têm as duas capacidades. Toda ação é revalidada no
servidor; POST sem CSRF retorna 403 e o menu esconde apenas por conveniência.

**Logs.** A categoria **Notícias** reúne `denuncia_recebida`,
`noticia_ocultada`, `noticia_restaurada`, `denuncia_improcedente`,
`publicacoes_bloqueadas` e `publicacoes_liberadas`. Denúncias públicas aparecem
com autoria **Visitante** (`admin_id = NULL`) e sem o e-mail do denunciante. A
atividade de cada moderador em **Equipe → Ver atividade** ganhou o filtro
**Notícias**.

**Limites e regras de publicação.** Título 10–90 caracteres, resumo 30–180,
texto 100–8.000, até 3 links e 1–10 fotos por notícia. Cada foto pode ter até
5 MB e 24 megapixels e precisa de descrição. A ONG publica no máximo 3 notícias
por dia. Só ONG aprovada e sem bloqueio ativo publica.

**Uploads.** Veja "Como as fotos são enviadas" em Notícias da ONG.

**Remover a demonstração.** `DELETE FROM noticias WHERE demonstracao = 1;`
remove as notícias demo e, por cascata, as fotos e os links.
Contas de teste de moderação saem com `sql/remover_contas_teste.sql`.

**Ainda fora do escopo.** Newsletter e envio automático de e-mails; edição de
notícia oculta pela ONG; exclusão definitiva de denúncias pela interface;
notificações em tempo real; moderação automática por IA; processamento,
custódia ou conciliação de doações; e exclusão de moderadores (apenas
desativação/reativação).

---

## 5. Funcionalidades

### Visitante (sem login)

| Recurso | Página | O que faz |
|---|---|---|
| Home | `index.php` | Estatísticas reais do banco (ONGs, arrecadado, movimentações) e carrossel com as ONGs mais recentes |
| Mapa de ONGs | `pages/ongs.php` | Filtros por nome, cidade, UF e área; ordenação por nome, cadastro recente ou arrecadação; chips de causas; ONG em destaque; paginação de 9 em 9 |
| Portal de Transparência | `pages/transparencia.php` | Cards por ONG, filtro por ano e categoria, busca por nome, e um modal por ONG com gráfico de receitas mensais, **balancete de 12 meses**, últimas movimentações, projetos e ações de exportação |
| Perfil público | `pages/perfilpublico.php?id=N` | Dados institucionais, projetos com indicadores, últimas 10 movimentações, canal público e o botão de doação via PIX (exibido apenas quando há chave verificada). Perfil de ONG pendente, recusada ou suspensa retorna 404 |
| Divulgação de doação via PIX | modal reutilizável | QR Code **sempre gerado a partir da chave PIX verificada** (o CNPJ da ONG), chave copiável e orientação para pagar diretamente no app do banco. Não existe upload de imagem. A plataforma não processa o pagamento |
| Selo de verificação | home, listagem, transparência e perfil | “Verificada em dd/mm/aaaa” com a data da última verificação; acompanhado do aviso de que é conferência cadastral, não auditoria financeira |
| CSV por ano | `pages/exportar_csv.php?ong=N&ano=AAAA` | Movimentações do ano, com BOM UTF-8 e separador `;` (abre direto no Excel em pt-BR), da mais antiga para a mais nova |
| CSV por período | `pages/exportar_csv.php?ong=N&ini=...&fim=...` | Mesmo formato, filtrado por intervalo de datas, na ordem do período informado |
| Relatório anual | `pages/relatorio_anual.php?ong=N&ano=AAAA` | PDF A4 com capa e indicadores, gráfico mensal, balancete de 12 meses, movimentações e projetos |
| Faça parte da equipe | `pages/equipe.php` | Página de voluntariado `noindex, nofollow`, com o fluxo de moderação e CTA para o WhatsApp **+55 16 99779-2777**; a mensagem inicial já vem preenchida |
| Notícias públicas | `pages/noticias-publicas.php` | Hub com capa, manchetes, carrossel, filtros por busca, tipo, área e ONG, e mosaico de cards; somente notícias publicadas de ONGs aprovadas aparecem |
| Notícia pública e denúncia | `pages/noticia.php` | Texto, galeria, compartilhamento e formulário de denúncia com motivo, detalhe e e-mail opcional. A denúncia é anônima para a ONG e entra na fila da equipe |
| Termos e Privacidade | `pages/termos.php`, `pages/privacidade.php` | Texto institucional real, linkado no rodapé |

### ONG logada

| Recurso | Página | O que faz |
|---|---|---|
| Cadastro | `pages/cadastro.php` | Nome, CNPJ, e-mail, senha, área, responsável legal e cargo, canal público, chave PIX obrigatória (igual ao CNPJ), cidade/UF, declaração de autorização e aceite dos termos. Nasce com status **pendente**, sem sessão e fora do site |
| Login / Logout | `pages/login.php`, `pages/logout.php` | `password_verify` + `session_regenerate_id(true)` no login; mensagens específicas para pendente, recusada e suspensa; CSRF, rate limit e timeout de sessão. O logout limpa sessão e cookie |
| Editar perfil | `pages/perfilprivado.php` | Nome, e-mail, área, descrição, canal público e cidade/UF. CNPJ, PIX e responsável legal são imutáveis pela ONG após a aprovação |
| Trocar senha | `pages/perfilprivado.php` | Exige confirmação e mínimo de 8 caracteres |
| Verificação e PIX | `pages/perfilprivado.php` | Card somente leitura com status, data de aprovação, data de verificação e chave PIX (CNPJ). Correções passam pela Equipe ONGHelp |
| QR Code automático | `includes/modal_doacao.php` | O QR é sempre montado a partir da chave PIX verificada, sem valor embutido, e desenhado no navegador com `assets/js/qrcode.min.js` |
| Personalizar tema | `pages/perfilprivado.php` | Escolha da cor do banner e da cor dos cards, com pré-visualização ao vivo. Sem cor definida, o perfil usa a paleta da área de atuação |
| Gestão financeira | `pages/gestao.php` | Cadastro e exclusão de receitas e despesas, com totais por tipo |
| Gestão de projetos | `pages/gestao.php` | Cadastro e exclusão de projetos (nome + indicador + unidade) |
| Minhas notícias | `pages/noticias.php` | KPIs de publicadas, rascunhos e ocultas; tabela com miniatura, tipo, estado, data e fotos; ações de ver, editar, publicar/despublicar e excluir com confirmação. Notícias ocultas pela equipe ficam somente para leitura, com o motivo e o canal de contato |
| Publicar notícia | `pages/noticia-editar.php` | Editor com título, resumo, texto, tipo, vínculos com projeto/movimentação, evento, links e até 10 fotos. Salva rascunho ou publica após as validações de conteúdo, direitos de imagem, limite diário e anti-PIX |
| Estatísticas | `pages/perfilprivado.php` | Projetos, movimentações e total arrecadado |
| Relatórios anuais | `pages/perfilprivado.php` | Seletor de ano, balancete de 12 meses, comparativo com o ano anterior e exportações PDF/CSV do ano escolhido |
| Excluir conta | `pages/desativar.php` | Hard delete com confirmação explícita e encerramento da sessão |

### Equipe ONGHelp (painel administrativo)

| Recurso | Página | O que faz |
|---|---|---|
| Acesso restrito | `admin/login.php` | Login individual para administrador e moderadores, com CSRF, rate limit, mensagens genéricas, sessão própria e timeout mais curto |
| Primeiro acesso | `admin/primeiro_acesso.php` | Senha temporária válida por 48 horas, checklist ao vivo, regras de conduta e troca obrigatória por senha pessoal; a senha temporária expira após a conclusão |
| Visão geral | `admin/index.php` | Cards de pendentes, aprovadas, recusadas e suspensas; busca por nome/CNPJ/e-mail/cidade; fila de pendentes com tempo em análise, aviso de denúncias pendentes e logs recentes |
| Análise do cadastro | `admin/verificar.php` | Dados da ONG, responsável legal, canal público, PIX, links para Receita/Google/Mapa das OSCs, histórico de verificações, painel de publicações e checklist |
| Checklist | `admin/verificar.php` | **11 bloqueantes** obrigatórios e **7 sinais de atenção** que não impedem a aprovação. O botão “Aprovar” só libera com todos os bloqueantes marcados, e o servidor revalida a regra |
| Decisões | `admin/acao.php` | Aprovar (grava `aprovado_em` e `verificada_em`), recusar, suspender, reativar, marcar e-mail de aprovação como enviado, corrigir PIX e responsável legal |
| Equipe | `admin/equipe.php` | Somente administrador: cria moderadores, gera senha temporária, redefine, desativa e reativa; mostra KPIs, painel da conta administradora, tabela de moderadores, atividade por pessoa em offcanvas e credenciais exibidas uma única vez. Não existe exclusão de moderador |
| Notícias | `admin/noticias.php` | Moderação: fila de denúncias, publicações, ocultas, KPIs, filtros, drawer de análise, ocultar/restaurar e bloquear publicações, com CSRF e logs |
| Minha conta | `admin/minha_conta.php` | Perfil pessoal, papel, membro desde, último acesso, troca da própria senha com confirmação da senha atual e, para moderadores, as ações recentes da conta |
| Logs | `admin/logs.php` | Histórico com filtros por categoria, pessoa e período, contador e carregamento de 25 em 25; chips de categoria, linha do tempo por dia e autoria individual. Inclui eventos de notícias e bloqueios |
| Logout | `admin/logout.php` | Encerra a sessão apenas por **POST + CSRF**; um GET nunca destrói a sessão |

### O que está propositalmente fora do escopo

Ver a [seção 9](#9-aderência-ao-escopo). Em resumo: não há processamento de
pagamentos, contabilidade fiscal, envio automático de e-mail nem suporte
técnico.

---

## 6. Decisões técnicas

**PHP puro, sem framework e sem Composer.** A escolha é coerente com o público-alvo
do projeto: a plataforma precisa rodar em qualquer XAMPP sem `composer install`.
O preço é a repetição de boilerplate de sessão e redirecionamento entre as
páginas — aceito em troca da portabilidade.

**Bootstrap 5.3 e ícones 1.11 via CDN.** Zero build step, zero arquivos de
bundle. `global.css` cobre apenas o que o Bootstrap não entrega: paleta, tipografia
Poppins, cards de ONG, gráfico de barras em CSS, balancete e layout de autenticação.

**JavaScript pequeno e sem build step.** `assets/js/qrcode.min.js` é o
`qrcodejs` (MIT), usado só para desenhar o QR estático no navegador a partir do
BR Code gerado pelo PHP. Os scripts da equipe são próprios e servidos
localmente: `admin-senha.js` faz o checklist ao vivo da senha e
`admin-equipe.js` cuida dos modais, da cópia e do descarte da senha temporária.
Não há framework de frontend, bundler nem biblioteca de gráfico. **Consequência
consciente:** o gráfico mensal é HTML e CSS puro, sem interação (zoom, tooltip
por barra).

**Batch queries para eliminar N+1.** `transparencia.php` busca projetos e
movimentações de todas as ONGs em uma consulta cada, indexando o resultado por
`usuario_id`. Com 8 ONGs funciona; com centenas de ONGs esse `IN (...)` começa a
ficar pesado, e aí vale migrar para `JOIN` com agrupamento.

**`balanceteDoAno()` é a fonte única da verdade.** A função em
`includes/helpers.php` monta as 12 linhas do balancete com saldo do mês e saldo
acumulado. Tanto o modal do portal quanto a seção "Balancete" do PDF chamam **a
mesma função** — é o que garante que o número da tela, do CSV exportado e do
arquivo baixado sejam sempre iguais. Se um dia alguém corrigir um cálculo,
corrige nos três lugares.

**O relatório anual é um PDF escrito à mão, sem biblioteca.** O projeto não usa
Composer e, neste ambiente, nem a extensão GD do PHP está disponível — o que
descarta DomPDF e afins. `includes/pdf.php` monta a estrutura do PDF
direto (catálogo, páginas, fontes e streams de conteúdo) usando apenas as 14
fontes padrão do formato, com `WinAnsiEncoding`. Duas consequências boas: nenhuma
fonte é embutida, então o arquivo fica com ~25 KB e abre idêntico em qualquer
leitor; e a acentuação do português é nativa, sem substituição.

O gerador também mede a largura real de cada glifo (larguras oficiais AFM do
Helvetica), o que permite alinhar números à direita, quebrar texto dentro da
célula e centralizar rótulos de gráfico. As tabelas paginam sozinhas repetindo o
cabeçalho, e o rodapé com "Página X de Y" é aplicado em todas as páginas.

**A ordem das exportações é crescente, e isso é deliberado.** O CSV e o PDF saem
do mais antigo para o mais novo, ao contrário das tabelas na tela. A ordem
depende do artefato, não do dado: "Últimas movimentações" na tela quer recência
primeiro; um documento de prestação de contas quer leitura cronológica. Como o
CSV e o relatório anual exportam o mesmo conjunto e aparecem lado a lado no
rodapé do modal do Portal de Transparência, divergir entre eles faria quem
cruzasse os dois arquivos ler a mesma movimentação em posições conflitantes. O
desempate é por `id ASC`, para que dois lançamentos na mesma data saiam na ordem
em que foram cadastrados.

**Tema por ONG com fallback por área.** Cada ONG pode definir `cor_tema` e
`cor_fundo`. Sem cor própria, `temaOng()` devolve a paleta da área. Isso dá
identidade visual sem exigir upload de logo, e o hex é validado por
`validarCorHex()` antes de ir para o banco — cor não validada poderia injetar
CSS pela barra de endereço.

**Doação é divulgação, não intermediação.** A plataforma não recebe, custodia nem
concilia pagamentos. O PIX acontece entre o doador e a ONG; o ONGHelp apenas
exibe a chave e o QR Code e direciona o doador para o pagamento no app do
banco. A chave é obrigatória e é **sempre o CNPJ da própria ONG**, sem máscara,
conferido pela Equipe ONGHelp na aprovação. Depois da aprovação, a ONG não
consegue mais alterá-la; qualquer correção passa pelo painel admin, com log e
nova data de verificação. `pixPayload()` monta um BR Code estático (EMVCo, com
CRC16) a partir da chave, sem valor embutido, e o navegador desenha o QR. Quando
a doação é recebida, a ONG a lança como receita em `movimentacoes`, e o portal
reflete o valor como qualquer outra entrada. A alternativa — integrar um PSP,
gerar QR dinâmico e confirmar por webhook — exigiria cadastro, conciliação e
custódia de recursos, incompatíveis com o escopo acadêmico e com o modelo
declaratório do portal. Não existe tabela `doacoes` nem upload de QR Code.

**`modal_doacao.php` como componente de divulgação.** O arquivo não sabe de onde vem o dado.
Quem o inclui define `$modalUid` (id único do modal na página) e `$modalOng` (nome,
cidade e chave PIX). Com isso, o mesmo componente serve à listagem de ONGs, ao
perfil público e ao portal de transparência sem nenhuma duplicação. O QR é
sempre gerado a partir da chave verificada, desenhado na abertura do modal e não
no carregamento da listagem; a chave continua copiável se a biblioteca falhar.
Em todos os casos, o modal apenas divulga o canal e não processa pagamentos.

**Verificação manual antes de publicar.** Todo cadastro nasce `pendente` e fica
invisível em todas as consultas públicas. A Equipe ONGHelp confere CNPJ, razão
social, natureza jurídica, CNAE, cidade/UF, canal público, contato, responsável
legal, autorização e titularidade do PIX antes de aprovar. A aprovação grava
`aprovado_em` e `verificada_em`; o selo mostra a data da última verificação. O
selo significa **conferência cadastral**, não auditoria financeira: os dados
financeiros continuam autodeclarados. Recusar e suspender exigem motivo, e o
histórico fica em `verificacoes` e `logs_admin`.

**Uma conta administradora compartilhada + moderadores individuais.** A conta
**Equipe ONGHelp** continua sendo a única administradora: não pode ser
desativada, excluída nem trocar de papel. As contas de moderação são pessoais,
com e-mail individual e autoria própria nos logs. O administrador cria,
redefine a senha, desativa e reativa moderadores; não há exclusão de conta pela
interface. O modelo separa o que precisa ser confiável e compartilhado (a
administração) do que precisa ser auditável (quem analisou cada ONG).

**Permissões declarativas, não espalhadas pelas telas.** `permissoesPorPapel()`
concentra as capacidades de cada papel. Páginas e ações chamam
`exigirPermissao($pdo, 'ong.analisar')` ou `exigirPermissao($pdo, 'equipe.gerenciar')`,
e a negativa é renderizada no casco do painel e registrada em `logs_admin`. Uma
capacidade nova, como moderação de notícias, entra nessa função e já pode ser
usada sem duplicar regras.

**Senha temporária só existe como hash.** Ao criar ou reativar uma conta, o
sistema gera a senha temporária, grava apenas o hash em `admins.senha` e mostra
o texto puro uma única vez, por um flash de sessão. O banco nunca guarda a senha
legível; se o administrador fechar o modal sem anotar, precisa gerar outra. A
validade é de 48 horas, e o primeiro acesso obriga a troca antes de liberar o
painel.

**Token de sessão no banco.** Cada conta tem `sessao_token`; o valor da sessão
é comparado em toda requisição. Redefinir a senha, desativar, reativar ou trocar
a própria senha rotaciona o token e invalida imediatamente as sessões antigas.
Desativar uma conta não apaga o histórico e não depende de esperar o timeout.

**Primeiro acesso com digest efêmero.** O checklist ao vivo precisa comparar a
nova senha com a temporária sem guardar a temporária em texto puro. O login
grava na sessão um sal aleatório e um digest SHA-256; o navegador usa Web Crypto
para marcar “diferente da senha temporária” enquanto a pessoa digita. A decisão
final continua no servidor, com `password_verify()` contra o hash real.

**WhatsApp em uma única configuração.** `includes/config.php` guarda o número
`5516997792777` e a mensagem exata. A página pública e qualquer CTA futuro usam
`whatsappEquipeUrl()`; não há número duplicado em HTML, JavaScript ou CSS. A
página é `noindex, nofollow`, linkada no rodapé com `rel="nofollow"` e não entra
na navegação principal.

**A aprovação é uma regra de servidor, não só de interface.** `admin/verificar.php`
mantém o botão “Aprovar” desabilitado até todos os bloqueantes serem marcados,
mas `admin/acao.php` refaz a checagem a partir do `$_POST` e recusa a aprovação
se faltar qualquer item. O checklist marcado é gravado em `verificacoes` como
JSON, com observações e resultado, para que a decisão seja auditável depois.

**Quem abre o modal é o Bootstrap, não o PHP.** O portal aceita `?modal=ID`
para abrir o relatório de uma ONG direto — é o que a home, a listagem e o perfil
privado usam. O modal, porém, é publicado **fechado** e marcado com
`data-abrir-no-load`; um `show()` no `DOMContentLoaded` é quem o abre.

A versão anterior fazia o oposto: o PHP colava `class="show"` e `display:block`
no HTML para o modal já nascer aberto. Parecia funcionar, mas o `hide()` do
Bootstrap começa com `if (this._isShown)`, e `_isShown` é estado da *instância*,
não do HTML. Como nenhum `show()` tinha rodado naquele modal, fechar não fazia
nada — e o botão "Doar via PIX", que depende de `hidden.bs.modal` para abrir o
modal seguinte, simplesmente não respondia. O modal também ficava sem backdrop
(fundo totalmente visível e clicável), sem resposta ao ESC e sem trava de foco.

Deixar o Bootstrap abrir corrige a família inteira de uma vez. Fechar passou a ser um
`<button data-bs-dismiss="modal">` em vez de `<a href>`: antes cada clique
recarregava a página e o scroll voltava ao topo. O `?modal=` remanescente é
removido com `history.replaceState`, que troca a URL sem recarregar e sem mexer
na posição da rolagem.

**Exclusão é hard delete.** Apagar a conta remove o usuário e, por `CASCADE`, todo
o histórico financeiro e todos os projetos. É o comportamento mais honesto frente
à LGPD, e a tela de confirmação deixa isso explícito antes do clique. O modelo
tem uma consequência discutível: a ONG que apaga a conta apaga também o próprio
histórico de prestação de contas. A alternativa de anonimização está no roadmap.

---

## 7. Segurança

### O que já está feito

- **Prepared statements** em todas as queries com dado de usuário, com
  `ATTR_EMULATE_PREPARES => false` (a proteção real, não a emulação)
- **`htmlspecialchars()`** em toda saída de dado vindo do banco
- **Senhas com `password_hash()` / `password_verify()`** e nunca retornadas em
  queries de perfil
- **CSRF em todos os formulários POST** (`includes/csrf.php`): token por sessão,
  campo oculto em cada formulário e validação no servidor com resposta 403
- **Rate limiting no login** (`includes/rate_limit.php`): 5 falhas por e-mail/IP
  em 15 minutos ou 20 falhas por IP em 1 hora bloqueiam a tentativa; login com
  sucesso limpa o histórico daquele e-mail/IP. ONG e admin têm escopos separados
- **Timeout e rotação de sessão** (`includes/session.php`): ONG expira após 30
  minutos de inatividade (máximo absoluto de 8 horas); admin expira após 20
  minutos (máximo de 4 horas); o id da sessão é regenerado a cada 15 minutos e
  no login. Cookie `httponly` e `samesite=Lax`, com `secure` automático em HTTPS
- **Detecção de HTTPS atrás de proxy reverso** (`requisicaoSegura()`): aceita
  `X-Forwarded-Proto: https` somente quando o IP imediato está na lista
  `ONGHELP_PROXIES_CONFIAVEIS` (ou na variável de ambiente
  `ONGHELP_TRUSTED_PROXIES`). Sem proxy configurado, o comportamento local
  continua HTTP
- **Headers de segurança** (`includes/security_headers.php`): CSP, `X-Frame-Options: DENY`,
  `X-Content-Type-Options: nosniff`, `Referrer-Policy` e `Permissions-Policy`
- **Painel admin separado**, com sessão própria, contas individuais, mensagem
  genérica de credencial inválida e revalidação da conta **e do `sessao_token`**
  no banco em toda requisição
- **Estados de conta aplicados no servidor**: `ativo` entra; `aguardando` só
  troca a senha temporária dentro de 48 horas; `desativado` perde a sessão
  imediatamente. A conta administradora não pode ser desativada nem trocar de
  papel, e moderadores não têm acesso à gestão da equipe
- **Política de senha da equipe**: mínimo de 10 caracteres, diferente da senha
  temporária e da senha atual, e sem conter o e-mail. O checklist do navegador é
  conveniência; a regra é revalidada no servidor com `password_verify()`
- **Logout somente POST + CSRF**: um GET em `admin/logout.php` não destrói a
  sessão, evitando logout por link, prefetch ou imagem de terceiros
- **Status revalidado a cada acesso da ONG**: perfil privado, gestão e exclusão
  exigem `status = 'aprovada'`; ONG suspensa ou recusada perde o acesso na hora
- **Trilha de auditoria** em `logs_admin` e `verificacoes`: ação, alvo, detalhe,
  IP, data e autoria individual; o histórico antigo permanece como Equipe ONGHelp
- **Página pública de recrutamento não indexada**: `pages/equipe.php` envia
  `noindex, nofollow`, não aparece no menu e o link do rodapé usa
  `rel="nofollow"`; o contato é centralizado em `includes/config.php`
- **PIX imutável pela ONG após a aprovação**; correção só pelo admin, com
  validação contra o CNPJ, log e nova data de verificação
- **Normalização de CNPJ** para 14 dígitos, checagem de duplicidade e validação
  de formato; a existência e a titularidade são conferidas manualmente
- **Validação de tipo e intervalo no `$_GET`**, com `ctype_digit()` para inteiros
- **Whitelist de valores** onde há interpolação: `LIMIT`/`OFFSET` de `ongs.php` são
  inteiros calculados no servidor, e `ORDER BY` passa por um `switch` de três casos
- **`LIMIT` e paginação** nas listagens públicas
- **Superfície de upload removida**: não existe mais upload de QR Code; a imagem
  do QR é sempre gerada no navegador a partir da chave PIX
- **Mensagem genérica** quando o banco está fora do ar, com o detalhe real indo
  para o `error_log()`
- **Handler global de exceções** em `db.php`, que cobre também erros de query
  (violação de chave, `NOT NULL`, valor inválido) e não só a falha de conexão

### HTTPS atrás de proxy reverso

No XAMPP local o site roda em HTTP, e o cookie de sessão não recebe `Secure`.
Em produção com Nginx, Apache intermediário ou balanceador, o PHP pode receber
a conexão como HTTP mesmo quando o visitante está em HTTPS. Para esse cenário,
`requisicaoSegura()` aceita o cabeçalho `X-Forwarded-Proto: https` **somente**
quando o IP imediato (`REMOTE_ADDR`) está em uma lista de proxies confiáveis:

- `ONGHELP_PROXIES_CONFIAVEIS`, em `includes/config.php`; ou
- variável de ambiente `ONGHELP_TRUSTED_PROXIES`, que tem prioridade.

A lista aceita IPs exatos e faixas CIDR, separados por vírgula, em IPv4 ou IPv6:

```php
const ONGHELP_PROXIES_CONFIAVEIS = '10.0.0.10, 172.16.0.0/12, 2001:db8::/32';
```

Sem essa configuração, o cabeçalho é ignorado — o que evita que qualquer
cliente forge `X-Forwarded-Proto` e force um cookie `Secure` em HTTP. Atrás de
um proxy, mantenha também o redirecionamento HTTP→HTTPS no próprio proxy.

### O que falta

| Lacuna | Gravidade | Observação |
|---|---|---|
| **Erros de PHP visíveis ao usuário** | Média | O XAMPP vem com `display_errors=On`; expõe caminho do servidor em falhas anteriores ao handler |
| **Credenciais no código** | Baixa (dev) | Aceito em XAMPP, inaceitável em produção |
| **Sem HTTPS obrigatório** | Média | O cookie usa `secure` quando detecta HTTPS; em produção, configure o proxy e force HTTPS nele |
| **Recuperação de senha ausente** | Média | Quem esquece a senha depende do administrador da equipe; não há e-mail automático de recuperação |
| **Contas de teste opcionais** | Baixa | Ficam fora do dump e da migração, em `sql/contas_teste.sql`, com senha pública; remova com `sql/remover_contas_teste.sql` antes de produção |
| **Sem segundo fator (2FA)** | Média | Login individual e rate limit existem, mas não há TOTP ou chave física |
| **Validação matemática de CNPJ** | Baixa | Hoje valida formato de 14 dígitos + conferência manual; os dígitos verificadores ficam no roadmap |

### Hard delete e LGPD

A exclusão é definitiva e remove também o histórico financeiro que já era público.
Isso atende ao direito ao apagamento, mas significa que **uma ONG que apaga a
conta apaga o próprio histórico de prestação de contas**. Vale considerar uma
alternativa de anonimização (manter os dados financeiros, remover os dados
pessoais) caso o projeto siga para produção.

---

## 8. Problemas conhecidos

Os itens abaixo foram **verificados no código ou reproduzidos no ambiente
atual** — não são suspeitas.

### 1. Home e ONGs em destaque (resolvido)

`index.php` ordena por `criado_em DESC, id DESC`, e `ongs.php` usa o nome como
critério secundário em todas as ordenações. O `id` como desempate elimina a
dependência do plano de execução do MySQL quando as ONGs de demonstração
compartilham o mesmo `criado_em`; antes, o carrossel e o bloco de destaque
mudavam de conteúdo entre execuções.

### 2. Os totais de `gestao.php` ignoram parte dos dados

A listagem tem `LIMIT 100` sem paginação, e os totais de receita e despesa são
somados em PHP **apenas sobre essas 100 linhas**. Uma ONG com mais de 100
movimentações vê o resumo "em receitas / em despesas" incorreto, sem nenhum
aviso na tela. O dado exibido na tabela também fica truncado sem indicação.

### 3. Código morto

- O fallback de nome genérico em `modal_doacao.php` é inalcançável: todos os
  pontos de inclusão já passam o nome real da ONG

### 4. Painel admin permite reaprovar uma ONG já aprovada

O formulário de checklist aparece em `admin/verificar.php` para qualquer status,
inclusive `aprovada`. Reenviar a aprovação não é bloqueado pelo servidor: ele
atualiza `verificada_em` para o momento atual e grava uma nova linha em
`verificacoes`. Na prática, isso serve como “reverificação”, mas a interface não
deixa isso explícito e o botão continua com o rótulo “Aprovar cadastro”.

### 5. Canal público editável após a aprovação

O plano original listava nome, e-mail, área, descrição, cidade/UF e tema como
editáveis pela ONG. `canal_tipo`/`canal_valor` também ficaram editáveis, porque é
o dado que o público usa para confirmar a organização. A alteração não dispara
nova verificação automática; o painel admin mostra o valor atual e a data da
última verificação, mas não sinaliza que o canal mudou depois dela.

### 6. JavaScript do `modal_doacao.php` (resolvido)

O bloco `<script>` com `copiarChave()` é emitido uma única vez por página,
protegido por `$GLOBALS['onghelpDoacaoScript']`. A duplicação descrita em
versões anteriores foi eliminada; `simularDoacao()` deixou de existir com a
mudança de escopo da doação.

### 7. Filtro de ano (resolvido)

`transparencia.php` deriva a lista de anos e o valor aceito da mesma consulta ao
banco, então o `<select>` e o filtro por URL concordam por construção. O
intervalo fixo de 2020 em diante deixou de existir.

### 8. Desempenho das imagens (resolvido)

As capas foram redimensionadas e comprimidas: `assets/img/` passou de ~93 MB
para ~1,6 MB — a maior é `assets/img/areas/ong-esporte.jpg`, com 578 KB.
Servir WebP continua no roadmap como ganho adicional, mas o peso deixou de
ser um problema.

### 9. Dados de demonstração

- As 8 ONGs do dump estão todas `aprovada`, com `aprovado_em` e `verificada_em`
  preenchidos; a fila de pendentes começa vazia
- Todas as contas compartilham o mesmo `criado_em`, o que exige desempate
  explícito nas ordenações — já aplicado em `index.php` e `ongs.php`
- Todas compartilham o mesmo hash de senha; a senha de demonstração é `senha123`
- A conta administradora é de demonstração; em produção, troque a senha em
  **Minha conta**. As três contas de moderação são opcionais, ficam em
  `sql/contas_teste.sql` e devem ser removidas com
  `sql/remover_contas_teste.sql`
  (veja [Removendo as contas de teste](#removendo-as-contas-de-teste))

### 10. Acessibilidade

- `.ong-transp-card` é um `div` com `data-bs-toggle` — abre um modal ao clique, mas
  **não é alcançável por teclado** e não é anunciado como botão
- Ícones em ações destrutivas não têm `aria-label`
- Modais não gerenciam foco ao fechar
- Sem meta `description` e sem tags Open Graph para compartilhamento em redes
  sociais

### 11. Revisão do modelo de equipe (resolvido)

A revisão final do acesso administrativo encontrou e corrigiu os pontos abaixo.
Todos foram verificados no código e, quando possível, exercitados no ambiente
local com o banco `onghelp`:

| Achado | Correção aplicada |
|---|---|
| O banco legado só tinha `admins.ativo`, incompatível com os três estados | `sql/migracao_equipe.sql` adiciona `estado`, converte `ativo = 1/0` para `ativo/desativado` e só então remove a coluna antiga |
| Uma conta compartilhada não permitia saber quem tomou cada decisão | Contas individuais de moderação, `admin_id` em cada log e autoria exibida em `logs.php`, `equipe.php` e `minha_conta.php` |
| Sessões antigas continuariam válidas após reset ou desativação | `sessao_token` comparado em toda requisição e rotacionado em reset, reativação, desativação e troca de senha |
| A senha temporária poderia ficar persistida em texto puro | Apenas o hash vai para `admins.senha`; o flash de credenciais mostra o texto uma vez e o JavaScript o apaga ao fechar o modal |
| O checklist “diferente da senha temporária” não tinha como comparar no navegador | Digest SHA-256 com sal efêmero na sessão, usado só como conveniência; o servidor revalida com `password_verify()` |
| Logout por GET permitia encerrar a sessão por link ou prefetch | `admin/logout.php` só aceita POST com CSRF; os botões usam um formulário oculto no rodapé |
| A conta administradora poderia ser desativada ou trocar de papel | A interface só lista moderadores nas ações de equipe e o servidor valida `papel = 'moderador'`; a conta principal fica fora dessas ações |
| Moderadores poderiam alcançar a gestão da equipe por URL direta | `exigirPermissao($pdo, 'equipe.gerenciar')` renderiza a página de negativa e grava `acesso_negado` |
| O número de WhatsApp poderia divergir entre páginas | `includes/config.php` é a fonte única do número e da mensagem; a página é `noindex, nofollow` e o link do rodapé usa `nofollow` |
| A migração poderia duplicar contas ou falhar em uma segunda execução | O script usa checagem em `information_schema`, `WHERE NOT EXISTS` por e-mail e pode ser reexecutado; foi executado duas vezes em banco temporário e aplicado ao banco local legado com backup |
| O dump novo poderia perder o histórico da conta compartilhada | A linha id 1 e os logs existentes foram preservados; o dump mantém apenas a conta `Equipe ONGHelp` e as contas de teste ficam em script separado |
| O hash fictício do login era o hash de uma senha conhecida | `admin/login.php` gera um hash aleatório e descartável a cada tentativa de e-mail inexistente |
| A detecção de HTTPS ignorava proxy reverso | `requisicaoSegura()` aceita `X-Forwarded-Proto` somente de proxies em `ONGHELP_PROXIES_CONFIAVEIS` ou `ONGHELP_TRUSTED_PROXIES` |
| Contas de teste ficavam no dump principal e na migração | Separação em `sql/contas_teste.sql` (criação) e `sql/remover_contas_teste.sql` (limpeza), com aviso destacado no README |
| Versões de CSS/JS em cache esconderiam o novo visual | `global.css` passou a `?v=38` em todas as páginas e os scripts da equipe usam `?v=4` |

---

## 9. Aderência ao escopo

Comparando com a documentação em `docs/ONGHelp_Documentacao.docx`. O item (iv) segue
a decisão de escopo registrada no documento: a plataforma **divulga** o canal
PIX da ONG, sem processar, custodiar ou conciliar pagamentos.

| Item do escopo | Status | Observação |
|---|---|---|
| (i) Aplicação web responsiva, sem instalação | ✅ Completo | Bootstrap 5, layout responsivo em toda parte |
| (ii) Cadastro e perfil institucional público | ✅ Completo | Inclui missão, área, projetos e contato |
| (iii) Portal de transparência com balancetes e relatórios exportáveis | ✅ Completo | Balancete mensal na tela e no painel, comparativo anual, relatório em PDF, CSV por ano e por período |
| (iv) Divulgação de doações via PIX | ✅ Completo | Chave obrigatória, sempre o CNPJ da ONG, e QR gerado a partir dela; o doador paga diretamente à ONG, que registra a receita no painel |
| (v) Painel de gestão de projetos | ✅ Completo | Cadastro, indicador e acompanhamento |
| (vi) Autenticação e controle de acesso | ✅ Completo | Login da ONG com status e rate limit, painel admin separado e páginas públicas para visitantes |
| Verificação manual e selo | ✅ Completo | Cadastro pendente, checklist de bloqueantes, datas de aprovação/verificação e log administrativo |
| Objetivo (d) Comunicações institucionais | ✅ Notícias completas | Hub público, notícia, denúncia, moderação e bloqueio implementados; newsletter segue fora do escopo |

**Resumo:** os seis itens de escopo estão completos, e o modelo de verificação
manual foi implementado como camada adicional de confiança. O item (iv) é, por
decisão deliberada, uma área de **divulgação**: a ONG informa a chave — que é
sempre o próprio CNPJ — e a plataforma gera o QR a partir dela. O doador paga
diretamente à organização no app do banco. Não há processamento, custódia nem
conciliação de pagamentos, e não existe tabela `doacoes` — quando a doação é
recebida, a ONG lança o valor como receita em `movimentacoes`, e o portal o
reflete como qualquer outra entrada. O selo “Verificada em dd/mm/aaaa” indica
conferência cadastral, não auditoria financeira. A justificativa está registrada
em `docs/ONGHelp_Documentacao.docx` e mantém o modelo declaratório do restante do
sistema. O objetivo (d) está implementado na parte de notícias: a ONG cria,
edita, publica e acompanha suas notícias; o público lê, compartilha e denuncia;
a equipe modera, oculta, restaura e bloqueia publicações. Newsletter e envio
automático de e-mails continuam fora do escopo.

---

## 10. Roadmap

Ordenado por impacto sobre o escopo declarado.

> **Doações não aparecem neste roadmap.** A ausência é decisão de escopo, não
> pendência: a plataforma divulga o canal PIX da ONG e a receita é lançada em
> `movimentacoes` como qualquer outra entrada. Integração com PSP, QR dinâmico ou
> tabela `doacoes` contrariaria o modelo declaratório e a decisão registrada em
> `docs/ONGHelp_Documentacao.docx`.

### Fase 1 — Fechar o escopo

1. **Newsletter institucional** — envio periódico de notícias e novidades;
   a área pública de notícias e a moderação já estão no código
2. **Balancete e relatório mais formais**
   - Balancete já aparece na tela e no PDF; falta o consolidado por período
     em uma única peça e a assinatura digital do documento

### Fase 2 — Robustez

3. **Paginação** em `gestao.php` e totais calculados no banco, não em PHP
4. **Recuperação de senha** por e-mail com token expirável
5. **Validação real de CNPJ** (dígitos verificadores), caso o selo passe a
   representar auditoria, e confirmação de senha no cadastro
6. **Notificações por e-mail** para pendente, aprovada, recusada e suspensa,
   mantendo o envio manual como fallback
7. **Revisão automática do canal público** quando ele muda depois da aprovação
8. **Reaprovação explícita** no painel: separar “reverificar” de “aprovar” e
   evitar que uma ONG já aprovada seja aprovada de novo por engano
9. **Anonimização em vez de hard delete**, preservando o histórico financeiro
   público quando a ONG encerra a conta

### Fase 3 — Evolução

10. **Gráfico interativo** com Chart.js, incluindo despesas mensais no comparativo
11. **Upload de logo** da ONG, no lugar das imagens estáticas por área
12. **Compressão de imagens** e servir WebP (impacto imediato de performance)
13. **Mapa das ONGs** (Leaflet) e busca por proximidade
14. **API REST** em JSON para integração futura
15. **Testes automatizados** — PHPUnit para `helpers.php` e E2E para os fluxos críticos
16. **Preparação para deploy** — `composer.json`, `.env` fora da raiz, HTTPS obrigatório

---

## 11. Tecnologias

| Camada | Escolha |
|---|---|
| Backend | PHP 8.2 — puro, sem framework |
| Banco de dados | MySQL 8 / MariaDB 10.4, via PDO |
| Frontend | Bootstrap 5.3.3 (CDN) |
| Ícones | Bootstrap Icons 1.11.3 (CDN) |
| Estilos | Design system próprio em `global.css` + Bootstrap |
| JavaScript | Bootstrap JS (CDN) + `qrcodejs` local + `admin-senha.js`/`admin-equipe.js` + scripts pontuais |
| Servidor | XAMPP (Apache + MySQL + PHP) |
| Tipografia | Google Fonts — Poppins |
| Dependências | Nenhuma instalada por gerenciador. `qrcodejs` é vendorizado em `assets/js/`; sem Composer, sem npm, sem build step |

---

## Equipe

**Desenvolvimento**
- Daniel Sydinei Sampaio Garcia
- Estevão Gabriel Feitosa Brito
- Raul Marin Bombarda

**Orientação**
- Prof. Rodrigo Sartor

**Disciplina**
- Engenharia de Software — Prof. Luciano Barros

---

<div align="center">

**ONGHelp** · Projeto acadêmico, 2026

*Feito para quem transforma o Brasil.*

</div>
