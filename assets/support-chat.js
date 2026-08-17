(function () {
  if (window.__supportChatLoaded) {
    return;
  }
  window.__supportChatLoaded = true;

  function parseConfig(widget) {
    if (!widget || !widget.dataset) {
      return null;
    }

    return {
      threadUrl: widget.dataset.threadUrl || '',
      pollUrl: widget.dataset.pollUrl || '',
      startUrl: widget.dataset.startUrl || '',
      messageUrl: widget.dataset.messageUrl || '',
      isAuthenticated: widget.dataset.isAuthenticated === '1',
      pageUrl: widget.dataset.pageUrl || window.location.href,
      pageTitle: widget.dataset.pageTitle || document.title,
      labels: {
        requestFailed: widget.dataset.requestFailed || 'Request failed'
      }
    };
  }

  function getToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return (meta ? meta.getAttribute('content') : '')
      || (document.querySelector('input[name="_token"]') ? document.querySelector('input[name="_token"]').value : '');
  }

  function requestJson(url, options) {
    return fetch(url, options).then(function (response) {
      return response.json().then(function (payload) {
        if (!response.ok) {
          throw new Error((payload && payload.message) ? payload.message : 'Request failed');
        }
        return payload;
      });
    });
  }

  function initWidget(widget) {
    if (widget.dataset.supportChatInitialized === '1') {
      return;
    }
    widget.dataset.supportChatInitialized = '1';

    var config = parseConfig(widget);
    if (!config) {
      return;
    }

    var toggle = widget.querySelector('[data-support-chat-toggle]');
    var closeButton = widget.querySelector('[data-support-chat-close]');
    var panel = widget.querySelector('.support-chat-widget__panel');
    var form = widget.querySelector('[data-support-chat-form]');
    var messages = widget.querySelector('[data-support-chat-messages]');
    var errorNode = widget.querySelector('[data-support-chat-error]');
    var guestFields = widget.querySelector('[data-support-chat-guest-fields]');
    var currentThread = null;
    var latestId = 0;
    var pollTimer = null;
    var isSending = false;

    function threadHost() {
      return messages.querySelector('.support-chat-widget__thread') || messages;
    }

    function setOpen(open) {
      if (!panel || !toggle) {
        return;
      }
      panel.hidden = !open;
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (open) {
        messages.scrollTop = messages.scrollHeight;
      }
    }

    function setError(message) {
      if (errorNode) {
        errorNode.textContent = message || '';
      }
    }

    function updateGuestFields() {
      if (!guestFields) {
        return;
      }
      guestFields.hidden = !!currentThread || !!config.isAuthenticated;
    }

    function renderFull(html) {
      messages.innerHTML = html || '';
      latestId = 0;
      Array.prototype.slice.call(messages.querySelectorAll('[data-message-id]')).forEach(function (node) {
        latestId = Math.max(latestId, parseInt(node.getAttribute('data-message-id') || '0', 10));
      });
      messages.scrollTop = messages.scrollHeight;
    }

    function appendItems(html) {
      if (!html) {
        return;
      }
      var host = threadHost();
      var container = document.createElement('div');
      container.innerHTML = html;
      Array.prototype.slice.call(container.children).forEach(function (child) {
        var id = child.getAttribute ? child.getAttribute('data-message-id') : null;
        if (id) {
          latestId = Math.max(latestId, parseInt(id, 10));
          // UI Deduplication: skip if already in DOM
          if (messages.querySelector('[data-message-id="' + id + '"]')) {
            return;
          }
        }
        host.appendChild(child);
      });
      messages.scrollTop = messages.scrollHeight;
    }

    function fetchThread() {
      requestJson(config.threadUrl, {
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      })
        .then(function (payload) {
          currentThread = payload.thread || null;
          updateGuestFields();
          renderFull(payload.html || '');
          if (payload.latest_id) {
            latestId = parseInt(payload.latest_id || '0', 10) || latestId;
          }
        })
        .catch(function () {});
    }

    function poll() {
      if (!currentThread || isSending) {
        return;
      }

      var url = new URL(config.pollUrl, window.location.origin);
      url.searchParams.set('after_id', String(latestId || 0));

      requestJson(url.toString(), {
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      })
        .then(function (payload) {
          if (!payload || !payload.count) {
            return;
          }
          appendItems(payload.html || '');
        })
        .catch(function () {});
    }

    if (toggle) {
      toggle.addEventListener('click', function () {
        setOpen(panel.hidden);
      });
    }

    if (closeButton) {
      closeButton.addEventListener('click', function () {
        setOpen(false);
      });
    }

    var submitBtn = form ? form.querySelector('button[type="submit"]') : null;
    var originalBtnText = submitBtn ? submitBtn.textContent : '';

    if (form) {
      form.addEventListener('submit', function (event) {
        event.preventDefault();
        if (isSending) {
          return;
        }

        setError('');

        var formData = new FormData(form);
        var message = (formData.get('message') || '').toString().trim();
        if (!message) {
          return;
        }

        isSending = true;
        if (submitBtn) {
          submitBtn.disabled = true;
          submitBtn.textContent = '...';
        }

        var actionUrl = currentThread ? config.messageUrl : config.startUrl;
        formData.append('latest_id', String(latestId || 0));
        if (!currentThread) {
          formData.append('page_url', config.pageUrl || window.location.href);
          formData.append('page_title', config.pageTitle || document.title);
        }

        requestJson(actionUrl, {
          method: 'POST',
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': getToken()
          },
          body: formData
        })
          .then(function (payload) {
            currentThread = payload.thread || currentThread;
            updateGuestFields();
            if (currentThread && payload.html && !messages.querySelector('[data-message-id]')) {
              renderFull(payload.html);
            } else if (currentThread && payload.html && actionUrl === config.startUrl) {
              renderFull(payload.html);
            } else {
              appendItems(payload.html || '');
            }
            form.reset();
            updateGuestFields();
          })
          .catch(function (error) {
            setError(error && error.message ? error.message : (config.labels.requestFailed || 'Request failed'));
          })
          .finally(function () {
            isSending = false;
            if (submitBtn) {
              submitBtn.disabled = false;
              submitBtn.textContent = originalBtnText;
            }
          });
      });
    }

    updateGuestFields();
    fetchThread();
    pollTimer = window.setInterval(poll, 5000);

    window.addEventListener('beforeunload', function () {
      if (pollTimer) {
        window.clearInterval(pollTimer);
      }
    });
  }

  function bootstrap() {
    var widgets = Array.prototype.slice.call(document.querySelectorAll('[data-support-chat-widget]'));
    if (widgets.length > 1) {
      // Remove any duplicate widget nodes in the DOM
      for (var i = 1; i < widgets.length; i++) {
        widgets[i].remove();
      }
    }
    if (widgets.length > 0) {
      initWidget(widgets[0]);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootstrap);
  } else {
    bootstrap();
  }
})();
