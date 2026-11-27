# ONGHelp

**Plataforma gratuita de gestão e transparência para ONGs brasileiras.**

Cadastro institucional, perfil público, portal de transparência com balancetes
mensais, relatórios exportáveis, notícias e divulgação de doações via PIX, em
um único lugar e sem depender de software pago.

Projeto Integrador de Engenharia de Software (2026)
Daniel Sydinei Sampaio Garcia · Estevão Gabriel Feitosa Brito · Raul Marin Bombarda
Orientação: Prof. Ricardo Sartor · Disciplina: Prof. Luciano Barros

---

## Índice

**Para rodar o projeto**

1. [Como o repositório está organizado](#1-como-o-repositório-está-organizado)
2. [Tutorial: abrindo o projeto pela primeira vez](#2-tutorial-abrindo-o-projeto-pela-primeira-vez)
3. [Contas de demonstração](#3-contas-de-demonstração)
4. [Roteiro rápido para conhecer o sistema](#4-roteiro-rápido-para-conhecer-o-sistema)
5. [Problemas comuns e soluções](#5-problemas-comuns-e-soluções)
6. [Atualizando o projeto e o banco](#6-atualizando-o-projeto-e-o-banco)
7. [Rodando os testes](#7-rodando-os-testes)

**Documentação técnica**

8. [O problema que o projeto resolve](#8-o-problema-que-o-projeto-resolve)
9. [Estrutura do site (`web/`)](#9-estrutura-do-site-web)
10. [Banco de dados](#10-banco-de-dados)
11. [Funcionalidades](#11-funcionalidades)
12. [Decisões técnicas](#12-decisões-técnicas)
13. [Segurança](#13-segurança)
14. [Limitações conhecidas e roadmap](#14-limitações-conhecidas-e-roadmap)
15. [Tecnologias](#15-tecnologias)

---

## 1. Como o repositório está organizado

```
ONGHelp/
├── back/     Documentação do back-end (o código PHP do servidor roda dentro de web/)
├── web/      O sistema completo: site, painel da ONG, painel da equipe, banco e testes
├── mobile/   Como usar o sistema no celular (o site é responsivo; ainda não há app nativo)
└── README.md Este arquivo
```

O ONGHelp é uma aplicação web em **PHP renderizado no servidor**: cada página
PHP aplica as regras de negócio, consulta o MySQL e devolve o HTML pronto. Por
isso back-end e front-end moram juntos em `web/`. A pasta [`back/`](back)
explica onde fica cada parte do servidor, e [`mobile/`](mobile) explica como
abrir o sistema no celular.

---

## 2. Tutorial: abrindo o projeto pela primeira vez

O tutorial é para **Windows** e leva uns 15 minutos. Não é preciso saber
programar.

### Passo 1: instalar o XAMPP

O XAMPP instala de uma vez o servidor web (Apache), o banco de dados (MySQL) e
o PHP.

1. Acesse [apachefriends.org](https://www.apachefriends.org/pt_br/index.html) e
   baixe o **XAMPP para Windows** com **PHP 8.2**.
2. Execute o instalador. Se aparecer um aviso sobre o "Controle de Conta de
   Usuário (UAC)", clique em **OK**: ele só recomenda não instalar em
   `C:\Arquivos de Programas`.
3. Na tela de componentes, deixe marcados pelo menos **Apache**, **MySQL**,
   **PHP** e **phpMyAdmin**.
4. Mantenha a pasta de instalação padrão: **`C:\xampp`**.
5. Conclua a instalação. Se o Firewall do Windows perguntar, permita o acesso
   do Apache e do MySQL.

### Passo 2: colocar o projeto na pasta do XAMPP

O XAMPP só enxerga os sites que estão dentro de **`C:\xampp\htdocs`**. Escolha
uma das duas formas:

**Forma A: baixando o ZIP (mais simples)**

1. Na página do repositório no GitHub, clique no botão verde **Code** e depois
   em **Download ZIP**.
2. Extraia o ZIP. Ele cria a pasta `ProjetoONGHelp-main`.
3. **Renomeie essa pasta para `ONGHelp`.**
4. Mova a pasta `ONGHelp` para dentro de `C:\xampp\htdocs\`.

> **Atenção ao extrair:** alguns programas criam uma pasta dentro de outra
> (`ProjetoONGHelp-main\ProjetoONGHelp-main\...`). O caminho certo é aquele em que você vê
> as pastas `back`, `web` e `mobile` direto em `C:\xampp\htdocs\ONGHelp\`.

**Forma B: clonando com o Git**

Abra o **Prompt de Comando** e digite:

```bat
cd C:\xampp\htdocs
git clone https://github.com/RaulMarinBombarda/ProjetoONGHelp.git ONGHelp
```

No fim, a pasta precisa ficar assim:

```
C:\xampp\htdocs\ONGHelp\
├── back\
├── web\
├── mobile\
└── README.md
```

> O nome `ONGHelp` não é obrigatório, mas define o endereço do site. Com outro
> nome (por exemplo, `projeto`), o endereço vira `http://localhost/projeto/`.

### Passo 3: ligar o Apache e o MySQL

1. Abra o **XAMPP Control Panel** (no menu Iniciar, ou em
   `C:\xampp\xampp-control.exe`).
2. Clique em **Start** na linha do **Apache** e na linha do **MySQL**.
3. Os dois nomes ficam com fundo **verde**. Se algum ficar vermelho ou
   amarelo, veja [Problemas comuns](#5-problemas-comuns-e-soluções).

Deixe o painel aberto enquanto usa o sistema.

### Passo 4: importar o banco de dados

O banco completo, com todas as ONGs, projetos, notícias e movimentações de
demonstração, está em um único arquivo: `web\database\onghelp.sql`.

**Pelo phpMyAdmin (recomendado)**

1. No navegador, abra **http://localhost/phpmyadmin**.
2. Clique na aba **Importar**, no topo da página.
3. Em "Arquivo a importar", clique em **Escolher arquivo** e selecione
   `C:\xampp\htdocs\ONGHelp\web\database\onghelp.sql`.
4. Não mude nenhuma outra opção. Role até o fim da página e clique em
   **Importar**.
5. Aparece uma mensagem verde de sucesso, e o banco **`onghelp`** surge na
   lista da esquerda.

> **Não é preciso criar o banco antes:** o próprio arquivo cria o `onghelp`.
> Se ele já existir, a importação recria as tabelas e volta tudo ao estado de
> demonstração.

**Ou pelo Prompt de Comando**

```bat
C:\xampp\mysql\bin\mysql -u root < C:\xampp\htdocs\ONGHelp\web\database\onghelp.sql
```

### Passo 5: abrir o site

No navegador, acesse:

**http://localhost/ONGHelp/**

O endereço leva automaticamente para `http://localhost/ONGHelp/web/`, que é a
página inicial. Pronto: o sistema está funcionando.

Endereços úteis:

| O quê | Endereço |
|---|---|
| Página inicial | http://localhost/ONGHelp/web/ |
| Entrar como ONG | http://localhost/ONGHelp/web/pages/login.php |
| Painel da equipe (administração) | http://localhost/ONGHelp/web/admin/ |
| Gerenciar o banco | http://localhost/phpmyadmin |

---

## 3. Contas de demonstração

Todos os dados são **fictícios**, criados só para a demonstração.

**ONGs:** o banco traz 38 ONGs de portes variados (36 aprovadas e 2
aguardando análise), nas 8 áreas de atuação e em 9 cidades do interior
paulista, com 70 projetos, 38 notícias e 2.806 movimentações de 2025 e 2026.
Todas usam a senha **`senha123`**. Exemplos:

| ONG | E-mail |
|---|---|
| Patas Unidas (Animais) | `patasunidas.ong@gmail.com` |
| Instituto Educacional Taquaritinga (Educação) | `ietaquaritinga.ong@gmail.com` |
| Associação Recicla Taquaritinga (Meio Ambiente) | `associacaoreciclataquari.ong@gmail.com` |

O e-mail de cada ONG aparece no painel da equipe: em **Visão geral**, clique
em **Analisar** na linha da ONG.

**Painel da equipe** (`/web/admin/`):

| Conta | E-mail | Senha | Papel |
|---|---|---|---|
| Equipe ONGHelp | `equipeonghelp@gmail.com` | `EquipeONGHelp@2026` | Administrador |
| Mariana Albuquerque | `mariana.albuquerque@moderacao.onghelp.test` | `Moderador@2026` | Moderador (teste) |
| Rafael Monteiro | `rafael.monteiro@moderacao.onghelp.test` | `Moderador@2026` | Moderador (teste) |
| Beatriz Nogueira | `beatriz.nogueira@moderacao.onghelp.test` | `Moderador@2026` | Moderador (teste) |

> **Estas senhas são públicas e servem só para demonstração.** Antes de
> colocar o site no ar de verdade, troque a senha da conta administradora em
> **Minha conta** e remova os moderadores de teste:
>
> ```bat
> C:\xampp\mysql\bin\mysql -u root onghelp < C:\xampp\htdocs\ONGHelp\web\database\remover-contas-teste.sql
> ```

As senhas de demonstração, como `senha123`, só funcionam porque já estavam no
banco: o sistema exige senha forte em todo cadastro e troca de senha novos
(ver [Regras de senha](#regras-de-senha)).

---

## 4. Roteiro rápido para conhecer o sistema

Um caminho de 10 minutos que passa pelas principais funções:

1. **Visitante:** na página inicial, veja os números da plataforma e as ONGs.
   Abra **ONGs**, filtre por área e entre no perfil de uma organização.
2. **Transparência:** em **Transparência**, clique em **Relatório** em
   qualquer ONG. Veja o gráfico mensal, o balancete de 12 meses e para onde foi
   o dinheiro; baixe o **CSV** (abre no Excel) e o **PDF** anual.
3. **Doação:** no perfil de uma ONG, clique em **Doar via PIX** para ver o QR
   Code gerado a partir do CNPJ.
4. **Notícias:** abra **Notícias**, filtre por causa e leia uma matéria.
5. **ONG:** em **Entrar**, use `patasunidas.ong@gmail.com` / `senha123`. Em
   **Gestão**, lance uma receita e confira que ela aparece no portal de
   transparência. Em **Minhas notícias**, crie uma notícia.
6. **Equipe:** saia e entre em `/web/admin/` com a conta **Equipe ONGHelp**.
   Em **Pendentes**, analise a "Associação Horta Viva": o botão **Aprovar** só
   libera depois que os 11 itens obrigatórios do checklist forem marcados.
7. **Avisos:** ainda no painel, use **Avisos às ONGs** para mandar um aviso;
   ele aparece no sino de notificações da ONG.

---

## 5. Problemas comuns e soluções

| Problema | Causa provável | Solução |
|---|---|---|
| O **Apache** não fica verde | Outro programa usando a porta 80 (Skype, IIS, VMware, outro servidor) | Feche o programa e tente de novo. Se não der, no XAMPP clique em **Config** do Apache → `httpd.conf`, troque `Listen 80` por `Listen 8080`, salve, reinicie o Apache e use `http://localhost:8080/ONGHelp/` |
| O **MySQL** não fica verde | Outro MySQL instalado usando a porta 3306 | Pare o outro MySQL em **Serviços** do Windows (`services.msc`) e clique em **Start** de novo |
| "Object not found" ou **erro 404** | A pasta está com outro nome ou em outro lugar | Confira se existe `C:\xampp\htdocs\ONGHelp\web\index.php` (atenção à pasta dentro de pasta do ZIP) |
| Página com aviso de **erro ao conectar ao banco** | MySQL desligado ou banco não importado | Ligue o MySQL no painel do XAMPP e refaça o [Passo 4](#passo-4-importar-o-banco-de-dados) |
| **Senha recusada** na conta de demonstração | O banco não foi importado, ou foi alterado em testes | Reimporte o `onghelp.sql` (volta tudo ao estado original) |
| Muitas tentativas: **login bloqueado** | Proteção contra força bruta (5 erros em 15 minutos) | Espere 15 minutos ou reimporte o banco |
| O site aparece **sem cores nem formatação** | O arquivo foi aberto direto pelo Explorador (`file:///...`) | Abra sempre pelo endereço `http://localhost/...` |
| O MySQL do computador **tem senha** | Instalação diferente da padrão do XAMPP | Coloque o usuário e a senha em `web\config\database.php` |
| O phpMyAdmin recusa o arquivo por **tamanho** | Limite de upload muito baixo | O arquivo tem cerca de 500 KB e o XAMPP aceita 40 MB; se mudaram o limite, use a importação pelo Prompt de Comando |

---

## 6. Atualizando o projeto e o banco

**Para baixar a versão mais nova do repositório:**

- Se você usou o Git: `cd C:\xampp\htdocs\ONGHelp` e depois `git pull`.
- Se você usou o ZIP: baixe o ZIP de novo e substitua a pasta.

Depois de atualizar, **reimporte** `web\database\onghelp.sql` (Passo 4), porque
a estrutura do banco pode ter mudado.

**Para salvar o seu banco atual no arquivo** (depois de mudar dados ou
estrutura):

```bat
cd C:\xampp\htdocs\ONGHelp\web
C:\xampp\mysql\bin\mysqldump -u root --databases onghelp --routines --default-character-set=utf8mb4 > database\onghelp.sql
```

O `--databases` faz o arquivo criar e selecionar o banco sozinho na
importação.

### Segurança do XAMPP

O XAMPP para Windows vem com o **PHP 8.2.12 (2023)**, vulnerável ao
**CVE-2024-4577**, explorado em massa em 2024 contra PHP no Windows. A
configuração padrão também deixa o `php-cgi.exe` exposto em `/php-cgi/`, que
é a porta desse ataque. O site não usa esse caminho, porque o PHP roda como
módulo do Apache. Para usar o XAMPP numa rede compartilhada:

1. **Feche o `/php-cgi/`:** em `C:\xampp\apache\conf\extra\httpd-xampp.conf`,
   comente (`#`) a linha `ScriptAlias /php-cgi/ "C:/xampp/php/"` e o bloco
   `<Directory "C:/xampp/php">` logo abaixo.
2. **Atualize o PHP dentro da série 8.2** (o código não precisa mudar):
   - baixe o pacote *VS16 x64 Thread Safe* mais recente da série 8.2 em
     [windows.php.net/download](https://windows.php.net/download/) e confira o
     SHA-256 publicado na mesma página;
   - pare o Apache, renomeie `C:\xampp\php` para `php-backup` e extraia o
     pacote numa nova `C:\xampp\php`;
   - copie da pasta antiga para a nova: `php.ini`, `pear\`, `extras\` (sem
     sobrescrever) e os demais arquivos que o XAMPP acrescenta (`logs\`,
     `tmp\`, `*.bat`);
   - se o log mostrar "Unable to load dynamic library 'curl'", acrescente
     `LoadFile "C:/xampp/php/brotlidec.dll"` depois das outras linhas
     `LoadFile` do `httpd-xampp.conf` e copie `libssh2.dll` e `nghttp2.dll` de
     `C:\xampp\php` para `C:\xampp\apache\bin` (guarde as antigas antes).
3. **Esconda as versões:** `expose_php=Off` no `php.ini` e, em
   `C:\xampp\apache\conf\extra\httpd-default.conf`, `ServerTokens Prod` e
   `ServerSignature Off`.
4. Reinicie o Apache, abra `http://localhost/php-cgi/php-cgi.exe` (tem que dar
   404) e rode os [testes](#7-rodando-os-testes).

---

## 7. Rodando os testes

O projeto tem quatro conjuntos de testes automatizados, num total de **857
testes**. Com o Apache e o MySQL ligados, abra o Prompt de Comando:

```bat
cd C:\xampp\htdocs\ONGHelp\web
C:\xampp\php\php.exe tests\unidades-test.php
C:\xampp\php\php.exe tests\noticias-test.php
C:\xampp\php\php.exe tests\e2e-test.php
C:\xampp\php\php.exe tests\varredura-test.php
```

Cada um termina com uma linha como `Testes: 405 | Falhas: 0`. **Falhas: 0**
significa que tudo funcionou.

| Arquivo | Testes | O que confere |
|---|---:|---|
| `unidades-test.php` | 216 | Funções sem abrir o navegador nem o banco: dinheiro, CNPJ e PIX (BR Code e CRC), canais públicos, senhas, validação de movimentações e projetos, limpeza do HTML das notícias contra XSS, links, slugs, notificações, proxies confiáveis e a coerência da configuração |
| `noticias-test.php` | 73 | Regras de notícias (limites, anti-PIX, estados), imagens ilustrativas e o mosaico, dentro de uma transação desfeita no final |
| `e2e-test.php` | 405 | Usa o site como um navegador (cookies, CSRF e redirecionamentos): cadastro, aprovação, login, gestão financeira, comprovantes (inclusive arquivos falsos), projetos, perfil, notícias, moderação, notificações, avisos, suspensão, equipe, exportações e controle de acesso |
| `varredura-test.php` | 163 | Percorre o site inteiro seguindo os links, como visitante, ONG, administrador e moderador, e confere erros do PHP, links quebrados, acessibilidade básica, CSRF, headers de segurança, pastas bloqueadas, permissões por papel e dados hostis (XSS e fórmulas no CSV) |

O `e2e-test.php` e o `varredura-test.php` criam contas próprias
(`@teste-e2e.onghelp.test`) e apagam tudo o que criaram no final; os dados de
demonstração não mudam. Se a pasta do projeto tiver outro nome, passe o
endereço do site: `C:\xampp\php\php.exe tests\e2e-test.php http://localhost/OUTRO/web/`.

---

## 8. O problema que o projeto resolve

O terceiro setor brasileiro tem centenas de milhares de organizações ativas,
mas a maioria das ONGs de pequeno e médio porte ainda controla dinheiro,
projetos e comunicação em planilhas e ferramentas soltas. Isso gera:

- **Prestação de contas desorganizada:** o doador não tem onde ver para onde
  foi o dinheiro.
- **Captação frágil:** a ONG depende de plataformas que cobram taxa e
  intermediam a relação com o doador.
- **Baixa visibilidade:** sem presença digital, é difícil conquistar parceiros
  e manter doadores.

O ONGHelp reúne tudo isso numa plataforma única e gratuita: a ONG publica seus
dados e o público confere.

---

## 9. Estrutura do site (`web/`)

Daqui em diante, os caminhos de arquivo são relativos à pasta `web/`.

```
web/
├── index.php              Home
├── .htaccess              Regras do Apache: bloqueia pastas internas, arquivos ocultos e de documentação
│
├── pages/                 Telas do site (visitante e ONG)
│   └── actions/           Endpoints sem tela: logout, exportar CSV, relatório PDF, denúncia
├── admin/                 Painel da equipe (administrador e moderadores)
│
├── includes/              PHP compartilhado (bloqueado para o navegador)
│   ├── bootstrap.php      Inicialização: toda página começa por ele
│   ├── core/              Base carregada em todas as páginas: caminhos, sessão, CSRF,
│   │                      banco, rate limit, headers de segurança, regras de senha
│   │                      e funções gerais
│   ├── modules/           Regras carregadas só onde são usadas: gestão (movimentações,
│   │                      projetos e comprovantes), notificações, notícias, fotos,
│   │                      permissões do painel e gerador de PDF
│   ├── components/        Blocos de interface reutilizados (modal de doação, cards,
│   │                      opções de WhatsApp, layout das páginas legais...)
│   └── layout/            Cabeçalho, rodapé e scripts do site e do painel
│
├── config/                Configuração (bloqueada para o navegador)
│   ├── app.php            Constantes: áreas, estados, contatos da equipe, limites de notícias
│   └── database.php       Dados de conexão com o MySQL
│
├── assets/
│   ├── css/               Estilos próprios: base.css (comum a tudo), site.css e
│   │                      noticias.css (site público), admin.css (painel)
│   ├── img/               Logo, favicons, ilustração da home e avatar padrão;
│   │                      areas/ (ícones das áreas) e noticias/ (imagens ilustrativas)
│   ├── js/                core/ (comum), site/ (páginas do site), admin/ (painel)
│   └── libs/              Bibliotecas de terceiros servidas localmente (sem CDN):
│                          Bootstrap, Bootstrap Icons, fontes e gerador de QR Code
│
├── uploads/               Arquivos enviados pelas ONGs pelo site (fora do Git); tem
│   │                      .htaccess que impede executar scripts
│   ├── perfis/            Fotos de perfil
│   ├── noticias/          Fotos das notícias
│   └── comprovantes/      Comprovantes das movimentações (PDF ou foto)
│
├── database/              onghelp.sql (banco completo) e remover-contas-teste.sql
├── docs/                  Documentação acadêmica, apresentações e o diagrama do fluxo
│                          (ONGHelp_Fluxo.drawio, abre no diagrams.net)
└── tests/                 Testes automatizados
```

### Arquivos enviados e o `.gitignore`

A pasta `uploads/` recebe o que as ONGs enviam pelo próprio site: fotos de
perfil, fotos de notícias e comprovantes. O nome de cada arquivo é gerado
pelo servidor (nunca o nome original), as fotos perdem os metadados e o
`uploads/.htaccess` impede que qualquer arquivo dali seja executado como
programa.

Esses arquivos são **dados de quem usa o site**, não código, por isso o
`web/.gitignore` deixa o conteúdo das três pastas fora do Git. Só os arquivos
`.gitkeep` (vazios) vão para o repositório, para que as pastas existam ao
clonar. As ONGs de demonstração não usam `uploads/`: as fotos delas são as
imagens de `assets/img/`.

### Convenções

- **Toda página começa por** `require_once __DIR__ . '/../includes/bootstrap.php';`.
- **Links e arquivos estáticos** usam `url('pages/x.php')` e `asset('css/base.css')`.
  O site funciona na raiz do domínio ou em qualquer subpasta, e o `?v=` dos
  arquivos muda sozinho quando eles mudam, sem cache velho.
- **CSS em quatro arquivos**, carregados por `includes/layout/head-assets.php`:
  `base.css` (tokens, fundo, tipografia, botões e formulários) vai em todas as
  telas; o site soma `site.css` e `noticias.css`; o painel (telas que definem
  `$css_area = 'admin'`) soma só `admin.css`. A ordem de carregamento faz
  parte da cascata: um estilo novo vai no arquivo da área que o usa. As regras
  que o site e o painel compartilham e que ajustam outras (modais, menus,
  campos, vidro) estão em `site.css` **e** em `admin.css`, na mesma posição;
  ao mudar uma delas, mude nos dois.
- **Nomes de arquivo em kebab-case** (`perfil-privado.php`, `rate-limit.php`).
- **Scripts por página:** cada página declara os seus antes do rodapé:

  ```php
  $page_scripts = ['site/noticia-editor.js'];
  include __DIR__ . '/../includes/layout/footer.php';
  ```

- **Comentários no código:** em minúsculas, sem ponto final e com vírgulas
  entre as frases; maiúscula só em siglas (CSS, PDF, PIX) e nomes próprios
  (ONGHelp, Bootstrap).
- **Regras de senha num lugar só:** `includes/core/senha.php` (servidor) e
  `assets/js/core/senha.js` (lista de requisitos marcada enquanto a pessoa
  digita) usam as mesmas regras.
- **Imagens substituíveis:** os ícones das áreas em `assets/img/areas/` e o
  avatar padrão (`assets/img/avatar-ong.png`) podem ser trocados mantendo o
  nome-base (PNG, WebP, JPG ou SVG), sem alterar o código.

---

## 10. Banco de dados

MariaDB 10.4 / MySQL 8, InnoDB, `utf8mb4`. O `database/onghelp.sql` já tem a
estrutura final e os dados; não há migrações a aplicar.

| Tabela | Guarda |
|---|---|
| `usuarios` | As ONGs: dados institucionais, status (`pendente`, `aprovada`, `recusada`, `suspensa`), datas de aprovação e verificação, responsável legal (privado), canal público, chave PIX e cor do banner |
| `movimentacoes` | Receitas e despesas (valor sempre positivo; o sinal vem do `tipo`), com categoria, projeto ligado (opcional), comprovante (opcional) e data da última edição |
| `projetos` | Projetos com descrição, situação (`planejado`, `andamento`, `concluido`), início e término, um indicador numérico com sua unidade ("850 mudas plantadas") e meta opcional |
| `notificacoes` | O que aparece no sino de cada ONG: avisos automáticos e cópias dos avisos escritos pelo administrador, com data de leitura |
| `avisos_equipe` | Cada aviso escrito em **Avisos às ONGs**: autor, destino (`todas` ou uma ONG), título, mensagem, página do botão e quantas ONGs receberam |
| `noticias`, `noticias_fotos`, `noticias_links` | Notícias das ONGs, suas fotos e links |
| `noticias_denuncias`, `noticias_bloqueios` | Denúncias do público e bloqueios de publicação |
| `admins` | Conta administradora e moderadores: papel, estado, token de sessão e senha temporária |
| `verificacoes` | Histórico de análises de cadastro, com o checklist em JSON |
| `logs_admin` | Auditoria das ações da equipe, com autoria, IP e data |
| `tentativas_login` | Base do limite de tentativas de login |

Regras importantes:

- **Só `status = 'aprovada'` aparece no site.** Toda consulta pública filtra por isso.
- **Exclusão em cascata:** apagar uma ONG apaga movimentações, projetos,
  notícias e notificações junto (`ON DELETE CASCADE`); a página de exclusão
  apaga também as fotos e os comprovantes do disco. Apagar um projeto mantém
  as movimentações e notícias ligadas a ele, só sem o vínculo.
- **Categorias e situações ficam em `config/app.php`**: o banco guarda o
  valor curto (`alimentacao`, `andamento`) e a tela mostra o rótulo.
- **Não existe tabela de doações.** A ONG recebe o PIX diretamente e lança o
  valor como receita em `movimentacoes`.
- **A chave PIX é sempre o CNPJ da ONG**, sem máscara, e fica bloqueada para a
  ONG depois da aprovação.
- **Há exatamente um administrador** (a conta compartilhada Equipe ONGHelp),
  que não pode ser desativado nem trocar de papel. Moderadores são contas
  individuais e podem ser desativados e reativados, nunca excluídos pela interface.

### Permissões da equipe

Declaradas em `permissoesPorPapel()` (`includes/modules/admin-auth.php`) e
verificadas no servidor em cada página e ação:

| Permissão | Administrador | Moderador |
|---|:---:|:---:|
| `painel.acessar` | ✅ | ✅ |
| `ong.analisar` | ✅ | ✅ |
| `noticias.moderar` | ✅ | ✅ |
| `logs.ver` | ✅ | ✅ |
| `equipe.gerenciar` | ✅ | — |
| `avisos.enviar` | ✅ | — |
| `conta.propria` | ✅ | ✅ |

Acesso negado é registrado em `logs_admin`.

---

## 11. Funcionalidades

### Visitante

| Recurso | Página | O que faz |
|---|---|---|
| Home | `index.php` | Números reais do banco, ONGs em destaque e últimas notícias |
| Lista de ONGs | `pages/ongs.php` | Filtros por nome, cidade, UF e área; ordenação; paginação; ONG em destaque |
| Portal de Transparência | `pages/transparencia.php` | Por ONG: gráfico mensal, despesas do ano por categoria, balancete de 12 meses, movimentações com categoria, projeto e comprovante, projetos com situação e meta, e exportações |
| Perfil público | `pages/perfil-publico.php?id=N` | Dados da ONG, projetos (situação, período, barra da meta e gastos), movimentações, notícias e doação via PIX |
| Doação via PIX | modal reutilizável | QR Code gerado a partir da chave verificada e chave copiável; o pagamento é feito no app do banco |
| Selo "Verificada" | várias páginas | Data da última verificação; indica conferência cadastral, não auditoria financeira |
| CSV e relatório anual | `pages/actions/` | CSV por ano ou período (abre direto no Excel) e PDF anual com balancete, despesas por categoria e projetos |
| Notícias | `pages/noticias.php`, `pages/noticia.php` | Busca e filtros, mosaico com cards de tamanhos variados, leitura, compartilhamento e denúncia anônima |
| Faça parte da equipe | `pages/equipe.php` | Página de voluntariado: o que a moderação faz, passos, regras e perguntas frequentes |
| Fale conosco | `pages/contato.php` | E-mail (Gmail no navegador, programa de e-mail ou copiar o endereço), WhatsApp e o caminho certo para cada assunto |
| Legislação | `pages/legislacao.php` | As leis do terceiro setor em linguagem simples, por tema e por área, com links para o texto oficial |
| Termos e Privacidade | `pages/termos.php`, `pages/privacidade.php` | Bases legais: LGPD, Marco Civil da Internet, ECA e Código Civil |

### ONG logada

| Recurso | Página | O que faz |
|---|---|---|
| Cadastro | `pages/cadastro.php` | Dados da ONG, responsável legal, canal público e PIX (igual ao CNPJ), senha forte. Nasce **pendente** |
| Login | `pages/login.php` | Mensagens para pendente, recusada e suspensa; limite de tentativas e expiração de sessão |
| Meu perfil | `pages/perfil-privado.php` | Editar dados e foto, cor do banner, trocar senha, status da verificação, estatísticas e relatórios anuais |
| Gestão | `pages/gestao.php` | Lançar, editar e excluir receitas e despesas (categoria, projeto ligado e comprovante opcional); criar, editar e excluir projetos; totais |
| Notificações | sino no topo e `pages/notificacoes.php` | Avisos automáticos (aprovação, suspensão, ajustes, moderação de notícias) e avisos do administrador |
| Minhas notícias | `pages/minhas-noticias.php`, `pages/noticia-editar.php` | Criar, editar, publicar, despublicar e excluir notícias, com fotos próprias ou imagem ilustrativa |
| Excluir conta | `pages/excluir-conta.php` | Exclusão definitiva, com confirmação |

### Equipe ONGHelp (`admin/`)

| Recurso | Página | O que faz |
|---|---|---|
| Login e primeiro acesso | `admin/login.php`, `admin/primeiro-acesso.php` | Login individual; moderador novo troca a senha temporária (válida por 48 h) e aceita as regras |
| Visão geral | `admin/index.php` | Contagem por status, busca e fila de cadastros pendentes |
| Análise do cadastro | `admin/verificar.php`, `admin/acao.php` | Checklist com 11 itens obrigatórios e 7 sinais de atenção; aprovar, recusar, suspender, reativar, corrigir PIX e responsável |
| Notícias | `admin/noticias.php` | Denúncias, ocultar e restaurar notícias, bloquear publicações de uma ONG |
| Avisos às ONGs | `admin/avisos.php` | Só o administrador: aviso para uma ONG ou para todas, com prévia e histórico de leitura |
| Equipe | `admin/equipe.php` | Só o administrador: criar moderadores, redefinir senha, desativar, reativar e ver atividade |
| Minha conta | `admin/minha-conta.php` | Dados pessoais e troca de senha |
| Logs | `admin/logs.php` | Histórico com filtros por categoria, pessoa e período |

### Regras da gestão

- **Categoria:** 6 de receita e 8 de despesa (`config/app.php`); sem escolha,
  a movimentação entra como "Outras receitas" ou "Outras despesas".
- **Projeto ligado:** só os projetos da própria ONG; o perfil e o painel somam
  as despesas ligadas a cada projeto.
- **Comprovante:** PDF, JPG, PNG ou WebP de até 5 MB, conferido pelo conteúdo
  (não pelo nome), salvo com nome aleatório e sem os metadados de localização
  das fotos. Ele fica **público** no portal, por isso o formulário avisa para
  cobrir dados pessoais antes de enviar.
- **Projeto:** situação planejado, em andamento ou concluído; o término não
  pode ser antes do início, e projeto concluído não pode terminar no futuro.

### Regras de senha

Valem para senhas novas (cadastro, troca de senha da ONG, primeiro acesso e
troca de senha da equipe):

| Regra | ONG | Equipe |
|---|:---:|:---:|
| Tamanho mínimo | 8 | 10 |
| Letras e números | ✅ | ✅ |
| Não contém o e-mail da conta | ✅ | ✅ |
| Não está na lista de senhas comuns nem repete um só caractere | ✅ | ✅ |
| No máximo 72 bytes (limite do bcrypt) | ✅ | ✅ |
| Diferente da senha atual (na troca) | ✅ | ✅ |
| Diferente da senha temporária (no primeiro acesso) | — | ✅ |

### Regras de notícias

- Título de 10 a 90 caracteres, resumo de 30 a 180, texto de 100 a 8.000, até
  3 links e até 10 fotos (até 5 MB e 24 megapixels cada, com descrição).
- As fotos são opcionais. Sem foto própria, a notícia usa uma das 3 imagens
  ilustrativas da área da ONG, escolhida no editor ou alternada
  automaticamente, e a página mostra o aviso "Imagem ilustrativa".
- No máximo 3 publicações por dia. Só ONG aprovada e sem bloqueio publica.
- O texto não pode conter chave PIX nem pedido de transferência.
- Ocultar uma notícia não apaga nada: texto, fotos e denúncias ficam guardados.

---

## 12. Decisões técnicas

- **PHP puro, sem framework, Composer ou npm.** Roda em qualquer XAMPP sem
  instalação extra. Cada página lê a requisição, consulta o banco e monta o
  HTML; a organização vem de funções pequenas e componentes incluídos.
- **Bibliotecas locais em `assets/libs/`, sem CDN.** O site funciona em redes
  que bloqueiam domínios externos (como laboratórios) e até sem internet.
- **O site funciona sem JavaScript.** Abas, filtros e seções são feitos em PHP
  com parâmetros na URL. O JavaScript só melhora a experiência.
- **Movimento reduzido respeitado.** Quando o sistema pede menos animação, o
  site remove os deslizes grandes e mantém o resto.
- **Mesma estrutura acessível em todas as telas.** Um único `<main>` por
  página e o link "Pular para o conteúdo" no primeiro Tab.
- **Uma fonte única para o balancete.** `balanceteDoAno()` alimenta o portal,
  o perfil privado e o PDF, então os números são sempre iguais nos três.
- **PDF escrito à mão, sem biblioteca** (`includes/modules/pdf.php`). Usa as
  fontes padrão do formato, então o arquivo fica pequeno, tem acentuação
  correta e abre igual em qualquer leitor.
- **Exportações em ordem cronológica.** CSV e PDF são documentos de prestação
  de contas e vão do mais antigo ao mais novo; a tela mostra o mais recente
  primeiro.
- **Doação é divulgação, não intermediação.** O ONGHelp gera um BR Code
  estático (sem valor) a partir do CNPJ e não recebe, guarda nem confere
  pagamentos.
- **Verificação manual antes de publicar.** A equipe confere CNPJ, dados,
  canal público, responsável e titularidade do PIX. O botão "Aprovar" só
  libera com todos os itens obrigatórios marcados, e o servidor confere de novo.
- **Administrador compartilhado e moderadores individuais.** A administração é
  única; cada análise tem autor identificado nos logs.
- **Cor do banner numa paleta fechada.** A ONG escolhe entre 16 cores com
  contraste testado; a cor da causa (anel da foto e bolinha da área) é sempre
  a da área, igual para todas as ONGs da mesma causa.
- **Avisos às ONGs pelo sino, não por e-mail.** O XAMPP não envia e-mail sem
  um servidor SMTP configurado, então a equipe fala com as ONGs dentro do
  próprio painel, e o botão do aviso só leva a páginas do próprio site.

---

## 13. Segurança

**O que está implementado:**

- Consultas preparadas (PDO) em tudo que recebe dado do usuário, e escape de
  toda saída com `e()` / `htmlspecialchars()`.
- Senhas com `password_hash()`; a senha temporária de moderador só existe como
  hash e aparece uma única vez na tela.
- Token CSRF em todos os formulários; logout só por POST.
- Limite de tentativas de login: 5 falhas por e-mail/IP em 15 minutos ou 20
  por IP em 1 hora.
- Sessões com expiração por inatividade (ONG 30 min, equipe 20 min), id
  renovado periodicamente e cookie `httponly`/`samesite`. O painel da equipe
  tem sessão separada da ONG.
- Status revalidado a cada página: ONG suspensa perde o acesso na hora.
- Headers de segurança (CSP, `X-Frame-Options`, `nosniff`, `Referrer-Policy`).
- **`.htaccess` da raiz do repositório:** leva para `web/`, bloqueia caminhos
  ocultos (como `.git`) e as pastas de documentação.
- **`web/.htaccess`:** bloqueia `config/`, `database/`, `docs/`, `includes/` e
  `tests/`, arquivos de documentação e banco soltos, e scripts dentro de
  `assets/`.
- **`web/uploads/.htaccess`:** nada enviado pelas ONGs pode ser executado.
- **CSV sem fórmulas:** células que começam com `=`, `+`, `-` ou `@` recebem
  um apóstrofo na frente, assim o Excel não executa nada que a ONG digitou.
- Erros do banco mostram mensagem genérica; o detalhe vai para o log.

**O que falta para produção:**

| Lacuna | Observação |
|---|---|
| Erros do PHP visíveis | O XAMPP vem com `display_errors=On`; desligue em produção |
| HTTPS obrigatório | Precisa ser configurado no servidor |
| Recuperação de senha | Não há envio de e-mail; quem esquece depende da equipe |
| Segundo fator (2FA) | Não implementado |
| Dígitos verificadores do CNPJ | Hoje valida o formato e a conferência é manual |
| CNPJ alfanumérico | Desde julho de 2026 a Receita emite CNPJs com letras e números; o cadastro aceita só os 14 dígitos do formato antigo |

---

## 14. Limitações conhecidas e roadmap

**Limitações:**

- **Reaprovar uma ONG já aprovada:** o checklist continua disponível e serve
  como "reverificação", mas o painel ainda não diferencia isso de uma aprovação nova.
- **Canal público editável depois da aprovação:** a mudança não dispara nova
  verificação.
- **Gestão sem paginação:** a lista mostra os 100 lançamentos mais recentes
  (os totais consideram todos).
- **Exclusão definitiva:** atende à LGPD, mas a ONG que apaga a conta apaga
  também o próprio histórico de prestação de contas.

**Roadmap:**

1. Newsletter e e-mails automáticos (aprovação, recusa, recuperação de senha)
2. Aplicativo mobile, consumindo uma API em `back/` sobre as mesmas regras
3. Paginação na Gestão
4. Separar "reverificar" de "aprovar" no painel e sinalizar mudança de canal
5. Validação dos dígitos do CNPJ, já no formato alfanumérico
6. Anonimização em vez de exclusão definitiva
7. Gráficos interativos e mapa das ONGs
8. Preparação para deploy: HTTPS obrigatório e credenciais fora do código

---

## 15. Tecnologias

| Camada | Escolha |
|---|---|
| Backend | PHP 8.2, sem framework |
| Banco | MySQL 8 / MariaDB 10.4, via PDO |
| Frontend | Bootstrap 5.3.3 e Bootstrap Icons 1.11.3 (locais) + CSS próprio em 4 arquivos |
| JavaScript | Bootstrap JS, qrcodejs e scripts próprios, sem build |
| Fontes | Poppins e Inter (locais) |
| Servidor | XAMPP (Apache + MySQL + PHP) |

---

## Equipe

**Desenvolvimento:** Daniel Sydinei Sampaio Garcia · Estevão Gabriel Feitosa Brito · Raul Marin Bombarda
**Orientação:** Prof. Ricardo Sartor
**Disciplina:** Engenharia de Software · Prof. Luciano Barros

<div align="center">

**ONGHelp** · Projeto acadêmico, 2026

*Feito para quem transforma o Brasil.*

</div>
