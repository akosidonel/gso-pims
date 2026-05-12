<?php

declare(strict_types=1);

if (!function_exists('gso_get_departments')) {
  /**
   * @return array<int, array{department_code:string, department_name:string}>
   */
  function gso_get_departments(mysqli $conn): array {
    $rows = [];
    $res = mysqli_query($conn, "SELECT department_code, department_name FROM department");
    if (!$res) {
      return $rows;
    }

    while ($row = mysqli_fetch_assoc($res)) {
      $rows[] = $row;
    }
    return $rows;
  }
}
