<?php
// PHP beépített szerver router: php -S localhost:8080 -t <wp core> dev/router.php
// A /wp-content/ kéréseket a repó wp-content mappájából szolgálja ki.
$repo = dirname(__DIR__);
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$root = $_SERVER['DOCUMENT_ROOT'];

if (strpos($uri, '/wp-content/') === 0) {
	$file = $repo.$uri;
	if (is_file($file)) {
		$types = ['css' => 'text/css', 'js' => 'application/javascript', 'svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'gif' => 'image/gif', 'woff2' => 'font/woff2', 'woff' => 'font/woff', 'json' => 'application/json'];
		$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
		if ($ext === 'php') { chdir(dirname($file)); require $file; return true; }
		header('Content-Type: '.($types[$ext] ?? 'application/octet-stream'));
		readfile($file);
		return true;
	}
}
if ($uri !== '/' && is_file($root.$uri)) return false;
if (is_dir($root.$uri) && is_file(rtrim($root.$uri, '/').'/index.php')) { require rtrim($root.$uri, '/').'/index.php'; return true; }
require $root.'/index.php';
