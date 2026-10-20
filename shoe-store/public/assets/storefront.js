document.addEventListener('DOMContentLoaded', () => {
  const menuButton = document.querySelector('[data-shop-menu]');
  const menu = document.querySelector('#shop-menu');
  const closeMenu = () => { menu?.classList.remove('open'); menuButton?.setAttribute('aria-expanded', 'false'); };
  menuButton?.addEventListener('click', () => { const open = menu?.classList.toggle('open'); menuButton.setAttribute('aria-expanded', String(Boolean(open))); });
  menu?.querySelectorAll('a').forEach(link => link.addEventListener('click', closeMenu));
  document.addEventListener('keydown', event => { if (event.key === 'Escape') closeMenu(); });
  document.addEventListener('click', event => { if (menu && !menu.contains(event.target) && !menuButton?.contains(event.target)) closeMenu(); });
  const dialog = document.querySelector('[data-size-dialog]');
  document.querySelector('[data-open-size]')?.addEventListener('click', () => dialog?.showModal());
  const variant = document.querySelector('[data-shop-variant]');
  const quantity = document.querySelector('[data-shop-quantity]');
  const buttons = document.querySelectorAll('[data-pick-variant]');
  const syncVariant = () => {
    if (!variant) return;
    buttons.forEach(button => button.setAttribute('aria-pressed', String(button.dataset.pickVariant === variant.value)));
    const stock = Number(variant.selectedOptions[0]?.dataset.stock || 0);
    if (quantity) { quantity.max = String(Math.min(stock, 1000)); if (Number(quantity.value) > stock && stock > 0) quantity.value = String(stock); }
  };
  buttons.forEach(button => button.addEventListener('click', () => { if (variant) { variant.value = button.dataset.pickVariant; variant.dispatchEvent(new Event('change', { bubbles: true })); } }));
  variant?.addEventListener('change', syncVariant);
  if (variant && Number(variant.selectedOptions[0]?.dataset.stock || 0) === 0) {
    const available = Array.from(variant.options).find(option => Number(option.dataset.stock) > 0);
    if (available) { variant.value = available.value; variant.dispatchEvent(new Event('change', { bubbles: true })); }
  }
  syncVariant();
  document.querySelectorAll('[data-quantity-step]').forEach(button => button.addEventListener('click', () => {
    if (!quantity) return;
    quantity.value = String(Math.max(1, Math.min(Number(quantity.max) || 1, (Number(quantity.value) || 1) + Number(button.dataset.quantityStep))));
    quantity.dispatchEvent(new Event('input', { bubbles: true }));
  }));
  document.querySelectorAll('[data-gallery-src]').forEach(button => button.addEventListener('click', () => { const img = document.querySelector('[data-gallery-image]'); if (img) img.src = button.dataset.gallerySrc; }));
  document.querySelectorAll('[data-toggle-password]').forEach(button => button.addEventListener('click', () => {
    const input = document.getElementById(button.dataset.togglePassword);
    if (!input) return;
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    button.textContent = show ? 'Ẩn' : 'Hiện';
    button.setAttribute('aria-label', show ? 'Ẩn mật khẩu' : 'Hiện mật khẩu');
  }));
});
