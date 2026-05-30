<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/auth.php';

if (!function_exists('gso_get_departments')) {
  /**
   * @return array<int, array{department_code:string, department_name:string}>
   */
  function gso_get_departments(mysqli $conn): array {
    $rows = [];
    foreach (gso_fetch_departments($conn) as $row) {
      $rows[] = [
        'department_code' => (string)($row['department_code'] ?? ''),
        'department_name' => (string)($row['department_name'] ?? ''),
      ];
    }

    return $rows;
  }
}
