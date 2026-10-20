document.addEventListener('DOMContentLoaded', () => {
  const menu = document.querySelector('[data-menu]');
  menu?.addEventListener('click', () => {
    const expanded = document.querySelector('.sidebar')?.classList.toggle('open');
    menu.setAttribute('aria-expanded', String(expanded));
  });
  document.querySelectorAll('[data-print]').forEach(button => button.addEventListener('click', () => window.print()));
  const confirmation = document.querySelector('[data-confirmation]');
  let pendingForm = null;
  let pendingSubmitter = null;
  if (confirmation) {
    document.querySelectorAll('form[data-confirm]').forEach(form => form.addEventListener('submit', event => {
      if (form.dataset.confirmed === 'true') { delete form.dataset.confirmed; return; }
      event.preventDefault();
      pendingForm = form;
      pendingSubmitter = event.submitter;
      confirmation.querySelector('[data-confirm-message]').textContent = form.dataset.confirm;
      confirmation.returnValue = 'cancel';
      confirmation.showModal();
    }));
    confirmation.addEventListener('close', () => {
      if (confirmation.returnValue === 'confirm' && pendingForm) {
        pendingForm.dataset.confirmed = 'true';
        pendingForm.requestSubmit(pendingSubmitter);
      }
      pendingForm = null;
      pendingSubmitter = null;
    });
  }
  const lines = document.querySelector('[data-lines]');
  const template = document.querySelector('#line-template');
  if (lines && template) {
    let index = Number(lines.dataset.nextIndex || 0);
    const update = () => {
      let total = 0;
      lines.querySelectorAll('.transaction-row').forEach(row => {
        const selected = row.querySelector('select')?.selectedOptions[0];
        const price = row.querySelector('[data-cost]')?.value ?? selected?.dataset.price ?? 0;
        const quantity = row.querySelector('[data-quantity]')?.value ?? 0;
        total += Number(price) * Number(quantity);
      });
      document.querySelector('[data-total]').textContent = new Intl.NumberFormat('vi-VN').format(total) + ' đ';
    };
    const add = () => {
      const element = document.createElement('div');
      element.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(index++));
      lines.append(element.firstElementChild);
      update();
    };
    document.querySelector('[data-add-line]')?.addEventListener('click', add);
    lines.addEventListener('click', event => {
      const button = event.target.closest('[data-remove-line]');
      if (button && lines.children.length > 1) { button.closest('.transaction-row').remove(); update(); }
    });
    lines.addEventListener('input', update);
    lines.addEventListener('change', update);
    if (!lines.children.length) add();
    update();
  }
  const variant = document.querySelector('[data-shop-variant]');
  const updateVariant = () => {
    const option = variant.selectedOptions[0];
    document.querySelector('[data-variant-price]').textContent = new Intl.NumberFormat('vi-VN').format(Number(option.dataset.price || 0)) + ' đ';
    document.querySelector('[data-variant-stock]').textContent = 'Còn ' + (option.dataset.stock || 0) + ' đôi';
    const quantity = document.querySelector('[data-shop-quantity]');
    quantity.max = option.dataset.stock || 0;
    document.querySelector('[data-add-cart]').disabled = Number(option.dataset.stock || 0) < 1;
  };
  if (variant && variant.options.length) { variant.addEventListener('change', updateVariant); updateVariant(); }
});
