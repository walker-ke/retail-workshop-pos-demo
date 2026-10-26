jQuery(function () {
 'use strict';
 const source = document.getElementById('hubCatalog');
 if (!source || !window.ProductSearch || !jQuery.fn.modal) return;
 const data = JSON.parse(source.textContent), products = data.products.map(window.ProductSearch.prepare);
 const el = id => document.getElementById(id);
 const search = el('hubStockSearch'), brand = el('hubStockBrand'), quantityFilter = el('hubStockQuantityFilter'), size = el('hubStockPageSize');
 const rows = el('hubStockRows'), editor = el('hubStockEditor'), modal = jQuery(editor);
 const form = el('hubStockForm'), save = el('hubStockSave'), action = el('hubStockAction'), quantity = el('hubStockQuantity');
 let page = 0, frame, selected = null, pending = null, generation = 0, submitting = false, opener = null;
 const balance = product => Number(data.balances[product.id] || 0);
 [...new Set(products.map(p => p.brand).filter(Boolean))].sort().forEach(name => {
  const option = document.createElement('option'); option.value = name; option.textContent = name; brand.appendChild(option);
 });
 function cell(row, value) { const td = document.createElement('td'); td.textContent = value; row.appendChild(td); return td; }
 function render() {
  let matches = search.value.trim() ? ProductSearch.rankedProducts(products, search.value.trim()) : products;
  matches = matches.filter(p => (!brand.value || p.brand === brand.value) && (quantityFilter.value === 'all' || (quantityFilter.value === 'positive' ? balance(p) > 0 : balance(p) === 0)));
  const count = Number(size.value), pages = Math.max(1, Math.ceil(matches.length / count)); page = Math.min(page, pages - 1);
  rows.replaceChildren();
  matches.slice(page * count, (page + 1) * count).forEach(product => {
   const row = document.createElement('tr'); cell(row, product.name); cell(row, balance(product));
   const button = document.createElement('button'); button.type = 'button'; button.className = 'btn btn-outline-primary btn-sm'; button.textContent = 'Adjust'; button.setAttribute('aria-label', 'Adjust ' + product.name);
   button.addEventListener('click', () => open(product, button)); cell(row, '').appendChild(button); rows.appendChild(row);
  });
  if (!matches.length) { const row = document.createElement('tr'); const td = cell(row, 'No matching products.'); td.colSpan = 3; rows.appendChild(row); }
  el('hubStockResultsStatus').textContent = matches.length + ' products' + (matches.length ? ' · Showing ' + (page * count + 1) + '–' + Math.min((page + 1) * count, matches.length) : '');
  el('hubStockPageLabel').textContent = 'Page ' + (page + 1) + ' of ' + pages;
  el('hubStockPrevious').disabled = page === 0; el('hubStockNext').disabled = page >= pages - 1;
 }
 function cancelLoad() { generation++; if (pending) pending.abort(); pending = null; }
 async function loadStock() {
  cancelLoad(); const revision = generation, productId = selected.id, controller = new AbortController(); pending = controller;
  const timeout = setTimeout(() => controller.abort(), 12000);
  el('hubStockCurrent').textContent = '—'; el('hubStockRetry').hidden = true; save.disabled = true;
  el('hubStockLoadStatus').textContent = 'Loading current stock…';
  try {
   const response = await fetch(data.stockUrl.replace('/products/0/', '/products/' + productId + '/') + '?hub_only=1', {headers: {'Accept': 'application/json'}, cache: 'no-store', signal: controller.signal});
   if (!response.ok) throw new Error('Stock unavailable');
   const result = await response.json(); if (revision !== generation || !selected || selected.id !== productId) return;
   data.balances[productId] = Number(result.hub_quantity); el('hubStockCurrent').textContent = result.hub_quantity; el('hubStockExpectedQuantity').value = result.hub_quantity;
   el('hubStockLoadStatus').textContent = '';
   save.disabled = submitting; render();
  } catch (_) {
   if (revision !== generation) return;
   el('hubStockLoadStatus').textContent = 'Could not load stock. Retry before saving.'; el('hubStockRetry').hidden = false;
  } finally { clearTimeout(timeout); if (revision === generation) pending = null; }
 }
 function updateAction() { quantity.min = action.value === 'count' ? '0' : '1'; el('hubStockQuantityLabel').textContent = action.value === 'count' ? 'New total quantity in hub' : 'Quantity to add'; }
 function open(product, button) {
  if (submitting) return;
  selected = product; opener = button; form.reset(); el('hubStockProductId').value = product.id; el('hubStockProductName').textContent = product.name; updateAction(); modal.modal('show'); loadStock();
 }
 search.addEventListener('input', () => { page = 0; clearTimeout(frame); frame = setTimeout(render, 180); });
 [brand, quantityFilter, size].forEach(input => input.addEventListener('change', () => { page = 0; render(); }));
 el('hubStockPrevious').addEventListener('click', () => { page--; render(); }); el('hubStockNext').addEventListener('click', () => { page++; render(); });
 el('hubStockRefresh').addEventListener('click', () => window.location.reload());
 el('hubStockRetry').addEventListener('click', () => { if (selected) loadStock(); }); action.addEventListener('change', updateAction);
 editor.addEventListener('hidden.bs.modal', () => { cancelLoad(); selected = null; el('hubStockProductId').value = ''; if (opener) opener.focus(); });
 form.addEventListener('submit', event => {
  if (!selected || save.disabled || submitting || !form.reportValidity()) { event.preventDefault(); return; }
  if (action.value === 'count' && !window.confirm('Set this hub’s total for ' + selected.name + ' to ' + quantity.value + '?')) { event.preventDefault(); return; }
  submitting = true; save.disabled = true; save.textContent = 'Saving…';
 });
 window.addEventListener('pageshow', event => { if (event.persisted) window.location.reload(); });
 render();
});
