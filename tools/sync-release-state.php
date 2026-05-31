<?php

declare(strict_types=1);

$_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$_GET = [];
$_POST = [];
$_REQUEST = [];

require_once __DIR__ . '/../auth/auth.php';

$result = gso_release_sync_database($conn);
$comments = gso_release_fetch_changelog_comments($conn, 1);
$latestComment = $comments ? (string) ($comments[0]['subject'] ?? '') : '';

echo json_encode([
    'status' => 'ok',
    'current_version' => (string) ($result['current_version'] ?? ''),
    'latest_patch_version' => (string) ($result['latest_patch_version'] ?? ''),
    'latest_comment' => $latestComment,
], JSON_UNESCAPED_SLASHES) . PHP_EOL;
