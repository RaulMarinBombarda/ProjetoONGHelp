# ONGHelp — Plano de Verificação, Administração e Confiança

- **Versão:** 1.4
- **Data:** 10/11/2026
- **Status:** implementado em 10/11/2026 — Lote E (notícias da ONG) e Lote F (moderação, denúncia, ocultação, bloqueio e logs) concluídos; a revisão 1.2.1 aplica as correções de hash fictício, detecção de HTTPS por proxy confiável e separação das contas de teste
- **Objetivo:** transformar o selo “Verificada” em um estado real, com cadastro pendente, análise manual, painel administrativo, datas de aprovação e verificação, PIX vinculado ao CNPJ, contas individuais de moderação, notícias geridas pela ONG e base mínima de segurança.

> **Nota de estado:** este documento nasceu como plano de implementação. Os
> lotes A, B, C, D, E e F da seção 9 foram executados, testados e estão no código.
> A versão 1.4 acrescenta o Lote F (moderação de notícias, denúncias, ocultação,
> restauração, bloqueio de publicações e logs) e atualiza as seções 3, 5, 9, 10,
> 11, 13 e 14. Onde uma seção antiga ainda descrever a conta compartilhada sem
> contas individuais, vale a seção 16. Itens ainda em aberto estão na seção 13.

---

## 0. Como usar este documento

Este arquivo é a fonte de verdade para a implementação. A nova sessão de execução deve lê-lo antes de alterar qualquer código.

Regras:

1. Não inventar decisões que não estejam aqui.
2. Se algo estiver marcado como “ponto em aberto”, perguntar antes de implementar.
3. Implementar em lotes, com teste entre eles.
4. Fazer backup/Git antes de começar.
5. Não alterar o design público sem necessidade; o painel administrativo deve seguir a identidade visual existente.
6. Toda query pública deve filtrar `status = 'aprovada'`.
7. Nenhum perfil pendente, recusado ou suspenso pode aparecer publicamente.

---

## 1. Contexto

### 1.1 O projeto

ONGHelp é uma plataforma PHP puro, sem framework, sem Composer e sem build, para ONGs brasileiras. Entrega:

- cadastro institucional;
- perfil público;
- portal de transparência com balancetes;
- movimentações e projetos;
- exportação CSV e relatório anual em PDF;
- divulgação de doação via PIX;
- painel da ONG logada.

Stack:

- PHP 8.2;
- MariaDB 10.4 / MySQL 8;
- Bootstrap 5.3 via CDN;
- Bootstrap Icons;
- Poppins;
- CSS próprio em `assets/css/global.css`;
- JavaScript vendorizado apenas para QR Code (`assets/js/qrcode.min.js`);
- PDF gerado à mão em `includes/pdf.php`.

### 1.2 Estado antes da implementação (snapshot)

Registro do que existia quando o plano foi escrito. Serve para entender o ponto
de partida; não descreve mais o código atual.

- 8 ONGs fictícias, IDs 24 a 31.
- 147 movimentações.
- 16 projetos.
- Senha de demonstração das ONGs: `senha123`.
- Todos os CNPJs demo são inválidos pelos dígitos verificadores, mas têm 14 dígitos.
- Existia apenas um QR oficial em disco: `assets/img/qrcodes/qr_30.png`.
- A coluna `emoji` já havia sido removida do SQL e do banco vivo.
- Não havia CSRF.
- Não havia rate limiting.
- Não havia expiração de sessão.
- Não havia headers de segurança.
- Não havia trilha de auditoria.
- `desativar.php` fazia hard delete e o `CASCADE` apagava movimentações e projetos.
- `gestao.php` somava totais apenas sobre `LIMIT 100`; é um bug conhecido, mas fora deste escopo.
- O README estava parcialmente atualizado e ainda precisava refletir o novo modelo.

### 1.3 Estado após a implementação

- Banco com 7 tabelas: `usuarios`, `movimentacoes`, `projetos`, `admins`,
  `verificacoes`, `logs_admin` e `tentativas_login`.
- `usuarios` sem `qr_code` e sem `ativo`; o status substituiu o booleano.
- Cadastro nasce `pendente`, não cria sessão e não aparece em nenhuma página pública.
- Painel admin em `admin/`, com uma conta administradora compartilhada e
  contas individuais de moderação, fila, checklist, decisões, gestão de equipe,
  primeiro acesso, histórico de verificações e logs com autoria.
- Novas rotas: `admin/primeiro_acesso.php`, `admin/equipe.php`,
  `admin/minha_conta.php` e a página pública `pages/equipe.php`.
- Estados de conta: `ativo`, `aguardando` (senha temporária de 48 horas) e
  `desativado`. A conta administradora nunca é desativada nem troca de papel.
- Aprovação grava `aprovado_em` e `verificada_em`; o selo mostra a data.
- PIX é sempre o CNPJ, bloqueado para a ONG após a aprovação e corrigível apenas
  pelo admin, com log e nova verificação.
- QR é sempre gerado a partir da chave; não existe upload nem arquivo de QR.
- CSRF, rate limit, timeout de sessão, headers e logs estão ativos.
- `onghelp.sql` foi regenerado a partir do banco vivo, com as 8 ONGs demo já
  aprovadas e a conta da Equipe ONGHelp.
- README, Termos, Privacidade e este plano refletem o modelo implementado.

### 1.4 Problema central

O cadastro é aberto. Qualquer pessoa pode criar uma ONG, informar CNPJ, chave PIX e publicar um perfil com aparência institucional. O selo “Verificada” é exibido para todas as ONGs ativas, sem nenhuma verificação real.

Isso permite:

- ONG inexistente;
- CNPJ real usado por pessoa errada;
- PIX de pessoa física no lugar da ONG;
- dados financeiros inventados;
- uso da credibilidade da plataforma para golpe.

A plataforma não processa pagamentos, mas empresta aparência de legitimidade. O selo precisa significar algo ou deixar de existir.

---

## 2. Decisões fechadas

### 2.1 Produto

| Decisão | Status |
|---|---|
| Cadastro continua aberto | Sim |
| Cadastro novo entra como pendente | Sim |
| Perfil pendente não aparece publicamente | Sim |
| Aprovação manual pela Equipe ONGHelp | Sim |
| Todas as ONGs aprovadas recebem selo | Sim |
| Selo exibe data de verificação | Sim |
| Dados financeiros marcados como autodeclarados | Sim |
| Denúncias | Previstas na área de notícias; a equipe analisa os relatos e pode ocultar a notícia |

### 2.2 Administração

| Decisão | Status |
|---|---|
| Uma única conta administradora compartilhada | Sim |
| Nome exibido: “Equipe ONGHelp” | Sim |
| Até 3 pessoas de confiança usando a conta administradora | Sim |
| Contas individuais de moderação | Sim; criadas pelo administrador |
| Hierarquia administrador/moderador | Sim |
| Permissões declarativas e revalidadas no servidor | Sim |
| Cadastro público de admin | Não |
| Página pública de voluntariado (“Faça parte da equipe”) | Sim, `noindex, nofollow`, com contato por WhatsApp |
| Tela de gestão de administradores | Sim, somente para o administrador (`admin/equipe.php`) |
| Exclusão de moderadores | Não; apenas desativação e reativação |
| Desativar ou trocar o papel da conta administradora | Proibido na interface e nas ações de servidor |
| E-mail institucional dedicado | Não é obrigatório; usar Gmail compartilhado com nome “Equipe ONGHelp” |
| Envio de aprovação | Manual, pelo Gmail compartilhado |
| Auditoria de autoria individual | Sim; `admin_id` em cada log |
| Revogação de acesso | Desativação imediata + rotação de `sessao_token` |
| Primeiro acesso | Senha temporária de 48 horas, hash no banco e troca obrigatória |
| Senha temporária exibida | Uma única vez, por flash de sessão; nunca recuperável depois |

### 2.3 Verificação

O admin verifica manualmente:

- CNPJ existe e está ativo na Receita;
- razão social confere;
- natureza jurídica é associação/fundação/OSC;
- CNAE e área de atuação são compatíveis;
- cidade/UF conferem;
- canal público existe e foi localizado independentemente;
- contato oficial confirmou o cadastro;
- responsável legal confere com fontes públicas;
- responsável legal autorizou o cadastro;
- PIX é do tipo CNPJ e o titular confere;
- nome não duplica outra ONG já cadastrada.

### 2.4 PIX e QR

| Decisão | Status |
|---|---|
| PIX obrigatoriamente do tipo CNPJ | Sim |
| Chave PIX igual ao CNPJ cadastrado | Sim |
| PIX bloqueado após aprovação | Sim |
| Alteração de PIX feita pelo admin, com nova verificação | Sim |
| Upload de QR Code | Removido |
| QR sempre gerado a partir da chave PIX | Sim |
| Campo `qr_code` no banco | Removido |
| Arquivo `qr_30.png` | Removido |

### 2.5 Segurança

Antes do painel administrativo:

- CSRF em todos os formulários POST;
- rate limiting no login de ONG e admin;
- sessão com timeout e cookie seguro;
- headers de segurança;
- mensagens genéricas de erro;
- log de ações administrativas;
- login admin separado;
- senha admin forte;
- nenhum cadastro público de admin.

### 2.6 Fora de escopo

- Cadastro público de moderador: a página de voluntariado é apenas contato, não um formulário de conta.
- Exclusão de moderadores pela interface: contas são desativadas, nunca apagadas.
- Permissões além das cinco chaves atuais; a estrutura permite acrescentar novas sem duplicar regras.
- Integração automática com a Receita.
- Envio automático de e-mail via SMTP; a senha temporária é entregue manualmente.
- Upload de documentos pessoais.
- Armazenamento de CPF/RG.
- Revalidação automática anual.
- 2FA.
- Recuperação de senha por e-mail; quem esquece depende do administrador.
- Correção do bug de `LIMIT 100` em `gestao.php`.

---

## 3. Modelo de dados

### 3.1 `usuarios`

Manter os campos atuais e adicionar/substituir:

```sql
status ENUM('pendente','aprovada','recusada','suspensa') NOT NULL DEFAULT 'pendente'
aprovado_em DATETIME NULL
verificada_em DATETIME NULL
verificado_por INT UNSIGNED NULL
motivo_recusa TEXT NULL
responsavel_nome VARCHAR(160) NOT NULL
responsavel_cargo VARCHAR(80) NOT NULL
responsavel_autorizacao TINYINT(1) NOT NULL DEFAULT 0
canal_tipo ENUM('site','instagram','facebook','telefone','outro') NOT NULL
canal_valor VARCHAR(255) NOT NULL
email_aprovacao_enviado_em DATETIME NULL
```

Regras:

- `status` controla presença pública e login.
- `aprovado_em` é a data da primeira aprovação.
- `verificada_em` é a data da última verificação.
- `verificado_por` referencia `admins.id`.
- `motivo_recusa` só é preenchido em recusa.
- `responsavel_nome` e `responsavel_cargo` são privados, visíveis apenas no painel.
- `responsavel_autorizacao` é o aceite da declaração.
- `canal_tipo` e `canal_valor` são obrigatórios; pelo menos um canal público.
- `email_aprovacao_enviado_em` registra quando o admin marcou o e-mail como enviado.

Remover:

- `ativo` — substituído por `status`;
- `qr_code` — upload removido.

Alterar:

- `pix_chave` para `VARCHAR(20) NOT NULL`, armazenando apenas os 14 dígitos do CNPJ.

Índices:

```sql
ADD KEY idx_usuarios_status (status);
ADD KEY idx_usuarios_status_cidade (status, cidade);
ADD KEY idx_usuarios_status_area (status, area);
```

Chaves estrangeiras:

```sql
ALTER TABLE usuarios
  ADD CONSTRAINT fk_usuarios_admin
  FOREIGN KEY (verificado_por) REFERENCES admins(id)
  ON DELETE SET NULL;
```

### 3.2 `admins`

