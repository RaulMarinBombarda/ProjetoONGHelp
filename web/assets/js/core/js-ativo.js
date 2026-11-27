/* marca o <html> com a classe "js" antes da renderização, o CSS usa
   .js para esconder conteúdo que só faz sentido com JavaScript (por
   exemplo, as etapas ainda não visitadas do cadastro), sem JS, tudo
   continua visível e funcional */
document.documentElement.classList.add('js');
