<?php
if (!function_exists('dt_bind_params')) {
    function dt_bind_params(mysqli_stmt $stmt, $types, array &$params) {
        if ($types === '' || empty($params)) {
            return true;
        }
        $refs = array($stmt, $types);
        foreach ($params as $key => &$value) {
            $refs[] = &$value;
        }
        return call_user_func_array('mysqli_stmt_bind_param', $refs);
    }
}

if (!function_exists('dt_execute_all')) {
    function dt_execute_all(mysqli $conn, $sql, $types = '', array $params = array()) {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return array(null, array());
        }
        if ($types !== '') {
            dt_bind_params($stmt, $types, $params);
        }
        if (!$stmt->execute()) {
            $stmt->close();
            return array(null, array());
        }
        $rows = array();
        $result = $stmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        return array($stmt, $rows);
    }
}

if (!function_exists('dt_execute_one')) {
    function dt_execute_one(mysqli $conn, $sql, $types = '', array $params = array()) {
        list($stmt, $rows) = dt_execute_all($conn, $sql, $types, $params);
        if ($stmt) {
            $stmt->close();
        }
        return !empty($rows) ? $rows[0] : null;
    }
}

if (!function_exists('dt_close_stmt')) {
    function dt_close_stmt($stmt) {
        if ($stmt instanceof mysqli_stmt) {
            $stmt->close();
        }
    }
}

if (!function_exists('dt_extract_exact_value')) {
    function dt_extract_exact_value($rawValue) {
        $rawValue = trim((string)$rawValue);
        if (preg_match('/^\^(.*)\$$/s', $rawValue, $m)) {
            return $m[1];
        }
        return $rawValue;
    }
}

if (!function_exists('dt_json_empty')) {
    function dt_json_empty($draw, $error = null, $statusCode = 200) {
        http_response_code($statusCode);
        $out = array(
            'draw' => (int)$draw,
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => array(),
        );
        if ($error !== null) {
            $out['error'] = $error;
        }
        echo json_encode($out);
        exit;
    }
}

if (!function_exists('dt_information_schema_column_exists')) {
    function dt_information_schema_column_exists(mysqli $conn, $tableName, $columnName) {
        $sql = "SELECT 1
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                  AND COLUMN_NAME = ?
                LIMIT 1";
        return dt_execute_one($conn, $sql, 'ss', array((string)$tableName, (string)$columnName)) !== null;
    }
}

if (!function_exists('dt_information_schema_table_exists')) {
    function dt_information_schema_table_exists(mysqli $conn, $tableName) {
        $sql = "SELECT 1
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                LIMIT 1";
        return dt_execute_one($conn, $sql, 's', array((string)$tableName)) !== null;
    }
}
