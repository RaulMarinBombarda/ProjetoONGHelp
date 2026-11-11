/* ============================================================
   ONGHelp — cadastro em 3 etapas
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
    var form = document.querySelector('[data-cadastro-form]');
    if (!form) return;

    var steps = Array.prototype.slice.call(form.querySelectorAll('.cadastro-step'));
    var indicators = Array.prototype.slice.call(document.querySelectorAll('[data-step-indicator]'));
    var counter = document.querySelector('[data-step-counter]');
    var cnpj = form.querySelector('[data-cnpj]');
    var senha = form.querySelector('[name="senha"]');
    var senhaConfirm = form.querySelector('[name="senha_confirm"]');
    var senhaRule = form.querySelector('[data-password-rule]');
    var matchRule = form.querySelector('[data-match-rule]');
    var currentStep = 1;
    var animationTimer = null;

    function getStep(numero) {
      return form.querySelector('.cadastro-step[data-step="' + numero + '"]');
    }

    function onlyDigits(value) {
      return String(value || '').replace(/\D/g, '');
    }

    function formatCnpj(value) {
      var digits = onlyDigits(value).slice(0, 14);
      var formatted = digits;

      if (digits.length > 2) {
        formatted = digits.slice(0, 2) + '.' + digits.slice(2);
      }
      if (digits.length > 5) {
        formatted = digits.slice(0, 2) + '.' + digits.slice(2, 5) + '.' + digits.slice(5);
      }
      if (digits.length > 8) {
        formatted = digits.slice(0, 2) + '.' + digits.slice(2, 5) + '.' + digits.slice(5, 8) + '/' + digits.slice(8);
      }
      if (digits.length > 12) {
        formatted = digits.slice(0, 2) + '.' + digits.slice(2, 5) + '.' + digits.slice(5, 8) + '/' + digits.slice(8, 12) + '-' + digits.slice(12);
      }

      return formatted;
    }

    function fieldError(field) {
      var wrapper = field.closest('.cadastro-field');
      return wrapper ? wrapper.querySelector('.cadastro-error') : null;
    }

    function setFieldError(field, message) {
      var wrapper = field.closest('.cadastro-field');
      var error = fieldError(field);

      if (wrapper) wrapper.classList.toggle('is-invalid', !!message);
      if (error) error.textContent = message || '';

      if (message) {
        field.setAttribute('aria-invalid', 'true');
      } else {
        field.removeAttribute('aria-invalid');
      }
    }

    function validateField(field) {
      var name = field.name;
      var value = field.value;

      if (field.type === 'checkbox') {
        return field.required && !field.checked ? 'Marque esta opção para continuar.' : '';
      }

      if (field.required && value.trim() === '') {
        return field.tagName === 'SELECT' ? 'Selecione uma opção.' : 'Preencha este campo.';
      }

      if (value.trim() === '') return '';

      if (name === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim())) {
        return 'Informe um e-mail válido.';
      }

      if (name === 'cnpj' && onlyDigits(value).length !== 14) {
        return 'Informe o CNPJ com 14 dígitos.';
      }

      if (name === 'senha' && value.length < 8) {
        return 'A senha deve ter pelo menos 8 caracteres.';
      }

      if (name === 'senha_confirm' && senha && value !== senha.value) {
        return 'As senhas não coincidem.';
      }

      return '';
    }

    function validateStep(numero) {
      var section = getStep(numero);
      if (!section) return true;

      var fields = section.querySelectorAll('input, select, textarea');
      var firstInvalid = null;

      Array.prototype.forEach.call(fields, function (field) {
        if (field.disabled) return;

        var message = validateField(field);
        setFieldError(field, message);

        if (message && !firstInvalid) firstInvalid = field;
      });

      if (firstInvalid) {
        firstInvalid.focus();
        return false;
      }

      return true;
    }

    function updatePasswordFeedback() {
      var senhaOk = !!senha && senha.value.length >= 8;
      var iguais = !!senha && !!senhaConfirm
        && senha.value !== ''
        && senhaConfirm.value !== ''
        && senha.value === senhaConfirm.value;

      if (senhaRule) senhaRule.classList.toggle('is-valid', senhaOk);
      if (matchRule) matchRule.classList.toggle('is-valid', iguais);
    }

    function updateStepper() {
      indicators.forEach(function (item) {
        var numero = parseInt(item.getAttribute('data-step-indicator'), 10);
        var ativo = numero === currentStep;

        item.classList.toggle('is-active', ativo);
        item.classList.toggle('is-complete', numero < currentStep);

        if (ativo) {
          item.setAttribute('aria-current', 'step');
        } else {
          item.removeAttribute('aria-current');
        }
      });

      if (counter) {
        counter.textContent = 'Etapa ' + currentStep + ' de ' + steps.length;
      }
    }

    function focusFirstField(numero) {
      var section = getStep(numero);
      if (!section) return;

      var field = section.querySelector('input, select, textarea');
      if (!field) return;

      try {
        field.focus({ preventScroll: false });
      } catch (erro) {
        field.focus();
      }
    }

    function showStep(numero, direction) {
      var destino = getStep(numero);
      if (!destino || numero === currentStep) {
        if (destino) focusFirstField(numero);
        return;
      }

      var anterior = currentStep;
      currentStep = numero;

      form.setAttribute('data-step', String(currentStep));
      form.setAttribute('data-direction', direction || (numero > anterior ? 'forward' : 'back'));

      steps.forEach(function (section) {
        var ativo = section === destino;
        section.classList.remove('is-entering');
        section.classList.toggle('is-active', ativo);
      });

      // Força o navegador a enxergar o novo estado antes de animar.
      void destino.offsetWidth;
      destino.classList.add('is-entering');

      updateStepper();
      focusFirstField(numero);

      if (animationTimer) window.clearTimeout(animationTimer);
      animationTimer = window.setTimeout(function () {
        destino.classList.remove('is-entering');
      }, 240);
    }

    // O JS assume a validação por etapa; sem JS o navegador continua
    // usando as validações nativas e o servidor recebe o formulário inteiro.
    form.noValidate = true;

    var passoInicial = parseInt(form.getAttribute('data-initial-step'), 10);
    if (!isNaN(passoInicial) && passoInicial >= 1 && passoInicial <= steps.length) {
      currentStep = passoInicial;
    }

    steps.forEach(function (section) {
      var numero = parseInt(section.getAttribute('data-step'), 10);
      section.classList.toggle('is-active', numero === currentStep);
    });

    form.setAttribute('data-step', String(currentStep));
    updateStepper();
    updatePasswordFeedback();

    if (cnpj) {
      cnpj.value = formatCnpj(cnpj.value);
      cnpj.addEventListener('input', function () {
        var formatado = formatCnpj(cnpj.value);
        if (cnpj.value !== formatado) cnpj.value = formatado;
      });
    }

    Array.prototype.forEach.call(form.querySelectorAll('.cadastro-field.is-invalid'), function (wrapper) {
      var field = wrapper.querySelector('input, select, textarea');
      if (field) field.setAttribute('aria-invalid', 'true');
    });

    var campoComErroDoServidor = getStep(currentStep)
      ? getStep(currentStep).querySelector('.cadastro-field.is-invalid input, .cadastro-field.is-invalid select')
      : null;
    if (campoComErroDoServidor) campoComErroDoServidor.focus();

    form.addEventListener('input', function (evento) {
      var field = evento.target;
      if (!field.matches('input, select, textarea')) return;

      if (field.closest('.cadastro-field.is-invalid')) {
        setFieldError(field, '');
      }

      if (field === senha || field === senhaConfirm) {
        setFieldError(senha, '');
        setFieldError(senhaConfirm, '');
        updatePasswordFeedback();
      }
    });

    form.addEventListener('change', function (evento) {
      var field = evento.target;
      if (!field.matches('input[type="checkbox"], select')) return;

      if (field.closest('.cadastro-field.is-invalid')) {
        setFieldError(field, '');
      }
    });

    form.addEventListener('click', function (evento) {
      var back = evento.target.closest('[data-step-back]');
      if (back) {
        evento.preventDefault();
        if (currentStep > 1) showStep(currentStep - 1, 'back');
        return;
      }

      var next = evento.target.closest('.cadastro-continue');
      if (!next) return;

      evento.preventDefault();
      if (validateStep(currentStep)) showStep(currentStep + 1, 'forward');
    });

    // Enter avança nas duas primeiras etapas. Na última, o envio é
    // o comportamento nativo do único botão de envio do formulário.
    form.addEventListener('keydown', function (evento) {
      if (evento.key !== 'Enter' || evento.shiftKey || evento.altKey || evento.ctrlKey || evento.metaKey) {
        return;
      }

      if (currentStep >= steps.length) return;
      if (!evento.target.matches('input, select, textarea')) return;

      evento.preventDefault();
      if (validateStep(currentStep)) showStep(currentStep + 1, 'forward');
    });

    form.addEventListener('submit', function (evento) {
      if (currentStep < steps.length) {
        evento.preventDefault();
        if (validateStep(currentStep)) showStep(currentStep + 1, 'forward');
        return;
      }

      if (!validateStep(currentStep)) evento.preventDefault();
    });
  });
})();
