<?php
require_once __DIR__ . '/session_bootstrap.php';
gso_start_secure_session();
error_reporting(0);

include('../database/databaseConnection.php');

// Build a dynamic base URL so the app works from any host/IP and subfolder
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Compute web path to project root relative to document root
$docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? rtrim(str_replace('\\','/', $_SERVER['DOCUMENT_ROOT']), '/') : '';
$appRoot = rtrim(str_replace('\\','/', realpath(__DIR__ . '/..')), '/');
$basePath = '/';
if ($docRoot && strpos($appRoot, $docRoot) === 0) {
	$basePath = substr($appRoot, strlen($docRoot));
	if ($basePath === false || $basePath === '') {
		$basePath = '/';
	}
}
// Ensure leading and single trailing slash
$basePath = rtrim('/' . ltrim($basePath, '/'), '/') . '/';

define('SITE_URL', $scheme . '://' . $host . $basePath);
?>
