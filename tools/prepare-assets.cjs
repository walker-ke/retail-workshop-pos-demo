const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const out = path.join(root, 'public/assets/vendor');
fs.mkdirSync(out, {recursive: true});
function copy(from, to) {
  fs.copyFileSync(path.join(root, 'node_modules', from), path.join(out, to));
}
copy('jquery/dist/jquery.min.js', 'jquery-3.7.1.min.js');
copy('toastr/build/toastr.min.js', 'toastr.min.js');
copy('toastr/build/toastr.min.css', 'toastr.min.css');
copy('datatables.net/js/jquery.dataTables.min.js', 'jquery.dataTables.min.js');
copy('datatables.net-bs5/js/dataTables.bootstrap5.min.js', 'dataTables.bootstrap5.min.js');
copy('datatables.net-bs5/css/dataTables.bootstrap5.min.css', 'dataTables.bootstrap5.min.css');
const fontDir = path.join(root, 'node_modules/@fortawesome/fontawesome-free');
fs.cpSync(path.join(fontDir, 'webfonts'), path.join(out, 'webfonts'), {recursive: true});
let css = fs.readFileSync(path.join(fontDir, 'css/all.min.css'), 'utf8').replaceAll('../webfonts/', '/assets/vendor/webfonts/');
fs.writeFileSync(path.join(out, 'fontawesome.css'), css);
fs.writeFileSync(path.join(root, 'resources/sass/_icons.generated.scss'), css);
console.log('Browser assets prepared from locked npm dependencies.');

const licenses = path.join(out, 'licenses');
fs.mkdirSync(licenses, {recursive:true});
for (const [name, file] of [['jquery','LICENSE.txt'],['bootstrap','LICENSE'],['toastr','LICENSE'],['datatables.net','License.txt'],['datatables.net-bs5','License.txt'],['@fortawesome/fontawesome-free','LICENSE.txt']]) {
 const source=path.join(root,'node_modules',name,file);
 if(fs.existsSync(source)) fs.copyFileSync(source,path.join(licenses,name.replaceAll('/','-')+'.txt'));
}
