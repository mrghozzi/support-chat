/**
 * Support Chat - Admin Interactivity & AI Tools
 * Handles live polling, AI suggestions, provider tests, and status switching
 */

(function () {
  var csrfToken = (window.SUPPORT_CHAT_CONFIG && window.SUPPORT_CHAT_CONFIG.csrfToken)
    || (document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').getAttribute('content') : '');

  var messagePane = document.getElementById('support-chat-admin-messages');
  var replyForm = document.querySelector('[data-support-chat-reply-form]');
  var assignForm = document.querySelector('[data-support-chat-assign-form]');
  var statusGroup = document.querySelector('[data-support-chat-status-group]');
  var statusLabel = document.querySelector('[data-thread-status-label]');
  var btnAiSuggest = document.getElementById('btn-ai-suggest');
  var aiSuggestionBox = document.getElementById('ai-suggestion-box');
  var aiSuggestionText = document.getElementById('ai-suggestion-text');
  var btnApplySuggestion = document.getElementById('btn-apply-suggestion');
  var btnDismissSuggestion = document.getElementById('btn-dismiss-suggestion');

  var latestId = messagePane ? parseInt(messagePane.getAttribute('data-latest-id') || '0', 10) : 0;
  var pollTimer = null;

  function getToken() {
    return csrfToken
      || (document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').getAttribute('content') : '')
      || (document.querySelector('input[name="_token"]') ? document.querySelector('input[name="_token"]').value : '');
  }

  function postData(url, data) {
    return fetch(url, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': getToken()
      },
      body: data instanceof FormData ? data : JSON.stringify(data)
    }).then(function (response) {
      return response.json().then(function (payload) {
        if (!response.ok) {
          throw new Error((payload && payload.message) ? payload.message : 'Request failed (' + response.status + ')');
        }
        return payload;
      });
    });
  }

  function host() {
    return messagePane ? (messagePane.querySelector('.support-chat-transcript__inner') || messagePane) : null;
  }

  function appendItems(html) {
    var inner = host();
    if (!inner || !html) {
      return;
    }

    var empty = inner.querySelector('.support-chat-empty');
    if (empty) {
      empty.remove();
    }

    var container = document.createElement('div');
    container.innerHTML = html;
    Array.prototype.slice.call(container.children).forEach(function (child) {
      if (child.getAttribute && child.getAttribute('data-message-id')) {
        latestId = Math.max(latestId, parseInt(child.getAttribute('data-message-id') || '0', 10));
      }
      inner.appendChild(child);
    });

    messagePane.setAttribute('data-latest-id', String(latestId || 0));
    messagePane.scrollTop = messagePane.scrollHeight;
  }

  // --- Live Polling & Chat Messaging ---
  if (replyForm && messagePane) {
    var textarea = replyForm.querySelector('textarea[name="message"]');
    var errorNode = replyForm.querySelector('[data-support-chat-error]');
    var pollUrl = replyForm.getAttribute('data-poll-url');

    function setError(msg) {
      if (errorNode) {
        errorNode.textContent = msg || '';
      }
    }

    function poll() {
      if (!pollUrl) return;

      var url = new URL(pollUrl, window.location.origin);
      url.searchParams.set('after_id', String(latestId || 0));

      fetch(url.toString(), {
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      })
        .then(function (res) {
          if (!res.ok) throw new Error('Poll failed');
          return res.json();
        })
        .then(function (payload) {
          if (!payload || payload.success !== true || !payload.count) return;
          appendItems(payload.html || '');
        })
        .catch(function () {});
    }

    replyForm.addEventListener('submit', function (e) {
      e.preventDefault();
      setError('');

      var text = textarea ? textarea.value.trim() : '';
      if (!text) return;

      var data = new FormData(replyForm);
      var submitBtn = replyForm.querySelector('button[type="submit"]');
      if (submitBtn) submitBtn.disabled = true;

      postData(replyForm.getAttribute('action'), data)
        .then(function (payload) {
          appendItems(payload.html || '');
          if (textarea) textarea.value = '';
          if (statusLabel && payload.status_label) {
            statusLabel.textContent = payload.status_label;
          }
        })
        .catch(function (err) {
          setError(err && err.message ? err.message : 'Error sending message');
        })
        .finally(function () {
          if (submitBtn) submitBtn.disabled = false;
        });
    });

    // Ctrl+Enter / Cmd+Enter Shortcut
    if (textarea) {
      textarea.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
          e.preventDefault();
          replyForm.dispatchEvent(new Event('submit'));
        }
      });
    }

    messagePane.scrollTop = messagePane.scrollHeight;
    pollTimer = window.setInterval(poll, 4000);
  }

  // --- AI Suggest Reply Co-Pilot ---
  if (btnAiSuggest && aiSuggestionBox && aiSuggestionText) {
    btnAiSuggest.addEventListener('click', function () {
      var actionUrl = btnAiSuggest.getAttribute('data-action');
      if (!actionUrl) return;

      var origHtml = btnAiSuggest.innerHTML;
      btnAiSuggest.disabled = true;
      btnAiSuggest.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> جاري التوليد...';

      postData(actionUrl, new FormData())
        .then(function (payload) {
          if (payload && payload.success && payload.suggestion) {
            aiSuggestionText.textContent = payload.suggestion;
            aiSuggestionBox.classList.remove('d-none');
          } else {
            alert((payload && payload.message) ? payload.message : 'تعذر توليد الاقتراح.');
          }
        })
        .catch(function (err) {
          alert('خطأ أثناء الاتصال بالذكاء الاصطناعي: ' + err.message);
        })
        .finally(function () {
          btnAiSuggest.disabled = false;
          btnAiSuggest.innerHTML = origHtml;
        });
    });

    if (btnApplySuggestion && textarea) {
      btnApplySuggestion.addEventListener('click', function () {
        textarea.value = aiSuggestionText.textContent.trim();
        textarea.focus();
        aiSuggestionBox.classList.add('d-none');
      });
    }

    if (btnDismissSuggestion) {
      btnDismissSuggestion.addEventListener('click', function () {
        aiSuggestionBox.classList.add('d-none');
      });
    }
  }

  // --- Provider Live Connection Test ---
  document.querySelectorAll('.btn-test-ai').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var provider = btn.getAttribute('data-provider');
      var keySelector = btn.getAttribute('data-key-input');
      var modelSelector = btn.getAttribute('data-model-input');

      var keyInput = document.querySelector(keySelector);
      var modelInput = document.querySelector(modelSelector);

      var apiKey = keyInput ? keyInput.value.trim() : '';
      var model = modelInput ? modelInput.value.trim() : '';

      var card = btn.closest('.provider-card');
      var resultBox = card ? card.querySelector('.test-result-box') : null;

      var origHtml = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> فحص...';

      if (resultBox) {
        resultBox.classList.remove('d-none');
        resultBox.innerHTML = '<div class="alert alert-info py-2 px-3 small mb-0 rounded-3"><span class="spinner-border spinner-border-sm me-1"></span> جاري فحص الاتصال بالمزود ' + provider + '...</div>';
      }

      var testUrl = window.SUPPORT_CHAT_CONFIG ? window.SUPPORT_CHAT_CONFIG.testAiUrl : '/admin/support-chat/test-ai';
      var formData = new FormData();
      formData.append('provider', provider);
      formData.append('api_key', apiKey);
      formData.append('model', model);

      postData(testUrl, formData)
        .then(function (res) {
          if (resultBox) {
            if (res.success) {
              resultBox.innerHTML = '<div class="alert alert-success py-2 px-3 small mb-0 rounded-3">'
                + '<div class="fw-bold mb-1"><i class="feather-check-circle me-1"></i> ' + (res.message || 'متصل بنجاح') + '</div>'
                + '<div class="text-muted fs-11 fst-italic">"' + (res.sample_reply || '') + '"</div>'
                + '</div>';
            } else {
              resultBox.innerHTML = '<div class="alert alert-danger py-2 px-3 small mb-0 rounded-3">'
                + '<i class="feather-x-circle me-1"></i> ' + (res.message || 'فشل الاتصال بالمزود')
                + '</div>';
            }
          }
        })
        .catch(function (err) {
          if (resultBox) {
            resultBox.innerHTML = '<div class="alert alert-danger py-2 px-3 small mb-0 rounded-3">'
              + '<i class="feather-alert-triangle me-1"></i> خطأ: ' + err.message
              + '</div>';
          }
        })
        .finally(function () {
          btn.disabled = false;
          btn.innerHTML = origHtml;
        });
    });
  });

  // --- Show / Hide Password Toggles ---
  document.querySelectorAll('.btn-toggle-pw').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = btn.closest('.input-group') ? btn.closest('.input-group').querySelector('input') : null;
      if (!input) return;

      var icon = btn.querySelector('i');
      if (input.type === 'password') {
        input.type = 'text';
        if (icon) {
          icon.className = 'feather-eye-off';
        }
      } else {
        input.type = 'password';
        if (icon) {
          icon.className = 'feather-eye';
        }
      }
    });
  });

  // --- Assignee Change Handler ---
  if (assignForm) {
    assignForm.addEventListener('change', function () {
      var data = new FormData(assignForm);
      postData(assignForm.getAttribute('action'), data).catch(function () {});
    });
  }

  // --- Status Switcher ---
  if (statusGroup) {
    statusGroup.addEventListener('click', function (event) {
      var button = event.target.closest('[data-status]');
      if (!button) return;

      var data = new FormData();
      data.append('status', button.getAttribute('data-status') || '');
      postData(statusGroup.getAttribute('data-action'), data)
        .then(function () {
          Array.prototype.slice.call(statusGroup.querySelectorAll('[data-status]')).forEach(function (item) {
            item.classList.remove('btn-primary');
            item.classList.add('btn-light');
          });
          button.classList.remove('btn-light');
          button.classList.add('btn-primary');
          if (statusLabel) {
            statusLabel.textContent = button.textContent.trim();
            statusLabel.className = 'badge status-badge status-' + (button.getAttribute('data-status') || 'open');
          }
        })
        .catch(function () {});
    });
  }

  window.addEventListener('beforeunload', function () {
    if (pollTimer) {
      window.clearInterval(pollTimer);
    }
  });
})();
