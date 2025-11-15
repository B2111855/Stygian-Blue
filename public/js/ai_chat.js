(function () {
  const widget = document.querySelector('[data-ai-chat-widget]');
  if (!widget) {
    return;
  }

  const endpoint = widget.dataset.endpoint;
  if (!endpoint) {
    console.error('AI chat endpoint is not configured.');
    return;
  }

  const toggleButton = widget.querySelector('.sb-ai-chat-toggle');
  const chatWindow = widget.querySelector('.sb-ai-chat-window');
  const closeButton = widget.querySelector('[data-ai-chat-close]');
  const tabs = Array.from(widget.querySelectorAll('.sb-ai-tab'));
  const panels = Array.from(widget.querySelectorAll('.sb-ai-panel'));
  const aiPanel = widget.querySelector('[data-panel="assistant"]');
  const messageList = aiPanel ? aiPanel.querySelector('.sb-ai-chat-body') : null;
  const typingLine = aiPanel ? aiPanel.querySelector('.sb-ai-typing') : null;
  const form = aiPanel ? aiPanel.querySelector('form') : null;
  const textarea = aiPanel ? aiPanel.querySelector('textarea') : null;
  const sendButton = aiPanel ? aiPanel.querySelector('[data-ai-chat-send]') : null;
  const quickSwitchButtons = Array.from(widget.querySelectorAll('[data-switch-tab]'));
  const copyButtons = Array.from(widget.querySelectorAll('[data-copy]'));
  const linkButtons = Array.from(widget.querySelectorAll('[data-open-link]'));

  if (!toggleButton || !chatWindow || !aiPanel || !messageList || !form || !textarea || !sendButton) {
    console.error('AI chat widget is missing required markup.');
    return;
  }

  const TAB_STORAGE_KEY = 'sb_ai_chat_active_tab';
  const SESSION_STORAGE_KEY = 'sb_ai_chat_session';
  const defaultTab = widget.dataset.defaultTab || 'assistant';
  const validTabs = new Set(panels.map((panel) => panel.dataset.panel));

  let activeTab = localStorage.getItem(TAB_STORAGE_KEY) || defaultTab;
  if (!validTabs.has(activeTab)) {
    activeTab = defaultTab;
  }

  let sessionId = localStorage.getItem(SESSION_STORAGE_KEY);
  if (sessionId && Number(sessionId) <= 0) {
    sessionId = null;
  }

  let isSending = false;
  let hasLoadedHistory = false;

  const renderMessage = (role, content, createdAt) => {
    const wrapper = document.createElement('div');
    wrapper.classList.add('sb-ai-message', role === 'assistant' ? 'assistant' : 'user');

    const bubble = document.createElement('span');
    bubble.textContent = content;
    wrapper.appendChild(bubble);

    if (createdAt) {
      wrapper.dataset.createdAt = createdAt;
    }

    messageList.appendChild(wrapper);
    messageList.scrollTop = messageList.scrollHeight;
  };

  const clearMessages = () => {
    messageList.innerHTML = '';
  };

  const setSendingState = (state) => {
    isSending = state;
    if (typingLine) {
      typingLine.classList.toggle('is-visible', state);
    }
    sendButton.disabled = state;
  };

  const ensureSession = async () => {
    if (sessionId && Number(sessionId) > 0) {
      return sessionId;
    }

    const response = await fetch(endpoint, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ action: 'start' }),
    });

    let data = null;
    try {
      data = await response.json();
    } catch (error) {
      // ignore JSON parse errors below
    }

    if (!response.ok || !data?.ok) {
      const message = data?.error || 'Không thể khởi tạo phiên chat.';
      throw new Error(message);
    }

    sessionId = data.session_id;
    localStorage.setItem(SESSION_STORAGE_KEY, String(sessionId));
    hasLoadedHistory = false;
    return sessionId;
  };

  const loadHistory = async (force = false) => {
    if (!sessionId || Number(sessionId) <= 0) {
      return;
    }

    if (hasLoadedHistory && !force) {
      return;
    }

    const response = await fetch(endpoint, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ action: 'history', session_id: Number(sessionId) }),
    });

    let data = null;
    try {
      data = await response.json();
    } catch (error) {
      console.warn('Không đọc được JSON lịch sử AI chat:', error);
      return;
    }

    if (!response.ok || !data?.ok) {
      const message = data?.error || `Không thể tải lịch sử AI chat: HTTP ${response.status}`;
      console.warn(message);
      if (message.includes('Phiên chat')) {
        localStorage.removeItem(SESSION_STORAGE_KEY);
        sessionId = null;
        hasLoadedHistory = false;
        try {
          await ensureSession();
          clearMessages();
        } catch (error) {
          console.error(error);
        }
      }
      return;
    }

    clearMessages();
    data.messages.forEach((item) => {
      renderMessage(item.ROLE, item.NOI_DUNG, item.CREATED_AT);
    });
    hasLoadedHistory = true;
  };

  const sendMessage = async (text) => {
    await ensureSession();
    setSendingState(true);
    renderMessage('user', text, new Date().toISOString());

    const response = await fetch(endpoint, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        action: 'send',
        session_id: Number(sessionId),
        message: text,
      }),
    });

    let data = null;
    try {
      data = await response.json();
    } catch (error) {
      console.warn('Không đọc được JSON từ phản hồi AI chat:', error);
    }

    setSendingState(false);

    if (!response.ok || !data?.ok) {
      const errorText = data?.error || `Máy chủ đang bận (HTTP ${response.status}). Thử lại sau nhé.`;
      renderMessage('assistant', errorText, new Date().toISOString());
      if (errorText.includes('Phiên chat')) {
        localStorage.removeItem(SESSION_STORAGE_KEY);
        sessionId = null;
        hasLoadedHistory = false;
      }
      return;
    }

    clearMessages();
    data.messages.forEach((item) => {
      renderMessage(item.ROLE, item.NOI_DUNG, item.CREATED_AT);
    });
    hasLoadedHistory = true;
  };

  const hydrateAssistant = async (options = {}) => {
    try {
      await ensureSession();
      await loadHistory(options.force === true);
    } catch (error) {
      console.error(error);
    }
  };

  const setActiveTab = (target, options = {}) => {
    if (!target || !validTabs.has(target)) {
      target = defaultTab;
    }

    activeTab = target;
    localStorage.setItem(TAB_STORAGE_KEY, activeTab);

    tabs.forEach((tab) => {
      const value = tab.dataset.tab;
      const isActive = value === activeTab;
      tab.classList.toggle('is-active', isActive);
      tab.setAttribute('aria-selected', String(isActive));
    });

    panels.forEach((panel) => {
      const value = panel.dataset.panel;
      const isActive = value === activeTab;
      panel.classList.toggle('is-active', isActive);
      panel.setAttribute('aria-hidden', String(!isActive));
    });

    quickSwitchButtons.forEach((button) => {
      const value = button.dataset.switchTab;
      if (!value) {
        return;
      }
      const isActive = value === activeTab;
      button.classList.toggle('is-active', isActive);
      button.disabled = isActive;
      button.setAttribute('aria-pressed', String(isActive));
    });

    const shouldLoadAssistant =
      activeTab === 'assistant' && (options.eagerHistory || chatWindow.classList.contains('is-open'));

    if (shouldLoadAssistant) {
      hydrateAssistant({ force: options.forceHistory });
      if (options.focusTextarea !== false) {
        requestAnimationFrame(() => {
          textarea.focus();
        });
      }
    }
  };

  toggleButton.addEventListener('click', () => {
    chatWindow.classList.toggle('is-open');
    if (chatWindow.classList.contains('is-open')) {
      setActiveTab(activeTab, {
        eagerHistory: true,
        focusTextarea: activeTab === 'assistant',
      });
    }
  });

  if (closeButton) {
    closeButton.addEventListener('click', () => {
      chatWindow.classList.remove('is-open');
    });
  }

  tabs.forEach((tab) => {
    tab.addEventListener('click', () => {
      const value = tab.dataset.tab;
      if (!value) {
        return;
      }
      setActiveTab(value, {
        eagerHistory: true,
        focusTextarea: value === 'assistant',
      });
    });
  });

  quickSwitchButtons.forEach((button) => {
    button.addEventListener('click', () => {
      const value = button.dataset.switchTab;
      if (!value) {
        return;
      }
      setActiveTab(value, {
        eagerHistory: true,
        focusTextarea: value === 'assistant',
      });
    });
  });

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (isSending) {
      return;
    }

    const value = textarea.value.trim();
    if (!value) {
      return;
    }

    textarea.value = '';
    textarea.style.height = 'auto';

    try {
      await sendMessage(value);
    } catch (error) {
      setSendingState(false);
      const fallback = error?.message ? String(error.message) : 'Xin lỗi, tôi không thể phản hồi ngay lúc này.';
      renderMessage('assistant', fallback, new Date().toISOString());
      if (fallback.includes('Phiên chat')) {
        localStorage.removeItem(SESSION_STORAGE_KEY);
        sessionId = null;
        hasLoadedHistory = false;
      }
      console.error(error);
    }
  });

  const adjustTextareaHeight = () => {
    textarea.style.height = 'auto';
    textarea.style.height = `${Math.min(textarea.scrollHeight, 140)}px`;
  };

  textarea.addEventListener('input', () => {
    adjustTextareaHeight();
  });

  copyButtons.forEach((button) => {
    button.addEventListener('click', async () => {
      const text = button.dataset.copy;
      if (!text) {
        return;
      }

      try {
        if (navigator.clipboard && navigator.clipboard.writeText) {
          await navigator.clipboard.writeText(text);
        } else {
          const helper = document.createElement('textarea');
          helper.value = text;
          helper.setAttribute('readonly', '');
          helper.style.position = 'absolute';
          helper.style.left = '-9999px';
          document.body.appendChild(helper);
          helper.select();
          document.execCommand('copy');
          document.body.removeChild(helper);
        }

        const originalLabel = button.dataset.originalLabel || button.textContent;
        button.dataset.originalLabel = originalLabel;
        button.textContent = 'Đã sao chép';
        button.disabled = true;

        window.setTimeout(() => {
          button.textContent = button.dataset.originalLabel || originalLabel;
          button.disabled = false;
        }, 1600);
      } catch (error) {
        console.warn('Không thể sao chép vào clipboard:', error);
        window.prompt('Sao chép thủ công nội dung này:', text);
      }
    });
  });

  linkButtons.forEach((button) => {
    button.addEventListener('click', () => {
      const url = button.dataset.openLink;
      if (!url) {
        return;
      }
      window.open(url, '_blank', 'noopener');
    });
  });

  setActiveTab(activeTab, { focusTextarea: false });
  adjustTextareaHeight();
})();
