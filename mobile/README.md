# mobile

O ONGHelp ainda **não tem um aplicativo nativo**. A versão para celular é o
próprio site, em [`../web`](../web), que é responsivo: todas as telas (site
público, painel da ONG e painel da equipe) se adaptam a telas pequenas, com
menu recolhível e conteúdo reorganizado em uma coluna.

Para usar no celular durante a demonstração, com o computador e o celular na
mesma rede Wi-Fi:

1. No computador, descubra o IP local: abra o **Prompt de Comando** e digite
   `ipconfig`; anote o "Endereço IPv4" (algo como `192.168.0.15`).
2. No navegador do celular, abra `http://192.168.0.15/ONGHelp/` (troque pelo
   IP anotado).
3. Se não abrir, libere o Apache no Firewall do Windows quando ele perguntar,
   ou em **Firewall do Windows → Permitir um aplicativo**.

Um aplicativo nativo está no roadmap do projeto (ver o README principal). Ele
consumiria uma API construída em [`../back`](../back) sobre as mesmas regras e
o mesmo banco do site.
