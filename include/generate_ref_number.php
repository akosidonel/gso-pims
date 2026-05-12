<?php
//reusable auto-generate reference number functions
function generateReferenceNumber($conn, $table, $column) {
    do {
        $yearMonth = date('Ym'); // e.g., 202506
        $randomStr = strtoupper(bin2hex(random_bytes(6))); // 12 alphanumeric
        $ref = "{$yearMonth}-{$randomStr}";

        // Check if it exists in the database
        $stmt = $conn->prepare("SELECT COUNT(*) FROM {$table} WHERE {$column} = ?");
        $stmt->bind_param('s', $ref);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
    } while ($count > 0);

    return $ref;
}
