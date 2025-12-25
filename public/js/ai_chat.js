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

  const speedDial = widget.querySelector('.sb-ai-speed-dial');
  const toggleButton = widget.querySelector('.sb-ai-chat-toggle');
  const chatWindow = widget.querySelector('.sb-ai-chat-window');
  const closeButton = widget.querySelector('[data-ai-chat-close]');
  const dialButtons = Array.from(widget.querySelectorAll('[data-dial-action]'));
  const panels = Array.from(widget.querySelectorAll('.sb-ai-panel'));
  const headerTitle = widget.querySelector('[data-header-title]');
  const headerSubtitle = widget.querySelector('[data-header-subtitle]');
  const headerIcon = widget.querySelector('[data-header-icon]');
  const aiPanel = widget.querySelector('[data-panel="assistant"]');
  const messageList = aiPanel ? aiPanel.querySelector('.sb-ai-chat-body') : null;
  const typingLine = aiPanel ? aiPanel.querySelector('.sb-ai-typing') : null;
  const form = aiPanel ? aiPanel.querySelector('form') : null;
  const textarea = aiPanel ? aiPanel.querySelector('textarea') : null;
  const sendButton = aiPanel ? aiPanel.querySelector('[data-ai-chat-send]') : null;
  const copyButtons = Array.from(widget.querySelectorAll('[data-copy]'));
  const linkButtons = Array.from(widget.querySelectorAll('[data-open-link]'));
  const quickReplyButtons = Array.from(widget.querySelectorAll('[data-quick-text]'));
  const scrollBottomButton = widget.querySelector('[data-scroll-bottom]');
  const faqToggle = aiPanel ? aiPanel.querySelector('.sb-ai-faq-toggle') : null;
  const toastStack = widget.querySelector('.sb-ai-toast-stack');
  // State namespace for future modularization
  const chatNS = {
    renderedIds: new Set(),
    lastHistoryCount: 0
  };

  if (!toggleButton || !chatWindow || !aiPanel || !messageList || !form || !textarea || !sendButton) {
    console.error('AI chat widget is missing required markup.');
    return;
  }

  const TAB_STORAGE_KEY = 'sb_ai_chat_active_tab';
  const SESSION_STORAGE_NAMESPACE = 'sb_ai_chat_session';
  const userKey = widget.dataset.userKey || 'guest';
  const SESSION_STORAGE_KEY = `${SESSION_STORAGE_NAMESPACE}_${userKey}`;
  const defaultTab = widget.dataset.defaultTab || 'assistant';
  const validTabs = new Set(panels.map((panel) => panel.dataset.panel));

  let activeTab = localStorage.getItem(TAB_STORAGE_KEY) || defaultTab;
  if (!validTabs.has(activeTab)) {
    activeTab = defaultTab;
  }

  let sessionId = null;
  let clientToken = null;
  try {
    const storedSession = localStorage.getItem(SESSION_STORAGE_KEY);
    if (storedSession) {
      const parsed = JSON.parse(storedSession);
      if (parsed && typeof parsed === 'object') {
        if (Number(parsed.id) > 0) {
          sessionId = Number(parsed.id);
        }
        if (typeof parsed.token === 'string' && parsed.token.trim() !== '') {
          clientToken = parsed.token.trim();
        }
      }
    }
  } catch (error) {
    localStorage.removeItem(SESSION_STORAGE_KEY);
    sessionId = null;
    clientToken = null;
  }

  let isSending = false;
  let hasLoadedHistory = false;
  let lastDateSeparator = null;
  let isSpeedDialOpen = speedDial?.classList.contains('is-open') || false;
  let offlineQueue = [];

  const clearStoredSession = () => {
    localStorage.removeItem(SESSION_STORAGE_KEY);
    sessionId = null;
    clientToken = null;
    hasLoadedHistory = false;
  };

  const persistSessionCredentials = (id, token) => {
    if (!id || !token) {
      clearStoredSession();
      return;
    }
    sessionId = Number(id);
    clientToken = String(token);
    localStorage.setItem(SESSION_STORAGE_KEY, JSON.stringify({
      id: sessionId,
      token: clientToken,
    }));
  };

  // Panel configuration
  const panelConfig = {
    assistant: {
      title: 'Trợ lý AI',
      subtitle: 'Luôn sẵn sàng hỗ trợ bạn',
      icon: '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" width="24" height="24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>'
    },
    zalo: {
      title: 'Chat Zalo',
      subtitle: 'Kết nối nhanh qua Zalo',
      icon: '<img src="../../../public/images/zalo.png" alt="Zalo" style="width: 24px; height: 24px;">'
    },
    messenger: {
      title: 'Chat Messenger',
      subtitle: 'Tư vấn qua Messenger',
      icon: '<img src="../../../public/images/mess_icon.png" alt="Messenger" style="width: 24px; height: 24px;">'
    }
  };

  // Status pill mapping
  const statusPill = widget.querySelector('[data-status-pill]');
  const dialBadgeEls = Array.from(widget.querySelectorAll('[data-dial-badge]'));
  const dialBadges = Object.fromEntries(dialBadgeEls.map(el => [el.dataset.dialBadge, el]));

  const unreadCounts = {
    assistant: 0,
    zalo: 0,
    messenger: 0,
  };

  const updateDialBadge = (channel) => {
    const el = dialBadges[channel];
    if (!el) return;
    const count = unreadCounts[channel] || 0;
    if (count > 0) {
      el.textContent = count > 99 ? '99+' : String(count);
      el.classList.add('is-visible');
    } else {
      el.textContent = '';
      el.classList.remove('is-visible');
    }
  };

  const updateStatusPill = (channel) => {
    if (!statusPill) return;
    if (!navigator.onLine) {
      statusPill.textContent = 'Offline';
      statusPill.dataset.status = 'offline';
      return;
    }
    switch (channel) {
      case 'assistant':
        statusPill.textContent = 'Online';
        statusPill.dataset.status = 'assistant';
        break;
      case 'zalo':
        statusPill.textContent = 'Nhanh • 1-3m';
        statusPill.dataset.status = 'zalo';
        break;
      case 'messenger':
        statusPill.textContent = 'Tư vấn • 3-5m';
        statusPill.dataset.status = 'messenger';
        break;
      default:
        statusPill.textContent = 'Online';
        statusPill.dataset.status = 'assistant';
        break;
    }
  };

  // Drag reposition for speed dial
  const DIAL_POS_X_KEY = 'sb_ai_dial_pos_x';
  const DIAL_POS_Y_KEY = 'sb_ai_dial_pos_y';
  const dragHandle = toggleButton; // use main toggle as drag handle when closed
  let dragStartX = 0;
  let dragStartY = 0;
  let dialStartX = 0;
  let dialStartY = 0;
  let isDragging = false;
  let isPointerDown = false;
  let skipNextToggle = false;

  const applyStoredDialPosition = () => {
    const storedX = localStorage.getItem(DIAL_POS_X_KEY);
    const storedY = localStorage.getItem(DIAL_POS_Y_KEY);
    if (storedX !== null && storedY !== null) {
      speedDial.style.left = `${Math.min(Math.max(0, Number(storedX)), window.innerWidth - speedDial.offsetWidth)}px`;
      speedDial.style.top = `${Math.min(Math.max(0, Number(storedY)), window.innerHeight - speedDial.offsetHeight)}px`;
      speedDial.style.right = '';
      speedDial.style.bottom = '';
      speedDial.style.position = 'fixed';
    }
  };
  applyStoredDialPosition();
  const onPointerMove = (e) => {
    if (!isPointerDown) return;
    e.preventDefault(); // Ngăn các hành vi mặc định
    const clientX = e.clientX;
    const clientY = e.clientY;
    const dx = clientX - dragStartX;
    const dy = clientY - dragStartY;

    if (!isDragging && (Math.abs(dx) > 4 || Math.abs(dy) > 4)) {
      isDragging = true;
      skipNextToggle = true; // prevent opening on click after drag
      speedDial.classList.add('dragging');
    }
    if (!isDragging) return;

    let newX = dialStartX + dx;
    let newY = dialStartY + dy;
    const maxX = window.innerWidth - speedDial.offsetWidth;
    const maxY = window.innerHeight - speedDial.offsetHeight;
    newX = Math.min(Math.max(0, newX), maxX);
    newY = Math.min(Math.max(0, newY), maxY);
    speedDial.style.left = `${newX}px`;
    speedDial.style.top = `${newY}px`;
    speedDial.style.right = '';
    speedDial.style.bottom = '';
  };

  const endDrag = () => {
    if (!isPointerDown) return;
    document.removeEventListener('pointermove', onPointerMove);
    document.removeEventListener('pointerup', endDrag);
    isPointerDown = false;
    if (isDragging) {
      speedDial.classList.remove('dragging');
      isDragging = false;
      // Save position
      const rect = speedDial.getBoundingClientRect();
      localStorage.setItem(DIAL_POS_X_KEY, String(rect.left));
      localStorage.setItem(DIAL_POS_Y_KEY, String(rect.top));
      setTimeout(() => { skipNextToggle = false; }, 120); // allow click after small delay
    } else {
      // Nếu không drag (chỉ click), reset ngay để cho phép toggle
      skipNextToggle = false;
    }
  };

  dragHandle.addEventListener('pointerdown', (e) => {
    // Only allow drag when menu closed
    if (isSpeedDialOpen) return;
    e.stopPropagation(); // Ngăn event bubble up
    isPointerDown = true;
    isDragging = false;
    dragStartX = e.clientX;
    dragStartY = e.clientY;
    const rect = speedDial.getBoundingClientRect();
    dialStartX = rect.left;
    dialStartY = rect.top;
    document.addEventListener('pointermove', onPointerMove, { passive: false });
    document.addEventListener('pointerup', endDrag);
  });

  // Helper functions for date formatting
  const formatTime = (dateString) => {
    try {
      const date = new Date(dateString);
      return date.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
    } catch (error) {
      return '';
    }
  };

  const formatDate = (dateString) => {
    try {
      const date = new Date(dateString);
      const today = new Date();
      const yesterday = new Date(today);
      yesterday.setDate(yesterday.getDate() - 1);

      const dateOnly = new Date(date.getFullYear(), date.getMonth(), date.getDate());
      const todayOnly = new Date(today.getFullYear(), today.getMonth(), today.getDate());
      const yesterdayOnly = new Date(yesterday.getFullYear(), yesterday.getMonth(), yesterday.getDate());

      if (dateOnly.getTime() === todayOnly.getTime()) {
        return 'Hôm nay';
      } else if (dateOnly.getTime() === yesterdayOnly.getTime()) {
        return 'Hôm qua';
      } else {
        return date.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });
      }
    } catch (error) {
      return '';
    }
  };

  const getDateKey = (dateString) => {
    try {
      const date = new Date(dateString);
      return `${date.getFullYear()}-${date.getMonth()}-${date.getDate()}`;
    } catch (error) {
      return '';
    }
  };

  const insertDateSeparator = (dateString) => {
    const dateKey = getDateKey(dateString);
    if (dateKey && dateKey !== lastDateSeparator) {
      const separator = document.createElement('div');
      separator.classList.add('sb-ai-date-separator');
      separator.innerHTML = `<span>${formatDate(dateString)}</span>`;
      messageList.appendChild(separator);
      lastDateSeparator = dateKey;
    }
  };

  const getInitials = (role) => {
    if (role === 'assistant') {
      return 'AI';
    }
    return 'U';
  };

  const createCopyButton = (content) => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'sb-ai-copy-btn';
    button.innerHTML = '📋 Sao chép';
    button.dataset.content = content;
    return button;
  };

  const renderMessage = (role, content, createdAt) => {
    // Create stable id to avoid duplicate re-renders when server returns full history
    const idBase = `${role}|${createdAt}|${content}`;
    if (chatNS.renderedIds.has(idBase)) {
      return; // skip duplicate
    }
    chatNS.renderedIds.add(idBase);
    insertDateSeparator(createdAt);

    const wrapper = document.createElement('div');
    wrapper.classList.add('sb-ai-message', role === 'assistant' ? 'assistant' : 'user');

    const avatar = document.createElement('div');
    avatar.className = 'sb-ai-message-avatar';
    avatar.textContent = getInitials(role);

    const contentWrapper = document.createElement('div');
    contentWrapper.className = 'sb-ai-message-content';

    const messageWrapper = document.createElement('div');
    messageWrapper.className = 'sb-ai-message-wrapper';

    const bubble = document.createElement('div');
    bubble.className = 'sb-ai-message-bubble';
    bubble.textContent = content;

    messageWrapper.appendChild(bubble);
    contentWrapper.appendChild(messageWrapper);

    const timestamp = document.createElement('div');
    timestamp.className = 'sb-ai-message-timestamp';
    timestamp.textContent = formatTime(createdAt);
    contentWrapper.appendChild(timestamp);

    if (role === 'assistant') {
      const actions = document.createElement('div');
      actions.className = 'sb-ai-message-actions';
      const copyBtn = createCopyButton(content);
      actions.appendChild(copyBtn);
      contentWrapper.appendChild(actions);
    }

    wrapper.appendChild(avatar);
    wrapper.appendChild(contentWrapper);

    if (createdAt) {
      wrapper.dataset.createdAt = createdAt;
    }

    messageList.appendChild(wrapper);
    scrollToBottom();

    // Unread counter logic: only count assistant messages when user not viewing assistant panel
    if (role === 'assistant' && activeTab !== 'assistant') {
      unreadCounts.assistant++;
      updateDialBadge('assistant');
    }
  };

  const clearMessages = () => {
    const messages = messageList.querySelectorAll('.sb-ai-message, .sb-ai-date-separator');
    messages.forEach(msg => msg.remove());
    lastDateSeparator = null;
    chatNS.renderedIds.clear();
  };

  const scrollToBottom = (smooth = true) => {
    if (messageList) {
      messageList.scrollTo({
        top: messageList.scrollHeight,
        behavior: smooth ? 'smooth' : 'auto'
      });
    }
  };

  const setSendingState = (state) => {
    isSending = state;
    if (typingLine) {
      typingLine.classList.toggle('is-visible', state);
    }
    sendButton.disabled = state;
  };

  const ensureSession = async () => {
    if (sessionId && clientToken) {
      return { sessionId, clientToken };
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
      showToast(message, 'error');
      throw new Error(message);
    }

    persistSessionCredentials(data.session_id, data.client_token);
    hasLoadedHistory = false;
    return { sessionId, clientToken };
  };

  const loadHistory = async (force = false) => {
    if (!sessionId || !clientToken) {
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
      body: JSON.stringify({
        action: 'history',
        session_id: Number(sessionId),
        client_token: clientToken,
      }),
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
      showToast(message, 'error');
      if (message.includes('Phiên chat')) {
        clearStoredSession();
        try {
          await ensureSession();
          clearMessages();
        } catch (error) {
          console.error(error);
        }
      }
      return;
    }

    if (data.session_id && data.client_token) {
      persistSessionCredentials(data.session_id, data.client_token);
    }

    clearMessages();
    data.messages.forEach((item) => {
      renderMessage(item.ROLE, item.NOI_DUNG, item.CREATED_AT);
    });
    hasLoadedHistory = true;
  };

  const sendMessage = async (text) => {
    if (!navigator.onLine) {
      offlineQueue.push({ text, ts: new Date().toISOString() });
      renderMessage('user', text, new Date().toISOString());
      showToast('Offline - tin đã được xếp hàng', 'info');
      return;
    }
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
        client_token: clientToken,
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
      showToast(errorText, 'error');
      if (errorText.includes('Phiên chat')) {
        clearStoredSession();
      }
      return;
    }
    if (data.session_id && data.client_token) {
      persistSessionCredentials(data.session_id, data.client_token);
    }
    // Append only new messages (avoid clearing list for better UX performance)
    data.messages.forEach((item) => {
      renderMessage(item.ROLE, item.NOI_DUNG, item.CREATED_AT);
    });
    // If API returns a 'reply' field that may not yet be inside messages array, render it explicitly.
    if (data.reply && typeof data.reply === 'string') {
      // Check if an assistant bubble already contains this exact content
      const existing = Array.from(messageList.querySelectorAll('.sb-ai-message.assistant .sb-ai-message-bubble'))
        .some(b => b.textContent === data.reply.trim());
      if (!existing) {
        renderMessage('assistant', data.reply.trim(), new Date().toISOString());
      }
    }
    hasLoadedHistory = true;
  };

  // Placeholder for future streaming implementation (progressive assistant response)
  // const streamAssistantResponse = async (payload) => {
  //   try {
  //     await ensureSession();
  //     const res = await fetch(endpoint, { method: 'POST', body: JSON.stringify(payload) });
  //     if (!res.body) return;
  //     const reader = res.body.getReader();
  //     let partial = '';
  //     const startTs = new Date().toISOString();
  //     // Render shell message for streaming
  //     renderMessage('assistant', '', startTs);
  //     const lastMessageEl = messageList.querySelector('.sb-ai-message.assistant:last-child .sb-ai-message-bubble');
  //     while (true) {
  //       const { done, value } = await reader.read();
  //       if (done) break;
  //       partial += new TextDecoder().decode(value);
  //       if (lastMessageEl) {
  //         lastMessageEl.textContent = partial;
  //         scrollToBottom(false);
  //       }
  //     }
  //   } catch (e) {
  //     console.warn('Streaming error:', e);
  //   }
  // };

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

    // Update panels
    panels.forEach((panel) => {
      const value = panel.dataset.panel;
      const isActive = value === activeTab;
      panel.classList.toggle('is-active', isActive);
      panel.setAttribute('aria-hidden', String(!isActive));
    });

    // Update header
    const config = panelConfig[activeTab] || panelConfig.assistant;
    if (headerTitle) headerTitle.textContent = config.title;
    if (headerSubtitle) headerSubtitle.textContent = config.subtitle;
    if (headerIcon) headerIcon.innerHTML = config.icon;
    updateStatusPill(activeTab);

    // Reset unread counter when opening this channel
    if (unreadCounts[activeTab] !== undefined) {
      unreadCounts[activeTab] = 0;
      updateDialBadge(activeTab);
    }

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

  // Toggle: nếu chat đang mở thì ưu tiên đóng; nếu không thì mở speed dial (nếu có) hoặc mở chat
  toggleButton.addEventListener('click', () => {
    if (skipNextToggle) return;

    if (chatWindow.classList.contains('is-open')) {
      closeChatWindow();
      if (speedDial) {
        speedDial.classList.remove('is-open');
        isSpeedDialOpen = false;
      }
      return;
    }

    if (dialButtons.length > 0) {
      const willOpen = !speedDial.classList.contains('is-open');
      speedDial.classList.toggle('is-open', willOpen);
      isSpeedDialOpen = willOpen;
      return;
    }

    setActiveTab('assistant', { eagerHistory: true, focusTextarea: true });
    openChatWindow();
  });

  // Handle dial button clicks (assistant opens chat; others handled via data-open-link)
  dialButtons.forEach((button) => {
    button.addEventListener('click', (e) => {
      e.stopPropagation();
      const action = button.dataset.dialAction;
      if (!action) return;

      // Chỉ mở panel trợ lý AI theo thiết kế speed dial mới
      setActiveTab('assistant', {
        eagerHistory: true,
        focusTextarea: true,
      });
      openChatWindow();
      speedDial.classList.remove('is-open');
      isSpeedDialOpen = false;
    });
  });

  if (closeButton) {
    closeButton.addEventListener('click', () => {
      chatWindow.classList.remove('is-open');
    });
  }

  // Close speed dial when clicking outside
  document.addEventListener('click', (e) => {
    if (isSpeedDialOpen && !speedDial.contains(e.target)) {
      speedDial.classList.remove('is-open');
      isSpeedDialOpen = false;
    }
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
        clearStoredSession();
      }
      console.error(error);
    }
  });

  const adjustTextareaHeight = () => {
    textarea.style.height = 'auto';
    textarea.style.height = `${Math.min(textarea.scrollHeight, 140)}px`;
  };

  const triggerFormSubmit = () => {
    if (!textarea.value.trim() || isSending) {
      return;
    }
    if (typeof form.requestSubmit === 'function') {
      form.requestSubmit(sendButton ?? undefined);
    } else {
      form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
    }
  };

  textarea.addEventListener('input', () => {
    adjustTextareaHeight();
  });

  // Nhấn Enter để gửi, Shift+Enter để xuống dòng mới
  textarea.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter' || event.shiftKey || event.ctrlKey || event.metaKey || event.altKey) {
      return;
    }
    event.preventDefault();
    triggerFormSubmit();
  });

  // Handle scroll to bottom button
  if (scrollBottomButton && messageList) {
    let scrollTick = false;
    messageList.addEventListener('scroll', () => {
      if (scrollTick) return;
      scrollTick = true;
      requestAnimationFrame(() => {
        scrollTick = false;
        const isNearBottom = messageList.scrollHeight - messageList.scrollTop - messageList.clientHeight < 100;
        if (isNearBottom) {
          scrollBottomButton.classList.remove('is-visible');
        } else if (messageList.scrollTop > 100) {
          scrollBottomButton.classList.add('is-visible');
        }
      });
    });

    scrollBottomButton.addEventListener('click', () => {
      scrollToBottom(true);
    });
  }

  // Handle quick reply buttons
  quickReplyButtons.forEach((button) => {
    button.addEventListener('click', () => {
      const text = button.dataset.quickText;
      if (!text || isSending) {
        return;
      }
      textarea.value = text;
      adjustTextareaHeight();
      textarea.focus();
      // Auto-send or let user review
      // Uncomment next line to auto-send:
      // form.dispatchEvent(new Event('submit', { cancelable: true }));
    });
  });

  // FAQ toggle expand/collapse
  if (faqToggle) {
    faqToggle.addEventListener('click', () => {
      const parent = faqToggle.closest('.sb-ai-faq');
      if (!parent) return;
      const isOpen = parent.classList.toggle('is-open');
      faqToggle.setAttribute('aria-expanded', String(isOpen));
      const items = parent.querySelector('.sb-ai-faq-items');
      if (items) {
        items.setAttribute('aria-hidden', String(!isOpen));
      }
    });
  }

  // Keyboard shortcuts: Esc close, Ctrl+Enter send
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && chatWindow.classList.contains('is-open')) {
      chatWindow.classList.remove('is-open');
      return;
    }
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter' && chatWindow.classList.contains('is-open')) {
      if (textarea.value.trim() && !isSending) {
        form.dispatchEvent(new Event('submit', { cancelable: true }));
      }
    }
  });

  // Toast helper
  const showToast = (message, type = 'info', timeout = 4000) => {
    if (!toastStack) return;
    const el = document.createElement('div');
    el.className = 'sb-ai-toast';
    el.dataset.type = type;
    el.textContent = message;
    toastStack.appendChild(el);
    if (toastStack.children.length > 5) {
      toastStack.firstElementChild?.remove();
    }
    setTimeout(() => {
      el.style.opacity = '0';
      el.style.transform = 'translateY(4px) scale(.98)';
      setTimeout(() => el.remove(), 250);
    }, timeout);
  };

  // Connectivity handlers
  window.addEventListener('offline', () => {
    updateStatusPill(activeTab);
    showToast('Bạn đang offline', 'error');
  });

  window.addEventListener('online', async () => {
    updateStatusPill(activeTab);
    if (offlineQueue.length) {
      showToast('Đang gửi lại tin đã xếp hàng...', 'info');
      const queue = [...offlineQueue];
      offlineQueue = [];
      for (const item of queue) {
        try {
          await sendMessage(item.text);
        } catch (e) {
          showToast('Gửi lại thất bại', 'error');
        }
      }
    } else {
      showToast('Đã kết nối lại', 'info');
    }
  });

  // Focus trap inside dialog
  const manageFocusTrap = () => {
    if (!chatWindow.classList.contains('is-open')) return;
    const focusable = chatWindow.querySelectorAll('button, [href], textarea, [tabindex]:not([tabindex="-1"])');
    if (!focusable.length) return;
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    chatWindow.addEventListener('keydown', (e) => {
      if (e.key === 'Tab') {
        if (e.shiftKey && document.activeElement === first) {
          e.preventDefault();
          last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
          e.preventDefault();
          first.focus();
        }
      }
    }, { once: true });
  };

  const openChatWindow = () => {
    chatWindow.classList.add('is-open');
    chatWindow.setAttribute('aria-modal', 'true');
    if (speedDial) {
      speedDial.classList.add('has-chat-open');
      speedDial.classList.remove('is-open');
      isSpeedDialOpen = false;
    }
    manageFocusTrap();
    requestAnimationFrame(() => textarea?.focus());
  };

  const closeChatWindow = () => {
    chatWindow.classList.remove('is-open');
    chatWindow.setAttribute('aria-modal', 'false');
    if (speedDial) {
      speedDial.classList.remove('has-chat-open');
    }
    toggleButton?.focus();
  };

  // Replace direct open/close usages
  if (closeButton) {
    closeButton.removeEventListener('click', () => {});
    closeButton.addEventListener('click', closeChatWindow);
  }

  // (Đã xử lý sự kiện dial buttons ở trên theo thiết kế speed dial)

  // Handle copy buttons dynamically (using event delegation)
  messageList.addEventListener('click', async (event) => {
    const copyBtn = event.target.closest('.sb-ai-copy-btn');
    if (!copyBtn) {
      return;
    }

    const content = copyBtn.dataset.content;
    if (!content) {
      return;
    }

    try {
      if (navigator.clipboard && navigator.clipboard.writeText) {
        await navigator.clipboard.writeText(content);
      } else {
        const helper = document.createElement('textarea');
        helper.value = content;
        helper.setAttribute('readonly', '');
        helper.style.position = 'absolute';
        helper.style.left = '-9999px';
        document.body.appendChild(helper);
        helper.select();
        document.execCommand('copy');
        document.body.removeChild(helper);
      }

      const originalHTML = copyBtn.innerHTML;
      copyBtn.innerHTML = '✓ Đã sao chép';
      copyBtn.classList.add('is-copied');
      copyBtn.disabled = true;

      window.setTimeout(() => {
        copyBtn.innerHTML = originalHTML;
        copyBtn.classList.remove('is-copied');
        copyBtn.disabled = false;
      }, 2000);
    } catch (error) {
      console.warn('Không thể sao chép vào clipboard:', error);
    }
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
      // Đóng speed dial trước khi mở link ngoài
      if (speedDial) {
        speedDial.classList.remove('is-open');
        isSpeedDialOpen = false;
      }
      window.open(url, '_blank', 'noopener');
    });
  });

  // Initialize
  setActiveTab(activeTab, { focusTextarea: false });
  adjustTextareaHeight();
})();
