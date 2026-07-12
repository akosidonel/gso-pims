<?php
require_once('../config/session.php');

$_SESSION['alogin'] = $_SESSION['alogin'] ?? 'codex-preview';
$_SESSION['role'] = 'SYSTEM-ADMIN';
$_SESSION['admin_name'] = 'Preview User';
$_SERVER['SCRIPT_NAME'] = '/gso-pims/admin/change-log.php';

require('change-log.php');
