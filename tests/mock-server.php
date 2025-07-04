<?php

namespace Unirest\Test\Mocking;

// Track active connections (simplified simulation)
$connectionPool = [];

function getConnectionKey($requestUri)
{
    // Extract domain/key from URL path
    if (preg_match('#^/get$#', $requestUri)) {
        return 'get';
    }
    if (preg_match('#^/t/([^/]+)$#', $requestUri, $matches)) {
        return 't-' . $matches[1];
    }
    if (preg_match('#^/([a-z0-9\.\-]+)(/|$)#', $requestUri, $matches)) {
        return preg_replace('/[^a-z0-9]/', '-', strtolower($matches[1]));
    }
    return 'default';
}

// Helper to get all headers
if (!function_exists('getallheaders')) {
    function getallheaders()
    {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', strtolower(str_replace('_', ' ', substr($name, 5))))] = $value;
            }
        }
        return $headers;
    }
}

$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];
$requestHeaders = getallheaders();
$body = file_get_contents('php://input');

// Determine Connection header value to send back based on request and endpoint
function getConnectionResponseHeader($requestUri, $requestHeaders)
{
    // Special handling for /get endpoint based on test expectation
    if ($requestUri === '/get') {
        if (!isset($requestHeaders['connection'])) {
            // First request, no connection header sent: respond with "close,keep-alive"
            return 'close,keep-alive';
        } elseif (strtolower($requestHeaders['connection']) === 'close') {
            // Request asks to close connection
            return 'close';
        } else {
            // Default to keep-alive
            return 'keep-alive';
        }
    }

    // For other endpoints, just respond with connection header or keep-alive
    if (isset($requestHeaders['connection']) && strtolower($requestHeaders['connection']) === 'close') {
        return 'close';
    }

    return 'keep-alive';
}

$connectionResponseHeader = getConnectionResponseHeader($requestUri, $requestHeaders);

header('Content-Type: application/json');

// Unified connection test handler for all test domains except /get (handled separately)
$testEndpoints = [
    '/t/',
    '/en2hoq5smpha9.x.pipedream.net',
    '/mockbin.com/request'
];

foreach ($testEndpoints as $endpoint) {
    if (strpos($requestUri, $endpoint) === 0) {
        header_remove();
        header('Connection: ' . $connectionResponseHeader);
        header('Content-Type: application/json');
        http_response_code(200);
        echo json_encode(['status' => 'connected']);
        exit;
    }
}

// Handle /get endpoint separately to apply connection header logic
if ($requestUri === '/get') {
    header_remove();
    header('Connection: ' . $connectionResponseHeader);
    header('Content-Type: application/json');
    http_response_code(200);
    echo json_encode(['status' => 'connected']);
    exit;
}

// /request endpoint (already implemented, but add HEAD support)
if (preg_match('#^/request#', $requestUri)) {
    // Handle cookies
    $cookies = [];
    if (isset($_SERVER['HTTP_COOKIE'])) {
        foreach (explode('; ', $_SERVER['HTTP_COOKIE']) as $cookie) {
            [$key, $value] = explode('=', $cookie, 2);
            $cookies[$key] = $value;
        }
    }
    // Handle headers
    $headers = [];
    foreach ($requestHeaders as $key => $value) {
        $headers[strtolower($key)] = $value;
    }
    // Handle query string
    $queryString = [];
    $query = parse_url($requestUri, PHP_URL_QUERY);  // get raw query string from URL
    if ($query !== null) {
        $pairs = explode('&', $query);                // explode on raw string
        foreach ($pairs as $pair) {
            if (empty($pair)) {
                continue;
            }
            [$k, $v] = explode('=', $pair, 2) + [1 => ''];
            $key = urldecode($k);
            $value = urldecode($v);
            $queryString[$key] = $value;
        }
    }

    // Handle POST/PUT/PATCH/DELETE data
    $postData = null;
    if (in_array($requestMethod, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
        $contentType = isset($headers['content-type']) ? $headers['content-type'] : '';
        if (strpos($contentType, 'application/json') !== false) {
            $postData = json_decode($body);
        } elseif (strpos($contentType, 'multipart/form-data') !== false) {
            $postData = (object) $_POST;
            // For file uploads, just echo back the file content
            foreach ($_FILES as $key => $file) {
                if (is_array($file['tmp_name'])) {
                    $postData->{$key} = [];
                    foreach ($file['tmp_name'] as $idx => $tmpName) {
                        $postData->{$key}[] = file_get_contents($tmpName);
                    }
                } else {
                    $postData->{$key} = file_get_contents($file['tmp_name']);
                }
            }
        } else {
            $postData = (object) $_POST;
        }
    }
    file_put_contents(__DIR__ . '/mock_debug.log', print_r([
        'method' => $requestMethod,
        'content_type' => $headers['content-type'] ?? '',
        '_POST' => $_POST,
        '_FILES' => $_FILES,
    ], true));

    header_remove();
    header('Connection: ' . $connectionResponseHeader);
    http_response_code(200);

    if ($requestMethod === 'HEAD') {
        // No body for HEAD
        exit;
    }
    echo json_encode([
        'method' => $requestMethod,
        'cookies' => (object) $cookies,
        'headers' => (object) $headers,
        'queryString' => (object) $queryString,
        'postData' => is_object($postData) ? (object) [
            'params' => $postData,
            'mimeType' => $headers['content-type'] ?? null,
            'text' => $body
        ] : null
    ]);
    exit;
}

// /delay/{ms} endpoint
if (preg_match('#^/delay/(\d+)$#', $requestUri, $matches)) {
    $delay = (int)$matches[1];
    usleep($delay * 1000); // Delay in milliseconds
    header_remove();
    header('Connection: ' . $connectionResponseHeader);
    http_response_code(200);
    echo json_encode(['message' => 'Request completed after delay']);
    exit;
}

// /gzip endpoint
if ($requestUri === '/gzip') {
    header_remove();
    header('Connection: ' . $connectionResponseHeader);
    header('Content-Encoding: gzip');
    http_response_code(200);
    $payload = json_encode(['message' => 'gzip']);
    echo gzencode($payload);
    exit;
}

// Default 404
header_remove();
header('Connection: ' . $connectionResponseHeader);
http_response_code(404);
echo json_encode(['error' => 'Not Found']);
