$requestPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);

/**
 * 想定外URLの吸収
 * - /admin/index.php/public... に来たら /admin/index.php に 301 で戻す
 * - /admin/index.php/xxxx のように index.php の後ろに余計なパスが付いたら 404
 */
if ($requestPath !== '' && preg_match('#/admin/index\.php/public(?:/.*)?$#', $requestPath) === 1) {
    header('Location: ' . app_url('/admin/index.php'), true, 301);
    exit;
}

if ($requestPath !== '' && preg_match('#/admin/index\.php/(.+)$#', $requestPath) === 1) {
    http_response_code(404);
    exit('Not Found');
}