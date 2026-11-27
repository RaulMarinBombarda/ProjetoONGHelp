/* ============================================================
   assets/js/site/perfil-privado.js — painel da ONG
   ============================================================
   a troca de seção é feita pelo servidor (?secao=), aqui ficam só
   as prévias ao vivo: barra de alterações não salvas, foto antes
   do envio, cor do banner, confirmação de exclusão e toasts
   ============================================================ */
(function () {
  'use strict';

  function ready(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  }

  ready(function () {
    var root = document.querySelector('.perfil-privado');
    if (!root) return;

    /* ---------- barra de alterações não salvas ---------- */
    var formularioEdicao = document.querySelector('[data-priv-dirty-form]');
    var barraSalvar = document.querySelector('[data-priv-savebar]');
    var botaoDescartar = document.querySelector('[data-priv-discard]');
    var valoresIniciais = null;

    function lerValoresFormulario() {
      var valores = {};
      if (!formularioEdicao) return valores;

      Array.prototype.forEach.call(formularioEdicao.elements, function (campo) {
        if (!campo.name || campo.disabled) return;
        if (campo.type === 'file' || campo.type === 'submit' ||
            campo.type === 'button' || campo.type === 'hidden') return;
        valores[campo.name] = campo.value;
      });

      return valores;
    }

    function atualizarSavebar() {
      if (!barraSalvar || !formularioEdicao) return;
      barraSalvar.hidden = !formularioSujo();
    }

    function formularioSujo() {
      if (!formularioEdicao || !valoresIniciais) return false;
      var atuais = lerValoresFormulario();
      return Object.keys(valoresIniciais).some(function (nome) {
        return atuais[nome] !== valoresIniciais[nome];
      });
    }

    if (formularioEdicao && barraSalvar) {
      valoresIniciais = lerValoresFormulario();

      formularioEdicao.addEventListener('input', atualizarSavebar);
      formularioEdicao.addEventListener('change', atualizarSavebar);

      formularioEdicao.addEventListener('submit', function () {
        barraSalvar.hidden = true;
      });

      if (botaoDescartar) {
        botaoDescartar.addEventListener('click', function () {
          Array.prototype.forEach.call(formularioEdicao.elements, function (campo) {
            if (!campo.name || campo.disabled) return;
            if (Object.prototype.hasOwnProperty.call(valoresIniciais, campo.name)) {
              campo.value = valoresIniciais[campo.name];
            }
          });
          atualizarSavebar();
        });
      }
    }

    /* ---------- foto de perfil ---------- */
    var campoFoto = document.getElementById('fotoPerfilInput');
    var nomeArquivoFoto = document.querySelector('[data-priv-photo-name]');
    var enviarFoto = document.querySelector('[data-priv-photo-submit]');
    var previaFoto = document.querySelector('[data-priv-photo-preview]');
    var avatarCabecalho = root.querySelector('.priv-avatar');
    var fotoOriginal = previaFoto ? previaFoto.getAttribute('src') : '';
    var avatarOriginal = avatarCabecalho ? avatarCabecalho.getAttribute('src') : '';
    var leituraFoto = 0;

    function restaurarFotoOriginal() {
      if (previaFoto) previaFoto.src = fotoOriginal;
      if (avatarCabecalho) avatarCabecalho.src = avatarOriginal;
    }

    if (campoFoto) {
      campoFoto.addEventListener('change', function () {
        var arquivo = campoFoto.files && campoFoto.files[0];
        var temArquivo = !!arquivo;

        if (nomeArquivoFoto) {
          nomeArquivoFoto.textContent = temArquivo
            ? arquivo.name
            : 'Nenhum arquivo escolhido';
        }
        if (enviarFoto) enviarFoto.disabled = !temArquivo;

        leituraFoto += 1;
        var leituraAtual = leituraFoto;

        if (!temArquivo || !/^image\//.test(arquivo.type)) {
          restaurarFotoOriginal();
          return;
        }

        // FileReader gera data URL: compatível com a CSP atual (img-src 'self' data:)
        var leitor = new FileReader();
        leitor.addEventListener('load', function () {
          if (leituraAtual !== leituraFoto) return;
          if (previaFoto) previaFoto.src = String(leitor.result);
          if (avatarCabecalho) avatarCabecalho.src = String(leitor.result);
        });
        leitor.readAsDataURL(arquivo);
      });
    }

    /* ---------- cor do banner (só a paleta) ---------- */
    // clicar numa cor da paleta muda o banner da prévia e do cabeçalho na
    // hora e prepara o envio, o servidor confere se a cor é da paleta
    var inputBanner = document.getElementById('corTemaInput');
    var nomeCor = document.querySelector('[data-priv-cor-nome]');

    function escurecer(hex) {
      var n = parseInt(hex.slice(1), 16);
      var r = Math.round(((n >> 16) & 255) * 0.8);
      var g = Math.round(((n >> 8) & 255) * 0.8);
      var b = Math.round((n & 255) * 0.8);
      return '#' + [r, g, b].map(function (valor) {
        return valor.toString(16).padStart(2, '0');
      }).join('');
    }

    if (inputBanner) {
      document.querySelectorAll('.btn-cor-tema').forEach(function (botao) {
        botao.addEventListener('click', function () {
          var cor = botao.getAttribute('data-cor');

          inputBanner.value = cor;
          root.style.setProperty('--priv-banner', cor);
          root.style.setProperty('--priv-banner-dark', escurecer(cor));
          if (nomeCor) nomeCor.textContent = botao.getAttribute('data-nome') || cor;

          document.querySelectorAll('.btn-cor-tema').forEach(function (outro) {
            var escolhido = outro === botao;
            outro.classList.toggle('is-selected', escolhido);
            outro.setAttribute('aria-pressed', escolhido ? 'true' : 'false');
          });
        });
      });
    }

    /* ---------- confirmação de exclusão ---------- */
    var campoExclusao = document.querySelector('[data-priv-delete-input]');
    var botaoExclusao = document.querySelector('[data-priv-delete-confirm]');
    var modalExclusao = document.getElementById('modalExcluirConta');

    function nomeConfere() {
      if (!campoExclusao) return false;
      return campoExclusao.value === campoExclusao.getAttribute('data-ong-nome');
    }

    function atualizarExclusao() {
      if (!botaoExclusao) return;
      var liberado = nomeConfere();
      botaoExclusao.classList.toggle('disabled', !liberado);
      botaoExclusao.setAttribute('aria-disabled', liberado ? 'false' : 'true');
      botaoExclusao.tabIndex = liberado ? 0 : -1;
    }

    if (campoExclusao && botaoExclusao) {
      campoExclusao.addEventListener('input', atualizarExclusao);
      atualizarExclusao();

      botaoExclusao.addEventListener('click', function (evento) {
        if (!nomeConfere()) evento.preventDefault();
      });

      campoExclusao.addEventListener('keydown', function (evento) {
        if (evento.key === 'Enter' && nomeConfere()) {
          evento.preventDefault();
          botaoExclusao.click();
        }
      });
    }

    if (modalExclusao) {
      modalExclusao.addEventListener('shown.bs.modal', function () {
        if (campoExclusao) campoExclusao.focus();
      });

      modalExclusao.addEventListener('hidden.bs.modal', function () {
        if (campoExclusao) campoExclusao.value = '';
        atualizarExclusao();
      });
    }

    /* ---------- toasts ---------- */
    document.querySelectorAll('[data-priv-toast]').forEach(function (toast) {
      var fechar = function () {
        toast.classList.add('is-hiding');
        window.setTimeout(function () {
          if (toast.parentNode) toast.parentNode.removeChild(toast);
        }, 240);
      };

      var timer = window.setTimeout(fechar, 6500);

      var botaoFechar = toast.querySelector('[data-priv-toast-close]');
      if (botaoFechar) {
        botaoFechar.addEventListener('click', function () {
          window.clearTimeout(timer);
          fechar();
        });
      }
    });
  });
})();
