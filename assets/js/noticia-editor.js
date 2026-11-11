// ============================================================
// assets/js/noticia-editor.js — Editor de notícias da ONG
// ============================================================
// O formulário funciona sem JavaScript. Aqui ficam apenas
// melhorias: editor de texto com formatação, prévia das fotos
// escolhidas, links extras, campos de evento e checklist.
(function () {
  'use strict';

  var form = document.getElementById('nedForm');

  if (!form) {
    return;
  }

  var ALT_MIN = parseInt(form.dataset.altMin, 10) || 10;
  var FOTOS_MAX = parseInt(form.dataset.fotosMax, 10) || 10;
  var LIMITE_ENVIO = 38 * 1024 * 1024; // abaixo do post_max_size do PHP

  // ── Editor de texto ──────────────────────────────────────
  var textarea = document.getElementById('nedTexto');
  var editor = document.getElementById('nedEditor');
  var ferramentas = form.querySelector('.ned-ferramentas');
  var textoInfo = document.getElementById('nedTextoInfo');
  var TEXTO_MIN = parseInt(form.dataset.textoMin, 10) || 100;

  function textoPlano() {
    return (editor && !editor.hidden ? editor.textContent : textarea.value.replace(/<[^>]+>/g, ' '))
      .replace(/\s+/g, ' ').trim();
  }

  function sincronizarTexto() {
    if (editor && !editor.hidden) {
      textarea.value = editor.innerHTML;
    }

    var total = textoPlano().length;

    if (textoInfo) {
      textoInfo.textContent = total + ' caracteres' + (total < TEXTO_MIN ? ' (mínimo de ' + TEXTO_MIN + ')' : '');
    }
  }

  if (textarea && editor) {
    editor.innerHTML = textarea.value;
    editor.hidden = false;
    textarea.hidden = true;

    if (ferramentas && editor.isContentEditable) {
      ferramentas.hidden = false;
    }

    editor.addEventListener('input', function () { sincronizarTexto(); atualizarChecklist(); });

    // Colar como texto simples evita trazer estilos de outros sites.
    editor.addEventListener('paste', function (evento) {
      evento.preventDefault();
      var texto = (evento.clipboardData || window.clipboardData).getData('text/plain');
      document.execCommand('insertText', false, texto);
    });

    if (ferramentas) {
      ferramentas.addEventListener('mousedown', function (evento) { evento.preventDefault(); });
      ferramentas.addEventListener('click', function (evento) {
        var botao = evento.target.closest('[data-cmd]');

        if (!botao) {
          return;
        }

        editor.focus();
        var cmd = botao.getAttribute('data-cmd');

        if (cmd === 'h2') {
          var bloco = document.queryCommandValue('formatBlock').toLowerCase();
          document.execCommand('formatBlock', false, bloco === 'h2' ? 'p' : 'h2');
        } else if (cmd === 'list') {
          document.execCommand('insertUnorderedList');
        } else if (cmd === 'link') {
          var url = window.prompt('Endereço do link (https://...)');

          if (url && /^https?:\/\//i.test(url.trim())) {
            document.execCommand('createLink', false, url.trim());
          }
        } else {
          document.execCommand(cmd);
        }

        sincronizarTexto();
      });
    }

    sincronizarTexto();
  }

  // ── Contadores de título e resumo ────────────────────────
  form.querySelectorAll('[data-contador]').forEach(function (campo) {
    var contador = document.createElement('span');
    contador.className = 'ned-contador';
    contador.setAttribute('aria-hidden', 'true');
    campo.insertAdjacentElement('afterend', contador);

    function atualizar() {
      contador.textContent = campo.value.length + '/' + campo.maxLength;
    }

    campo.addEventListener('input', atualizar);
    atualizar();
  });

  // ── Bloco de evento ──────────────────────────────────────
  var blocoEvento = document.getElementById('nedEvento');

  form.querySelectorAll('input[name="tipo"]').forEach(function (radio) {
    radio.addEventListener('change', function () {
      if (blocoEvento) {
        blocoEvento.hidden = form.querySelector('input[name="tipo"]:checked').value !== 'evento';
      }
    });
  });

  // ── Links ────────────────────────────────────────────────
  var maisLink = document.getElementById('nedMaisLink');
  var links = document.getElementById('nedLinks');

  function atualizarBotaoLink() {
    if (maisLink && links) {
      maisLink.hidden = links.children.length >= (parseInt(maisLink.dataset.max, 10) || 3);
    }
  }

  if (maisLink && links) {
    maisLink.addEventListener('click', function () {
      var novo = links.firstElementChild.cloneNode();
      novo.value = '';
      novo.setAttribute('aria-label', 'Link ' + (links.children.length + 1));
      links.appendChild(novo);
      novo.focus();
      atualizarBotaoLink();
    });
    atualizarBotaoLink();
  }

  // ── Fotos existentes: marcar para remover ────────────────
  form.addEventListener('change', function (evento) {
    if (evento.target.matches('[data-remover]')) {
      evento.target.closest('.ned-foto').classList.toggle('is-removida', evento.target.checked);
      atualizarChecklist();
    }
  });

  // ── Fotos novas: prévia com descrição ────────────────────
  var arquivos = document.getElementById('nedArquivos');
  var areaEnvio = document.getElementById('nedEnviar');
  var gradeNovas = document.getElementById('nedFotosNovas');
  var modelo = document.getElementById('nedModeloFoto');
  var selecionadas = []; // { arquivo, alt, legenda, url }
  var podeTrocarArquivos = typeof DataTransfer === 'function';

  function fotosExistentesMantidas() {
    return form.querySelectorAll('.ned-foto:not(.is-removida):not(#nedFotosNovas .ned-foto)').length;
  }

  function aplicarArquivosNoInput() {
    if (!podeTrocarArquivos) {
      return;
    }

    var dt = new DataTransfer();
    selecionadas.forEach(function (item) { dt.items.add(item.arquivo); });
    arquivos.files = dt.files;
  }

  function renderizarNovas() {
    gradeNovas.innerHTML = '';

    selecionadas.forEach(function (item, indice) {
      var no = modelo.content.firstElementChild.cloneNode(true);
      var img = no.querySelector('img');
      var radio = no.querySelector('input[type="radio"]');
      var alt = no.querySelector('textarea');
      var legenda = no.querySelector('input[type="text"]');

      img.src = item.url;
      radio.value = 'nova-' + indice;
      alt.name = 'novas_alt[' + indice + ']';
      alt.value = item.alt;
      legenda.name = 'novas_legenda[' + indice + ']';
      legenda.value = item.legenda;

      alt.addEventListener('input', function () { item.alt = alt.value; atualizarChecklist(); });
      legenda.addEventListener('input', function () { item.legenda = legenda.value; });
      no.querySelector('[data-tirar]').addEventListener('click', function () {
        URL.revokeObjectURL(item.url);
        selecionadas.splice(indice, 1);
        aplicarArquivosNoInput();
        renderizarNovas();
      });

      // Sem DataTransfer não dá para tirar um arquivo da seleção.
      if (!podeTrocarArquivos) {
        no.querySelector('[data-tirar]').hidden = true;
      }

      gradeNovas.appendChild(no);
    });

    // Sem fotos ainda: a primeira nova vira capa.
    var capaMarcada = form.querySelector('input[name="capa"]:checked');

    if (!capaMarcada) {
      var primeira = form.querySelector('input[name="capa"]');

      if (primeira) {
        primeira.checked = true;
      }
    }

    atualizarChecklist();
  }

  function adicionarArquivos(lista) {
    var avisos = [];

    Array.prototype.forEach.call(lista, function (arquivo) {
      if (!/^image\/(jpeg|png|webp)$/.test(arquivo.type)) {
        avisos.push(arquivo.name + ': use JPG, PNG ou WebP.');
      } else if (arquivo.size > 5 * 1024 * 1024) {
        avisos.push(arquivo.name + ': passa de 5 MB.');
      } else if (fotosExistentesMantidas() + selecionadas.length >= FOTOS_MAX) {
        avisos.push('Limite de ' + FOTOS_MAX + ' fotos atingido.');
      } else {
        selecionadas.push({ arquivo: arquivo, alt: '', legenda: '', url: URL.createObjectURL(arquivo) });
      }
    });

    aplicarArquivosNoInput();
    renderizarNovas();

    if (avisos.length) {
      window.alert(Array.from(new Set(avisos)).join('\n'));
    }
  }

  if (arquivos && gradeNovas && modelo) {
    arquivos.addEventListener('change', function () {
      if (podeTrocarArquivos) {
        adicionarArquivos(arquivos.files);
      } else {
        selecionadas = [];
        adicionarArquivos(arquivos.files);
      }
    });

    ['dragenter', 'dragover'].forEach(function (tipo) {
      areaEnvio.addEventListener(tipo, function (evento) {
        evento.preventDefault();
        areaEnvio.classList.add('is-arrastando');
      });
    });

    ['dragleave', 'drop'].forEach(function (tipo) {
      areaEnvio.addEventListener(tipo, function () { areaEnvio.classList.remove('is-arrastando'); });
    });

    areaEnvio.addEventListener('drop', function (evento) {
      evento.preventDefault();

      if (podeTrocarArquivos && evento.dataTransfer && evento.dataTransfer.files.length) {
        adicionarArquivos(evento.dataTransfer.files);
      }
    });
  }

  // ── Checklist ────────────────────────────────────────────
  var checklist = document.getElementById('nedChecklist');

  function marcar(chave, ok) {
    var item = checklist && checklist.querySelector('[data-check="' + chave + '"]');

    if (item) {
      item.classList.toggle('is-ok', ok);
    }
  }

  function atualizarChecklist() {
    var titulo = document.getElementById('nedTitulo');
    var resumo = document.getElementById('nedResumo');
    var alts = Array.prototype.filter.call(
      form.querySelectorAll('.ned-foto:not(.is-removida) [data-alt]'),
      function (campo) { return campo.value.trim().length >= ALT_MIN; }
    );
    var totalFotos = fotosExistentesMantidas() + selecionadas.length;

    marcar('titulo', titulo.value.trim().length >= (parseInt(form.dataset.tituloMin, 10) || 10)
      && resumo.value.trim().length >= (parseInt(form.dataset.resumoMin, 10) || 30));
    marcar('tipo', !!form.querySelector('input[name="tipo"]:checked'));
    marcar('texto', textoPlano().length >= TEXTO_MIN);
    marcar('fotos', totalFotos > 0 && alts.length === totalFotos);
    marcar('direitos', document.getElementById('nedDireitos').checked);
  }

  form.addEventListener('input', atualizarChecklist);
  form.addEventListener('change', atualizarChecklist);
  atualizarChecklist();

  // ── Envio ────────────────────────────────────────────────
  form.addEventListener('submit', function (evento) {
    sincronizarTexto();

    var botao = evento.submitter;
    var confirmacao = botao && botao.getAttribute('data-confirmar');

    if (confirmacao && !window.confirm(confirmacao)) {
      evento.preventDefault();
      return;
    }

    var tamanho = selecionadas.reduce(function (soma, item) { return soma + item.arquivo.size; }, 0);

    if (tamanho > LIMITE_ENVIO) {
      evento.preventDefault();
      window.alert('As fotos escolhidas somam mais de 38 MB. Envie menos fotos agora e adicione as demais depois de salvar.');
      return;
    }

    // Evita duplo envio.
    window.setTimeout(function () {
      form.querySelectorAll('button[type="submit"]').forEach(function (b) { b.disabled = true; });
    }, 0);
  });
})();
