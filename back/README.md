# back

O back-end do ONGHelp é **PHP renderizado no servidor** e roda junto com o
site, dentro de [`../web`](../web). Não existe uma API separada: cada página
PHP recebe a requisição, aplica as regras de negócio, consulta o MySQL e já
devolve o HTML pronto.

Onde fica cada parte do back-end dentro de `web/`:

| Pasta | O que tem |
|---|---|
| `web/includes/core/` | Base de todas as páginas: conexão com o banco (PDO), sessão, CSRF, limite de tentativas de login, cabeçalhos de segurança, regras de senha e funções gerais |
| `web/includes/modules/` | Regras de negócio: gestão financeira e projetos, notícias, notificações, permissões do painel e o gerador de PDF |
| `web/pages/actions/` | Endpoints sem tela: exportação CSV, relatório anual em PDF, denúncia e logout |
| `web/admin/acao.php` | Decisões do painel da equipe (aprovar, recusar, suspender, reativar) |
| `web/config/` | Configuração do sistema e da conexão com o banco |
| `web/database/` | Banco completo (`onghelp.sql`) para importar no MySQL |
| `web/tests/` | Testes automatizados |

Essas pastas são bloqueadas para o navegador pelo `web/.htaccess`: só o PHP
lê o que está nelas.

Se um dia o projeto ganhar um aplicativo (ver [`../mobile`](../mobile)), o
caminho natural é expor essas mesmas regras como uma API JSON nesta pasta,
reaproveitando `includes/` e o mesmo banco.
