/* Native Community Jeedom support for plugin desktop pages. */
(function () {
  'use strict';

  var selector = '[data-action="createCommunityPost"][data-plugin_id]';
  var button = document.querySelector(selector);
  if (!button) return;

  var apiAvailable = typeof jeedom !== 'undefined'
    && jeedom.plugin
    && typeof jeedom.plugin.createCommunityPost === 'function';

  if (!apiAvailable) {
    button.hidden = true;
    button.setAttribute('aria-hidden', 'true');
    return;
  }

  function setBusy(busy) {
    button.dataset.communityBusy = busy ? '1' : '0';
    button.setAttribute('aria-busy', busy ? 'true' : 'false');
    button.classList.toggle('disabled', busy);
  }

  function showError(error) {
    setBusy(false);
    var message = error && error.message
      ? error.message
      : '{{Impossible de préparer le sujet Community. Réessayez ultérieurement.}}';
    if (typeof jeedomUtils !== 'undefined' && typeof jeedomUtils.showAlert === 'function') {
      jeedomUtils.showAlert({ message: message, level: 'danger' });
    }
  }

  function openValidatedUrl(url) {
    setBusy(false);
    var link = document.createElement('a');
    link.href = url;
    link.target = '_blank';
    link.rel = 'noopener noreferrer';
    link.hidden = true;
    document.body.appendChild(link);
    link.click();
    link.remove();
  }

  function requestConsent(url) {
    var parsedUrl;
    try {
      parsedUrl = new URL(url);
    } catch (error) {
      showError({ message: '{{La réponse Community reçue est invalide.}}' });
      return;
    }
    var diagnostic = parsedUrl.searchParams.get('body') || '{{Aucun diagnostic disponible.}}';
    var warning = '{{Le sujet Community peut être public. Vérifiez les informations ci-dessous avant de continuer.}}';
    var optionalStart = '<!-- JEEDOM_PLUGIN_DIAGNOSTICS_START -->';
    var optionalEnd = '<!-- JEEDOM_PLUGIN_DIAGNOSTICS_END -->';
    var optionalStartIndex = diagnostic.indexOf(optionalStart);
    var optionalEndIndex = diagnostic.indexOf(optionalEnd);
    var hasOptionalDiagnostic = optionalStartIndex !== -1
      && optionalEndIndex > optionalStartIndex;
    var previewDiagnostic = diagnostic
      .replace(optionalStart, '')
      .replace(optionalEnd, '');

    if (typeof jeeDialog !== 'undefined' && typeof jeeDialog.confirm === 'function') {
      var preview = document.createElement('pre');
      preview.className = 'communityDiagnosticPreview';
      preview.textContent = previewDiagnostic;
      var optionalControl = hasOptionalDiagnostic
        ? '<label><input type="checkbox" id="communityIncludeOptionalDiagnostic" checked> {{Inclure le diagnostic facultatif du plugin}}</label>'
        : '';
      var includeOptionalDiagnostic = true;
      function handleOptionalDiagnosticChange(event) {
        if (event.target && event.target.id === 'communityIncludeOptionalDiagnostic') {
          includeOptionalDiagnostic = event.target.checked;
        }
      }
      if (hasOptionalDiagnostic) {
        document.addEventListener('change', handleOptionalDiagnosticChange);
      }
      jeeDialog.confirm('<strong>' + warning + '</strong>' + optionalControl + preview.outerHTML, function (confirmed) {
        if (hasOptionalDiagnostic) {
          document.removeEventListener('change', handleOptionalDiagnosticChange);
        }
        if (!confirmed) {
          setBusy(false);
          return;
        }

        var targetUrl = url;
        if (hasOptionalDiagnostic && !includeOptionalDiagnostic) {
          var filteredDiagnostic = diagnostic.slice(0, optionalStartIndex)
            + diagnostic.slice(optionalEndIndex + optionalEnd.length);
          parsedUrl.searchParams.set('body', filteredDiagnostic.trim());
          targetUrl = parsedUrl.toString();
        }
        openValidatedUrl(targetUrl);
      });
      return;
    }

    if (typeof confirm === 'function') {
      if (confirm(warning + '\n\n' + previewDiagnostic)) {
        openValidatedUrl(url);
      } else {
        setBusy(false);
      }
      return;
    }

    showError({ message: '{{Impossible d’afficher l’aperçu du diagnostic Community.}}' });
  }

  function openCommunityUrl(data) {
    if (!data || typeof data.url !== 'string' || !/^https:\/\/community\.jeedom\.com\//.test(data.url)) {
      showError({ message: '{{La réponse Community reçue est invalide.}}' });
      return;
    }
    requestConsent(data.url);
  }

  function createPost(event) {
    if (event) {
      event.preventDefault();
      event.stopPropagation();
    }
    if (button.dataset.communityBusy === '1') return;

    var pluginId = button.getAttribute('data-plugin_id');
    if (!pluginId) {
      showError({ message: '{{Identifiant du plugin indisponible.}}' });
      return;
    }

    setBusy(true);
    jeedom.plugin.createCommunityPost({
      type: pluginId,
      success: openCommunityUrl,
      error: showError
    });
  }

  button.addEventListener('click', createPost);
  button.addEventListener('keydown', function (event) {
    if (event.key === 'Enter' || event.key === ' ') createPost(event);
  });
}());
