<?php
/** Insert a bounded batch with bound parameters; the caller owns the transaction. */
function gso_insert_batch(mysqli $conn, string $insertSql, string $rowTypes, array $rows): void {
    if (!$rows) { return; }
    $valuesPosition = stripos($insertSql, ' VALUES ');
    if ($valuesPosition === false) { throw new InvalidArgumentException('Missing VALUES clause.'); }
    $rowPlaceholder = '(' . implode(',', array_fill(0, strlen($rowTypes), '?')) . ')';
    $sql = substr($insertSql, 0, $valuesPosition) . ' VALUES ' . implode(',', array_fill(0, count($rows), $rowPlaceholder));
    $params = [];
    foreach ($rows as $row) {
        if (count($row) !== strlen($rowTypes)) { throw new InvalidArgumentException('Invalid batch row.'); }
        foreach ($row as $value) { $params[] = $value; }
    }
    $stmt = $conn->prepare($sql);
    if (!$stmt) { throw new RuntimeException('Unable to prepare batch insert.'); }
    try {
        $stmt->bind_param(str_repeat($rowTypes, count($rows)), ...$params);
        if (!$stmt->execute()) { throw new RuntimeException('Batch insert failed.'); }
    } finally {
        $stmt->close();
    }
}
