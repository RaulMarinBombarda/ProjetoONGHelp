-- ============================================================
-- ONGHelp — remove as contas fictícias de demonstração
-- ============================================================
--
-- Use este script antes de qualquer apresentação final, publicação
-- ou implantação real. Ele remove SOMENTE contas marcadas com
-- conta_teste = 1 e papel = 'moderador', além dos logs e tentativas
-- de login gerados por elas.
--
-- A conta administradora compartilhada NUNCA é removida: ela tem
-- conta_teste = 0 e papel = 'administrador'.
--
-- Faça backup antes:
--   mysqldump -u root onghelp > backup_onghelp_antes_da_limpeza.sql
--
-- Uso:
--   mysql -u root onghelp < sql/remover_contas_teste.sql
--
-- A FK verificacoes.admin_id é ON DELETE SET NULL: decisões antigas
-- de ONGs reais continuam no histórico, apenas sem o autor fictício.
-- ============================================================

START TRANSACTION;

-- 1. Remove os logs cujo autor é uma conta de teste. Sem isso, os
--    registros ficariam órfãos (admin_id passa a NULL) e continuariam
--    aparecendo como "Equipe ONGHelp" no histórico.
DELETE l
FROM   `logs_admin` l
JOIN   `admins` a ON a.id = l.admin_id
WHERE  a.conta_teste = 1;

-- 1b. Remove também os eventos cujo ALVO é uma conta de teste
--     (criação, reset de senha, desativação, reativação).
DELETE FROM `logs_admin`
WHERE  `alvo_tipo` = 'admin'
  AND  `alvo_id` IN (
       SELECT `id` FROM `admins` WHERE `conta_teste` = 1
  );

-- 2. Remove as contas de teste. A cláusula de papel impede que uma
--    eventual conta administradora seja apagada por engano.
DELETE FROM `admins`
WHERE  `conta_teste` = 1
  AND  `papel` = 'moderador';

-- 3. Limpa o rate limiting das contas fictícias.
DELETE FROM `tentativas_login`
WHERE  `escopo` = 'admin'
  AND  `email` IN (
       'pipoca@moderacao.onghelp.test',
       'tapioca@moderacao.onghelp.test',
       'cuscuz@moderacao.onghelp.test'
  );

COMMIT;

-- Conferência: deve sobrar apenas a conta administradora.
SELECT `id`, `nome`, `email`, `papel`, `estado`, `conta_teste`
FROM   `admins`
ORDER  BY (`papel` = 'administrador') DESC, `nome` ASC;

-- ============================================================
-- Fim da limpeza.
-- ============================================================
