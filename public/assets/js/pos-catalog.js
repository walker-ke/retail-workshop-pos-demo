// Keep the catalog as data. Create product forms only for the visible page.
(function () {
    'use strict';
    var source = document.getElementById('posCatalog');
    var grid = document.getElementById('tileGrid');
    if (!source || !grid) return;
    var products = JSON.parse(source.textContent).map(function (product) {
        product.searchName = String(product.name || '').toLowerCase();
        product.searchCode = String(product.code || '').toLowerCase();
        product.searchBrand = String(product.brand || '').trim().toLowerCase();
        product.compactName = product.searchName.replace(/\s+/g, '');
        product.compactBrand = product.searchBrand.replace(/\s+/g, '');
        product.compactCode = product.searchCode.replace(/\s+/g, '');
        product.searchWords = searchWords(product.searchName + ' ' + product.searchBrand);
        return product;
    });
    var codes = new Map();
    products.forEach(function (product) {
        if (!codes.has(product.searchCode)) codes.set(product.searchCode, product);
    });
    var csrf = document.querySelector('meta[name="csrf-token"]').content;

    function label(className, text) {
        var span = document.createElement('span');
        span.className = className;
        span.textContent = text;
        return span;
    }

    function productForm(product) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = source.dataset.addUrl;
        form.className = 'add-cart-form';
        var values = { _token: csrf, id: product.id, name: product.name + ' - ' + (product.brand || ''), quantity: 1, price: product.price };
        Object.keys(values).forEach(function (name) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = values[name];
            form.appendChild(input);
        });
        return form;
    }

    function productTile(product) {
        var wrap = document.createElement('div');
        wrap.className = 'tile-wrap';
        var form = productForm(product);
        var button = document.createElement('button');
        button.type = 'submit';
        button.className = 'tile';
        button.disabled = Number(product.quantity) === 0;
        button.appendChild(label('tile-name', product.name));
        var price = label('tile-price', '');
        price.appendChild(label('currency', 'KES'));
        price.appendChild(document.createTextNode(' ' + product.price));
        button.appendChild(price);
        var qty = Number(product.quantity);
        if (qty === 0) button.appendChild(label('tile-stock out', 'Out of stock'));
        form.appendChild(button);
        wrap.appendChild(form);
        return wrap;
    }

    function searchWords(text) { return window.ProductSearch.searchWords(text); }
    function matchScore(product, term) { return window.ProductSearch.matchScore(product, term); }
    function rankedProducts(value, brand) { return window.ProductSearch.rankedProducts(products, value, brand); }

    var search = document.getElementById('posSearch');
    var chips = Array.from(document.querySelectorAll('#categoryChips .chip'));
    var pageSize = document.getElementById('pageSizeSelect');
    var prev = document.getElementById('prevPage');
    var next = document.getElementById('nextPage');
    var indicator = document.getElementById('pageIndicator');
    var category = 'all';
    var term = '';
    var page = 1;
    var renderFrame;

    function render() {
        var filtered = rankedProducts(term, category);
        var size = parseInt(pageSize.value, 10);
        var pages = Math.max(1, Math.ceil(filtered.length / size));
        page = Math.max(1, Math.min(page, pages));
        var fragment = document.createDocumentFragment();
        filtered.slice((page - 1) * size, page * size).forEach(function (product) {
            fragment.appendChild(productTile(product));
        });
        grid.replaceChildren(fragment);
        indicator.textContent = 'Page ' + page + ' / ' + pages + ' (' + filtered.length + ' items)';
        prev.disabled = page <= 1;
        next.disabled = page >= pages;
    }

    search.addEventListener('input', function () {
        term = this.value.trim().toLowerCase();
        page = 1;
        cancelAnimationFrame(renderFrame);
        renderFrame = requestAnimationFrame(render);
    });
    chips.forEach(function (chip) {
        chip.addEventListener('click', function () {
            chips.forEach(function (item) { item.classList.remove('active'); });
            chip.classList.add('active');
            category = chip.dataset.category;
            page = 1;
            render();
        });
    });
    pageSize.addEventListener('change', function () { page = 1; render(); });
    prev.addEventListener('click', function () { page--; render(); });
    next.addEventListener('click', function () { page++; render(); });
    $('#browseModal').on('shown.bs.modal', function () { search.focus(); });
    grid.addEventListener('submit', function (event) {
        if (!event.target.classList.contains('add-cart-form')) return;
        event.preventDefault();
        window.submitAddToCart(event.target);
    });
    render();

    var barcode = document.getElementById('barcodeInput');
    var results = document.getElementById('topbarSearchResults');
    if (!barcode || !results) return;
    var searchFrame;

    function findProducts(value, limit) {
        return rankedProducts(value).slice(0, limit);
    }
    function closeResults() {
        cancelAnimationFrame(searchFrame);
        results.classList.remove('show');
        results.replaceChildren();
    }
    function addProduct(product) {
        if (Number(product.quantity) === 0) {
            toastr.error('That product is out of stock');
            return;
        }
        window.submitAddToCart(productForm(product));
    }
    function renderResults(value) {
        var found = rankedProducts(value);
        var fragment = document.createDocumentFragment();
        if (!found.length) fragment.appendChild(label('topbar-search-empty', 'No product found matching "' + value + '"'));
        found.forEach(function (product) {
            var row = document.createElement('button');
            row.type = 'button';
            row.className = 'topbar-search-row' + (Number(product.quantity) === 0 ? ' disabled' : '');
            row.appendChild(label('topbar-search-name', product.name));
            row.appendChild(label('topbar-search-price', 'KES ' + product.price));
            row.addEventListener('click', function () {
                addProduct(product);
                barcode.value = '';
                closeResults();
                barcode.focus();
            });
            fragment.appendChild(row);
        });
        results.replaceChildren(fragment);
        results.classList.add('show');
    }
    barcode.addEventListener('input', function () {
        var value = this.value.trim().toLowerCase();
        cancelAnimationFrame(searchFrame);
        if (!value) { closeResults(); return; }
        searchFrame = requestAnimationFrame(function () { renderResults(value); });
    });
    barcode.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') { closeResults(); return; }
        if (event.key !== 'Enter') return;
        event.preventDefault();
        var value = this.value.trim().toLowerCase();
        if (!value) return;
        var product = codes.get(value) || findProducts(value, 1)[0];
        if (!product) { toastr.error('No product found matching "' + value + '"'); return; }
        if (matchScore(product, value) >= 10) { renderResults(value); return; }
        addProduct(product);
        this.value = '';
        closeResults();
    });
    document.addEventListener('click', function (event) {
        if (event.target !== barcode && !results.contains(event.target)) closeResults();
    });
})();
