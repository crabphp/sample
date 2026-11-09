// Optional live experiences. Shopping, accounts and administration use native forms.
(() => {
  document.documentElement.classList.add('enhanced');
  const close = [];
  document.querySelectorAll('[data-stock]').forEach(widget => {
    if (!window.EventSource) return;
    const events = new EventSource('/api/products/' + encodeURIComponent(widget.dataset.stock) + '/stock');
    const status = widget.querySelector('[data-stock-status]');
    events.addEventListener('stock', event => {
      try {
        const value = JSON.parse(event.data).stock;
        widget.querySelector('[data-stock-value]').textContent = value === null ? '0' : String(value);
        status.textContent = 'Live stock · connected';
      } catch { status.textContent = 'Refresh to check availability.'; }
    });
    events.onerror = () => { status.textContent = 'Reconnecting · refresh to check availability.'; };
    close.push(() => events.close());
  });
  document.querySelectorAll('[data-order]').forEach(widget => {
    if (!window.WebSocket) return;
    let version = -1;
    let timer;
    let socket;
    let stopped = false;
    const status = widget.querySelector('[data-order-status]');
    const connection = widget.querySelector('[data-order-connection]');
    const connect = () => {
      socket = new WebSocket((location.protocol === 'https:' ? 'wss://' : 'ws://') + location.host + '/orders/' + encodeURIComponent(widget.dataset.order) + '/live');
      socket.onmessage = event => {
        try {
          const state = JSON.parse(event.data);
          if (!Number.isInteger(state.version) || state.version <= version || !['pending', 'paid', 'shipped', 'cancelled'].includes(state.status)) return;
          version = state.version;
          status.textContent = state.status[0].toUpperCase() + state.status.slice(1);
          connection.textContent = 'Live updates · connected';
        } catch { connection.textContent = 'Refresh to check order status.'; }
      };
      socket.onclose = () => {
        if (stopped) return;
        connection.textContent = 'Reconnecting · refresh for the latest status.';
        timer = setTimeout(connect, 5000);
      };
    };
    connect();
    close.push(() => { stopped = true; clearTimeout(timer); socket.close(); });
  });
  document.querySelectorAll('[data-assistant-form]').forEach(form => {
    if (!window.fetch || !window.ReadableStream) return;
    const output = form.parentElement.querySelector('[data-assistant-answer]');
    const thread = form.parentElement.querySelector('[data-chat-thread]');
    const conversation = form.dataset.conversation === 'true';
    const scrollChat = (smooth = false) => {
      const active = form.parentElement.querySelector('[data-chat-thread]');
      if (active) active.scrollTo({ top: active.scrollHeight, behavior: smooth ? 'smooth' : 'auto' });
    };
    const fallback = document.createElement('button');
    if (!conversation) {
      fallback.type = 'submit';
      fallback.className = 'button button-ghost';
      fallback.setAttribute('formaction', '/assistant');
      fallback.textContent = 'Read full answer';
      form.querySelector('.actions').append(fallback);
    }
    if (conversation && thread === null) return;
    form.addEventListener('submit', async event => {
      // Explicit full-answer and personal-chat submissions remain ordinary server actions.
      if (event.submitter?.hasAttribute('formaction')) return;
      event.preventDefault();
      const button = event.submitter;
      const field = form.querySelector('input[name="question"]');
      const body = new URLSearchParams(new FormData(form));
      const questionText = body.get('question') || '';
      if (button) button.disabled = true;
      if (field) field.disabled = true;
      form.setAttribute('aria-busy', 'true');
      output.textContent = conversation ? 'Thinking…' : 'Finding a few good things…';
      const controller = new AbortController();
      close.push(() => controller.abort());
      try {
        if (conversation) {
          const response = await fetch(form.action, {
            method: 'POST', body, headers: { Accept: 'text/html' }, signal: controller.signal
          });
          if (!response.ok || !response.headers.get('Content-Type')?.startsWith('text/html')) throw new Error('Conversation unavailable');
          const documentText = await response.text();
          const parsed = new DOMParser().parseFromString(documentText, 'text/html');
          const freshThread = parsed.querySelector('[data-chat-thread]');
          if (freshThread === null || thread === null) throw new Error('Conversation unavailable');
          thread.replaceWith(freshThread);
          const currentProducts = document.querySelector('.assistant-page + .section');
          const freshProducts = parsed.querySelector('.assistant-page + .section');
          if (freshProducts !== null) {
            if (currentProducts !== null) currentProducts.replaceWith(freshProducts);
            else document.querySelector('.assistant-page')?.after(freshProducts);
          }
          history.replaceState(null, '', response.url);
          form.reset();
          freshThread.scrollTop = freshThread.scrollHeight;
          field?.focus();
          return;
        }
        const response = await fetch('/assistant/stream', {
          method: 'POST', body, headers: { Accept: 'text/event-stream' }, signal: controller.signal
        });
        if (!response.ok || !response.headers.get('Content-Type')?.startsWith('text/event-stream')) throw new Error('Stream unavailable');
        output.textContent = '';
        const turn = document.createElement('article');
        turn.className = 'chat-turn chat-turn-live';
        const question = document.createElement('div');
        question.className = 'chat-message chat-message-user';
        question.innerHTML = '<p class="eyebrow">You</p><p></p>';
        question.querySelector('p:last-child').textContent = questionText;
        const answer = document.createElement('div');
        answer.className = 'chat-message chat-message-assistant';
        answer.innerHTML = '<p class="eyebrow">Crab assistant</p><p></p>';
        turn.append(question, answer);
        (thread || output).append(turn);
        if (thread) thread.querySelector('.chat-empty')?.remove();
        scrollChat(true);
        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';
        let complete = false;
        while (true) {
          const { value, done } = await reader.read();
          if (done) break;
          buffer += decoder.decode(value, { stream: true });
          buffer = buffer.replace(/\r\n/g, '\n');
          let boundary;
          while ((boundary = buffer.indexOf('\n\n')) !== -1) {
            const frame = buffer.slice(0, boundary);
            buffer = buffer.slice(boundary + 2);
            const data = frame.split('\n').filter(line => line.startsWith('data:')).map(line => line.slice(5).trimStart()).join('\n');
            if (!data) continue;
            const message = JSON.parse(data);
            if (typeof message.token === 'string') {
              answer.querySelector('p:last-child').textContent += message.token;
              scrollChat();
            }
            if (Array.isArray(message.products)) {
              complete = true;
              for (const id of message.products) {
                if (!Number.isSafeInteger(id) || id < 1) continue;
                const link = document.createElement('a');
                link.href = '/products/' + id;
                link.textContent = 'Explore product #' + id + ' →';
                answer.append(link);
              }
            }
          }
        }
        if (!complete) throw new Error('Stream interrupted');
        form.reset();
        field?.focus();
      } catch {
        output.textContent = conversation
          ? 'We couldn’t continue the conversation. Please try again.'
          : 'We couldn’t finish the live answer. Choose “Read full answer” to try the standard response.';
      } finally {
        if (button) button.disabled = false;
        if (field) field.disabled = false;
        form.removeAttribute('aria-busy');
      }
    });
  });
  window.addEventListener('pageshow', event => { if (event.persisted) location.reload(); });
  window.addEventListener('pagehide', () => close.forEach(stop => stop()), { once: true });
})();
