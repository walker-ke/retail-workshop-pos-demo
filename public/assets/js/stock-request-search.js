(function () {
    'use strict';
    var source = document.getElementById('transferCatalog');
    if (!source || !window.ProductSearch) return;
    var data = JSON.parse(source.textContent);
    var products = data.products.map(window.ProductSearch.prepare);
    var balances = new Map(data.stock.map(function (s) { return [s.product_id + ':' + s.shop_id, s]; }));
    var search = document.getElementById('transferSearch');
    var results = document.getElementById('transferSearchResults');
    var status = document.getElementById('transferSearchStatus');
    var previous = document.getElementById('transferPrevious');
    var next = document.getElementById('transferNext');
    var indicator = document.getElementById('transferSearchPage');
    var pagination = document.getElementById('transferPagination');
    var page = 1, frame;
    var csrf = document.querySelector('meta[name="csrf-token"]').content;
    search.value = new URLSearchParams(location.search).get('q') || search.value;
    function element(tag, text, className) {
        var node = document.createElement(tag);
        if (text !== undefined) node.textContent = text;
        if (className) node.className = className;
        return node;
    }
    function uuid() {
        if (crypto.randomUUID) return crypto.randomUUID();
        var bytes = crypto.getRandomValues(new Uint8Array(16));
        bytes[6] = (bytes[6] & 15) | 64; bytes[8] = (bytes[8] & 63) | 128;
        var hex = Array.from(bytes, function (b) { return b.toString(16).padStart(2, '0'); }).join('');
        return hex.slice(0,8)+'-'+hex.slice(8,12)+'-'+hex.slice(12,16)+'-'+hex.slice(16,20)+'-'+hex.slice(20);
    }
    function hidden(form, name, value) {
        var input = element('input'); input.type = 'hidden'; input.name = name; input.value = value; form.appendChild(input);
    }
    function requestForm(product, shop, available) {
        var form = element('form'); form.method = 'POST'; form.action = source.dataset.requestUrl;
        hidden(form, '_token', csrf); hidden(form, 'request_key', uuid());
        hidden(form, 'product_id', product.id); hidden(form, 'from_shop_id', shop.id);
        var label = element('label', 'Quantity');
        var quantity = element('input', undefined, 'form-control');
        quantity.type = 'number'; quantity.name = 'quantity'; quantity.min = 1; quantity.max = Math.min(999999, available); quantity.value = 1; quantity.required = true;
        label.appendChild(quantity); form.appendChild(label);
        label = element('label', 'Note (optional)');
        var note = element('input', undefined, 'form-control'); note.name = 'note'; note.maxLength = 500;
        label.appendChild(note); form.appendChild(label);
        var button = element('button', 'Send request', 'btn btn-primary'); button.type = 'submit'; form.appendChild(button);
        return form;
    }
    function card(product) {
        var article = element('article', undefined, 'transfer-card');
        article.appendChild(element('h3', product.name, 'h6'));
        var own = balances.get(product.id + ':' + data.receivingShopId) || {};
        var ownQuantity = Number(own.quantity || 0);
        var eligible = Boolean(data.receivingShopId) && ownQuantity <= 5;
        if (data.receivingShopId && !eligible) article.appendChild(element('p', 'Your shop has ' + ownQuantity + '. Requests are allowed only when you have 5 or fewer.', 'text-muted'));
        var shops = element('div', undefined, 'transfer-stock');
        data.shops.slice().sort(function (a, b) {
            return Number(b.id === data.receivingShopId) - Number(a.id === data.receivingShopId);
        }).forEach(function (shop) {
            var balance = balances.get(product.id + ':' + shop.id) || {};
            var reserved = Number(balance.reserved_quantity || 0);
            var available = Math.max(0, Number(balance.quantity || 0) - reserved);
            var canRequest = eligible && shop.id !== data.receivingShopId && available > 0;
            var row = element(canRequest ? 'details' : 'div', undefined, 'transfer-shop');
            var heading = canRequest ? element('summary') : row;
            heading.appendChild(element('strong', shop.name + (shop.id === data.receivingShopId ? ' (your shop)' : '')));
            heading.appendChild(element('span', available + ' available' + (reserved ? ' · ' + reserved + ' reserved' : ''), 'transfer-available'));
            if (canRequest) {
                heading.appendChild(element('span', 'Request stock', 'transfer-request-label'));
                row.appendChild(heading);
                row.addEventListener('toggle', function () {
                    if (row.open && !row.querySelector('form')) row.appendChild(requestForm(product, shop, available));
                });
            }
            shops.appendChild(row);
        });
        article.appendChild(shops); return article;
    }
    function render() {
        var term = search.value.trim();
        var matches = term ? window.ProductSearch.rankedProducts(products, term) : [];
        var pages = Math.max(1, Math.ceil(matches.length / 50));
        page = Math.max(1, Math.min(page, pages));
        var fragment = document.createDocumentFragment();
        matches.slice((page-1)*50, page*50).forEach(function (product) { fragment.appendChild(card(product)); });
        results.replaceChildren(fragment);
        status.textContent = !term ? 'Type an item name to see availability at each shop.' : matches.length ? matches.length + ' matching items — availability by shop below.' : 'No matching items. Try another name or fewer words.';
        pagination.hidden = !term;
        previous.disabled = page <= 1; next.disabled = page >= pages;
        indicator.textContent = matches.length ? 'Page ' + page + ' of ' + pages + ' · 50 items per page' : '';
    }
    search.addEventListener('input', function () {
        page = 1; cancelAnimationFrame(frame); frame = requestAnimationFrame(render);
    });
    previous.addEventListener('click', function () { page--; render(); });
    next.addEventListener('click', function () { page++; render(); });
    render();
})();
