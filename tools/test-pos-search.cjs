const fs = require('fs');
const vm = require('vm');
const assert = require('assert');
const context = vm.createContext({});
vm.runInContext(fs.readFileSync(require('path').join(__dirname, '../public/assets/js/product-search.js'), 'utf8'), context);
vm.runInContext('var searchWords=ProductSearch.searchWords; var matchScore=ProductSearch.matchScore; function rankedProducts(value,brand){return ProductSearch.rankedProducts(products,value,brand);}', context);
function product(name, code = '618') {
    return {searchName: name.toLowerCase(), searchBrand: '', searchCode: code, searchWords: context.searchWords(name.toLowerCase())};
}
const shoe = product('Bata shoes size 23');
for (const query of ['shooe', 'shoes', 'shose', 'bata shooe']) assert(Number.isFinite(context.matchScore(shoe, query)), query);
assert.strictEqual(context.matchScore(shoe, '618'), 0);
assert.strictEqual(context.matchScore(shoe, '619'), Infinity);
assert.strictEqual(context.matchScore(product('School trousers'), 'shooe'), Infinity);
assert.strictEqual(context.matchScore(product('Uniform'), 'xy'), Infinity);
assert(context.matchScore(shoe, 'shoes') < context.matchScore(shoe, 'shooe'));
assert(Number.isFinite(context.matchScore(product('School shirts'), 'shirtt')));
const catalog = Array.from({length: 2910}, (_, i) => product('Bata shoes size ' + i, String(i)));
context.products = catalog;
const start = performance.now();
assert.strictEqual(context.rankedProducts('shooe').length, 2910);
console.log('Search checks passed; 2,910 products searched in ' + (performance.now() - start).toFixed(1) + ' ms.');

for (const query of ['schoolshirt', 'school shirt', 'school   shirt', 'sch ool sh irt']) {
    assert(Number.isFinite(context.matchScore(product('School shirt size 24'), query)), query);
}
assert.strictEqual(context.matchScore(shoe, '6 1 8'), 0);
console.log('Spacing checks passed.');

for (const name of ['Sky blue shirts size 24', 'Shirts sky blue long sleeves', 'Sky blue shirt size 30']) {
    assert(Number.isFinite(context.matchScore(product(name), 'shirtt skyblue')), name);
}
assert.strictEqual(context.matchScore(product('Red shirts'), 'shirtt skyblue'), Infinity);
console.log('Combined typo and phrase checks passed.');

const universalCases = [
    ['Sky blue shirts size 24', 'shirtt skyblue 24'],
    ['Navy blue trousers size 30', 'trouserr navyblue 30'],
    ['White rubber shoes size 38', 'shooe whiterubber 38'],
    ['A4 graph book 96 pgs', 'graphbook a4 96'],
    ['School bags blue', 'blue bagss school'],
    ['Red sweater size 28', 'sweatter red 28'],
];
for (const [name, query] of universalCases) assert(Number.isFinite(context.matchScore(product(name), query)), query);
assert.strictEqual(context.matchScore(product('Sky blue shirts size 24'), 'shirtt skyblue 25'), Infinity);
console.log('Catalog-wide names, colors, word order and size checks passed.');
