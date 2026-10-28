// Progressive enhancement only: every form still posts and validates on the server without this file.
const gbp = new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP' });
const pence = (v) => {
  const s = String(v).replace(/[£,\s]/g, '');
  return /^\d{1,7}(\.\d{1,2})?$/.test(s) ? Math.round(parseFloat(s) * 100) : 0;
};
const size = (n) => (n >= 1048576 ? (n / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(n / 1024)) + ' KB');
const X_ICON = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>';

document.addEventListener('DOMContentLoaded', () => {
  const toast = document.querySelector('[data-toast]');
  if (toast) setTimeout(() => toast.classList.add('is-gone'), 5000);

  const form = document.querySelector('[data-claim-form]');
  if (form) claimForm(form);

  document.querySelectorAll('[data-decide]').forEach((decide) => {
    decide.addEventListener('submit', (e) => {
      const note = decide.querySelector('[data-note]');
      const err = decide.querySelector('[data-note-err]');
      if (e.submitter?.value !== 'approve' && !note.value.trim()) {
        e.preventDefault();
        err.hidden = false;
        note.setAttribute('aria-invalid', 'true');
        note.focus();
        return;
      }
      // Disable after the browser has captured the clicked button's value, or the decision is lost.
      setTimeout(() => decide.querySelectorAll('button').forEach((b) => (b.disabled = true)));
    });
  });

  document.querySelectorAll('[data-viewer]').forEach(viewer);

  // Roles save as soon as a box is ticked; the Save button stays for no-JS.
  document.querySelectorAll('[data-autosave]').forEach((f) => {
    f.querySelector('[data-save]').hidden = true;
    f.addEventListener('change', () => f.requestSubmit());
  });
});

function viewer(root) {
  const link = root.querySelector('[data-viewer-link]');
  const name = root.querySelector('[data-viewer-name]');
  root.querySelectorAll('.thumbs button').forEach((b) => {
    b.addEventListener('click', () => {
      root.querySelectorAll('.thumbs button').forEach((x) => x.setAttribute('aria-pressed', String(x === b)));
      link.href = b.dataset.src;
      name.textContent = b.dataset.name;
      link.replaceChildren();
      if (b.dataset.img === '1') {
        const img = document.createElement('img');
        img.src = b.dataset.src;
        img.alt = 'Receipt: ' + b.dataset.name;
        link.append(img);
      } else {
        const span = document.createElement('span');
        span.className = 'pdf';
        const big = document.createElement('b');
        big.textContent = b.textContent.trim();
        span.append(big, 'Open ' + b.dataset.name);
        link.append(span);
      }
    });
  });
}

function claimForm(form) {
  const lines = form.querySelector('[data-lines]');
  const tpl = form.querySelector('[data-line-template]');
  let next = lines.children.length;

  const recount = () => {
    const rows = lines.querySelectorAll('[data-line]');
    const total = [...lines.querySelectorAll('[data-amount]')].reduce((t, i) => t + pence(i.value), 0);
    form.querySelectorAll('[data-total]').forEach((el) => (el.textContent = gbp.format(total / 100)));
    form.querySelector('[data-count]').textContent = rows.length + (rows.length === 1 ? ' line' : ' lines');
    rows.forEach((r) => (r.querySelector('[data-remove-line]').disabled = rows.length === 1));
  };

  form.querySelector('[data-add-line]').addEventListener('click', () => {
    lines.insertAdjacentHTML('beforeend', tpl.innerHTML.replaceAll('__i__', String(next++)));
    const row = lines.lastElementChild;
    row.classList.add('item-new');
    // Same day as the line above is the usual case: one trip, several receipts.
    row.querySelector('input[type=date]').value = row.previousElementSibling?.querySelector('input[type=date]')?.value || new Date().toISOString().slice(0, 10);
    row.querySelector('select').focus();
    recount();
  });
  lines.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-remove-line]');
    if (!btn || lines.children.length === 1) return;
    btn.closest('[data-line]').remove();
    recount();
  });
  lines.addEventListener('input', (e) => e.target.matches('[data-amount]') && recount());
  recount();

  // Receipts: keep a running list so choosing files twice adds rather than replaces.
  const input = form.querySelector('[data-proof]');
  const drop = form.querySelector('[data-drop]');
  const list = form.querySelector('[data-picked]');
  let picked = new DataTransfer();

  const sync = () => {
    input.files = picked.files;
    list.querySelectorAll('[data-new]').forEach((li) => li.remove());
    [...picked.files].forEach((f, idx) => {
      const li = document.createElement('li');
      li.dataset.new = '';
      const isImg = f.type.startsWith('image/') && !/hei[cf]/.test(f.type);
      const pic = document.createElement(isImg ? 'img' : 'span');
      pic.className = 'pk-img';
      if (isImg) { pic.src = URL.createObjectURL(f); pic.alt = ''; } else pic.textContent = (f.name.split('.').pop() || 'FILE').toUpperCase().slice(0, 4);
      const cap = document.createElement('div');
      cap.className = 'pk-cap';
      const tooBig = f.size > 10485760;
      cap.textContent = tooBig ? 'Over 10 MB' : f.name + ' · ' + size(f.size);
      if (tooBig) cap.classList.add('err');
      const rm = document.createElement('button');
      rm.type = 'button';
      rm.className = 'pk-x';
      rm.setAttribute('aria-label', 'Remove ' + f.name);
      rm.innerHTML = X_ICON;
      rm.addEventListener('click', () => {
        const keep = new DataTransfer();
        [...picked.files].forEach((x, j) => j !== idx && keep.items.add(x));
        picked = keep;
        sync();
      });
      li.append(pic, cap, rm);
      list.append(li);
    });
    drop.classList.toggle('has-files', list.children.length > 0);
  };
  drop.classList.toggle('has-files', list.children.length > 0);

  const add = (files) => { [...files].forEach((f) => picked.items.add(f)); sync(); };
  input.addEventListener('change', () => add(input.files));
  ['dragenter', 'dragover'].forEach((t) => drop.addEventListener(t, (e) => { e.preventDefault(); drop.classList.add('is-over'); }));
  ['dragleave', 'drop'].forEach((t) => drop.addEventListener(t, () => drop.classList.remove('is-over')));
  drop.addEventListener('drop', (e) => { e.preventDefault(); add(e.dataTransfer.files); });
  list.addEventListener('change', (e) => {
    if (e.target.matches('[data-remove-existing]')) e.target.closest('li').classList.toggle('is-removed', e.target.checked);
  });

  form.addEventListener('submit', () => {
    const btn = form.querySelector('[data-submit]');
    setTimeout(() => { btn.disabled = true; btn.textContent = 'Sending…'; });
  });
}
