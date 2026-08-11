(function () {
  'use strict';

  function setPasskeyStatus(message, isError) {
    document.querySelectorAll('[data-sauth-passkey-status]').forEach(function (node) {
      node.textContent = message || '';
      node.classList.toggle('sa-notice-error', !!isError);
    });
  }

  function passkeysSupported() {
    return !!(window.PublicKeyCredential && navigator.credentials && navigator.credentials.create && navigator.credentials.get);
  }

  function base64urlToBuffer(value) {
    var text = String(value || '').replace(/-/g, '+').replace(/_/g, '/');
    while (text.length % 4) {
      text += '=';
    }
    var binary = window.atob(text);
    var bytes = new Uint8Array(binary.length);
    for (var i = 0; i < binary.length; i += 1) {
      bytes[i] = binary.charCodeAt(i);
    }
    return bytes.buffer;
  }

  function bufferToBase64url(value) {
    if (!value) {
      return '';
    }
    var bytes = value instanceof Uint8Array ? value : new Uint8Array(value);
    var binary = '';
    for (var i = 0; i < bytes.byteLength; i += 1) {
      binary += String.fromCharCode(bytes[i]);
    }
    return window.btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
  }

  function ajaxConfig() {
    return window.SabriAuthPasskeys || {};
  }

  async function post(action, values) {
    var cfg = ajaxConfig();
    if (!cfg.ajaxUrl) {
      throw new Error('passkey_configuration_missing');
    }
    var body = new URLSearchParams();
    body.set('action', action);
    Object.keys(values || {}).forEach(function (key) {
      if (values[key] !== undefined && values[key] !== null) {
        body.set(key, String(values[key]));
      }
    });
    var response = await window.fetch(cfg.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body.toString()
    });
    var payload;
    try {
      payload = await response.json();
    } catch (error) {
      throw new Error('passkey_response_invalid');
    }
    if (!response.ok || !payload || payload.success !== true) {
      throw new Error(payload && payload.data && payload.data.code ? payload.data.code : 'passkey_request_failed');
    }
    return payload.data || {};
  }

  function normalizeCreationOptions(data) {
    var options = data.publicKey || {};
    options.challenge = base64urlToBuffer(options.challenge);
    if (options.user && options.user.id) {
      options.user.id = base64urlToBuffer(options.user.id);
    }
    if (Array.isArray(options.excludeCredentials)) {
      options.excludeCredentials = options.excludeCredentials.map(function (item) {
        item.id = base64urlToBuffer(item.id);
        return item;
      });
    }
    return options;
  }

  function normalizeRequestOptions(data) {
    var options = data.publicKey || {};
    options.challenge = base64urlToBuffer(options.challenge);
    if (Array.isArray(options.allowCredentials)) {
      options.allowCredentials = options.allowCredentials.map(function (item) {
        item.id = base64urlToBuffer(item.id);
        return item;
      });
    }
    return options;
  }

  function friendlyError(error) {
    var cfg = ajaxConfig();
    if (error && (error.name === 'NotAllowedError' || error.name === 'AbortError')) {
      return 'The passkey request was cancelled, timed out, or no matching passkey was available.';
    }
    if (error && error.name === 'InvalidStateError') {
      return 'This authenticator already has a matching passkey for the account.';
    }
    if (error && error.message === 'fresh_reauthentication_required') {
      return 'Enter the stronger account verification requested on this page before changing passkeys.';
    }
    if (error && error.message === 'rate_limited') {
      return 'Too many passkey attempts were made. Wait before trying again.';
    }
    return cfg.genericError || 'The passkey operation could not be completed safely.';
  }

  async function signInWithPasskey(button) {
    var cfg = ajaxConfig();
    if (!passkeysSupported() || !cfg.available) {
      setPasskeyStatus(cfg.unsupported || 'Passkeys are unavailable in this browser or connection.', true);
      return;
    }
    button.disabled = true;
    setPasskeyStatus('Waiting for your passkey…', false);
    try {
      var redirectField = document.querySelector('input[name="redirect_to"]');
      var rememberField = document.querySelector('input[name="rememberme"]');
      var begin = await post('sauth_passkey_begin_authentication', {
        redirect_to: redirectField ? redirectField.value : window.location.href
      });
      var credential = await navigator.credentials.get({ publicKey: normalizeRequestOptions(begin) });
      if (!credential || credential.type !== 'public-key' || !credential.response) {
        throw new Error('passkey_assertion_missing');
      }
      var finish = await post('sauth_passkey_finish_authentication', {
        challenge_id: begin.challengeId,
        raw_id: bufferToBase64url(credential.rawId),
        client_data_json: bufferToBase64url(credential.response.clientDataJSON),
        authenticator_data: bufferToBase64url(credential.response.authenticatorData),
        signature: bufferToBase64url(credential.response.signature),
        user_handle: credential.response.userHandle ? bufferToBase64url(credential.response.userHandle) : '',
        remember: rememberField && rememberField.checked ? '1' : '0'
      });
      setPasskeyStatus('Passkey verified. Opening your account…', false);
      window.location.assign(finish.redirect || '/');
    } catch (error) {
      setPasskeyStatus(friendlyError(error), true);
      button.disabled = false;
    }
  }

  async function registerPasskey(button) {
    var cfg = ajaxConfig();
    if (!passkeysSupported() || !cfg.available || !cfg.loggedIn) {
      setPasskeyStatus(cfg.unsupported || 'Passkey registration is unavailable.', true);
      return;
    }
    var password = document.getElementById('sauth-passkey-password');
    var stepUp = document.getElementById('sauth-passkey-stepup');
    var nickname = document.getElementById('sauth-passkey-name');
    button.disabled = true;
    setPasskeyStatus('Confirming your current account security…', false);
    try {
      var begin = await post('sauth_passkey_begin_registration', {
        nonce: cfg.nonce || '',
        current_password: password ? password.value : '',
        step_up_code: stepUp ? stepUp.value : ''
      });
      if (password) {
        password.value = '';
      }
      if (stepUp) {
        stepUp.value = '';
      }
      setPasskeyStatus('Use your device or security key to create the passkey…', false);
      var credential = await navigator.credentials.create({ publicKey: normalizeCreationOptions(begin) });
      if (!credential || credential.type !== 'public-key' || !credential.response || !credential.response.attestationObject) {
        throw new Error('passkey_attestation_unavailable');
      }
      var transports = typeof credential.response.getTransports === 'function' ? credential.response.getTransports() : [];
      var finish = await post('sauth_passkey_finish_registration', {
        nonce: cfg.nonce || '',
        challenge_id: begin.challengeId,
        raw_id: bufferToBase64url(credential.rawId),
        client_data_json: bufferToBase64url(credential.response.clientDataJSON),
        attestation_object: bufferToBase64url(credential.response.attestationObject),
        attachment: credential.authenticatorAttachment || '',
        transports: transports.join(','),
        nickname: nickname ? nickname.value : ''
      });
      setPasskeyStatus(finish.message || 'Passkey added successfully.', false);
      if (finish.reload) {
        window.setTimeout(function () { window.location.reload(); }, 500);
      }
    } catch (error) {
      setPasskeyStatus(friendlyError(error), true);
      button.disabled = false;
    }
  }

  async function revokePasskey(button) {
    var cfg = ajaxConfig();
    if (!cfg.loggedIn) {
      setPasskeyStatus('Sign in before changing passkeys.', true);
      return;
    }
    if (!window.confirm('Revoke this passkey? It will no longer be accepted for sign-in.')) {
      return;
    }
    var password = document.getElementById('sauth-passkey-password');
    var stepUp = document.getElementById('sauth-passkey-stepup');
    button.disabled = true;
    setPasskeyStatus('Revoking the selected passkey…', false);
    try {
      var result = await post('sauth_passkey_revoke', {
        nonce: cfg.nonce || '',
        credential_id: button.getAttribute('data-sauth-passkey-revoke') || '',
        current_password: password ? password.value : '',
        step_up_code: stepUp ? stepUp.value : ''
      });
      if (password) {
        password.value = '';
      }
      if (stepUp) {
        stepUp.value = '';
      }
      setPasskeyStatus(result.message || 'Passkey revoked.', false);
      if (result.reload) {
        window.setTimeout(function () { window.location.reload(); }, 500);
      }
    } catch (error) {
      setPasskeyStatus(friendlyError(error), true);
      button.disabled = false;
    }
  }

  document.addEventListener('click', function (event) {
    var toggle = event.target.closest('.sa-show-password');
    if (toggle) {
      var id = toggle.getAttribute('aria-controls');
      var field = document.getElementById(id);
      if (field) {
        var reveal = field.type === 'password';
        field.type = reveal ? 'text' : 'password';
        toggle.textContent = reveal ? 'Hide' : 'Show';
        toggle.setAttribute('aria-pressed', reveal ? 'true' : 'false');
      }
      return;
    }

    var login = event.target.closest('[data-sauth-passkey-login]');
    if (login) {
      event.preventDefault();
      signInWithPasskey(login);
      return;
    }

    var register = event.target.closest('[data-sauth-passkey-register]');
    if (register) {
      event.preventDefault();
      registerPasskey(register);
      return;
    }

    var revoke = event.target.closest('[data-sauth-passkey-revoke]');
    if (revoke) {
      event.preventDefault();
      revokePasskey(revoke);
      return;
    }

    var gated = event.target.closest('[data-sa-auth-required]');
    if (gated && window.SabriAuth && !window.SabriAuth.loggedIn) {
      event.preventDefault();
      window.location.href = window.SabriAuth.loginUrl;
    }
  });

  document.addEventListener('DOMContentLoaded', function () {
    var cfg = ajaxConfig();
    if (document.querySelector('[data-sauth-passkey-login], [data-sauth-passkey-register]') && (!passkeysSupported() || !cfg.available)) {
      setPasskeyStatus(cfg.unsupported || 'Passkeys are unavailable in this browser or connection.', true);
      document.querySelectorAll('[data-sauth-passkey-login], [data-sauth-passkey-register]').forEach(function (button) {
        button.disabled = true;
      });
    }
  });
})();
