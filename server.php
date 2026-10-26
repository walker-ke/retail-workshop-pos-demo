<?php
// Local development router: cache and compress static assets, never POS responses.
$public = __DIR__.'/public';
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
$file = realpath($public.$path);
$types = ['css'=>'text/css', 'js'=>'application/javascript', 'svg'=>'image/svg+xml', 'woff'=>'font/woff', 'woff2'=>'font/woff2', 'png'=>'image/png', 'jpg'=>'image/jpeg', 'jpeg'=>'image/jpeg', 'ico'=>'image/x-icon', 'webp'=>'image/webp'];
$extension = $file ? strtolower(pathinfo($file, PATHINFO_EXTENSION)) : '';
if ($file && str_starts_with($file, $public.'/') && is_file($file) && isset($types[$extension]) && preg_match('#^/(assets|build|vendor)/#', $path)) {
    $mtime = filemtime($file);
    $etag = '"'.hash_file('sha256', $file).'"';
    header('Content-Type: '.$types[$extension]);
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: public, max-age='.(str_starts_with($path, '/build/') ? '31536000, immutable' : '86400'));
    header('ETag: '.$etag);
    header('Last-Modified: '.gmdate('D, d M Y H:i:s', $mtime).' GMT');
    header('Vary: Accept-Encoding');
    if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
        http_response_code(304);
        return;
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') return;
    $content = file_get_contents($file);
    if (in_array($extension, ['css', 'js', 'svg'], true) && str_contains($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', 'gzip')) {
        header('Content-Encoding: gzip');
        $content = gzencode($content, 6);
    }
    header('Content-Length: '.strlen($content));
    echo $content;
    return;
}
if ($path !== '/' && $file && str_starts_with($file, $public.'/') && is_file($file)) return false;
require $public.'/index.php';