```sql
CREATE TABLE admins (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome VARCHAR(160) NOT NULL,
  email VARCHAR(160) NOT NULL,
  senha VARCHAR(255) NOT NULL,
  papel ENUM('administrador','moderador') NOT NULL DEFAULT 'moderador',
  estado ENUM('ativo','aguardando','desativado') NOT NULL DEFAULT 'ativo',
  senha_temporaria_expira_em DATETIME NULL,
  sessao_token VARCHAR(64) NOT NULL DEFAULT '',
  ultimo_acesso_em DATETIME NULL,
  criado_por INT UNSIGNED NULL,
  conta_teste TINYINT(1) NOT NULL DEFAULT 0,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

Seed inicial:

```text
nome: Equipe ONGHelp
email: equipe.onghelp@gmail.com
senha: hash de EquipeONGHelp@2026 na demonstração
papel: administrador
estado: ativo
```

Contas de teste (opcionais, fictícias, ativas, `conta_teste = 1`):

| Nome | E-mail | Senha de demonstração | Papel |
|---|---|---|---|
| Sir Pipoca | `pipoca@moderacao.onghelp.test` | `Moderador@2026` | moderador |
| Dona Tapioca | `tapioca@moderacao.onghelp.test` | `Moderador@2026` | moderador |
| Capitão Cuscuz | `cuscuz@moderacao.onghelp.test` | `Moderador@2026` | moderador |

Essas contas **não fazem parte do dump principal nem da migração**. Para uma
demonstração, execute `sql/contas_teste.sql`; para removê-las, use
`sql/remover_contas_teste.sql`. Nunca importe o script de contas de teste em
produção.

Regras:

- Exatamente uma conta com `papel = 'administrador'`; as demais são moderadoras.
- A conta administradora não pode ser desativada, excluída nem trocar de papel.
- Moderadores não são excluídos pela interface; são desativados e reativados.
- `senha` guarda apenas hash bcrypt, inclusive durante o estado `aguardando`.
- `senha_temporaria_expira_em` é a validade de 48 horas do primeiro acesso.
- `sessao_token` é comparado em toda requisição e rotacionado em reset, troca de senha, desativação e reativação.
- `conta_teste = 1` marca dados fictícios, criados por `sql/contas_teste.sql` e removidos por `sql/remover_contas_teste.sql` antes de produção.
- Não criar cadastro público de admin. A conta administradora é criada por SQL/seed; as de moderação, pela tela Equipe.

### 3.3 `verificacoes`

```sql
CREATE TABLE verificacoes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id INT UNSIGNED NOT NULL,
  admin_id INT UNSIGNED NULL,
  checklist_json JSON NOT NULL,
  observacoes TEXT NULL,
  resultado ENUM('aprovado','recusado','suspenso') NOT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_verificacoes_usuario (usuario_id),
  CONSTRAINT fk_verificacoes_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  CONSTRAINT fk_verificacoes_admin
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

A checklist fica registrada mesmo que a ONG seja alterada depois.

### 3.4 `logs_admin`

