/* ============================================================
   ONGHelp — perfil privado
   Navegação entre seções, barra de alterações, foto, tema,
   confirmação de exclusão e toasts.
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

    var CHAVE_SECAO = 'onghelpPerfilPrivadoSecao';

    function lerSecaoSalva() {
      try {
        return sessionStorage.getItem(CHAVE_SECAO) || '';
      } catch (e) {
        return '';
      }
    }

    function salvarSecao(id) {
      try {
        sessionStorage.setItem(CHAVE_SECAO, id);
      } catch (e) {
        /* sessionStorage indisponível: a hash da URL continua funcionando. */
      }
    }

    /* ---------- Navegação entre seções ---------- */
    var paineis = Array.prototype.slice.call(
      root.querySelectorAll('[data-priv-panel]')
    );
    var abas = Array.prototype.slice.call(
      root.querySelectorAll('[data-priv-tab]')
    );
    var abasMenu = abas.filter(function (aba) {
      return aba.classList.contains('priv-menu-item');
    });
    var secaoAtual = '';

    function idValido(id) {
      return paineis.some(function (painel) {
        return painel.getAttribute('data-priv-panel') === id;
      });
    }

    function atualizarSavebar() {
      if (!barraSalvar || !formularioEdicao) return;
      var sujo = formularioSujo();
      barraSalvar.hidden = !(sujo && secaoAtual === 'editar-perfil');
    }

    function ativarSecao(id, opcoes) {
      opcoes = opcoes || {};

      if (!idValido(id)) id = 'visao-geral';

      paineis.forEach(function (painel) {
        painel.hidden = painel.getAttribute('data-priv-panel') !== id;
      });

      abasMenu.forEach(function (aba) {
        var ativa = aba.getAttribute('data-priv-tab') === id;
        aba.classList.toggle('is-active', ativa);
        if (ativa) {
          aba.setAttribute('aria-current', 'page');
        } else {
          aba.removeAttribute('aria-current');
        }
      });

      secaoAtual = id;
      salvarSecao(id);

      if (opcoes.pushHash !== false && location.hash !== '#' + id) {
        if (opcoes.replace) {
          history.replaceState(null, '', '#' + id);
        } else {
          history.pushState(null, '', '#' + id);
        }
      }

      atualizarSavebar();

      if (opcoes.scroll) {
        var layout = root.querySelector('.priv-layout');
        if (layout) {
          var topo = layout.getBoundingClientRect().top +
            (window.pageYOffset || document.documentElement.scrollTop) - 104;
          window.scrollTo({ top: Math.max(0, topo), behavior: 'smooth' });
        }
      }
    }

    abas.forEach(function (aba) {
      aba.addEventListener('click', function (evento) {
        evento.preventDefault();
        ativarSecao(aba.getAttribute('data-priv-tab'), {
          scroll: aba.getAttribute('data-priv-scroll') === 'true'
        });
      });
    });

    window.addEventListener('hashchange', function () {
      var id = location.hash.replace(/^#/, '');
      if (idValido(id)) {
        ativarSecao(id, { pushHash: false });
      } else {
        history.replaceState(null, '', '#' + secaoAtual);
      }
    });

    /* ---------- Barra de alterações não salvas ---------- */
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

    /* ---------- Foto de perfil ---------- */
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

        // FileReader gera data URL: compatível com a CSP atual (img-src 'self' data:).
        var leitor = new FileReader();
        leitor.addEventListener('load', function () {
          if (leituraAtual !== leituraFoto) return;
          if (previaFoto) previaFoto.src = String(leitor.result);
          if (avatarCabecalho) avatarCabecalho.src = String(leitor.result);
        });
        leitor.readAsDataURL(arquivo);
      });
    }

    /* ---------- Tema do perfil ---------- */
    var pickBanner = document.getElementById('corTemaPick');
    var hexBanner = document.getElementById('corTemaHex');
    var inputBanner = document.getElementById('corTemaInput');
    var pickFundo = document.getElementById('corFundoPick');
    var hexFundo = document.getElementById('corFundoHex');
    var inputFundo = document.getElementById('corFundoInput');
    var switchFundo = document.getElementById('semFundoCheck');
    var previaTema = document.querySelector('[data-priv-preview]');
    var COR_FUNDO_PADRAO = '#F4F8FB';

    function normalizarCor(cor) {
      cor = String(cor || '').trim().toUpperCase();
      if (/^[0-9A-F]{6}$/.test(cor)) cor = '#' + cor;
      return /^#[0-9A-F]{6}$/.test(cor) ? cor : '';
    }

    function escurecer(hex) {
      var n = parseInt(hex.slice(1), 16);
      var r = Math.round(((n >> 16) & 255) * 0.8);
      var g = Math.round(((n >> 8) & 255) * 0.8);
      var b = Math.round((n & 255) * 0.8);
      return '#' + [r, g, b].map(function (valor) {
        return valor.toString(16).padStart(2, '0');
      }).join('');
    }

    function marcarAmostras(seletor, cor) {
      document.querySelectorAll(seletor).forEach(function (botao) {
        var igual = normalizarCor(botao.getAttribute('data-cor')) === cor;
        botao.classList.toggle('is-selected', igual);
        botao.setAttribute('aria-pressed', igual ? 'true' : 'false');
      });
    }

    function aplicarBanner(cor, atualizarHex, sincronizarDark) {
      cor = normalizarCor(cor);
      if (!cor) return false;

      if (inputBanner) inputBanner.value = cor;
      if (pickBanner) pickBanner.value = cor.toLowerCase();
      if (hexBanner && atualizarHex !== false) hexBanner.value = cor;

      root.style.setProperty('--priv-banner', cor);
      // Na carga inicial preserva a cor escura calculada pelo PHP (temaOng),
      // que pode vir da paleta por área e não do fator de escurecimento.
      if (sincronizarDark !== false) {
        root.style.setProperty('--priv-banner-dark', escurecer(cor));
      }
      marcarAmostras('.btn-cor-tema', cor);

      return true;
    }

    function aplicarFundo(cor) {
      cor = normalizarCor(cor);

      if (inputFundo) inputFundo.value = cor;
      if (hexFundo) hexFundo.value = cor;
      if (pickFundo) pickFundo.value = (cor || COR_FUNDO_PADRAO).toLowerCase();
      if (switchFundo) switchFundo.checked = !cor;

      if (previaTema) {
        previaTema.style.setProperty('--priv-preview-fundo', cor || COR_FUNDO_PADRAO);
      }

      marcarAmostras('.btn-cor-fundo', cor);
    }

    if (inputBanner && pickBanner) {
      aplicarBanner(inputBanner.value || '#0D5C8F', true, false);

      pickBanner.addEventListener('input', function () {
        aplicarBanner(pickBanner.value);
      });

      if (hexBanner) {
        hexBanner.addEventListener('input', function () {
          var cor = normalizarCor(hexBanner.value);
          if (cor) aplicarBanner(cor, false);
        });

        hexBanner.addEventListener('blur', function () {
          var cor = normalizarCor(hexBanner.value);
          if (cor) {
            aplicarBanner(cor);
          } else if (hexBanner) {
            hexBanner.value = inputBanner.value;
          }
        });
      }

      document.querySelectorAll('.btn-cor-tema').forEach(function (botao) {
        botao.addEventListener('click', function () {
          aplicarBanner(botao.getAttribute('data-cor'));
        });
      });
    }

    if (inputFundo && pickFundo) {
      aplicarFundo(inputFundo.value);

      pickFundo.addEventListener('input', function () {
        aplicarFundo(pickFundo.value);
      });

      if (hexFundo) {
        hexFundo.addEventListener('input', function () {
          var cor = normalizarCor(hexFundo.value);
          if (cor) {
            if (inputFundo) inputFundo.value = cor;
            if (switchFundo) switchFundo.checked = false;
            if (previaTema) {
              previaTema.style.setProperty('--priv-preview-fundo', cor);
            }
            marcarAmostras('.btn-cor-fundo', cor);
          }
        });

        hexFundo.addEventListener('blur', function () {
          var cor = normalizarCor(hexFundo.value);
          if (cor) {
            aplicarFundo(cor);
          } else if (hexFundo) {
            hexFundo.value = inputFundo ? inputFundo.value : '';
          }
        });
      }

      document.querySelectorAll('.btn-cor-fundo').forEach(function (botao) {
        botao.addEventListener('click', function () {
          aplicarFundo(botao.getAttribute('data-cor'));
        });
      });

      if (switchFundo) {
        switchFundo.addEventListener('change', function () {
          if (switchFundo.checked) {
            aplicarFundo('');
          } else {
            aplicarFundo(
              normalizarCor(hexFundo && hexFundo.value) ||
              (pickFundo && pickFundo.value) ||
              COR_FUNDO_PADRAO
            );
          }
        });
      }
    }

    /* ---------- Confirmação de exclusão ---------- */
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

    /* ---------- Toasts ---------- */
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

    /* ---------- Seção inicial ---------- */
    var temHash = location.hash !== '';
    var hash = location.hash.replace(/^#/, '');
    var hashValida = idValido(hash);
    var salva = lerSecaoSalva();

    var inicial;
    if (temHash) {
      // Hash explícita manda: se for inválida, volta para a visão geral.
      inicial = hashValida ? hash : 'visao-geral';
    } else {
      inicial = idValido(salva) ? salva : 'visao-geral';
    }

    ativarSecao(inicial, { pushHash: false });

    if (!hashValida || !temHash) {
      history.replaceState(null, '', '#' + inicial);
    }
  });
})();
