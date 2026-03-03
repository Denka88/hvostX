<?php
// /redirect.php

require_once 'includes/db.php';

$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$request_uri = preg_replace('#/+#', '/', $request_uri);

if ($request_uri && $request_uri[0] !== '/') {
    $request_uri = '/' . $request_uri;
}

if (empty($request_uri) || $request_uri === '/') {
    header("Location: index.php");
    exit;
}

$query = "SELECT * FROM redirects WHERE from_url = ? AND is_active = 1 LIMIT 1";
$stmt = $connection->prepare($query);
$stmt->bind_param("s", $request_uri);
$stmt->execute();
$redirect = $stmt->get_result()->fetch_assoc();

if ($redirect) {
    $update_query = "UPDATE redirects SET hits = hits + 1 WHERE id = ?";
    $update_stmt = $connection->prepare($update_query);
    $update_stmt->bind_param("i", $redirect['id']);
    $update_stmt->execute();

    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");

    http_response_code((int)$redirect['redirect_type']);
    header("Location: " . $redirect['to_url']);
    exit;
}

http_response_code(404);
include '404.php';
exit;
