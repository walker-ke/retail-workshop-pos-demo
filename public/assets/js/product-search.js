// Shared product matching for POS and shop stock requests.
(function (root, factory) {
    if (typeof module === 'object' && module.exports) module.exports = factory();
    else root.ProductSearch = factory();
})(typeof window !== 'undefined' ? window : this, function () {
    'use strict';
    // Search matching: exact text first, then bounded spelling corrections.
    function searchWords(text) {
        var words = text.match(/[a-z0-9]+/g) || [];
        var candidates = words.slice();
        // Joined phrases let each query token match names such as "sky blue".
        for (var i = 0; i < words.length; i++) {
            var joined = words[i];
            for (var j = i + 1; j < Math.min(words.length, i + 3); j++) {
                joined += words[j];
                candidates.push(joined);
            }
        }
        return Array.from(new Set(candidates));
    }
    var cachedQuery = null;
    var wordScores = new Map();
    function distance(a, b, limit) {
        if (Math.abs(a.length - b.length) > limit) return limit + 1;
        var previous = Array.from({length: b.length + 1}, function (_, i) { return i; });
        var beforePrevious;
        for (var i = 1; i <= a.length; i++) {
            var current = [i];
            var minimum = i;
            for (var j = 1; j <= b.length; j++) {
                current[j] = Math.min(current[j - 1] + 1, previous[j] + 1, previous[j - 1] + (a[i - 1] === b[j - 1] ? 0 : 1));
                if (i > 1 && j > 1 && a[i - 1] === b[j - 2] && a[i - 2] === b[j - 1]) current[j] = Math.min(current[j], beforePrevious[j - 2] + 1);
                minimum = Math.min(minimum, current[j]);
            }
            if (minimum > limit) return limit + 1;
            beforePrevious = previous;
            previous = current;
        }
        return previous[b.length];
    }
    function matchScore(product, term) {
        if (!term) return 0;
        var compact = term.replace(/\s+/g, '');
        if (!compact) return 0;
        if ((product.compactCode || product.searchCode.replace(/\s+/g, '')) === compact) return 0;
        if ((product.compactName || product.searchName.replace(/\s+/g, '')).includes(compact) || (product.compactBrand || product.searchBrand.replace(/\s+/g, '')).includes(compact)) return 1;
        if (product.searchName.includes(term) || product.searchCode.includes(term) || product.searchBrand.includes(term)) return 1;
        if (cachedQuery !== term) { cachedQuery = term; wordScores.clear(); }
        var tokens = term.split(/\s+/);
        var score = 0;
        for (var token of tokens) {
            if (!/^[a-z0-9]+$/.test(token)) return Infinity;
            var numeric = /^\d+$/.test(token);
            var limit = numeric || token.length < 3 ? 0 : (token.length >= 5 ? 2 : 1);
            var best = limit + 1;
            for (var word of product.searchWords) {
                var key = token + ':' + word;
                if (!wordScores.has(key)) {
                    var cost = (numeric ? word === token : word.includes(token)) ? 0 : (limit ? distance(token, word, limit) : 1);
                    // Two corrections need a shared prefix to avoid broad, unrelated matches.
                    if (cost === 2 && token.slice(0, 2) !== word.slice(0, 2)) cost = limit + 1;
                    wordScores.set(key, cost);
                }
                best = Math.min(best, wordScores.get(key));
                if (best === 0) break;
            }
            if (best > limit) return Infinity;
            score += best;
        }
        return 10 + score;
    }
    function rankedProducts(products, value, brand) {
        value = String(value || '').trim().toLowerCase();
        return products.filter(function (product) { return !brand || brand === 'all' || product.searchBrand === brand; })
            .map(function (product) { return {product: product, score: matchScore(product, value)}; })
            .filter(function (result) { return Number.isFinite(result.score); })
            .sort(function (a, b) { return a.score - b.score; })
            .map(function (result) { return result.product; });
    }

    function prepare(product) {
        product.searchName = String(product.name || '').toLowerCase();
        product.searchCode = String(product.code || '').toLowerCase();
        product.searchBrand = String(product.brand || '').trim().toLowerCase();
        product.compactName = product.searchName.replace(/\s+/g, '');
        product.compactBrand = product.searchBrand.replace(/\s+/g, '');
        product.compactCode = product.searchCode.replace(/\s+/g, '');
        product.searchWords = searchWords(product.searchName + ' ' + product.searchBrand);
        return product;
    }
    return {searchWords:searchWords, matchScore:matchScore, rankedProducts:rankedProducts, prepare:prepare};
});
