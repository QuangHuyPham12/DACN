document.addEventListener('DOMContentLoaded', () => {
  const root = document.querySelector('[data-advisor]');
  if (!root) return;
  const panel = root.querySelector('.advisor-panel');
  const toggle = root.querySelector('[data-advisor-toggle]');
  const messages = root.querySelector('[data-advisor-messages]');
  const suggestions = root.querySelector('[data-advisor-suggestions]');
  const input = root.querySelector('[data-advisor-input]');
  const send = root.querySelector('[data-advisor-send]');
  const clear = root.querySelector('[data-advisor-clear]');
  const status = root.querySelector('[data-advisor-status]');
  const criteria = root.querySelector('[data-advisor-criteria]');
  const mode = root.querySelector('[data-advisor-mode]');
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
  const initialSuggestions = ['Nike size 40 dưới 1 triệu', 'Giày đi học màu trắng', 'Hướng dẫn chọn size'];
  const greeting = { role: 'assistant', text: 'Chào bạn, tôi là SOLE Assistant ✦\nBạn đang tìm giày hãng nào, đi size bao nhiêu và có ngân sách khoảng bao nhiêu? Tôi sẽ tìm những lựa chọn đang còn hàng.', suggestions: initialSuggestions };
  let initialized = false;
  let busy = false;
  let lastOpener = toggle;

  const element = (tag, className, text) => {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  };
  const safeUrl = value => {
    try { const url = new URL(value, location.origin); return url.origin === location.origin && ['http:', 'https:'].includes(url.protocol) ? url.href : '#'; }
    catch { return '#'; }
  };
  const formatMoney = amount => `${new Intl.NumberFormat('vi-VN').format(amount)}đ`;
  const scrollEnd = () => { messages.scrollTop = messages.scrollHeight; };
  const showSuggestions = values => {
    suggestions.replaceChildren();
    (values || initialSuggestions).forEach(value => {
      const button = element('button', '', value);
      button.type = 'button';
      button.disabled = busy;
      button.addEventListener('click', () => { input.value = value; submit(); });
      suggestions.append(button);
    });
  };
  const addMessage = turn => {
    const article = element('article', `advisor-message ${turn.role === 'user' ? 'from-user' : 'from-assistant'}`);
    article.append(element('span', 'advisor-message-author', turn.role === 'user' ? 'Bạn' : 'SOLE'));
    article.append(element('p', 'advisor-message-text', turn.text));
    if (turn.products?.length) {
      const cards = element('div', 'advisor-products');
      turn.products.forEach(product => {
        const card = element('a', 'advisor-product');
        card.href = safeUrl(product.url);
        const img = element('img');
        img.src = safeUrl(product.image_url);
        img.alt = product.name;
        img.loading = 'lazy';
        img.width = 74;
        img.height = 72;
        const details = element('div');
        details.append(element('small', '', product.brand));
        details.append(element('strong', '', product.name));
        details.append(element('span', 'advisor-product-price', formatMoney(product.price)));
        details.append(element('small', '', `Size ${product.size} · ${product.color} · Còn ${product.stock} đôi`));
        details.append(element('span', 'advisor-product-link', 'Xem & chọn mua ↗'));
        card.append(img, details);
        cards.append(card);
      });
      article.append(cards);
      article.append(element('small', 'advisor-stock-note', 'Giá và tồn tại lúc tư vấn. Trang sản phẩm và giỏ hàng kiểm tra lại trước khi mua.'));
    }
    messages.append(article);
    if (turn.role === 'assistant') {
      messages.scrollTop += article.getBoundingClientRect().top - messages.getBoundingClientRect().top;
    } else scrollEnd();
  };
  const updateContext = answer => {
    if (answer.mode) mode.textContent = answer.mode === 'ai' ? 'AI · Có tra cứu danh mục' : answer.mode === 'fallback' ? 'AI tạm gián đoạn · Đang tra cứu danh mục' : 'Tư vấn theo danh mục · Chưa kết nối AI';
    criteria.hidden = !answer.criteria;
    criteria.textContent = answer.criteria ? `Đang chọn: ${answer.criteria}` : '';
    showSuggestions(answer.suggestions);
  };
  const setBusy = value => {
    busy = value;
    send.disabled = value;
    clear.disabled = value;
    input.readOnly = value;
    suggestions.querySelectorAll('button').forEach(button => { button.disabled = value; });
    messages.setAttribute('aria-busy', String(value));
  };
  const request = async (method, body) => {
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 25000);
    try {
      const response = await fetch(root.dataset.endpoint, {
        method, signal: controller.signal, credentials: 'same-origin',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
        ...(body ? { body: JSON.stringify(body) } : {}),
      });
      if (!response.ok) {
        if (response.status === 419) throw new Error('Phiên đã hết hạn. Tải lại trang để tiếp tục.');
        if (response.status === 429) throw new Error('Bạn hỏi hơi nhanh. Vui lòng chờ một phút rồi thử lại.');
        throw new Error('Chưa gửi được câu hỏi. Kiểm tra kết nối rồi thử lại.');
      }
      return await response.json();
    } finally { clearTimeout(timeout); }
  };
  const initialize = async () => {
    if (initialized) return;
    setBusy(true);
    status.textContent = 'Đang mở tư vấn…';
    try {
      const data = await request('GET');
      messages.replaceChildren();
      (data.messages.length ? data.messages : [greeting]).forEach(addMessage);
      updateContext({ mode: data.mode, ...(data.messages.filter(turn => turn.role === 'assistant').at(-1) || {}) });
      initialized = true;
      status.textContent = '';
    } catch { status.textContent = 'Chưa tải được cuộc trò chuyện. Đóng rồi mở lại để thử.'; }
    finally { setBusy(false); }
  };
  const open = async opener => {
    lastOpener = opener || toggle;
    panel.hidden = false;
    toggle.setAttribute('aria-expanded', 'true');
    await initialize();
    input.focus({ preventScroll: true });
  };
  const close = () => { panel.hidden = true; toggle.setAttribute('aria-expanded', 'false'); lastOpener?.focus({ preventScroll: true }); };
  const submit = async () => {
    const text = input.value.trim();
    if (busy || !initialized || !text) return;
    setBusy(true);
    status.textContent = 'Đang tìm lựa chọn cho bạn…';
    try {
      const answer = await request('POST', { message: text, ...(root.dataset.productId ? { product_id: Number(root.dataset.productId) } : {}) });
      addMessage({ role: 'user', text });
      addMessage({ role: 'assistant', ...answer });
      input.value = '';
      updateContext(answer);
      status.textContent = '';
    } catch (error) {
      if (error.name === 'AbortError') initialized = false;
      status.textContent = error.name === 'AbortError' ? 'Tư vấn đang mất nhiều thời gian. Đóng rồi mở lại khung chat để tải kết quả.' : error.message;
    }
    finally { setBusy(false); input.focus({ preventScroll: true }); }
  };
  toggle.addEventListener('click', () => panel.hidden ? open(toggle) : close());
  root.querySelector('[data-advisor-close]').addEventListener('click', close);
  root.querySelector('[data-advisor-form]').addEventListener('submit', event => { event.preventDefault(); submit(); });
  input.addEventListener('keydown', event => { if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) { event.preventDefault(); submit(); } });
  root.addEventListener('keydown', event => { if (event.key === 'Escape' && !panel.hidden) { event.stopPropagation(); close(); } });
  clear.addEventListener('click', async () => {
    if (busy) return;
    setBusy(true);
    try { await request('DELETE'); messages.replaceChildren(); addMessage(greeting); updateContext({ criteria: '', suggestions: initialSuggestions }); status.textContent = 'Đã bắt đầu cuộc trò chuyện mới.'; }
    catch { status.textContent = 'Chưa xóa được cuộc trò chuyện. Vui lòng thử lại.'; }
    finally { setBusy(false); }
  });
  document.querySelectorAll('[data-open-advisor]').forEach(button => button.addEventListener('click', async () => {
    await open(button);
    if (button.dataset.advisorPrompt) { input.value = button.dataset.advisorPrompt; submit(); }
  }));
});
