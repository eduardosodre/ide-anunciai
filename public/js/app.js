(function () {
  'use strict';

  function basePath() {
    return document.documentElement.getAttribute('data-base') || '';
  }

  function apiUrl(path) {
    if (path.charAt(0) !== '/') {
      path = '/' + path;
    }
    return basePath() + path;
  }

  function clearFormErrors(form) {
    form.querySelectorAll('.field-error').forEach(function (el) {
      el.textContent = '';
    });
    form.querySelectorAll('.form-global-error').forEach(function (el) {
      el.textContent = '';
    });
    form.querySelectorAll('.invalid').forEach(function (el) {
      el.classList.remove('invalid');
    });
  }

  function showFieldErrors(form, fields) {
    if (!fields || typeof fields !== 'object') {
      return;
    }
    Object.keys(fields).forEach(function (key) {
      var msg = fields[key];
      if (typeof msg !== 'string') {
        msg = String(msg);
      }
      var err = form.querySelector('[data-error-for="' + key + '"]');
      var input = form.querySelector('[name="' + key + '"]');
      if (err) {
        err.textContent = msg;
      }
      if (input) {
        input.classList.add('invalid');
      }
    });
  }

  function setLoading(button, loading) {
    if (!button) {
      return;
    }
    button.disabled = !!loading;
    var label = button.querySelector('.btn-label');
    var spin = button.querySelector('.btn-spinner');
    if (label) {
      label.hidden = !!loading;
    }
    if (spin) {
      spin.hidden = !loading;
    }
  }

  function validatePasswordClient(pw) {
    var errs = [];
    if (!pw || pw.length < 8) {
      errs.push('Mínimo 8 caracteres.');
    }
    if (pw && !/[A-Z]/.test(pw)) {
      errs.push('Uma letra maiúscula.');
    }
    if (pw && !/[a-z]/.test(pw)) {
      errs.push('Uma letra minúscula.');
    }
    if (pw && !/[0-9]/.test(pw)) {
      errs.push('Um número.');
    }
    return errs;
  }

  async function fetchJson(method, path, body) {
    var opts = {
      method: method,
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
      },
    };
    if (body !== undefined && body !== null) {
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(body);
    }
    var res = await fetch(apiUrl(path), opts);
    var text = await res.text();
    var data = null;
    try {
      data = text ? JSON.parse(text) : null;
    } catch (e) {
      throw new Error('Resposta inválida do servidor.');
    }
    return { ok: res.ok, status: res.status, data: data };
  }

  function initRegister() {
    var form = document.getElementById('form-register');
    if (!form) {
      return;
    }
    form.addEventListener('submit', async function (e) {
      e.preventDefault();
      clearFormErrors(form);
      var nome = (form.querySelector('[name="nome"]') || {}).value || '';
      var email = (form.querySelector('[name="email"]') || {}).value || '';
      var senha = (form.querySelector('[name="senha"]') || {}).value || '';
      var senha2 = (form.querySelector('[name="senha_confirmacao"]') || {}).value || '';
      var consent = !!(form.querySelector('[name="consentimento"]') || {}).checked;

      var clientErr = {};
      if (!nome.trim()) {
        clientErr.nome = 'Informe o nome.';
      }
      if (!email.trim()) {
        clientErr.email = 'Informe o e-mail.';
      }
      if (senha !== senha2) {
        clientErr.senha_confirmacao = 'As senhas não conferem.';
      }
      var pwErrs = validatePasswordClient(senha);
      if (pwErrs.length) {
        clientErr.senha = pwErrs.join(' ');
      }
      if (!consent) {
        clientErr.consentimento = 'É necessário aceitar a Política de Privacidade.';
      }
      if (Object.keys(clientErr).length) {
        showFieldErrors(form, clientErr);
        return;
      }

      var btn = form.querySelector('button[type="submit"]');
      setLoading(btn, true);
      try {
        var r = await fetchJson('POST', '/api/auth/register', {
          nome: nome.trim(),
          email: email.trim(),
          senha: senha,
          senha_confirmacao: senha2,
          consentimento: consent ? 1 : 0,
        });
        if (r.ok && r.data && r.data.id) {
          window.location.href = apiUrl('/conta');
          return;
        }
        if (r.data && r.data.error && r.data.error.fields) {
          showFieldErrors(form, r.data.error.fields);
        } else {
          var ge = form.querySelector('#form-register-global');
          if (ge) {
            ge.textContent =
              (r.data && r.data.error && r.data.error.message) || 'Não foi possível cadastrar.';
          }
        }
      } catch (err) {
        var g = form.querySelector('#form-register-global');
        if (g) {
          g.textContent = err.message || 'Erro de rede.';
        }
      } finally {
        setLoading(btn, false);
      }
    });
  }

  function initLogin() {
    var form = document.getElementById('form-login');
    if (!form) {
      return;
    }
    form.addEventListener('submit', async function (e) {
      e.preventDefault();
      clearFormErrors(form);
      var email = (form.querySelector('[name="email"]') || {}).value || '';
      var senha = (form.querySelector('[name="senha"]') || {}).value || '';
      if (!email.trim()) {
        showFieldErrors(form, { email: 'Informe o e-mail.' });
        return;
      }
      if (!senha) {
        showFieldErrors(form, { senha: 'Informe a senha.' });
        return;
      }
      var btn = form.querySelector('button[type="submit"]');
      setLoading(btn, true);
      try {
        var r = await fetchJson('POST', '/api/auth/login', {
          email: email.trim(),
          senha: senha,
        });
        if (r.ok && r.data && r.data.id) {
          window.location.href = apiUrl('/conta');
          return;
        }
        var ge = form.querySelector('#form-login-global');
        var msg =
          (r.data && r.data.error && r.data.error.message) || 'Credenciais inválidas.';
        if (ge) {
          ge.textContent = msg;
        }
      } catch (err) {
        var g = form.querySelector('#form-login-global');
        if (g) {
          g.textContent = err.message || 'Erro de rede.';
        }
      } finally {
        setLoading(btn, false);
      }
    });
  }

  function formatKm(km) {
    if (km === undefined || km === null || isNaN(Number(km))) {
      return '';
    }
    return (
      Number(km).toLocaleString('pt-BR', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 1,
      }) + ' km'
    );
  }

  function renderBuscaResults(data) {
    var meta = data.meta || {};
    var centro = meta.centro || {};
    var items = data.items || [];
    var html = '';
    html += '<div class="card"><p><strong>' + escapeHtml(String(meta.message || '')) + '</strong></p>';
    if (centro.label) {
      html +=
        '<p class="field-hint" style="margin:0.35rem 0 0.75rem">Centro da busca: <strong>' +
        escapeHtml(String(centro.label)) +
        '</strong>' +
        (centro.uf ? ' (UF ' + escapeHtml(String(centro.uf)) + ')' : '') +
        '</p>';
    }
    if (items.length === 0) {
      html += '<p>Nenhum perfil encontrado neste raio. Tente ampliar o km ou mudar o local.</p></div>';
      return html;
    }
    var totalShown = meta.total !== undefined ? meta.total : items.length;
    html +=
      '<p class="busca-summary" role="status">' +
      String(totalShown) +
      (totalShown === 1 ? ' resultado' : ' resultados') +
      ' (ordenado por distância)</p>';
    html += '<ul class="busca-result-list" role="list">';
    items.forEach(function (item) {
      var tipo = String(item.tipo || '');
      var tagClass = tipo === 'profissional' ? 'busca-tag--ministro' : 'busca-tag--igreja';
      var tipoLabel = escapeHtml(
        String(item.tipo_label || (tipo === 'profissional' ? 'Ministro' : 'Igreja'))
      );
      var verified = item.verificado
        ? ' <span class="badge-verified">Verificado</span>'
        : '';
      var distStr = formatKm(item.distancia_km);
      var loc = escapeHtml(String(item.cidade || ''));
      if (item.estado) {
        loc += ' · ' + escapeHtml(String(item.estado));
      }
      var metaLine =
        '<strong>' +
        loc +
        '</strong>' +
        (distStr ? ' · aprox. ' + escapeHtml(distStr) + ' do centro da busca' : '');
      html += '<li class="busca-result" role="listitem">';
      html += '<div class="busca-result-head">';
      html += '<span class="busca-tag ' + tagClass + '">' + tipoLabel + '</span>';
      html +=
        '<a class="busca-result-title" href="' +
        escapeAttr(String(item.url || '#')) +
        '">' +
        escapeHtml(String(item.nome || '')) +
        '</a>';
      html += verified;
      html += '</div>';
      html += '<p class="busca-result-meta">' + metaLine + '</p>';
      if (tipo === 'profissional' && item.habilidades && item.habilidades.length) {
        var skills = item.habilidades;
        var maxShow = 5;
        var shown = skills.slice(0, maxShow);
        html += '<div class="busca-skills" aria-label="Habilidades">';
        shown.forEach(function (s) {
          html +=
            '<span class="busca-skill-pill">' + escapeHtml(String(s.label || '')) + '</span>';
        });
        if (skills.length > maxShow) {
          html +=
            '<span class="busca-skill-pill">+' +
            (skills.length - maxShow) +
            '</span>';
        }
        html += '</div>';
      }
      if (tipo === 'igreja') {
        html +=
          '<p class="busca-igreja-hint">Perfil institucional — abra para ver dados públicos e iniciar conversa.</p>';
      } else if (tipo === 'profissional' && (!item.habilidades || !item.habilidades.length)) {
        html +=
          '<p class="busca-igreja-hint">Ministro — abra o perfil para ver habilidades completas.</p>';
      }
      html += '</li>';
    });
    html += '</ul></div>';
    return html;
  }

  function escapeHtml(s) {
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  }

  function escapeAttr(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/"/g, '&quot;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }

  function initBusca() {
    var form = document.getElementById('form-busca');
    var out = document.getElementById('busca-results');
    if (!form || !out) {
      return;
    }

    async function runSearch() {
      var fd = new FormData(form);
      var params = new URLSearchParams();
      if (fd.get('cep')) {
        params.set('cep', String(fd.get('cep')).trim());
      }
      if (fd.get('cidade')) {
        params.set('cidade', String(fd.get('cidade')).trim());
      }
      var est = fd.get('estado');
      if (est) {
        params.set('estado', String(est).trim());
      }
      params.set('raio_km', String(fd.get('raio_km') || '20'));
      params.set('tipo', String(fd.get('tipo') || 'ambos'));
      var hid = fd.get('habilidade_id');
      if (hid) {
        params.set('habilidade_id', String(hid));
      }
      params.set('pagina', '1');
      params.set('limite', '20');

      if (!params.get('cep') && !params.get('cidade')) {
        out.innerHTML =
          '<div class="card"><p>Informe CEP ou cidade para buscar perfis por raio.</p></div>';
        return;
      }

      out.innerHTML = '<div class="busca-loading">Buscando…</div>';
      var btn = form.querySelector('button[type="submit"]');
      setLoading(btn, true);
      try {
        var r = await fetch(apiUrl('/api/search?' + params.toString()), {
          credentials: 'same-origin',
          headers: { Accept: 'application/json' },
        });
        var text = await r.text();
        var data = JSON.parse(text);
        if (!r.ok) {
          out.innerHTML =
            '<div class="card"><p class="form-global-error">Erro ao buscar.</p></div>';
          return;
        }
        out.innerHTML = renderBuscaResults(data);
        if (params.get('cep') || params.get('cidade')) {
          history.replaceState(null, '', window.location.pathname + '?' + params.toString());
        }
      } catch (err) {
        out.innerHTML =
          '<div class="card"><p class="form-global-error">' +
          escapeHtml(err.message || 'Erro de rede.') +
          '</p></div>';
      } finally {
        setLoading(btn, false);
      }
    }

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      runSearch();
    });

    var qs = new URLSearchParams(window.location.search);
    if (qs.get('cep') || qs.get('cidade')) {
      if (form.querySelector('[name="cep"]')) {
        form.querySelector('[name="cep"]').value = qs.get('cep') || '';
      }
      if (form.querySelector('[name="cidade"]')) {
        form.querySelector('[name="cidade"]').value = qs.get('cidade') || '';
      }
      if (qs.get('raio_km') && form.querySelector('[name="raio_km"]')) {
        form.querySelector('[name="raio_km"]').value = qs.get('raio_km');
      }
      if (qs.get('tipo') && form.querySelector('[name="tipo"]')) {
        form.querySelector('[name="tipo"]').value = qs.get('tipo');
      }
      if (qs.get('habilidade_id') && form.querySelector('[name="habilidade_id"]')) {
        form.querySelector('[name="habilidade_id"]').value = qs.get('habilidade_id');
      }
      if (qs.get('estado') && form.querySelector('[name="estado"]')) {
        form.querySelector('[name="estado"]').value = qs.get('estado');
      }
      runSearch();
    } else {
      out.innerHTML =
        '<div class="card"><p>Informe CEP ou cidade para buscar perfis por raio.</p></div>';
    }
  }

  function initRecuperar() {
    var form = document.getElementById('form-recuperar');
    if (!form) {
      return;
    }
    form.addEventListener('submit', async function (e) {
      e.preventDefault();
      clearFormErrors(form);
      var email = (form.querySelector('[name="email"]') || {}).value || '';
      if (!email.trim()) {
        showFieldErrors(form, { email: 'Informe o e-mail.' });
        return;
      }
      var btn = form.querySelector('button[type="submit"]');
      var ge = form.querySelector('#form-recuperar-global');
      setLoading(btn, true);
      if (ge) {
        ge.textContent = '';
        ge.style.display = 'none';
      }
      try {
        var r = await fetchJson('POST', '/api/auth/password/forgot', {
          email: email.trim(),
        });
        if (r.ok) {
          if (ge) {
            ge.className = 'form-msg-success';
            ge.style.display = 'block';
            ge.textContent =
              'Se o e-mail existir em nossa base, enviaremos instruções em instantes.';
          }
        } else {
          if (ge) {
            ge.style.display = 'block';
            ge.className = 'form-global-error';
            ge.textContent = 'Não foi possível enviar. Tente novamente.';
          }
        }
      } catch (err) {
        if (ge) {
          ge.style.display = 'block';
          ge.className = 'form-global-error';
          ge.textContent = err.message || 'Erro de rede.';
        }
      } finally {
        setLoading(btn, false);
      }
    });
  }

  function initRedefinir() {
    var form = document.getElementById('form-redefinir');
    if (!form) {
      return;
    }
    form.addEventListener('submit', async function (e) {
      e.preventDefault();
      clearFormErrors(form);
      var token = (form.querySelector('[name="token"]') || {}).value || '';
      var senha = (form.querySelector('[name="nova_senha"]') || {}).value || '';
      var senha2 = (form.querySelector('[name="nova_senha_confirmacao"]') || {}).value || '';
      if (senha !== senha2) {
        showFieldErrors(form, { nova_senha_confirmacao: 'As senhas não conferem.' });
        return;
      }
      var pwErrs = validatePasswordClient(senha);
      if (pwErrs.length) {
        showFieldErrors(form, { nova_senha: pwErrs.join(' ') });
        return;
      }
      var btn = form.querySelector('button[type="submit"]');
      setLoading(btn, true);
      try {
        var r = await fetchJson('POST', '/api/auth/password/reset', {
          token: token,
          nova_senha: senha,
          nova_senha_confirmacao: senha2,
        });
        if (r.ok && r.data && (r.data.ok === true || r.data.ok === 1)) {
          window.location.href = apiUrl('/login');
          return;
        }
        if (r.data && r.data.error && r.data.error.fields) {
          showFieldErrors(form, r.data.error.fields);
        } else {
          var ge = form.querySelector('#form-redefinir-global');
          if (ge) {
            ge.textContent =
              (r.data && r.data.error && r.data.error.message) ||
              'Não foi possível redefinir.';
          }
        }
      } catch (err) {
        var g = form.querySelector('#form-redefinir-global');
        if (g) {
          g.textContent = err.message || 'Erro de rede.';
        }
      } finally {
        setLoading(btn, false);
      }
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    initRegister();
    initLogin();
    initBusca();
    initRecuperar();
    initRedefinir();
  });
})();
