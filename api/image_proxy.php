<?php
// api/image_proxy.php - High-Performance Cached Image Proxy for MangaDex & CDN Content
// Bypasses anti-hotlinking and CORS restrictions while caching assets locally.

// Disable output buffering to stream directly
if (ob_get_level()) {
    ob_end_clean();
}

$rawUrl = trim($_GET['url'] ?? '');
if (empty($rawUrl)) {
    http_response_code(400);
    exit('Missing image URL parameter.');
}

// Decode URL if needed
$url = filter_var($rawUrl, FILTER_VALIDATE_URL);
if (!$url) {
    http_response_code(400);
    exit('Invalid image URL format.');
}

$parsed = parse_url($url);
$host = strtolower($parsed['host'] ?? '');

// Whitelist authorized image source domains
$isAllowed = false;
$allowedHosts = [
    'mangadex.org',
    'uploads.mangadex.org',
    'api.mangadex.org',
    'temp.compsci88.com',
    'asurascans.com',
    'anisascans.in',
    'mgread.io',
    'like.mgread.io'
];

foreach ($allowedHosts as $allowed) {
    if ($host === $allowed || str_ends_with($host, '.' . $allowed)) {
        $isAllowed = true;
        break;
    }
}

// Also allow MangaDex @Home Network nodes (*.mangadex.network)
if (!$isAllowed && (str_ends_with($host, '.mangadex.network') || $host === 'mangadex.network')) {
    $isAllowed = true;
}

if (!$isAllowed) {
    http_response_code(403);
    exit('Domain not authorized for proxy.');
}

// Local cache configuration
$cacheDir = __DIR__ . '/../cache/img_proxy';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0777, true);
}

$urlHash = md5($url);
$pathInfo = pathinfo($parsed['path'] ?? '');
$ext = strtolower($pathInfo['extension'] ?? 'jpg');
if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
    $ext = 'jpg';
}
$cacheFile = $cacheDir . '/' . $urlHash . '.' . $ext;

// Determine MIME type
$mimeTypes = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'webp' => 'image/webp',
    'gif'  => 'image/gif'
];
$mime = $mimeTypes[$ext] ?? 'image/jpeg';

// Check cache & HTTP conditional headers (304 Not Modified)
$cacheTtl = 86400 * 7; // Cache for 7 days
if (file_exists($cacheFile) && filesize($cacheFile) > 0) {
    $mtime = filemtime($cacheFile);
    $etag = '"' . $urlHash . '-' . $mtime . '"';

    header("Access-Control-Allow-Origin: *");
    header("Cache-Control: public, max-age={$cacheTtl}, immutable");
    header("ETag: {$etag}");

    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
        http_response_code(304);
        exit;
    }

    if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $mtime) {
        http_response_code(304);
        exit;
    }

    header("Content-Type: {$mime}");
    header("Content-Length: " . filesize($cacheFile));
    @readfile($cacheFile);
    exit;
}

// Fetch image via cURL with MangaDex Referer to bypass anti-hotlinking
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36');
curl_setopt($ch, CURLOPT_REFERER, 'https://mangadex.org/');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
    'Accept-Language: en-US,en;q=0.9',
    'Sec-Fetch-Dest: image',
    'Sec-Fetch-Mode: no-cors',
    'Sec-Fetch-Site: cross-site'
]);

$imageData = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$remoteMime = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

if ($httpCode === 200 && !empty($imageData)) {
    // Save to local cache
    @file_put_contents($cacheFile, $imageData);

    if (!empty($remoteMime) && str_starts_with($remoteMime, 'image/')) {
        $mime = $remoteMime;
    }

    $mtime = time();
    $etag = '"' . $urlHash . '-' . $mtime . '"';

    header("Access-Control-Allow-Origin: *");
    header("Cache-Control: public, max-age={$cacheTtl}, immutable");
    header("Content-Type: {$mime}");
    header("Content-Length: " . strlen($imageData));
    header("ETag: {$etag}");
    echo $imageData;
    exit;
}

// Return 404 if remote server returned error
http_response_code($httpCode > 0 ? $httpCode : 502);
exit('Failed to fetch image from upstream CDN.');