```sql
CREATE TABLE logs_admin (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id INT UNSIGNED NULL,
  acao VARCHAR(60) NOT NULL,
  alvo_tipo VARCHAR(30) NULL,
  alvo_id INT UNSIGNED NULL,
  alvo_nome VARCHAR(160) NULL,
  detalhe TEXT NULL,
  ip VARCHAR(45) NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_logs_admin_data (criado_em),
  KEY idx_logs_admin_alvo (alvo_tipo, alvo_id),
  CONSTRAINT fk_logs_admin
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

Ações previstas:

- `login_admin`;
- `logout_admin`;
- `aprovar_ong`;
- `recusar_ong`;
- `suspender_ong`;
- `reativar_ong`;
- `marcar_email_enviado`;
- `alterar_pix`;
- `alterar_responsavel`;
- `conta_criada`;
- `senha_redefinida`;
- `conta_desativada`;
- `conta_reativada`;
- `senha_alterada`;
- `primeiro_acesso_concluido`;
- `acesso_negado`;
- `denuncia_recebida` (autoria **Visitante**, `admin_id = NULL`);
- `noticia_ocultada`;
- `noticia_restaurada`;
- `denuncia_improcedente`;
- `publicacoes_bloqueadas`;
- `publicacoes_liberadas`.

Cada linha guarda `admin_id` e a interface resolve o nome atual da pessoa
autora. O histórico anterior ao modelo individual continua como **Equipe
ONGHelp**, sem reescrita. Denúncias públicas nunca guardam o e-mail do
denunciante no log.

### 3.5 `tentativas_login`

```sql
CREATE TABLE tentativas_login (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  escopo ENUM('ong','admin') NOT NULL,
  email VARCHAR(160) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  sucesso TINYINT(1) NOT NULL DEFAULT 0,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tentativas_escopo_ip (escopo, ip, criado_em),
  KEY idx_tentativas_email (email, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

Limpeza oportunista de registros com mais de 24 horas.

### 3.6 Migração

Ordem segura:

1. Backup do banco e dos arquivos.
2. Criar `admins`.
3. Inserir a conta “Equipe ONGHelp”.
4. Adicionar colunas novas em `usuarios` como `NULL` quando necessário.
5. Preencher os dados demo.
6. Tornar `responsavel_nome`, `responsavel_cargo`, `canal_tipo`, `canal_valor` e `pix_chave` obrigatórios.
7. Adicionar FK de `verificado_por`.
8. Criar `verificacoes`, `logs_admin` e `tentativas_login`.
9. Atualizar todas as queries de `ativo = 1` para `status = 'aprovada'`.
10. Remover `ativo` e `qr_code` no fim do Lote C.
11. Atualizar `onghelp.sql`.
12. Criar `sql/migracao_verificacao.sql` para o banco vivo.
13. Testar a importação em banco temporário.
14. Para o modelo de equipe, criar `sql/migracao_equipe.sql`: adicionar
    `papel`, `estado`, `senha_temporaria_expira_em`, `sessao_token`,
    `ultimo_acesso_em`, `criado_por` e `conta_teste`; converter `ativo` para
    `estado`; remover `ativo`; e garantir a conta administradora. As três contas
    de teste ficam em `sql/contas_teste.sql`, fora da migração.
15. Executar a migração de equipe duas vezes no banco de teste para comprovar a
    idempotência.

Além disso, a migração normaliza o `cnpj` existente para 14 dígitos antes de
igualar `pix_chave` a ele. Sem esse passo, contas antigas gravadas com máscara
escapariam da checagem de duplicidade do cadastro.

Nunca rodar a migração sem backup. A migração de equipe preserva a linha id 1,
o nome, o e-mail, o hash e todo o histórico de `logs_admin` e `verificacoes`.

### 3.7 Dados demo

Todos os 8 registros passam para `status = 'aprovada'`, com data fictícia de aprovação e verificação.

Sugestão de data: `2026-06-09 10:00:00`.

| ID | ONG | Responsável | Cargo | Canal | PIX (CNPJ) |
|---|---|---|---|---|---|
| 24 | Instituto Educar Taquaritinga | Ana Paula Ferreira | Presidente | site `https://institutoeducar.org.br` | `12345678000190` |
| 25 | Saúde Sem Fronteiras | Carlos Henrique Souza | Diretor-Presidente | site `https://saudesemfronteiras.org.br` | `23456789000101` |
| 26 | Patas Solidárias | Mariana Alves | Presidente | Instagram `https://instagram.com/patassolidarias` | `34567890000112` |
| 27 | Verde Vida | João Pedro Lima | Coordenador-Geral | site `https://verdevida.org.br` | `45678901000123` |
| 28 | Ação Fraterna | Luciana Martins | Presidente | Facebook `https://facebook.com/acaofraterna` | `56789012000134` |
| 29 | Instituto Cultural de Ribeirão Preto | Rafael Gomes | Diretor | site `https://institutoculturalrp.org.br` | `67890123000145` |
| 30 | Esporte Cidadão | Bruno Oliveira | Presidente | Instagram `https://instagram.com/esportecidadao` | `78901234000156` |
| 31 | GAMA — Grupo de Apoio ao Meio Animal | Fernanda Costa | Presidente | Instagram `https://instagram.com/gamaanimais` | `89012345000167` |

Todos os dados acima são fictícios. O README deve deixar isso explícito.

---

## 4. Fluxos

### 4.1 Cadastro

1. ONG acessa `pages/cadastro.php`.
2. Preenche:
   - nome;
   - CNPJ;
   - e-mail;
   - senha e confirmação;
   - área;
   - chave PIX;
   - cidade/UF;
   - responsável legal (nome e cargo);
   - canal público (tipo e valor);
   - aceite dos termos.
3. Validações:
   - campos obrigatórios;
   - e-mail válido;
   - CNPJ com 14 dígitos após normalização;
   - PIX com 14 dígitos e igual ao CNPJ;
   - área válida;
   - senha mínima de 8 caracteres;
   - confirmação de senha;
   - termos aceitos;
   - canal público válido;
   - declaração de autorização marcada;
   - e-mail/CNPJ únicos.
4. Cadastro é inserido com `status = 'pendente'`.
5. Nenhuma sessão é criada.
6. Tela de sucesso: “Cadastro enviado para análise da Equipe ONGHelp.”
7. Nada aparece nas páginas públicas.

### 4.2 Análise

1. Admin entra em `admin/index.php`.
2. Vê a fila de pendentes.
3. Abre `admin/verificar.php?id=N`.
4. Confere os dados com as fontes externas.
5. Preenche a checklist.
6. Decide.

### 4.3 Aprovação

1. Todos os bloqueantes marcados.
2. Admin clica em “Aprovar”.
3. Servidor valida a checklist novamente.
4. Atualiza:
   - `status = 'aprovada'`;
   - `aprovado_em = NOW()`;
   - `verificada_em = NOW()`;
   - `verificado_por = admin_id`.
5. Insere registro em `verificacoes`.
6. Insere log `aprovar_ong`.
7. Exibe modelo de e-mail de aprovação.
8. Admin envia manualmente pelo Gmail compartilhado.
9. Admin marca “E-mail enviado”, gravando `email_aprovacao_enviado_em`.
10. ONG passa a aparecer publicamente com selo e data.

### 4.4 Recusa

1. Admin marca o motivo.
2. Status vira `recusada`.
3. Perfil continua invisível.
4. Log `recusar_ong`.
5. Modelo de e-mail de recusa com o motivo.
6. ONG pode contatar a equipe para corrigir; não há reenvio automático neste escopo.

### 4.5 Suspensão

1. Admin informa o motivo.
2. Status vira `suspensa`.
3. Perfil sai do ar.
4. Dados financeiros e histórico permanecem no banco.
5. Log `suspender_ong`.
6. Modelo de e-mail de suspensão.
7. Admin pode reativar depois, voltando para `aprovada` com nova `verificada_em`.

### 4.6 Login da ONG

1. `pages/login.php` valida CSRF e rate limit.
2. Busca por e-mail.
3. Verifica senha.
4. Verifica status:
   - `aprovada`: cria sessão e redireciona para `perfilprivado.php`;
   - `pendente`: “Seu cadastro está em análise pela Equipe ONGHelp.”;
   - `recusada`: “Seu cadastro não foi aprovado. Entre em contato com a Equipe ONGHelp.”;
   - `suspensa`: “Sua conta está suspensa. Entre em contato com a Equipe ONGHelp.”.
5. Nunca revelar status para quem não acertou a senha.

### 4.7 PIX

1. Cadastro exige chave PIX igual ao CNPJ.
2. Admin confere no app do banco:
   - inicia transferência para a chave;
   - confere o nome do recebedor;
   - cancela antes de confirmar.
3. Após aprovação, o campo PIX fica bloqueado.
4. Alteração só pelo admin, com nova verificação e log.
5. QR é sempre gerado a partir da chave verificada.

### 4.8 Alteração de dados

- Nome, e-mail, área, descrição, cidade/UF e tema continuam editáveis pela ONG.
- CNPJ é imutável.
- PIX é imutável após aprovação.
- Responsável legal é imutável após aprovação; alteração via admin.
- Canal público pode ser editado, mas alteração substancial deve ser revisada pelo admin.
- Qualquer alteração de PIX/responsável exige nova verificação.

### 4.9 Equipe e primeiro acesso

1. A pessoa interessada em moderar acessa `pages/equipe.php` (página não indexada) e chama a equipe no WhatsApp.
2. O administrador entra em `admin/equipe.php` e cria a conta com nome e e-mail individual.
3. O sistema gera uma senha temporária aleatória, grava apenas o hash e mostra o texto uma única vez.
4. A conta nasce com `estado = 'aguardando'` e `senha_temporaria_expira_em = NOW() + 48 horas`.
5. A pessoa entra em `admin/login.php` com a senha temporária e é levada a `admin/primeiro_acesso.php`.
6. A tela mostra a validade, o checklist ao vivo e as regras de conduta.
7. No envio, o servidor revalida:
   - mínimo de 10 caracteres;
   - senha diferente da temporária (`password_verify` no hash antigo);
   - senha sem o e-mail;
   - confirmação igual;
   - aceite das regras.
8. A senha pessoal substitui o hash, `estado` vira `ativo`, `senha_temporaria_expira_em` vira `NULL` e `sessao_token` é rotacionado.
9. O evento `primeiro_acesso_concluido` é registrado e a pessoa entra no painel conforme o papel.

Se a senha temporária expirar, o login reconhece o estado somente depois de a
senha conferir e mostra a mensagem de expiração. O administrador precisa gerar
uma nova senha. Reativar uma conta desativada também gera nova senha temporária
e volta o estado para `aguardando`.

Desativar uma conta:

1. O administrador confirma a ação no modal.
2. O servidor valida que o alvo é moderador e que não é a própria conta.
3. `estado` vira `desativado`, `senha_temporaria_expira_em` vira `NULL` e
   `sessao_token` é rotacionado.
4. Qualquer sessão aberta daquela conta perde validade na requisição seguinte.
5. A conta e o histórico permanecem no banco; não há `DELETE`.

---

## 5. Painel administrativo

### 5.1 Rotas

Criar pasta `admin/` na raiz:

```text
admin/
├── login.php
├── primeiro_acesso.php
├── index.php
├── verificar.php
├── acao.php
├── noticias.php
├── equipe.php
├── minha_conta.php
├── logs.php
└── logout.php
```

Includes:

```text
includes/admin_auth.php
includes/admin_header.php
includes/admin_footer.php
includes/config.php
```

CSS e JavaScript:

```text
assets/css/global.css       (seção ADMIN + EQUIPE)
assets/js/admin-senha.js    (checklist ao vivo)
assets/js/admin-equipe.js   (modais, cópia e descarte da senha)
assets/js/admin-verificar.js
```

### 5.2 Layout

- Sidebar escura em desktop, offcanvas em mobile.
- Topbar branca com pílula do usuário (avatar, nome, papel, e-mail), link para Minha conta e sair por POST.
- Navegação por papel: “Equipe” só aparece para administrador; “Logs” e “Minha conta” para todos.
- Conteúdo em `var(--cor-fundo)`.
- Cards brancos com `var(--radius)` e `var(--shadow-card)`.
- Mesma paleta, tipografia e botões do site.
- Ícones Bootstrap Icons.
- Sem framework novo.
- Sem inline styles, exceto cores dinâmicas.

### 5.3 Login

`admin/login.php`:

- reutilizar `.auth-layout`, `.auth-left`, `.auth-right`, `.auth-form-wrap`, `.auth-input`, `.auth-btn`;
- texto “Acesso da equipe”;
- marca “Equipe ONGHelp”;
- campos e-mail e senha;
- CSRF;
- rate limit por e-mail/IP;
- mensagem genérica para e-mail inexistente ou senha errada;
- estados `desativado` e `aguardando` só são revelados **depois** de a senha conferir;
- `session_regenerate_id(true)` no sucesso;
- sessão `admin_id`, `admin_nome`, `admin_papel`, `admin_sessao_token` e `admin_pendente`;
- conta `aguardando` redireciona para `primeiro_acesso.php`; conta `ativo`, para `index.php`.

`admin/primeiro_acesso.php`:

- reutilizar o mesmo layout de autenticação;
- validar a senha temporária no login e avisar quando ela estiver expirada;
- checklist ao vivo com Web Crypto;
- regras de conduta com aceite obrigatório;
- servidor revalida todos os requisitos e o prazo de 48 horas;
- rotacionar o token e liberar o painel.

### 5.4 Dashboard e fila

`admin/index.php`:

- cards de resumo:
  - pendentes;
  - aprovadas;
  - recusadas;
  - suspensas.
- fila de pendentes em tabela/cards:
  - ID;
  - nome;
  - CNPJ;
  - cidade/UF;
  - data do cadastro;
  - tempo em análise;
  - botão “Analisar”.
- filtros por status.
- aviso de vidro acima da fila quando houver denúncias de notícias pendentes:
  “N denúncias de notícias aguardam análise →”, apontando para `noticias.php`;
  sem pendências, o aviso não é renderizado.
- link para logs recentes.

### 5.5 Detalhe da ONG

`admin/verificar.php?id=N`:

- dados da ONG;
- CNPJ formatado com botão copiar;
- links:
  - Consulta CNPJ da Receita;
  - Google;
  - site/Instagram/Facebook informado;
  - Mapa das OSCs.
- responsável legal e cargo;
- canal público;
- chave PIX;
- declaração de autorização;
- painel **Publicações**, com estado Liberadas/Bloqueadas desde a data e a
  pessoa autora, motivo e botão para bloquear ou liberar com confirmação em
  modal (motivo obrigatório ao bloquear);
- checklist;
- observações;
- ações.

### 5.6 Checklist

Chaves em JSON:

**Bloqueantes:**

```text
cnpj_ativo
razao_social
natureza_juridica
cnae_area
cidade_uf
canal_publico
contato_confirmado
responsavel_confere
responsavel_autorizou
pix_titular
nome_unico
```

**Sinais de atenção:**

```text
email_generico
cnpj_recente
endereco_divergente
nome_parecido
canal_fraco
sem_mapa_osc
pix_recente
```

Regras:

- “Aprovar” só habilita com todos os bloqueantes marcados.
- O servidor valida novamente; não confiar no cliente.
- Observações obrigatórias em recusa e suspensão.
- A checklist é salva em `verificacoes.checklist_json`.

### 5.7 Ações

`admin/acao.php`:

- aceitar apenas POST;
- validar CSRF;
- validar `acao`;
- validar permissão de admin;
- executar:
  - `aprovar`;
  - `recusar`;
  - `suspender`;
  - `reativar`;
  - `marcar_email_enviado`;
  - `alterar_pix`;
  - `alterar_responsavel`.
- gravar log;
- redirecionar com mensagem flash.

### 5.8 Modelos de e-mail

Aprovação:

```text
Assunto: Cadastro aprovado — ONGHelp

Olá, [responsável]!

O cadastro da [ONG] foi aprovado pela Equipe ONGHelp.

Acesse a plataforma em:
[URL]/pages/login.php

Use o e-mail [e-mail] e a senha que você definiu no cadastro.

Se você não solicitou este cadastro, ignore esta mensagem.

Equipe ONGHelp
```

Recusa:

```text
Assunto: Cadastro não aprovado — ONGHelp

Olá, [responsável]!

Analisamos o cadastro da [ONG] e não foi possível aprová-lo neste momento.

Motivo: [motivo]

Você pode entrar em contato com a Equipe ONGHelp para corrigir as informações.

Equipe ONGHelp
```

Suspensão:

```text
Assunto: Cadastro suspenso — ONGHelp

Olá, [responsável]!

O perfil da [ONG] foi suspenso preventivamente.

Motivo: [motivo]

Entre em contato com a Equipe ONGHelp para mais informações.

Equipe ONGHelp
```

Nunca enviar senha por e-mail.

### 5.9 Logs

`admin/logs.php`:

- filtros por tipo (Decisões, Acessos, Conta e senha, Equipe, Notícias), por
  pessoa (conta individual) e por intervalo de datas;
- contador total e carregamento incremental de 25 em 25, preservando os filtros;
- agrupamento visual por dia, com linha do tempo;
- ícone circular de 36px por tipo de evento, com o ícone específico da ação;
- alvo, detalhe, IP, data e horário;
- autoria individual resolvida por `admin_id`, com fallback “Equipe ONGHelp”
  para o histórico anterior e “Visitante” para denúncia pública;
- ações desconhecidas caem na categoria visual “Outros”, sem quebrar a tela.

A categoria **Notícias** inclui `denuncia_recebida`, `noticia_ocultada`,
`noticia_restaurada`, `denuncia_improcedente`, `publicacoes_bloqueadas` e
`publicacoes_liberadas`. Ações de conta e acesso negado também entram na trilha:
`conta_criada`, `senha_redefinida`, `conta_desativada`, `conta_reativada`,
`senha_alterada`, `primeiro_acesso_concluido` e `acesso_negado`.

### 5.10 Gestão da equipe

`admin/equipe.php`, somente com `equipe.gerenciar`:

- três KPIs de contas de moderação (ativas, aguardando primeiro acesso,
  desativadas);
- painel da conta administradora, com aviso de que ela não pode ser desativada
  nem trocar de papel e atalho para alterar a senha;
- tabela de moderadores com pessoa (nome, e-mail e etiqueta “Teste”), status
  (com a observação “senha temporária expirada” quando for o caso), último
  acesso, criado por (data e autor) e menu de ações;
- criação de moderador com nome e e-mail único;
- redefinição de senha com nova validade de 48 horas;
- desativação e reativação (reativação gera nova senha temporária);
- drawer de atividade por pessoa, com controle “Tudo · Decisões · Notícias ·
  Conta”;
- modal de credenciais exibido uma única vez, com cópia e descarte do texto ao
  fechar;
- nenhuma ação de exclusão de moderador.

O servidor valida que o alvo é `papel = 'moderador'` e que não é a própria conta
do administrador. A conta principal não aparece na lista de alvos.

### 5.11 Minha conta

`admin/minha_conta.php`, com `conta.propria`:

- nome, e-mail, papel, “Membro desde” e último acesso;
- troca de senha com confirmação da senha atual, mínimo de 10 caracteres,
  senha diferente da atual, sem o e-mail (ou o trecho antes do `@`) e com
  confirmação idêntica;
- rotação de `sessao_token` (as demais sessões caem);
- atividade recente da própria conta, exibida para moderadores;
- etiqueta de conta fictícia quando `conta_teste = 1`.

### 5.12 Aplicação de permissões

Toda página do painel começa por uma destas funções:

```text
exigirAdminAtivo($pdo)
exigirPrimeiroAcesso($pdo)
exigirPermissao($pdo, 'ong.analisar')
exigirPermissao($pdo, 'noticias.moderar')
exigirPermissao($pdo, 'logs.ver')
exigirPermissao($pdo, 'equipe.gerenciar')
exigirPermissao($pdo, 'conta.propria')
```

`exigirAdminAtivo()` revalida conta, estado e token no banco em toda requisição.
Uma negativa renderiza a página de permissão dentro do layout do painel, grava
`acesso_negado` e responde HTTP 403. O menu esconde itens sem permissão, mas a
proteção real é sempre a chamada no servidor.

### 5.13 Moderação de notícias

**Fluxo.** O visitante denuncia uma notícia publicada em `pages/noticia.php`; a
denúncia nasce pendente e agrupada em `admin/noticias.php`. A equipe pode manter
publicada (improcedente), ocultar com categoria e mensagem (mínimo 20
caracteres), ocultar e bloquear ou restaurar. Ocultar não apaga texto, fotos nem
denúncias: a ONG vê o motivo em Minhas notícias e o conteúdo continua guardado.
Uma nova denúncia em notícia tratada reabre o caso. Na análise da ONG
(`admin/verificar.php`), o painel Publicações bloqueia ou libera o envio de
novas notícias, com motivo obrigatório ao bloquear.

**Permissões.** `noticias.moderar` protege a fila e as decisões de notícias;
`ong.analisar` protege a análise da ONG e o painel de publicações. As duas
capacidades pertencem a administrador e moderador. Toda ação é POST com CSRF e
revalidada no servidor.

**Logs.** A categoria Notícias registra `denuncia_recebida` (autoria Visitante,
`admin_id = NULL`, sem e-mail), `noticia_ocultada`, `noticia_restaurada`,
`denuncia_improcedente`, `publicacoes_bloqueadas` e `publicacoes_liberadas`.
Cada evento tem ícone circular de 36px e a atividade de cada moderador ganhou o
segmento Notícias.

**Limites.** Título 10–90, resumo 30–180, texto 100–8.000, até 3 links, 1–10
fotos, 5 MB e 24 megapixels por foto. A ONG publica até 3 notícias por dia;
só publica se estiver aprovada e sem bloqueio.

**Uploads.** As fotos são enviadas pelo próprio formulário do editor (sem API
separada) e cada uma vira um arquivo em `assets/img/uploads/noticias/`;
`.htaccess` bloqueia listagem/execução. Remover uma foto ou excluir a notícia
apaga os arquivos.

**Remover a demonstração.** `DELETE FROM noticias WHERE demonstracao = 1;`
(fotos e links saem por cascata). Contas fictícias de moderação saem com
`sql/remover_contas_teste.sql`.

**Fora do escopo.** Newsletter, envio automático de e-mail, edição de notícia
oculta pela ONG, exclusão definitiva de denúncias pela interface, notificações
em tempo real, moderação automática por IA, processamento/custódia de doações e
exclusão de moderadores.

---

## 6. Regras públicas

### 6.1 Filtros obrigatórios

Toda consulta pública deve usar:

```sql
status = 'aprovada'
```

Arquivos que usavam `ativo = 1` antes da migração (registro histórico do
plano; o código atual filtra `status = 'aprovada'`):

- `index.php` (linhas 15 e 37);
- `pages/ongs.php` (linhas 47, 138, 142, 143, 144);
- `pages/transparencia.php` (linhas 80, 203, 323);
- `pages/perfilpublico.php` (linha 21);
- `pages/login.php` (linhas 13 e 29);
- `pages/cadastro.php` (linha 12);
- `pages/exportar_csv.php` (linha 73);
- `pages/relatorio_anual.php` (linha 60);
- `pages/desativar.php` (consulta do usuário).

Fazer `grep -rn "ativo = 1"` e revisar todos os resultados.

### 6.2 Selo

- Apenas ONGs com `status = 'aprovada'` e `verificada_em` preenchido exibem selo.
- Texto: **“Verificada em dd/mm/aaaa”**.
- Em cards compactos: ícone com tooltip “Cadastro verificado em dd/mm/aaaa”.
- No perfil público: “Verificada em dd/mm/aaaa” e, abaixo, “Cadastro aprovado em dd/mm/aaaa”.
- Usar `<time datetime="AAAA-MM-DD">`.
- Se suspensa: sem selo e perfil indisponível.
- Se pendente/recusada: perfil indisponível.

### 6.3 Páginas

| Página | Regra |
|---|---|
| `index.php` | Estatísticas e destaques só de aprovadas |
| `ongs.php` | Listagem, filtros e destaque só de aprovadas |
| `transparencia.php` | Cards, filtros e modal só de aprovadas |
| `perfilpublico.php` | Só aprovada; caso contrário, 404 |
| `exportar_csv.php` | Só aprovada; caso contrário, 404 |
| `relatorio_anual.php` | Só aprovada; caso contrário, 404 |
| `login.php` | Status define a mensagem |
| `cadastro.php` | Contagem de ONGs aprovadas |
| `perfilprivado.php` | Sessão só de aprovada; revalidar status |
| `gestao.php` | Sessão só de aprovada; revalidar status |
| `desativar.php` | Só aprovada |

### 6.4 Avisos

- Portal de transparência: “Os valores são autodeclarados pela ONG. O ONGHelp não audita as movimentações.”
- Perfil público: aviso curto de dados autodeclarados.
- Selo: “Cadastro verificado” não significa auditoria financeira.
- README: explicar o significado e os limites do selo.

### 6.5 Página pública de voluntariado

`pages/equipe.php`:

- `noindex, nofollow`, sem entrada na navegação principal;
- link no rodapé com `rel="nofollow"`;
- explica o papel da moderação, o fluxo de primeiro acesso e as regras de conduta;
- CTA para `https://wa.me/5516997792777` com a mensagem exata
  `Olá! Quero fazer parte da equipe de moderação do ONGHelp. Nome: / Cidade:`;
- número exibido como `+55 16 99779-2777`;
- número e mensagem definidos apenas em `includes/config.php`;
- não é cadastro público: a conta só é criada pelo administrador.

---

## 7. Segurança

### 7.1 CSRF

Criar `includes/csrf.php`:

```php
csrfToken(): string
csrfField(): string
csrfValidar(): bool
```

Regras:

- token de 32 bytes em `$_SESSION['csrf_token']`;
- `hash_equals`;
- validar todo POST;
- falha retorna HTTP 403 e mensagem genérica;
- adicionar campo oculto em todos os formulários:
  - cadastro;
  - login;
  - perfilprivado (todos os formulários);
  - gestao (movimentações e projetos);
  - desativar;
  - admin login;
  - admin ações.

### 7.2 Rate limiting

Criar `includes/rate_limit.php`:

```php
registrarTentativa(string $escopo, string $email, string $ip, bool $sucesso): void
loginBloqueado(string $escopo, string $email, string $ip): bool
```

Regras:

- 5 falhas em 15 minutos por e-mail + IP;
- 20 falhas por hora por IP;
- admin e ONG em escopos separados;
- usar `$_SERVER['REMOTE_ADDR']`;
- limpar registros antigos oportunisticamente;
- mensagem: “Muitas tentativas. Tente novamente em alguns minutos.”

### 7.3 Sessão

Criar `includes/session.php`:

```php
iniciarSessao(): void
exigirLoginOng(): void
exigirLoginAdmin(): void
```

Regras:

- cookie `httponly`;
- `samesite=Lax`;
- `secure` quando HTTPS;
- `X-Forwarded-Proto: https` só é aceito quando o IP imediato está na lista
  `ONGHELP_PROXIES_CONFIAVEIS` (ou na variável de ambiente
  `ONGHELP_TRUSTED_PROXIES`), para funcionar atrás de proxy reverso sem
  permitir que qualquer cliente forge o cabeçalho;
- timeout por inatividade:
  - ONG: 30 minutos;
  - admin: 20 minutos;
- tempo absoluto:
  - ONG: 8 horas;
  - admin: 4 horas;
- regenerar ID a cada 15 minutos;
- destruir sessão ao expirar;
- redirecionar para login.

### 7.4 Headers

Criar `includes/security_headers.php` e incluir antes de qualquer saída:

```text
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: geolocation=(), microphone=(), camera=()
```

CSP inicial:

```text
default-src 'self';
style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com;
font-src https://fonts.gstatic.com https://cdn.jsdelivr.net;
script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net;
img-src 'self' data:;
```

Ajustar se algum recurso legítimo for bloqueado.

### 7.5 Admin e equipe

- login separado, individual, com e-mail e senha;
- sem cadastro público de administrador; a página de voluntariado é apenas contato;
- sem link de acesso administrativo no rodapé;
- rate limit por e-mail/IP e por IP, com escopo `admin`;
- CSRF em todos os POST, inclusive logout;
- sessão curta (20 minutos de inatividade, 4 horas absolutas);
- detecção de HTTPS com `X-Forwarded-Proto` aceito apenas de proxies confiáveis (`ONGHELP_PROXIES_CONFIAVEIS` ou `ONGHELP_TRUSTED_PROXIES`);
- hash fictício aleatório e descartável quando o e-mail não existe, para igualar o tempo de resposta sem reutilizar o hash de uma senha conhecida;
- senha forte: mínimo de 10 caracteres, diferente da temporária e da atual, sem o e-mail completo nem o trecho antes do `@`;
- senha temporária de 48 horas, guardada apenas como hash e exibida uma única vez;
- `sessao_token` revalidado em toda requisição; reset, troca, desativação e reativação invalidam sessões antigas;
- mensagem genérica para credencial inválida; estado só é revelado após senha correta;
- conta administradora protegida contra desativação e troca de papel;
- moderadores só acessam painel, análise, logs e a própria conta;
- toda negativa de permissão registra `acesso_negado`;
- logs com autoria individual;
- nunca exibir hash de senha;
- nunca permitir alteração de `status` por GET;
- logout somente POST + CSRF;
- página pública de recrutamento `noindex, nofollow`, com contato centralizado.

### 7.6 LGPD

- `responsavel_nome` e `responsavel_cargo`: privados, apenas admin.
- Finalidade: verificação de representação.
- Não coletar CPF/RG.
- Canal público é dado de contato escolhido pela ONG.
- Atualizar Política de Privacidade com:
  - dados coletados;
  - finalidade;
  - acesso restrito;
  - retenção;
  - direitos do titular.
- Atualizar Termos de Uso com:
  - análise manual;
  - aprovação/recusa/suspensão;
  - veracidade dos dados;
  - limites do selo;
  - dados financeiros autodeclarados.

---

## 8. Design

### 8.1 Princípios

- O painel administrativo deve parecer parte do ONGHelp.
- Não criar uma identidade visual nova.
- Usar Bootstrap 5.3 e os tokens do `global.css`.
- Simplicidade operacional: uma pessoa deve conseguir analisar um cadastro sem treinamento longo.
- Mobile funcional, mas desktop é o foco do painel.

### 8.2 Tokens existentes

```css
--glacier: #72a3bf;
--marine:  #446e87;
--harbor:  #1d4052;
--depth:   #030f18;
--frost:   #e0e8e6;

--cor-primaria:    var(--marine);
--cor-secundaria:  var(--glacier);
--cor-acento:      var(--harbor);
--cor-fundo:       var(--frost);
--cor-texto:       var(--depth);
--cor-texto-muted: var(--harbor);
--radius:          12px;
--shadow-card:     0 4px 24px rgba(3,15,24,.10);
```

Tipografia: Poppins.

Componentes existentes a reutilizar:

- `.btn-primary`, `.btn-outline-primary`, `.btn-acento`;
- `.badge-secao`;
- `.card`, `.rounded-4`, `.shadow-sm`;
- `.auth-layout`, `.auth-left`, `.auth-right`, `.auth-form-wrap`, `.auth-input`, `.auth-btn`;
- `.badge-verificado`;
- `.ong-card-*`.

### 8.3 Layout do painel

Classes novas em `global.css`:

```text
.admin-shell
.admin-sidebar
.admin-sidebar-brand
.admin-sidebar-nav
.admin-sidebar-link
.admin-main
.admin-topbar
.admin-content
.admin-stat
.admin-stat-icon
.admin-stat-num
.admin-stat-label
.admin-panel
.admin-panel-header
.admin-table
.status-badge
.status-pendente
.status-aprovada
.status-recusada
.status-suspensa
.checklist-block
.checklist-item
.admin-detail-grid
.admin-empty
```

Cores dos status:

| Status | Texto | Fundo |
|---|---|---|
| Pendente | `#8A5A00` | `#FFF3D6` |
| Aprovada | `#007A55` | `#D6F5EB` |
| Recusada | `#A93226` | `#FDE8E8` |
| Suspensa | `#4E5D72` | `#EDF1F6` |

### 8.4 Login admin

- Reutilizar `.auth-layout`.
- Lado esquerdo: “Área restrita”, “Equipe ONGHelp”, texto curto sobre análise de cadastros.
- Lado direito: formulário limpo.
- Botão principal: “Entrar no painel”.
- Link discreto “Voltar ao site”.

### 8.5 Acessibilidade

- Todo input com `<label>`.
- Ícones com `aria-hidden="true"` quando decorativos.
- Botões com texto ou `aria-label`.
- Checklist com checkbox nativo.
- Foco visível.
- Contraste adequado.
- Tabela com `<th scope="col">`.
- Sidebar navegável por teclado.
- Não usar `div` clicável como botão.

---

## 9. Implementação

### Lote A — Fundação e segurança ✅ Concluído

Objetivo: base segura, status, cadastro pendente e filtros públicos.

Arquivos:

- `includes/csrf.php` (novo);
- `includes/rate_limit.php` (novo);
- `includes/session.php` (novo);
- `includes/security_headers.php` (novo);
- `includes/db.php` (incluir headers/timezone, se necessário);
- `includes/helpers.php` (normalização de CNPJ/PIX, canal, status);
- `pages/cadastro.php`;
- `pages/login.php`;
- `includes/header.php` (sessão);
- `pages/perfilprivado.php` (guard e CSRF);
- `pages/gestao.php` (guard e CSRF);
- `pages/desativar.php` (CSRF e status);
- `index.php`;
- `pages/ongs.php`;
- `pages/transparencia.php`;
- `pages/perfilpublico.php`;
- `pages/exportar_csv.php`;
- `pages/relatorio_anual.php`;
- `onghelp.sql`;
- `sql/migracao_verificacao.sql` (novo).

Entregas:

- CSRF em todos os POST;
- rate limit;
- sessão com timeout;
- headers;
- status `pendente/aprovada/recusada/suspensa`;
- cadastro com responsável, canal e PIX CNPJ;
- cadastro não loga automaticamente;
- login com mensagens de status;
- filtros públicos;
- demo migrada para aprovada.

Teste ao final do lote:

- cadastro pendente não aparece;
- login pendente bloqueado;
- login aprovado funciona;
- CSRF bloqueia POST sem token;
- rate limit bloqueia após 5 falhas;
- nenhuma página pública lista pendente.

### Lote B — Painel administrativo ✅ Concluído

Objetivo: permitir análise, aprovação, recusa, suspensão e datas.

Arquivos:

- `admin/login.php` (novo);
- `admin/index.php` (novo);
- `admin/verificar.php` (novo);
- `admin/acao.php` (novo);
- `admin/logout.php` (novo);
- `includes/admin_auth.php` (novo);
- `includes/admin_header.php` (novo);
- `includes/admin_footer.php` (novo);
- `assets/css/global.css` (seção ADMIN);
- `assets/js/admin.js` (novo, se necessário);
- `includes/helpers.php` (badge com data);
- `pages/perfilpublico.php` (selo com data);
- `pages/transparencia.php` (selo com data);
- `pages/ongs.php` (tooltip/data);
- `README.md` (seção admin).

Entregas:

- login da Equipe ONGHelp;
- dashboard;
- fila;
- detalhe;
- checklist;
- aprovar/recusar/suspender/reativar;
- datas;
- logs;
- modelos de e-mail;
- selo com data.

Teste ao final do lote:

- aprovar torna público com data;
- recusar mantém oculto;
- suspender remove do público e preserva dados;
- checklist bloqueante impede aprovação;
- log registra ações;
- login admin não acessível sem sessão.

### Lote C — PIX, QR, textos e acabamento ✅ Concluído

Objetivo: fechar o modelo de doação e a documentação.

Arquivos:

- `pages/perfilprivado.php` (remover upload/remoção de QR; bloquear PIX);
- `includes/modal_doacao.php` (sempre gerar QR);
- `includes/helpers.php` (remover `validarChavePix` se não usada; manter `pixPayload`);
- `admin/acao.php` (alterar PIX/responsável);
- `pages/termos.php`;
- `pages/privacidade.php`;
- `README.md`;
- `onghelp.sql`;
- `sql/migracao_verificacao.sql`;
- remover `assets/img/qrcodes/qr_30.png`;
- remover coluna `qr_code`;
- remover `ativo` se ainda existir.

Entregas:

- PIX apenas CNPJ;
- PIX bloqueado após aprovação;
- sem upload de QR;
- QR gerado da chave;
- textos legais;
- README final;
- banco limpo.

Teste ao final do lote:

- QR gerado bate com a chave;
- não existe upload de QR;
- PIX não pode ser alterado pela ONG;
- admin consegue alterar com log;
- documentação coerente;
- importação do SQL limpa.

### Lote D — Equipe individual e primeiro acesso ✅ Concluído (v1.2)

Objetivo: separar a conta administradora compartilhada das pessoas
moderadoras, com autoria individual, estados de conta, primeiro acesso e
permissões declarativas.

Arquivos:

- `includes/config.php` (novo — WhatsApp e mensagem da equipe);
- `includes/session.php` (token e sessão mais estrita);
- `includes/admin_auth.php` (papéis, permissões, primeiro acesso, logs);
- `includes/admin_header.php` e `includes/admin_footer.php` (navegação por
  papel, pílula do usuário e logout por POST);
- `admin/login.php`, `admin/primeiro_acesso.php` (novo), `admin/logout.php`;
- `admin/equipe.php` (novo), `admin/minha_conta.php` (novo) e `admin/logs.php`;
- `admin/index.php`, `admin/verificar.php` e `admin/acao.php` (permissões);
- `pages/equipe.php` (novo — página pública não indexada);
- `includes/header.php` e `includes/footer.php` (robots e link no rodapé);
- `assets/js/admin-senha.js` e `assets/js/admin-equipe.js` (novos);
- `assets/css/global.css` (extensão da seção ADMIN + EQUIPE);
- `sql/migracao_equipe.sql` (novo) e `sql/onghelp.sql`.

Entregas:

- uma conta administradora compartilhada e contas individuais de moderação;
- estados `ativo`, `aguardando` e `desativado`;
- senha temporária de 48 horas, hash no banco e exibição única;
- permissões por papel revalidadas no servidor;
- invalidação de sessão por `sessao_token`;
- gestão de equipe, minha conta, logs filtrados e página de acesso negado;
- página pública de voluntariado com WhatsApp centralizado;
- migração idempotente com backup e preservação do histórico.

Teste ao final do lote: seção 10.8 e critérios das seções 16.11 e 16.13.

### Lote E — Notícias da ONG ✅ Concluído (v1.3)

Objetivo: dar à ONG uma área completa para criar, publicar, despublicar e
acompanhar suas notícias, com estados claros, bloqueios, moderação pela equipe
e documentação legal coerente.

Arquivos:

- `includes/noticias.php` (publicação de rascunho com revalidação);
- `pages/noticias.php` (reescrita: KPIs, tabela, estados, ações e estado vazio);
- `pages/gestao.php` (botão secundário “Publicar notícia”);
- `includes/header.php` (item “Minhas notícias” e versão do CSS);
- `assets/js/noticias-lista.js` (novo — confirmação de exclusão);
- `assets/css/global.css` (bloco `.nmg-*` e ações do cabeçalho de Gestão);
- `pages/termos.php` e `pages/privacidade.php` (conteúdo, imagem, crianças,
  PIX/transferência, remoção e denúncias);
- `README.md` (como publicar, limites, uploads e remoção da demonstração);
- `docs/PLANO_VERIFICACAO.md` (este lote, testes e escopo de denúncias).

Entregas:

- KPIs de publicadas, rascunhos e ocultas pela equipe;
- tabela em painel de vidro com linhas de 80px, miniatura 72×54, contagem de
  fotos, título, tipo, estado, data e fotos no formato “N/10”;
- menu “⋯” com Ver, Editar, Publicar/Despublicar e Excluir com confirmação;
- publicação revalida título, resumo, texto, vínculos, fotos, direitos de
  imagem, limite diário e anti-PIX antes de mudar o estado;
- notícia oculta exibe nota vermelha com data, motivo e “Fale conosco”, sem
  edição nem exclusão;
- ONG não aprovada ou bloqueada vê o motivo no topo e os botões de publicar
  desabilitados;
- estado vazio com ícone de jornal, texto e botão de publicação;
- layout mobile em duas camadas;
- Termos, Privacidade, README e este plano atualizados.

Teste ao final do lote: seção 10.9 e critérios do Lote E na seção 11.

### Lote F — Moderação e bloqueio de notícias ✅ Concluído (v1.4)

Objetivo: fechar o ciclo público das notícias com denúncia, moderação,
ocultação, restauração, bloqueio de publicações e rastreabilidade completa.

Arquivos:

- `admin/noticias.php` (novo — fila de denúncias, publicações, ocultas e drawer);
- `admin/verificar.php` (painel de publicações e ações de bloqueio/liberação);
- `admin/logs.php` (categoria Notícias, filtro e ícone por evento);
- `admin/index.php` (aviso de denúncias pendentes acima da fila);
- `admin/equipe.php` (segmento Notícias na atividade);
- `includes/admin_auth.php` (novos eventos, resumos e segmento);
- `pages/denunciar-noticia.php` (log de denúncia com autoria Visitante);
- `pages/noticias.php` e `pages/noticia.php` (mensagem de ocultação e denúncia públicas);
- `assets/js/admin-noticias.js` (novo — drawer, galeria e contadores);
- `assets/css/global.css` (bloco `.anm-*`, aviso, painel e cores de log);
- `README.md` e `docs/PLANO_VERIFICACAO.md` (documentação final).

Entregas:

- denúncia pública com motivo, detalhe e e-mail opcional, sem expor o e-mail à
  ONG e sem gravar o e-mail no log;
- fila agrupada por notícia, com estados Pendente/Procedente/Improcedente;
- KPIs de denúncias pendentes, publicadas nos últimos 7 dias, ocultas e ONGs
  bloqueadas;
- abas Denúncias/Publicações/Ocultas, filtros por título/ONG e motivo/tipo;
- drawer de 720px com Conteúdo/Denúncias/Histórico e ações fixas;
- ocultar com categoria e mensagem mínima de 20 caracteres; ocultar e bloquear
  com confirmação explícita e motivo; restaurar preservando evidências;
- bloqueio e liberação de publicações na análise da ONG, com modal e log;
- nova denúncia em notícia já tratada reabre o caso;
- eventos de notícias no filtro de logs, ícone de 36px por evento e autoria
  individual; “Ver atividade” lista as ações no segmento Notícias;
- aviso de vidro na visão geral apenas quando há denúncias pendentes.

Teste ao final do lote: seção 10.10 e critérios do Lote F na seção 11.

---

## 10. Testes

### 10.1 Segurança

- [ ] POST sem CSRF retorna 403.
- [ ] POST com CSRF válido funciona.
- [ ] 5 logins errados bloqueiam por 15 minutos.
- [ ] Login correto após bloqueio continua bloqueado até a janela expirar.
- [ ] Sessão expira por inatividade.
- [ ] Sessão admin expira mais rápido.
- [ ] Cookie é `httponly` e `samesite=Lax`.
- [ ] Headers aparecem na resposta.
- [ ] Admin sem sessão é redirecionado.
- [ ] ONG suspensa perde acesso.

### 10.2 Cadastro

- [ ] Cadastro válido cria `pendente`.
- [ ] Cadastro não cria sessão.
- [ ] Cadastro não aparece em nenhuma página pública.
- [ ] E-mail duplicado é recusado.
- [ ] CNPJ duplicado é recusado.
- [ ] CNPJ com menos de 14 dígitos é recusado.
- [ ] PIX diferente do CNPJ é recusado.
- [ ] Canal público ausente é recusado.
- [ ] Responsável ausente é recusado.
- [ ] Declaração de autorização ausente é recusada.
- [ ] Senha curta é recusada.
- [ ] Termos não aceitos são recusados.

### 10.3 Admin

- [ ] Login admin funciona.
- [ ] Login admin errado é genérico.
- [ ] Fila mostra pendentes.
- [ ] Detalhe mostra CNPJ, responsável, canal e PIX.
- [ ] Aprovar sem bloqueantes é impedido.
- [ ] Aprovar com bloqueantes grava datas e admin.
- [ ] Aprovar torna público.
- [ ] Recusar grava motivo e mantém oculto.
- [ ] Suspender remove do público.
- [ ] Reativar volta a público.
- [ ] Log registra cada ação.
- [ ] E-mail de aprovação não contém senha.
- [ ] Marcar e-mail enviado grava data.

### 10.4 Público

- [ ] Home conta só aprovadas.
- [ ] `ongs.php` lista só aprovadas.
- [ ] Filtros de `ongs.php` só consideram aprovadas.
- [ ] `transparencia.php` só mostra aprovadas.
- [ ] `perfilpublico.php` de pendente retorna 404.
- [ ] `perfilpublico.php` de suspensa retorna 404.
- [ ] CSV de pendente retorna 404.
- [ ] Relatório de pendente retorna 404.
- [ ] Selo mostra data correta.
- [ ] Selo não aparece em suspensa.
- [ ] Aviso de dados autodeclarados aparece.

### 10.5 PIX e QR

- [ ] QR é gerado a partir da chave.
- [ ] Não existe upload de QR.
- [ ] `qr_code` não existe no banco.
- [ ] `qr_30.png` não existe no disco.
- [ ] PIX não é editável pela ONG após aprovação.
- [ ] Admin altera PIX com log e nova verificação.
- [ ] Modal de doação não quebra sem QR oficial.

### 10.6 Regressão

- [ ] Login demo `contato@institutoeducar.org` / `senha123` funciona.
- [ ] 8 ONGs aprovadas aparecem.
- [ ] 147 movimentações continuam.
- [ ] 16 projetos continuam.
- [ ] PDF anual continua gerando.
- [ ] CSV continua exportando.
- [ ] Gestão de movimentações continua.
- [ ] Gestão de projetos continua.
- [ ] Edição de perfil continua.
- [ ] Troca de senha continua.
- [ ] Tema continua.
- [ ] Logout continua.

### 10.7 SQL

- [ ] `onghelp.sql` importa em banco vazio.
- [ ] `sql/migracao_verificacao.sql` importa em banco existente.
- [ ] Não há erro de FK.
- [ ] `status` default é `pendente`.
- [ ] Demo está `aprovada`.
- [ ] `emoji`, `qr_code` e `ativo` não existem no dump final.
- [ ] `sql/migracao_equipe.sql` importa em banco legado, preserva a linha id 1 e não duplica contas quando executado duas vezes.
- [ ] O dump final tem 1 linha em `admins`: a conta administradora. As 3 contas de teste são criadas por `sql/contas_teste.sql`.
- [ ] O dump final não tem a coluna `admins.ativo`, mas tem `papel`, `estado`, `senha_temporaria_expira_em`, `sessao_token`, `ultimo_acesso_em`, `criado_por` e `conta_teste`.

### 10.8 Equipe e primeiro acesso

- [ ] Administrador entra com `equipe.onghelp@gmail.com` / `EquipeONGHelp@2026`.
- [ ] Depois de executar `sql/contas_teste.sql`, o moderador de teste entra com `pipoca@moderacao.onghelp.test` / `Moderador@2026`.
- [ ] Login errado devolve mensagem genérica.
- [ ] Conta `desativado` só informa o estado depois da senha correta.
- [ ] Conta `aguardando` com senha temporária válida vai para `primeiro_acesso.php`.
- [ ] Conta `aguardando` com senha expirada recebe a mensagem de expiração.
- [ ] Primeiro acesso rejeita senha igual à temporária.
- [ ] Primeiro acesso rejeita senha com menos de 10 caracteres.
- [ ] Primeiro acesso rejeita senha que contém o e-mail.
- [ ] Primeiro acesso exige confirmação igual e aceite das regras.
- [ ] Primeiro acesso concluído grava `ativo`, limpa a validade, rotaciona o token e registra `primeiro_acesso_concluido`.
- [ ] Administrador cria moderador e vê a senha temporária uma única vez.
- [ ] Recarregar a página depois de fechar o modal não mostra a senha.
- [ ] Redefinir senha invalida a sessão antiga daquele moderador.
- [ ] Desativar invalida a sessão imediatamente e bloqueia novo login.
- [ ] Reativar gera nova senha temporária e volta para `aguardando`.
- [ ] Moderador recebe 403 em `admin/equipe.php` e o evento `acesso_negado` aparece nos logs.
- [ ] Moderador não vê o item “Equipe” no menu.
- [ ] Administrador não consegue desativar a própria conta nem a conta principal.
- [ ] Troca de senha em Minha conta exige a senha atual e derruba as demais sessões.
- [ ] Logout por GET não encerra a sessão; logout por POST + CSRF encerra.
- [ ] Página pública responde com `noindex, nofollow`, mostra `+55 16 99779-2777` e o link contém a mensagem exata.
- [ ] A página pública não aparece no menu principal e o link do rodapé tem `rel="nofollow"`.

### 10.9 Notícias da ONG

- [ ] ONG aprovada vê o item “Minhas notícias” no menu da conta.
- [ ] `gestao.php` mostra o botão secundário “Publicar notícia” no cabeçalho.
- [ ] “Minhas notícias” mostra KPIs de publicadas, rascunhos e ocultas pela equipe.
- [ ] Cada linha tem miniatura 72×54, contagem de fotos, título, tipo, estado, data e fotos no formato “N/10”.
- [ ] O menu “⋯” mostra Ver, Editar, Publicar/Despublicar e Excluir conforme o estado.
- [ ] Publicar revalida conteúdo, fotos, direitos de imagem, limite diário e anti-PIX; rascunho inválido não publica.
- [ ] Despublicar volta para rascunho e preserva `publicada_em`.
- [ ] Excluir pede confirmação, remove a notícia e apaga as fotos.
- [ ] Notícia oculta mostra a nota vermelha com data, motivo e “Fale conosco”; não oferece Editar nem Excluir.
- [ ] POST direto para editar ou excluir notícia oculta é recusado.
- [ ] ONG sem notícias vê o estado vazio com ícone de jornal 48px e botão “+ Publicar notícia”.
- [ ] ONG pendente, recusada, suspensa ou com bloqueio ativo vê a faixa com o motivo e os botões de publicar desabilitados.
- [ ] No mobile, as linhas da tabela aparecem em duas camadas.
- [ ] Termos e Privacidade citam responsabilidade pelo conteúdo, direitos de imagem, crianças, proibição de PIX/transferência, remoção pela equipe e denúncias.
- [ ] README e este plano descrevem publicação, limites, estrutura de uploads e remoção da demonstração.

### 10.10 Notícias: denúncia e moderação

- [ ] ONG aprovada publica uma notícia com 3 fotos.
- [ ] A notícia aparece na home, no hub público de notícias e no perfil da ONG.
- [ ] Visitante abre a notícia e envia denúncia com motivo e detalhe.
- [ ] A denúncia entra como pendente e o log registra autoria Visitante sem e-mail.
- [ ] O aviso “N denúncias de notícias aguardam análise” aparece na visão geral e leva a `noticias.php`.
- [ ] Moderador abre o drawer, vê Conteúdo/Denúncias/Histórico e oculta com categoria e mensagem de 20+ caracteres.
- [ ] A notícia some das páginas públicas e das listas públicas.
- [ ] A ONG vê a mensagem da equipe em Minhas notícias e não consegue editar a notícia oculta.
- [ ] A notícia oculta mantém texto, fotos e denúncias no banco.
- [ ] Administrador restaura a notícia; ela volta ao público e o histórico permanece.
- [ ] Bloquear publicações pela análise da ONG grava `noticias_bloqueios` e log.
- [ ] A ONG bloqueada vê o motivo e o POST de publicação é recusado pelo servidor.
- [ ] Liberar publicações remove o bloqueio ativo e registra `publicacoes_liberadas`.
- [ ] Nova denúncia em notícia já tratada volta o caso para pendente.
- [ ] Filtro de logs inclui Notícias e “Ver atividade” lista as ações no segmento Notícias.
- [ ] POST de moderação sem CSRF retorna 403; acesso sem login redireciona ao login.
- [ ] Home, login da ONG, perfil, transparência, gestão e painel admin continuam funcionando.

---

## 11. Critérios de aceite

### Lote A

- ✅ Segurança básica ativa.
- ✅ Cadastro pendente invisível.
- ✅ Login com status.
- ✅ Nenhuma query pública usando `ativo = 1`.
- ✅ Demo aprovada.

### Lote B

- ✅ Painel admin funcional.
- ✅ Checklist obrigatória.
- ✅ Aprovação grava datas.
- ✅ Selo com data.
- ✅ Logs.
- ✅ Suspensão sem apagar histórico.

### Lote C

- ✅ PIX CNPJ.
- ✅ Sem upload de QR.
- ✅ QR gerado.
- ✅ Textos e README atualizados.
- ✅ Banco limpo.
- ✅ Regressão completa.

### Lote D — equipe individual (v1.2)

- ✅ Uma única conta administradora compartilhada e contas individuais de
  moderação.
- ✅ Papéis e permissões declarados no servidor e revalidados em cada
  requisição.
- ✅ Estados `ativo`, `aguardando` e `desativado`, sem exclusão de moderadores.
- ✅ Senha temporária de 48 horas, apenas como hash e exibida uma única vez.
- ✅ Primeiro acesso obrigatório com política de senha e aceite das regras.
- ✅ Sessões invalidadas em reset, troca de senha, desativação e reativação.
- ✅ Conta administradora protegida contra desativação e troca de papel.
- ✅ Logs com autoria individual, filtros, contagem e linha do tempo.
- ✅ Página pública de voluntariado `noindex, nofollow`, com WhatsApp
  centralizado em `includes/config.php`.
- ✅ Migração idempotente, backup documentado e dump importado em banco de
  teste.

### Lote E — notícias da ONG (v1.3)

- ✅ Lista “Minhas notícias” com KPIs, tabela de 80px e menu de ações.
- ✅ Publicação revalida o rascunho e respeita bloqueios e limite diário.
- ✅ Despublicar, excluir e confirmar exclusão funcionam com CSRF.
- ✅ Notícia oculta fica somente para leitura e mostra data, motivo e contato.
- ✅ ONG bloqueada vê o motivo e não consegue publicar.
- ✅ Estado vazio e layout mobile em duas camadas.
- ✅ Termos, Privacidade, README e este plano atualizados.
- ✅ Denúncias de notícias retiradas da lista de itens fora do escopo.

### Lote F — moderação de notícias (v1.4)

- ✅ Denúncia pública com motivo, detalhe e e-mail opcional; e-mail nunca exibido à ONG.
- ✅ Fila de denúncias agrupada, KPIs, abas, filtros e drawer de análise.
- ✅ Ocultar com categoria/mensagem, bloquear com confirmação/motivo e restaurar.
- ✅ Conteúdo, fotos e denúncias preservados como evidência.
- ✅ Bloqueio e liberação de publicações na análise da ONG, com motivo e log.
- ✅ Nova denúncia em notícia tratada reabre o caso.
- ✅ Categoria Notícias no filtro de logs, ícone por evento e autoria Visitante.
- ✅ “Ver atividade” inclui o segmento Notícias.
- ✅ Aviso de pendências na visão geral, visível apenas quando há denúncias.
- ✅ README e este plano atualizados com fluxo, permissões, limites, uploads,
  limpeza de órfãos, remoção da demonstração e itens fora do escopo.
- ✅ Regressão de ponta a ponta executada em 10/11/2026.

---

## 12. Riscos e mitigação

| Risco | Impacto | Mitigação |
|---|---|---|
| Esquecer uma query pública | Perfil pendente vaza | `grep -rn "ativo = 1"` e revisão manual; hoje todas usam `status = 'aprovada'` |
| Migração sem backup | Perda de dados | Backup + teste em banco temporário |
| Admin sobre base insegura | Comprometimento total | Lote A antes do Lote B |
| Conta administradora compartilhada | Sem autoria individual nas ações do administrador | Aceito; o log registra a conta administradora. Moderadores têm autoria própria e o histórico antigo permanece como “Equipe ONGHelp” |
| Senha temporária exposta | Acesso indevido à conta | Hash no banco, exibição única, validade de 48 horas, troca obrigatória e invalidação por `sessao_token` |
| Sessão antiga após reset ou desativação | Acesso residual | Token rotacionado e estado revalidado no banco em toda requisição |
| Moderador acessa área restrita | Gestão indevida | Permissões por papel, chamada obrigatória no servidor, menu condicional e evento `acesso_negado` |
| Número de WhatsApp divergente | Contato errado ou perda de voluntário | Número e mensagem definidos apenas em `includes/config.php` |
| Contas de teste em produção | Acesso público indevido | Ficam fora do dump e da migração, em `sql/contas_teste.sql`; remoção por `sql/remover_contas_teste.sql` antes da implantação, conforme a seção 16.10 |
| `X-Forwarded-Proto` forjado | Detecção errada de HTTPS e cookie `Secure` em HTTP | Cabeçalho só é aceito quando `REMOTE_ADDR` está na lista de proxies confiáveis |
| E-mail manual esquecido | ONG não sabe da aprovação | Botão “marcar e-mail enviado” + log |
| PIX de terceiro | Golpe | Chave CNPJ + conferência no app do banco |
| CNPJ real usado por impostor | Impersonação | Contato oficial independente + responsável legal |
| QR malicioso | Desvio de doação | Upload removido; QR gerado da chave |
| Dados demo inválidos | Confusão na defesa | README explicando que são fictícios e pré-aprovados |
| Falta de Git | Irreversibilidade | `git init` + commit inicial + backup |

---

## 13. Decisões tomadas e pontos em aberto

### Resolvidos durante a implementação

| Tema | Decisão |
|---|---|
| Autoexclusão da ONG | Hard delete mantido, com confirmação explícita e `CASCADE`. A anonimização ficou no roadmap por preservar o histórico público, mas não foi implementada agora |
| Exibição do canal público | Exibido no perfil público, com link montado por `canalUrl()` |
| Exibição do responsável legal | Privado; visível apenas em `admin/verificar.php` |
| Admin editar dados da ONG | Pode corrigir PIX e responsável legal; nome, descrição, área e demais dados continuam sob responsabilidade da ONG |
| Denúncias de notícias | Recebidas pelos canais oficiais e analisadas pela equipe; a notícia pode ser ocultada com data e motivo. Não há fluxo público automatizado nesta fase |
| Revalidação periódica | Fora deste escopo; o selo mostra a data da última verificação, sem validade automática |
| Prazo de análise | Não foi fixado um prazo no cadastro; a mensagem diz apenas que o cadastro passa por análise manual |
| Recuperação de senha | Fora deste escopo; moderadores recebem nova senha temporária do administrador. A conta administradora ainda não tem recuperação autônoma |
| Dígitos verificadores do CNPJ | Mantida apenas a validação de 14 dígitos + conferência manual, porque a checagem matemática quebraria os CNPJs demo |
| Conta administradora compartilhada | Mantida como única conta `administrador`; não pode ser desativada, excluída nem trocar de papel. Ações dela aparecem como “Equipe ONGHelp” |
| Moderadores individuais | Contas próprias, criadas pelo administrador, com autoria nos logs e permissões limitadas a painel, análise, logs e própria conta |
| Estados de conta | Apenas `ativo`, `aguardando` e `desativado`; não há exclusão de moderador pela interface |
| Primeiro acesso | Senha temporária de 48 horas, hash no banco, exibição única e troca obrigatória antes de liberar o painel |
| Invalidação de sessão | `sessao_token` rotacionado em reset, troca de senha, desativação e reativação; estado revalidado em cada requisição |
| Contas fictícias de teste | Três moderadores com `conta_teste = 1` e senha pública de demonstração, em `sql/contas_teste.sql` (fora do dump e da migração); remoção por `sql/remover_contas_teste.sql` antes de produção |
| Hash fictício do login | Gerado aleatoriamente a cada tentativa de e-mail inexistente; nunca reutiliza o hash de uma senha conhecida |
| Detecção de HTTPS | `requisicaoSegura()` aceita `X-Forwarded-Proto` somente de proxies configurados em `ONGHELP_PROXIES_CONFIAVEIS` ou `ONGHELP_TRUSTED_PROXIES` |
| Página pública de voluntariado | `pages/equipe.php`, `noindex, nofollow`, sem entrada no menu principal, com link `nofollow` no rodapé e WhatsApp centralizado |

### Ainda em aberto

1. **Prazo de análise:** definir o texto final (“até 2 dias úteis” ou outro) e
   exibi-lo no cadastro e no e-mail de confirmação.
2. **Recuperação de senha:** decidir se entra no escopo, com token expirável,
   especialmente para a conta administradora; hoje moderadores dependem do
   administrador e a conta principal não tem recuperação autônoma.
3. **Validação matemática de CNPJ:** decidir se reativa os dígitos verificadores
   e como tratar os dados demo.
4. **Notificações automáticas:** hoje o e-mail de aprovação é manual; decidir se
   o envio passa a ser automático no futuro.
5. **Revalidação periódica do selo:** decidir se a verificação tem validade e
   quando precisa ser refeita.
6. **Reaprovação no painel:** separar visualmente “reverificar” de “aprovar”
   para evitar aprovação duplicada de uma ONG já aprovada.

---

## 14. Prompt de handoff para a nova sessão (histórico)

> Este prompt foi usado para executar a implementação e fica registrado como
> referência. Não é necessário reexecutá-lo.

Copiar e colar no início da sessão de execução:

```text
Implemente o ONGHelp seguindo o documento docs/PLANO_VERIFICACAO.md.

Regras:
- Leia o documento inteiro antes de alterar código.
- Implemente em 3 lotes: A (segurança + status + cadastro pendente + filtros públicos), B (painel administrativo + checklist + datas + logs), C (PIX CNPJ + remoção do QR + textos + regressão).
- Faça backup/Git antes de começar.
- Não invente decisões: o que estiver em “Pontos em aberto”, pergunte.
- Use o design existente (Bootstrap 5.3, Poppins, variáveis do global.css).
- Toda query pública deve filtrar status = 'aprovada'.
- Teste ao final de cada lote.
- Não implemente envio automático de e-mail.
```

> **Atualização v1.3:** a última linha do prompt histórico foi superada. A
> versão 1.2 implementa a hierarquia administrador/moderador, contas individuais,
> primeiro acesso e a página de voluntariado descritas na seção 16. A versão 1.3
> acrescenta a área de notícias e as denúncias de notícias analisadas pela
> equipe. O restante do prompt permanece como registro do escopo original.

---

## 15. Resumo executivo

O ONGHelp deixa de aceitar qualquer perfil automaticamente e passa a ter:

1. cadastro pendente;
2. análise manual pela Equipe ONGHelp;
3. checklist de verificação;
4. aprovação, recusa e suspensão;
5. selo com data;
6. PIX vinculado ao CNPJ;
7. QR gerado da chave;
8. segurança mínima;
9. logs administrativos;
10. documentação coerente;
11. uma conta administradora compartilhada e moderadores individuais;
12. primeiro acesso com senha temporária de 48 horas e política de senha;
13. permissões declarativas, invalidação de sessão e auditoria com autoria.

A implementação foi feita em três lotes, com teste entre eles: Lote A
(segurança, status, cadastro pendente e filtros públicos), Lote B (painel
administrativo, checklist, datas e logs) e Lote C (PIX CNPJ, remoção do QR,
textos e regressão). A versão 1.2 acrescentou o Lote D (equipe individual,
primeiro acesso, permissões, gestão da equipe e página pública de
voluntariado). O estado final está descrito nas seções 1.3 e 16, os testes
executados na seção 10 e os critérios de aceite na seção 11.

---

## 16. Atualização v1.2 — equipe individual

### 16.1 Motivação e resultado

No modelo anterior, uma única conta compartilhada fazia todas as ações e o log
não permitia saber quem havia aprovado, recusado ou suspendido uma ONG. A
versão 1.2 mantém a conta **Equipe ONGHelp** como administradora única, mas
acrescenta contas individuais de moderação, autoria nos logs, estados de conta,
primeiro acesso e permissões declarativas.

O resultado é:

- uma conta administradora compartilhada, que nunca é desativada nem troca de
  papel;
- moderadores individuais, criados pelo administrador, com nome e e-mail
  próprios;
- autoria individual em cada ação nova, mantendo o histórico antigo como
  **Equipe ONGHelp**;
- acesso mínimo por papel, revalidado no servidor em toda página e ação;
- possibilidade de acrescentar permissões futuras sem duplicar regras;
- primeiro acesso obrigatório com senha temporária de 48 horas;
- invalidação imediata de sessões quando a senha ou o estado mudam.

### 16.2 Papéis e permissões

As permissões são declaradas em `permissoesPorPapel()` e verificadas por
`exigirPermissao()` no início de cada página e ação:

| Permissão | Administrador | Moderador |
|---|:---:|:---:|
| `painel.acessar` | ✅ | ✅ |
| `ong.analisar` | ✅ | ✅ |
| `logs.ver` | ✅ | ✅ |
| `equipe.gerenciar` | ✅ | — |
| `conta.propria` | ✅ | ✅ |

Regras:

- existe exatamente uma conta com `papel = 'administrador'`;
- o administrador gerencia a equipe; o moderador não vê a página Equipe nem
  executa nenhuma ação de conta;
- toda negativa renderiza a página de acesso negado dentro do layout do painel,
  responde HTTP 403 e registra `acesso_negado`;
- o menu esconde itens sem permissão, mas a proteção real é sempre a chamada no
  servidor;
- para uma capacidade futura, como notícias, basta acrescentar a chave em
  `permissoesPorPapel()` e usá-la com `exigirPermissao($pdo, 'noticias.moderar')`.

### 16.3 Estados e invariantes de conta

Estados possíveis:

| Estado | Significado | Acesso |
|---|---|---|
| `ativo` | senha pessoal já definida | painel conforme o papel |
| `aguardando` | senha temporária ainda válida | somente `primeiro_acesso.php` |
| `desativado` | acesso revogado | nenhum; login bloqueado |

Invariantes aplicadas na interface e no servidor:

- a conta administradora não pode ser desativada, excluída nem trocar de papel;
- o administrador não consegue desativar a própria conta;
- moderadores não são excluídos pela interface: são desativados e reativados;
- reativar gera uma nova senha temporária e devolve a conta para `aguardando`;
- o e-mail é único e o login é sempre individual;
- o hash da senha nunca é exibido; a senha temporária em texto puro aparece uma
  única vez, no modal de credenciais, e é descartada ao fechar.

### 16.4 Esquema e migração

A tabela `admins` recebeu:

- `papel` (`administrador`/`moderador`);
- `estado` (`ativo`/`aguardando`/`desativado`);
- `senha_temporaria_expira_em`;
- `sessao_token`;
- `ultimo_acesso_em`;
- `criado_por`;
- `conta_teste`.

A migração `sql/migracao_equipe.sql`:

1. exige backup prévio do banco;
2. adiciona as colunas com checagem em `information_schema`, sem depender de
   `ADD COLUMN IF NOT EXISTS`, para funcionar em MySQL 8 e MariaDB;
3. converte a antiga coluna `ativo` em `estado`;
4. normaliza o papel da conta existente para `administrador`;
5. garante a conta **Equipe ONGHelp** sem duplicar a linha id 1 nem sobrescrever
   nome, e-mail, hash ou histórico;
6. remove a coluna `ativo` ao final.

As três contas fictícias de moderação **não** são criadas pela migração. Elas
ficam em `sql/contas_teste.sql`, que é opcional e idempotente, e são removidas
por `sql/remover_contas_teste.sql`. O dump `sql/onghelp.sql` cria apenas a
conta administradora (id 1, `AUTO_INCREMENT = 2`). A migração foi executada no
banco vivo legado e duas vezes em banco temporário para comprovar a
idempotência; a linha id 1 e os logs anteriores foram preservados.

### 16.5 Primeiro acesso e senha temporária

Fluxo:

1. O administrador cria a conta em `admin/equipe.php` com nome e e-mail únicos.
2. O servidor gera uma senha aleatória, grava apenas o hash bcrypt e define
   `estado = 'aguardando'` e validade de 48 horas.
3. A senha temporária é mostrada uma única vez no modal; recarregar a página
   depois de fechar não a exibe novamente.
4. A pessoa entra em `admin/login.php` com a senha temporária.
5. Com a senha correta e a conta `aguardando`, o login cria uma sessão pendente e
   redireciona para `admin/primeiro_acesso.php`.
6. A tela mostra a validade, as regras de conduta e um checklist ao vivo.
7. O servidor revalida no envio:
   - mínimo de 10 caracteres;
   - senha diferente da temporária (`password_verify` no hash antigo);
   - senha sem o e-mail e sem a parte local do e-mail;
   - confirmação igual;
   - aceite obrigatório das regras.
8. A nova senha substitui o hash, `estado` vira `ativo`,
   `senha_temporaria_expira_em` vira `NULL`, `sessao_token` é rotacionado e
   `ultimo_acesso_em` é gravado.
9. O evento `primeiro_acesso_concluido` é registrado e o painel é liberado
   conforme o papel.

A validade de 48 horas é checada no login e novamente em
`exigirPrimeiroAcesso()`. Se expirar, a pessoa precisa de uma nova senha gerada
pelo administrador. O estado `desativado` e a senha expirada só são revelados
**depois** de a senha conferir; caso contrário, a resposta é genérica.

O checklist ao vivo usa Web Crypto para comparar a candidata com um digest
SHA-256 temporário, salgado e guardado na sessão, sem colocar a senha
temporária em texto puro no HTML. A decisão final continua sendo do servidor,
que usa `password_verify` contra o hash real.

### 16.6 Sessão, invalidação e bloqueio

- sessão admin com 20 minutos de inatividade e 4 horas absolutas;
- `session_regenerate_id(true)` no login;
- `sessao_token` comparado com o banco em toda requisição;
- reset de senha, troca de senha, desativação e reativação rotacionam o token;
- desativação invalida a sessão na requisição seguinte;
- estado e papel são relidos do banco, não confiados apenas à sessão;
- cookie `HttpOnly`, `SameSite=Lax` e `Secure` quando a conexão é HTTPS;
- CSRF em todos os POST, inclusive logout;
- logout somente por POST + CSRF; GET nunca encerra a sessão;
- rate limit por e-mail/IP e por IP, com escopo `admin`;
- mensagens genéricas para credencial inválida;
- sem redirecionamentos externos abertos;
- headers de segurança existentes aplicados ao painel;
- toda negativa de permissão grava `acesso_negado` com autor, alvo, IP e data.

### 16.7 Interface administrativa

- **Login:** layout de autenticação existente, texto “Acesso da equipe”, marca
  “Equipe ONGHelp”, campos de e-mail e senha e avisos de saída, sessão expirada
  e senha temporária expirada.
- **Primeiro acesso:** mesmo layout, checklist ao vivo, regras de conduta e
  confirmação; a validade de 48 horas é verificada no login.
- **Shell do painel:** sidebar por papel, topbar com pílula do usuário (avatar,
  nome, papel e e-mail), link para Minha conta e logout por POST.
- **Equipe:** KPIs de moderação, painel da conta administradora, tabela de
  moderadores, criação, reset, desativação, reativação, modal de credenciais
  única vez e atividade por pessoa em offcanvas.
- **Minha conta:** dados da conta, troca de senha com senha atual e atividade
  recente para moderadores.
- **Logs:** filtros por categoria, pessoa e intervalo de datas; contador e
  carregamento incremental de 25 em 25 preservando filtros; agrupamento por dia
  e linha do tempo.
- **Acesso negado:** página dentro do layout, sem vazar dados, com HTTP 403 e
  registro na trilha.
- **Responsividade:** tabelas e modais adaptados ao mobile, reutilizando os
  tokens, cards, botões e tipografia de `global.css`.

### 16.8 Página pública de voluntariado

`pages/equipe.php`:

- é `noindex, nofollow` e não aparece na navegação principal;
- o link do rodapé usa `rel="nofollow"`;
- explica o papel da moderação, o fluxo de primeiro acesso e as regras de
  conduta;
- não é cadastro público: a conta só é criada pelo administrador;
- usa o CTA `https://wa.me/5516997792777` com a mensagem exata
  `Olá! Quero fazer parte da equipe de moderação do ONGHelp. Nome: / Cidade:`;
- exibe o número como `+55 16 99779-2777`;
- mantém número e mensagem em `includes/config.php`, fonte única para a página e
  futuros pontos de contato.

### 16.9 Dados de teste

O dump principal e o banco de produção têm **uma única conta**:

| ID | Nome | E-mail | Papel | Estado | `conta_teste` |
|---|---|---|---|---|---|
| 1 | Equipe ONGHelp | `equipe.onghelp@gmail.com` | administrador | ativo | 0 |

As três contas fictícias de moderação são **opcionais e externas** ao dump e à
migração:

| Nome | E-mail | Papel | Estado | `conta_teste` |
|---|---|---|---|---|
| Sir Pipoca | `pipoca@moderacao.onghelp.test` | moderador | ativo | 1 |
| Dona Tapioca | `tapioca@moderacao.onghelp.test` | moderador | ativo | 1 |
| Capitão Cuscuz | `cuscuz@moderacao.onghelp.test` | moderador | ativo | 1 |

Elas são criadas por `sql/contas_teste.sql`, compartilham a senha de
demonstração `Moderador@2026` (guardada apenas como hash) e existem para
demonstrar autoria individual, primeiro acesso e limites de permissão. A senha
pública torna obrigatória a remoção com `sql/remover_contas_teste.sql` antes de
qualquer implantação real.

### 16.10 Operação e remoção dos dados fictícios

Antes de produção:

1. fazer backup do banco e dos arquivos;
2. trocar a senha da conta administradora em **Minha conta**;
3. remover as contas de teste e seus logs fictícios com
   `sql/remover_contas_teste.sql`, exceção documentada à regra de nunca excluir
   moderadores;
4. trocar as senhas das ONGs demo ou remover os dados de demonstração;
5. confirmar que `conta_teste = 0` em todas as contas restantes;
6. revisar os logs e a conta administradora;
7. manter o backup fora da raiz pública.

Comandos de remoção segura:

```bash
mysqldump -u root onghelp > backup_onghelp_antes_da_limpeza.sql
mysql -u root onghelp < sql/remover_contas_teste.sql
```

O script remove apenas contas com `conta_teste = 1` e `papel = 'moderador'`,
além dos logs em que elas são autoras ou alvo e das tentativas de login
correspondentes. A FK de `verificacoes.admin_id` usa `ON DELETE SET NULL`;
históricos de verificação de ONGs reais não são apagados por essa limpeza. Como
as contas de teste só participaram de testes fictícios, a remoção não afeta o
histórico da conta **Equipe ONGHelp**.

### 16.11 Testes executados

Fluxo e interface:

- login da conta administradora e de moderador;
- erro genérico para e-mail inexistente ou senha errada;
- conta desativada revelada apenas depois da senha correta;
- conta `aguardando` com senha válida levada ao primeiro acesso;
- senha temporária expirada com mensagem específica;
- rejeição de senha igual à temporária, curta, contendo o e-mail ou com
  confirmação diferente;
- primeiro acesso concluído grava `ativo`, limpa a validade, rotaciona o token e
  registra `primeiro_acesso_concluido`;
- criação de moderador com senha temporária exibida uma única vez;
- reset de senha invalidando a sessão antiga;
- desativação bloqueando o login e invalidando a sessão imediatamente;
- reativação gerando nova senha temporária e voltando para `aguardando`;
- moderador recebendo 403 em `admin/equipe.php`, com `acesso_negado` nos logs e
  sem o item “Equipe” no menu;
- administrador impedido de desativar a própria conta e a conta principal;
- troca de senha em Minha conta exigindo a senha atual e derrubando as demais
  sessões;
- logout por GET sem efeito e logout por POST + CSRF funcionando;
- POST sem CSRF retornando HTTP 403 nas ações do painel;
- filtros de logs por categoria, pessoa e intervalo de datas, com contador e
  carregamento incremental;
- página pública com `noindex, nofollow`, número exibido e mensagem exata no
  link; link do rodapé com `nofollow`;
- revisão visual em desktop e mobile no Edge headless, com screenshots das telas
  de login, primeiro acesso, Equipe, logs, minha conta e página pública.

Banco e infraestrutura:

- migração de equipe executada no banco vivo legado e duas vezes em banco
  temporário, sem duplicar contas;
- migração testada também em esquema legado com `admins.ativo`: `ativo = 1`
  virou `estado = 'ativo'`, `ativo = 0` virou `estado = 'desativado'` e a coluna
  `ativo` foi removida; a segunda execução não alterou o resultado;
- dump `sql/onghelp.sql` importado em banco temporário vazio no MariaDB 10.4.32;
- esquema final sem `admins.ativo` e com `papel`, `estado`,
  `senha_temporaria_expira_em`, `sessao_token`, `ultimo_acesso_em`, `criado_por`
  e `conta_teste`;
- uma conta no dump principal (id 1), com o histórico preservado;
- `sql/contas_teste.sql` executado duas vezes em banco temporário, sem duplicar
  as três contas;
- `sql/remover_contas_teste.sql` executado duas vezes em seguida, deixando
  apenas a conta administradora e removendo logs (como autor e como alvo) e
  tentativas de login das contas fictícias;
- hashes conferidos com `password_verify()`: `EquipeONGHelp@2026` para a conta
  administradora e `Moderador@2026` para as três contas de teste;
- `AUTO_INCREMENT` de `admins` no dump principal = 2; após o script de teste, os
  ids seguem a sequência natural e não são reaproveitados na remoção;
- lint de PHP em todos os arquivos alterados; os arquivos JavaScript foram
  exercitados no navegador, mas o ambiente não tem Node.js para uma checagem de
  sintaxe standalone.

Não houve falha reproduzível nos testes finais.

### 16.12 Revisão: achados e correções

| Achado | Risco | Correção |
|---|---|---|
| Uma conta compartilhada não identificava a pessoa autora | Falta de responsabilização | Contas individuais, `admin_id` em cada log e autoria exibida no painel |
| Todos os administradores podiam gerenciar contas | Escalada de privilégio | Permissão `equipe.gerenciar` só para administrador, com validação no servidor e HTTP 403 |
| Reset de senha não derrubava sessões antigas | Acesso residual | Rotação de `sessao_token` em reset, troca, desativação e reativação |
| Conta desativada podia continuar com sessão aberta | Acesso indevido | Estado relido do banco em toda requisição e sessão destruída |
| Senha temporária sem prazo | Credencial eterna | Validade de 48 horas, hash no banco e exibição única |
| “Diferente da temporária” só no navegador | Bypass da política | `password_verify` no servidor + digest temporário apenas no checklist |
| Senha temporária podia permanecer no DOM | Vazamento em tela compartilhada | Flash de sessão, modal único e descarte do texto ao fechar |
| Logout por GET | CSRF de logout | Somente POST + CSRF; GET não altera a sessão |
| Excluir moderador apagaria o histórico | Perda de auditoria | Sem exclusão pela interface; desativar e reativar |
| Administrador podia ser desativado ou trocar de papel | Perda da conta principal | Bloqueio na interface e nas ações do servidor |
| Logs sem filtros nem linha do tempo | Dificuldade de auditoria | Categorias, pessoa, datas, contador e agrupamento por dia |
| WhatsApp repetido em páginas | Número divergente | `includes/config.php` como fonte única |
| Página pública indexável | Exposição indevida | `noindex, nofollow` e link de rodapé com `nofollow` |
| Migração podia duplicar contas ou perder histórico | Perda de dados | Migração idempotente, backup, teste em banco temporário e preservação da linha id 1 |
| `ativo` e `estado` convivendo | Regras ambíguas | Conversão, remoção de `ativo` e uso exclusivo de `estado` no código novo |
| Sessão admin longa | Janela de sequestro | 20 minutos de inatividade e 4 horas absolutas |

### 16.13 Critérios de aceite da v1.2

- ✅ Existe exatamente uma conta administradora compartilhada.
- ✅ Moderadores são individuais, criados pelo administrador.
- ✅ O administrador gerencia a equipe; o moderador recebe 403 e não vê o item.
- ✅ Permissões são declarativas e revalidadas no servidor.
- ✅ Estados são apenas `ativo`, `aguardando` e `desativado`.
- ✅ Moderadores não são excluídos pela interface.
- ✅ A conta administradora não pode ser desativada nem trocar de papel.
- ✅ A senha temporária vale 48 horas, é hash no banco e aparece uma única vez.
- ✅ O primeiro acesso exige a política de senha e o aceite das regras.
- ✅ Reset, troca, desativação e reativação invalidam sessões antigas.
- ✅ Logs têm autoria individual, filtros, contagem e linha do tempo.
- ✅ A página pública é `noindex, nofollow`, usa o número exibido
  `+55 16 99779-2777` e a mensagem exata no WhatsApp.
- ✅ Número e mensagem ficam em `includes/config.php`.
- ✅ A migração preserva a conta e o histórico antigos e é idempotente.
- ✅ O dump importa em banco vazio e contém a conta administradora; as três
  contas de teste são criadas por `sql/contas_teste.sql`.
- ✅ O hash fictício do login é aleatório e descartável.
- ✅ A detecção de HTTPS aceita `X-Forwarded-Proto` apenas de proxy confiável
  configurado.
- ✅ As contas de teste ficam fora do dump e da migração, com script de remoção.

### 16.14 Correções pós-verificação (v1.2.1)

Esta revisão resolve os três itens marcados `[PENDENTE]` no prompt original.

| Item `[PENDENTE]` | Como foi resolvido |
|---|---|
| Hash fictício do login | `admin/login.php` não usa mais uma constante com o hash de `Moderador@2026`. Quando o e-mail não existe, o código gera um hash novo e descartável com `password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT)` e roda `password_verify()` contra ele. O resultado é sempre falso e o custo do bcrypt é pago, igualando o tempo de resposta sem reutilizar senha conhecida. |
| Cookie `Secure` atrás de proxy | `includes/session.php` ganhou `requisicaoSegura()`, `proxiesConfiaveis()` e `ipEmListaConfiavel()`. A decisão considera `$_SERVER['HTTPS']`, porta 443 e, por último, `X-Forwarded-Proto: https` — este só é aceito quando `REMOTE_ADDR` pertence à lista configurada. A lista vem de `ONGHELP_PROXIES_CONFIAVEIS` (`includes/config.php`) ou da variável de ambiente `ONGHELP_TRUSTED_PROXIES`, com suporte a IPv4, IPv6 e CIDR. `urlPainelAdmin()` usa a mesma função. No XAMPP local, sem proxy configurado, o comportamento continua HTTP. |
| Contas de teste fora do dump e da migração | `sql/onghelp.sql` agora cria apenas a conta administradora (id 1, `AUTO_INCREMENT = 2`). `sql/migracao_equipe.sql` não cria mais moderadores fictícios. Foram criados `sql/contas_teste.sql` (criação idempotente das três contas, senha `Moderador@2026` apenas como hash) e `sql/remover_contas_teste.sql` (remove contas `conta_teste = 1` e `papel = 'moderador'`, logs como autora ou alvo e tentativas de login). O README traz aviso destacado e os comandos de uso. |

A configuração de produção do proxy fica documentada no README, na seção
“HTTPS atrás de proxy reverso”. Exemplo:

```php
const ONGHELP_PROXIES_CONFIAVEIS = '10.0.0.10, 172.16.0.0/12, 2001:db8::/32';
```

ou, sem editar código:

```bash
ONGHELP_TRUSTED_PROXIES="10.0.0.10,172.16.0.0/12" php-fpm
```
