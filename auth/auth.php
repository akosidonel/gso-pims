<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/../database/databaseConnection.php';
require_once __DIR__ . '/../include/getuser_ipaddress.php';
require_once __DIR__ . '/../include/generate_par_ics_number.php';
require_once __DIR__ . '/../include/generate_ptr_number.php';
require_once __DIR__ . '/../include/generate_ref_number.php';
date_default_timezone_set('Asia/Manila');
@extract($_REQUEST);
// Helper utilities (restored)
if(!function_exists('json_response')){
    function json_response($status,$message,$data=null){
        $res=['status'=>$status,'message'=>$message];
        if($data!==null){$res['data']=$data;} echo json_encode($res); return false;
    }
}
if(!function_exists('gso_log_activity')){
    function gso_log_activity($conn, $uid, $uip, $activity){
        $uid = (string)$uid;
        $uip = (string)$uip;
        $activity = (string)$activity;
        $stmt = mysqli_prepare($conn, 'INSERT INTO activity_log(admin_id, ip_address, activity) VALUES(?, ?, ?)');
        if (!$stmt) { return false; }
        mysqli_stmt_bind_param($stmt, 'sss', $uid, $uip, $activity);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return (bool)$ok;
    }
}
if(!function_exists('escape_up')){ function escape_up($conn,$v){ return mysqli_real_escape_string($conn,strtoupper($v)); } }
if(!function_exists('escape_raw')){ function escape_raw($conn,$v){ return mysqli_real_escape_string($conn,$v); } }
if(!function_exists('gso_current_role')){
    function gso_current_role(){
        return strtoupper(trim((string)($_SESSION['role'] ?? '')));
    }
}
if(!function_exists('gso_user_has_role')){
    function gso_user_has_role(array $roles){
        $role = gso_current_role();
        foreach ($roles as $allowedRole) {
            if ($role === strtoupper(trim((string)$allowedRole))) {
                return true;
            }
        }
        return false;
    }
}
if(!function_exists('gso_require_role_json')){
    function gso_require_role_json(array $roles){
        if (empty($_SESSION['alogin'])) {
            echo json_encode(['status' => 401, 'message' => 'Not logged in']);
            return false;
        }
        if (!gso_user_has_role($roles)) {
            echo json_encode(['status' => 403, 'message' => 'Forbidden']);
            return false;
        }
        return true;
    }
}
if(!function_exists('gso_issue_form_token')){
    function gso_issue_form_token($group){
        $group = trim((string)$group);
        if ($group === '') { return ''; }
        if (!isset($_SESSION['form_tokens']) || !is_array($_SESSION['form_tokens'])) {
            $_SESSION['form_tokens'] = [];
        }
        if (!isset($_SESSION['form_tokens'][$group]) || !is_array($_SESSION['form_tokens'][$group])) {
            $_SESSION['form_tokens'][$group] = [];
        }
        $token = bin2hex(random_bytes(16));
        $_SESSION['form_tokens'][$group][$token] = time();
        return $token;
    }
}
if(!function_exists('gso_validate_form_token')){
    function gso_validate_form_token($group, $token, $maxAgeSeconds = 1800, $consume = false){
        $group = trim((string)$group);
        $token = trim((string)$token);
        if ($group === '' || $token === '') { return false; }
        if (!isset($_SESSION['form_tokens'][$group]) || !is_array($_SESSION['form_tokens'][$group])) {
            return false;
        }
        foreach ($_SESSION['form_tokens'][$group] as $storedToken => $createdAt) {
            if ((int)$createdAt < time() - (int)$maxAgeSeconds) {
                unset($_SESSION['form_tokens'][$group][$storedToken]);
            }
        }
        if (!isset($_SESSION['form_tokens'][$group][$token])) {
            return false;
        }
        if ($consume) {
            unset($_SESSION['form_tokens'][$group][$token]);
        }
        return true;
    }
}
if(!function_exists('gso_fetch_all_rows')){
    function gso_fetch_all_rows(mysqli_stmt $stmt){
        $rows = [];
        $result = mysqli_stmt_get_result($stmt);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
        }
        return $rows;
    }
}
if(!function_exists('gso_fetch_one_row')){
    function gso_fetch_one_row(mysqli_stmt $stmt){
        $result = mysqli_stmt_get_result($stmt);
        if ($result && ($row = mysqli_fetch_assoc($result))) {
            return $row;
        }
        return null;
    }
}
if(!function_exists('gso_query_all')){
    function gso_query_all(mysqli $conn, $sql, $types = '', array $params = array()){
        $stmt = $conn->prepare($sql);
        if (!$stmt) { return array(null, array()); }
        if ($types !== '') {
            gso_stmt_bind_params($stmt, $types, $params);
        }
        if (!$stmt->execute()) {
            $stmt->close();
            return array(null, array());
        }
        return array($stmt, gso_fetch_all_rows($stmt));
    }
}
if(!function_exists('gso_query_one')){
    function gso_query_one(mysqli $conn, $sql, $types = '', array $params = array()){
        list($stmt, $rows) = gso_query_all($conn, $sql, $types, $params);
        if ($stmt instanceof mysqli_stmt) { $stmt->close(); }
        return !empty($rows) ? $rows[0] : null;
    }
}
if(!function_exists('gso_fetch_administrator_by_emp_number')){
    function gso_fetch_administrator_by_emp_number(mysqli $conn, $employeeNumber){
        $employeeNumber = trim((string)$employeeNumber);
        $stmt = $conn->prepare('SELECT * FROM administrator WHERE emp_number = ? LIMIT 1');
        if (!$stmt) { return null; }
        $stmt->bind_param('s', $employeeNumber);
        $stmt->execute();
        $row = gso_fetch_one_row($stmt);
        $stmt->close();
        return $row;
    }
}
if(!function_exists('gso_fetch_administrator_by_email')){
    function gso_fetch_administrator_by_email(mysqli $conn, $email){
        $email = trim((string)$email);
        $stmt = $conn->prepare('SELECT admin_id, email FROM administrator WHERE email = ? LIMIT 1');
        if (!$stmt) { return null; }
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $row = gso_fetch_one_row($stmt);
        $stmt->close();
        return $row;
    }
}
if(!function_exists('gso_store_administrator_reset_token')){
    function gso_store_administrator_reset_token(mysqli $conn, $email, $token){
        $email = trim((string)$email);
        $token = trim((string)$token);
        $stmt = $conn->prepare('UPDATE administrator SET token = ? WHERE email = ? LIMIT 1');
        if (!$stmt) { return false; }
        $stmt->bind_param('ss', $token, $email);
        $ok = $stmt->execute();
        $stmt->close();
        return (bool)$ok;
    }
}
if(!function_exists('gso_fetch_administrator_by_reset_token')){
    function gso_fetch_administrator_by_reset_token(mysqli $conn, $token){
        $token = trim((string)$token);
        $stmt = $conn->prepare('SELECT admin_id, token FROM administrator WHERE token = ? LIMIT 1');
        if (!$stmt) { return null; }
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $row = gso_fetch_one_row($stmt);
        $stmt->close();
        return $row;
    }
}
if(!function_exists('gso_update_password_by_reset_token')){
    function gso_update_password_by_reset_token(mysqli $conn, $passwordHash, $token){
        $passwordHash = (string)$passwordHash;
        $token = trim((string)$token);
        $stmt = $conn->prepare('UPDATE administrator SET password = ? WHERE token = ? LIMIT 1');
        if (!$stmt) { return false; }
        $stmt->bind_param('ss', $passwordHash, $token);
        $ok = $stmt->execute();
        $stmt->close();
        return (bool)$ok;
    }
}
if(!function_exists('gso_rotate_password_reset_token')){
    function gso_rotate_password_reset_token(mysqli $conn, $currentToken, $newToken){
        $currentToken = trim((string)$currentToken);
        $newToken = trim((string)$newToken);
        $stmt = $conn->prepare('UPDATE administrator SET token = ? WHERE token = ? LIMIT 1');
        if (!$stmt) { return false; }
        $stmt->bind_param('ss', $newToken, $currentToken);
        $ok = $stmt->execute();
        $stmt->close();
        return (bool)$ok;
    }
}
if(!function_exists('gso_fetch_account_codes')){
    function gso_fetch_account_codes(mysqli $conn){
        $stmt = $conn->prepare('SELECT account_code, account_name FROM account_code ORDER BY account_code ASC');
        if (!$stmt) { return []; }
        $stmt->execute();
        $rows = gso_fetch_all_rows($stmt);
        $stmt->close();
        return $rows;
    }
}
if(!function_exists('gso_fetch_departments')){
    function gso_fetch_departments(mysqli $conn){
        $stmt = $conn->prepare('SELECT department_code, department_name, dept_id FROM department ORDER BY department_name ASC');
        if (!$stmt) { return []; }
        $stmt->execute();
        $rows = gso_fetch_all_rows($stmt);
        $stmt->close();
        return $rows;
    }
}
if(!function_exists('gso_fetch_clearance_types')){
    function gso_fetch_clearance_types(mysqli $conn){
        $stmt = $conn->prepare('SELECT clearance_code, clearance_name FROM clearance_type ORDER BY clearance_name ASC');
        if (!$stmt) { return []; }
        $stmt->execute();
        $rows = gso_fetch_all_rows($stmt);
        $stmt->close();
        return $rows;
    }
}
if(!function_exists('gso_fetch_administrator_by_id')){
    function gso_fetch_administrator_by_id(mysqli $conn, $adminId){
        $adminId = (string)$adminId;
        $stmt = $conn->prepare('SELECT admin_id, first_name, last_name, contact_number, email, role, emp_number FROM administrator WHERE admin_id = ? LIMIT 1');
        if (!$stmt) { return null; }
        $stmt->bind_param('s', $adminId);
        $stmt->execute();
        $row = gso_fetch_one_row($stmt);
        $stmt->close();
        return $row;
    }
}
if(!function_exists('gso_fetch_dashboard_admin_summary')){
    function gso_fetch_dashboard_admin_summary(mysqli $conn, $adminId){
        $adminId = trim((string)$adminId);
        if ($adminId === '') { return null; }
        $stmt = $conn->prepare(
            'SELECT first_name, last_name, last_session, ip
             FROM administrator
             WHERE admin_id = ?
             LIMIT 1'
        );
        if (!$stmt) { return null; }
        $stmt->bind_param('s', $adminId);
        $stmt->execute();
        $row = gso_fetch_one_row($stmt);
        $stmt->close();
        return $row;
    }
}
if(!function_exists('gso_fetch_dashboard_approved_clearances')){
    function gso_fetch_dashboard_approved_clearances(mysqli $conn, $limit = 4){
        $limit = (int)$limit;
        if ($limit <= 0) {
            $limit = 4;
        }
        $sql = "
            SELECT e.emp_name, h.created_at, h.status, h.release_date AS date, c.clearance_name
            FROM clearance_history AS h
            JOIN employee AS e ON h.emp_id = e.emp_id
            JOIN clearance_type AS c ON h.ctype_id = c.clearance_code
            WHERE h.status = 1
            ORDER BY h.created_at DESC
            LIMIT {$limit}
        ";
        $stmt = $conn->prepare($sql);
        if (!$stmt) { return []; }
        $stmt->execute();
        $rows = gso_fetch_all_rows($stmt);
        $stmt->close();
        return $rows;
    }
}
if(!function_exists('gso_insert_administrator')){
    function gso_insert_administrator(mysqli $conn, array $data){
        $stmt = $conn->prepare(
            'INSERT INTO administrator (first_name, last_name, contact_number, email, role, emp_number, password, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        if (!$stmt) { return false; }
        $stmt->bind_param(
            'sssssssi',
            $data['first_name'],
            $data['last_name'],
            $data['contact_number'],
            $data['email'],
            $data['role'],
            $data['emp_number'],
            $data['password'],
            $data['status']
        );
        $ok = $stmt->execute();
        $stmt->close();
        return (bool)$ok;
    }
}
if(!function_exists('gso_update_administrator')){
    function gso_update_administrator(mysqli $conn, array $data){
        $stmt = $conn->prepare(
            'UPDATE administrator
             SET first_name = ?, last_name = ?, contact_number = ?, email = ?, role = ?, emp_number = ?
             WHERE admin_id = ?
             LIMIT 1'
        );
        if (!$stmt) { return false; }
        $stmt->bind_param(
            'sssssss',
            $data['first_name'],
            $data['last_name'],
            $data['contact_number'],
            $data['email'],
            $data['role'],
            $data['emp_number'],
            $data['admin_id']
        );
        $ok = $stmt->execute();
        $stmt->close();
        return (bool)$ok;
    }
}
if(!function_exists('gso_delete_administrator')){
    function gso_delete_administrator(mysqli $conn, $adminId){
        $adminId = (string)$adminId;
        $stmt = $conn->prepare('DELETE FROM administrator WHERE admin_id = ? LIMIT 1');
        if (!$stmt) { return false; }
        $stmt->bind_param('s', $adminId);
        $ok = $stmt->execute();
        $stmt->close();
        return (bool)$ok;
    }
}
if(!function_exists('gso_fetch_admin_panel_rows')){
    function gso_fetch_admin_panel_rows(mysqli $conn, $timeoutSeconds = 90){
        $timeoutSeconds = (int)$timeoutSeconds;
        if ($timeoutSeconds <= 0) {
            $timeoutSeconds = 90;
        }
        if (gso_admin_has_last_activity_column($conn)) {
            $sql = "SELECT *,
                           CASE
                             WHEN status = 1 AND last_activity >= DATE_SUB(NOW(), INTERVAL {$timeoutSeconds} SECOND)
                             THEN 1 ELSE 0
                           END AS is_online
                    FROM administrator";
        } else {
            $sql = 'SELECT *, status AS is_online FROM administrator';
        }
        $stmt = $conn->prepare($sql);
        if (!$stmt) { return []; }
        $stmt->execute();
        $rows = gso_fetch_all_rows($stmt);
        $stmt->close();
        return $rows;
    }
}
if(!function_exists('gso_column_exists')){
    function gso_column_exists($conn, $table, $column){
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$table);
        $column = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$column);
        if ($table === '' || $column === '') { return false; }
        $q = mysqli_query($conn, "SHOW COLUMNS FROM {$table} LIKE '{$column}'");
        return ($q && mysqli_num_rows($q) > 0);
    }
}
if(!function_exists('gso_id_is_auto_increment')){
    function gso_id_is_auto_increment($conn, $table, $idColumn = 'id'){
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$table);
        $idColumn = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$idColumn);
        if ($table === '' || $idColumn === '') { return true; }
        $q = mysqli_query($conn, "SHOW COLUMNS FROM {$table} LIKE '{$idColumn}'");
        if ($q && ($row = mysqli_fetch_assoc($q))) {
            return stripos((string)($row['Extra'] ?? ''), 'auto_increment') !== false;
        }
        return true;
    }
}
if(!function_exists('gso_next_int_id')){
    function gso_next_int_id($conn, $table, $idColumn){
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$table);
        $idColumn = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$idColumn);
        $q = mysqli_query($conn, "SELECT COALESCE(MAX(`{$idColumn}`), 0) + 1 AS next_id FROM `{$table}`");
        if ($q && ($row = mysqli_fetch_assoc($q))) { return (int)($row['next_id'] ?? 1); }
        return 1;
    }
}
if(!function_exists('gso_stmt_bind_params')){
    function gso_stmt_bind_params(mysqli_stmt $stmt, $types, array &$params){
        $refs = [$stmt, $types];
        foreach ($params as $key => &$value) {
            $refs[] = &$value;
        }
        return call_user_func_array('mysqli_stmt_bind_param', $refs);
    }
}
if(!function_exists('gso_find_department_by_code')){
    function gso_find_department_by_code($conn, $departmentCode){
        $departmentCode = trim((string)$departmentCode);
        if ($departmentCode === '') { return null; }
        $stmt = mysqli_prepare($conn, 'SELECT dept_id, department_code, department_name FROM department WHERE department_code = ? LIMIT 1');
        if (!$stmt) { return null; }
        mysqli_stmt_bind_param($stmt, 's', $departmentCode);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = ($res && ($data = mysqli_fetch_assoc($res))) ? $data : null;
        mysqli_stmt_close($stmt);
        return $row;
    }
}
if(!function_exists('gso_find_department_by_pk')){
    function gso_find_department_by_pk($conn, $deptId){
        $deptId = (int)$deptId;
        if ($deptId <= 0) { return null; }
        $stmt = mysqli_prepare($conn, 'SELECT dept_id, department_code, department_name FROM department WHERE dept_id = ? LIMIT 1');
        if (!$stmt) { return null; }
        mysqli_stmt_bind_param($stmt, 'i', $deptId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = ($res && ($data = mysqli_fetch_assoc($res))) ? $data : null;
        mysqli_stmt_close($stmt);
        return $row;
    }
}
if(!function_exists('gso_resolve_history_department')){
    function gso_resolve_history_department($conn, $historyDeptId, $employeeDeptCode = ''){
        $historyDeptId = trim((string)$historyDeptId);
        $employeeDeptCode = trim((string)$employeeDeptCode);

        if ($employeeDeptCode !== '') {
            $preferred = gso_find_department_by_code($conn, $employeeDeptCode);
            if ($preferred) {
                $preferredCode = trim((string)($preferred['department_code'] ?? ''));
                $preferredPk = trim((string)($preferred['dept_id'] ?? ''));
                if ($historyDeptId === $preferredCode || $historyDeptId === $preferredPk) {
                    return $preferred;
                }
            }
        }

        $byCode = gso_find_department_by_code($conn, $historyDeptId);
        $byPk = ctype_digit($historyDeptId) ? gso_find_department_by_pk($conn, (int)$historyDeptId) : null;

        if ($byCode) { return $byCode; }
        if ($byPk) { return $byPk; }
        if ($employeeDeptCode !== '') { return gso_find_department_by_code($conn, $employeeDeptCode); }
        return null;
    }
}
if (!function_exists('gso_new_purchase_group_ids')) {
    function gso_new_purchase_group_ids(mysqli $conn, $po = '', $rowId = 0) {
        $po = trim((string)$po);
        $rowId = (int)$rowId;

        if ($po === '' && $rowId <= 0) {
            return [];
        }

        if ($po !== '') {
            $sql = "SELECT id
                    FROM new_purchase
                    WHERE UPPER(TRIM(COALESCE(purchase_order, ''))) = ?
                    ORDER BY id ASC";
            $stmt = mysqli_prepare($conn, $sql);
            if (!$stmt) { return []; }
            $poKey = strtoupper($po);
            mysqli_stmt_bind_param($stmt, 's', $poKey);
        } else {
            $sql = "SELECT id
                    FROM new_purchase
                    WHERE id = ?
                    ORDER BY id ASC";
            $stmt = mysqli_prepare($conn, $sql);
            if (!$stmt) { return []; }
            mysqli_stmt_bind_param($stmt, 'i', $rowId);
        }

        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $ids = [];
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $id = (int)($row['id'] ?? 0);
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }
        mysqli_stmt_close($stmt);

        return $ids;
    }
}
if (!function_exists('gso_new_purchase_group_rows')) {
    function gso_new_purchase_group_rows(mysqli $conn, $po = '', $rowId = 0) {
        $po = trim((string)$po);
        $rowId = (int)$rowId;
        if ($po === '' && $rowId <= 0) {
            return [];
        }

        if ($po === '' && $rowId > 0) {
            $lookupStmt = $conn->prepare('SELECT purchase_order FROM new_purchase WHERE id = ? LIMIT 1');
            if ($lookupStmt) {
                $lookupStmt->bind_param('i', $rowId);
                $lookupStmt->execute();
                $lookupResult = $lookupStmt->get_result();
                if ($lookupResult && ($lookupRow = mysqli_fetch_assoc($lookupResult))) {
                    $po = trim((string)($lookupRow['purchase_order'] ?? ''));
                }
                $lookupStmt->close();
            }
        }

        $where = $po !== '' ? 'np.purchase_order = ?' : 'np.id = ?';
        $sql = "
            SELECT
                np.id,
                np.unit, np.item, np.model, np.description,
                np.serial_number, np.serial_number_2,
                COALESCE(
                    NULLIF(np.property_number, ''),
                    CASE
                        WHEN h.par_number LIKE 'NPID:%' THEN ''
                        ELSE COALESCE(h.par_number, '')
                    END
                ) AS property_number,
                np.fund, np.category, np.unit_value, np.date_aquired,
                np.account_code, np.supplier, np.par_ics_number,
                np.purchase_order, np.purchase_request, np.obr_number,
                np.jev_number, np.remarks,
                COALESCE(d_by_id.department_name, d_by_code.department_name) AS department_name,
                COALESCE(d_by_id.department_code, d_by_code.department_code) AS department_code,
                h.dept_id, h.emp_id, e.emp_name,
                h.category AS doc_type, h.reference_number
            FROM new_purchase AS np
            LEFT JOIN new_purchase_history AS h
                ON (h.par_number = np.property_number OR h.par_number = CONCAT('NPID:', np.id)) AND h.status = 1
            LEFT JOIN department AS d_by_id ON d_by_id.dept_id = h.dept_id
            LEFT JOIN department AS d_by_code ON d_by_code.department_code = h.dept_id
            LEFT JOIN employee AS e ON e.emp_id = h.emp_id
            WHERE {$where}
            ORDER BY np.id ASC
        ";

        $stmt = $conn->prepare($sql);
        if (!$stmt) { return []; }
        if ($po !== '') {
            $stmt->bind_param('s', $po);
        } else {
            $stmt->bind_param('i', $rowId);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
        }
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('gso_fund_inventory_source_map')) {
    function gso_fund_inventory_source_map($fundKey) {
        $fundKey = strtolower(trim((string)$fundKey));
        $map = [
            'trust' => [
                'table' => 'trust_fund',
                'history' => 'trust_fund_history',
                'label' => 'TRUST FUND',
            ],
            'donation' => [
                'table' => 'donation',
                'history' => 'donation_history',
                'label' => 'DONATION',
            ],
        ];
        return $map[$fundKey] ?? null;
    }
}

if (!function_exists('gso_fund_inventory_summary_rows')) {
    function gso_fund_inventory_summary_rows(mysqli $conn, $fundKey) {
        $source = gso_fund_inventory_source_map($fundKey);
        if (!$source) { return false; }

        $table = $source['table'];
        $historyTable = $source['history'];
        $fundLabel = mysqli_real_escape_string($conn, $source['label']);

        $sql = "
            SELECT
                MIN(f.id) AS row_id,
                NULLIF(TRIM(f.purchase_order), '') AS purchase_order,
                '{$fundLabel}' AS fund,
                MAX(f.purchase_request) AS purchase_request,
                MAX(f.obr_number) AS obr_number,
                MAX(f.supplier) AS supplier,
                MAX(f.par_ics_number) AS par_ics_number,
                MAX(COALESCE(d_by_id.department_code, d_by_code.department_code, h.dept_id)) AS department_code,
                MAX(COALESCE(d_by_id.department_name, d_by_code.department_name)) AS department_name,
                SUM(COALESCE(f.unit_value, 0)) AS total_amount,
                MAX(f.created_at) AS created_at,
                GROUP_CONCAT(f.id ORDER BY f.id ASC) AS item_ids_csv
            FROM {$table} AS f
            LEFT JOIN {$historyTable} AS h
                ON h.id = f.id AND h.status = 1
            LEFT JOIN department AS d_by_code ON d_by_code.department_code = h.dept_id
            LEFT JOIN department AS d_by_id ON d_by_id.dept_id = h.dept_id
            GROUP BY
                CASE
                    WHEN TRIM(COALESCE(f.purchase_order, '')) = '' THEN CONCAT('ID:', f.id)
                    ELSE CONCAT('PO:', UPPER(TRIM(f.purchase_order)))
                END
            ORDER BY MAX(f.created_at) DESC, MIN(f.id) DESC
        ";

        $stmt = $conn->prepare($sql);
        if (!$stmt) { return false; }
        $stmt->execute();
        return $stmt->get_result();
    }
}

if (!function_exists('gso_fund_inventory_group_rows')) {
    function gso_fund_inventory_group_rows(mysqli $conn, $fundKey, $po = '', $rowId = 0) {
        $source = gso_fund_inventory_source_map($fundKey);
        if (!$source) { return []; }

        $po = trim((string)$po);
        $rowId = (int)$rowId;
        if ($po === '' && $rowId <= 0) {
            return [];
        }

        $table = $source['table'];
        $historyTable = $source['history'];

        if ($po === '' && $rowId > 0) {
            $lookupStmt = $conn->prepare("SELECT purchase_order FROM {$table} WHERE id = ? LIMIT 1");
            if ($lookupStmt) {
                $lookupStmt->bind_param('i', $rowId);
                $lookupStmt->execute();
                $lookupResult = $lookupStmt->get_result();
                if ($lookupResult && ($lookupRow = mysqli_fetch_assoc($lookupResult))) {
                    $po = trim((string)($lookupRow['purchase_order'] ?? ''));
                }
                $lookupStmt->close();
            }
        }

        $where = $po !== '' ? 'f.purchase_order = ?' : 'f.id = ?';
        $sql = "
            SELECT
                f.id,
                f.unit, f.item, f.model, f.description,
                f.serial_number, f.serial_number_2,
                COALESCE(
                    NULLIF(f.property_number, ''),
                    CASE
                        WHEN h.par_number LIKE 'NPID:%' THEN ''
                        ELSE COALESCE(h.par_number, '')
                    END
                ) AS property_number,
                f.fund, f.category, f.unit_value, f.date_aquired,
                f.account_code, f.supplier, f.par_ics_number,
                f.purchase_order, f.purchase_request, f.obr_number,
                f.jev_number, f.remarks,
                COALESCE(d_by_id.department_name, d_by_code.department_name) AS department_name,
                COALESCE(d_by_id.department_code, d_by_code.department_code) AS department_code,
                h.dept_id, h.emp_id, e.emp_name,
                h.category AS doc_type, h.reference_number
            FROM {$table} AS f
            LEFT JOIN {$historyTable} AS h
                ON h.id = f.id AND h.status = 1
            LEFT JOIN department AS d_by_id ON d_by_id.dept_id = h.dept_id
            LEFT JOIN department AS d_by_code ON d_by_code.department_code = h.dept_id
            LEFT JOIN employee AS e ON e.emp_id = h.emp_id
            WHERE {$where}
            ORDER BY f.id ASC
        ";

        $stmt = $conn->prepare($sql);
        if (!$stmt) { return []; }
        if ($po !== '') {
            $stmt->bind_param('s', $po);
        } else {
            $stmt->bind_param('i', $rowId);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
        }
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('gso_new_purchase_group_modal_items')) {
    function gso_new_purchase_group_modal_items(array $rows): array {
        $grouped = [];
        foreach ($rows as $row) {
            $signatureParts = [
                strtoupper(trim((string)($row['category'] ?? ''))),
                strtoupper(trim((string)($row['unit'] ?? ''))),
                strtoupper(trim((string)($row['item'] ?? ''))),
                strtoupper(trim((string)($row['model'] ?? ''))),
                strtoupper(trim((string)($row['description'] ?? ''))),
                strtoupper(trim((string)($row['unit_value'] ?? ''))),
                strtoupper(trim((string)($row['account_code'] ?? ''))),
                strtoupper(trim((string)($row['remarks'] ?? ''))),
                strtoupper(trim((string)($row['emp_id'] ?? ''))),
                strtoupper(trim((string)($row['dept_id'] ?? ''))),
                strtoupper(trim((string)($row['par_ics_number'] ?? ''))),
            ];
            $signature = implode('|', $signatureParts);

            if (!isset($grouped[$signature])) {
                $grouped[$signature] = [
                    'id' => (int)($row['id'] ?? 0),
                    'category' => (string)($row['category'] ?? ''),
                    'unit' => (string)($row['unit'] ?? ''),
                    'item' => (string)($row['item'] ?? ''),
                    'model' => (string)($row['model'] ?? ''),
                    'description' => (string)($row['description'] ?? ''),
                    'property_number' => '',
                    'par_ics_number' => (string)($row['par_ics_number'] ?? ''),
                    'unit_value' => (string)($row['unit_value'] ?? ''),
                    'account_code' => (string)($row['account_code'] ?? ''),
                    'remarks' => (string)($row['remarks'] ?? ''),
                    'emp_id' => (string)($row['emp_id'] ?? ''),
                    'emp_name' => (string)($row['emp_name'] ?? ''),
                    'item_quantity' => 0,
                    'existing_item_ids' => [],
                    'serial_numbers' => [],
                    'serial_numbers_2' => [],
                    'property_numbers' => [],
                ];
            }

            $rowId = (int)($row['id'] ?? 0);
            if ($rowId > 0) {
                $grouped[$signature]['existing_item_ids'][] = $rowId;
            }
            $grouped[$signature]['item_quantity']++;
            $grouped[$signature]['serial_numbers'][] = (string)($row['serial_number'] ?? '');
            $grouped[$signature]['serial_numbers_2'][] = (string)($row['serial_number_2'] ?? '');

            $propertyNumber = trim((string)($row['property_number'] ?? ''));
            if ($propertyNumber !== '') {
                if ($grouped[$signature]['property_number'] === '') {
                    $grouped[$signature]['property_number'] = $propertyNumber;
                }
                $grouped[$signature]['property_numbers'][] = $propertyNumber;
            }
        }

        $items = [];
        $setNo = 1;
        foreach ($grouped as $item) {
            $item['set_no'] = $setNo++;
            $item['serial_number'] = $item['serial_numbers'][0] ?? '';
            $item['serial_number_2'] = $item['serial_numbers_2'][0] ?? '';
            $item['property_number_preview'] = implode(', ', $item['property_numbers']);
            $items[] = $item;
        }

        return $items;
    }
}

if (!function_exists('gso_new_purchase_group_modal_bundles')) {
    function gso_new_purchase_group_modal_bundles(mysqli $conn, array $rows): array {
        $parentPropertyNumbers = [];
        foreach ($rows as $row) {
            $propertyNumber = strtoupper(trim((string)($row['property_number'] ?? '')));
            if ($propertyNumber !== '' && !in_array($propertyNumber, $parentPropertyNumbers, true)) {
                $parentPropertyNumbers[] = $propertyNumber;
            }
        }
        if (empty($parentPropertyNumbers)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($parentPropertyNumbers), '?'));
        $bindTypes = str_repeat('s', count($parentPropertyNumbers));
        $bundleUnitExpr = gso_column_exists($conn, 'new_bundle_purchase', 'unit') ? 'unit' : "'' AS unit";
        $sql = "SELECT id, property_number, bundle_with, category, {$bundleUnitExpr}, item, model, description, serial_number, serial_number_2, par_ics_number
                FROM new_bundle_purchase
                WHERE bundle_with IN ({$placeholders})
                ORDER BY bundle_with ASC, id ASC";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param($bindTypes, ...$parentPropertyNumbers);
        $stmt->execute();
        $result = $stmt->get_result();

        $grouped = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $bundleWith = strtoupper(trim((string)($row['bundle_with'] ?? '')));
                $signatureParts = [
                    $bundleWith,
                    strtoupper(trim((string)($row['category'] ?? ''))),
                    strtoupper(trim((string)($row['unit'] ?? ''))),
                    strtoupper(trim((string)($row['item'] ?? ''))),
                    strtoupper(trim((string)($row['model'] ?? ''))),
                    strtoupper(trim((string)($row['description'] ?? ''))),
                    strtoupper(trim((string)($row['par_ics_number'] ?? ''))),
                ];
                $signature = implode('|', $signatureParts);
                if (!isset($grouped[$signature])) {
                    $grouped[$signature] = [
                        'id' => (int)($row['id'] ?? 0),
                        'bundle_with' => (string)($row['bundle_with'] ?? ''),
                        'property_number' => (string)($row['property_number'] ?? ''),
                        'category' => (string)($row['category'] ?? ''),
                        'unit' => (string)($row['unit'] ?? ''),
                        'item' => (string)($row['item'] ?? ''),
                        'model' => (string)($row['model'] ?? ''),
                        'description' => (string)($row['description'] ?? ''),
                        'par_ics_number' => (string)($row['par_ics_number'] ?? ''),
                        'item_quantity' => 0,
                        'serial_numbers' => [],
                        'serial_numbers_2' => [],
                    ];
                }
                $grouped[$signature]['item_quantity']++;
                $grouped[$signature]['serial_numbers'][] = (string)($row['serial_number'] ?? '');
                $grouped[$signature]['serial_numbers_2'][] = (string)($row['serial_number_2'] ?? '');
            }
        }
        $stmt->close();

        return array_values($grouped);
    }
}

if (!function_exists('gso_new_purchase_property_number_in_use')) {
    function gso_new_purchase_property_number_in_use($conn, $candidate, $newPurchaseId = 0, $oldPropertyNumber = '') {
        $candidate = strtoupper(trim((string)$candidate));
        $oldPropertyNumber = strtoupper(trim((string)$oldPropertyNumber));
        $newPurchaseId = (int)$newPurchaseId;

        if ($candidate === '') {
            return false;
        }

        if ($oldPropertyNumber !== '' && $candidate === $oldPropertyNumber) {
            return false;
        }

        $stmtDup = $conn->prepare('
            SELECT 1
            FROM (
                SELECT property_number AS prop FROM new_purchase WHERE property_number = ? AND id <> ?
                UNION ALL SELECT par_number AS prop FROM new_purchase_history WHERE par_number = ? AND par_number <> ?
                UNION ALL SELECT property_number AS prop FROM new_bundle_purchase WHERE property_number = ? AND property_number <> ?
                UNION ALL SELECT bundle_with AS prop FROM new_bundle_purchase WHERE bundle_with = ? AND bundle_with <> ?
                UNION ALL SELECT par_number AS prop FROM par_gen_fund WHERE par_number = ?
                UNION ALL SELECT property_number AS prop FROM property_sef WHERE property_number = ?
            ) AS duplicates
            LIMIT 1
        ');

        if (!$stmtDup) {
            throw new RuntimeException('Prepare failed: ' . $conn->error);
        }

        $stmtDup->bind_param(
            'sissssssss',
            $candidate,
            $newPurchaseId,
            $candidate,
            $oldPropertyNumber,
            $candidate,
            $oldPropertyNumber,
            $candidate,
            $oldPropertyNumber,
            $candidate,
            $candidate
        );
        $stmtDup->execute();
        $resDup = $stmtDup->get_result();
        $inUse = $resDup && $resDup->num_rows > 0;
        $stmtDup->close();

        return $inUse;
    }
}

if (!function_exists('gso_motor_vehicle_account_codes')) {
    function gso_motor_vehicle_account_codes() {
        return ['1-07-06-010', '1-07-05-080'];
    }
}

if (!function_exists('gso_motor_vehicle_inventory_sql')) {
    function gso_motor_vehicle_inventory_sql(mysqli $conn) {
        $codes = array_map(function ($code) use ($conn) {
            return "'" . mysqli_real_escape_string($conn, $code) . "'";
        }, gso_motor_vehicle_account_codes());
        $accountCodes = implode(',', $codes);
        $remarksColumn = function ($table, $alias) use ($conn) {
            return gso_column_exists($conn, $table, 'remarks') ? "{$alias}.remarks" : "''";
        };
        $parRemarks = $remarksColumn('par_gen_fund', 'p');
        $sefRemarks = $remarksColumn('property_sef', 's');
        $trustRemarks = $remarksColumn('trust_fund', 't');
        $donationRemarks = $remarksColumn('donation', 'd');

        return "
            SELECT *
            FROM (
                SELECT
                    'par_gen_fund' AS source_table,
                    p.pargf_id AS source_id,
                    'General Fund' AS fund_name,
                    p.account_code,
                    p.item AS asset_class,
                    p.model AS brand_model,
                    p.description,
                    p.serial_number AS primary_serial,
                    p.serial_number_2 AS secondary_serial,
                    p.par_number AS property_number,
                    p.unit_value,
                    p.date_aquired,
                    p.supplier,
                    p.purchase_order,
                    p.purchase_request,
                    p.obr_number,
                    p.jev_number,
                    {$parRemarks} AS remarks,
                    COALESCE(ed.department_name, hd.department_name, hdp.department_name, '') AS department_name,
                    COALESCE(e.emp_name, '') AS end_user
                FROM par_gen_fund AS p
                LEFT JOIN (
                    SELECT par_number, MAX(emp_id) AS emp_id, MAX(dept_id) AS dept_id
                    FROM general_fund_property_history
                    WHERE status = 1
                    GROUP BY par_number
                ) AS h ON h.par_number = p.par_number
                LEFT JOIN employee AS e ON e.emp_id = h.emp_id
                LEFT JOIN department AS ed ON ed.department_code = e.department_code
                LEFT JOIN department AS hd ON hd.department_code = h.dept_id
                LEFT JOIN department AS hdp ON hdp.dept_id = h.dept_id
                WHERE p.account_code IN ({$accountCodes})

                UNION ALL

                SELECT
                    'property_sef' AS source_table,
                    s.sef_id AS source_id,
                    'SEF' AS fund_name,
                    s.account_code,
                    s.item AS asset_class,
                    s.model AS brand_model,
                    s.description,
                    s.serial_number AS primary_serial,
                    s.serial_number_2 AS secondary_serial,
                    s.property_number,
                    s.unit_value,
                    s.date_aquired,
                    s.supplier,
                    s.purchase_order,
                    s.purchase_request,
                    s.obr_number,
                    s.jev_number,
                    {$sefRemarks} AS remarks,
                    COALESCE(ed.department_name, sd.department_name, sdp.department_name, '') AS department_name,
                    COALESCE(e.emp_name, '') AS end_user
                FROM property_sef AS s
                LEFT JOIN (
                    SELECT property_number, MAX(emp_id) AS emp_id, MAX(sch_id) AS sch_id
                    FROM sef_property_history
                    WHERE status = 1
                    GROUP BY property_number
                ) AS h ON h.property_number = s.property_number
                LEFT JOIN employee AS e ON e.emp_id = h.emp_id
                LEFT JOIN department AS ed ON ed.department_code = e.department_code
                LEFT JOIN department AS sd ON sd.department_code = h.sch_id
                LEFT JOIN department AS sdp ON sdp.dept_id = h.sch_id
                WHERE s.account_code IN ({$accountCodes})

                UNION ALL

                SELECT
                    'trust_fund' AS source_table,
                    t.id AS source_id,
                    'Trust Fund' AS fund_name,
                    t.account_code,
                    t.item AS asset_class,
                    t.model AS brand_model,
                    t.description,
                    t.serial_number AS primary_serial,
                    t.serial_number_2 AS secondary_serial,
                    COALESCE(NULLIF(t.property_number, ''), h.par_number, '') AS property_number,
                    t.unit_value,
                    t.date_aquired,
                    t.supplier,
                    t.purchase_order,
                    t.purchase_request,
                    t.obr_number,
                    t.jev_number,
                    {$trustRemarks} AS remarks,
                    COALESCE(ed.department_name, hd.department_name, hdp.department_name, '') AS department_name,
                    COALESCE(e.emp_name, '') AS end_user
                FROM trust_fund AS t
                LEFT JOIN (
                    SELECT id, MAX(emp_id) AS emp_id, MAX(dept_id) AS dept_id, MAX(par_number) AS par_number
                    FROM trust_fund_history
                    WHERE status = 1
                    GROUP BY id
                ) AS h ON h.id = t.id
                LEFT JOIN employee AS e ON e.emp_id = h.emp_id
                LEFT JOIN department AS ed ON ed.department_code = e.department_code
                LEFT JOIN department AS hd ON hd.department_code = h.dept_id
                LEFT JOIN department AS hdp ON hdp.dept_id = h.dept_id
                WHERE t.account_code IN ({$accountCodes})

                UNION ALL

                SELECT
                    'donation' AS source_table,
                    d.id AS source_id,
                    'Donation' AS fund_name,
                    d.account_code,
                    d.item AS asset_class,
                    d.model AS brand_model,
                    d.description,
                    d.serial_number AS primary_serial,
                    d.serial_number_2 AS secondary_serial,
                    COALESCE(NULLIF(d.property_number, ''), h.par_number, '') AS property_number,
                    d.unit_value,
                    d.date_aquired,
                    d.supplier,
                    d.purchase_order,
                    d.purchase_request,
                    d.obr_number,
                    d.jev_number,
                    {$donationRemarks} AS remarks,
                    COALESCE(ed.department_name, hd.department_name, hdp.department_name, '') AS department_name,
                    COALESCE(e.emp_name, '') AS end_user
                FROM donation AS d
                LEFT JOIN (
                    SELECT id, MAX(emp_id) AS emp_id, MAX(dept_id) AS dept_id, MAX(par_number) AS par_number
                    FROM donation_history
                    WHERE status = 1
                    GROUP BY id
                ) AS h ON h.id = d.id
                LEFT JOIN employee AS e ON e.emp_id = h.emp_id
                LEFT JOIN department AS ed ON ed.department_code = e.department_code
                LEFT JOIN department AS hd ON hd.department_code = h.dept_id
                LEFT JOIN department AS hdp ON hdp.dept_id = h.dept_id
                WHERE d.account_code IN ({$accountCodes})
            ) AS vehicle_inventory
        ";
    }
}

if (!function_exists('gso_motor_vehicle_source_map')) {
    function gso_motor_vehicle_source_map() {
        return [
            'par_gen_fund' => ['id' => 'pargf_id'],
            'property_sef' => ['id' => 'sef_id'],
            'trust_fund' => ['id' => 'id'],
            'donation' => ['id' => 'id'],
        ];
    }
}

if (!function_exists('gso_motor_vehicle_date_column')) {
    function gso_motor_vehicle_date_column(mysqli $conn) {
        static $dateColumn = null;
        if ($dateColumn !== null) { return $dateColumn; }

        foreach (['mv_date_acquired', 'date_acquired'] as $column) {
            $res = mysqli_query($conn, "SHOW COLUMNS FROM motor_vehicle LIKE '{$column}'");
            if ($res && mysqli_num_rows($res) > 0) {
                mysqli_free_result($res);
                $dateColumn = $column;
                return $dateColumn;
            }
            if ($res) { mysqli_free_result($res); }
        }

        $dateColumn = 'mv_date_acquired';
        return $dateColumn;
    }
}

if (!function_exists('gso_motor_vehicle_classification_column')) {
    function gso_motor_vehicle_classification_column(mysqli $conn) {
        static $classificationColumn = null;
        if ($classificationColumn !== null) { return $classificationColumn; }

        foreach (['classification', 'clasification'] as $column) {
            if (gso_column_exists($conn, 'motor_vehicle', $column)) {
                $classificationColumn = $column;
                return $classificationColumn;
            }
        }

        $classificationColumn = '';
        return $classificationColumn;
    }
}

if (!function_exists('gso_motor_vehicle_ensure_schema')) {
    function gso_motor_vehicle_ensure_schema(mysqli $conn) {
        static $ensured = false;
        if ($ensured) { return; }
        $ensured = true;

        if (!gso_column_exists($conn, 'motor_vehicle', 'conduction_sticker')) {
            @mysqli_query($conn, "ALTER TABLE motor_vehicle ADD COLUMN conduction_sticker VARCHAR(100) NULL AFTER mv_file");
        }
    }
}

if (!function_exists('gso_motor_vehicle_fetch_record')) {
    function gso_motor_vehicle_fetch_record(mysqli $conn, $sourceTable, $sourceId) {
        $sourceTable = trim((string)$sourceTable);
        $sourceId = (int)$sourceId;
        if ($sourceTable === '' || $sourceId <= 0 || !isset(gso_motor_vehicle_source_map()[$sourceTable])) {
            return null;
        }

        $inventorySql = gso_motor_vehicle_inventory_sql($conn);
        $vehicleDateColumn = gso_motor_vehicle_date_column($conn);
        $conductionStickerColumn = gso_column_exists($conn, 'motor_vehicle', 'conduction_sticker') ? 'mv.conduction_sticker' : "''";
        $sql = "
            SELECT
                v.*,
                mv.motor_vehicle_id,
                mv.{$vehicleDateColumn} AS mv_date_acquired,
                mv.chassis_no,
                mv.engine_no,
                mv.plate_no,
                mv.color,
                mv.mv_file,
                {$conductionStickerColumn} AS conduction_sticker,
                mv.vehicle_usage,
                mv.capacity,
                mv.year_model,
                mv.cr_number,
                mv.or_number,
                mv.coverage
            FROM ({$inventorySql}) AS v
            LEFT JOIN motor_vehicle AS mv
                ON CONVERT(mv.property_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
                 = CONVERT(v.property_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
            WHERE v.source_table = ? AND v.source_id = ?
            LIMIT 1
        ";

        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) { return null; }
        mysqli_stmt_bind_param($stmt, 'si', $sourceTable, $sourceId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = ($res && ($data = mysqli_fetch_assoc($res))) ? $data : null;
        mysqli_stmt_close($stmt);
        return $row;
    }
}

if (!function_exists('gso_motor_vehicle_response_record')) {
    function gso_motor_vehicle_response_record($row) {
        $pick = function ($preferred, $fallback = '') use ($row) {
            $value = trim((string)($row[$preferred] ?? ''));
            if ($value !== '') { return $value; }
            return trim((string)($row[$fallback] ?? ''));
        };

        return [
            'source_table' => (string)($row['source_table'] ?? ''),
            'source_id' => (int)($row['source_id'] ?? 0),
            'fund_name' => (string)($row['fund_name'] ?? ''),
            'brand_model' => (string)($row['brand_model'] ?? ''),
            'description' => (string)($row['description'] ?? ''),
            'property_number' => (string)($row['property_number'] ?? ''),
            'date_acquired' => $pick('mv_date_acquired', 'date_aquired'),
            'chassis_no' => $pick('chassis_no', 'primary_serial'),
            'engine_no' => $pick('engine_no', 'secondary_serial'),
            'plate_no' => (string)($row['plate_no'] ?? ''),
            'color' => (string)($row['color'] ?? ''),
            'mv_file' => (string)($row['mv_file'] ?? ''),
            'conduction_sticker' => (string)($row['conduction_sticker'] ?? ''),
            'vehicle_usage' => (string)($row['vehicle_usage'] ?? ''),
            'capacity' => (string)($row['capacity'] ?? ''),
            'year_model' => (string)($row['year_model'] ?? ''),
            'cr_number' => (string)($row['cr_number'] ?? ''),
            'or_number' => (string)($row['or_number'] ?? ''),
            'coverage' => trim((string)($row['coverage'] ?? '')) !== '' ? (string)$row['coverage'] : 'None',
            'supplier' => (string)($row['supplier'] ?? ''),
            'amount' => (string)($row['unit_value'] ?? ''),
            'po' => (string)($row['purchase_order'] ?? ''),
            'pr' => (string)($row['purchase_request'] ?? ''),
            'obr' => (string)($row['obr_number'] ?? ''),
            'jev' => (string)($row['jev_number'] ?? ''),
            'remarks' => (string)($row['remarks'] ?? ''),
            'department_name' => (string)($row['department_name'] ?? ''),
            'end_user' => (string)($row['end_user'] ?? ''),
        ];
    }
}

if (!function_exists('gso_motor_vehicle_dashboard_from_sql')) {
    function gso_motor_vehicle_dashboard_from_sql($inventorySql) {
        return "
            FROM ({$inventorySql}) AS v
            LEFT JOIN motor_vehicle AS mv
                ON CONVERT(mv.property_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
                 = CONVERT(v.property_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
        ";
    }
}

if (!function_exists('gso_motor_vehicle_schedule')) {
    function gso_motor_vehicle_schedule($plateNo) {
        preg_match_all('/\d/', strtoupper((string)$plateNo), $matches);
        $digits = implode('', $matches[0] ?? []);

        if (strlen($digits) < 2) {
            return ['valid' => false, 'label' => 'Invalid plate number'];
        }

        $lastDigit = (int)substr($digits, -1);
        $secondLastDigit = (int)substr($digits, -2, 1);
        $monthNo = $lastDigit === 0 ? 10 : $lastDigit;
        $monthName = date('F', mktime(0, 0, 0, $monthNo, 1));

        if ($secondLastDigit >= 1 && $secondLastDigit <= 3) {
            $startDay = 1;
            $endDay = 7;
        } elseif ($secondLastDigit >= 4 && $secondLastDigit <= 6) {
            $startDay = 8;
            $endDay = 14;
        } elseif ($secondLastDigit >= 7 && $secondLastDigit <= 8) {
            $startDay = 15;
            $endDay = 21;
        } else {
            $startDay = 22;
            $endDay = 31;
        }

        return [
            'valid' => true,
            'month_no' => $monthNo,
            'month_name' => $monthName,
            'start_day' => $startDay,
            'end_day' => $endDay,
            'label' => $monthName . ' ' . $startDay . '-' . $endDay,
        ];
    }
}

if (!function_exists('gso_motor_vehicle_purchase_date')) {
    function gso_motor_vehicle_purchase_date($value) {
        $text = trim((string)$value);
        if ($text === '') { return ['type' => 'none']; }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $text)) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $text);
            if ($date && $date->format('Y-m-d') === $text) {
                return ['type' => 'date', 'date' => $date];
            }
        }

        if (preg_match('/^(\d{4})/', $text, $match)) {
            return ['type' => 'year', 'year' => (int)$match[1]];
        }

        return ['type' => 'none'];
    }
}

if (!function_exists('gso_motor_vehicle_renewal_start')) {
    function gso_motor_vehicle_renewal_start($dateAcquired, DateTimeImmutable $today) {
        $purchase = gso_motor_vehicle_purchase_date($dateAcquired);

        if (($purchase['type'] ?? '') === 'date') {
            $startDate = $purchase['date']->modify('+3 years');
            return [
                'eligible' => $startDate <= $today,
                'label' => $startDate->format('M j, Y'),
            ];
        }

        if (($purchase['type'] ?? '') === 'year') {
            $startYear = ((int)$purchase['year']) + 3;
            return [
                'eligible' => (int)$today->format('Y') >= $startYear,
                'label' => (string)$startYear,
            ];
        }

        return ['eligible' => true, 'label' => ''];
    }
}

if (!function_exists('gso_motor_vehicle_registration_info')) {
    function gso_motor_vehicle_registration_info($row) {
        $today = new DateTimeImmutable('today');
        $schedule = gso_motor_vehicle_schedule($row['plate_no'] ?? '');
        $dateAcquired = trim((string)($row['mv_date_acquired'] ?? ''));
        if ($dateAcquired === '') {
            $dateAcquired = trim((string)($row['date_aquired'] ?? $row['year_acquired'] ?? ''));
        }

        $base = [
            'registration_schedule' => (string)($schedule['label'] ?? ''),
            'registration_status' => '',
            'registration_status_key' => 'scheduled',
            'is_due_current_month' => false,
        ];

        if ((int)($row['motor_vehicle_id'] ?? 0) <= 0) {
            $base['registration_schedule'] = '';
            $base['registration_status'] = 'Needs vehicle details';
            $base['registration_status_key'] = 'unregistered';
            return $base;
        }

        if (empty($schedule['valid'])) {
            $base['registration_status'] = 'Invalid plate number';
            $base['registration_status_key'] = 'invalid';
            return $base;
        }

        $renewalStart = gso_motor_vehicle_renewal_start($dateAcquired, $today);
        if (empty($renewalStart['eligible'])) {
            $base['registration_status'] = 'Starts ' . $renewalStart['label'];
            $base['registration_status_key'] = 'new_vehicle';
            return $base;
        }

        if ((int)$schedule['month_no'] !== (int)$today->format('n')) {
            $base['registration_status'] = 'Scheduled for ' . $schedule['month_name'];
            return $base;
        }

        $base['is_due_current_month'] = true;
        $day = (int)$today->format('j');

        if ($day > (int)$schedule['end_day']) {
            $base['registration_status'] = 'Past deadline';
            $base['registration_status_key'] = 'past_deadline';
        } elseif ($day >= (int)$schedule['start_day']) {
            $base['registration_status'] = 'Due this week';
            $base['registration_status_key'] = 'due_now';
        } else {
            $base['registration_status'] = 'Due this month';
            $base['registration_status_key'] = 'due_current_month';
        }

        return $base;
    }
}

if (!function_exists('gso_motor_vehicle_dashboard_rows')) {
    function gso_motor_vehicle_dashboard_rows(mysqli $conn, $fromSql, $vehicleDateColumn, $whereSql = '', $orderSql = '', $limitSql = '', $types = '', array $params = array()) {
        $sql = "
            SELECT
                v.source_table,
                v.source_id,
                v.fund_name,
                v.account_code,
                v.brand_model,
                v.date_aquired AS year_acquired,
                v.date_aquired,
                v.property_number,
                mv.motor_vehicle_id,
                mv.{$vehicleDateColumn} AS mv_date_acquired,
                COALESCE(NULLIF(mv.chassis_no, ''), v.primary_serial) AS chassis_no,
                COALESCE(NULLIF(mv.engine_no, ''), v.secondary_serial) AS engine_no,
                COALESCE(mv.plate_no, '') AS plate_no,
                COALESCE(mv.coverage, '') AS coverage,
                v.department_name,
                v.end_user
            {$fromSql}
            {$whereSql}
            {$orderSql}
            {$limitSql}
        ";

        list($stmt, $rows) = gso_query_all($conn, $sql, $types, $params);
        if ($stmt instanceof mysqli_stmt) { $stmt->close(); }
        return $rows;
    }
}

if (!function_exists('gso_motor_vehicle_dashboard_data')) {
    function gso_motor_vehicle_dashboard_data($row) {
        $registration = gso_motor_vehicle_registration_info($row);
        return [
            'source_table' => (string)($row['source_table'] ?? ''),
            'source_id' => (int)($row['source_id'] ?? 0),
            'fund_name' => (string)($row['fund_name'] ?? ''),
            'account_code' => (string)($row['account_code'] ?? ''),
            'brand_model' => (string)($row['brand_model'] ?? ''),
            'year_acquired' => (string)($row['year_acquired'] ?? ''),
            'property_number' => (string)($row['property_number'] ?? ''),
            'chassis_no' => (string)($row['chassis_no'] ?? ''),
            'engine_no' => (string)($row['engine_no'] ?? ''),
            'plate_no' => (string)($row['plate_no'] ?? ''),
            'coverage' => (string)($row['coverage'] ?? ''),
            'department_name' => (string)($row['department_name'] ?? ''),
            'end_user' => (string)($row['end_user'] ?? ''),
            'registration_schedule' => (string)$registration['registration_schedule'],
            'registration_status' => (string)$registration['registration_status'],
            'registration_status_key' => (string)$registration['registration_status_key'],
            'is_due_current_month' => !empty($registration['is_due_current_month']) ? 1 : 0,
        ];
    }
}

if (!function_exists('gso_motor_vehicle_due_count')) {
    function gso_motor_vehicle_due_count(mysqli $conn, $fromSql, $vehicleDateColumn, $whereSql = '', $types = '', array $params = array()) {
        $rows = gso_motor_vehicle_dashboard_rows($conn, $fromSql, $vehicleDateColumn, $whereSql, '', '', $types, $params);
        $count = 0;
        foreach ($rows as $row) {
            $registration = gso_motor_vehicle_registration_info($row);
            if (!empty($registration['is_due_current_month'])) { $count++; }
        }
        return $count;
    }
}

if (!function_exists('gso_table_exists')) {
    function gso_table_exists(mysqli $conn, $table) {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$table);
        if ($table === '') { return false; }
        $res = mysqli_query($conn, "SHOW TABLES LIKE '{$table}'");
        return ($res && mysqli_num_rows($res) > 0);
    }
}

if (!function_exists('gso_motor_vehicle_stat_label')) {
    function gso_motor_vehicle_stat_label($value) {
        $text = strtoupper(trim((string)$value));
        if ($text === '') { return 'Unspecified'; }
        if ($text === 'SEF' || strpos($text, 'SPECIAL EDUCATION') !== false) { return 'SEF'; }
        if (strpos($text, 'TRUST') !== false) { return 'Trust Fund'; }
        if (strpos($text, 'DONATION') !== false) { return 'Donation'; }
        if (strpos($text, 'GENERAL') !== false) { return 'General Fund'; }
        return ucwords(strtolower($text));
    }
}

if (!function_exists('gso_motor_vehicle_is_insured')) {
    function gso_motor_vehicle_is_insured($coverage) {
        $text = strtoupper(trim((string)$coverage));
        return $text !== '' && $text !== 'NONE';
    }
}

if (!function_exists('gso_motor_vehicle_is_current_year_purchase')) {
    function gso_motor_vehicle_is_current_year_purchase($row, $year) {
        $dateAcquired = trim((string)($row['mv_date_acquired'] ?? ''));
        if ($dateAcquired === '') {
            $dateAcquired = trim((string)($row['date_aquired'] ?? $row['year_acquired'] ?? ''));
        }
        if (!preg_match('/^(\d{4})/', $dateAcquired, $match)) { return false; }
        return (int)$match[1] === (int)$year;
    }
}

if (!function_exists('gso_motor_vehicle_active_unserviceable_rows')) {
    function gso_motor_vehicle_active_unserviceable_rows(mysqli $conn, $vehicleDateColumn) {
        if (!gso_table_exists($conn, 'unserviceable_items')) { return []; }

        $codes = array_map(function ($code) use ($conn) {
            return "'" . mysqli_real_escape_string($conn, $code) . "'";
        }, gso_motor_vehicle_account_codes());
        $accountCodes = implode(',', $codes);

        $historyJoin = '';
        $statusWhere = '';
        if (gso_table_exists($conn, 'unserviceable_items_history')) {
            $historyJoin = "
                LEFT JOIN (
                    SELECT h1.*
                    FROM unserviceable_items_history AS h1
                    INNER JOIN (
                        SELECT par_number, MAX(created_at) AS max_created_at
                        FROM unserviceable_items_history
                        GROUP BY par_number
                    ) AS h2
                        ON h1.par_number = h2.par_number
                       AND h1.created_at = h2.max_created_at
                ) AS h ON h.par_number = u.par_number
            ";
            $statusWhere = ' AND COALESCE(h.status, 0) = 0';
        }

        $sql = "
            SELECT
                'unserviceable_items' AS source_table,
                u.id AS source_id,
                COALESCE(u.fund, '') AS fund_name,
                u.account_code,
                u.model AS brand_model,
                u.date_aquired AS year_acquired,
                u.date_aquired,
                u.par_number AS property_number,
                mv.motor_vehicle_id,
                mv.{$vehicleDateColumn} AS mv_date_acquired,
                COALESCE(NULLIF(mv.chassis_no, ''), u.serial_number) AS chassis_no,
                COALESCE(NULLIF(mv.engine_no, ''), u.serial_number_2) AS engine_no,
                COALESCE(mv.plate_no, '') AS plate_no,
                COALESCE(mv.coverage, '') AS coverage
            FROM unserviceable_items AS u
            {$historyJoin}
            LEFT JOIN motor_vehicle AS mv
                ON CONVERT(mv.property_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
                 = CONVERT(u.par_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
            WHERE u.account_code IN ({$accountCodes})
            {$statusWhere}
        ";

        $res = mysqli_query($conn, $sql);
        $rows = [];
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $rows[] = $row;
            }
        }
        return $rows;
    }
}

if (!function_exists('gso_motor_vehicle_return_stock_rows')) {
    function gso_motor_vehicle_return_stock_rows(mysqli $conn, $vehicleDateColumn) {
        if (!gso_table_exists($conn, 'return_to_stock')) { return []; }

        $codes = array_map(function ($code) use ($conn) {
            return "'" . mysqli_real_escape_string($conn, $code) . "'";
        }, gso_motor_vehicle_account_codes());
        $accountCodes = implode(',', $codes);

        $sql = "
            SELECT
                'return_to_stock' AS source_table,
                r.id AS source_id,
                COALESCE(r.fund, '') AS fund_name,
                r.account_code,
                r.model AS brand_model,
                r.date_aquired AS year_acquired,
                r.date_aquired,
                r.par_number AS property_number,
                mv.motor_vehicle_id,
                mv.{$vehicleDateColumn} AS mv_date_acquired,
                COALESCE(NULLIF(mv.chassis_no, ''), r.serial_number) AS chassis_no,
                COALESCE(NULLIF(mv.engine_no, ''), r.serial_number_2) AS engine_no,
                COALESCE(mv.plate_no, '') AS plate_no,
                COALESCE(mv.coverage, '') AS coverage
            FROM return_to_stock AS r
            LEFT JOIN motor_vehicle AS mv
                ON CONVERT(mv.property_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
                 = CONVERT(r.par_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
            WHERE r.account_code IN ({$accountCodes})
        ";

        $res = mysqli_query($conn, $sql);
        $rows = [];
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $rows[] = $row;
            }
        }
        return $rows;
    }
}

if (!function_exists('gso_motor_vehicle_unique_rows')) {
    function gso_motor_vehicle_unique_rows($rows) {
        $unique = [];
        foreach ($rows as $index => $row) {
            $propertyNumber = strtoupper(trim((string)($row['property_number'] ?? '')));
            $key = $propertyNumber !== '' ? $propertyNumber : ('ROW-' . (string)$index);
            if (isset($unique[$key])) { continue; }
            $unique[$key] = $row;
        }
        return array_values($unique);
    }
}

if (!function_exists('gso_motor_vehicle_statistics_data')) {
    function gso_motor_vehicle_statistics_data(mysqli $conn, $dashboardFromSql, $vehicleDateColumn) {
        $currentYear = (int)date('Y');
        $serviceableRows = gso_motor_vehicle_unique_rows(array_merge(
            gso_motor_vehicle_dashboard_rows($conn, $dashboardFromSql, $vehicleDateColumn),
            gso_motor_vehicle_return_stock_rows($conn, $vehicleDateColumn)
        ));
        $unserviceableRows = gso_motor_vehicle_active_unserviceable_rows($conn, $vehicleDateColumn);
        $allRows = array_merge($serviceableRows, $unserviceableRows);

        $cards = [
            'total_vehicles' => count($allRows),
            'registered_vehicles' => 0,
            'for_registration' => 0,
            'insured_vehicles' => 0,
            'serviceable_vehicles' => count($serviceableRows),
            'unserviceable_vehicles' => count($unserviceableRows),
            'new_motor_vehicles' => 0,
            'needs_details' => 0,
        ];

        $funds = [];
        foreach ($serviceableRows as $row) {
            $fund = gso_motor_vehicle_stat_label($row['fund_name'] ?? '');
            if (!isset($funds[$fund])) {
                $funds[$fund] = ['fund_name' => $fund, 'serviceable' => 0, 'unserviceable' => 0, 'total' => 0];
            }
            $funds[$fund]['serviceable']++;
            $funds[$fund]['total']++;
        }

        foreach ($unserviceableRows as $row) {
            $fund = gso_motor_vehicle_stat_label($row['fund_name'] ?? '');
            if (!isset($funds[$fund])) {
                $funds[$fund] = ['fund_name' => $fund, 'serviceable' => 0, 'unserviceable' => 0, 'total' => 0];
            }
            $funds[$fund]['unserviceable']++;
            $funds[$fund]['total']++;
        }

        foreach ($allRows as $row) {
            $registered = (int)($row['motor_vehicle_id'] ?? 0) > 0;
            if ($registered) {
                $cards['registered_vehicles']++;
                if (gso_motor_vehicle_is_insured($row['coverage'] ?? '')) {
                    $cards['insured_vehicles']++;
                }
            } else {
                $cards['needs_details']++;
            }

            if (gso_motor_vehicle_is_current_year_purchase($row, $currentYear)) {
                $cards['new_motor_vehicles']++;
            }
        }

        foreach ($serviceableRows as $row) {
            $registration = gso_motor_vehicle_registration_info($row);
            if (!empty($registration['is_due_current_month'])) {
                $cards['for_registration']++;
            }
        }

        usort($funds, function ($a, $b) {
            return strcmp((string)$a['fund_name'], (string)$b['fund_name']);
        });

        return [
            'cards' => $cards,
            'charts' => [
                'breakdown' => [
                    'labels' => ['Registered', 'For Registration', 'Insured', 'Serviceable', 'Unserviceable', 'New Motor Vehicles', 'Needs Details'],
                    'data' => [
                        $cards['registered_vehicles'],
                        $cards['for_registration'],
                        $cards['insured_vehicles'],
                        $cards['serviceable_vehicles'],
                        $cards['unserviceable_vehicles'],
                        $cards['new_motor_vehicles'],
                        $cards['needs_details'],
                    ],
                ],
                'condition' => [
                    'labels' => ['Serviceable', 'Unserviceable'],
                    'data' => [$cards['serviceable_vehicles'], $cards['unserviceable_vehicles']],
                ],
                'registration' => [
                    'labels' => ['Registered', 'Needs Details', 'For Registration'],
                    'data' => [$cards['registered_vehicles'], $cards['needs_details'], $cards['for_registration']],
                ],
                'coverage' => [
                    'labels' => ['Insured', 'No Coverage / No Details'],
                    'data' => [$cards['insured_vehicles'], max(0, $cards['total_vehicles'] - $cards['insured_vehicles'])],
                ],
            ],
            'funds' => array_values($funds),
            'as_of' => date('F j, Y'),
        ];
    }
}

if (!function_exists('gso_motor_vehicle_can_access')) {
    function gso_motor_vehicle_can_access() {
        $role = strtoupper(trim((string)($_SESSION['role'] ?? '')));
        return in_array($role, ['SYSTEM-ADMIN', 'MV-ADMIN'], true);
    }
}

if (isset($_REQUEST['motor_vehicle_dashboard'])) {
    header('Content-Type: application/json; charset=utf-8');

    if (!isset($_SESSION['alogin']) || trim((string)$_SESSION['alogin']) === '') {
        http_response_code(401);
        echo json_encode(['status' => 401, 'message' => 'Unauthorized']);
        exit();
    }

    if (!gso_motor_vehicle_can_access()) {
        http_response_code(403);
        echo json_encode(['status' => 403, 'message' => 'Unauthorized']);
        exit();
    }

    $mode = strtolower(trim((string)$_REQUEST['motor_vehicle_dashboard']));
    gso_motor_vehicle_ensure_schema($conn);
    $inventorySql = gso_motor_vehicle_inventory_sql($conn);
    $vehicleDateColumn = gso_motor_vehicle_date_column($conn);
    $dashboardFromSql = gso_motor_vehicle_dashboard_from_sql($inventorySql);

    if ($mode === 'metrics') {
        $sql = "
            SELECT
                COUNT(*) AS total_vehicles,
                SUM(CASE WHEN mv.motor_vehicle_id IS NULL THEN 0 ELSE 1 END) AS registered_vehicles,
                COUNT(DISTINCT v.fund_name) AS fund_sources
            {$dashboardFromSql}
        ";
        $res = mysqli_query($conn, $sql);
        $row = $res ? mysqli_fetch_assoc($res) : [];
        $total = (int)($row['total_vehicles'] ?? 0);
        $registered = (int)($row['registered_vehicles'] ?? 0);
        $dueCount = gso_motor_vehicle_due_count($conn, $dashboardFromSql, $vehicleDateColumn, ' WHERE mv.motor_vehicle_id IS NOT NULL');

        echo json_encode([
            'total_vehicles' => $total,
            'registered_vehicles' => $registered,
            'for_registration' => $dueCount,
            'unregistered_vehicles' => max(0, $total - $registered),
            'fund_sources' => (int)($row['fund_sources'] ?? 0),
            'account_codes' => gso_motor_vehicle_account_codes(),
            'current_month' => date('F Y'),
        ]);
        exit();
    }

    if ($mode === 'statistics') {
        echo json_encode([
            'status' => 200,
            'message' => 'OK',
            'data' => gso_motor_vehicle_statistics_data($conn, $dashboardFromSql, $vehicleDateColumn),
        ]);
        exit();
    }

    if ($mode === 'filters') {
        $years = [];
        $departments = [];

        $yearSql = "
            SELECT DISTINCT TRIM(v.date_aquired) AS year_acquired
            FROM ({$inventorySql}) AS v
            WHERE TRIM(COALESCE(v.date_aquired, '')) <> ''
            ORDER BY CAST(TRIM(v.date_aquired) AS UNSIGNED) DESC, TRIM(v.date_aquired) DESC
        ";
        $yearRes = mysqli_query($conn, $yearSql);
        if ($yearRes) {
            while ($row = mysqli_fetch_assoc($yearRes)) {
                $year = trim((string)($row['year_acquired'] ?? ''));
                if ($year !== '') { $years[] = $year; }
            }
        }

        $departmentSql = "
            SELECT DISTINCT TRIM(v.department_name) AS department_name
            FROM ({$inventorySql}) AS v
            WHERE TRIM(COALESCE(v.department_name, '')) <> ''
            ORDER BY TRIM(v.department_name) ASC
        ";
        $departmentRes = mysqli_query($conn, $departmentSql);
        if ($departmentRes) {
            while ($row = mysqli_fetch_assoc($departmentRes)) {
                $department = trim((string)($row['department_name'] ?? ''));
                if ($department !== '') { $departments[] = $department; }
            }
        }

        echo json_encode([
            'status' => 200,
            'message' => 'OK',
            'data' => [
                'years' => $years,
                'departments' => $departments,
            ],
        ]);
        exit();
    }

    if ($mode === 'table') {
        $draw = isset($_POST['draw']) ? (int)$_POST['draw'] : 0;
        $searchValue = trim((string)($_POST['search']['value'] ?? ''));
        $yearFilter = trim((string)($_POST['mv_year_acquired'] ?? ''));
        $departmentFilter = trim((string)($_POST['mv_department'] ?? ''));
        $scope = strtolower(trim((string)($_POST['mv_scope'] ?? 'all')));
        $scope = in_array($scope, ['all', 'registered', 'unregistered', 'due_current_month'], true) ? $scope : 'all';
        $scopeParts = [];
        $scopeTypes = '';
        $scopeParams = [];
        $filterParts = [];
        $filterTypes = '';
        $filterParams = [];

        if ($scope === 'registered' || $scope === 'due_current_month') {
            $scopeParts[] = 'mv.motor_vehicle_id IS NOT NULL';
        } elseif ($scope === 'unregistered') {
            $scopeParts[] = 'mv.motor_vehicle_id IS NULL';
        }

        if ($searchValue !== '') {
            $filterParts[] = "(
                v.brand_model LIKE ?
                OR v.date_aquired LIKE ?
                OR v.property_number LIKE ?
                OR v.primary_serial LIKE ?
                OR v.secondary_serial LIKE ?
                OR mv.chassis_no LIKE ?
                OR mv.engine_no LIKE ?
                OR mv.plate_no LIKE ?
                OR v.department_name LIKE ?
                OR v.end_user LIKE ?
            )";
            $like = '%' . $searchValue . '%';
            $filterTypes .= str_repeat('s', 10);
            for ($i = 0; $i < 10; $i++) { $filterParams[] = $like; }
        }

        if ($yearFilter !== '') {
            $filterParts[] = "TRIM(v.date_aquired) = ?";
            $filterTypes .= 's';
            $filterParams[] = $yearFilter;
        }

        if ($departmentFilter !== '') {
            $filterParts[] = "TRIM(v.department_name) = ?";
            $filterTypes .= 's';
            $filterParams[] = $departmentFilter;
        }

        $whereSql = function ($parts) {
            return !empty($parts) ? ' WHERE ' . implode(' AND ', $parts) : '';
        };
        $scopeWhereSql = $whereSql($scopeParts);
        $filteredWhereSql = $whereSql(array_merge($scopeParts, $filterParts));
        $filteredTypes = $scopeTypes . $filterTypes;
        $filteredParams = array_merge($scopeParams, $filterParams);

        $columns = [
            0 => 'v.brand_model',
            1 => 'v.date_aquired',
            2 => "COALESCE(NULLIF(mv.chassis_no, ''), v.primary_serial)",
            3 => "COALESCE(NULLIF(mv.engine_no, ''), v.secondary_serial)",
            4 => 'mv.plate_no',
            5 => 'v.department_name',
            6 => 'v.end_user',
        ];
        $orderSql = ' ORDER BY v.department_name ASC, v.end_user ASC, v.brand_model ASC';
        if (isset($_POST['order'][0]['column'])) {
            $idx = (int)$_POST['order'][0]['column'];
            $dir = (isset($_POST['order'][0]['dir']) && $_POST['order'][0]['dir'] === 'desc') ? 'DESC' : 'ASC';
            if (isset($columns[$idx])) {
                $orderSql = ' ORDER BY ' . $columns[$idx] . ' ' . $dir;
            }
        }

        $start = isset($_POST['start']) ? max(0, (int)$_POST['start']) : 0;
        $length = isset($_POST['length']) ? (int)$_POST['length'] : 10;
        $limitSql = $length !== -1 ? ' LIMIT ' . $start . ',' . max(0, $length) : '';

        if ($scope === 'due_current_month') {
            $total = gso_motor_vehicle_due_count($conn, $dashboardFromSql, $vehicleDateColumn, $scopeWhereSql, $scopeTypes, $scopeParams);
            $rows = gso_motor_vehicle_dashboard_rows($conn, $dashboardFromSql, $vehicleDateColumn, $filteredWhereSql, $orderSql, '', $filteredTypes, $filteredParams);
            $dueRows = [];
            foreach ($rows as $row) {
                $registration = gso_motor_vehicle_registration_info($row);
                if (!empty($registration['is_due_current_month'])) { $dueRows[] = $row; }
            }
            $filtered = count($dueRows);
            $rows = $length !== -1 ? array_slice($dueRows, $start, max(0, $length)) : $dueRows;
        } else {
            $totalRow = gso_query_one($conn, "SELECT COUNT(*) AS cnt {$dashboardFromSql} {$scopeWhereSql}", $scopeTypes, $scopeParams);
            $total = (int)($totalRow['cnt'] ?? 0);

            $filteredRow = gso_query_one($conn, "SELECT COUNT(*) AS cnt {$dashboardFromSql} {$filteredWhereSql}", $filteredTypes, $filteredParams);
            $filtered = (int)($filteredRow['cnt'] ?? 0);

            $limitTypes = $filteredTypes;
            $limitParams = $filteredParams;
            if ($length !== -1) {
                $limitTypes .= 'ii';
                $limitParams[] = $start;
                $limitParams[] = max(0, $length);
                $limitSql = ' LIMIT ?, ?';
            } else {
                $limitSql = '';
            }

            $rows = gso_motor_vehicle_dashboard_rows($conn, $dashboardFromSql, $vehicleDateColumn, $filteredWhereSql, $orderSql, $limitSql, $limitTypes, $limitParams);
        }

        $data = [];
        foreach ($rows as $row) {
            $data[] = gso_motor_vehicle_dashboard_data($row);
        }

        echo json_encode([
            'draw' => $draw,
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $data,
        ]);
        exit();
    }

    if ($mode === 'detail') {
        $sourceTable = trim((string)($_REQUEST['source_table'] ?? ''));
        $sourceId = (int)($_REQUEST['source_id'] ?? 0);
        $row = gso_motor_vehicle_fetch_record($conn, $sourceTable, $sourceId);

        if (!$row) {
            echo json_encode(['status' => 404, 'message' => 'Motor vehicle record not found.']);
            exit();
        }

        echo json_encode([
            'status' => 200,
            'message' => 'OK',
            'data' => gso_motor_vehicle_response_record($row),
        ]);
        exit();
    }

    if ($mode === 'save') {
        $sourceTable = trim((string)($_POST['source_table'] ?? ''));
        $sourceId = (int)($_POST['source_id'] ?? 0);
        $sourceMap = gso_motor_vehicle_source_map();

        if (!isset($sourceMap[$sourceTable]) || $sourceId <= 0) {
            echo json_encode(['status' => 422, 'message' => 'Invalid source record.']);
            exit();
        }

        $row = gso_motor_vehicle_fetch_record($conn, $sourceTable, $sourceId);
        if (!$row) {
            echo json_encode(['status' => 404, 'message' => 'Motor vehicle record not found.']);
            exit();
        }

        $propertyNumber = trim((string)($row['property_number'] ?? ''));
        if ($propertyNumber === '') {
            echo json_encode(['status' => 422, 'message' => 'Property number is required before saving vehicle details.']);
            exit();
        }

        $text = function ($key) {
            return strtoupper(trim((string)($_POST[$key] ?? '')));
        };

        $brandModel = $text('brand_model');
        $description = $text('description');
        $dateAcquired = $text('date_acquired');
        $chassisNo = $text('chassis_no');
        $engineNo = $text('engine_no');
        $plateNo = $text('plate_no');
        $color = $text('color');
        $mvFile = $text('mv_file');
        $conductionSticker = $text('conduction_sticker');
        $vehicleUsage = $text('vehicle_usage');
        $capacity = $text('capacity');
        $yearModel = (int)($_POST['year_model'] ?? 0);
        if ($yearModel <= 0 && preg_match('/^(\d{4})/', $dateAcquired, $yearMatch)) {
            $yearModel = (int)$yearMatch[1];
        }
        $crNumber = $text('cr_number');
        $orNumber = $text('or_number');
        $supplier = $text('supplier');
        $po = $text('po');
        $pr = $text('pr');
        $obr = $text('obr');
        $jev = $text('jev');
        $remarks = $text('remarks');

        $amountRaw = preg_replace('/[^0-9.]/', '', (string)($_POST['amount'] ?? ''));
        $amount = $amountRaw === '' ? 0.0 : (float)$amountRaw;

        $coverageInput = strtoupper(preg_replace('/\s+/', '', trim((string)($_POST['coverage'] ?? 'None'))));
        $coverage = 'None';
        if ($coverageInput === 'TPL') {
            $coverage = 'TPL';
        } elseif (in_array($coverageInput, ['COMPREHENSIVE', 'COMPHRENSIVE'], true)) {
            $coverage = 'Comprehensive';
        }

        $table = $sourceTable;
        $idColumn = $sourceMap[$sourceTable]['id'];
        $vehicleDateColumn = gso_motor_vehicle_date_column($conn);
        $hasSourceRemarks = gso_column_exists($conn, $table, 'remarks');
        $hasConductionSticker = gso_column_exists($conn, 'motor_vehicle', 'conduction_sticker');
        $vehicleClassificationColumn = gso_motor_vehicle_classification_column($conn);
        $vehicleClassification = strtoupper(trim((string)($row['asset_class'] ?? '')));
        if ($vehicleClassification === '') { $vehicleClassification = 'MOTOR VEHICLE'; }

        mysqli_begin_transaction($conn);
        try {
            $assetRemarksSql = $hasSourceRemarks ? ",\n                    remarks = ?" : '';
            $assetSql = "
                UPDATE {$table}
                SET model = ?,
                    description = ?,
                    serial_number = ?,
                    serial_number_2 = ?,
                    unit_value = ?,
                    date_aquired = ?,
                    supplier = ?,
                    purchase_order = ?,
                    purchase_request = ?,
                    obr_number = ?,
                    jev_number = ?{$assetRemarksSql}
                WHERE {$idColumn} = ?
                LIMIT 1
            ";
            $assetStmt = mysqli_prepare($conn, $assetSql);
            if (!$assetStmt) { throw new Exception('Unable to prepare asset update.'); }
            if ($hasSourceRemarks) {
                mysqli_stmt_bind_param(
                    $assetStmt,
                    'ssssdsssssssi',
                    $brandModel,
                    $description,
                    $chassisNo,
                    $engineNo,
                    $amount,
                    $dateAcquired,
                    $supplier,
                    $po,
                    $pr,
                    $obr,
                    $jev,
                    $remarks,
                    $sourceId
                );
            } else {
                mysqli_stmt_bind_param(
                    $assetStmt,
                    'ssssdssssssi',
                    $brandModel,
                    $description,
                    $chassisNo,
                    $engineNo,
                    $amount,
                    $dateAcquired,
                    $supplier,
                    $po,
                    $pr,
                    $obr,
                    $jev,
                    $sourceId
                );
            }
            if (!mysqli_stmt_execute($assetStmt)) {
                $assetError = mysqli_stmt_error($assetStmt);
                mysqli_stmt_close($assetStmt);
                throw new Exception($assetError !== '' ? $assetError : 'Unable to update asset details.');
            }
            mysqli_stmt_close($assetStmt);

            $vehicleId = 0;
            $findStmt = mysqli_prepare($conn, 'SELECT motor_vehicle_id FROM motor_vehicle WHERE property_number = ? LIMIT 1');
            if (!$findStmt) { throw new Exception('Unable to prepare vehicle lookup.'); }
            mysqli_stmt_bind_param($findStmt, 's', $propertyNumber);
            mysqli_stmt_execute($findStmt);
            $findRes = mysqli_stmt_get_result($findStmt);
            if ($findRes && ($found = mysqli_fetch_assoc($findRes))) {
                $vehicleId = (int)($found['motor_vehicle_id'] ?? 0);
            }
            mysqli_stmt_close($findStmt);

            if ($vehicleId > 0) {
                $vehicleSetParts = [];
                $vehicleTypes = '';
                $vehicleParams = [];

                if ($vehicleClassificationColumn !== '') {
                    $vehicleSetParts[] = "{$vehicleClassificationColumn} = ?";
                    $vehicleTypes .= 's';
                    $vehicleParams[] = $vehicleClassification;
                }

                $vehicleSetParts[] = "{$vehicleDateColumn} = ?";
                $vehicleSetParts[] = "chassis_no = ?";
                $vehicleSetParts[] = "engine_no = ?";
                $vehicleSetParts[] = "plate_no = ?";
                $vehicleSetParts[] = "color = ?";
                $vehicleSetParts[] = "mv_file = ?";
                $vehicleTypes .= 'ssssss';
                array_push($vehicleParams, $dateAcquired, $chassisNo, $engineNo, $plateNo, $color, $mvFile);

                if ($hasConductionSticker) {
                    $vehicleSetParts[] = "conduction_sticker = ?";
                    $vehicleTypes .= 's';
                    $vehicleParams[] = $conductionSticker;
                }

                $vehicleSetParts[] = "vehicle_usage = ?";
                $vehicleSetParts[] = "capacity = ?";
                $vehicleSetParts[] = "year_model = ?";
                $vehicleSetParts[] = "cr_number = ?";
                $vehicleSetParts[] = "or_number = ?";
                $vehicleSetParts[] = "coverage = ?";
                $vehicleTypes .= 'ssisss';
                array_push($vehicleParams, $vehicleUsage, $capacity, $yearModel, $crNumber, $orNumber, $coverage);

                $vehicleTypes .= 'i';
                $vehicleParams[] = $vehicleId;
                $vehicleSql = "
                    UPDATE motor_vehicle
                    SET " . implode(",\n                        ", $vehicleSetParts) . "
                    WHERE motor_vehicle_id = ?
                    LIMIT 1
                ";
                $vehicleStmt = mysqli_prepare($conn, $vehicleSql);
                if (!$vehicleStmt) { throw new Exception('Unable to prepare vehicle update.'); }
                gso_stmt_bind_params($vehicleStmt, $vehicleTypes, $vehicleParams);
            } else {
                $vehicleColumns = [];
                $vehicleTypes = '';
                $vehicleParams = [];

                if ($vehicleClassificationColumn !== '') {
                    $vehicleColumns[] = $vehicleClassificationColumn;
                    $vehicleTypes .= 's';
                    $vehicleParams[] = $vehicleClassification;
                }

                $vehicleColumns[] = 'property_number';
                $vehicleColumns[] = $vehicleDateColumn;
                $vehicleColumns[] = 'chassis_no';
                $vehicleColumns[] = 'engine_no';
                $vehicleColumns[] = 'plate_no';
                $vehicleColumns[] = 'color';
                $vehicleColumns[] = 'mv_file';
                $vehicleTypes .= 'sssssss';
                array_push($vehicleParams, $propertyNumber, $dateAcquired, $chassisNo, $engineNo, $plateNo, $color, $mvFile);

                if ($hasConductionSticker) {
                    $vehicleColumns[] = 'conduction_sticker';
                    $vehicleTypes .= 's';
                    $vehicleParams[] = $conductionSticker;
                }

                $vehicleColumns[] = 'vehicle_usage';
                $vehicleColumns[] = 'capacity';
                $vehicleColumns[] = 'year_model';
                $vehicleColumns[] = 'cr_number';
                $vehicleColumns[] = 'or_number';
                $vehicleColumns[] = 'coverage';
                $vehicleTypes .= 'ssisss';
                array_push($vehicleParams, $vehicleUsage, $capacity, $yearModel, $crNumber, $orNumber, $coverage);

                $placeholders = implode(',', array_fill(0, count($vehicleColumns), '?'));
                $vehicleSql = "
                    INSERT INTO motor_vehicle (
                        " . implode(",\n                        ", $vehicleColumns) . "
                    ) VALUES ({$placeholders})
                ";
                $vehicleStmt = mysqli_prepare($conn, $vehicleSql);
                if (!$vehicleStmt) { throw new Exception('Unable to prepare vehicle registration.'); }
                gso_stmt_bind_params($vehicleStmt, $vehicleTypes, $vehicleParams);
            }

            if (!mysqli_stmt_execute($vehicleStmt)) {
                $errNo = mysqli_stmt_errno($vehicleStmt);
                $err = mysqli_stmt_error($vehicleStmt);
                mysqli_stmt_close($vehicleStmt);
                if ($errNo === 1062) {
                    throw new Exception('Chassis number, engine number, or plate number already exists on another vehicle.', 1062);
                }
                throw new Exception($err !== '' ? $err : 'Unable to save vehicle details.');
            }
            mysqli_stmt_close($vehicleStmt);

            $adminId = (string)($_SESSION['alogin'] ?? '');
            $ipAddress = function_exists('getUserIpAddr') ? getUserIpAddr() : '';
            gso_log_activity($conn, $adminId, $ipAddress, 'Updated motor vehicle details for ' . $propertyNumber);

            mysqli_commit($conn);
            echo json_encode(['status' => 200, 'message' => 'Motor vehicle updated successfully.']);
            exit();
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $status = ((int)$e->getCode() === 1062) ? 422 : 500;
            echo json_encode(['status' => $status, 'message' => $e->getMessage()]);
            exit();
        }
    }

    echo json_encode(['status' => 422, 'message' => 'Invalid dashboard request']);
    exit();
}

// ==========================================================
// Exports: General Fund Inventory (PAR / ICS)
// Output: Excel-readable .xls (HTML table)
// Note: Keep export logic here; UI JS lives in assets/dist/js/script.js
// ==========================================================
if (isset($_GET['export_gf_inventory'])) {
    if (!isset($_SESSION['alogin']) || trim((string)$_SESSION['alogin']) === '') {
        header('Location:../index.php');
        exit();
    }

    $category = strtoupper(trim((string)($_GET['category'] ?? '')));
    if (!in_array($category, ['PAR', 'ICS', 'ALL'], true)) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "Invalid category.";
        exit();
    }

    $dept = trim((string)($_GET['dept'] ?? ''));
    if ($dept !== '' && !preg_match('/^\d+$/', $dept)) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "Invalid department.";
        exit();
    }

    if (!function_exists('gso_excel_safe_cell')) {
        function gso_excel_safe_cell($value) {
            $s = (string)($value ?? '');
            $s = preg_replace("/\r\n|\r|\n/", ' ', $s);
            $s = trim($s);
            // Mitigate Excel formula injection
            if ($s !== '' && preg_match('/^[=+\-@]/', $s)) {
                $s = "'" . $s;
            }
            return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
    }

    $reportTitle = ($category === 'ALL')
        ? 'General Fund - Inventory Report (PAR & ICS)'
        : "General Fund - Inventory Report ({$category})";
    $todayIso = date('Y-m-d');
    $filename = "General_Fund_Inventory_Report_{$category}_{$todayIso}.xls";

    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $deptPk = 0;
    if ($dept !== '') {
        $deptRow = gso_find_department_by_code($conn, $dept);
        $deptPk = isset($deptRow['dept_id']) ? (int)$deptRow['dept_id'] : 0;
    }

    $sql = "SELECT
                p.item AS asset_class,
                p.model AS brand_model,
                p.description,
                p.serial_number,
                p.serial_number_2,
                p.par_number AS property_number,
                p.unit_value,
                p.date_aquired AS year_acquired,
                p.account_code,
                p.supplier,
                e.emp_name AS end_user,
                d.department_name
            FROM general_fund_property_history AS g
            INNER JOIN par_gen_fund AS p ON g.par_number = p.par_number
            INNER JOIN employee AS e ON g.emp_id = e.emp_id
            INNER JOIN department AS d ON e.department_code = d.department_code
            WHERE g.status = 1";

    if ($category === 'ALL') {
        $sql .= " AND UPPER(TRIM(p.category)) IN ('PAR','ICS') ";
    } else {
        $sql .= " AND UPPER(TRIM(p.category)) = ? ";
    }

    if ($dept !== '') {
        $deptNum = (int)$dept;
        $sql .= " AND e.department_code = $deptNum";
        if ($deptPk > 0 && $deptPk !== $deptNum) {
            $sql .= " AND g.dept_id IN ($deptNum, $deptPk)";
        } else {
            $sql .= " AND g.dept_id = $deptNum";
        }
    }

    // Sorting: if a specific department is selected, sort by year/date acquired first.
    if ($dept !== '') {
        $sql .= " ORDER BY p.date_aquired ASC, e.emp_name ASC, p.item ASC, p.par_number ASC";
    } else {
        $sql .= " ORDER BY d.department_name ASC, e.emp_name ASC, p.item ASC, p.par_number ASC";
    }

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Export error: unable to prepare query.';
        exit();
    }

    if ($category !== 'ALL') {
        mysqli_stmt_bind_param($stmt, 's', $category);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    echo "<html><head><meta charset=\"utf-8\"></head><body>";
    echo "<table border=\"1\" cellspacing=\"0\" cellpadding=\"4\">";
    echo "<tr><th colspan=\"12\" style=\"font-size:16px;\">" . gso_excel_safe_cell($reportTitle) . "</th></tr>";
    echo "<tr>";
    echo "<th>Asset Class</th>";
    echo "<th>Brand/Model</th>";
    echo "<th>Description</th>";
    echo "<th>Serial Number 1</th>";
    echo "<th>Serial Number 2</th>";
    echo "<th>Property Number</th>";
    echo "<th>Unit Value</th>";
    echo "<th>Year Acquired</th>";
    echo "<th>Account Code</th>";
    echo "<th>Supplier</th>";
    echo "<th>End User</th>";
    echo "<th>Department Name</th>";
    echo "</tr>";

    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            echo "<tr>";
            echo "<td>" . gso_excel_safe_cell($row['asset_class'] ?? '') . "</td>";
            echo "<td>" . gso_excel_safe_cell($row['brand_model'] ?? '') . "</td>";
            echo "<td>" . gso_excel_safe_cell($row['description'] ?? '') . "</td>";
            echo "<td>" . gso_excel_safe_cell($row['serial_number'] ?? '') . "</td>";
            echo "<td>" . gso_excel_safe_cell($row['serial_number_2'] ?? '') . "</td>";
            echo "<td>" . gso_excel_safe_cell($row['property_number'] ?? '') . "</td>";
            echo "<td>" . gso_excel_safe_cell($row['unit_value'] ?? '') . "</td>";
            echo "<td>" . gso_excel_safe_cell($row['year_acquired'] ?? '') . "</td>";
            echo "<td>" . gso_excel_safe_cell($row['account_code'] ?? '') . "</td>";
            echo "<td>" . gso_excel_safe_cell($row['supplier'] ?? '') . "</td>";
            echo "<td>" . gso_excel_safe_cell($row['end_user'] ?? '') . "</td>";
            echo "<td>" . gso_excel_safe_cell($row['department_name'] ?? '') . "</td>";
            echo "</tr>";
        }
    }

    echo "</table></body></html>";
    mysqli_stmt_close($stmt);
    exit();
}

// ==========================================================
// Print: General Fund Inventory (PAR / ICS / ALL)
// Output: Print-friendly HTML page (8.5 x 13 landscape)
// ==========================================================
if (isset($_GET['print_gf_inventory'])) {
    if (!isset($_SESSION['alogin']) || trim((string)$_SESSION['alogin']) === '') {
        header('Location:../index.php');
        exit();
    }

    $category = strtoupper(trim((string)($_GET['category'] ?? '')));
    if (!in_array($category, ['PAR', 'ICS', 'ALL'], true)) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "Invalid category.";
        exit();
    }

    $dept = trim((string)($_GET['dept'] ?? ''));
    if ($dept !== '' && !preg_match('/^\d+$/', $dept)) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "Invalid department.";
        exit();
    }

    if (!function_exists('gso_excel_safe_cell')) {
        function gso_excel_safe_cell($value) {
            $s = (string)($value ?? '');
            $s = preg_replace("/\r\n|\r|\n/", ' ', $s);
            $s = trim($s);
            if ($s !== '' && preg_match('/^[=+\-@]/', $s)) {
                $s = "'" . $s;
            }
            return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
    }

    $reportTitle = ($category === 'ALL')
        ? 'General Fund - Inventory Report (PAR & ICS)'
        : "General Fund - Inventory Report ({$category})";

    $deptPk = 0;
    if ($dept !== '') {
        $deptRow = gso_find_department_by_code($conn, $dept);
        $deptPk = isset($deptRow['dept_id']) ? (int)$deptRow['dept_id'] : 0;
    }

    $sql = "SELECT
                p.item AS asset_class,
                p.model AS brand_model,
                p.description,
                p.serial_number,
                p.serial_number_2,
                p.par_number AS property_number,
                p.unit_value,
                p.date_aquired AS year_acquired,
                p.account_code,
                p.supplier,
                e.emp_name AS end_user,
                d.department_name
            FROM general_fund_property_history AS g
            INNER JOIN par_gen_fund AS p ON g.par_number = p.par_number
            INNER JOIN employee AS e ON g.emp_id = e.emp_id
            INNER JOIN department AS d ON e.department_code = d.department_code
            WHERE g.status = 1";

    if ($category === 'ALL') {
        $sql .= " AND UPPER(TRIM(p.category)) IN ('PAR','ICS') ";
    } else {
        $sql .= " AND UPPER(TRIM(p.category)) = ? ";
    }

    if ($dept !== '') {
        $deptNum = (int)$dept;
        $sql .= " AND e.department_code = $deptNum";
        if ($deptPk > 0 && $deptPk !== $deptNum) {
            $sql .= " AND g.dept_id IN ($deptNum, $deptPk)";
        } else {
            $sql .= " AND g.dept_id = $deptNum";
        }
    }

    // Sorting: if a specific department is selected, sort by year/date acquired first.
    if ($dept !== '') {
        $sql .= " ORDER BY p.date_aquired ASC, e.emp_name ASC, p.item ASC, p.par_number ASC";
    } else {
        $sql .= " ORDER BY d.department_name ASC, e.emp_name ASC, p.item ASC, p.par_number ASC";
    }

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Print error: unable to prepare query.';
        exit();
    }

    if ($category !== 'ALL') {
        mysqli_stmt_bind_param($stmt, 's', $category);
    }

    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    header('Content-Type: text/html; charset=utf-8');

    echo "<!doctype html>";
    echo "<html><head><meta charset=\"utf-8\">";
    echo "<title>" . gso_excel_safe_cell($reportTitle) . "</title>";
    echo "<style>\n";
    echo "@page { size: 8.5in 13in landscape; margin: 0.5in; }\n";
    echo "body { font-family: Arial, Helvetica, sans-serif; color: #000; }\n";
    echo "h1 { font-size: 16px; margin: 0 0 10px; }\n";
    echo "table { width: 100%; border-collapse: collapse; font-size: 11px; }\n";
    echo "th, td { border: 1px solid #000; padding: 4px 6px; vertical-align: top; }\n";
    echo "th { text-align: left; }\n";
    echo "@media print { .no-print { display: none; } }\n";
    echo "</style></head><body>";
    echo "<div class=\"no-print\" style=\"margin-bottom:10px;\">";
    echo "<button onclick=\"window.print()\">Print</button>";
    echo "</div>";
    echo "<h1>" . gso_excel_safe_cell($reportTitle) . "</h1>";
    echo "<table>";
    echo "<tr>";
    echo "<th>Asset Class</th>";
    echo "<th>Brand/Model</th>";
    echo "<th>Description</th>";
    echo "<th>Serial Number 1</th>";
    echo "<th>Serial Number 2</th>";
    echo "<th>Property Number</th>";
    echo "<th>Unit Value</th>";
    echo "<th>Year Acquired</th>";
    echo "<th>Account Code</th>";
    echo "<th>Supplier</th>";
    echo "<th>End User</th>";
    echo "<th>Department Name</th>";
    echo "</tr>";

    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            echo "<tr>";
            echo "<td>" . gso_excel_safe_cell($row['asset_class'] ?? '') . "</td>";
            echo "<td>" . gso_excel_safe_cell($row['brand_model'] ?? '') . "</td>";
            echo "<td>" . gso_excel_safe_cell($row['description'] ?? '') . "</td>";
            echo "<td>" . gso_excel_safe_cell($row['serial_number'] ?? '') . "</td>";
            echo "<td>" . gso_excel_safe_cell($row['serial_number_2'] ?? '') . "</td>";
            echo "<td>" . gso_excel_safe_cell($row['property_number'] ?? '') . "</td>";
            echo "<td>" . gso_excel_safe_cell($row['unit_value'] ?? '') . "</td>";
            echo "<td>" . gso_excel_safe_cell($row['year_acquired'] ?? '') . "</td>";
            echo "<td>" . gso_excel_safe_cell($row['account_code'] ?? '') . "</td>";
            echo "<td>" . gso_excel_safe_cell($row['supplier'] ?? '') . "</td>";
            echo "<td>" . gso_excel_safe_cell($row['end_user'] ?? '') . "</td>";
            echo "<td>" . gso_excel_safe_cell($row['department_name'] ?? '') . "</td>";
            echo "</tr>";
        }
    }

    echo "</table>";
    echo "<script>\n";
    echo "window.addEventListener('load', function(){ try { window.print(); } catch(e){} });\n";
    echo "</script>";
    echo "</body></html>";

    mysqli_stmt_close($stmt);
    exit();
}

// ==========================================================
// Exports: SEF Inventory (PAR / ICS / ALL)
// Output: Excel-readable .xls (HTML table)
// ==========================================================
if (isset($_GET['export_sef_inventory'])) {
    if (!isset($_SESSION['alogin']) || trim((string)$_SESSION['alogin']) === '') {
        header('Location:../index.php');
        exit();
    }

    $category = strtoupper(trim((string)($_GET['category'] ?? '')));
    if (!in_array($category, ['PAR', 'ICS', 'ALL'], true)) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "Invalid category.";
        exit();
    }

    $dept = trim((string)($_GET['dept'] ?? ''));
    if ($dept !== '' && !preg_match('/^\d+$/', $dept)) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "Invalid institution.";
        exit();
    }

    if (!function_exists('gso_excel_safe_cell')) {
        function gso_excel_safe_cell($value) {
            $s = (string)($value ?? '');
            $s = preg_replace("/\r\n|\r|\n/", ' ', $s);
            $s = trim($s);
            if ($s !== '' && preg_match('/^[=+\-@]/', $s)) {
                $s = "'" . $s;
            }
            return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
    }

    $reportTitle = ($category === 'ALL')
        ? 'Special Education Fund - Inventory Report (PAR & ICS)'
        : "Special Education Fund - Inventory Report ({$category})";

    $todayIso = date('Y-m-d');
    $filename = "SEF_Inventory_Report_{$category}_{$todayIso}.xls";

    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $sql = "SELECT
                s.item AS asset_class,
                s.model AS brand_model,
                s.description,
                s.serial_number,
                s.serial_number_2,
                s.property_number AS property_number,
                s.unit_value,
                s.date_aquired AS year_acquired,
                s.account_code,
                s.supplier,
                e.emp_name AS end_user,
                d.department_name
            FROM sef_property_history AS sh
            INNER JOIN property_sef AS s ON sh.property_number = s.property_number
            INNER JOIN employee AS e ON sh.emp_id = e.emp_id
            INNER JOIN department AS d ON sh.sch_id = d.department_code
            WHERE sh.status = 1
            ";

    if ($category === 'ALL') {
        $sql .= " AND UPPER(TRIM(s.category)) IN ('PAR','ICS') ";
    } else {
        $sql .= " AND UPPER(TRIM(s.category)) = ? ";
    }

    if ($dept !== '') {
        $sql .= " AND sh.sch_id = ?";
    }

    // Sorting: if a specific institution is selected, sort by year/date acquired first.
    if ($dept !== '') {
        $sql .= " ORDER BY s.date_aquired ASC, e.emp_name ASC, s.item ASC, s.property_number ASC";
    } else {
        $sql .= " ORDER BY d.department_name ASC, e.emp_name ASC, s.item ASC, s.property_number ASC";
    }

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Export error: unable to prepare query.';
        exit();
    }

    if ($category === 'ALL') {
        if ($dept !== '') {
            mysqli_stmt_bind_param($stmt, 's', $dept);
        }
    } else {
        if ($dept !== '') {
            mysqli_stmt_bind_param($stmt, 'ss', $category, $dept);
        } else {
            mysqli_stmt_bind_param($stmt, 's', $category);
        }
    }

    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    echo "<html><head><meta charset=\"utf-8\"></head><body>";
    echo "<table border=\"1\" cellspacing=\"0\" cellpadding=\"4\">";
    echo "<tr><th colspan=\"12\" style=\"font-size:16px;\">" . gso_excel_safe_cell($reportTitle) . "</th></tr>";
    echo "<tr>";
    echo "<th>Asset Class</th>";
    echo "<th>Brand/Model</th>";
    echo "<th>Description</th>";
    echo "<th>Serial Number 1</th>";
    echo "<th>Serial Number 2</th>";
    echo "<th>Property Number</th>";
    echo "<th>Unit Value</th>";
    echo "<th>Year Acquired</th>";
    echo "<th>Account Code</th>";
    echo "<th>Supplier</th>";
    echo "<th>End User</th>";
    echo "<th>Institution</th>";
    echo "</tr>";

    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            echo "<tr>";
            echo "<td>" . gso_excel_safe_cell($row['asset_class'] ?? '') . "</td>";
            echo "<td>" . gso_excel_safe_cell($row['brand_model'] ?? '') . "</td>";
            echo "<td>" . gso_excel_safe_cell($row['description'] ?? '') . "</td>";
            echo "<td>" . gso_excel_safe_cell($row['serial_number'] ?? '') . "</td>";
            echo "<td>" . gso_excel_safe_cell($row['serial_number_2'] ?? '') . "</td>";
            echo "<td>" . gso_excel_safe_cell($row['property_number'] ?? '') . "</td>";
            echo "<td>" . gso_excel_safe_cell($row['unit_value'] ?? '') . "</td>";
            echo "<td>" . gso_excel_safe_cell($row['year_acquired'] ?? '') . "</td>";
            echo "<td>" . gso_excel_safe_cell($row['account_code'] ?? '') . "</td>";
            echo "<td>" . gso_excel_safe_cell($row['supplier'] ?? '') . "</td>";
            echo "<td>" . gso_excel_safe_cell($row['end_user'] ?? '') . "</td>";
            echo "<td>" . gso_excel_safe_cell($row['department_name'] ?? '') . "</td>";
            echo "</tr>";
        }
    }

    echo "</table></body></html>";
    mysqli_stmt_close($stmt);
    exit();
}

// ==========================================================
// Print: SEF Inventory (PAR / ICS / ALL)
// Output: Print-friendly HTML page (8.5 x 13 landscape)
// ==========================================================
if (isset($_GET['print_sef_inventory'])) {
    if (!isset($_SESSION['alogin']) || trim((string)$_SESSION['alogin']) === '') {
        header('Location:../index.php');
        exit();
    }

    $category = strtoupper(trim((string)($_GET['category'] ?? '')));
    if (!in_array($category, ['PAR', 'ICS', 'ALL'], true)) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "Invalid category.";
        exit();
    }

    $dept = trim((string)($_GET['dept'] ?? ''));
    if ($dept !== '' && !preg_match('/^\d+$/', $dept)) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "Invalid institution.";
        exit();
    }

    if (!function_exists('gso_excel_safe_cell')) {
        function gso_excel_safe_cell($value) {
            $s = (string)($value ?? '');
            $s = preg_replace("/\r\n|\r|\n/", ' ', $s);
            $s = trim($s);
            if ($s !== '' && preg_match('/^[=+\-@]/', $s)) {
                $s = "'" . $s;
            }
            return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
    }

    $reportTitle = ($category === 'ALL')
        ? 'Special Education Fund - Inventory Report (PAR & ICS)'
        : "Special Education Fund - Inventory Report ({$category})";

    $sql = "SELECT
                s.item AS asset_class,
                s.model AS brand_model,
                s.description,
                s.serial_number,
                s.serial_number_2,
                s.property_number AS property_number,
                s.unit_value,
                s.date_aquired AS year_acquired,
                s.account_code,
                s.supplier,
                e.emp_name AS end_user,
                d.department_name
            FROM sef_property_history AS sh
            INNER JOIN property_sef AS s ON sh.property_number = s.property_number
            INNER JOIN employee AS e ON sh.emp_id = e.emp_id
            INNER JOIN department AS d ON sh.sch_id = d.department_code
            WHERE sh.status = 1
            ";

    if ($category === 'ALL') {
        $sql .= " AND UPPER(TRIM(s.category)) IN ('PAR','ICS') ";
    } else {
        $sql .= " AND UPPER(TRIM(s.category)) = ? ";
    }

    if ($dept !== '') {
        $sql .= " AND sh.sch_id = ?";
    }

    // Sorting: if a specific institution is selected, sort by year/date acquired first.
    if ($dept !== '') {
        $sql .= " ORDER BY s.date_aquired ASC, e.emp_name ASC, s.item ASC, s.property_number ASC";
    } else {
        $sql .= " ORDER BY d.department_name ASC, e.emp_name ASC, s.item ASC, s.property_number ASC";
    }

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Print error: unable to prepare query.';
        exit();
    }

    if ($category === 'ALL') {
        if ($dept !== '') {
            mysqli_stmt_bind_param($stmt, 's', $dept);
        }
    } else {
        if ($dept !== '') {
            mysqli_stmt_bind_param($stmt, 'ss', $category, $dept);
        } else {
            mysqli_stmt_bind_param($stmt, 's', $category);
        }
    }

    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    header('Content-Type: text/html; charset=utf-8');

    echo "<!doctype html>";
    echo "<html><head><meta charset=\"utf-8\">";
    echo "<title>" . gso_excel_safe_cell($reportTitle) . "</title>";
    echo "<style>\n";
    echo "@page { size: 8.5in 13in landscape; margin: 0.5in; }\n";
    echo "body { font-family: Arial, Helvetica, sans-serif; color: #000; }\n";
    echo "h1 { font-size: 16px; margin: 0 0 10px; }\n";
    echo "table { width: 100%; border-collapse: collapse; font-size: 11px; }\n";
    echo "th, td { border: 1px solid #000; padding: 4px 6px; vertical-align: top; }\n";
    echo "th { text-align: left; }\n";
    echo "@media print { .no-print { display: none; } }\n";
    echo "</style></head><body>";
    echo "<div class=\"no-print\" style=\"margin-bottom:10px;\">";
    echo "<button onclick=\"window.print()\">Print</button>";
    echo "</div>";
    echo "<h1>" . gso_excel_safe_cell($reportTitle) . "</h1>";
    echo "<table>";
    echo "<tr>";
    echo "<th>Asset Class</th>";
    echo "<th>Brand/Model</th>";
    echo "<th>Description</th>";
    echo "<th>Serial Number 1</th>";
    echo "<th>Serial Number 2</th>";
    echo "<th>Property Number</th>";
    echo "<th>Unit Value</th>";
    echo "<th>Year Acquired</th>";
    echo "<th>Account Code</th>";
    echo "<th>Supplier</th>";
    echo "<th>End User</th>";
    echo "<th>Institution</th>";
    echo "</tr>";

    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            echo "<tr>";
            echo "<td>" . gso_excel_safe_cell($row['asset_class'] ?? '') . "</td>";
            echo "<td>" . gso_excel_safe_cell($row['brand_model'] ?? '') . "</td>";
            echo "<td>" . gso_excel_safe_cell($row['description'] ?? '') . "</td>";
            echo "<td>" . gso_excel_safe_cell($row['serial_number'] ?? '') . "</td>";
            echo "<td>" . gso_excel_safe_cell($row['serial_number_2'] ?? '') . "</td>";
            echo "<td>" . gso_excel_safe_cell($row['property_number'] ?? '') . "</td>";
            echo "<td>" . gso_excel_safe_cell($row['unit_value'] ?? '') . "</td>";
            echo "<td>" . gso_excel_safe_cell($row['year_acquired'] ?? '') . "</td>";
            echo "<td>" . gso_excel_safe_cell($row['account_code'] ?? '') . "</td>";
            echo "<td>" . gso_excel_safe_cell($row['supplier'] ?? '') . "</td>";
            echo "<td>" . gso_excel_safe_cell($row['end_user'] ?? '') . "</td>";
            echo "<td>" . gso_excel_safe_cell($row['department_name'] ?? '') . "</td>";
            echo "</tr>";
        }
    }

    echo "</table>";
    echo "<script>\n";
    echo "window.addEventListener('load', function(){ try { window.print(); } catch(e){} });\n";
    echo "</script>";
    echo "</body></html>";

    mysqli_stmt_close($stmt);
    exit();
}

// emp_id allocation (concurrency-safe)
if(!function_exists('gso_emp_id_lock_acquire')){
    function gso_emp_id_lock_acquire($conn, $timeoutSeconds = 10){
        $lockName = 'gso_employee_emp_id';
        $timeoutSeconds = (int)$timeoutSeconds;
        if ($timeoutSeconds < 0) { $timeoutSeconds = 0; }
        $lockEsc = mysqli_real_escape_string($conn, $lockName);
        $q = mysqli_query($conn, "SELECT GET_LOCK('{$lockEsc}', {$timeoutSeconds}) AS got");
        if(!$q){ return false; }
        $row = mysqli_fetch_assoc($q);
        return isset($row['got']) && (int)$row['got'] === 1;
    }
}
if(!function_exists('gso_emp_id_lock_release')){
    function gso_emp_id_lock_release($conn){
        $lockName = 'gso_employee_emp_id';
        $lockEsc = mysqli_real_escape_string($conn, $lockName);
        @mysqli_query($conn, "SELECT RELEASE_LOCK('{$lockEsc}')");
    }
}
if(!function_exists('gso_emp_id_next_candidate')){
    function gso_emp_id_next_candidate($conn){
        $q = mysqli_query($conn, "SELECT COALESCE(MAX(emp_id), 0) AS max_id FROM employee");
        if(!$q){ return 1; }
        $row = mysqli_fetch_assoc($q);
        $max = isset($row['max_id']) && $row['max_id'] !== null ? (int)$row['max_id'] : 0;
        return $max + 1;
    }
}
if(!function_exists('gso_create_employee_atomic')){
    // Creates an employee row with a freshly allocated emp_id (safe under concurrent inserts).
    // Returns ['ok'=>true,'emp_id'=>(int)] on success, or ['ok'=>false,'message'=>string] on failure.
    function gso_create_employee_atomic($conn, $empName, $departmentCode, $position, $empStatus = 1, $propertyCustodian = null){
        $empName = strtoupper(trim((string)$empName));
        $position = strtoupper(trim((string)$position));
        if($position === ''){ $position = 'N/A'; }
        $departmentCode = trim((string)$departmentCode);
        $empStatus = (int)$empStatus;

        if($empName === '' || $departmentCode === ''){
            return ['ok'=>false,'message'=>'Missing employee name/department.'];
        }

        if(!gso_emp_id_lock_acquire($conn, 10)){
            return ['ok'=>false,'message'=>'System is busy. Please try again.'];
        }

        try {
            $tries = 0;
            while($tries < 30){
                $tries++;
                $empId = gso_emp_id_next_candidate($conn);

                $empIdEsc = mysqli_real_escape_string($conn, (string)$empId);
                $nameEsc = mysqli_real_escape_string($conn, $empName);
                $deptEsc = mysqli_real_escape_string($conn, $departmentCode);
                $posEsc = mysqli_real_escape_string($conn, $position);
                $agencies = employee_agency_from_department_code($departmentCode);
                $agEsc = mysqli_real_escape_string($conn, (string)$agencies);

                $cols = "emp_id, emp_name, agencies, department_code, position, emp_status";
                $vals = "'{$empIdEsc}', '{$nameEsc}', '{$agEsc}', '{$deptEsc}', '{$posEsc}', '{$empStatus}'";
                if($propertyCustodian !== null){
                    $pc = (int)$propertyCustodian;
                    $cols .= ", property_custodian";
                    $vals .= ", '{$pc}'";
                }

                $sql = "INSERT INTO employee ({$cols}) VALUES ({$vals})";
                if(mysqli_query($conn, $sql)){
                    return ['ok'=>true,'emp_id'=>$empId];
                }

                // 1062 = duplicate key; regenerate and retry (covers any legacy writers that didn't use the lock)
                if((int)mysqli_errno($conn) === 1062){
                    continue;
                }

                return ['ok'=>false,'message'=>'Error creating employee: '.mysqli_error($conn)];
            }
            return ['ok'=>false,'message'=>'Unable to allocate employee number. Please retry.'];
        } finally {
            gso_emp_id_lock_release($conn);
        }
    }
}

// Lightweight preview for UI display only (NOT an allocation)
if (isset($_GET['next_emp_id'])) {
    $next = gso_emp_id_next_candidate($conn);
    return json_response(200, 'OK', ['next_emp_id'=>$next]);
}

// Employee agencies rule helper
// Rule: if department_code is 400-499 => agencies = 2, otherwise agencies = 1.
if(!function_exists('employee_agency_from_department_code')){
    function employee_agency_from_department_code($departmentCode){
        $raw = trim((string)$departmentCode);
        if($raw===''){ return 1; }

        // Extract numeric portion (supports numeric strings; ignores non-numeric prefixes/suffixes defensively).
        $num = null;
        if(preg_match('/\d+/', $raw, $m)){
            $num = (int)$m[0];
        }
        if($num !== null && $num >= 400 && $num <= 499){
            return 2;
        }
        return 1;
    }
}

// Employee status mapping based on selected clearance type
// Rule: RESIGNATION => 2, RETIREMENT => 0, else => 1
if(!function_exists('employee_status_from_clearance_name')){
    function employee_status_from_clearance_name($clearanceName){
        $name = strtoupper(trim((string)$clearanceName));
        if($name === 'RESIGNATION'){ return 2; }
        if($name === 'RETIREMENT'){ return 0; }
        return 1;
    }
}
if(!function_exists('gso_employee_name_exists')){
    function gso_employee_name_exists($conn, $employeeName){
        $employeeName = strtoupper(trim((string)$employeeName));
        if($employeeName === ''){ return false; }

        $nameEsc = mysqli_real_escape_string($conn, $employeeName);
        $query = mysqli_query($conn, "SELECT 1 FROM employee WHERE UPPER(emp_name) = '{$nameEsc}' LIMIT 1");
        return $query && mysqli_num_rows($query) > 0;
    }
}

// Property Clearance helpers
if(!function_exists('pc_reprint_count')){
    function pc_reprint_count($conn, $controlNumber){
        $controlNumber = trim((string)$controlNumber);
        if($controlNumber===''){ return 0; }
        $controlEsc = mysqli_real_escape_string($conn, $controlNumber);
        $likeCtrl = str_replace(['%', '_'], ['\\%', '\\_'], $controlEsc);
        $q = mysqli_query(
            $conn,
            "SELECT COUNT(*) AS cnt
             FROM activity_log
             WHERE activity LIKE 'PROPERTY CLEARANCE REPRINT|CTRL={$likeCtrl}|%' ESCAPE '\\\\'"
        );
        if($q){
            $r = mysqli_fetch_assoc($q);
            return isset($r['cnt']) ? (int)$r['cnt'] : 0;
        }
        return 0;
    }
}

// Readable helpers for resolving display names
if(!function_exists('get_emp_name')){
    function get_emp_name($conn, $emp_id){
        if($emp_id===null || $emp_id===''){ return $emp_id; }
        $id = mysqli_real_escape_string($conn, $emp_id);
        $res = mysqli_query($conn, "SELECT emp_name FROM employee WHERE emp_id='$id' LIMIT 1");
        if($res && mysqli_num_rows($res)===1){ $row=mysqli_fetch_assoc($res); return $row['emp_name']; }
        return $emp_id; // fallback to ID string if not found
    }
}
if(!function_exists('get_dept_name')){
    function get_dept_name($conn, $dept_code){
        if($dept_code===null || $dept_code===''){ return $dept_code; }
        $code = mysqli_real_escape_string($conn, $dept_code);
        $res = mysqli_query($conn, "SELECT department_name FROM department WHERE department_code='$code' LIMIT 1");
        if($res && mysqli_num_rows($res)===1){ $row=mysqli_fetch_assoc($res); return $row['department_name']; }
        return $dept_code; // fallback to code string if not found
    }
}

$today = date("F j, Y");
$status = 0;
$isRead = 0;
$isCancel = 2;
$isPrinted = 1;
$uip = getUserIpAddr();
$uid = $_SESSION['alogin'] ?? '';

// ==========================================================
// Presence (online/offline) - DB backed via last_activity
// ==========================================================
if(!defined('GSO_PRESENCE_TIMEOUT_SECONDS')){
    // Users are considered ONLINE if they were active within this window.
    // Keep this slightly larger than the JS heartbeat interval.
    define('GSO_PRESENCE_TIMEOUT_SECONDS', 90);
}

if(!function_exists('gso_admin_has_last_activity_column')){
    function gso_admin_has_last_activity_column($conn){
        $cacheTtlSeconds = 600;
        if(isset($_SESSION['_gso_has_admin_last_activity'], $_SESSION['_gso_has_admin_last_activity_checked_at'])){
            $age = time() - (int)$_SESSION['_gso_has_admin_last_activity_checked_at'];
            if($age >= 0 && $age < $cacheTtlSeconds){
                return (bool)$_SESSION['_gso_has_admin_last_activity'];
            }
        }
        $q = mysqli_query(
            $conn,
            "SELECT 1
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'administrator'
               AND COLUMN_NAME = 'last_activity'
             LIMIT 1"
        );
        $has = ($q && mysqli_num_rows($q) === 1);
        $_SESSION['_gso_has_admin_last_activity'] = $has ? 1 : 0;
        $_SESSION['_gso_has_admin_last_activity_checked_at'] = time();
        return $has;
    }
}

if(!function_exists('gso_admin_touch_activity')){
    function gso_admin_touch_activity($conn, $adminId, $ipAddress){
        $adminId = trim((string)$adminId);
        if($adminId===''){ return false; }

        // Do not break the app if the column is not yet migrated.
        if(!gso_admin_has_last_activity_column($conn)){
            $stmt = mysqli_prepare($conn, "UPDATE administrator SET status = 1, ip = ? WHERE admin_id = ? LIMIT 1");
            if(!$stmt){ return false; }
            mysqli_stmt_bind_param($stmt, 'ss', $ipAddress, $adminId);
            $ok = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            return (bool)$ok;
        }

        $stmt = mysqli_prepare(
            $conn,
            "UPDATE administrator
             SET status = 1,
                 ip = ?,
                 last_activity = NOW()
             WHERE admin_id = ?
             LIMIT 1"
        );
        if(!$stmt){ return false; }
        mysqli_stmt_bind_param($stmt, 'ss', $ipAddress, $adminId);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return (bool)$ok;
    }
}

// Heartbeat endpoint: called by JS to keep session fresh and update last_activity.
if(isset($_GET['presence_heartbeat'])){
    header('Content-Type: application/json; charset=utf-8');

    if(empty($_SESSION['alogin'])){
        echo json_encode(['status'=>401,'message'=>'Not logged in']);
        exit;
    }

    $_SESSION['start'] = time();

    $adminId = (string)$_SESSION['alogin'];
    $ip = getUserIpAddr();
    $ok = gso_admin_touch_activity($conn, $adminId, $ip);
    if(!$ok){
        echo json_encode(['status'=>500,'message'=>'Failed to update presence']);
        exit;
    }

    echo json_encode(['status'=>200,'message'=>'OK']);
    exit;
}

// Presence feed endpoint for SYSTEM-ADMIN: provides real-time ONLINE/OFFLINE status.
if(isset($_GET['fetch_presence_status'])){
    header('Content-Type: application/json; charset=utf-8');

    $role = isset($_SESSION['role']) ? strtoupper(trim((string)$_SESSION['role'])) : '';
    if(empty($_SESSION['alogin']) || $role !== 'SYSTEM-ADMIN'){
        echo json_encode(['status'=>403,'message'=>'Forbidden']);
        exit;
    }

    $timeoutSeconds = (int)GSO_PRESENCE_TIMEOUT_SECONDS;

    if(!gso_admin_has_last_activity_column($conn)){
        // Fallback: legacy behavior (status column only).
        $res = mysqli_query($conn, "SELECT admin_id, role, status FROM administrator");
        $items = [];
        if($res){
            while($r = mysqli_fetch_assoc($res)){
                $items[] = [
                    'admin_id' => $r['admin_id'],
                    'role' => $r['role'],
                    'is_online' => ((int)$r['status'] === 1) ? 1 : 0
                ];
            }
        }
        echo json_encode(['status'=>200,'message'=>'OK','data'=>['items'=>$items,'timeout_seconds'=>$timeoutSeconds,'has_last_activity'=>0]]);
        exit;
    }

    $sql = "SELECT admin_id,
                   role,
                   status,
                   last_activity,
                   CASE
                     WHEN status = 1 AND last_activity >= DATE_SUB(NOW(), INTERVAL {$timeoutSeconds} SECOND)
                     THEN 1 ELSE 0
                   END AS is_online
            FROM administrator";
    $res = mysqli_query($conn, $sql);
    $items = [];
    if($res){
        while($r = mysqli_fetch_assoc($res)){
            $items[] = [
                'admin_id' => $r['admin_id'],
                'role' => $r['role'],
                'is_online' => (int)$r['is_online']
            ];
        }
    }

    echo json_encode(['status'=>200,'message'=>'OK','data'=>['items'=>$items,'timeout_seconds'=>$timeoutSeconds,'has_last_activity'=>1]]);
    exit;
}

// Print helpers (shared by admin/print-*.php)
if(!function_exists('should_use_elected_as')){
    function should_use_elected_as($employeeName, $position){
        $hay = trim((string)$employeeName . ' ' . (string)$position);
        if($hay===''){ return false; }

        // Match common elected positions/titles whether present in the name or position field.
        // Handles variants like "COUN.", "BRGY.CAPTAIN", "BRGY. CAPTAIN", "SK CHAIRMAN", etc.
        $pattern = '/\b(?:COUN\.?|COUNCILOR|VICE\s*MAYOR|CITY\s*MAYOR|MAYOR|CONGRESSMAN|CONGRESSWOMAN|CHAIRMAN|CHAIRWOMAN|SK\s*CHAIRMAN|SK\s*CHAIRWOMAN|SK\s*KAGAWAD|KAGAWAD|BRGY\.?\s*CAPTAIN|BARANGAY\s*CAPTAIN)\b/i';
        return (bool)preg_match($pattern, $hay);
    }
}

if(!function_exists('should_force_council_secretary_department')){
    function should_force_council_secretary_department($employeeName, $position, $departmentName){
        // Updated rule: focus on POSITION.
        // If the position indicates council/vice mayor/SK chair, the print templates will
        // force department to OFFICE OF THE CITY COUNCIL SECRETARY.
        return forced_council_secretary_position_from_position($position) !== null;
    }
}

if(!function_exists('forced_council_secretary_position_from_position')){
    function forced_council_secretary_position_from_position($position){
        $pos = strtoupper(trim((string)$position));
        if($pos===''){ return null; }
        $pos = preg_replace('/\s+/', ' ', $pos);

        // Normalize common variants
        if(preg_match('/\b(?:CITY\s*)?VICE\s*MAYOR\b/i', $pos)){
            return 'CITY VICE MAYOR';
        }
        // Accept CITY COUNCILOR and common shorthand variants
        if(preg_match('/\b(?:CITY\s*)?COUNCILOR\b/i', $pos) || preg_match('/\bCOUN\.?\b/i', $pos)){
            return 'CITY COUNCILOR';
        }
        if(preg_match('/\bSK\s*PRESIDENT\b/i', $pos)){
            return 'SK PRESIDENT';
        }

        return null;
    }
}

if(!function_exists('forced_council_secretary_position_from_name')){
    function forced_council_secretary_position_from_name($employeeName){
        $name = trim((string)$employeeName);
        if($name===''){ return null; }

        // Priority: VICE MAYOR wins over COUNCILOR/COUN.
        if(preg_match('/\b(?:CITY\s*)?VICE\s*MAYOR\b/i', $name)){
            return 'CITY VICE MAYOR';
        }
        if(preg_match('/\b(?:COUNCILOR|COUN\.?)\b/i', $name)){
            return 'CITY COUNCILOR';
        }

        return null;
    }
}

// City executive position normalization (shared by print templates)
if(!function_exists('normalize_city_executive_position')){
    function normalize_city_executive_position($position){
        $pos = strtoupper(trim((string)$position));
        if($pos===''){ return null; }
        $pos = preg_replace('/\s+/', ' ', $pos);

        if($pos === 'CITY MAYOR'){ return 'CITY MAYOR'; }
        if($pos === 'CITY VICE MAYOR'){ return 'CITY VICE MAYOR'; }
        return null;
    }
}

if(!function_exists('is_gso_officer_in_charge_applicant')){
    function is_gso_officer_in_charge_applicant($departmentName, $position): bool {
        $dept = strtoupper(trim((string)$departmentName));
        $pos = strtoupper(trim((string)$position));
        if($dept==='' || $pos===''){ return false; }

        // Normalize separators and whitespace
        $dept = preg_replace('/\s+/', ' ', $dept);
        $pos = str_replace('-', ' ', $pos);
        $pos = preg_replace('/\s+/', ' ', $pos);

        $isGso = (bool)preg_match('/\bGENERAL\s+SERVICES\s+OFFICE\b|\bGSO\b/i', $dept);
        $isOic = (bool)preg_match('/\bOFFICER\s*IN\s*CHARGE\b|\bOIC\b/i', $pos);
        return $isGso && $isOic;
    }
}

if(!function_exists('fetch_city_administrator_signatory')){
    /**
     * Returns ['name' => string, 'position' => string, 'department_name' => string] or null.
     */
    function fetch_city_administrator_signatory($conn){
        if(!$conn){ return null; }

                $sql = "SELECT e.emp_name AS name, e.position, d.department_name
                    FROM employee AS e
                    JOIN department AS d ON e.department_code = d.department_code
                                WHERE UPPER(e.position) LIKE '%CITY ADMINISTRATOR%'
                                ORDER BY
                                    CASE WHEN UPPER(d.department_name) LIKE '%CITY ADMINISTRATOR%' THEN 0 ELSE 1 END,
                                    e.emp_id ASC
                                LIMIT 1";

        $res = mysqli_query($conn, $sql);
        if(!$res){ return null; }
        $row = mysqli_fetch_assoc($res);
        return $row ?: null;
    }
}

if(!function_exists('fetch_property_clearance_print_data')){
    function fetch_property_clearance_print_data($conn, $controlNumber){
        $controlNumber = trim((string)$controlNumber);
        if($controlNumber===''){ return null; }

        $stmt = mysqli_prepare(
            $conn,
            "SELECT p.emp_id,
                    p.ctype_id,
                    p.dept_id,
                    p.created_at,
                    p.control_number,
                    p.or_number,
                    p.address,
                    p.city,
                    ct.clearance_code,
                    ct.clearance_name,
                    d.department_name,
                    e.emp_name,
                    e.position
             FROM property_clearance AS p
             JOIN clearance_type AS ct ON p.ctype_id = ct.clearance_code
             JOIN department AS d ON p.dept_id = d.department_code
             JOIN employee AS e ON p.emp_id = e.emp_id
             WHERE p.control_number = ?
             LIMIT 1"
        );
        if(!$stmt){ return null; }
        mysqli_stmt_bind_param($stmt, 's', $controlNumber);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = ($res && mysqli_num_rows($res)===1) ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return $row;
    }
}

if(!function_exists('fetch_authorization_clearance_print_data')){
    function fetch_authorization_clearance_print_data($conn, $controlNumber){
        $controlNumber = trim((string)$controlNumber);
        if($controlNumber===''){ return null; }

        $stmt = mysqli_prepare(
            $conn,
            "SELECT ac.location,
                    ac.remarks,
                    ac.emp_id,
                    ac.ctype_id,
                    ac.dept_id,
                    ac.from_date,
                    ac.to_date,
                    ac.created_at,
                    ac.control_number,
                    ac.address,
                    ac.city,
                    ct.clearance_name,
                    d.department_name,
                    e.emp_name,
                    e.position
             FROM authorization_clearance AS ac
             JOIN clearance_type AS ct ON ac.ctype_id = ct.ctype_id
             JOIN department AS d ON ac.dept_id = d.department_code
             JOIN employee AS e ON ac.emp_id = e.emp_id
             WHERE ac.control_number = ?
             LIMIT 1"
        );
        if(!$stmt){ return null; }
        mysqli_stmt_bind_param($stmt, 's', $controlNumber);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = ($res && mysqli_num_rows($res)===1) ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return $row;
    }
}

if(!function_exists('fetch_reference_clearance_print_data')){
    function fetch_reference_clearance_print_data($conn, $controlNumber){
        $controlNumber = trim((string)$controlNumber);
        if($controlNumber===''){ return null; }

        $stmt = mysqli_prepare(
            $conn,
            "SELECT rc.remarks,
                    rc.emp_id,
                    rc.ctype_id,
                    rc.dept_id,
                    rc.created_at,
                    rc.control_number,
                    rc.address,
                    rc.city,
                    ct.clearance_name,
                    d.department_name,
                    e.emp_name,
                    e.position
             FROM reference_clearance AS rc
             JOIN clearance_type AS ct ON rc.ctype_id = ct.ctype_id
             JOIN department AS d ON rc.dept_id = d.department_code
             JOIN employee AS e ON rc.emp_id = e.emp_id
             WHERE rc.control_number = ?
             LIMIT 1"
        );
        if(!$stmt){ return null; }
        mysqli_stmt_bind_param($stmt, 's', $controlNumber);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = ($res && mysqli_num_rows($res)===1) ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return $row;
    }
}

// IIRUP (Appendix 74) print data
if(!function_exists('fetch_iirup_appendix74_print_data')){
    function fetch_iirup_appendix74_print_data($conn, $disposalReference, $accountCode){
        if(!($conn instanceof mysqli)){ return ['meta'=>[], 'items'=>[]]; }

        $disposalReference = trim((string)$disposalReference);
        $accountCode = trim((string)$accountCode);
        if($disposalReference==='' || $accountCode===''){ return ['meta'=>[], 'items'=>[]]; }

        $meta = [
            'entity_name' => 'Local Government of Parañaque',
            'fund_cluster' => $accountCode,
            'as_of' => date('Y'),
            'name_officer' => '',
            'designation' => '',
            'station' => '',
            'requested_by_name' => '',
            'requested_by_position' => '',
            'approved_by_name' => '',
            'approved_by_position' => 'Local Chief Executive',
            'chairperson_name' => '',
            'chairperson_position' => 'Chairperson, Disposal Committee',
            'witness_name' => '',
            'witness_position' => '',
            'inspectors' => []
        ];

        $safeTrim = function($v){ return trim((string)$v); };

        // Meta (iirup_info) – supports legacy misspelling
        $acctCol = null;
        $col = mysqli_query($conn, "SHOW COLUMNS FROM iirup_info LIKE 'acccountable_officer'");
        if($col && mysqli_num_rows($col)===1){ $acctCol = 'acccountable_officer'; }
        if(!$acctCol){
            $col2 = mysqli_query($conn, "SHOW COLUMNS FROM iirup_info LIKE 'accountable_officer'");
            if($col2 && mysqli_num_rows($col2)===1){ $acctCol = 'accountable_officer'; }
        }
        if(!$acctCol){ $acctCol = 'acccountable_officer'; }

        $stmtInfo = mysqli_prepare(
            $conn,
            "SELECT {$acctCol} AS accountable_officer, designation, station, disposal_chairperson, local_chief_executive
             FROM iirup_info
             WHERE disposal_reference = ?
             LIMIT 1"
        );
        if($stmtInfo){
            mysqli_stmt_bind_param($stmtInfo, 's', $disposalReference);
            mysqli_stmt_execute($stmtInfo);
            $res = mysqli_stmt_get_result($stmtInfo);
            if($res && mysqli_num_rows($res)===1){
                $r = mysqli_fetch_assoc($res);
                $meta['name_officer'] = $safeTrim($r['accountable_officer'] ?? '');
                $meta['designation'] = $safeTrim($r['designation'] ?? '');
                $meta['station'] = $safeTrim($r['station'] ?? '');
                $meta['requested_by_name'] = $safeTrim($r['accountable_officer'] ?? '');
                $meta['requested_by_position'] = $safeTrim($r['designation'] ?? '');
                $meta['approved_by_name'] = $safeTrim($r['local_chief_executive'] ?? '');
                $meta['chairperson_name'] = $safeTrim($r['disposal_chairperson'] ?? '');
            }
            mysqli_stmt_close($stmtInfo);
        }

        // Witness
        $stmtW = mysqli_prepare($conn, "SELECT name, position FROM iirup_witness WHERE disposal_reference = ? LIMIT 1");
        if($stmtW){
            mysqli_stmt_bind_param($stmtW, 's', $disposalReference);
            mysqli_stmt_execute($stmtW);
            $res = mysqli_stmt_get_result($stmtW);
            if($res && mysqli_num_rows($res)===1){
                $w = mysqli_fetch_assoc($res);
                $meta['witness_name'] = $safeTrim($w['name'] ?? '');
                $meta['witness_position'] = $safeTrim($w['position'] ?? '');
            }
            mysqli_stmt_close($stmtW);
        }

        // Inspectors
        $pkCol = 'id';
        $pk1 = mysqli_query($conn, "SHOW COLUMNS FROM iirup_inspector LIKE 'iirup_ins_id'");
        if($pk1 && mysqli_num_rows($pk1)===1){ $pkCol = 'iirup_ins_id'; }

        $stmtI = mysqli_prepare($conn, "SELECT name, position FROM iirup_inspector WHERE disposal_reference = ? ORDER BY sort_order ASC, {$pkCol} ASC LIMIT 5");
        if($stmtI){
            mysqli_stmt_bind_param($stmtI, 's', $disposalReference);
            mysqli_stmt_execute($stmtI);
            $res = mysqli_stmt_get_result($stmtI);
            if($res){
                while($i = mysqli_fetch_assoc($res)){
                    $nm = $safeTrim($i['name'] ?? '');
                    $pos = $safeTrim($i['position'] ?? '');
                    if($nm!=='' || $pos!==''){
                        $meta['inspectors'][] = ['name'=>$nm, 'position'=>$pos];
                    }
                }
            }
            mysqli_stmt_close($stmtI);
        }

        // Items
        $items = [];
        $sqlItems = "
            SELECT
                COALESCE(NULLIF(TRIM(u.account_code), ''), '(NO ACCOUNT CODE)') AS account_code,
                COALESCE(u.category, '') AS category,
                COALESCE(u.date_aquired, '') AS date_acquired,
                COALESCE(i.particulars, '') AS particulars,
                COALESCE(i.par_number, '') AS property_no,
                COALESCE(i.qty, 1) AS qty,
                COALESCE(i.unit_cost, 0) AS unit_cost,
                COALESCE(i.appraise_value, 0) AS appraise_value,
                (COALESCE(i.qty, 1) * COALESCE(i.unit_cost, 0)) AS total_cost,
                COALESCE(i.remarks, '') AS remarks,
                COALESCE(NULLIF(TRIM(i.fund), ''), NULLIF(TRIM(u.fund), ''), '') AS fund
            FROM iirup_report_items AS i
            LEFT JOIN unserviceable_items AS u ON u.par_number = i.par_number
            LEFT JOIN (
              SELECT h1.*
              FROM unserviceable_items_history AS h1
              INNER JOIN (
                SELECT par_number, MAX(created_at) AS max_created_at
                FROM unserviceable_items_history
                GROUP BY par_number
              ) AS h2
                ON h1.par_number = h2.par_number
               AND h1.created_at = h2.max_created_at
            ) AS h ON h.par_number = i.par_number
            WHERE COALESCE(h.status, 0) = 1
              AND COALESCE(i.disposal_reference,'') = ?
              AND COALESCE(NULLIF(TRIM(u.account_code), ''), '(NO ACCOUNT CODE)') = ?
            ORDER BY i.par_number ASC
        ";

        $stmtItems = mysqli_prepare($conn, $sqlItems);
        if($stmtItems){
            mysqli_stmt_bind_param($stmtItems, 'ss', $disposalReference, $accountCode);
            mysqli_stmt_execute($stmtItems);
            $res = mysqli_stmt_get_result($stmtItems);
            if($res){
                while($r = mysqli_fetch_assoc($res)){
                    $items[] = [
                        'fund' => $safeTrim($r['fund'] ?? ''),
                        'category' => $safeTrim($r['category'] ?? ''),
                        'date_acquired' => (string)($r['date_acquired'] ?? ''),
                        'particulars' => (string)($r['particulars'] ?? ''),
                        'property_no' => (string)($r['property_no'] ?? ''),
                        'qty' => (string)($r['qty'] ?? '1'),
                        'unit_cost' => (string)($r['unit_cost'] ?? '0'),
                        'appraise_value' => (string)($r['appraise_value'] ?? '0'),
                        'total_cost' => (string)($r['total_cost'] ?? '0'),
                        'remarks' => (string)($r['remarks'] ?? ''),
                    ];
                }
            }
            mysqli_stmt_close($stmtItems);
        }

        return ['meta'=>$meta, 'items'=>$items];
    }
}

if(!function_exists('gso_fetch_property_transfer_report_rows')){
    function gso_fetch_property_transfer_report_rows(mysqli $conn, array $referenceNumbers): array {
        $refs = [];
        foreach ($referenceNumbers as $ref) {
            $ref = trim((string)$ref);
            if ($ref !== '' && !in_array($ref, $refs, true)) {
                $refs[] = $ref;
            }
        }

        if (count($refs) === 0) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($refs), '?'));
        $sql = "
            SELECT
                i.par_number,
                i.ptr_number,
                i.reference_number,
                i.new_dept,
                i.reason,
                i.previous_user,
                i.previous_dept,
                i.new_user,
                i.unit_condition,
                COALESCE(pg.par_number, ps.property_number) AS p_par_number,
                COALESCE(pg.model, ps.model) AS model,
                COALESCE(pg.serial_number, ps.serial_number) AS serial_number,
                COALESCE(pg.serial_number_2, ps.serial_number_2) AS serial_number_2,
                COALESCE(pg.unit_value, ps.unit_value) AS unit_value,
                COALESCE(pg.description, ps.description) AS description,
                COALESCE(pg.date_aquired, ps.date_aquired) AS date_aquired,
                e.emp_id,
                e.emp_name,
                d.department_name,
                d.department_code
            FROM items_user_history AS i
            LEFT JOIN par_gen_fund AS pg ON i.par_number = pg.par_number
            LEFT JOIN property_sef AS ps ON i.par_number = ps.property_number
            JOIN employee AS e ON i.new_user = e.emp_id
            JOIN department AS d ON i.new_dept = d.department_code
            WHERE i.reference_number IN ($placeholders)
            ORDER BY i.reference_number ASC, i.id ASC
        ";

        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return [];
        }

        $types = str_repeat('s', count($refs));
        gso_stmt_bind_params($stmt, $types, $refs);
        mysqli_stmt_execute($stmt);

        $rows = [];
        $result = mysqli_stmt_get_result($stmt);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
        }

        mysqli_stmt_close($stmt);
        return $rows;
    }
}

if(!function_exists('gso_fetch_printpt_rows')){
    function gso_fetch_printpt_rows(mysqli $conn, string $referenceNumber, string $parFilter = '', array $parsFilter = []): array {
        $referenceNumber = trim($referenceNumber);
        if ($referenceNumber === '') {
            return [];
        }

        $pars = [];
        foreach ($parsFilter as $par) {
            $par = trim((string)$par);
            if ($par !== '' && !in_array($par, $pars, true)) {
                $pars[] = $par;
            }
        }
        if (count($pars) > 200) {
            $pars = array_slice($pars, 0, 200);
        }

        $sql = "
            SELECT
                e.emp_name AS user,
                i.previous_user,
                i.previous_dept,
                COALESCE(d_code.department_name, d_id.department_name, i.new_dept) AS department_name,
                COALESCE(pg.model, ps.model) AS model,
                COALESCE(pg.description, ps.description) AS description,
                COALESCE(pg.serial_number, ps.serial_number) AS serial_number,
                COALESCE(pg.serial_number_2, ps.serial_number_2) AS serial_number_2,
                i.par_number,
                COALESCE(pg.date_aquired, ps.date_aquired) AS date_aquired,
                COALESCE(pg.unit_value, ps.unit_value) AS unit_value,
                COALESCE(pg.supplier, ps.supplier) AS supplier,
                COALESCE(pg.purchase_order, ps.purchase_order) AS purchase_order,
                COALESCE(pg.purchase_request, ps.purchase_request) AS purchase_request,
                COALESCE(pg.obr_number, ps.obr_number) AS obr_number,
                COALESCE(pg.account_code, ps.account_code) AS account_code,
                UPPER(COALESCE(pg.category, ps.category)) AS category,
                UPPER(COALESCE(pg.fund, ps.fund)) AS fund
            FROM items_user_history AS i
            LEFT JOIN department AS d_code ON i.new_dept = d_code.department_code
            LEFT JOIN department AS d_id ON CAST(i.new_dept AS UNSIGNED) = d_id.dept_id
            LEFT JOIN par_gen_fund AS pg ON i.par_number = pg.par_number
            LEFT JOIN property_sef AS ps ON i.par_number = ps.property_number
            JOIN employee AS e ON i.new_user = e.emp_id
            WHERE i.reference_number = ?
              AND REPLACE(UPPER(TRIM(COALESCE(pg.category, ps.category))), '.', '') = 'PAR'
        ";

        $types = 's';
        $params = [$referenceNumber];
        if ($parFilter !== '') {
            $sql .= " AND i.par_number = ?";
            $types .= 's';
            $params[] = $parFilter;
        } elseif (count($pars) > 0) {
            $sql .= " AND i.par_number IN (" . implode(',', array_fill(0, count($pars), '?')) . ")";
            $types .= str_repeat('s', count($pars));
            foreach ($pars as $par) {
                $params[] = $par;
            }
        }
        $sql .= " ORDER BY i.id ASC";

        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return [];
        }

        gso_stmt_bind_params($stmt, $types, $params);
        mysqli_stmt_execute($stmt);

        $rows = [];
        $result = mysqli_stmt_get_result($stmt);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
        }

        mysqli_stmt_close($stmt);
        return $rows;
    }
}

// Allow other PHP scripts (e.g., print templates) to include this file as a library without
// running the request/JSON handlers below.
if (defined('GSO_AUTH_LIB_ONLY') && GSO_AUTH_LIB_ONLY) {
    return;
}

// Property Clearance list (DataTables)
if (isset($_GET['fetch_property_clearance_list'])) {
    $role = isset($_SESSION['role']) ? strtoupper(trim($_SESSION['role'])) : '';
    $canViewDetails = in_array($role, ['SYSTEM-ADMIN', 'CLEARANCE-ADMIN'], true);

    // Clearance types that should ignore accountability checks (UI-wise)
    $ignoreAccountabilityTypes = ['TRAVEL ABROAD', 'MATERNITY LEAVE', 'VACATION LEAVE', 'VACTION LEAVE'];

    $sql = "SELECT
                ch.control_number,
                ch.created_at,
                ch.status,
                e.emp_name,
                ct.clearance_name,
                (acct.emp_id IS NOT NULL) AS has_accountability,
                COALESCE(rp.reprint_count, 0) AS reprint_count
            FROM clearance_history AS ch
            JOIN property_clearance AS pc ON pc.control_number = ch.control_number
            JOIN employee AS e ON ch.emp_id = e.emp_id
            JOIN clearance_type AS ct ON ch.ctype_id = ct.clearance_code
            LEFT JOIN (
                SELECT emp_id FROM general_fund_property_history WHERE status = 1
                UNION
                SELECT emp_id FROM sef_property_history WHERE status = 1
            ) AS acct ON acct.emp_id = ch.emp_id
            LEFT JOIN (
                SELECT ctrl, COUNT(*) AS reprint_count
                FROM (
                    SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(activity, '|CTRL=', -1), '|', 1) AS ctrl
                    FROM activity_log
                    WHERE activity LIKE 'PROPERTY CLEARANCE REPRINT|CTRL=%'
                ) AS x
                GROUP BY ctrl
            ) AS rp ON rp.ctrl = ch.control_number
            ORDER BY ch.created_at DESC";

    $query = mysqli_query($conn, $sql);
    if (!$query) {
        return json_response(500, 'Failed to fetch clearances.', ['error' => mysqli_error($conn)]);
    }

    $rows = [];
    while ($row = mysqli_fetch_assoc($query)) {
        $releaseStatus = isset($row['status']) ? (int)$row['status'] : 0;
        $clearanceNameUpper = strtoupper(trim((string)($row['clearance_name'] ?? '')));
        $hasAccountabilityRaw = !empty($row['has_accountability']);
        $ignoreAccountability = in_array($clearanceNameUpper, $ignoreAccountabilityTypes, true);
        $hasAccountability = ($hasAccountabilityRaw && !$ignoreAccountability);
        $reprintCount = isset($row['reprint_count']) ? (int)$row['reprint_count'] : 0;

        $badge = '';
        if ($releaseStatus === 2) {
            $badge = '<span class="badge badge-danger"><i class="fa-solid fa-ban"></i> CANCELED</span>';
        } elseif ($releaseStatus === 1) {
            $badge = '<span class="badge badge-success"><i class="fa-solid fa-thumbs-up"></i> RELEASED</span>';
        } elseif ($hasAccountability) {
            $badge = '<span class="badge badge-warning"><i class="fa-solid fa-arrows-rotate"></i> PROCESSING</span>';
        } else {
            $badge = '<span class="badge badge-primary"><i class="fa-solid fa-circle-check"></i> READY</span>';
        }

        $controlNumber = (string)($row['control_number'] ?? '');
        $empName = (string)($row['emp_name'] ?? '');
        // Always plain text: editing is done via modal (Action column)
        $employeeCell = htmlspecialchars($empName);

        $createdAtRaw = (string)($row['created_at'] ?? '');
        $createdAtDisplay = $createdAtRaw ? date('F j, Y, g:i a', strtotime($createdAtRaw)) : '';

        $actionHtml = '';
        if ($canViewDetails) {
            if ($reprintCount >= 1) {
                $actionHtml = '<span class="btn btn-sm btn-secondary disabled" title="Locked (re-printed)" data-toggle="tooltip" data-placement="top" aria-label="Locked"><i class="fa-solid fa-lock"></i></span>';
            } else {
                $actionHtml = '<button type="button" class="btn btn-sm btn-info mr-1 btnEditPc" data-control="' . htmlspecialchars($controlNumber) . '" title="Re-print" data-toggle="tooltip" data-placement="top" aria-label="Re-print"><i class="fa-solid fa-print"></i></button>';
            }
        }

        $rows[] = [
            'employee_cell' => $employeeCell,
            'clearance_name' => (string)($row['clearance_name'] ?? ''),
            'created_at' => $createdAtRaw,
            'created_at_display' => $createdAtDisplay,
            'control_number' => $controlNumber,
            'status_badge' => $badge,
            'action_html' => $actionHtml,
        ];
    }

    echo json_encode([
        'status' => 200,
        'data' => $rows,
    ]);
    return false;
}

// Property Clearance READY notifications (navbar bell)
// Definition of READY matches the DataTable:
// - ch.status = 0 (not released/canceled)
// - and either no active accountability OR clearance type is in the ignore list
if (isset($_GET['fetch_pc_ready_notifications'])) {
    $role = isset($_SESSION['role']) ? strtoupper(trim((string)$_SESSION['role'])) : '';
    if (empty($_SESSION['alogin']) || $role !== 'CLEARANCE-ADMIN') {
        return json_response(403, 'Unauthorized.');
    }

    $ignoreAccountabilityTypes = ['TRAVEL ABROAD', 'MATERNITY LEAVE', 'VACATION LEAVE', 'VACTION LEAVE'];
    $limit = 30;

    $sql = "SELECT
                ch.control_number,
                ch.created_at,
                ch.status,
                e.emp_name,
                ct.clearance_name,
                (acct.emp_id IS NOT NULL) AS has_accountability
            FROM clearance_history AS ch
            JOIN property_clearance AS pc ON pc.control_number = ch.control_number
            JOIN employee AS e ON ch.emp_id = e.emp_id
            JOIN clearance_type AS ct ON ch.ctype_id = ct.clearance_code
            LEFT JOIN (
                SELECT emp_id FROM general_fund_property_history WHERE status = 1
                UNION
                SELECT emp_id FROM sef_property_history WHERE status = 1
            ) AS acct ON acct.emp_id = ch.emp_id
            WHERE ch.status = 0
            ORDER BY ch.created_at DESC
            LIMIT {$limit}";

    $query = mysqli_query($conn, $sql);
    if (!$query) {
        return json_response(500, 'Failed to fetch notifications.', ['error' => mysqli_error($conn)]);
    }

    $items = [];
    while ($row = mysqli_fetch_assoc($query)) {
        $clearanceNameUpper = strtoupper(trim((string)($row['clearance_name'] ?? '')));
        $ignoreAccountability = in_array($clearanceNameUpper, $ignoreAccountabilityTypes, true);
        $hasAccountability = !empty($row['has_accountability']);
        $isReady = ($ignoreAccountability || !$hasAccountability);
        if (!$isReady) { continue; }

        $createdAtRaw = (string)($row['created_at'] ?? '');
        $createdAtDisplay = $createdAtRaw ? date('M j, g:i a', strtotime($createdAtRaw)) : '';

        $items[] = [
            'control_number' => (string)($row['control_number'] ?? ''),
            'emp_name' => (string)($row['emp_name'] ?? ''),
            'clearance_name' => (string)($row['clearance_name'] ?? ''),
            'created_at' => $createdAtRaw,
            'created_at_display' => $createdAtDisplay,
        ];
    }

    return json_response(200, 'OK', [
        'items' => $items,
        'ready_count' => count($items)
    ]);
}

// Clearance statistics (released per month, grouped by clearance type)
// Used by services/clearance-statistic.php
if (isset($_GET['fetch_clearance_statistics'])) {
    $role = isset($_SESSION['role']) ? strtoupper(trim((string)$_SESSION['role'])) : '';
    if (empty($_SESSION['alogin']) || !in_array($role, ['CLEARANCE-ADMIN', 'SYSTEM-ADMIN'], true)) {
        return json_response(403, 'Unauthorized.');
    }

    // Lightweight cache (5 minutes) to keep the dashboard snappy.
    // Cache key includes filters.
    $cache_ttl = 300;
    $cache_key_parts = [];
    $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
    if ($year < 2000 || $year > 2100) { $year = (int)date('Y'); }

    $from = isset($_GET['from']) ? trim((string)$_GET['from']) : '';
    $to = isset($_GET['to']) ? trim((string)$_GET['to']) : '';
    $fromOk = ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from));
    $toOk = ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to));

    $cache_key_parts[] = 'fetch_clearance_statistics';
    $cache_key_parts[] = 'y='.$year;
    $cache_key_parts[] = 'from='.($fromOk ? $from : '');
    $cache_key_parts[] = 'to='.($toOk ? $to : '');
    $cache_key = sha1(implode('|', $cache_key_parts));
    $cache_file = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'gso_' . $cache_key . '.json';

    if (is_file($cache_file)) {
        $age = time() - (int)@filemtime($cache_file);
        if ($age >= 0 && $age <= $cache_ttl) {
            $cached = @file_get_contents($cache_file);
            if ($cached !== false && $cached !== '') {
                $decoded = json_decode($cached, true);
                if (is_array($decoded)) {
                    return json_response(200, 'OK', $decoded);
                }
            }
        }
    }

    $months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    $types = [];
    $series = []; // type => [12]
    $totalReleased = 0;

    // Build a safe WHERE clause based on filters.
    // Released definition: ch.status=1 with non-null release_date.
    $where = "ch.status = 1 AND ch.release_date IS NOT NULL";
    $params = [];
    $typestr = '';

    if ($fromOk && $toOk) {
        // Inclusive range: from 00:00:00 to 23:59:59
        $where .= " AND ch.release_date >= ? AND ch.release_date <= ?";
        $params[] = $from . ' 00:00:00';
        $params[] = $to . ' 23:59:59';
        $typestr .= 'ss';
    } else {
        // Year filter default
        $where .= " AND YEAR(ch.release_date) = ?";
        $params[] = $year;
        $typestr .= 'i';
    }

    $sql = "SELECT
                MONTH(ch.release_date) AS m,
                ct.clearance_name AS clearance_name,
                COUNT(*) AS cnt
            FROM clearance_history AS ch
            JOIN clearance_type AS ct ON ch.ctype_id = ct.clearance_code
            WHERE {$where}
            GROUP BY MONTH(ch.release_date), ct.clearance_name
            ORDER BY MONTH(ch.release_date) ASC, ct.clearance_name ASC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return json_response(500, 'Failed to prepare query.', ['error' => mysqli_error($conn)]);
    }
    if ($typestr !== '') {
        $stmt->bind_param($typestr, ...$params);
    }
    if (!$stmt->execute()) {
        $stmt->close();
        return json_response(500, 'Failed to fetch statistics.', ['error' => mysqli_error($conn)]);
    }
    $res = $stmt->get_result();

    while ($row = $res ? $res->fetch_assoc() : null) {
        if (!$row) { break; }
        $m = isset($row['m']) ? (int)$row['m'] : 0;
        if ($m < 1 || $m > 12) { continue; }
        $typeName = (string)($row['clearance_name'] ?? '');
        $cnt = isset($row['cnt']) ? (int)$row['cnt'] : 0;
        if ($typeName === '') { $typeName = 'UNKNOWN'; }

        if (!isset($series[$typeName])) {
            $series[$typeName] = array_fill(0, 12, 0);
            $types[] = $typeName;
        }
        $series[$typeName][$m - 1] += $cnt;
        $totalReleased += $cnt;
    }
    $stmt->close();

    $totalsByType = [];
    foreach ($series as $t => $arr) { $totalsByType[$t] = array_sum($arr); }

    // KPIs
    $thisMonth = (int)date('n');
    $lastMonth = (int)date('n', strtotime('first day of last month'));
    $thisMonthCount = 0;
    $lastMonthCount = 0;
    foreach ($series as $t => $arr) {
        $thisMonthCount += (int)($arr[$thisMonth - 1] ?? 0);
        $lastMonthCount += (int)($arr[$lastMonth - 1] ?? 0);
    }

    // Pending / For Release: not yet released/printed.
    $pendingCount = 0;
    $qPending = $conn->prepare("SELECT COUNT(*) AS cnt FROM clearance_history WHERE status = 0");
    if ($qPending) {
        if ($qPending->execute()) {
            $rPending = $qPending->get_result();
            if ($rPending && ($rowPending = $rPending->fetch_assoc())) {
                $pendingCount = (int)($rowPending['cnt'] ?? 0);
            }
        }
        $qPending->close();
    }

    $payload = [
        'year' => $year,
        'from' => $fromOk ? $from : '',
        'to' => $toOk ? $to : '',
        'months' => $months,
        'types' => $types,
        'series' => $series,
        'totals_by_type' => $totalsByType,
        'total_released' => $totalReleased,
        'kpis' => [
            'total' => $totalReleased,
            'this_month' => $thisMonthCount,
            'last_month' => $lastMonthCount,
            'pending' => $pendingCount,
        ],
    ];

    @file_put_contents($cache_file, json_encode($payload));
    return json_response(200, 'OK', $payload);
}

// Clearance statistics CSV export (summary)
if (isset($_GET['export_clearance_statistics_csv'])) {
    $role = isset($_SESSION['role']) ? strtoupper(trim((string)$_SESSION['role'])) : '';
    if (empty($_SESSION['alogin']) || !in_array($role, ['CLEARANCE-ADMIN', 'SYSTEM-ADMIN'], true)) {
        http_response_code(403);
        exit('Unauthorized');
    }

    $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
    if ($year < 2000 || $year > 2100) { $year = (int)date('Y'); }
    $from = isset($_GET['from']) ? trim((string)$_GET['from']) : '';
    $to = isset($_GET['to']) ? trim((string)$_GET['to']) : '';
    $fromOk = ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from));
    $toOk = ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to));

    $where = "ch.status = 1 AND ch.release_date IS NOT NULL";
    $params = [];
    $typestr = '';
    if ($fromOk && $toOk) {
        $where .= " AND ch.release_date >= ? AND ch.release_date <= ?";
        $params[] = $from . ' 00:00:00';
        $params[] = $to . ' 23:59:59';
        $typestr .= 'ss';
    } else {
        $where .= " AND YEAR(ch.release_date) = ?";
        $params[] = $year;
        $typestr .= 'i';
    }

    $sql = "SELECT DATE_FORMAT(ch.release_date, '%Y-%m') AS ym, ct.clearance_name, COUNT(*) AS cnt
            FROM clearance_history ch
            JOIN clearance_type ct ON ch.ctype_id = ct.clearance_code
            WHERE {$where}
            GROUP BY ym, ct.clearance_name
            ORDER BY ym ASC, ct.clearance_name ASC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) { http_response_code(500); exit('Failed to prepare query'); }
    $stmt->bind_param($typestr, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="clearance_statistics_' . ($fromOk?$from:$year) . '.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Period', 'Clearance Category', 'Released Count']);
    while ($res && ($row = $res->fetch_assoc())) {
        fputcsv($out, [$row['ym'], $row['clearance_name'], (int)$row['cnt']]);
    }
    fclose($out);
    $stmt->close();
    exit();
}

// Drilldown: released clearances list for a given filter (month/type)
if (isset($_GET['fetch_clearance_released_details'])) {
    $role = isset($_SESSION['role']) ? strtoupper(trim((string)$_SESSION['role'])) : '';
    if (empty($_SESSION['alogin']) || !in_array($role, ['CLEARANCE-ADMIN', 'SYSTEM-ADMIN'], true)) {
        return json_response(403, 'Unauthorized.');
    }

    $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
    if ($year < 2000 || $year > 2100) { $year = (int)date('Y'); }
    $month = isset($_GET['month']) ? (int)$_GET['month'] : 0;
    if ($month < 0 || $month > 12) { $month = 0; }

    $from = isset($_GET['from']) ? trim((string)$_GET['from']) : '';
    $to = isset($_GET['to']) ? trim((string)$_GET['to']) : '';
    $fromOk = ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from));
    $toOk = ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to));

    $clearanceName = isset($_GET['type']) ? trim((string)$_GET['type']) : '';

    $where = "ch.status = 1 AND ch.release_date IS NOT NULL";
    $params = [];
    $typestr = '';

    if ($fromOk && $toOk) {
        $where .= " AND ch.release_date >= ? AND ch.release_date <= ?";
        $params[] = $from . ' 00:00:00';
        $params[] = $to . ' 23:59:59';
        $typestr .= 'ss';
    } else {
        $where .= " AND YEAR(ch.release_date) = ?";
        $params[] = $year;
        $typestr .= 'i';
    }

    if ($month >= 1 && $month <= 12) {
        $where .= " AND MONTH(ch.release_date) = ?";
        $params[] = $month;
        $typestr .= 'i';
    }
    if ($clearanceName !== '') {
        $where .= " AND ct.clearance_name = ?";
        $params[] = $clearanceName;
        $typestr .= 's';
    }

    $sql = "SELECT
                ch.control_number,
                e.emp_name,
                ct.clearance_name,
                ch.release_date
            FROM clearance_history ch
            JOIN employee e ON ch.emp_id = e.emp_id
            JOIN clearance_type ct ON ch.ctype_id = ct.clearance_code
            WHERE {$where}
            ORDER BY ch.release_date DESC
            LIMIT 500";

    $stmt = $conn->prepare($sql);
    if (!$stmt) { return json_response(500, 'Failed to prepare query.'); }
    if ($typestr !== '') { $stmt->bind_param($typestr, ...$params); }
    if (!$stmt->execute()) { $stmt->close(); return json_response(500, 'Failed to fetch details.'); }
    $res = $stmt->get_result();

    $rows = [];
    while ($res && ($row = $res->fetch_assoc())) {
        $rows[] = [
            'control_number' => (string)($row['control_number'] ?? ''),
            'emp_name' => (string)($row['emp_name'] ?? ''),
            'clearance_name' => (string)($row['clearance_name'] ?? ''),
            'release_date' => (string)($row['release_date'] ?? ''),
            'release_date_display' => !empty($row['release_date']) ? date('M j, Y, g:i a', strtotime($row['release_date'])) : '',
        ];
    }
    $stmt->close();

    return json_response(200, 'OK', [
        'items' => $rows,
        'count' => count($rows)
    ]);
}

// Drilldown CSV export (details)
if (isset($_GET['export_clearance_released_details_csv'])) {
    $role = isset($_SESSION['role']) ? strtoupper(trim((string)$_SESSION['role'])) : '';
    if (empty($_SESSION['alogin']) || !in_array($role, ['CLEARANCE-ADMIN', 'SYSTEM-ADMIN'], true)) {
        http_response_code(403);
        exit('Unauthorized');
    }

    // Reuse same filters as fetch_clearance_released_details.
    $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
    if ($year < 2000 || $year > 2100) { $year = (int)date('Y'); }
    $month = isset($_GET['month']) ? (int)$_GET['month'] : 0;
    if ($month < 0 || $month > 12) { $month = 0; }

    $from = isset($_GET['from']) ? trim((string)$_GET['from']) : '';
    $to = isset($_GET['to']) ? trim((string)$_GET['to']) : '';
    $fromOk = ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from));
    $toOk = ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to));

    $clearanceName = isset($_GET['type']) ? trim((string)$_GET['type']) : '';

    $where = "ch.status = 1 AND ch.release_date IS NOT NULL";
    $params = [];
    $typestr = '';
    if ($fromOk && $toOk) {
        $where .= " AND ch.release_date >= ? AND ch.release_date <= ?";
        $params[] = $from . ' 00:00:00';
        $params[] = $to . ' 23:59:59';
        $typestr .= 'ss';
    } else {
        $where .= " AND YEAR(ch.release_date) = ?";
        $params[] = $year;
        $typestr .= 'i';
    }
    if ($month >= 1 && $month <= 12) {
        $where .= " AND MONTH(ch.release_date) = ?";
        $params[] = $month;
        $typestr .= 'i';
    }
    if ($clearanceName !== '') {
        $where .= " AND ct.clearance_name = ?";
        $params[] = $clearanceName;
        $typestr .= 's';
    }

    $sql = "SELECT ch.control_number, e.emp_name, ct.clearance_name, ch.release_date
            FROM clearance_history ch
            JOIN employee e ON ch.emp_id = e.emp_id
            JOIN clearance_type ct ON ch.ctype_id = ct.clearance_code
            WHERE {$where}
            ORDER BY ch.release_date DESC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) { http_response_code(500); exit('Failed to prepare query'); }
    if ($typestr !== '') { $stmt->bind_param($typestr, ...$params); }
    $stmt->execute();
    $res = $stmt->get_result();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="clearance_released_details.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Control No.', 'Employee', 'Clearance Category', 'Release Date']);
    while ($res && ($row = $res->fetch_assoc())) {
        fputcsv($out, [$row['control_number'], $row['emp_name'], $row['clearance_name'], $row['release_date']]);
    }
    fclose($out);
    $stmt->close();
    exit();
}

// Property Clearance: allow exactly one re-print after released
if (isset($_POST['reprint_property_clearance'])) {
    $role = isset($_SESSION['role']) ? strtoupper(trim($_SESSION['role'])) : '';
    if (!in_array($role, ['SYSTEM-ADMIN', 'CLEARANCE-ADMIN'], true)) {
        return json_response(403, 'Unauthorized.');
    }

    $controlNumber = isset($_POST['control_number']) ? trim((string)$_POST['control_number']) : '';
    if ($controlNumber === '') {
        return json_response(422, 'Missing control number.');
    }
    $controlEsc = mysqli_real_escape_string($conn, $controlNumber);

    $reason = isset($_POST['reason']) ? strtoupper(trim((string)$_POST['reason'])) : '';
    $allowedReasons = ['DATA CORRECTION', 'RE-ISSUANCE', 'PRINTER OR SYSTEM ERROR'];
    if (!in_array($reason, $allowedReasons, true)) {
        return json_response(422, 'Invalid reason.');
    }
    $reasonEsc = mysqli_real_escape_string($conn, $reason);

    $hdrQ = mysqli_query($conn, "SELECT status FROM clearance_history WHERE control_number='$controlEsc' LIMIT 1");
    if (!$hdrQ || mysqli_num_rows($hdrQ) !== 1) {
        return json_response(404, 'Clearance not found.');
    }
    $hdrRow = mysqli_fetch_assoc($hdrQ);
    $statusVal = isset($hdrRow['status']) ? (int)$hdrRow['status'] : 0;
    if ($statusVal !== 1) {
        return json_response(422, 'Re-print is allowed only for RELEASED clearances.');
    }

    $likeCtrl = str_replace(['%', '_'], ['\\%', '\\_'], $controlEsc);
    $cntQ = mysqli_query(
        $conn,
        "SELECT COUNT(*) AS cnt
         FROM activity_log
         WHERE activity LIKE 'PROPERTY CLEARANCE REPRINT|CTRL={$likeCtrl}|%' ESCAPE '\\\\'"
    );
    if ($cntQ) {
        $cntRow = mysqli_fetch_assoc($cntQ);
        $cnt = isset($cntRow['cnt']) ? (int)$cntRow['cnt'] : 0;
        if ($cnt >= 1) {
            return json_response(422, 'Re-print already used for this clearance.');
        }
    }

    $actvtyRaw = "PROPERTY CLEARANCE REPRINT|CTRL={$controlNumber}|REASON={$reason}";
    $actvty = mysqli_real_escape_string($conn, $actvtyRaw);
    $logQ = mysqli_query($conn, "INSERT INTO activity_log(admin_id,ip_address,activity) VALUES('$uid','$uip','$actvty')");
    if (!$logQ) {
        return json_response(500, 'Failed to log re-print.', ['error' => mysqli_error($conn)]);
    }

    return json_response(200, 'Re-print allowed.');
}

// Property Clearance details for modal
if (isset($_GET['fetch_pc_details'])) {
    $role = isset($_SESSION['role']) ? strtoupper(trim($_SESSION['role'])) : '';
    if (!in_array($role, ['SYSTEM-ADMIN', 'CLEARANCE-ADMIN'], true)) {
        return json_response(403, 'Unauthorized.');
    }

    $controlNumber = isset($_GET['control_number']) ? trim((string)$_GET['control_number']) : '';
    if ($controlNumber === '') {
        return json_response(422, 'Missing control number.');
    }
    $controlEsc = mysqli_real_escape_string($conn, $controlNumber);

    $sql = "SELECT
                pc.control_number,
                pc.or_number,
                pc.dept_id,
                pc.ctype_id,
                pc.address,
                pc.city,
                pc.created_at,
                e.emp_id,
                e.emp_name,
                e.position,
                ch.status AS release_status,
                ct.clearance_name
            FROM property_clearance AS pc
            JOIN employee AS e ON pc.emp_id = e.emp_id
            JOIN clearance_history AS ch ON ch.control_number = pc.control_number
            JOIN clearance_type AS ct ON pc.ctype_id = ct.clearance_code
            WHERE pc.control_number='$controlEsc'
            LIMIT 1";

    $q = mysqli_query($conn, $sql);
    if (!$q || mysqli_num_rows($q) !== 1) {
        return json_response(404, 'Clearance not found.');
    }
    $row = mysqli_fetch_assoc($q);

    // Lock rule: once re-printed, do not allow viewing/editing
    $reprintCount = pc_reprint_count($conn, $controlNumber);
    if ($reprintCount >= 1) {
        return json_response(423, 'This clearance is locked after re-print.');
    }

    $empId = mysqli_real_escape_string($conn, (string)($row['emp_id'] ?? ''));
    $releaseStatus = isset($row['release_status']) ? (int)$row['release_status'] : 0;
    $clearanceName = strtoupper(trim((string)($row['clearance_name'] ?? '')));

    // Types where accountability should NOT block approval/printing nor show warning
    $ignoreAccountabilityTypes = ['TRAVEL ABROAD', 'MATERNITY LEAVE', 'VACATION LEAVE', 'VACTION LEAVE'];
    $ignoreAccountability = in_array($clearanceName, $ignoreAccountabilityTypes, true);

    $acctQ = mysqli_query(
        $conn,
        "SELECT 1 FROM (
            SELECT emp_id FROM general_fund_property_history WHERE status = 1
            UNION
            SELECT emp_id FROM sef_property_history WHERE status = 1
        ) AS acct WHERE emp_id='$empId' LIMIT 1"
    );
    $hasAccountabilityRaw = ($acctQ && mysqli_num_rows($acctQ) > 0);
    $hasAccountability = ($hasAccountabilityRaw && !$ignoreAccountability);

    $canApprove = false;
    if ($releaseStatus === 0) {
        $canApprove = !$hasAccountability;
    }

    $statusLabel = 'PROCESSING';
    if ($releaseStatus === 2) { $statusLabel = 'CANCELED'; }
    elseif ($releaseStatus === 1) { $statusLabel = 'RELEASED'; }
    elseif (!$hasAccountability) { $statusLabel = 'READY'; }

    // Re-print eligibility (exactly once) for this control number
    $canReprint = ($releaseStatus === 1 && $reprintCount < 1);

    return json_response(200, 'OK', [
        'record' => [
            'control_number' => (string)($row['control_number'] ?? ''),
            'or_number' => (string)($row['or_number'] ?? ''),
            'dept_id' => (string)($row['dept_id'] ?? ''),
            'ctype_id' => (string)($row['ctype_id'] ?? ''),
            'address' => (string)($row['address'] ?? ''),
            'city' => (string)($row['city'] ?? ''),
            'created_at' => (string)($row['created_at'] ?? ''),
            'emp_id' => (string)($row['emp_id'] ?? ''),
            'emp_name' => (string)($row['emp_name'] ?? ''),
            'position' => (string)($row['position'] ?? ''),
            'clearance_name' => (string)($row['clearance_name'] ?? ''),
            'release_status' => $releaseStatus,
        ],
        'flags' => [
            'has_accountability' => $hasAccountability,
            'has_accountability_raw' => $hasAccountabilityRaw,
            'ignore_accountability' => $ignoreAccountability,
            'can_approve' => $canApprove,
            'status_label' => $statusLabel,
            'reprint_count' => $reprintCount,
            'can_reprint' => $canReprint,
        ]
    ]);
}

//contains all sql query statement(select,insert,update,delete)

if (!function_exists('gso_admin_role_allowed')) {
    function gso_admin_role_allowed($role) {
        return in_array(strtoupper(trim((string)$role)), [
            'SYSTEM-ADMIN',
            'GF/SEF-ADMIN',
            'DISPOSAL-ADMIN',
            'CLEARANCE-ADMIN',
            'MV-ADMIN',
            'USER',
        ], true);
    }
}

//add administrator
if (isset($_POST['save_admin_info'])) {
    header('Content-Type: application/json; charset=utf-8');
    if (!gso_require_role_json(['SYSTEM-ADMIN'])) { return false; }
    $formToken = (string)($_POST['admin_form_token'] ?? '');
    if (!gso_validate_form_token('admin_panel', $formToken, 1800, false)) {
        return json_response(419, 'Invalid or expired form token.');
    }
    $fname = strtoupper(trim((string)($_POST['fname'] ?? '')));
    $lname = strtoupper(trim((string)($_POST['lname'] ?? '')));
    $email = trim((string)($_POST['email'] ?? ''));
    $contact = trim((string)($_POST['contact'] ?? ''));
    $empnumber = strtoupper(trim((string)($_POST['emp_number'] ?? '')));
    $role = strtoupper(trim((string)($_POST['role'] ?? '')));
    if (!gso_admin_role_allowed($role)) {
        return json_response(422, 'Invalid administrator role.');
    }
    if ($fname === '' || $lname === '' || $email === '' || $empnumber === '') {
        return json_response(422, 'Missing required administrator fields.');
    }
    $password = password_hash('12345',PASSWORD_DEFAULT);
    $statusAdmin = 0;
    $payload = [
        'first_name' => $fname,
        'last_name' => $lname,
        'contact_number' => $contact,
        'email' => $email,
        'role' => $role,
        'emp_number' => $empnumber,
        'password' => $password,
        'status' => $statusAdmin,
    ];
    if(gso_insert_administrator($conn, $payload)){ return json_response(200,'Added succesfully!'); }
    return json_response(500,'opps..something went wrong..');
}
//to fetch administrator information
if(isset($_GET['adminid'])){
    header('Content-Type: application/json; charset=utf-8');
    if (!gso_require_role_json(['SYSTEM-ADMIN'])) { return false; }
    $adminid = (string)($_GET['adminid'] ?? '');
    $adminRow = gso_fetch_administrator_by_id($conn, $adminid);
    if ($adminRow) { return json_response(200,'Admin id fetch successfully',$adminRow); }
    return json_response(422,'No Admin id found');
}


//update administrator information
if (isset($_POST['update_admin_info'])) {
    header('Content-Type: application/json; charset=utf-8');
    if (!gso_require_role_json(['SYSTEM-ADMIN'])) { return false; }
    $formToken = (string)($_POST['admin_form_token'] ?? '');
    if (!gso_validate_form_token('admin_panel', $formToken, 1800, false)) {
        return json_response(419, 'Invalid or expired form token.');
    }
    $adminid = trim((string)($_POST['id'] ?? ''));
    $first_name = trim((string)($_POST['efname'] ?? ''));
    $last_name = trim((string)($_POST['elname'] ?? ''));
    $email = trim((string)($_POST['eemail'] ?? ''));
    $contact_number = trim((string)($_POST['econtact'] ?? ''));
    $role = strtoupper(trim((string)($_POST['erole'] ?? '')));
    if (!gso_admin_role_allowed($role)) {
        return json_response(422, 'Invalid administrator role.');
    }
    $emp_number = trim((string)($_POST['empnumber'] ?? ''));
    if ($adminid === '' || $first_name === '' || $last_name === '' || $email === '' || $emp_number === '') {
        return json_response(422, 'Missing required administrator fields.');
    }
    $payload = [
        'admin_id' => $adminid,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'contact_number' => $contact_number,
        'email' => $email,
        'role' => $role,
        'emp_number' => $emp_number,
    ];
    if(gso_update_administrator($conn, $payload)){ return json_response(200,'Updated succesfully!'); }
    return json_response(500,'opps..something went wrong..');
}
//delete administrator
if (isset($_POST['delete_admin'])) {
    header('Content-Type: application/json; charset=utf-8');
    if (!gso_require_role_json(['SYSTEM-ADMIN'])) { return false; }
    $formToken = (string)($_POST['admin_form_token'] ?? '');
    if (!gso_validate_form_token('admin_panel', $formToken, 1800, false)) {
        return json_response(419, 'Invalid or expired form token.');
    }
    $adid = trim((string)($_POST['deladmin'] ?? ''));
    if ($adid === '') {
        return json_response(422, 'Missing administrator id.');
    }
    if(gso_delete_administrator($conn, $adid)){ return json_response(200,'Admin Deleted succesfully!'); }
    return json_response(500,'opps..something went wrong..');
}
//add employee
if (isset($_POST['save_employee_info'])) {
    $fname = escape_up($conn,$_POST['fname']);
    $department = escape_raw($conn, $_POST['department']);
    $position = escape_up($conn, $_POST['position']);
    $pcustodian = isset($_POST['pcustodian']) ? (int)$_POST['pcustodian'] : null;

    $created = gso_create_employee_atomic($conn, $fname, $department, $position, 1, $pcustodian);
    if(isset($created['ok']) && $created['ok']){
        return json_response(200,'Added successfully!.', ['emp_id'=>$created['emp_id']]);
    }
    return json_response(500, $created['message'] ?? 'opps..something went wrong..');
}
//fetch employee details
if (isset($_GET['empid'])) {
    $empid = mysqli_real_escape_string($conn, $_GET['empid']);

    $sql = "SELECT e.emp_id,e.emp_name as name ,e.position,e.department_code,e.property_custodian,d.department_name,d.department_code 
    FROM employee AS e JOIN department AS d ON e.department_code = d.department_code WHERE e.emp_id = '$empid'";
    $query = mysqli_query($conn, $sql);

    if (mysqli_num_rows($query) == 1) {
        $emp = mysqli_fetch_array($query);

        $res = [
            'status' => 200,
            'message' => 'Employee id fetch successfully',
            'data' => $emp,
        ];
        echo json_encode($res);
        return false;
    } else {
        $res = [
            'status' => 422,
            'message' => 'No Employee id found',
        ];
        echo json_encode($res);
        return false;
    }
}
//update employee
if (isset($_POST['update_employee_info'])) {
    $empId = escape_raw($conn, $_POST['empId']);
    $fullname = escape_up($conn,$_POST['name']);
    $position = escape_up($conn,$_POST['eposition']);
    $department = escape_raw($conn,$_POST['edepartment']);
    $custodian = isset($_POST['epcustodian']) ? (int)$_POST['epcustodian'] : 0;
    $agencies = employee_agency_from_department_code($department);
    // Quote department_code to support alphanumeric department codes
    $sql = "UPDATE employee SET emp_name='$fullname', position='$position', agencies='$agencies', department_code='$department', property_custodian='$custodian' WHERE emp_id = '$empId' ";
    if(mysqli_query($conn,$sql)){ return json_response(200,'Updated succesfully!'); }
    return json_response(500,'opps..something went wrong..');
}
//delete employee
if (isset($_POST['delete_emp'])) {
    $delemp = escape_raw($conn, $_POST['delemployee']);
    $sql = "DELETE FROM employee WHERE emp_id = '$delemp' ";
    if(mysqli_query($conn,$sql)){ return json_response(200,'Deleted succesfully!'); }
    return json_response(500,'opps..something went wrong..');
}
//add department
if (isset($_POST['save_dept'])) {
    $agencies = escape_up($conn,$_POST['agency_type']);
    $deptname = escape_up($conn,$_POST['deptname']);
    $deptcode = escape_up($conn,$_POST['deptcode']);
    $sql = "INSERT INTO department (agencies, department_name, department_code) VALUES('$agencies','$deptname','$deptcode')";
    if(mysqli_query($conn,$sql)){ return json_response(200,'Added successfully!.'); }
    return json_response(500,'opps..something went wrong..');
}
//fetch department details
if (isset($_GET['deptid'])) {
    $deptid = mysqli_real_escape_string($conn, $_GET['deptid']);
    $sql = "SELECT * FROM department WHERE dept_id = '$deptid' LIMIT 1 ";
    $query = mysqli_query($conn, $sql);
    if (mysqli_num_rows($query) == 1) { return json_response(200,'Department id fetch successfully',mysqli_fetch_assoc($query)); }
    return json_response(422,'No department id found');
}
//update department
if (isset($_POST['update_dept'])) {
    $DeptId = escape_up($conn,$_POST['DeptId']);
    $Agencies = escape_up($conn,$_POST['eagency_type']);
    $DeptName = escape_up($conn,$_POST['edeptname']);
    $DeptCode = escape_raw($conn,$_POST['edeptcode']);
    $sql = "UPDATE department SET agencies = '$Agencies', department_name = '$DeptName' , department_code = '$DeptCode' WHERE dept_id = '$DeptId' ";
    if(mysqli_query($conn,$sql)){ return json_response(200,'Updated succesfully!'); }
    return json_response(500,'opps..something went wrong..');
}
//delete department
if (isset($_POST['delete_dept'])) {
    $deldept = mysqli_real_escape_string($conn, $_POST['deldept']);
    $sql = "DELETE FROM department WHERE dept_id = '$deldept' ";
    if(mysqli_query($conn,$sql)){ return json_response(200,'Deleted succesfully!'); }
    return json_response(500,'opps..something went wrong..');
}


//to return p.a.r and i.c.s general fund to stock
if (isset($_POST['returned_item'])) {
    $par = mysqli_real_escape_string($conn, $_POST['parnum']);
    $employeeNumber = mysqli_real_escape_string($conn,$_POST['empid']);
    $deptid = mysqli_real_escape_string($conn,$_POST['cdept_id']);
    $category = mysqli_real_escape_string($conn, $_POST['cat']);
    $reference = mysqli_real_escape_string($conn, $_POST['refnumber']);

    // Determine if this is GF or SEF by checking existence in primary tables
    $isGf = false; $isSef = false;
    $chkG = mysqli_query($conn, "SELECT 1 FROM par_gen_fund WHERE par_number='$par' LIMIT 1");
    if ($chkG && mysqli_num_rows($chkG) === 1) { $isGf = true; }
    if (!$isGf) {
        $chkS = mysqli_query($conn, "SELECT 1 FROM property_sef WHERE property_number='$par' LIMIT 1");
        if ($chkS && mysqli_num_rows($chkS) === 1) { $isSef = true; }
    }

    // Keep the copy explicit (no SELECT *) so schema changes don't break transfers.
    // return_to_stock uses a single unified `id` (older DBs may not have AUTO_INCREMENT).
    if (!function_exists('return_to_stock_id_is_auto_increment')) {
        function return_to_stock_id_is_auto_increment($conn) {
            $q = mysqli_query($conn, "SHOW COLUMNS FROM return_to_stock LIKE 'id'");
            if ($q && ($row = mysqli_fetch_assoc($q))) {
                return stripos((string)($row['Extra'] ?? ''), 'auto_increment') !== false;
            }
            return false;
        }
    }
    if (!function_exists('next_return_to_stock_id')) {
        function next_return_to_stock_id($conn) {
            $q = mysqli_query($conn, "
                SELECT GREATEST(
                    COALESCE((SELECT MAX(id) FROM return_to_stock), 0),
                    COALESCE((SELECT MAX(id) FROM unserviceable_items), 0)
                ) + 1 AS next_id
            ");
            if ($q && ($r = mysqli_fetch_assoc($q))) {
                return (int)($r['next_id'] ?? 1);
            }
            return 1;
        }
    }

    $stockColsNoId = "fund,category,item,model,description,serial_number,serial_number_2,par_number,unit_value,date_aquired,account_code,supplier,par_ics_number,purchase_order,purchase_request,obr_number,jev_number,remarks";
    $stockColsWithId = "id,{$stockColsNoId}";
    $needsExplicitId = !return_to_stock_id_is_auto_increment($conn);

    if ($isGf) {
        $insertReturnStock = $needsExplicitId
            ? ("INSERT INTO return_to_stock ({$stockColsWithId}) SELECT ".next_return_to_stock_id($conn).", {$stockColsNoId} FROM par_gen_fund WHERE par_number = '$par';")
            : ("INSERT INTO return_to_stock ({$stockColsNoId}) SELECT {$stockColsNoId} FROM par_gen_fund WHERE par_number = '$par';");

        $sql = $insertReturnStock . "
        UPDATE general_fund_property_history SET status = 2 WHERE par_number ='$par' AND emp_id = '$employeeNumber' AND status = 1 LIMIT 1;
        INSERT INTO return_history(emp_id,dept_id,par_number,reference_number,status,category,created_at) VALUES ('$employeeNumber','$deptid','$par','$reference',2,'$category','$today');
        DELETE FROM par_gen_fund WHERE par_number = '$par'; ";
    } else if ($isSef) {
        $newId = $needsExplicitId ? next_return_to_stock_id($conn) : null;
        // Map property_sef -> return_to_stock (column par_number will receive property_number)
        $insertReturnStock = $needsExplicitId
            ? ("INSERT INTO return_to_stock ({$stockColsWithId})\n                SELECT {$newId}, fund,category,item,model,description,serial_number,serial_number_2,property_number,unit_value,date_aquired,account_code,supplier,par_ics_number,purchase_order,purchase_request,obr_number,jev_number,remarks\n                    FROM property_sef WHERE property_number = '$par';")
            : ("INSERT INTO return_to_stock ({$stockColsNoId})\n                SELECT fund,category,item,model,description,serial_number,serial_number_2,property_number,unit_value,date_aquired,account_code,supplier,par_ics_number,purchase_order,purchase_request,obr_number,jev_number,remarks\n                    FROM property_sef WHERE property_number = '$par';");

        $sql = $insertReturnStock . "
                UPDATE sef_property_history SET status = 2 WHERE property_number ='$par' AND emp_id = '$employeeNumber' AND status = 1 LIMIT 1;
                INSERT INTO return_history(emp_id,dept_id,par_number,reference_number,status,category,created_at) VALUES ('$employeeNumber','$deptid','$par','$reference',2,'$category','$today');
                DELETE FROM property_sef WHERE property_number = '$par'; ";
    } else {
        echo json_encode(['status'=>422,'message'=>'Property not found in GF or SEF.']);
        return false;
    }
    // Execute as an all-or-nothing unit; also detect failures in *any* statement.
    mysqli_begin_transaction($conn);
    try {
        if (!mysqli_multi_query($conn, $sql)) {
            throw new Exception(mysqli_error($conn));
        }

        // Consume all results so we can catch errors in later statements.
        while (true) {
            $result = mysqli_store_result($conn);
            if ($result instanceof mysqli_result) {
                mysqli_free_result($result);
            }

            if (!mysqli_more_results($conn)) {
                break;
            }
            if (!mysqli_next_result($conn)) {
                throw new Exception(mysqli_error($conn));
            }
        }

        if (mysqli_errno($conn)) {
            throw new Exception(mysqli_error($conn));
        }

        mysqli_commit($conn);
        gso_log_activity($conn, $uid, $uip, "Returned property {$par} to stock (Reference: {$reference}).");
        echo json_encode(['status'=>200,'message'=>'Succesfully archive!']);
        return false;
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        echo json_encode(['status'=>500,'message'=>'Return to stock failed: '.$e->getMessage()]);
        return false;
    }
}

// Manual Return Item (admin/add-return.php)
// - If return_type = UNSERVICEABLE => insert into unserviceable_items + unserviceable_items_history
// - Else (RETURN TO STOCK) => insert into return_to_stock
// In both cases we insert into return_history so the Property Return Slip can print by reference_number.

// Generate Return Property Number (admin/add-return.php only)
// Formats:
// - PAR: RYYYY-SUBACCOUNT-0000-DEPT (e.g. R2025-05-030-0001-47)
// - ICS: RYY-ICS_SUB-000-DEPT (e.g. R26-223-001-47)
if (isset($_POST['generate_return_property_number'])) {
    if (!isset($_SESSION['alogin'])) {
        return json_response(401, 'Unauthorized');
    }

    $category = strtoupper(trim((string)($_POST['category'] ?? '')));
    $year = strtoupper(trim((string)($_POST['year'] ?? '')));
    $accountCode = strtoupper(trim((string)($_POST['account_code'] ?? '')));
    $deptCode = trim((string)($_POST['dept'] ?? ''));

    if ($category === '' || $year === '' || $accountCode === '' || $deptCode === '') {
        return json_response(422, 'Missing required inputs.');
    }

    $isFoundAtStation = in_array($year, ['FS', 'RFS'], true);
    if (!$isFoundAtStation && !preg_match('/^\d{4}$/', $year)) {
        return json_response(422, 'Invalid year.');
    }

    $parSub = [
        '1-07-05-030' => '05-030',
        '1-07-05-020' => '05-020',
        '1-07-06-010' => '06-010',
        '1-07-07-020' => '07-020',
        '1-07-07-010' => '07-010',
        '1-07-05-070' => '05-070',
        '1-07-05-990' => '05-990',
        '1-07-05-090' => '05-090',
        '1-07-05-080' => '05-080',
        '1-07-05-100' => '05-100',
        '1-09-01-020' => '01-020',
        '1-07-99-990' => '99-990',
        '1-07-05-110' => '05-110',
        '5-02-03-080' => '03-080',
        '1-07-05-130' => '05-130',
        '1-07-05-140' => '05-140',
        '1-07-06-040' => '06-040',
        '5-02-03-990' => '03-990',
        '5-02-03-010' => '03-010',
        '5-02-99-990' => '99-990',
        '5-02-99-020' => '99-020'
    ];
    $icsSub = [
        '1-07-05-030' => '223',
        '1-07-05-020' => '221',
        '1-07-06-010' => '241',
        '1-07-07-020' => '224',
        '1-07-07-010' => '222',
        '1-07-05-070' => '229',
        '1-07-05-990' => '240',
        '1-07-05-090' => '231',
        '1-07-05-080' => '230',
        '1-07-05-100' => '234',
        '1-09-01-020' => '323',
        '1-07-99-990' => '250',
        '1-07-05-110' => '233',
        '5-02-03-080' => '760',
        '1-07-05-130' => '235',
        '1-07-05-140' => '236',
        '1-07-06-040' => '244',
        '5-02-03-990' => '878',
        '5-02-03-010' => '755',
        '5-02-99-990' => '779',
        '5-02-99-020' => '781'
    ];

    $seqLen = 0;
    $sub = '';
    $prefix = '';
    if ($category === 'PAR') {
        // Special non-year selection: Found at Station (FS)
        $prefix = $isFoundAtStation ? 'RFS' : ('R' . $year);
        $seqLen = 4;
        $sub = $parSub[$accountCode] ?? '';
    } elseif ($category === 'ICS') {
        $prefix = $isFoundAtStation ? 'RFS' : ('R' . substr($year, -2));
        $seqLen = 3;
        $sub = $icsSub[$accountCode] ?? '';
    } else {
        return json_response(422, 'Invalid category.');
    }

    if ($sub === '') {
        return json_response(422, 'Account code is not mapped for this category.');
    }

    $like = $prefix . '-' . $sub . '-%-' . $deptCode;
    $stmt = mysqli_prepare(
        $conn,
        "SELECT par_number
           FROM (
                SELECT par_number FROM return_to_stock WHERE par_number LIKE ?
                UNION ALL
                SELECT par_number FROM unserviceable_items WHERE par_number LIKE ?
           ) AS t
          ORDER BY par_number DESC
          LIMIT 1"
    );
    if (!$stmt) {
        return json_response(500, 'Unable to prepare generator query.');
    }
    mysqli_stmt_bind_param($stmt, 'ss', $like, $like);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $last = '';
    if ($res && mysqli_num_rows($res) > 0) {
        $row = mysqli_fetch_assoc($res);
        $last = isset($row['par_number']) ? (string)$row['par_number'] : '';
    }
    mysqli_stmt_close($stmt);

    $seq = 1;
    if (trim($last) !== '') {
        $parts = explode('-', $last);
        $idx = count($parts) - 2;
        if ($idx >= 0) {
            $seq = ((int)($parts[$idx] ?? 0)) + 1;
        }
    }
    $seqStr = str_pad((string)$seq, $seqLen, '0', STR_PAD_LEFT);
    $propertyNumber = $prefix . '-' . $sub . '-' . $seqStr . '-' . $deptCode;

    return json_response(200, 'OK', ['property_number' => $propertyNumber]);
}

// Generate Property Number (admin/add-item.php)
// Formats:
// - PAR: YYYY-SUBACCOUNT-0000-DEPT
// - ICS: YY-ICS_SUB-000-DEPT
// Notes:
// - Checks duplication in the correct fund table only:
//   - GENERAL FUND => par_gen_fund.par_number
//   - SEF => property_sef.property_number

// Shared helpers for property number generation.
// IMPORTANT: must be defined outside request-specific endpoints so that
// generate_bundle_property_numbers can call the same generator.
if (!function_exists('gso_propnum_maps')) {
    function gso_propnum_maps() {
        $parSub = [
            '1-07-05-030' => '05-030',
            '1-07-05-020' => '05-020',
            '1-07-06-010' => '06-010',
            '1-07-07-020' => '07-020',
            '1-07-07-010' => '07-010',
            '1-07-05-070' => '05-070',
            '1-07-05-990' => '05-990',
            '1-07-05-090' => '05-090',
            '1-07-05-080' => '05-080',
            '1-07-05-100' => '05-100',
            '1-09-01-020' => '01-020',
            '1-07-99-990' => '99-990',
            '1-07-05-110' => '05-110',
            '5-02-03-080' => '03-080',
            '1-07-05-130' => '05-130',
            '1-07-05-140' => '05-140',
            '1-07-06-040' => '06-040',
            '5-02-03-990' => '03-990',
            '5-02-03-010' => '03-010',
            '5-02-99-990' => '99-990',
            '5-02-99-020' => '99-020',
        ];
        $icsSub = [
            '1-07-05-030' => '223',
            '1-07-05-020' => '221',
            '1-07-06-010' => '241',
            '1-07-07-020' => '224',
            '1-07-07-010' => '222',
            '1-07-05-070' => '229',
            '1-07-05-990' => '240',
            '1-07-05-090' => '231',
            '1-07-05-080' => '230',
            '1-07-05-100' => '234',
            '1-09-01-020' => '323',
            '1-07-99-990' => '250',
            '1-07-05-110' => '233',
            '5-02-03-080' => '760',
            '1-07-05-130' => '235',
            '1-07-05-140' => '236',
            '1-07-06-040' => '244',
            '5-02-03-990' => '878',
            '5-02-03-010' => '755',
            '5-02-99-990' => '779',
            '5-02-99-020' => '781',
        ];
        $deptMap = [
            '04' => '04',
            '47' => '47',
            '55' => '55',
        ];
        return [$parSub, $icsSub, $deptMap];
    }
}

if (!function_exists('gso_propnum_is_sef')) {
    function gso_propnum_is_sef($fundRaw) {
        $f = strtoupper(trim((string)$fundRaw));
        return in_array($f, ['SEF', 'SF', 'SPECIAL EDUCATION FUND'], true);
    }
}

if (!function_exists('gso_propnum_table_col')) {
    function gso_propnum_table_col($isSef) {
        if ($isSef) { return ['property_sef', 'property_number']; }
        return ['par_gen_fund', 'par_number'];
    }
}

if (!function_exists('gso_propnum_table_col_for_fund')) {
    function gso_propnum_table_col_for_fund($fundRaw) {
        $fund = strtoupper(trim((string)$fundRaw));
        if ($fund === 'DONATION') { return ['donation', 'property_number']; }
        return gso_propnum_table_col(gso_propnum_is_sef($fund));
    }
}

if (!function_exists('gso_propnum_exists')) {
    function gso_propnum_exists($conn, $table, $col, $candidate) {
        $candidate = strtoupper(trim((string)$candidate));
        if ($candidate === '') {
            return false;
        }

        $checks = [
            ['table' => 'new_purchase', 'col' => 'property_number'],
            ['table' => 'new_purchase_history', 'col' => 'par_number'],
            ['table' => 'new_bundle_purchase', 'col' => 'property_number'],
            ['table' => 'new_bundle_purchase', 'col' => 'bundle_with'],
            ['table' => 'par_gen_fund', 'col' => 'par_number'],
            ['table' => 'property_sef', 'col' => 'property_number'],
            ['table' => 'trust_fund', 'col' => 'property_number'],
            ['table' => 'donation', 'col' => 'property_number'],
        ];

        $table = preg_replace('/[^A-Za-z0-9_]/', '', (string)$table);
        $col = preg_replace('/[^A-Za-z0-9_]/', '', (string)$col);
        if ($table !== '' && $col !== '') {
            $alreadyIncluded = false;
            foreach ($checks as $check) {
                if ($check['table'] === $table && $check['col'] === $col) {
                    $alreadyIncluded = true;
                    break;
                }
            }
            if (!$alreadyIncluded) {
                $checks[] = ['table' => $table, 'col' => $col];
            }
        }

        $sqlParts = [];
        $types = '';
        $params = [];
        foreach ($checks as $check) {
            $sqlParts[] = "SELECT 1 FROM {$check['table']} WHERE UPPER(TRIM(COALESCE({$check['col']}, ''))) = ?";
            $types .= 's';
            $params[] = $candidate;
        }

        $sql = 'SELECT 1 FROM (' . implode(' UNION ALL ', $sqlParts) . ') AS property_matches LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return true;
        }
        gso_stmt_bind_params($stmt, $types, $params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $exists = $result && mysqli_num_rows($result) > 0;
        mysqli_stmt_close($stmt);
        return $exists;
    }
}

if (!function_exists('gso_generate_one_property_number')) {
    function gso_generate_one_property_number($conn, $category, $year, $accountCode, $dept, $fund, $exclude = []) {
        $category = strtoupper(trim((string)$category));
        $year = trim((string)$year);
        $accountCode = strtoupper(trim((string)$accountCode));
        $dept = trim((string)$dept);
        $fund = trim((string)$fund);

        if ($category === '' || $year === '' || $accountCode === '' || $dept === '' || $fund === '') {
            return ['ok' => false, 'error' => 'Missing required inputs.'];
        }
        if (in_array(strtoupper($fund), ['TRUST FUND', 'DONATION'], true)) {
            return ['ok' => false, 'error' => 'Property number is not generated for TRUST FUND or DONATION.'];
        }

        [$parSub, $icsSub, $deptMap] = gso_propnum_maps();
        $deptCode = $deptMap[$dept] ?? $dept;

        [$table, $col] = gso_propnum_table_col_for_fund($fund);

        $yearShort = substr($year, -2);
        $sub = '';
        $seqLen = 0;
        $prefix = '';

        if ($category === 'PAR') {
            $prefix = $year;
            $sub = $parSub[$accountCode] ?? '';
            $seqLen = 4;
        } elseif ($category === 'ICS') {
            $prefix = $yearShort;
            $sub = $icsSub[$accountCode] ?? '';
            $seqLen = 3;
        } else {
            return ['ok' => false, 'error' => 'Invalid category.'];
        }

        if ($sub === '') {
            if (strtoupper($fund) === 'TRUST FUND' && preg_match('/^[0-9-]{1,18}$/', $accountCode)) {
                $sub = $accountCode;
            } else {
                return ['ok' => false, 'error' => 'Account code is not mapped for this category.'];
            }
        }

        $pattern = $prefix . '-' . $sub . '-%-' . $deptCode;

        $stmt = mysqli_prepare($conn, "SELECT {$col} FROM {$table} WHERE {$col} LIKE ? ORDER BY {$col} DESC LIMIT 1");
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Unable to prepare generator query.'];
        }
        mysqli_stmt_bind_param($stmt, 's', $pattern);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $last = '';
        if ($res && mysqli_num_rows($res) > 0) {
            $row = mysqli_fetch_assoc($res);
            $last = isset($row[$col]) ? (string)$row[$col] : '';
        }
        mysqli_stmt_close($stmt);

        $seq = 1;
        if (trim($last) !== '') {
            $parts = explode('-', $last);
            $idx = count($parts) - 2;
            if ($idx >= 0) {
                $seq = ((int)($parts[$idx] ?? 0)) + 1;
            }
        }

        $excludeSet = [];
        if (is_array($exclude)) {
            foreach ($exclude as $x) {
                $k = strtoupper(trim((string)$x));
                if ($k !== '') { $excludeSet[$k] = true; }
            }
        }

        $guard = 0;
        while ($guard < 50000) {
            $seqStr = str_pad((string)$seq, $seqLen, '0', STR_PAD_LEFT);
            $candidate = $prefix . '-' . $sub . '-' . $seqStr . '-' . $deptCode;
            $candKey = strtoupper($candidate);
            if (!isset($excludeSet[$candKey]) && !gso_propnum_exists($conn, $table, $col, $candidate)) {
                return ['ok' => true, 'property_number' => $candidate, 'table' => $table, 'col' => $col];
            }
            $seq++;
            $guard++;
        }

        return ['ok' => false, 'error' => 'Unable to allocate a unique property number.'];
    }
}

if (isset($_POST['generate_property_number'])) {
    if (!isset($_SESSION['alogin'])) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        return false;
    }

    if (!function_exists('gso_propnum_maps')) {
        function gso_propnum_maps() {
            $parSub = [
                '1-07-05-030' => '05-030',
                '1-07-05-020' => '05-020',
                '1-07-06-010' => '06-010',
                '1-07-07-020' => '07-020',
                '1-07-07-010' => '07-010',
                '1-07-05-070' => '05-070',
                '1-07-05-990' => '05-990',
                '1-07-05-090' => '05-090',
                '1-07-05-080' => '05-080',
                '1-07-05-100' => '05-100',
                '1-09-01-020' => '01-020',
                '1-07-99-990' => '99-990',
                '1-07-05-110' => '05-110',
                '5-02-03-080' => '03-080',
                '1-07-05-130' => '05-130',
                '1-07-05-140' => '05-140',
                '1-07-06-040' => '06-040',
                '5-02-03-990' => '03-990',
                '5-02-03-010' => '03-010',
                '5-02-99-990' => '99-990',
                '5-02-99-020' => '99-020'
            ];
            $icsSub = [
                '1-07-05-030' => '223',
                '1-07-05-020' => '221',
                '1-07-06-010' => '241',
                '1-07-07-020' => '224',
                '1-07-07-010' => '222',
                '1-07-05-070' => '229',
                '1-07-05-990' => '240',
                '1-07-05-090' => '231',
                '1-07-05-080' => '230',
                '1-07-05-100' => '234',
                '1-09-01-020' => '323',
                '1-07-99-990' => '250',
                '1-07-05-110' => '233',
                '5-02-03-080' => '760',
                '1-07-05-130' => '235',
                '1-07-05-140' => '236',
                '1-07-06-040' => '244',
                '5-02-03-990' => '878',
                '5-02-03-010' => '755',
                '5-02-99-990' => '779',
                '5-02-99-020' => '781'
            ];
            $deptMap = [
                '04' => '04',
                '47' => '47',
                '55' => '55',
            ];
            return [$parSub, $icsSub, $deptMap];
        }
    }

    if (!function_exists('gso_propnum_is_sef')) {
        function gso_propnum_is_sef($fundRaw) {
            $f = strtoupper(trim((string)$fundRaw));
            return in_array($f, ['SEF', 'SF', 'SPECIAL EDUCATION FUND'], true);
        }
    }

    if (!function_exists('gso_propnum_table_col')) {
        function gso_propnum_table_col($isSef) {
            if ($isSef) { return ['property_sef', 'property_number']; }
            return ['par_gen_fund', 'par_number'];
        }
    }

    if (!function_exists('gso_propnum_exists')) {
        function gso_propnum_exists($conn, $table, $col, $candidate) {
            $cand = mysqli_real_escape_string($conn, (string)$candidate);
            $q = mysqli_query($conn, "SELECT 1 FROM {$table} WHERE {$col}='{$cand}' LIMIT 1");
            return ($q && mysqli_num_rows($q) > 0);
        }
    }

    if (!function_exists('gso_generate_one_property_number')) {
        function gso_generate_one_property_number($conn, $category, $year, $accountCode, $dept, $fund, $exclude = []) {
            $category = strtoupper(trim((string)$category));
            $year = trim((string)$year);
            $accountCode = strtoupper(trim((string)$accountCode));
            $dept = trim((string)$dept);
            $fund = trim((string)$fund);

            if ($category === '' || $year === '' || $accountCode === '' || $dept === '' || $fund === '') {
                return ['ok' => false, 'error' => 'Missing required inputs.'];
            }
            if (in_array(strtoupper($fund), ['TRUST FUND', 'DONATION'], true)) {
                return ['ok' => false, 'error' => 'Property number is not generated for TRUST FUND or DONATION.'];
            }

            [$parSub, $icsSub, $deptMap] = gso_propnum_maps();
            $deptCode = $deptMap[$dept] ?? $dept;

            $isSef = gso_propnum_is_sef($fund);
            [$table, $col] = gso_propnum_table_col($isSef);

            $yearShort = substr($year, -2);
            $sub = '';
            $seqLen = 0;
            $prefix = '';

            if ($category === 'PAR') {
                $prefix = $year;
                $sub = $parSub[$accountCode] ?? '';
                $seqLen = 4;
            } elseif ($category === 'ICS') {
                $prefix = $yearShort;
                $sub = $icsSub[$accountCode] ?? '';
                $seqLen = 3;
            } else {
                return ['ok' => false, 'error' => 'Invalid category.'];
            }

            if ($sub === '') {
                if (strtoupper($fund) === 'TRUST FUND' && preg_match('/^[0-9-]{1,18}$/', $accountCode)) {
                    $sub = $accountCode;
                } else {
                    return ['ok' => false, 'error' => 'Account code is not mapped for this category.'];
                }
            }

            $pattern = $prefix . '-' . $sub . '-%-' . $deptCode;

            $stmt = mysqli_prepare($conn, "SELECT {$col} FROM {$table} WHERE {$col} LIKE ? ORDER BY {$col} DESC LIMIT 1");
            if (!$stmt) {
                return ['ok' => false, 'error' => 'Unable to prepare generator query.'];
            }
            mysqli_stmt_bind_param($stmt, 's', $pattern);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $last = '';
            if ($res && mysqli_num_rows($res) > 0) {
                $row = mysqli_fetch_assoc($res);
                $last = isset($row[$col]) ? (string)$row[$col] : '';
            }
            mysqli_stmt_close($stmt);

            $seq = 1;
            if (trim($last) !== '') {
                $parts = explode('-', $last);
                $idx = count($parts) - 2;
                if ($idx >= 0) {
                    $seq = ((int)($parts[$idx] ?? 0)) + 1;
                }
            }

            // Normalize exclude set
            $excludeSet = [];
            if (is_array($exclude)) {
                foreach ($exclude as $x) {
                    $k = strtoupper(trim((string)$x));
                    if ($k !== '') { $excludeSet[$k] = true; }
                }
            }

            $guard = 0;
            while ($guard < 50000) {
                $seqStr = str_pad((string)$seq, $seqLen, '0', STR_PAD_LEFT);
                $candidate = $prefix . '-' . $sub . '-' . $seqStr . '-' . $deptCode;
                $candKey = strtoupper($candidate);
                if (!isset($excludeSet[$candKey]) && !gso_propnum_exists($conn, $table, $col, $candidate)) {
                    return ['ok' => true, 'property_number' => $candidate, 'table' => $table, 'col' => $col];
                }
                $seq++;
                $guard++;
            }

            return ['ok' => false, 'error' => 'Unable to allocate a unique property number.'];
        }
    }

    $category = $_POST['category'] ?? '';
    $year = $_POST['year'] ?? '';
    $accountCode = $_POST['account_code'] ?? '';
    $dept = $_POST['dept'] ?? '';
    $fund = $_POST['fund'] ?? '';

    $exclude = [];
    if (isset($_POST['exclude'])) {
        if (is_array($_POST['exclude'])) {
            $exclude = $_POST['exclude'];
        } else {
            $raw = (string)$_POST['exclude'];
            $exclude = preg_split('/\s*,\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY);
        }
    }

    $gen = gso_generate_one_property_number($conn, $category, $year, $accountCode, $dept, $fund, $exclude);
    if (!isset($gen['ok']) || !$gen['ok']) {
        echo json_encode(['success' => false, 'error' => $gen['error'] ?? 'Failed to generate property number.']);
        return false;
    }
    echo json_encode(['success' => true, 'pr_number' => $gen['property_number']]);
    return false;
}

// Generate Bundle Property Numbers (admin/add-item.php)
// - Generates unique numbers for each bundle row, using the row category (PAR/ICS)
// - Inherits year/account_code/dept/fund from the parent item
if (isset($_POST['generate_bundle_property_numbers'])) {
    if (!isset($_SESSION['alogin'])) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        return false;
    }

    $year = $_POST['year'] ?? '';
    $accountCode = $_POST['account_code'] ?? '';
    $dept = $_POST['dept'] ?? '';
    $fund = $_POST['fund'] ?? '';
    $categories = $_POST['categories'] ?? [];

    if (!is_array($categories) || count($categories) < 1) {
        echo json_encode(['success' => false, 'error' => 'Missing bundle categories.']);
        return false;
    }

    $exclude = [];
    if (isset($_POST['exclude'])) {
        if (is_array($_POST['exclude'])) {
            $exclude = $_POST['exclude'];
        } else {
            $raw = (string)$_POST['exclude'];
            $exclude = preg_split('/\s*,\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY);
        }
    }
    // Keep numbers unique within this request too
    $localExclude = [];
    foreach ($exclude as $x) {
        $k = strtoupper(trim((string)$x));
        if ($k !== '') { $localExclude[$k] = true; }
    }

    $out = [];
    foreach ($categories as $cat) {
        $cat = strtoupper(trim((string)$cat));
        if ($cat === '') {
            $out[] = '';
            continue;
        }
        $gen = gso_generate_one_property_number($conn, $cat, $year, $accountCode, $dept, $fund, array_keys($localExclude));
        if (!isset($gen['ok']) || !$gen['ok']) {
            echo json_encode(['success' => false, 'error' => $gen['error'] ?? 'Failed to generate bundle property numbers.']);
            return false;
        }
        $num = (string)$gen['property_number'];
        $out[] = $num;
        $localExclude[strtoupper($num)] = true;
    }

    echo json_encode(['success' => true, 'numbers' => $out]);
    return false;
}

if (isset($_POST['manual_return_item'])) {
    if (!isset($_SESSION['alogin'])) {
        return json_response(401, 'Unauthorized');
    }

    if (!function_exists('gso_id_is_auto_increment')) {
        function gso_id_is_auto_increment($conn, $table) {
            $table = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$table);
            if ($table === '') { return true; }
            $q = mysqli_query($conn, "SHOW COLUMNS FROM {$table} LIKE 'id'");
            if ($q && ($row = mysqli_fetch_assoc($q))) {
                return stripos((string)($row['Extra'] ?? ''), 'auto_increment') !== false;
            }
            // If the column doesn't exist or can't be inspected, assume we don't need an explicit id.
            return true;
        }
    }

    if (!function_exists('gso_next_staging_id')) {
        // Keep ids unique across return_to_stock + unserviceable_items for older DBs.
        function gso_next_staging_id($conn) {
            $q = mysqli_query($conn, "
                SELECT GREATEST(
                    COALESCE((SELECT MAX(id) FROM return_to_stock), 0),
                    COALESCE((SELECT MAX(id) FROM unserviceable_items), 0)
                ) + 1 AS next_id
            ");
            if ($q && ($r = mysqli_fetch_assoc($q))) {
                return (int)($r['next_id'] ?? 1);
            }
            return 1;
        }
    }

    if (!function_exists('gso_par_exists_in_staging')) {
        function gso_par_exists_in_staging($conn, $parNumber) {
            $parNumber = trim((string)$parNumber);
            if ($parNumber === '') { return false; }
            $stmt = mysqli_prepare(
                $conn,
                "SELECT 1 AS x
                   FROM (
                        SELECT par_number FROM return_to_stock WHERE par_number = ?
                        UNION ALL
                        SELECT par_number FROM unserviceable_items WHERE par_number = ?
                   ) AS t
                  LIMIT 1"
            );
            if (!$stmt) { return false; }
            mysqli_stmt_bind_param($stmt, 'ss', $parNumber, $parNumber);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $exists = ($res && mysqli_num_rows($res) > 0);
            mysqli_stmt_close($stmt);
            return $exists;
        }
    }

    if (!function_exists('gso_unique_par')) {
        function gso_unique_par($conn, $candidate) {
            $candidate = strtoupper(trim((string)$candidate));
            if ($candidate === '') { return ''; }
            if (!gso_par_exists_in_staging($conn, $candidate)) { return $candidate; }
            $suffix = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
            $alt = $candidate . '-' . $suffix;
            if (!gso_par_exists_in_staging($conn, $alt)) { return $alt; }
            // Last resort: include timestamp.
            return $candidate . '-' . date('His');
        }
    }

    $qty = isset($_POST['qty']) ? (int)$_POST['qty'] : 1;
    if ($qty < 1) { $qty = 1; }
    if ($qty > 50) { $qty = 50; }

    $fund = strtoupper(trim((string)($_POST['fund'] ?? '')));
    $category = strtoupper(trim((string)($_POST['category'] ?? '')));
    $accountCode = strtoupper(trim((string)($_POST['account_code'] ?? '')));
    $assetClass = strtoupper(trim((string)($_POST['item'] ?? '')));
    $model = strtoupper(trim((string)($_POST['model'] ?? '')));
    $description = strtoupper(trim((string)($_POST['description'] ?? '')));
    $unitValue = (float)($_POST['unit_value'] ?? 0);
    $dateAquired = trim((string)($_POST['date_aquired'] ?? ''));
    $supplier = strtoupper(trim((string)($_POST['supplier'] ?? '')));
    $parIcsNumber = strtoupper(trim((string)($_POST['par_ics_number'] ?? '')));
    $purchaseOrder = strtoupper(trim((string)($_POST['purchase_order'] ?? '')));
    $purchaseRequest = strtoupper(trim((string)($_POST['purchase_request'] ?? '')));
    $obrNumber = strtoupper(trim((string)($_POST['obr_number'] ?? '')));
    $jevNumber = strtoupper(trim((string)($_POST['jev_number'] ?? '')));
    $remarks = strtoupper(trim((string)($_POST['remarks'] ?? '')));

    $returnTypeRaw = strtoupper(trim((string)($_POST['return_type'] ?? '')));
    $deptId = trim((string)($_POST['end_user_department'] ?? ''));
    $empId = trim((string)($_POST['end_user_employee'] ?? ''));

    $isUnserviceable = ($returnTypeRaw === 'UNSERVICEABLE');
    $isServiceable = in_array($returnTypeRaw, ['RETURN TO STOCK', 'SERVICEABLE'], true);
    $isUnrepeated = $isUnserviceable && !empty($_POST['unrepeated']);

    // Property number(s): required.
    // - qty=1: accept a single property_number.
    // - qty>1: accept either legacy property_numbers[1..qty] OR a single textarea (property_number)
    //         containing comma/newline-separated values.
    $basePar = '';
    if (isset($_POST['property_number']) && !is_array($_POST['property_number'])) {
        $basePar = strtoupper(trim((string)$_POST['property_number']));
    }
    $propertyNumbers = (isset($_POST['property_numbers']) && is_array($_POST['property_numbers'])) ? $_POST['property_numbers'] : [];

    $propertyNumberList = [];
    if ($isUnrepeated) {
        // Unrepeated (UNSERVICEABLE only): always treat the input as a single property number.
        $parts = preg_split('/[\n\r,]+/', (string)$basePar) ?: [];
        $propertyNumberList[1] = strtoupper(trim((string)($parts[0] ?? '')));
    } elseif ($qty <= 1) {
        $propertyNumberList[1] = $basePar;
    } else {
        $rawList = [];
        if (!empty($propertyNumbers)) {
            for ($i = 1; $i <= $qty; $i++) {
                $rawList[] = (string)($propertyNumbers[$i] ?? ($propertyNumbers[(string)$i] ?? ''));
            }
        } else {
            $rawList = preg_split('/[\n\r,]+/', (string)$basePar) ?: [];
        }

        $clean = [];
        foreach ($rawList as $p) {
            $p = strtoupper(trim((string)$p));
            if ($p === '') { continue; }
            $clean[] = $p;
        }
        // Preserve order; remove duplicates early to avoid silent double-inserts.
        $uniq = [];
        foreach ($clean as $p2) {
            if (isset($uniq[$p2])) { continue; }
            $uniq[$p2] = true;
        }
        $vals = array_keys($uniq);
        for ($i = 1; $i <= $qty; $i++) {
            $propertyNumberList[$i] = $vals[$i - 1] ?? '';
        }
    }
    $serialArr = isset($_POST['serial']) && is_array($_POST['serial']) ? $_POST['serial'] : [];
    $serial2Arr = isset($_POST['serial2']) && is_array($_POST['serial2']) ? $_POST['serial2'] : [];

    $missing = [];
    if ($fund === '') { $missing[] = 'Fund'; }
    if ($category === '') { $missing[] = 'Category'; }
    if ($accountCode === '') { $missing[] = 'Account Code'; }
    if ($assetClass === '') { $missing[] = 'Asset Class'; }
    if ($model === '') { $missing[] = 'Model'; }
    if ($description === '') { $missing[] = 'Description'; }
    if ($dateAquired === '') { $missing[] = 'Date Acquired'; }
    if ($deptId === '') { $missing[] = 'Department'; }
    if ($empId === '') { $missing[] = 'Employee'; }

    if ($isUnrepeated || $qty <= 1) {
        if (($propertyNumberList[1] ?? '') === '') { $missing[] = 'Property Number'; }
    } else {
        $hasAll = true;
        for ($i = 1; $i <= $qty; $i++) {
            if (strtoupper(trim((string)($propertyNumberList[$i] ?? ''))) === '') { $hasAll = false; break; }
        }
        if (!$hasAll) { $missing[] = 'Property Numbers'; }
    }

    if (!$isUnserviceable && !$isServiceable) {
        $missing[] = 'Return Type';
    }

    if (count($missing) > 0) {
        return json_response(422, 'Please complete: ' . implode(', ', $missing) . '.');
    }

    $referenceNumber = generateReferenceNumber($conn, 'return_history', 'reference_number');

    $targetTable = $isUnserviceable ? 'unserviceable_items' : 'return_to_stock';
    $needsExplicitId = !gso_id_is_auto_increment($conn, $targetTable);

    // Prepared statements
    $itemColsNoId = "fund,category,item,model,description,serial_number,serial_number_2,par_number,unit_value,date_aquired,account_code,supplier,par_ics_number,purchase_order,purchase_request,obr_number,jev_number,remarks";
    $itemColsWithId = "id,{$itemColsNoId}";
    $placeholdersNoId = rtrim(str_repeat('?,', 18), ',');
    $placeholdersWithId = rtrim(str_repeat('?,', 19), ',');

    $sqlItem = $needsExplicitId
        ? "INSERT INTO {$targetTable} ({$itemColsWithId}) VALUES ({$placeholdersWithId})"
        : "INSERT INTO {$targetTable} ({$itemColsNoId}) VALUES ({$placeholdersNoId})";
    $stmtItem = mysqli_prepare($conn, $sqlItem);
    if (!$stmtItem) {
        return json_response(500, 'Unable to prepare item insert.');
    }

    $stmtReturnHist = mysqli_prepare(
        $conn,
        'INSERT INTO return_history(emp_id,dept_id,par_number,reference_number,status,category,created_at) VALUES (?,?,?,?,?,?,?)'
    );
    if (!$stmtReturnHist) {
        mysqli_stmt_close($stmtItem);
        return json_response(500, 'Unable to prepare return history insert.');
    }

    $stmtUnservHist = null;
    if ($isUnserviceable) {
        if (!function_exists('gso_column_exists')) {
            function gso_column_exists($conn, $table, $column) {
                $table = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$table);
                $column = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$column);
                if ($table === '' || $column === '') { return false; }
                $q = mysqli_query($conn, "SHOW COLUMNS FROM {$table} LIKE '{$column}'");
                return ($q && mysqli_num_rows($q) > 0);
            }
        }

        $hasUnservQtyCol = gso_column_exists($conn, 'unserviceable_items_history', 'qty');
        $stmtUnservHist = $hasUnservQtyCol
            ? mysqli_prepare($conn, 'INSERT INTO unserviceable_items_history(dept_id,par_number,reference_number,category,qty,created_at) VALUES (?,?,?,?,?,?)')
            : mysqli_prepare($conn, 'INSERT INTO unserviceable_items_history(dept_id,par_number,reference_number,category,created_at) VALUES (?,?,?,?,?)');
        if (!$stmtUnservHist) {
            mysqli_stmt_close($stmtReturnHist);
            mysqli_stmt_close($stmtItem);
            return json_response(500, 'Unable to prepare unserviceable history insert.');
        }
    }

    // If serviceable, also record the return event in the appropriate property history table.
    // This mirrors the older return-to-stock flow which marks property history with status=2.
    $stmtPropHist = null;
    if ($isServiceable) {
        $fundIsSef = in_array($fund, ['SEF', 'SPECIAL EDUCATION FUND'], true);
        if ($fundIsSef) {
            $stmtPropHist = mysqli_prepare(
                $conn,
                'INSERT INTO sef_property_history (emp_id,sch_id,property_number,reference_number,status,category,created_at) VALUES (?,?,?,?,?,?,?)'
            );
        } else {
            $stmtPropHist = mysqli_prepare(
                $conn,
                'INSERT INTO general_fund_property_history (emp_id,dept_id,par_number,reference_number,status,category,created_at) VALUES (?,?,?,?,?,?,?)'
            );
        }
        if (!$stmtPropHist) {
            if ($stmtUnservHist) { mysqli_stmt_close($stmtUnservHist); }
            mysqli_stmt_close($stmtReturnHist);
            mysqli_stmt_close($stmtItem);
            return json_response(500, 'Unable to prepare property history insert.');
        }
    }

    // 8 strings (fund..par_number), 1 double (unit_value), 9 strings (date_aquired..remarks)
    $typesItemNoId = str_repeat('s', 8) . 'd' . str_repeat('s', 9);
    $typesItemWithId = 'i' . $typesItemNoId;

    mysqli_begin_transaction($conn);
    try {
        $nextId = $needsExplicitId ? gso_next_staging_id($conn) : null;
        $statusReturned = 2;
        $todayStr = $today;

        $seenPar = [];
        $rowsToInsert = $isUnrepeated ? 1 : $qty;
        for ($i = 1; $i <= $rowsToInsert; $i++) {
            $rowSerial1 = $isUnrepeated ? '' : (isset($serialArr[$i]) ? (string)$serialArr[$i] : (isset($serialArr[(string)$i]) ? (string)$serialArr[(string)$i] : ''));
            $rowSerial2 = $isUnrepeated ? '' : (isset($serial2Arr[$i]) ? (string)$serial2Arr[$i] : (isset($serial2Arr[(string)$i]) ? (string)$serial2Arr[(string)$i] : ''));
            $serial1 = strtoupper(trim($rowSerial1));
            $serial2 = strtoupper(trim($rowSerial2));

            // Explicit property number is required. Do not generate MANUAL-* placeholders or suffixes.
            $parNumber = strtoupper(trim((string)($propertyNumberList[$i] ?? '')));
            if ($parNumber === '') {
                throw new Exception('Property number is required for row ' . $i . '.');
            }
            if (isset($seenPar[$parNumber])) {
                throw new Exception('Duplicate property number in this submission: ' . $parNumber);
            }
            $seenPar[$parNumber] = true;
            if (gso_par_exists_in_staging($conn, $parNumber)) {
                throw new Exception('Property number already exists in staging: ' . $parNumber);
            }

            if ($needsExplicitId) {
                $id = (int)$nextId;
                $nextId++;
                mysqli_stmt_bind_param(
                    $stmtItem,
                    $typesItemWithId,
                    $id,
                    $fund,
                    $category,
                    $assetClass,
                    $model,
                    $description,
                    $serial1,
                    $serial2,
                    $parNumber,
                    $unitValue,
                    $dateAquired,
                    $accountCode,
                    $supplier,
                    $parIcsNumber,
                    $purchaseOrder,
                    $purchaseRequest,
                    $obrNumber,
                    $jevNumber,
                    $remarks
                );
            } else {
                mysqli_stmt_bind_param(
                    $stmtItem,
                    $typesItemNoId,
                    $fund,
                    $category,
                    $assetClass,
                    $model,
                    $description,
                    $serial1,
                    $serial2,
                    $parNumber,
                    $unitValue,
                    $dateAquired,
                    $accountCode,
                    $supplier,
                    $parIcsNumber,
                    $purchaseOrder,
                    $purchaseRequest,
                    $obrNumber,
                    $jevNumber,
                    $remarks
                );
            }

            if (!mysqli_stmt_execute($stmtItem)) {
                throw new Exception('Item insert failed.');
            }

            mysqli_stmt_bind_param(
                $stmtReturnHist,
                'ssssiss',
                $empId,
                $deptId,
                $parNumber,
                $referenceNumber,
                $statusReturned,
                $category,
                $todayStr
            );
            if (!mysqli_stmt_execute($stmtReturnHist)) {
                throw new Exception('Return history insert failed.');
            }

            if ($stmtPropHist) {
                // Property history tracks where the item came from; mark as returned (status=2).
                mysqli_stmt_bind_param(
                    $stmtPropHist,
                    'ssssiss',
                    $empId,
                    $deptId,
                    $parNumber,
                    $referenceNumber,
                    $statusReturned,
                    $category,
                    $todayStr
                );
                if (!mysqli_stmt_execute($stmtPropHist)) {
                    throw new Exception('Property history insert failed.');
                }
            }

            if ($isUnserviceable && $stmtUnservHist) {
                if (isset($hasUnservQtyCol) && $hasUnservQtyCol) {
                    $histQty = $isUnrepeated ? $qty : 1;
                    mysqli_stmt_bind_param(
                        $stmtUnservHist,
                        'ssssis',
                        $deptId,
                        $parNumber,
                        $referenceNumber,
                        $category,
                        $histQty,
                        $todayStr
                    );
                } else {
                    mysqli_stmt_bind_param(
                        $stmtUnservHist,
                        'sssss',
                        $deptId,
                        $parNumber,
                        $referenceNumber,
                        $category,
                        $todayStr
                    );
                }
                if (!mysqli_stmt_execute($stmtUnservHist)) {
                    throw new Exception('Unserviceable history insert failed.');
                }
            }
        }

        mysqli_commit($conn);

        $loggedReturnType = $isUnserviceable ? 'Unserviceable' : 'Return to Stock';
        gso_log_activity($conn, $uid, $uip, "Added return item to {$loggedReturnType} (Reference: {$referenceNumber}, Qty: {$qty}).");

        if ($stmtUnservHist) { mysqli_stmt_close($stmtUnservHist); }
        if ($stmtPropHist) { mysqli_stmt_close($stmtPropHist); }
        mysqli_stmt_close($stmtReturnHist);
        mysqli_stmt_close($stmtItem);

        return json_response(200, 'Saved. Printing Property Return Slip...', [
            'reference_number' => $referenceNumber,
            'return_type' => $isUnserviceable ? 'UNSERVICEABLE' : 'RETURN TO STOCK',
            'qty' => $qty,
        ]);
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        if ($stmtUnservHist) { mysqli_stmt_close($stmtUnservHist); }
        if ($stmtPropHist) { mysqli_stmt_close($stmtPropHist); }
        mysqli_stmt_close($stmtReturnHist);
        mysqli_stmt_close($stmtItem);
        return json_response(500, 'Unable to save return item.');
    }
}
//fetch return_property details
if (isset($_GET['retid'])) {
    $retid = mysqli_real_escape_string($conn, $_GET['retid']);

    $sql = "SELECT * FROM return_item WHERE id = '$retid' ";
    $query = mysqli_query($conn, $sql);

    if (mysqli_num_rows($query) == 1) {
        $returnItem = mysqli_fetch_array($query);

        $res = [
            'status' => 200,
            'message' => 'Inventory id fetch successfully',
            'data' => $returnItem,
        ];
        echo json_encode($res);
        return false;
    } else {
        $res = [
            'status' => 422,
            'message' => 'No inventory id found',
        ];
        echo json_encode($res);
        return false;
    }
}
//to update i.c.s general fund information
if (isset($_POST['update_ics_inventory'])) {
    $icsId = mysqli_real_escape_string($conn, $_POST['icsId']);
    $par = mysqli_real_escape_string($conn, $_POST['par']);
    $dateaquired = mysqli_real_escape_string($conn, $_POST['edate']);
    $article = mysqli_real_escape_string($conn,strtoupper($_POST['paritem']));
    $brand = mysqli_real_escape_string($conn,strtoupper($_POST['brand']));
    $pardescription = mysqli_real_escape_string($conn,strtoupper($_POST['description']));
    $serial1 = mysqli_real_escape_string($conn,strtoupper($_POST['serial']));
    $serial2 = mysqli_real_escape_string($conn,strtoupper($_POST['serial2']));
    $unit = isset($_POST['unit']) ? mysqli_real_escape_string($conn, strtoupper(trim($_POST['unit']))) : '';
    $unitSet = ($unit !== '') ? ", unit='$unit'" : '';
    $unitvalue = mysqli_real_escape_string($conn, $_POST['uvalue']);
    $acode = mysqli_real_escape_string($conn,$_POST['acode']);
    $parsupplier = mysqli_real_escape_string($conn, strtoupper($_POST['supplier']));
    $po = mysqli_real_escape_string($conn,strtoupper($_POST['po']));
    $pr = mysqli_real_escape_string($conn,strtoupper($_POST['pr']));
    $obr = mysqli_real_escape_string($conn,strtoupper($_POST['obr']));
    $jev = mysqli_real_escape_string($conn,strtoupper($_POST['jev']));
    $actvty = "Updated the details of ".$par;

    $updatequery = "UPDATE ics_gen_fund SET date_aquired='$dateaquired', item ='$article', model='$brand', description ='$pardescription',
    serial_number='$serial1', serial_number_2='$serial2', unit_value='$unitvalue', account_code='$acode', supplier='$parsupplier',purchase_order='$po',purchase_request='$pr',obr_number='$obr',jev_number='$jev'
    WHERE icsgf_id = '$icsId' ";
    $results = mysqli_query($conn, $updatequery);

    if ($results) {
        mysqli_query($conn, "INSERT INTO activity_log(admin_id,ip_address,activity) VALUES('$uid','$uip','$actvty')");
        $res = [
            'status' => 200,
            'message' => 'Updated succesfully!',
        ];
        echo json_encode($res);
        return false;
    } else {
        $res = [
            'status' => 500,
            'message' => 'opps..something went wrong..',
        ];
        echo json_encode($res);
        return false;
    }
}
//to update p.a.r general fund information
if (isset($_POST['update_par'])) {
    header('Content-Type: application/json; charset=utf-8');

    $par = mysqli_real_escape_string($conn, $_POST['par']);
    $dateaquired = mysqli_real_escape_string($conn, $_POST['edate']);
    $article = mysqli_real_escape_string($conn,strtoupper($_POST['paritem']));
    $brand = mysqli_real_escape_string($conn,strtoupper($_POST['brand']));
    $pardescription = mysqli_real_escape_string($conn,strtoupper($_POST['description']));
    $serial1 = mysqli_real_escape_string($conn,strtoupper($_POST['serial']));
    $serial2 = mysqli_real_escape_string($conn,strtoupper($_POST['serial2']));
    $unitvalue = mysqli_real_escape_string($conn,$_POST['uvalue']);
    $sanitizedValue = floatval(preg_replace('/[^0-9.]/', '', $unitvalue));
    $acode = mysqli_real_escape_string($conn,$_POST['acode']);
    $parsupplier = mysqli_real_escape_string($conn, strtoupper($_POST['supplier']));
    $po = mysqli_real_escape_string($conn,strtoupper($_POST['po']));
    $pr = mysqli_real_escape_string($conn,strtoupper($_POST['pr']));
    $obr = mysqli_real_escape_string($conn,strtoupper($_POST['obr']));
    $jev = mysqli_real_escape_string($conn,strtoupper($_POST['jev']));
    $remarks = isset($_POST['remarks']) ? mysqli_real_escape_string($conn, strtoupper($_POST['remarks'])) : '';
    $par_ics_no = isset($_POST['par_ics_no']) ? mysqli_real_escape_string($conn, strtoupper($_POST['par_ics_no'])) : '';
    $par_new    = isset($_POST['par_new'])    ? mysqli_real_escape_string($conn, strtoupper(trim($_POST['par_new']))) : '';
    $renaming   = ($par_new !== '' && $par_new !== $par);
    $actvty = "Updated the details of ".$par;

    // Decide table: try GF first, else SEF
    $existsGf = mysqli_query($conn, "SELECT 1 FROM par_gen_fund WHERE par_number='$par' LIMIT 1");

    // Duplicate check when renaming property number
    if ($renaming) {
        $dupGf  = mysqli_query($conn, "SELECT 1 FROM par_gen_fund WHERE par_number='$par_new' LIMIT 1");
        $dupSef = mysqli_query($conn, "SELECT 1 FROM property_sef WHERE property_number='$par_new' LIMIT 1");
        if (($dupGf && mysqli_num_rows($dupGf) > 0) || ($dupSef && mysqli_num_rows($dupSef) > 0)) {
            echo json_encode(['status' => 422, 'message' => 'Property number already exists. Please try again.']);
            return false;
        }
    }

    if ($existsGf && mysqli_num_rows($existsGf) === 1) {
        $updatequery = "UPDATE par_gen_fund SET date_aquired='$dateaquired', item ='$article', model='$brand', description ='$pardescription',
        serial_number='$serial1', serial_number_2='$serial2', unit_value='$sanitizedValue', account_code='$acode', supplier='$parsupplier',purchase_order='$po',purchase_request='$pr',obr_number='$obr',jev_number='$jev', remarks='$remarks', par_ics_number='$par_ics_no'
        WHERE par_number = '$par' ";
    } else {
        $updatequery = "UPDATE property_sef SET date_aquired='$dateaquired', item ='$article', model='$brand', description ='$pardescription',
        serial_number='$serial1', serial_number_2='$serial2', unit_value='$sanitizedValue', account_code='$acode', supplier='$parsupplier',purchase_order='$po',purchase_request='$pr',obr_number='$obr',jev_number='$jev', remarks='$remarks', par_ics_number='$par_ics_no'
        WHERE property_number = '$par' ";
    }
    $results = mysqli_query($conn, $updatequery);

    if ($results) {
        // Cascade rename property number across all related tables
        if ($renaming) {
            if ($existsGf && mysqli_num_rows($existsGf) === 1) {
                mysqli_query($conn, "UPDATE par_gen_fund SET par_number='$par_new' WHERE par_number='$par'");
                mysqli_query($conn, "UPDATE general_fund_property_history SET par_number='$par_new' WHERE par_number='$par'");
                mysqli_query($conn, "UPDATE bundle_gen_fund SET property_number='$par_new' WHERE property_number='$par'");
                mysqli_query($conn, "UPDATE bundle_gen_fund SET bundle_with='$par_new' WHERE bundle_with='$par'");
            } else {
                mysqli_query($conn, "UPDATE property_sef SET property_number='$par_new' WHERE property_number='$par'");
                mysqli_query($conn, "UPDATE sef_property_history SET property_number='$par_new' WHERE property_number='$par'");
            }
        }
        mysqli_query($conn, "INSERT INTO activity_log(admin_id,ip_address,activity) VALUES('$uid','$uip','$actvty')");
        echo json_encode(['status' => 200, 'message' => 'Updated succesfully!']);
        return false;
    } else {
        echo json_encode(['status' => 500, 'message' => 'Error: '.mysqli_error($conn)]);
        return false;
    }
}

//fetch p.a.r and i.c.s general fund transfer detail
if (isset($_GET['propertyTransfer'])) {
    $par_number = mysqli_real_escape_string($conn, $_GET['propertyTransfer']);
    $sql = "SELECT e.emp_name as user,e.emp_id,e.department_code AS employee_department_code,p.par_number,p.category,p.item,g.par_number,g.emp_id,g.status,g.dept_id
    FROM general_fund_property_history AS g JOIN par_gen_fund AS p ON g.par_number = p.par_number JOIN employee AS e ON g.emp_id = e.emp_id
    WHERE g.par_number = '$par_number' AND g.status = '1' LIMIT 1";
    $query = mysqli_query($conn, $sql);

    if (mysqli_num_rows($query) == 1) {
        $returnProperty = mysqli_fetch_array($query);
        $departmentRow = gso_resolve_history_department($conn, $returnProperty['dept_id'] ?? '', $returnProperty['employee_department_code'] ?? '');
        $returnProperty['department_code'] = $departmentRow['department_code'] ?? '';
        $returnProperty['department_name'] = $departmentRow['department_name'] ?? '';

        $res = [
            'status' => 200,
            'message' => 'Property number fetch successfully',
            'data' => $returnProperty,
        ];
        echo json_encode($res);
        return false;
    } else {
        $res = [
            'status' => 422,
            'message' => 'No property number found',
        ];
        echo json_encode($res);
        return false;
    }
}

// Re-print info lookup: get category (status=1) and latest reference number for a given par number
if (isset($_GET['reprintInfo'])) {
    header('Content-Type: application/json');
    $par = mysqli_real_escape_string($conn, $_GET['reprintInfo']);
    if ($par === '') { echo json_encode(['status'=>422,'message'=>'Missing par number']); return false; }

    // Get category from active assignment (status=1)
    $cat = null; $ref = null;
    $q1 = mysqli_query($conn, "SELECT category, reference_number FROM general_fund_property_history WHERE par_number='$par' AND status='1' ORDER BY id DESC LIMIT 1");
    if ($q1 && mysqli_num_rows($q1) === 1) {
        $r1 = mysqli_fetch_assoc($q1);
        $cat = $r1['category'];
        if (!empty($r1['reference_number'])) { $ref = $r1['reference_number']; }
    } else {
        // Try SEF history using property_number
        $q1b = mysqli_query($conn, "SELECT category, reference_number FROM sef_property_history WHERE property_number='$par' AND status='1' ORDER BY id DESC LIMIT 1");
        if ($q1b && mysqli_num_rows($q1b) === 1) {
            $r1b = mysqli_fetch_assoc($q1b);
            $cat = $r1b['category'];
            if (!empty($r1b['reference_number'])) { $ref = $r1b['reference_number']; }
        }
    }
    if ($cat === null || $cat === '') { echo json_encode(['status'=>422,'message'=>'Active record not found for this property.']); return false; }

    // Prefer the latest items_user_history reference for this par
    $q2 = mysqli_query($conn, "SELECT reference_number FROM items_user_history WHERE par_number='$par' ORDER BY id DESC LIMIT 1");
    if ($q2 && mysqli_num_rows($q2) === 1) {
        $r2 = mysqli_fetch_assoc($q2);
        if (!empty($r2['reference_number'])) { $ref = $r2['reference_number']; }
    }

    if (empty($ref)) {
        echo json_encode(['status'=>422,'message'=>'No printable reference found for this item.']);
        return false;
    }

    echo json_encode([
        'status' => 200,
        'message' => 'OK',
        'data' => [
            'category' => $cat,
            'reference_number' => $ref
        ]
    ]);
    return false;
}
//fetch p.a.r and i.c.s general fund detail for return to stock
if (isset($_GET['returnToStock'])) {
    $par_number = mysqli_real_escape_string($conn, $_GET['returnToStock']);
    // Try GF first
    $sql = "SELECT e.emp_id,e.department_code AS employee_department_code,p.par_number,g.par_number,g.category,g.emp_id,g.status,g.dept_id FROM general_fund_property_history AS g 
    JOIN par_gen_fund AS p ON g.par_number = p.par_number 
    JOIN employee AS e ON g.emp_id = e.emp_id 
    WHERE g.par_number = '$par_number' AND g.status = '1' LIMIT 1";
    $query = mysqli_query($conn, $sql);
    if ($query && mysqli_num_rows($query) == 1) {
        $returnProperty = mysqli_fetch_array($query);
        $departmentRow = gso_resolve_history_department($conn, $returnProperty['dept_id'] ?? '', $returnProperty['employee_department_code'] ?? '');
        $returnProperty['department_code'] = $departmentRow['department_code'] ?? '';
        echo json_encode(['status'=>200,'message'=>'Property number fetch successfully','data'=>$returnProperty]);
        return false;
    }
    // Fallback to SEF (map to GF-like field names for frontend compatibility)
    $sql2 = "SELECT e.emp_id, s.property_number AS par_number, sh.property_number AS par_number_dup, sh.category, sh.emp_id, sh.status, sh.sch_id AS dept_id, d.department_code
             FROM sef_property_history AS sh
             JOIN property_sef AS s ON sh.property_number = s.property_number
             JOIN employee AS e ON sh.emp_id = e.emp_id
             JOIN department AS d ON sh.sch_id = d.department_code
             WHERE sh.property_number = '$par_number' AND sh.status = '1' LIMIT 1";
    $q2 = mysqli_query($conn, $sql2);
    if ($q2 && mysqli_num_rows($q2) == 1) {
        $row = mysqli_fetch_assoc($q2);
        $row['par_number'] = $row['par_number'];
        echo json_encode(['status'=>200,'message'=>'Property number fetch successfully','data'=>$row]);
        return false;
    }
    echo json_encode(['status'=>422,'message'=>'No property number found']);
    return false;
}
//fetch p.a.r and i.c.s general fund transfer detail from stock
if (isset($_GET['TransferFromStock'])) {
    $par_number = mysqli_real_escape_string($conn, $_GET['TransferFromStock']);
    // Try General Fund first: item currently in return_to_stock mapped by par_number
    $sql = "SELECT e.emp_name AS user, e.emp_id,
                    e.department_code AS employee_department_code,
                    p.par_number, p.category, p.item,
                    g.par_number AS hist_par_number, g.emp_id AS hist_emp_id, g.status, g.dept_id
            FROM general_fund_property_history AS g
            JOIN return_to_stock AS p ON g.par_number = p.par_number
            JOIN employee AS e ON g.emp_id = e.emp_id
            WHERE g.par_number = '$par_number' AND g.status = '2' LIMIT 1";
    $query = mysqli_query($conn, $sql);
    if ($query && mysqli_num_rows($query) == 1) {
        $returnProperty = mysqli_fetch_array($query);
        $departmentRow = gso_resolve_history_department($conn, $returnProperty['dept_id'] ?? '', $returnProperty['employee_department_code'] ?? '');
        $returnProperty['department_code'] = $departmentRow['department_code'] ?? '';
        $returnProperty['department_name'] = $departmentRow['department_name'] ?? '';
        echo json_encode(['status'=>200,'message'=>'Property number fetch successfully','data'=>$returnProperty]);
        return false;
    }
    // Fallback to SEF: when the item came from property_sef and is now in return_to_stock,
    // the history table is sef_property_history and uses property_number + sch_id.
    $sql2 = "SELECT e.emp_name AS user, e.emp_id,
                     r.par_number, r.category, r.item,
                     sh.property_number AS hist_par_number, sh.emp_id AS hist_emp_id, sh.status, sh.sch_id AS dept_id,
                     d.department_code, d.department_name
              FROM sef_property_history AS sh
              JOIN return_to_stock AS r ON sh.property_number = r.par_number
              JOIN employee AS e ON sh.emp_id = e.emp_id
              JOIN department AS d ON sh.sch_id = d.department_code
              WHERE sh.property_number = '$par_number' AND sh.status = '2' LIMIT 1";
    $q2 = mysqli_query($conn, $sql2);
    if ($q2 && mysqli_num_rows($q2) == 1) {
        $row = mysqli_fetch_assoc($q2);
        // Normalize field names for frontend: keep using par_number
        $row['par_number'] = $row['par_number'];
        echo json_encode(['status'=>200,'message'=>'Property number fetch successfully','data'=>$row]);
        return false;
    }
    echo json_encode(['status'=>422,'message'=>'No property number found']);
    return false;
}




// Bulk transfer of trust fund / donation inventory to a department
if (isset($_POST['bulkTransferFundInventory'])) {
    $fundKey = strtolower(trim((string)($_POST['fund_bulk'] ?? '')));
    $fundMap = [
        'trust' => [
            'table' => 'trust_fund',
            'history' => 'trust_fund_history',
            'label' => 'TRUST FUND',
        ],
        'donation' => [
            'table' => 'donation',
            'history' => 'donation_history',
            'label' => 'DONATION',
        ],
    ];

    if (!isset($fundMap[$fundKey])) {
        echo json_encode(['status' => 422, 'message' => 'Invalid fund selected.']);
        return false;
    }

    $selectedIds = json_decode(isset($_POST['selected_fund_ids']) ? $_POST['selected_fund_ids'] : '[]', true);
    if (!is_array($selectedIds) || !count($selectedIds)) {
        echo json_encode(['status' => 422, 'message' => 'No items selected for transfer.']);
        return false;
    }

    $fundIds = [];
    foreach ($selectedIds as $selectedId) {
        $fundId = (int)$selectedId;
        if ($fundId !== 0) {
            $fundIds[] = $fundId;
        }
    }
    $fundIds = array_values(array_unique($fundIds));

    if (!count($fundIds)) {
        echo json_encode(['status' => 422, 'message' => 'No valid items selected for transfer.']);
        return false;
    }

    $sourceTable = $fundMap[$fundKey]['table'];
    $sourceHistoryTable = $fundMap[$fundKey]['history'];
    $today = date('F j, Y');
    $referenceNumber = mysqli_real_escape_string($conn, generateReferenceNumber($conn, 'items_user_history', 'reference_number'));

    $icsCounters = [];
    $nextParIcs = function ($table, $category, $cacheKey) use ($conn, &$icsCounters) {
        $prefix = date('Ym') . '-' . (strpos(strtoupper($category), 'ICS') !== false ? 'I' : 'P');
        if (!isset($icsCounters[$cacheKey])) {
            $prefixEscaped = mysqli_real_escape_string($conn, $prefix);
            $result = mysqli_query($conn, "SELECT MAX(CAST(SUBSTRING(par_ics_number, LENGTH('$prefixEscaped') + 1) AS UNSIGNED)) AS max_counter FROM $table WHERE par_ics_number LIKE CONCAT('$prefixEscaped','%')");
            $icsCounters[$cacheKey] = ($result && ($row = mysqli_fetch_assoc($result)) && $row['max_counter'] !== null) ? (int)$row['max_counter'] : 0;
        }

        $tries = 0;
        do {
            $icsCounters[$cacheKey]++;
            $candidate = $prefix . sprintf('%04d', $icsCounters[$cacheKey]);
            $candidateEscaped = mysqli_real_escape_string($conn, $candidate);
            $exists = mysqli_num_rows(mysqli_query($conn, "SELECT 1 FROM $table WHERE par_ics_number='$candidateEscaped' LIMIT 1")) > 0;
        } while ($exists && ++$tries < 20000);

        return $candidate;
    };

    $movedCount = 0;

    $run = function ($sql, $mustAffect = false) use ($conn) {
        if (!mysqli_query($conn, $sql)) {
            throw new Exception(mysqli_error($conn));
        }
        if ($mustAffect && mysqli_affected_rows($conn) < 1) {
            throw new Exception('No property data was copied. Transfer cancelled.');
        }
    };

    mysqli_begin_transaction($conn);
    try {
        foreach ($fundIds as $fundId) {
            $fundId = (int)$fundId;

            $itemQuery = mysqli_query($conn, "SELECT * FROM {$sourceTable} WHERE id = {$fundId} LIMIT 1");
            $historyQuery = mysqli_query($conn, "SELECT * FROM {$sourceHistoryTable} WHERE id = {$fundId} AND status = 1 ORDER BY created_at DESC LIMIT 1");
            if (!$itemQuery || !$historyQuery || mysqli_num_rows($itemQuery) !== 1 || mysqli_num_rows($historyQuery) !== 1) {
                continue;
            }

            $itemRow = mysqli_fetch_assoc($itemQuery);
            $historyRow = mysqli_fetch_assoc($historyQuery);
            $category = trim((string)($itemRow['category'] ?? $historyRow['category'] ?? ''));
            $propertyNumber = trim((string)($itemRow['property_number'] ?? ''));
            if ($propertyNumber === '') {
                $propertyNumber = trim((string)($historyRow['par_number'] ?? ''));
            }
            if ($propertyNumber === '') {
                $propertyNumber = 'NPID:' . $fundId;
            }

            $propertyNumberEscaped = mysqli_real_escape_string($conn, $propertyNumber);
            $currentDept = trim((string)($historyRow['dept_id'] ?? ''));
            if ($currentDept === '') {
                throw new Exception('Missing department assignment for ' . $propertyNumber . '.');
            }

            $currentDeptEscaped = mysqli_real_escape_string($conn, $currentDept);
            $deptRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT agencies FROM department WHERE department_code='{$currentDeptEscaped}' LIMIT 1"));
            if (!$deptRow) {
                throw new Exception('Assigned department not found for ' . $propertyNumber . '.');
            }

            $destIsGF = strtoupper(trim((string)($deptRow['agencies'] ?? ''))) === 'CITY DEPARTMENT';
            $destTable = $destIsGF ? 'par_gen_fund' : 'property_sef';
            $destPropertyColumn = $destIsGF ? 'par_number' : 'property_number';
            $destHistoryTable = $destIsGF ? 'general_fund_property_history' : 'sef_property_history';
            $destHistoryDeptColumn = $destIsGF ? 'dept_id' : 'sch_id';
            $destFundLabel = $destIsGF ? 'GENERAL FUND' : 'SPECIAL EDUCATION FUND';
            $parIcsNumber = mysqli_real_escape_string($conn, $nextParIcs($destTable, $category, $destIsGF ? 'GF' : 'SEF'));
            $categoryEscaped = mysqli_real_escape_string($conn, $category);
            $destFundEscaped = mysqli_real_escape_string($conn, $destFundLabel);

            $run("UPDATE {$sourceHistoryTable} SET status='0', created_at='$today' WHERE id='{$fundId}' AND status='1'", true);

            $run("INSERT INTO {$destTable} (category,item,model,description,serial_number,serial_number_2,{$destPropertyColumn},unit,unit_value,date_aquired,account_code,fund,supplier,par_ics_number,purchase_order,purchase_request,obr_number,jev_number,remarks)
                SELECT
                    category,
                    item,
                    model,
                    description,
                    serial_number,
                    serial_number_2,
                    '{$propertyNumberEscaped}',
                    unit,
                    unit_value,
                    date_aquired,
                    account_code,
                    '{$destFundEscaped}',
                    supplier,
                    '{$parIcsNumber}',
                    purchase_order,
                    purchase_request,
                    obr_number,
                    jev_number,
                    remarks
                FROM {$sourceTable}
                WHERE id='{$fundId}'
                LIMIT 1", true);

            $run("DELETE FROM {$sourceTable} WHERE id='{$fundId}' LIMIT 1", true);

            $run("INSERT INTO {$destHistoryTable} (emp_id,{$destHistoryDeptColumn},{$destPropertyColumn},reference_number,status,category,created_at)
                VALUES (NULL,'{$currentDeptEscaped}','{$propertyNumberEscaped}','{$referenceNumber}','1','{$categoryEscaped}','{$today}')");

            $activityText = 'Transferred ' . $propertyNumber . ' from ' . $fundMap[$fundKey]['label'] . ' to records for department ' . $currentDept;
            $run("INSERT INTO activity_log (admin_id,ip_address,activity)
                VALUES ('$uid','$uip','" . mysqli_real_escape_string($conn, $activityText) . "')");

            $movedCount++;
        }

        if ($movedCount < 1) {
            throw new Exception('No active property records were found for transfer.');
        }

        mysqli_commit($conn);
        echo json_encode([
            'status' => 200,
            'message' => 'Transferred to records successfully.',
        ]);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(['status' => 500, 'message' => 'Transfer failed: ' . $e->getMessage()]);
    }
    return false;
}

// Bulk transfer of multiple par numbers
if (isset($_POST['bulkTransferPar'])) {
    $parList = json_decode(isset($_POST['selected_par_numbers']) ? $_POST['selected_par_numbers'] : '[]', true);
    if (!is_array($parList) || !count($parList)) {
        echo json_encode(['status' => 422, 'message' => 'No items selected for bulk transfer.']);
        return false;
    }

    $newDept         = mysqli_real_escape_string($conn, $_POST['dept_bulk']);
    $parEmpRaw       = isset($_POST['parEmp_bulk']) ? $_POST['parEmp_bulk'] : '';
    $referenceNumber = mysqli_real_escape_string($conn, generateReferenceNumber($conn, 'items_user_history', 'reference_number'));
    $reason          = isset($_POST['reason_bulk'])    && $_POST['reason_bulk']    !== '' ? "'" . mysqli_real_escape_string($conn, strtoupper($_POST['reason_bulk']))    . "'" : 'NULL';
    $condition       = isset($_POST['condition_bulk']) && $_POST['condition_bulk'] !== '' ? "'" . mysqli_real_escape_string($conn, strtoupper($_POST['condition_bulk'])) . "'" : 'NULL';
    $today           = date('F j, Y');

    // Handle "add new employee" sentinel
    if (strtoupper(trim($parEmpRaw)) === 'ADD_NEW_EMP') {
        $new_emp_name = mysqli_real_escape_string($conn, strtoupper(trim($_POST['new_emp_bulk'])));
        $position     = mysqli_real_escape_string($conn, strtoupper(trim($_POST['position_bulk'])));
        if (mysqli_num_rows(mysqli_query($conn, "SELECT 1 FROM employee WHERE UPPER(emp_name)='$new_emp_name' LIMIT 1")) > 0) {
            echo json_encode(['status' => 422, 'message' => 'Employee name already exists.']);
            return false;
        }
        $created = gso_create_employee_atomic($conn, $new_emp_name, $newDept, $position, 1, null);
        if (!isset($created['ok']) || !$created['ok']) {
            echo json_encode(['status' => 500, 'message' => $created['message'] ?? 'Error creating employee.']);
            return false;
        }
        $newUserId = (string)$created['emp_id'];
    } else {
        $newUserId = mysqli_real_escape_string($conn, $parEmpRaw);
    }

    // Resolve destination fund type once (CITY DEPARTMENT = GF, INSTITUTION = SEF)
    $deptRow  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT agencies FROM department WHERE department_code='$newDept' LIMIT 1"));
    $destIsGF = strtoupper(trim($deptRow['agencies'] ?? '')) === 'CITY DEPARTMENT';

    // Helper: generate next unique par_ics_number for a given table, keyed by fund to avoid counter collisions
    $icsCounters = [];
    $nextParIcs  = function ($table, $category, $cacheKey) use ($conn, &$icsCounters) {
        $prefix = date('Ym') . '-' . (strpos(strtoupper($category), 'ICS') !== false ? 'I' : 'P');
        if (!isset($icsCounters[$cacheKey])) {
            $p   = mysqli_real_escape_string($conn, $prefix);
            $res = mysqli_query($conn, "SELECT MAX(CAST(SUBSTRING(par_ics_number, LENGTH('$p') + 1) AS UNSIGNED)) AS m FROM $table WHERE par_ics_number LIKE CONCAT('$p','%')");
            $icsCounters[$cacheKey] = ($res && ($r = mysqli_fetch_assoc($res)) && $r['m'] !== null) ? (int)$r['m'] : 0;
        }
        $tries = 0;
        do {
            $icsCounters[$cacheKey]++;
            $candidate = $prefix . sprintf('%04d', $icsCounters[$cacheKey]);
            $c = mysqli_real_escape_string($conn, $candidate);
            $busy = (bool)mysqli_num_rows(mysqli_query($conn, "SELECT 1 FROM $table WHERE par_ics_number='$c' LIMIT 1"));
        } while ($busy && ++$tries < 20000);
        return $candidate;
    };

    // PTR counter for this batch
    $ptrPrefix  = date('ym') . '-';
    $ptrPrefEsc = mysqli_real_escape_string($conn, $ptrPrefix);
    $ptrRes     = mysqli_query($conn, "SELECT MAX(CAST(SUBSTRING_INDEX(ptr_number,'-',-1) AS UNSIGNED)) AS m FROM items_user_history WHERE ptr_number LIKE CONCAT('$ptrPrefEsc','%')");
    $ptrCounter = ($ptrRes && ($r = mysqli_fetch_assoc($ptrRes)) && $r['m'] !== null) ? (int)$r['m'] : 0;
    $usedPtrs   = [];
    $movedCount = 0;
    $run = function ($sql, $mustAffect = false) use ($conn) {
        if (!mysqli_query($conn, $sql)) {
            throw new Exception(mysqli_error($conn));
        }
        if ($mustAffect && mysqli_affected_rows($conn) < 1) {
            throw new Exception('No property data was copied. Transfer cancelled.');
        }
    };

    mysqli_begin_transaction($conn);
    try {
        foreach ($parList as $par) {
            $parSafe = mysqli_real_escape_string($conn, $par);

            // Determine source fund table
            $srcIsGF = true;
            $curr    = mysqli_query($conn, "SELECT emp_id, dept_id, category FROM general_fund_property_history WHERE par_number='$parSafe' AND status='1' LIMIT 1");
            if (!$curr || mysqli_num_rows($curr) !== 1) {
                $srcIsGF = false;
                $curr    = mysqli_query($conn, "SELECT emp_id, sch_id AS dept_id, category FROM sef_property_history WHERE property_number='$parSafe' AND status='1' LIMIT 1");
                if (!$curr || mysqli_num_rows($curr) !== 1) continue;
            }
            $currRow      = mysqli_fetch_assoc($curr);
            $category     = $currRow['category'];
            $prevEmpName  = mysqli_real_escape_string($conn, get_emp_name($conn, $currRow['emp_id']));
            $prevDeptName = mysqli_real_escape_string($conn, get_dept_name($conn, $currRow['dept_id']));

            // Deactivate current history
            if ($srcIsGF) {
                $run("UPDATE general_fund_property_history SET status='0', created_at='$today' WHERE par_number='$parSafe' AND status='1'", true);
            } else {
                $run("UPDATE sef_property_history SET status='0', created_at='$today' WHERE property_number='$parSafe' AND status='1'", true);
            }

            // Move physical data between fund tables when fund type changes
            if ($srcIsGF && !$destIsGF) {
                // GF → SEF: copy from par_gen_fund to property_sef, then remove from par_gen_fund
                $newIcs = mysqli_real_escape_string($conn, $nextParIcs('property_sef', $category, 'SEF'));
                $run("INSERT INTO property_sef (category,item,model,description,serial_number,serial_number_2,property_number,unit_value,date_aquired,account_code,fund,supplier,par_ics_number,purchase_order,purchase_request,obr_number,jev_number,remarks)
                    SELECT category,item,model,description,serial_number,serial_number_2,par_number,unit_value,date_aquired,account_code,'SPECIAL EDUCATION FUND',supplier,'$newIcs',purchase_order,purchase_request,obr_number,jev_number,remarks
                    FROM par_gen_fund WHERE par_number='$parSafe' LIMIT 1", true);
                $run("DELETE FROM par_gen_fund WHERE par_number='$parSafe'", true);
            } elseif (!$srcIsGF && $destIsGF) {
                // SEF → GF: copy from property_sef to par_gen_fund, then remove from property_sef
                $newIcs = mysqli_real_escape_string($conn, $nextParIcs('par_gen_fund', $category, 'GF'));
                $run("INSERT INTO par_gen_fund (category,item,model,description,serial_number,serial_number_2,par_number,unit_value,date_aquired,account_code,fund,supplier,par_ics_number,purchase_order,purchase_request,obr_number,jev_number,remarks)
                    SELECT category,item,model,description,serial_number,serial_number_2,property_number,unit_value,date_aquired,account_code,'GENERAL FUND',supplier,'$newIcs',purchase_order,purchase_request,obr_number,jev_number,remarks
                    FROM property_sef WHERE property_number='$parSafe' LIMIT 1", true);
                $run("DELETE FROM property_sef WHERE property_number='$parSafe'", true);
            } else {
                // Same fund: just refresh par_ics_number in the existing table
                $destTable = $destIsGF ? 'par_gen_fund' : 'property_sef';
                $destPnCol = $destIsGF ? 'par_number'   : 'property_number';
                $newIcs    = mysqli_real_escape_string($conn, $nextParIcs($destTable, $category, $destIsGF ? 'GF' : 'SEF'));
                $run("UPDATE $destTable SET par_ics_number='$newIcs' WHERE $destPnCol='$parSafe' LIMIT 1", true);
            }

            // Insert active history in the destination fund table
            if ($destIsGF) {
                $run("INSERT INTO general_fund_property_history (emp_id,dept_id,par_number,reference_number,status,category,created_at) VALUES ('$newUserId','$newDept','$parSafe','$referenceNumber','1','$category','$today')");
            } else {
                $run("INSERT INTO sef_property_history (emp_id,sch_id,property_number,reference_number,status,category,created_at) VALUES ('$newUserId','$newDept','$parSafe','$referenceNumber','1','$category','$today')");
            }

            // Generate unique PTR number for this item
            do {
                $ptrCounter++;
                $ptr       = $ptrPrefix . sprintf('%03d', $ptrCounter);
                $ptrEsc    = mysqli_real_escape_string($conn, $ptr);
                $ptrExists = !empty($usedPtrs[$ptr]) || (bool)mysqli_num_rows(mysqli_query($conn, "SELECT 1 FROM items_user_history WHERE ptr_number='$ptrEsc' LIMIT 1"));
            } while ($ptrExists);
            $usedPtrs[$ptr] = true;

            $run("INSERT INTO items_user_history (par_number,new_user,new_dept,previous_user,previous_dept,reason,unit_condition,reference_number,ptr_number) VALUES ('$parSafe','$newUserId','$newDept','$prevEmpName','$prevDeptName',$reason,$condition,'$referenceNumber','$ptrEsc')");
            $run("INSERT INTO activity_log (admin_id,ip_address,activity) VALUES ('$uid','$uip','" . mysqli_real_escape_string($conn, "Transferred $par to new user id $newUserId") . "')");
            $movedCount++;
        }

        if ($movedCount < 1) {
            throw new Exception('No active property records were found for transfer.');
        }

        mysqli_commit($conn);
        echo json_encode(['status' => 200, 'message' => 'Bulk transfer completed successfully.', 'data' => ['reference_number' => $referenceNumber]]);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(['status' => 500, 'message' => 'Bulk transfer failed: ' . $e->getMessage()]);
    }
    return false;
}
//to transfer property to new employee from stock, and to return back the data to the correct table (GF/SEF)
if (isset($_POST['parTransferFromStock'])) {
    $par = mysqli_real_escape_string($conn, $_POST['par_num']);
    $cuserid = mysqli_real_escape_string($conn, $_POST['cuser']);
    $cdeptid = mysqli_real_escape_string($conn, $_POST['cdept']);
    $parEmpRaw = isset($_POST['parEmp']) ? $_POST['parEmp'] : '';
    $parEmpUpper = strtoupper(trim($parEmpRaw));
    $newdept = mysqli_real_escape_string($conn, $_POST['dept']);
    $referenceNumber = mysqli_real_escape_string($conn, $_POST['refnum']);
    $category = mysqli_real_escape_string($conn, $_POST['category']);
    $reason = isset($_POST['reason']) ? (empty($_POST['reason']) ? 'NULL' : "'" . mysqli_real_escape_string($conn, strtoupper($_POST['reason'])) . "'") : 'NULL';
    $condition = isset($_POST['condition']) ? (empty($_POST['condition']) ? 'NULL' : "'" . mysqli_real_escape_string($conn, strtoupper($_POST['condition'])) . "'") : 'NULL';
    $status1 = 0; $status2 = 1;
    // If add new employee sentinel
    if ($parEmpUpper === 'ADD_NEW_EMP') {
        $new_emp_name = mysqli_real_escape_string($conn, strtoupper(trim($_POST['new_emp'])));
        $positionRaw = strtoupper(trim($_POST['position'] ?? ''));
        if ($positionRaw === '') { $positionRaw = 'N/A'; }
        $position = mysqli_real_escape_string($conn, $positionRaw);
        $dupCheck = mysqli_query($conn, "SELECT 1 FROM employee WHERE UPPER(emp_name)='$new_emp_name' LIMIT 1");
        if ($dupCheck && mysqli_num_rows($dupCheck) > 0) {
            echo json_encode(['status'=>422,'message'=>'Employee name already exists.']);
            return false;
        }
        $created = gso_create_employee_atomic($conn, $new_emp_name, $newdept, $position, 1, null);
        if(!isset($created['ok']) || !$created['ok']){
            echo json_encode(['status'=>500,'message'=>$created['message'] ?? 'Error creating employee.']);
            return false;
        }
        $newuser = (string)$created['emp_id'];
    } else {
        $newuser = mysqli_real_escape_string($conn, $parEmpRaw);
    }
    $actvty = "Transferred the $par to new user id $newuser";
    // Generate a PTR number for this transfer (single item)
    $ptrNumberSingle = generatePtrNumber($conn);
    // Resolve full names for previous user/department from provided IDs
    $prevEmpName  = mysqli_real_escape_string($conn, get_emp_name($conn, $cuserid));
    $prevDeptName = mysqli_real_escape_string($conn, get_dept_name($conn, $cdeptid));
    // Decide destination table based on the selected department's agencies (CITY DEPARTMENT => GF, INSTITUTION => SEF)
    $deptRow = null; $agencyVal = '';
    $deptQ = mysqli_query($conn, "SELECT agencies FROM department WHERE department_code='".mysqli_real_escape_string($conn,$newdept)."' LIMIT 1");
    if ($deptQ && mysqli_num_rows($deptQ)===1) { $deptRow = mysqli_fetch_assoc($deptQ); $agencyVal = strtoupper(trim($deptRow['agencies'] ?? '')); }
    $targetIsGF = in_array($agencyVal, ['CITY DEPARTMENT','1','GF','GENERAL FUND']);

    // Before inserting into destination, mark any active source history record as inactive (both GF and SEF variants defensively)
    $preUpdates = "UPDATE general_fund_property_history SET status='".$status1."', created_at='".$today."' WHERE par_number='".$par."' AND status='1';".
                 "UPDATE sef_property_history SET status='".$status1."', created_at='".$today."' WHERE property_number='".$par."' AND status='1';";

    if ($targetIsGF) {
        // Move into General Fund tables and create active GF history
        $sql = $preUpdates.
        "INSERT INTO general_fund_property_history (emp_id,dept_id,par_number,reference_number,status,category,created_at) VALUES ('".$newuser."','".$newdept."','".$par."','".$referenceNumber."','".$status2."','".$category."','".$today."');".
        "INSERT INTO items_user_history (par_number,new_user,new_dept,previous_user,previous_dept,reason,unit_condition,reference_number,ptr_number) VALUES ('".$par."','".$newuser."','".$newdept."','".$prevEmpName."','".$prevDeptName."',".$reason.",".$condition.",'".$referenceNumber."','".mysqli_real_escape_string($conn,$ptrNumberSingle)."');".
        // Map return_to_stock -> par_gen_fund (par_number columns match)
        "INSERT INTO par_gen_fund (category,item,model,description,serial_number,serial_number_2,par_number,unit_value,date_aquired,account_code,fund,supplier,par_ics_number,purchase_order,purchase_request,obr_number,jev_number,remarks)
            SELECT category,item,model,description,serial_number,serial_number_2,par_number,unit_value,date_aquired,account_code,'GENERAL FUND',supplier,par_ics_number,purchase_order,purchase_request,obr_number,jev_number,remarks FROM return_to_stock WHERE par_number='".$par."';".
        "DELETE FROM return_to_stock WHERE par_number='".$par."';";
    } else {
        // Move into SEF tables and create active SEF history
        $sql = $preUpdates.
        "INSERT INTO sef_property_history (emp_id,sch_id,property_number,reference_number,status,category,created_at) VALUES ('".$newuser."','".$newdept."','".$par."','".$referenceNumber."','".$status2."','".$category."','".$today."');".
        "INSERT INTO items_user_history (par_number,new_user,new_dept,previous_user,previous_dept,reason,unit_condition,reference_number,ptr_number) VALUES ('".$par."','".$newuser."','".$newdept."','".$prevEmpName."','".$prevDeptName."',".$reason.",".$condition.",'".$referenceNumber."','".mysqli_real_escape_string($conn,$ptrNumberSingle)."');".
        // Map return_to_stock -> property_sef (property_number receives par_number)
        "INSERT INTO property_sef (category,item,model,description,serial_number,serial_number_2,property_number,unit_value,date_aquired,account_code,fund,supplier,par_ics_number,purchase_order,purchase_request,obr_number,jev_number,remarks)
            SELECT category,item,model,description,serial_number,serial_number_2,par_number,unit_value,date_aquired,account_code,'SPECIAL EDUCATION FUND',supplier,par_ics_number,purchase_order,purchase_request,obr_number,jev_number,remarks FROM return_to_stock WHERE par_number='".$par."';".
        "DELETE FROM return_to_stock WHERE par_number='".$par."';";
    }
    $query = mysqli_multi_query($conn,$sql);
    if($query){
        mysqli_query($conn, "INSERT INTO activity_log(admin_id,ip_address,activity) VALUES('$uid','$uip','$actvty')");
        echo json_encode(['status'=>200,'message'=>'Transferred Successfully!']);
        return false;
    }
    echo json_encode(['status'=>500,'message'=>'Transfer failed: '.mysqli_error($conn)]);
    return false;
}

//add general fund property section
// ==========================================================
// New Purchase -> Records (move)
// - Moves selected rows out of:
//     new_purchase, new_purchase_history, new_bundle_purchase
//   into fund-specific tables:
//     GENERAL FUND -> par_gen_fund + general_fund_property_history + bundle_gen_fund
//     SEF          -> property_sef + sef_property_history + bundle_sef
//     TRUST FUND   -> trust_fund + trust_fund_history
//     DONATION     -> donation + donation_history
// - Implementation: INSERT INTO ... SELECT ... then DELETE
// ==========================================================

if (!function_exists('gso_get_table_columns')) {
    function gso_get_table_columns(mysqli $conn, string $table, bool $refresh = false): array {
        static $cache = [];

        $tableKey = strtolower(trim($table));
        if ($tableKey === '') {
            return [];
        }
        if ($refresh) {
            unset($cache[$tableKey]);
        }
        if (isset($cache[$tableKey])) {
            return $cache[$tableKey];
        }

        $safeTable = preg_replace('/[^A-Za-z0-9_]/', '', $tableKey);
        if ($safeTable === '') {
            $cache[$tableKey] = [];
            return $cache[$tableKey];
        }

        $cache[$tableKey] = [];
        $sql = "SHOW COLUMNS FROM `{$safeTable}`";
        if ($res = mysqli_query($conn, $sql)) {
            while ($row = mysqli_fetch_assoc($res)) {
                $field = strtolower(trim((string)($row['Field'] ?? '')));
                if ($field !== '') {
                    $cache[$tableKey][$field] = true;
                }
            }
            mysqli_free_result($res);
        }

        return $cache[$tableKey];
    }
}

if (!function_exists('gso_ensure_bundle_transfer_columns')) {
    function gso_ensure_bundle_transfer_columns(mysqli $conn, string $table): array {
        $safeTable = preg_replace('/[^A-Za-z0-9_]/', '', strtolower(trim($table)));
        if ($safeTable === '') {
            return [];
        }

        $definitions = [
            'category' => "VARCHAR(50) NULL DEFAULT NULL AFTER `bundle_with`",
            'unit' => "VARCHAR(50) NULL DEFAULT NULL AFTER `category`",
            'item' => "VARCHAR(255) NULL DEFAULT NULL AFTER `unit`",
            'model' => "VARCHAR(255) NULL DEFAULT NULL AFTER `item`",
            'description' => "TEXT NULL AFTER `model`",
            'serial_number' => "VARCHAR(255) NULL DEFAULT NULL AFTER `description`",
            'serial_number_2' => "VARCHAR(255) NULL DEFAULT NULL AFTER `serial_number`",
            'par_ics_number' => "VARCHAR(100) NULL DEFAULT NULL AFTER `serial_number_2`",
            'unit_value' => "DECIMAL(15,2) NULL DEFAULT NULL AFTER `par_ics_number`",
            'date_aquired' => "VARCHAR(32) NULL DEFAULT NULL AFTER `unit_value`",
            'account_code' => "VARCHAR(100) NULL DEFAULT NULL AFTER `date_aquired`",
            'fund' => "VARCHAR(100) NULL DEFAULT NULL AFTER `account_code`",
            'supplier' => "VARCHAR(255) NULL DEFAULT NULL AFTER `fund`",
            'purchase_order' => "VARCHAR(255) NULL DEFAULT NULL AFTER `supplier`",
            'purchase_request' => "VARCHAR(255) NULL DEFAULT NULL AFTER `purchase_order`",
            'obr_number' => "VARCHAR(255) NULL DEFAULT NULL AFTER `purchase_request`",
            'jev_number' => "VARCHAR(255) NULL DEFAULT NULL AFTER `obr_number`",
            'remarks' => "TEXT NULL AFTER `jev_number`",
        ];

        $columns = gso_get_table_columns($conn, $safeTable, true);
        foreach ($definitions as $column => $definition) {
            if (isset($columns[$column])) {
                continue;
            }

            $sql = "ALTER TABLE `{$safeTable}` ADD COLUMN `{$column}` {$definition}";
            if (!mysqli_query($conn, $sql)) {
                throw new RuntimeException('Unable to prepare bundle transfer columns for ' . $safeTable . ': ' . mysqli_error($conn));
            }
        }

        return gso_get_table_columns($conn, $safeTable, true);
    }
}

if (!function_exists('gso_build_bundle_transfer_insert_sql')) {
    function gso_build_bundle_transfer_insert_sql(mysqli $conn, string $table, string $fundName, string $placeholders): string {
        $columns = gso_ensure_bundle_transfer_columns($conn, $table);
        $fundNameEscaped = mysqli_real_escape_string($conn, $fundName);

        $insertColumns = ['dept_id', 'emp_id', 'property_number', 'bundle_with'];
        $selectColumns = [
            'CAST(d.department_code AS UNSIGNED)',
            'b.emp_id',
            'parent_np.property_number',
            'parent_np.property_number',
        ];

        $optionalColumns = [
            'category' => 'b.category',
            'unit' => 'b.unit',
            'item' => 'b.item',
            'model' => 'b.model',
            'description' => 'b.description',
            'serial_number' => 'b.serial_number',
            'serial_number_2' => 'b.serial_number_2',
            'par_ics_number' => 'b.par_ics_number',
            'unit_value' => 'parent_np.unit_value',
            'date_aquired' => 'parent_np.date_aquired',
            'account_code' => 'parent_np.account_code',
            'fund' => 'parent_np.fund',
            'supplier' => 'parent_np.supplier',
            'purchase_order' => 'parent_np.purchase_order',
            'purchase_request' => 'parent_np.purchase_request',
            'obr_number' => 'parent_np.obr_number',
            'jev_number' => 'parent_np.jev_number',
            'remarks' => 'parent_np.remarks',
        ];

        foreach ($optionalColumns as $column => $expression) {
            if (isset($columns[$column])) {
                $insertColumns[] = $column;
                $selectColumns[] = $expression;
            }
        }

        return "INSERT INTO {$table} (" . implode(', ', $insertColumns) . ")
                SELECT " . implode(', ', $selectColumns) . "
                FROM new_bundle_purchase b
                INNER JOIN department d ON d.dept_id = b.dept_id
                INNER JOIN new_purchase parent_np
                    ON parent_np.property_number = b.bundle_with
                   AND UPPER(TRIM(parent_np.fund))='" . $fundNameEscaped . "'
                   AND parent_np.id = (
                        SELECT np2.id
                        FROM new_purchase np2
                        WHERE np2.property_number = b.bundle_with
                          AND UPPER(TRIM(np2.fund))='" . $fundNameEscaped . "'
                        ORDER BY (COALESCE(np2.unit_value, 0) > 0) DESC, np2.id ASC
                        LIMIT 1
                   )
                WHERE UPPER(TRIM(parent_np.fund))='" . $fundNameEscaped . "'
                  AND (b.bundle_with IN ({$placeholders}) OR b.property_number IN ({$placeholders}))";
    }
}

if (isset($_POST['transfer_new_purchase_to_records'])) {
    header('Content-Type: application/json; charset=utf-8');

    if (!isset($_SESSION['alogin']) || trim((string)$_SESSION['alogin']) === '') {
        echo json_encode(['status'=>401,'message'=>'Unauthorized']);
        return false;
    }

    // One-time token (generated in admin/add-item.php)
    $token = (string)($_POST['token'] ?? '');
    if ($token === '' || !isset($_SESSION['form_tokens']['np_transfer'][$token])) {
        echo json_encode(['status'=>409,'message'=>'Invalid or expired transfer token. Please refresh the page.']);
        return false;
    }
    // Do not consume token yet; consume on successful transfer so users can retry after validation errors.
    if (isset($_SESSION['form_tokens']['np_transfer']) && is_array($_SESSION['form_tokens']['np_transfer'])) {
        foreach($_SESSION['form_tokens']['np_transfer'] as $tk=>$ts){
            if ((int)$ts < time() - 1800) { unset($_SESSION['form_tokens']['np_transfer'][$tk]); }
        }
    }

    $makeIn = function($n){ return implode(',', array_fill(0, (int)$n, '?')); };
    $bindParams = function($stmt, $types, &$vals){
        $args = [];
        $args[] = $stmt;
        $args[] = $types;
        foreach ($vals as $k => &$v) { $args[] = &$v; }
        return call_user_func_array('mysqli_stmt_bind_param', $args);
    };

    $transferAll = isset($_POST['transfer_all']) && ((string)$_POST['transfer_all'] === '1' || $_POST['transfer_all'] === 1 || $_POST['transfer_all'] === true);
    $raw = $_POST['property_numbers'] ?? [];
    if (is_string($raw)) { $raw = [$raw]; }
    if (!is_array($raw)) {
        echo json_encode(['status'=>422,'message'=>'Invalid property_numbers']);
        return false;
    }

    $props = [];
    $sourceIds = [];
    foreach ($raw as $v) {
        $p = strtoupper(trim((string)$v));
        if ($p !== '') { $props[] = $p; }
    }
    $props = array_values(array_unique($props));

    $rawPurchaseOrders = $_POST['purchase_orders'] ?? [];
    if (is_string($rawPurchaseOrders)) { $rawPurchaseOrders = [$rawPurchaseOrders]; }
    if (!is_array($rawPurchaseOrders)) {
        echo json_encode(['status'=>422,'message'=>'Invalid purchase_orders']);
        return false;
    }

    $purchaseOrders = [];
    foreach ($rawPurchaseOrders as $v) {
        $purchaseOrder = trim((string)$v);
        if ($purchaseOrder !== '') { $purchaseOrders[] = $purchaseOrder; }
    }
    $purchaseOrders = array_values(array_unique($purchaseOrders));

    if ($transferAll) {
        $props = [];
        $sourceIds = [];
        $sqlAll = "SELECT id, property_number FROM new_purchase ORDER BY id ASC";
        $resAll = mysqli_query($conn, $sqlAll);
        if (!$resAll) {
            echo json_encode(['status'=>500,'message'=>'Failed to load rows for bulk transfer.']);
            return false;
        }
        while ($r = mysqli_fetch_assoc($resAll)) {
            $id = (int)($r['id'] ?? 0);
            $pn = strtoupper(trim((string)($r['property_number'] ?? '')));
            if ($id > 0) { $sourceIds[] = $id; }
            if ($pn !== '') { $props[] = $pn; }
        }
        $sourceIds = array_values(array_unique($sourceIds));
        $props = array_values(array_unique($props));
    } elseif (count($purchaseOrders) > 0) {
        if (count($purchaseOrders) > 500) {
            echo json_encode(['status'=>422,'message'=>'Too many selected P.O. rows. Please transfer in smaller batches.']);
            return false;
        }

        $props = [];
        $sourceIds = [];
        $inPo = $makeIn(count($purchaseOrders));
        $sqlByPo = "SELECT id, property_number FROM new_purchase WHERE purchase_order IN ($inPo) ORDER BY id ASC";
        $stmtByPo = mysqli_prepare($conn, $sqlByPo);
        if (!$stmtByPo) {
            echo json_encode(['status'=>500,'message'=>'Failed to load rows for selected P.O. numbers.']);
            return false;
        }
        $typesPo = str_repeat('s', count($purchaseOrders));
        $purchaseOrdersForBind = $purchaseOrders;
        $bindParams($stmtByPo, $typesPo, $purchaseOrdersForBind);
        mysqli_stmt_execute($stmtByPo);
        $resByPo = mysqli_stmt_get_result($stmtByPo);
        if ($resByPo) {
            while ($r = mysqli_fetch_assoc($resByPo)) {
                $id = (int)($r['id'] ?? 0);
                $pn = strtoupper(trim((string)($r['property_number'] ?? '')));
                if ($id > 0) { $sourceIds[] = $id; }
                if ($pn !== '') { $props[] = $pn; }
            }
        }
        mysqli_stmt_close($stmtByPo);
        $sourceIds = array_values(array_unique($sourceIds));
        $props = array_values(array_unique($props));
    }

    if (count($sourceIds) <= 0 && count($props) > 0) {
        $inPropsForIds = $makeIn(count($props));
        $stmtIds = mysqli_prepare($conn, "SELECT id FROM new_purchase WHERE property_number IN ($inPropsForIds) ORDER BY id ASC");
        if (!$stmtIds) {
            echo json_encode(['status'=>500,'message'=>'Failed to load selected New Purchase rows.']);
            return false;
        }
        $typesPropsForIds = str_repeat('s', count($props));
        $propsForIds = $props;
        $bindParams($stmtIds, $typesPropsForIds, $propsForIds);
        mysqli_stmt_execute($stmtIds);
        $resIds = mysqli_stmt_get_result($stmtIds);
        if ($resIds) {
            while ($r = mysqli_fetch_assoc($resIds)) {
                $id = (int)($r['id'] ?? 0);
                if ($id > 0) { $sourceIds[] = $id; }
            }
        }
        mysqli_stmt_close($stmtIds);
        $sourceIds = array_values(array_unique($sourceIds));
    }

    if (count($sourceIds) <= 0) {
        echo json_encode(['status'=>422,'message'=>'Please select at least one row.']);
        return false;
    }
    if (count($sourceIds) > 5000) {
        echo json_encode(['status'=>422,'message'=>'Too many selected rows. Please transfer in smaller batches (max 5000).']);
        return false;
    }

    // Load selected rows and group by fund
    $inAll = $makeIn(count($sourceIds));
    $sqlSel = "SELECT id, property_number, UPPER(TRIM(fund)) AS fund FROM new_purchase WHERE id IN ($inAll)";
    $stmtSel = mysqli_prepare($conn, $sqlSel);
    if (!$stmtSel) {
        echo json_encode(['status'=>500,'message'=>'Server error: cannot prepare selection query.']);
        return false;
    }
    $typesAll = str_repeat('i', count($sourceIds));
    $sourceIdsForBind = $sourceIds;
    $bindParams($stmtSel, $typesAll, $sourceIdsForBind);
    mysqli_stmt_execute($stmtSel);
    $resSel = mysqli_stmt_get_result($stmtSel);
    $rows = [];
    if ($resSel) {
        while ($r = mysqli_fetch_assoc($resSel)) { $rows[] = $r; }
    }
    mysqli_stmt_close($stmtSel);

    if (count($rows) !== count($sourceIds)) {
        echo json_encode(['status'=>404,'message'=>'Some selected items were not found in New Purchase. Please refresh the page.']);
        return false;
    }

    $props = [];
    $historyKeys = [];
    $previewKeys = [];
    $gf = [];
    $sef = [];
    $trust = [];
    $donation = [];
    foreach ($rows as $r) {
        $id = (int)($r['id'] ?? 0);
        $pn = strtoupper(trim((string)($r['property_number'] ?? '')));
        $fd = strtoupper(trim((string)($r['fund'] ?? '')));
        $historyKey = $pn !== '' ? $pn : 'NPID:' . $id;
        if ($pn !== '') { $props[] = $pn; }
        if ($id > 0) {
            $historyKeys[] = $historyKey;
            $previewKeys[] = $historyKey;
        }
        if ($fd === 'GENERAL FUND') {
            if ($pn === '') {
                echo json_encode(['status'=>422,'message'=>'General Fund item has no property number: NPID:'.$id]);
                return false;
            }
            $gf[] = $pn;
        } elseif ($fd === 'SPECIAL EDUCATION FUND') {
            if ($pn === '') {
                echo json_encode(['status'=>422,'message'=>'SEF item has no property number: NPID:'.$id]);
                return false;
            }
            $sef[] = $pn;
        } elseif ($fd === 'TRUST FUND') {
            if ($id > 0) { $trust[] = $id; }
        } elseif ($fd === 'DONATION') {
            if ($id > 0) { $donation[] = $id; }
        } else {
            echo json_encode(['status'=>422,'message'=>'Invalid fund value for selected item: '.$historyKey]);
            return false;
        }
    }

    $props = array_values(array_unique(array_filter($props)));
    $historyKeys = array_values(array_unique(array_filter($historyKeys)));
    $previewKeys = array_values(array_unique(array_filter($previewKeys)));
    $gf = array_values(array_unique(array_filter($gf)));
    $sef = array_values(array_unique(array_filter($sef)));
    $trust = array_values(array_unique(array_filter($trust)));
    $donation = array_values(array_unique(array_filter($donation)));

    // Duplicate guard: do not delete from new_purchase if destination already has the record
    $checkDup = function($sql, $vals) use ($conn, $makeIn, $bindParams){
        if (count($vals) <= 0) { return []; }
        $in = $makeIn(count($vals));
        $stmt = mysqli_prepare($conn, sprintf($sql, $in));
        if (!$stmt) { throw new Exception('Server error: cannot prepare duplicate check.'); }
        $types = str_repeat('s', count($vals));
        $valsBind = $vals;
        $bindParams($stmt, $types, $valsBind);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $found = [];
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $found[] = strtoupper(trim((string)array_values($row)[0]));
            }
        }
        mysqli_stmt_close($stmt);
        return array_values(array_unique(array_filter($found)));
    };

    $assertAllInserted = function($label, $sql, $vals) use ($checkDup) {
        if (count($vals) <= 0) { return; }
        $found = $checkDup($sql, $vals);
        if (count($found) !== count($vals)) {
            $missing = array_values(array_diff($vals, $found));
            $preview = array_slice($missing, 0, 15);
            $suffix = count($missing) > 15 ? ' ...' : '';
            throw new Exception($label.' verification failed. Missing: '.implode(', ', $preview).$suffix);
        }
    };

    try {
        $dupGf = $checkDup('SELECT par_number FROM par_gen_fund WHERE par_number IN (%s)', $gf);
        if (count($dupGf) > 0) {
            echo json_encode(['status'=>409,'message'=>'Already exists in General Fund records: '.implode(', ', $dupGf)]);
            return false;
        }
        $dupSef = $checkDup('SELECT property_number FROM property_sef WHERE property_number IN (%s)', $sef);
        if (count($dupSef) > 0) {
            echo json_encode(['status'=>409,'message'=>'Already exists in SEF records: '.implode(', ', $dupSef)]);
            return false;
        }
        $dupTrust = $checkDup('SELECT id FROM trust_fund WHERE id IN (%s)', $trust);
        if (count($dupTrust) > 0) {
            echo json_encode(['status'=>409,'message'=>'Already exists in Trust Fund records: '.implode(', ', array_map(function($id){ return 'NPID:'.$id; }, $dupTrust))]);
            return false;
        }
        $dupDonation = $checkDup('SELECT id FROM donation WHERE id IN (%s)', $donation);
        if (count($dupDonation) > 0) {
            echo json_encode(['status'=>409,'message'=>'Already exists in Donation records: '.implode(', ', array_map(function($id){ return 'NPID:'.$id; }, $dupDonation))]);
            return false;
        }

        // Pre-flight: ensure every selected item has at least one history row.
        // Inventory pages depend on history tables; transferring without history makes items "disappear".
        $histFound = $checkDup('SELECT par_number FROM new_purchase_history WHERE par_number IN (%s)', $historyKeys);
        if (count($histFound) !== count($historyKeys)) {
            $missing = array_values(array_diff($historyKeys, $histFound));
            $preview = array_slice($missing, 0, 15);
            $suffix = count($missing) > 15 ? ' ...' : '';
            echo json_encode(['status'=>422,'message'=>'Cannot transfer: missing New Purchase history for: '.implode(', ', $preview).$suffix]);
            return false;
        }

        mysqli_begin_transaction($conn);

        // 1) Main rows
        if (count($gf) > 0) {
            $in = $makeIn(count($gf));
            $sql = "INSERT INTO par_gen_fund (category,item,model,description,serial_number,serial_number_2,par_number,unit_value,date_aquired,account_code,fund,supplier,par_ics_number,purchase_order,purchase_request,obr_number,jev_number,remarks)
                                        SELECT np.category,np.item,np.model,np.description,np.serial_number,np.serial_number_2,np.property_number,np.unit_value,np.date_aquired,np.account_code,'GENERAL FUND',np.supplier,np.par_ics_number,np.purchase_order,np.purchase_request,np.obr_number,np.jev_number,np.remarks
                                        FROM new_purchase np
                                        WHERE UPPER(TRIM(np.fund))='GENERAL FUND'
                                            AND np.property_number IN ($in)
                                            AND np.id = (
                                                    SELECT np2.id
                                                    FROM new_purchase np2
                                                    WHERE np2.property_number = np.property_number
                                                        AND UPPER(TRIM(np2.fund))='GENERAL FUND'
                                                    ORDER BY (COALESCE(np2.unit_value, 0) > 0) DESC, np2.id ASC
                                                    LIMIT 1
                                            )";
            $st = mysqli_prepare($conn, $sql);
            if (!$st) { throw new Exception('Failed to prepare General Fund insert.'); }
            $types = str_repeat('s', count($gf));
            $vals = $gf;
            $bindParams($st, $types, $vals);
            if (!mysqli_stmt_execute($st)) { throw new Exception('Failed to insert General Fund records.'); }
            $insRows = (int)mysqli_stmt_affected_rows($st);
            mysqli_stmt_close($st);

            if ($insRows !== count($gf)) {
                throw new Exception('General Fund insert count mismatch. Inserted '.$insRows.' of '.count($gf).'.');
            }

            // Verify that the destination now has all rows before we delete sources.
            $assertAllInserted('General Fund records', 'SELECT par_number FROM par_gen_fund WHERE par_number IN (%s)', $gf);

            $sqlH = "INSERT INTO general_fund_property_history (emp_id,dept_id,par_number,reference_number,status,category,created_at)
                     SELECT h.emp_id,CAST(d.department_code AS UNSIGNED),h.par_number,h.reference_number,h.status,h.category,h.created_at
                     FROM (
                         SELECT DISTINCT emp_id, dept_id, par_number, reference_number, status, category, created_at
                         FROM new_purchase_history
                     ) h
                     INNER JOIN department d ON d.dept_id = h.dept_id
                     INNER JOIN (
                         SELECT DISTINCT property_number, fund
                         FROM new_purchase
                     ) np ON np.property_number = h.par_number
                     WHERE UPPER(TRIM(np.fund))='GENERAL FUND' AND h.par_number IN ($in)";
            $stH = mysqli_prepare($conn, $sqlH);
            if (!$stH) { throw new Exception('Failed to prepare General Fund history insert.'); }
            $valsH = $gf;
            $bindParams($stH, $types, $valsH);
            if (!mysqli_stmt_execute($stH)) { throw new Exception('Failed to insert General Fund history.'); }
            $histRows = (int)mysqli_stmt_affected_rows($stH);
            mysqli_stmt_close($stH);

            if ($histRows <= 0) {
                throw new Exception('General Fund history insert inserted 0 rows.');
            }

            $sqlB = gso_build_bundle_transfer_insert_sql($conn, 'bundle_gen_fund', 'GENERAL FUND', $in);
            $stB = mysqli_prepare($conn, $sqlB);
            if (!$stB) { throw new Exception('Failed to prepare General Fund bundle insert.'); }
            $valsB = array_merge($gf, $gf);
            $bindParams($stB, $types.$types, $valsB);
            if (!mysqli_stmt_execute($stB)) { throw new Exception('Failed to insert General Fund bundles.'); }
            mysqli_stmt_close($stB);
        }

        if (count($sef) > 0) {
            $in = $makeIn(count($sef));
            $sql = "INSERT INTO property_sef (category,item,model,description,serial_number,serial_number_2,property_number,unit_value,date_aquired,account_code,fund,supplier,par_ics_number,purchase_order,purchase_request,obr_number,jev_number,remarks)
                                        SELECT np.category,np.item,np.model,np.description,np.serial_number,np.serial_number_2,np.property_number,np.unit_value,np.date_aquired,np.account_code,'SPECIAL EDUCATION FUND',np.supplier,np.par_ics_number,np.purchase_order,np.purchase_request,np.obr_number,np.jev_number,np.remarks
                                        FROM new_purchase np
                                        WHERE UPPER(TRIM(np.fund))='SPECIAL EDUCATION FUND'
                                            AND np.property_number IN ($in)
                                            AND np.id = (
                                                    SELECT np2.id
                                                    FROM new_purchase np2
                                                    WHERE np2.property_number = np.property_number
                                                        AND UPPER(TRIM(np2.fund))='SPECIAL EDUCATION FUND'
                                                    ORDER BY (COALESCE(np2.unit_value, 0) > 0) DESC, np2.id ASC
                                                    LIMIT 1
                                            )";
            $st = mysqli_prepare($conn, $sql);
            if (!$st) { throw new Exception('Failed to prepare SEF insert.'); }
            $types = str_repeat('s', count($sef));
            $vals = $sef;
            $bindParams($st, $types, $vals);
            if (!mysqli_stmt_execute($st)) { throw new Exception('Failed to insert SEF records.'); }
            $insRows = (int)mysqli_stmt_affected_rows($st);
            mysqli_stmt_close($st);

            if ($insRows !== count($sef)) {
                throw new Exception('SEF insert count mismatch. Inserted '.$insRows.' of '.count($sef).'.');
            }

            $assertAllInserted('SEF records', 'SELECT property_number FROM property_sef WHERE property_number IN (%s)', $sef);

            $sqlH = "INSERT INTO sef_property_history (emp_id,sch_id,property_number,reference_number,status,category,created_at)
                     SELECT h.emp_id,CAST(d.department_code AS UNSIGNED),h.par_number,h.reference_number,h.status,h.category,h.created_at
                     FROM (
                         SELECT DISTINCT emp_id, dept_id, par_number, reference_number, status, category, created_at
                         FROM new_purchase_history
                     ) h
                     INNER JOIN department d ON d.dept_id = h.dept_id
                     INNER JOIN (
                         SELECT DISTINCT property_number, fund
                         FROM new_purchase
                     ) np ON np.property_number = h.par_number
                     WHERE UPPER(TRIM(np.fund))='SPECIAL EDUCATION FUND' AND h.par_number IN ($in)";
            $stH = mysqli_prepare($conn, $sqlH);
            if (!$stH) { throw new Exception('Failed to prepare SEF history insert.'); }
            $valsH = $sef;
            $bindParams($stH, $types, $valsH);
            if (!mysqli_stmt_execute($stH)) { throw new Exception('Failed to insert SEF history.'); }
            $histRows = (int)mysqli_stmt_affected_rows($stH);
            mysqli_stmt_close($stH);

            if ($histRows <= 0) {
                throw new Exception('SEF history insert inserted 0 rows.');
            }

            $sqlB = gso_build_bundle_transfer_insert_sql($conn, 'bundle_sef', 'SPECIAL EDUCATION FUND', $in);
            $stB = mysqli_prepare($conn, $sqlB);
            if (!$stB) { throw new Exception('Failed to prepare SEF bundle insert.'); }
            $valsB = array_merge($sef, $sef);
            $bindParams($stB, $types.$types, $valsB);
            if (!mysqli_stmt_execute($stB)) { throw new Exception('Failed to insert SEF bundles.'); }
            mysqli_stmt_close($stB);
        }

        if (count($trust) > 0) {
            $in = $makeIn(count($trust));
            $sql = "INSERT INTO trust_fund (id,fund,category,unit,item,model,description,serial_number,serial_number_2,property_number,unit_value,date_aquired,account_code,supplier,par_ics_number,purchase_order,purchase_request,obr_number,jev_number,remarks,created_at)
                    SELECT np.id,'TRUST FUND',np.category,np.unit,np.item,np.model,np.description,np.serial_number,np.serial_number_2,np.property_number,np.unit_value,np.date_aquired,np.account_code,np.supplier,np.par_ics_number,np.purchase_order,np.purchase_request,np.obr_number,np.jev_number,np.remarks,np.created_at
                    FROM new_purchase np
                    WHERE UPPER(TRIM(np.fund))='TRUST FUND'
                        AND np.id IN ($in)";
            $st = mysqli_prepare($conn, $sql);
            if (!$st) { throw new Exception('Failed to prepare Trust Fund insert.'); }
            $types = str_repeat('i', count($trust));
            $vals = $trust;
            $bindParams($st, $types, $vals);
            if (!mysqli_stmt_execute($st)) { throw new Exception('Failed to insert Trust Fund records.'); }
            $insRows = (int)mysqli_stmt_affected_rows($st);
            mysqli_stmt_close($st);

            if ($insRows !== count($trust)) {
                throw new Exception('Trust Fund insert count mismatch. Inserted '.$insRows.' of '.count($trust).'.');
            }

            $assertAllInserted('Trust Fund records', 'SELECT id FROM trust_fund WHERE id IN (%s)', $trust);

            $sqlH = "INSERT INTO trust_fund_history (id,emp_id,dept_id,par_number,reference_number,status,category,created_at)
                     SELECT h.id,h.emp_id,h.dept_id,h.par_number,h.reference_number,h.status,h.category,h.created_at
                     FROM (
                         SELECT DISTINCT id, emp_id, dept_id, par_number, reference_number, status, category, created_at
                         FROM new_purchase_history
                     ) h
                     INNER JOIN (
                         SELECT DISTINCT id, property_number, fund
                         FROM new_purchase
                     ) np ON h.par_number = np.property_number OR h.par_number = CONCAT('NPID:', np.id)
                     WHERE UPPER(TRIM(np.fund))='TRUST FUND' AND np.id IN ($in)";
            $stH = mysqli_prepare($conn, $sqlH);
            if (!$stH) { throw new Exception('Failed to prepare Trust Fund history insert.'); }
            $valsH = $trust;
            $bindParams($stH, $types, $valsH);
            if (!mysqli_stmt_execute($stH)) { throw new Exception('Failed to insert Trust Fund history.'); }
            $histRows = (int)mysqli_stmt_affected_rows($stH);
            mysqli_stmt_close($stH);

            if ($histRows <= 0) {
                throw new Exception('Trust Fund history insert inserted 0 rows.');
            }
        }

        if (count($donation) > 0) {
            $in = $makeIn(count($donation));
            $sql = "INSERT INTO donation (id,fund,category,unit,item,model,description,serial_number,serial_number_2,property_number,unit_value,date_aquired,account_code,supplier,par_ics_number,purchase_order,purchase_request,obr_number,jev_number,remarks,created_at)
                    SELECT np.id,'DONATION',np.category,np.unit,np.item,np.model,np.description,np.serial_number,np.serial_number_2,np.property_number,np.unit_value,np.date_aquired,np.account_code,np.supplier,np.par_ics_number,np.purchase_order,np.purchase_request,np.obr_number,np.jev_number,np.remarks,np.created_at
                    FROM new_purchase np
                    WHERE UPPER(TRIM(np.fund))='DONATION'
                        AND np.id IN ($in)";
            $st = mysqli_prepare($conn, $sql);
            if (!$st) { throw new Exception('Failed to prepare Donation insert.'); }
            $types = str_repeat('i', count($donation));
            $vals = $donation;
            $bindParams($st, $types, $vals);
            if (!mysqli_stmt_execute($st)) { throw new Exception('Failed to insert Donation records.'); }
            $insRows = (int)mysqli_stmt_affected_rows($st);
            mysqli_stmt_close($st);

            if ($insRows !== count($donation)) {
                throw new Exception('Donation insert count mismatch. Inserted '.$insRows.' of '.count($donation).'.');
            }

            $assertAllInserted('Donation records', 'SELECT id FROM donation WHERE id IN (%s)', $donation);

            $sqlH = "INSERT INTO donation_history (id,emp_id,dept_id,par_number,reference_number,status,category,created_at)
                     SELECT h.id,h.emp_id,h.dept_id,h.par_number,h.reference_number,h.status,h.category,h.created_at
                     FROM (
                         SELECT DISTINCT id, emp_id, dept_id, par_number, reference_number, status, category, created_at
                         FROM new_purchase_history
                     ) h
                     INNER JOIN (
                         SELECT DISTINCT id, property_number, fund
                         FROM new_purchase
                     ) np ON h.par_number = np.property_number OR h.par_number = CONCAT('NPID:', np.id)
                     WHERE UPPER(TRIM(np.fund))='DONATION' AND np.id IN ($in)";
            $stH = mysqli_prepare($conn, $sqlH);
            if (!$stH) { throw new Exception('Failed to prepare Donation history insert.'); }
            $valsH = $donation;
            $bindParams($stH, $types, $valsH);
            if (!mysqli_stmt_execute($stH)) { throw new Exception('Failed to insert Donation history.'); }
            $histRows = (int)mysqli_stmt_affected_rows($stH);
            mysqli_stmt_close($stH);

            if ($histRows <= 0) {
                throw new Exception('Donation history insert inserted 0 rows.');
            }
        }

        // 2) Delete sources (all selected)
        if (count($props) > 0) {
            $inProps = $makeIn(count($props));
            $typesProps = str_repeat('s', count($props));

            $sqlDelB = "DELETE FROM new_bundle_purchase WHERE bundle_with IN ($inProps) OR property_number IN ($inProps)";
            $stDelB = mysqli_prepare($conn, $sqlDelB);
            if (!$stDelB) { throw new Exception('Failed to prepare bundle delete.'); }
            $valsDelB = array_merge($props, $props);
            $bindParams($stDelB, $typesProps.$typesProps, $valsDelB);
            if (!mysqli_stmt_execute($stDelB)) { throw new Exception('Failed to delete new bundle purchase rows.'); }
            mysqli_stmt_close($stDelB);
        }

        $inHistory = $makeIn(count($historyKeys));
        $typesHistory = str_repeat('s', count($historyKeys));
        $sqlDelH = "DELETE FROM new_purchase_history WHERE par_number IN ($inHistory)";
        $stDelH = mysqli_prepare($conn, $sqlDelH);
        if (!$stDelH) { throw new Exception('Failed to prepare history delete.'); }
        $valsDelH = $historyKeys;
        $bindParams($stDelH, $typesHistory, $valsDelH);
        if (!mysqli_stmt_execute($stDelH)) { throw new Exception('Failed to delete new purchase history rows.'); }
        mysqli_stmt_close($stDelH);

        $inSourceIds = $makeIn(count($sourceIds));
        $typesSourceIds = str_repeat('i', count($sourceIds));
        $sqlDel = "DELETE FROM new_purchase WHERE id IN ($inSourceIds)";
        $stDel = mysqli_prepare($conn, $sqlDel);
        if (!$stDel) { throw new Exception('Failed to prepare new purchase delete.'); }
        $valsDel = $sourceIds;
        $bindParams($stDel, $typesSourceIds, $valsDel);
        if (!mysqli_stmt_execute($stDel)) { throw new Exception('Failed to delete new purchase rows.'); }
        mysqli_stmt_close($stDel);

        mysqli_commit($conn);

        // Activity log (best-effort)
        try {
            $gfCount = count($gf);
            $sefCount = count($sef);
            $trustCount = count($trust);
            $donationCount = count($donation);
            $totalCount = count($sourceIds);
            $preview = array_slice($previewKeys, 0, 10);
            $previewTxt = implode(', ', $preview);
            if ($totalCount > 10) { $previewTxt .= ' ...'; }
            $actvty = "Transferred New Purchase to Records: {$totalCount} item(s) (GF {$gfCount}, SEF {$sefCount}, Trust {$trustCount}, Donation {$donationCount}) [{$previewTxt}]";
            $actEsc = mysqli_real_escape_string($conn, $actvty);
            $uidEsc = mysqli_real_escape_string($conn, (string)($uid ?? ($_SESSION['alogin'] ?? '')));
            $uipEsc = mysqli_real_escape_string($conn, (string)($uip ?? getUserIpAddr()));
            mysqli_query($conn, "INSERT INTO activity_log(admin_id,ip_address,activity) VALUES('{$uidEsc}','{$uipEsc}','{$actEsc}')");
        } catch (Exception $e) {
            // ignore logging errors
        }

        echo json_encode([
            'status'=>200,
            'message'=>'Transferred to records successfully.',
            'data'=>[
                'general_fund'=>count($gf),
                'sef'=>count($sef),
                'trust_fund'=>count($trust),
                'donation'=>count($donation)
            ]
        ]);
        if (isset($_SESSION['form_tokens']['np_transfer'][$token])) {
            unset($_SESSION['form_tokens']['np_transfer'][$token]);
        }
        return false;
    } catch (Exception $ex) {
        try { mysqli_rollback($conn); } catch (Exception $e) {}
        $dbErr = '';
        try {
            $dbErrRaw = mysqli_error($conn);
            if ($dbErrRaw) { $dbErr = ' (DB: '.$dbErrRaw.')'; }
        } catch (Throwable $t) {}
        echo json_encode(['status'=>500,'message'=>$ex->getMessage().$dbErr]);
        return false;
    }
}

// ==========================================================
// New Purchase: fetch items by P.O. No. (for detail datatable)
// ==========================================================
if (isset($_POST['fetch_np_items_by_po'])) {
    header('Content-Type: application/json; charset=utf-8');

    if (!isset($_SESSION['alogin']) || trim((string)$_SESSION['alogin']) === '') {
        echo json_encode(['status' => 401, 'data' => []]);
        return false;
    }

    $po = trim((string)($_POST['purchase_order'] ?? ''));
    if ($po === '') {
        echo json_encode(['status' => 422, 'data' => []]);
        return false;
    }

    $sql = "
        SELECT
            np.item         AS asset_class,
            np.model,
            np.description,
            np.serial_number,
            np.serial_number_2,
            np.property_number,
            COALESCE(d_by_id.department_name, d_by_code.department_name) AS department_name,
            e.emp_name
        FROM new_purchase AS np
        LEFT JOIN new_purchase_history AS h
            ON (h.par_number = np.property_number OR h.par_number = CONCAT('NPID:', np.id)) AND h.status = 1
        LEFT JOIN department AS d_by_id ON d_by_id.dept_id = h.dept_id
        LEFT JOIN department AS d_by_code ON d_by_code.department_code = h.dept_id
        LEFT JOIN employee   AS e ON e.emp_id  = h.emp_id
        WHERE np.purchase_order = ?
          AND np.id = (
              SELECT MIN(np2.id)
              FROM new_purchase AS np2
              WHERE np2.property_number = np.property_number
          )
        ORDER BY np.id ASC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['status' => 500, 'data' => []]);
        return false;
    }
    $stmt->bind_param('s', $po);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $model       = trim((string)($row['model']       ?? ''));
        $description = trim((string)($row['description'] ?? ''));
        $particulars = ($model !== '' && $description !== '')
            ? ($model . ' - ' . $description)
            : ($model !== '' ? $model : $description);

        $rows[] = [
            'asset_class'    => $row['asset_class']    ?? '',
            'particulars'    => $particulars,
            'serial_number'  => $row['serial_number']  ?? '',
            'serial_number_2'=> $row['serial_number_2']?? '',
            'property_number'=> $row['property_number']?? '',
            'department_name'=> $row['department_name']?? '',
            'emp_name'       => $row['emp_name']       ?? '',
        ];
    }
    $stmt->close();

    echo json_encode(['status' => 200, 'data' => $rows]);
    return false;
}

// ==========================================================
// New Purchase: update P.O. information (edit modal)
// ==========================================================
if (isset($_POST['update_np_po'])) {
    header('Content-Type: application/json; charset=utf-8');

    if (!isset($_SESSION['alogin']) || trim((string)$_SESSION['alogin']) === '') {
        echo json_encode(['status' => 401, 'message' => 'Unauthorized']);
        return false;
    }

    $originalPo   = trim((string)($_POST['original_po']      ?? ''));
    $newFund      = strtoupper(trim((string)($_POST['fund']             ?? '')));
    $newDeptInput = trim((string)($_POST['dept_id']          ?? ''));
    $newPo        = strtoupper(trim((string)($_POST['purchase_order']  ?? '')));
    $newPr        = strtoupper(trim((string)($_POST['purchase_request']?? '')));
    $newObr       = strtoupper(trim((string)($_POST['obr_number']      ?? '')));
    $newSupplier  = strtoupper(trim((string)($_POST['supplier']        ?? '')));
    $newParIcs    = strtoupper(trim((string)($_POST['par_ics_number']  ?? '')));

    if ($originalPo === '') {
        echo json_encode(['status' => 422, 'message' => 'Original P.O. is required.']);
        return false;
    }

    $newDeptPk = null;
    if ($newDeptInput !== '') {
        $deptRow = gso_find_department_by_code($conn, $newDeptInput);
        if (!$deptRow && ctype_digit($newDeptInput)) {
            $deptRow = gso_find_department_by_pk($conn, (int)$newDeptInput);
        }
        if (!$deptRow) {
            echo json_encode(['status' => 422, 'message' => 'Invalid department selected.']);
            return false;
        }
        $newDeptPk = (int)($deptRow['dept_id'] ?? 0);
    }

    $originalPoKey = strtoupper($originalPo);
    $stmtCheck = $conn->prepare('SELECT id FROM new_purchase WHERE UPPER(TRIM(COALESCE(purchase_order, \'\'))) = ?');
    if (!$stmtCheck) {
        echo json_encode(['status' => 500, 'message' => 'Server error.']);
        return false;
    }
    $stmtCheck->bind_param('s', $originalPoKey);
    $stmtCheck->execute();
    $checkResult = $stmtCheck->get_result();
    $matchedIds = [];
    while ($checkResult && ($checkRow = $checkResult->fetch_assoc())) {
        $matchedIds[] = (int)($checkRow['id'] ?? 0);
    }
    $stmtCheck->close();

    $matchedIds = array_values(array_filter($matchedIds, function ($id) { return $id > 0; }));
    if (empty($matchedIds)) {
        echo json_encode(['status' => 404, 'message' => 'No matching P.O. found.']);
        return false;
    }

    $idPlaceholders = implode(',', array_fill(0, count($matchedIds), '?'));
    $idTypes = str_repeat('i', count($matchedIds));

    mysqli_begin_transaction($conn);

    try {
        $sql = "UPDATE new_purchase
                SET purchase_order   = ?,
                    fund             = ?,
                    purchase_request = ?,
                    obr_number       = ?,
                    supplier         = ?,
                    par_ics_number   = ?
                WHERE id IN ($idPlaceholders)";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Unable to prepare P.O. update: ' . $conn->error);
        }
        $stmt->bind_param('ssssss' . $idTypes, $newPo, $newFund, $newPr, $newObr, $newSupplier, $newParIcs, ...$matchedIds);
        if (!$stmt->execute()) {
            throw new RuntimeException('Unable to update P.O. information: ' . $stmt->error);
        }
        $stmt->close();

        if ($newDeptPk !== null && $newDeptPk > 0) {
            $stmtDept = $conn->prepare("
                UPDATE new_purchase_history AS h
                INNER JOIN new_purchase AS np
                    ON h.par_number = np.property_number OR h.par_number = CONCAT('NPID:', np.id)
                SET h.dept_id = ?
                WHERE np.id IN ($idPlaceholders) AND h.status = 1
            ");
            if (!$stmtDept) {
                throw new RuntimeException('Unable to prepare department update: ' . $conn->error);
            }
            $stmtDept->bind_param('i' . $idTypes, $newDeptPk, ...$matchedIds);
            if (!$stmtDept->execute()) {
                throw new RuntimeException('Unable to update department: ' . $stmtDept->error);
            }
            $stmtDept->close();
        }

        mysqli_commit($conn);
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        echo json_encode(['status' => 500, 'message' => $e->getMessage()]);
        return false;
    }

    echo json_encode(['status' => 200, 'message' => 'P.O. information updated successfully.']);
    return false;
}

if (isset($_POST['fetch_new_purchase_group'])) {
    header('Content-Type: application/json; charset=utf-8');

    if (!isset($_SESSION['alogin']) || trim((string)$_SESSION['alogin']) === '') {
        echo json_encode(['status' => 401, 'message' => 'Unauthorized.']);
        return false;
    }

    $po = trim((string)($_POST['po'] ?? ''));
    $rowId = (int)($_POST['row_id'] ?? 0);
    $sourceContext = trim((string)($_POST['source_context'] ?? ''));
    $fundInventoryKey = trim((string)($_POST['fund_inventory_key'] ?? ''));
    $isFundInventoryContext = $sourceContext === 'fund_inventory';

    if ($isFundInventoryContext) {
        $rows = gso_fund_inventory_group_rows($conn, $fundInventoryKey, $po, $rowId);
    } else {
        $rows = gso_new_purchase_group_rows($conn, $po, $rowId);
    }

    if (empty($rows)) {
        echo json_encode(['status' => 404, 'message' => 'No matching purchase details found.']);
        return false;
    }

    $first = $rows[0];
    $items = gso_new_purchase_group_modal_items($rows);
    $bundles = gso_new_purchase_group_modal_bundles($conn, $rows);

    echo json_encode([
        'status' => 200,
        'message' => 'OK',
        'data' => [
            'group' => [
                'po' => (string)($first['purchase_order'] ?? ''),
                'row_id' => $rowId,
                'condition' => 'NEW',
                'category' => (string)($first['category'] ?? ''),
                'year' => (string)($first['date_aquired'] ?? ''),
                'fund' => (string)($first['fund'] ?? ''),
                'par_ics_number' => (string)($first['par_ics_number'] ?? ''),
                'purchase_request' => (string)($first['purchase_request'] ?? ''),
                'supplier' => (string)($first['supplier'] ?? ''),
                'obr_number' => (string)($first['obr_number'] ?? ''),
                'jev_number' => (string)($first['jev_number'] ?? ''),
                'department_code' => (string)($first['department_code'] ?? ''),
                'department_name' => (string)($first['department_name'] ?? ''),
                'reference_number' => (string)($first['reference_number'] ?? ''),
                'source_context' => $isFundInventoryContext ? 'fund_inventory' : 'new_purchase',
                'fund_inventory_key' => $isFundInventoryContext ? $fundInventoryKey : '',
            ],
            'items' => $items,
            'bundles' => $bundles,
        ],
    ]);
    return false;
}

if (isset($_POST['delete_new_purchase_group_set'])) {
    header('Content-Type: application/json; charset=utf-8');

    if (!isset($_SESSION['alogin']) || trim((string)$_SESSION['alogin']) === '') {
        echo json_encode(['status' => 401, 'message' => 'Unauthorized.']);
        return false;
    }

    $po = trim((string)($_POST['po'] ?? ''));
    $rowId = (int)($_POST['row_id'] ?? 0);
    $postedItemIdsRaw = $_POST['item_ids'] ?? ($_POST['item_ids_raw'] ?? []);
    $postedItemIds = [];
    if (is_array($postedItemIdsRaw)) {
        $postedItemIds = $postedItemIdsRaw;
    } elseif ($postedItemIdsRaw !== null && $postedItemIdsRaw !== '') {
        $postedItemIds = preg_split('/\s*,\s*/', (string)$postedItemIdsRaw, -1, PREG_SPLIT_NO_EMPTY);
    }
    $currentRows = gso_new_purchase_group_rows($conn, $po, $rowId);

    if (empty($currentRows)) {
        echo json_encode(['status' => 404, 'message' => 'No matching purchase details found.']);
        return false;
    }

    $currentById = [];
    foreach ($currentRows as $row) {
        $id = (int)($row['id'] ?? 0);
        if ($id > 0) {
            $currentById[$id] = $row;
        }
    }

    $deleteIds = [];
    foreach ($postedItemIds as $postedItemId) {
        $itemId = (int)$postedItemId;
        if ($itemId > 0 && isset($currentById[$itemId]) && !in_array($itemId, $deleteIds, true)) {
            $deleteIds[] = $itemId;
        }
    }

    if (empty($deleteIds)) {
        echo json_encode(['status' => 422, 'message' => 'No valid set items were selected for deletion.']);
        return false;
    }

    mysqli_begin_transaction($conn);

    try {
        $deleteItemStmt = $conn->prepare('DELETE FROM new_purchase WHERE id = ?');
        $deleteHistoryStmt = $conn->prepare('DELETE FROM new_purchase_history WHERE par_number = ? OR par_number = ?');
        $clearBundleStmt = $conn->prepare('DELETE FROM new_bundle_purchase WHERE property_number = ? OR bundle_with = ?');

        if (!$deleteItemStmt || !$deleteHistoryStmt || !$clearBundleStmt) {
            throw new RuntimeException('Unable to prepare delete statements.');
        }

        foreach ($deleteIds as $deleteId) {
            $row = $currentById[$deleteId];
            $propertyNumber = strtoupper(trim((string)($row['property_number'] ?? '')));
            $historyLink = 'NPID:' . $deleteId;

            if ($propertyNumber !== '') {
                $clearBundleStmt->bind_param('ss', $propertyNumber, $propertyNumber);
                if (!$clearBundleStmt->execute()) {
                    throw new RuntimeException('Unable to delete linked bundle records.');
                }
            }

            $deleteHistoryStmt->bind_param('ss', $propertyNumber, $historyLink);
            if (!$deleteHistoryStmt->execute()) {
                throw new RuntimeException('Unable to delete linked history records.');
            }

            $deleteItemStmt->bind_param('i', $deleteId);
            if (!$deleteItemStmt->execute()) {
                throw new RuntimeException('Unable to delete set item #' . $deleteId . '.');
            }
        }

        $deleteItemStmt->close();
        $deleteHistoryStmt->close();
        $clearBundleStmt->close();
        mysqli_commit($conn);

        echo json_encode([
            'status' => 200,
            'message' => 'Set deleted permanently.',
            'data' => [
                'deleted_item_ids' => $deleteIds,
                'remaining_items' => max(0, count($currentById) - count($deleteIds)),
            ],
        ]);
        return false;
    } catch (Throwable $e) {
        if (isset($deleteItemStmt) && $deleteItemStmt instanceof mysqli_stmt) {
            $deleteItemStmt->close();
        }
        if (isset($deleteHistoryStmt) && $deleteHistoryStmt instanceof mysqli_stmt) {
            $deleteHistoryStmt->close();
        }
        if (isset($clearBundleStmt) && $clearBundleStmt instanceof mysqli_stmt) {
            $clearBundleStmt->close();
        }
        mysqli_rollback($conn);
        echo json_encode(['status' => 500, 'message' => $e->getMessage()]);
        return false;
    }
}

if (isset($_POST['update_new_purchase_group'])) {
    header('Content-Type: application/json; charset=utf-8');

    if (!isset($_SESSION['alogin']) || trim((string)$_SESSION['alogin']) === '') {
        echo json_encode(['status' => 401, 'message' => 'Unauthorized.']);
        return false;
    }

    $sourceContext = trim((string)($_POST['source_context'] ?? ''));
    $fundInventoryKey = trim((string)($_POST['fund_inventory_key'] ?? ''));
    if ($sourceContext === 'fund_inventory') {
        $po = trim((string)($_POST['po'] ?? ''));
        $rowId = (int)($_POST['row_id'] ?? 0);
        $source = gso_fund_inventory_source_map($fundInventoryKey);
        if (!$source) {
            echo json_encode(['status' => 422, 'message' => 'Invalid fund inventory source.']);
            return false;
        }

        $currentRows = gso_fund_inventory_group_rows($conn, $fundInventoryKey, $po, $rowId);
        if (empty($currentRows)) {
            echo json_encode(['status' => 404, 'message' => 'No matching fund inventory details found.']);
            return false;
        }

        $currentById = [];
        foreach ($currentRows as $row) {
            $id = (int)($row['id'] ?? 0);
            if ($id > 0) {
                $currentById[$id] = $row;
            }
        }
        if (empty($currentById)) {
            echo json_encode(['status' => 404, 'message' => 'No editable fund inventory items were found.']);
            return false;
        }

        $currentModalItems = gso_new_purchase_group_modal_items($currentRows);
        $groupedItems = [];
        foreach ($currentModalItems as $item) {
            $itemKey = (string)($item['id'] ?? '');
            if ($itemKey !== '') {
                $groupedItems[$itemKey] = $item;
            }
        }

        $postedSetKeys = isset($_POST['set_keys']) && is_array($_POST['set_keys']) ? $_POST['set_keys'] : [];
        $setKeys = [];
        foreach ($postedSetKeys as $postedKey) {
            $setKey = trim((string)$postedKey);
            if ($setKey !== '' && isset($groupedItems[$setKey]) && !in_array($setKey, $setKeys, true)) {
                $setKeys[] = $setKey;
            }
        }
        if (empty($setKeys)) {
            $setKeys = array_keys($groupedItems);
        }

        $fund = strtoupper(trim((string)($_POST['fund'] ?? '')));
        if ($fund === '') {
            $fund = strtoupper(trim((string)($source['label'] ?? '')));
        }
        if ($fund !== strtoupper(trim((string)($source['label'] ?? '')))) {
            echo json_encode(['status' => 422, 'message' => 'Changing fund type is not supported here.']);
            return false;
        }

        $year = trim((string)($_POST['year'] ?? ''));
        $purchaseOrder = strtoupper(trim((string)($_POST['purchase_order'] ?? '')));
        $purchaseRequest = strtoupper(trim((string)($_POST['purchase_request'] ?? '')));
        $obrNumber = strtoupper(trim((string)($_POST['obr_number'] ?? '')));
        $jevNumber = strtoupper(trim((string)($_POST['jev_number'] ?? '')));
        $supplier = strtoupper(trim((string)($_POST['supplier'] ?? '')));
        $deptInput = trim((string)($_POST['dept_id'] ?? ''));

        if ($year === '' || $deptInput === '') {
            echo json_encode(['status' => 422, 'message' => 'Year and department are required.']);
            return false;
        }

        $departmentRow = gso_find_department_by_code($conn, $deptInput);
        if (!$departmentRow && ctype_digit($deptInput)) {
            $departmentRow = gso_find_department_by_pk($conn, (int)$deptInput);
        }
        if (!$departmentRow) {
            echo json_encode(['status' => 422, 'message' => 'Invalid department selected.']);
            return false;
        }

        $departmentCode = trim((string)($departmentRow['department_code'] ?? ''));
        $departmentPk = trim((string)($departmentRow['dept_id'] ?? ''));
        $deptHistoryValue = $departmentCode !== '' ? $departmentCode : $departmentPk;

        $getPostedMapValue = function ($fieldName, $itemKey, $default = '') {
            $sourceMap = isset($_POST[$fieldName]) && is_array($_POST[$fieldName]) ? $_POST[$fieldName] : [];
            if (isset($sourceMap[$itemKey]) && !is_array($sourceMap[$itemKey])) {
                return (string)$sourceMap[$itemKey];
            }
            if (isset($sourceMap[(string)$itemKey]) && !is_array($sourceMap[(string)$itemKey])) {
                return (string)$sourceMap[(string)$itemKey];
            }
            return $default;
        };
        $getPostedNestedMapValue = function ($fieldName, $itemKey, $copyIndex, $default = '') {
            $sourceMap = isset($_POST[$fieldName]) && is_array($_POST[$fieldName]) ? $_POST[$fieldName] : [];
            $row = null;
            if (isset($sourceMap[$itemKey]) && is_array($sourceMap[$itemKey])) {
                $row = $sourceMap[$itemKey];
            } elseif (isset($sourceMap[(string)$itemKey]) && is_array($sourceMap[(string)$itemKey])) {
                $row = $sourceMap[(string)$itemKey];
            }
            if ($row === null) {
                return $default;
            }
            if (isset($row[$copyIndex])) {
                return (string)$row[$copyIndex];
            }
            if (isset($row[(string)$copyIndex])) {
                return (string)$row[(string)$copyIndex];
            }
            return $default;
        };
        $normalizeItemQuantity = function ($value) {
            $qty = (int)trim((string)$value);
            if ($qty < 1) { $qty = 1; }
            if ($qty > 5000) { $qty = 5000; }
            return $qty;
        };

        $employeeCache = [];
        $resolveEmployeeId = function ($itemKey) use ($conn, &$employeeCache, $getPostedMapValue, $departmentCode) {
            $empValue = trim((string)$getPostedMapValue('emp_id', $itemKey, ''));
            if ($empValue === '') {
                return '';
            }
            if (strtolower($empValue) !== 'add_new_emp') {
                return $empValue;
            }

            $newName = strtoupper(trim((string)$getPostedMapValue('emp_new_name', $itemKey, '')));
            $newPosition = strtoupper(trim((string)$getPostedMapValue('emp_new_position', $itemKey, 'N/A')));
            if ($newName === '') {
                throw new RuntimeException('New employee name is required.');
            }
            if ($newPosition === '') {
                $newPosition = 'N/A';
            }

            $cacheKey = $departmentCode . '|' . $newName . '|' . $newPosition;
            if (isset($employeeCache[$cacheKey])) {
                return $employeeCache[$cacheKey];
            }

            $created = gso_create_employee_atomic($conn, $newName, $departmentCode, $newPosition, 1, null);
            if (!isset($created['ok']) || !$created['ok']) {
                throw new RuntimeException($created['message'] ?? 'Error creating employee.');
            }
            $employeeCache[$cacheKey] = (string)$created['emp_id'];
            return $employeeCache[$cacheKey];
        };

        $table = $source['table'];
        $historyTable = $source['history'];
        mysqli_begin_transaction($conn);

        try {
            $itemSql = "UPDATE {$table}
                        SET unit=?, item=?, model=?, description=?, serial_number=?, serial_number_2=?,
                            property_number=?, unit_value=?, date_aquired=?, account_code=?, supplier=?,
                            par_ics_number=?, purchase_order=?, purchase_request=?, obr_number=?, jev_number=?,
                            remarks=?, fund=?, category=?
                        WHERE id=?";
            $itemStmt = $conn->prepare($itemSql);
            if (!$itemStmt) {
                throw new RuntimeException('Unable to prepare fund inventory update: ' . $conn->error);
            }

            $historySql = "UPDATE {$historyTable}
                           SET par_number = ?, category = ?, dept_id = ?, emp_id = CASE WHEN ? > 0 THEN ? ELSE NULL END
                           WHERE id = ? AND status = 1";
            $historyStmt = $conn->prepare($historySql);
            if (!$historyStmt) {
                throw new RuntimeException('Unable to prepare fund history update: ' . $conn->error);
            }

            foreach ($setKeys as $setKey) {
                $currentItem = $groupedItems[$setKey] ?? null;
                if (!$currentItem) {
                    continue;
                }

                $existingIdsCsv = $getPostedMapValue('existing_item_ids', $setKey, '');
                $existingIds = array_values(array_filter(array_map('intval', preg_split('/\s*,\s*/', $existingIdsCsv, -1, PREG_SPLIT_NO_EMPTY))));
                if (empty($existingIds)) {
                    $existingIds = array_values(array_filter(array_map('intval', (array)($currentItem['existing_item_ids'] ?? []))));
                }
                if (empty($existingIds)) {
                    continue;
                }

                $postedQuantity = $normalizeItemQuantity($getPostedMapValue('item_quantity', $setKey, (string)count($existingIds)));
                if ($postedQuantity !== count($existingIds)) {
                    throw new RuntimeException('Changing set quantity is not supported for fund inventory records.');
                }

                $resolvedEmployeeId = $resolveEmployeeId($setKey);
                $resolvedEmployeeInt = (int)$resolvedEmployeeId;
                $category = strtoupper(trim((string)$getPostedMapValue('category', $setKey, $currentItem['category'] ?? '')));
                $unit = strtoupper(trim((string)$getPostedMapValue('unit', $setKey, $currentItem['unit'] ?? '')));
                $itemName = strtoupper(trim((string)$getPostedMapValue('item', $setKey, $currentItem['item'] ?? '')));
                $model = strtoupper(trim((string)$getPostedMapValue('model', $setKey, $currentItem['model'] ?? '')));
                $description = strtoupper(trim((string)$getPostedMapValue('description', $setKey, $currentItem['description'] ?? '')));
                $accountCode = strtoupper(trim((string)$getPostedMapValue('account_code', $setKey, $currentItem['account_code'] ?? '')));
                $remarks = strtoupper(trim((string)$getPostedMapValue('remarks', $setKey, $currentItem['remarks'] ?? '')));
                $parIcsNumber = strtoupper(trim((string)$getPostedMapValue('par_ics_number_preview_value', $setKey, $currentItem['par_ics_number'] ?? '')));
                $propertyNumberInput = trim((string)$getPostedMapValue('property_number', $setKey, $currentItem['property_number'] ?? ''));
                $unitValueRaw = str_replace(',', '', trim((string)$getPostedMapValue('unit_value', $setKey, $currentItem['unit_value'] ?? '0')));
                $unitValue = (float)($unitValueRaw !== '' ? $unitValueRaw : 0);

                foreach (array_values($existingIds) as $copyOffset => $existingId) {
                    $existingId = (int)$existingId;
                    $currentRow = $currentById[$existingId] ?? null;
                    if (!$currentRow) {
                        continue;
                    }

                    $copyIndex = $copyOffset + 1;
                    $serialOne = strtoupper(trim((string)$getPostedNestedMapValue('serial_number', $setKey, $copyIndex, $currentRow['serial_number'] ?? '')));
                    $serialTwo = strtoupper(trim((string)$getPostedNestedMapValue('serial_number_2', $setKey, $copyIndex, $currentRow['serial_number_2'] ?? '')));
                    $rowPropertyNumber = trim((string)($currentRow['property_number'] ?? ''));
                    if (count($existingIds) === 1) {
                        $rowPropertyNumber = strtoupper(trim($propertyNumberInput));
                    }
                    $historyParNumber = $rowPropertyNumber !== '' ? $rowPropertyNumber : ('NPID:' . $existingId);
                    $fundValue = strtoupper(trim((string)($source['label'] ?? '')));

                    $itemStmt->bind_param(
                        'sssssssdsssssssssssi',
                        $unit,
                        $itemName,
                        $model,
                        $description,
                        $serialOne,
                        $serialTwo,
                        $rowPropertyNumber,
                        $unitValue,
                        $year,
                        $accountCode,
                        $supplier,
                        $parIcsNumber,
                        $purchaseOrder,
                        $purchaseRequest,
                        $obrNumber,
                        $jevNumber,
                        $remarks,
                        $fundValue,
                        $category,
                        $existingId
                    );
                    if (!$itemStmt->execute()) {
                        throw new RuntimeException('Unable to update fund inventory item #' . $existingId . '.');
                    }

                    $historyStmt->bind_param(
                        'sssiii',
                        $historyParNumber,
                        $category,
                        $deptHistoryValue,
                        $resolvedEmployeeInt,
                        $resolvedEmployeeInt,
                        $existingId
                    );
                    if (!$historyStmt->execute()) {
                        throw new RuntimeException('Unable to update fund history item #' . $existingId . '.');
                    }
                }
            }

            $itemStmt->close();
            $historyStmt->close();
            mysqli_commit($conn);
            echo json_encode(['status' => 200, 'message' => 'Updated successfully.']);
            return false;
        } catch (Throwable $e) {
            if (isset($itemStmt) && $itemStmt instanceof mysqli_stmt) {
                $itemStmt->close();
            }
            if (isset($historyStmt) && $historyStmt instanceof mysqli_stmt) {
                $historyStmt->close();
            }
            mysqli_rollback($conn);
            echo json_encode(['status' => 500, 'message' => $e->getMessage()]);
            return false;
        }
    }

    $po = trim((string)($_POST['po'] ?? ''));
    $rowId = (int)($_POST['row_id'] ?? 0);
    $currentRows = gso_new_purchase_group_rows($conn, $po, $rowId);
    if (empty($currentRows)) {
        echo json_encode(['status' => 404, 'message' => 'No matching purchase details found.']);
        return false;
    }

    $currentById = [];
    foreach ($currentRows as $row) {
        $id = (int)($row['id'] ?? 0);
        if ($id > 0) {
            $currentById[$id] = $row;
        }
    }
    if (empty($currentById)) {
        echo json_encode(['status' => 404, 'message' => 'No editable items were found.']);
        return false;
    }

    $postedSetKeys = isset($_POST['set_keys']) && is_array($_POST['set_keys']) ? $_POST['set_keys'] : [];
    $setKeys = [];
    foreach ($postedSetKeys as $postedKey) {
        $setKey = trim((string)$postedKey);
        if ($setKey !== '' && !in_array($setKey, $setKeys, true)) {
            $setKeys[] = $setKey;
        }
    }
    if (empty($setKeys)) {
        foreach (array_keys($currentById) as $fallbackId) {
            $setKeys[] = (string)$fallbackId;
        }
    }

    $fund = strtoupper(trim((string)($_POST['fund'] ?? '')));
    $year = trim((string)($_POST['year'] ?? ''));
    $purchaseOrder = strtoupper(trim((string)($_POST['purchase_order'] ?? '')));
    $purchaseRequest = strtoupper(trim((string)($_POST['purchase_request'] ?? '')));
    $obrNumber = strtoupper(trim((string)($_POST['obr_number'] ?? '')));
    $jevNumber = strtoupper(trim((string)($_POST['jev_number'] ?? '')));
    $supplier = strtoupper(trim((string)($_POST['supplier'] ?? '')));
    $deptInput = trim((string)($_POST['dept_id'] ?? ''));

    if ($fund === '' || $year === '' || $deptInput === '') {
        echo json_encode(['status' => 422, 'message' => 'Fund, year, and department are required.']);
        return false;
    }

    $departmentRow = gso_find_department_by_code($conn, $deptInput);
    if (!$departmentRow && ctype_digit($deptInput)) {
        $departmentRow = gso_find_department_by_pk($conn, (int)$deptInput);
    }
    if (!$departmentRow) {
        echo json_encode(['status' => 422, 'message' => 'Invalid department selected.']);
        return false;
    }

    $departmentPk = (int)($departmentRow['dept_id'] ?? 0);
    $departmentCode = trim((string)($departmentRow['department_code'] ?? ''));
    $propertyNumberOptionalFund = in_array($fund, ['TRUST FUND', 'DONATION'], true);
    $referenceNumber = '';
    foreach ($currentRows as $row) {
        $candidateReference = trim((string)($row['reference_number'] ?? ''));
        if ($candidateReference !== '') {
            $referenceNumber = $candidateReference;
            break;
        }
    }
    if ($referenceNumber === '') {
        $referenceNumber = generateReferenceNumber($conn, 'new_purchase_history', 'reference_number');
    }

    $existingItemIdMap = isset($_POST['existing_item_id']) && is_array($_POST['existing_item_id']) ? $_POST['existing_item_id'] : [];
    $existingItemIdsMap = isset($_POST['existing_item_ids']) && is_array($_POST['existing_item_ids']) ? $_POST['existing_item_ids'] : [];
    $bundleCategories = (isset($_POST['bundle_category']) && is_array($_POST['bundle_category'])) ? $_POST['bundle_category'] : [];
    $bundleUnits = (isset($_POST['bundle_unit']) && is_array($_POST['bundle_unit'])) ? $_POST['bundle_unit'] : [];
    $bundleAssets = (isset($_POST['bundle_asset_class']) && is_array($_POST['bundle_asset_class'])) ? $_POST['bundle_asset_class'] : [];
    $bundleModels = (isset($_POST['bundle_brand_model']) && is_array($_POST['bundle_brand_model'])) ? $_POST['bundle_brand_model'] : [];
    $bundleDescs = (isset($_POST['bundle_description']) && is_array($_POST['bundle_description'])) ? $_POST['bundle_description'] : [];
    $bundleSerial1 = (isset($_POST['bundle_serial1']) && is_array($_POST['bundle_serial1'])) ? $_POST['bundle_serial1'] : [];
    $bundleSerial2 = (isset($_POST['bundle_serial2']) && is_array($_POST['bundle_serial2'])) ? $_POST['bundle_serial2'] : [];
    $bundleParentIndexes = (isset($_POST['bundle_parent_index']) && is_array($_POST['bundle_parent_index'])) ? $_POST['bundle_parent_index'] : [];
    $bundlePropertyNumbers = (isset($_POST['bundle_property_number']) && is_array($_POST['bundle_property_number'])) ? $_POST['bundle_property_number'] : [];
    $getPostedMapValue = function ($fieldName, $itemKey, $default = '') {
        $source = isset($_POST[$fieldName]) && is_array($_POST[$fieldName]) ? $_POST[$fieldName] : [];
        if (isset($source[$itemKey]) && !is_array($source[$itemKey])) {
            return (string)$source[$itemKey];
        }
        if (isset($source[(string)$itemKey]) && !is_array($source[(string)$itemKey])) {
            return (string)$source[(string)$itemKey];
        }
        return $default;
    };
    $getPostedNestedMapValue = function ($fieldName, $itemKey, $copyIndex, $default = '') {
        $source = isset($_POST[$fieldName]) && is_array($_POST[$fieldName]) ? $_POST[$fieldName] : [];
        $row = null;
        if (isset($source[$itemKey]) && is_array($source[$itemKey])) {
            $row = $source[$itemKey];
        } elseif (isset($source[(string)$itemKey]) && is_array($source[(string)$itemKey])) {
            $row = $source[(string)$itemKey];
        }
        if ($row === null) {
            return $default;
        }
        if (isset($row[$copyIndex])) {
            return (string)$row[$copyIndex];
        }
        if (isset($row[(string)$copyIndex])) {
            return (string)$row[(string)$copyIndex];
        }
        return $default;
    };
    $normalizeItemQuantity = function ($value) {
        $qty = (int)trim((string)$value);
        if ($qty < 1) { $qty = 1; }
        if ($qty > 5000) { $qty = 5000; }
        return $qty;
    };
    $normalizeCategory = function ($value) {
        $category = strtoupper(trim((string)$value));
        return in_array($category, ['PAR', 'ICS'], true) ? $category : '';
    };
    $nextPropertyNumber = function ($current) {
        $parts = explode('-', (string)$current);
        if (count($parts) < 4) { return (string)$current; }
        $seqIndex = count($parts) - 2;
        $seq = $parts[$seqIndex];
        $parts[$seqIndex] = str_pad((string)(((int)$seq) + 1), strlen($seq), '0', STR_PAD_LEFT);
        return implode('-', $parts);
    };
    $generatedParIcsByCategory = [];
    $currentParIcsByCategory = [];
    foreach ($currentRows as $row) {
        $rowCategory = $normalizeCategory($row['category'] ?? '');
        $rowParIcs = strtoupper(trim((string)($row['par_ics_number'] ?? '')));
        if ($rowCategory !== '' && $rowParIcs !== '' && !isset($currentParIcsByCategory[$rowCategory])) {
            $currentParIcsByCategory[$rowCategory] = $rowParIcs;
        }
    }
    $getParIcsForCategory = function ($rowCategory) use ($conn, &$generatedParIcsByCategory, $currentParIcsByCategory, $normalizeCategory) {
        $rowCategory = $normalizeCategory($rowCategory);
        if ($rowCategory === '') {
            throw new RuntimeException('Each set must include a valid category.');
        }
        if (isset($generatedParIcsByCategory[$rowCategory])) {
            return $generatedParIcsByCategory[$rowCategory];
        }
        if (isset($currentParIcsByCategory[$rowCategory])) {
            $generatedParIcsByCategory[$rowCategory] = $currentParIcsByCategory[$rowCategory];
            return $generatedParIcsByCategory[$rowCategory];
        }
        $ym = date('Ym');
        $letter = ($rowCategory === 'ICS') ? 'I' : 'P';
        $prefix = $ym . '-' . $letter;
        $prefixEsc = mysqli_real_escape_string($conn, $prefix);
        $max = 0;
        foreach (['new_purchase', 'par_gen_fund', 'property_sef', 'trust_fund', 'donation'] as $tableName) {
            $tableName = preg_replace('/[^A-Za-z0-9_]/', '', $tableName);
            $sql = "SELECT MAX(CAST(SUBSTRING(par_ics_number, LENGTH('$prefixEsc') + 1) AS UNSIGNED)) AS max_sfx
                    FROM {$tableName}
                    WHERE par_ics_number LIKE CONCAT('$prefixEsc','%')";
            $result = mysqli_query($conn, $sql);
            if ($result && mysqli_num_rows($result) === 1) {
                $row = mysqli_fetch_assoc($result);
                if ($row && $row['max_sfx'] !== null) {
                    $max = max($max, (int)$row['max_sfx']);
                }
            }
        }
        $generatedParIcsByCategory[$rowCategory] = $prefix . sprintf('%04d', ($max + 1));
        return $generatedParIcsByCategory[$rowCategory];
    };
    $generateAvailablePropertyNumber = function ($category, $accountCode, $existingId = 0, $oldPropertyNumber = '', array $exclude = [], $quantity = 1) use ($conn, $year, $departmentCode, $fund, $nextPropertyNumber) {
        $category = strtoupper(trim((string)$category));
        $accountCode = strtoupper(trim((string)$accountCode));
        $existingId = (int)$existingId;
        $oldPropertyNumber = strtoupper(trim((string)$oldPropertyNumber));
        $quantity = max(1, min(5000, (int)$quantity));
        $excludeList = [];

        if ($oldPropertyNumber !== '') {
            $excludeList[] = $oldPropertyNumber;
        }
        foreach ($exclude as $excludedNumber) {
            $excludedNumber = strtoupper(trim((string)$excludedNumber));
            if ($excludedNumber !== '' && !in_array($excludedNumber, $excludeList, true)) {
                $excludeList[] = $excludedNumber;
            }
        }

        for ($attempt = 0; $attempt < 50; $attempt++) {
            $generated = gso_generate_one_property_number($conn, $category, $year, $accountCode, $departmentCode, $fund, $excludeList);
            if (!isset($generated['ok']) || !$generated['ok']) {
                throw new RuntimeException($generated['error'] ?? 'Unable to generate property number.');
            }

            $candidate = strtoupper(trim((string)($generated['property_number'] ?? '')));
            if ($candidate === '') {
                continue;
            }

            $candidateBlock = [];
            $blockNumber = $candidate;
            $blockAvailable = true;
            for ($copyIndex = 1; $copyIndex <= $quantity; $copyIndex++) {
                $blockKey = strtoupper(trim((string)$blockNumber));
                $checkId = $copyIndex === 1 ? $existingId : 0;
                $checkOld = $copyIndex === 1 ? $oldPropertyNumber : '';
                if ($blockKey === '' || in_array($blockKey, $excludeList, true) || gso_new_purchase_property_number_in_use($conn, $blockKey, $checkId, $checkOld)) {
                    $blockAvailable = false;
                    break;
                }
                $candidateBlock[] = $blockKey;
                $blockNumber = strtoupper(trim((string)$nextPropertyNumber($blockKey)));
            }

            if ($blockAvailable) {
                return $candidate;
            }

            foreach ($candidateBlock as $blockKey) {
                if (!in_array($blockKey, $excludeList, true)) {
                    $excludeList[] = $blockKey;
                }
            }
            if (!in_array($candidate, $excludeList, true)) {
                $excludeList[] = $candidate;
            }
        }

        throw new RuntimeException('Unable to allocate an available property number.');
    };
    $totalRequestedItems = 0;
    foreach ($setKeys as $setKey) {
        $totalRequestedItems += $normalizeItemQuantity($getPostedMapValue('item_quantity', $setKey, '1'));
    }
    if ($totalRequestedItems > 5000) {
        echo json_encode(['status' => 422, 'message' => 'A maximum of 5,000 total quantity is allowed per update.']);
        return false;
    }

    mysqli_begin_transaction($conn);

    try {
        $itemSql = 'UPDATE new_purchase
                    SET unit=?, item=?, model=?, description=?, serial_number=?, serial_number_2=?,
                        property_number=?, unit_value=?, date_aquired=?, account_code=?, supplier=?,
                        par_ics_number=?, purchase_order=?, purchase_request=?, obr_number=?, jev_number=?,
                        remarks=?, fund=?, category=?
                    WHERE id=?';
        $itemStmt = $conn->prepare($itemSql);
        if (!$itemStmt) {
            throw new RuntimeException('Unable to prepare item update: ' . $conn->error);
        }
        $insertStmt = $conn->prepare(
            'INSERT INTO new_purchase (fund,category,unit,item,model,description,serial_number,serial_number_2,property_number,unit_value,date_aquired,account_code,supplier,par_ics_number,purchase_order,purchase_request,obr_number,jev_number,remarks,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        if (!$insertStmt) {
            throw new RuntimeException('Unable to prepare item insert: ' . $conn->error);
        }
        $historyInsertStmt = $conn->prepare(
            'INSERT INTO new_purchase_history (emp_id,dept_id,par_number,reference_number,status,category,created_at) VALUES (?,?,?,?,?,?,?)'
        );
        if (!$historyInsertStmt) {
            throw new RuntimeException('Unable to prepare history insert: ' . $conn->error);
        }
        $historyUpdateWithOldStmt = $conn->prepare(
            'UPDATE new_purchase_history
             SET par_number = ?, category = ?, dept_id = ?, emp_id = CASE WHEN ? > 0 THEN ? ELSE NULL END
             WHERE (par_number = ? OR par_number = ?) AND status = 1'
        );
        if (!$historyUpdateWithOldStmt) {
            throw new RuntimeException('Unable to prepare history update: ' . $conn->error);
        }
        $historyUpdateLinkStmt = $conn->prepare(
            'UPDATE new_purchase_history
             SET par_number = ?, category = ?, dept_id = ?, emp_id = CASE WHEN ? > 0 THEN ? ELSE NULL END
             WHERE par_number = ? AND status = 1'
        );
        if (!$historyUpdateLinkStmt) {
            throw new RuntimeException('Unable to prepare history link update: ' . $conn->error);
        }
        $deleteItemStmt = $conn->prepare('DELETE FROM new_purchase WHERE id = ?');
        $deleteHistoryStmt = $conn->prepare('DELETE FROM new_purchase_history WHERE par_number = ? OR par_number = ?');
        $clearBundleStmt = $conn->prepare('DELETE FROM new_bundle_purchase WHERE property_number = ? OR bundle_with = ?');
        $bundleNullStmt = $conn->prepare(
            'UPDATE new_bundle_purchase
             SET property_number = CASE WHEN property_number = ? THEN NULL ELSE property_number END,
                 bundle_with = CASE WHEN bundle_with = ? THEN NULL ELSE bundle_with END
             WHERE property_number = ? OR bundle_with = ?'
        );
        $bundleMoveStmt = $conn->prepare(
            'UPDATE new_bundle_purchase
             SET property_number = CASE WHEN property_number = ? THEN ? ELSE property_number END,
                 bundle_with = CASE WHEN bundle_with = ? THEN ? ELSE bundle_with END
             WHERE property_number = ? OR bundle_with = ?'
        );
        $bundleHasUnitColumn = gso_column_exists($conn, 'new_bundle_purchase', 'unit');
        $bundleInsertSql = $bundleHasUnitColumn
            ? 'INSERT INTO new_bundle_purchase (dept_id, emp_id, property_number, bundle_with, category, unit, item, model, description, serial_number, serial_number_2, par_ics_number) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
            : 'INSERT INTO new_bundle_purchase (dept_id, emp_id, property_number, bundle_with, category, item, model, description, serial_number, serial_number_2, par_ics_number) VALUES (?,?,?,?,?,?,?,?,?,?,?)';
        $bundleInsertStmt = $conn->prepare($bundleInsertSql);
        if (!$bundleInsertStmt) {
            throw new RuntimeException('Unable to prepare bundle insert statement.');
        }

        $keptExistingIds = [];
        $updatedParentItems = [];
        $setDisplayIndex = 0;
        foreach ($setKeys as $setKey) {
            $setDisplayIndex++;
            $existingId = 0;
            if (isset($existingItemIdMap[$setKey])) {
                $existingId = (int)$existingItemIdMap[$setKey];
            } elseif (isset($existingItemIdMap[(string)$setKey])) {
                $existingId = (int)$existingItemIdMap[(string)$setKey];
            }
            $existingIds = [];
            $existingIdsRaw = $existingItemIdsMap[$setKey] ?? ($existingItemIdsMap[(string)$setKey] ?? '');
            foreach (preg_split('/\s*,\s*/', (string)$existingIdsRaw, -1, PREG_SPLIT_NO_EMPTY) as $postedExistingId) {
                $postedExistingId = (int)$postedExistingId;
                if ($postedExistingId > 0 && isset($currentById[$postedExistingId]) && !in_array($postedExistingId, $existingIds, true)) {
                    $existingIds[] = $postedExistingId;
                }
            }
            if (empty($existingIds) && $existingId > 0 && isset($currentById[$existingId])) {
                $existingIds[] = $existingId;
            }
            $currentRow = ($existingId > 0 && isset($currentById[$existingId])) ? $currentById[$existingId] : null;
            $oldPropNum = strtoupper(trim((string)($currentRow['property_number'] ?? '')));
            $npidLink = $existingId > 0 ? ('NPID:' . $existingId) : '';

            $unit = strtoupper(trim($getPostedMapValue('unit', $setKey)));
            $item = strtoupper(trim($getPostedMapValue('item', $setKey)));
            $category = $normalizeCategory($getPostedMapValue('category', $setKey, $currentRow['category'] ?? ''));
            $model = strtoupper(trim($getPostedMapValue('model', $setKey)));
            $description = strtoupper(trim($getPostedMapValue('description', $setKey)));
            $accountCode = strtoupper(trim($getPostedMapValue('account_code', $setKey)));
            $remarks = strtoupper(trim($getPostedMapValue('remarks', $setKey)));
            $propertyNumber = strtoupper(trim($getPostedMapValue('property_number', $setKey)));
            $unitValue = (float)preg_replace('/[^0-9.]/', '', $getPostedMapValue('unit_value', $setKey, '0'));
            $skipAccountAndProperty = in_array(strtoupper(trim($getPostedMapValue('item_no_account_property', $setKey, '0'))), ['1', 'ON', 'YES', 'TRUE'], true);
            $employeeRaw = trim($getPostedMapValue('emp_id', $setKey));
            $newEmployeeName = strtoupper(trim($getPostedMapValue('emp_new_name', $setKey)));
            $newEmployeePosition = strtoupper(trim($getPostedMapValue('emp_new_position', $setKey)));
            $itemQuantity = $normalizeItemQuantity($getPostedMapValue('item_quantity', $setKey, '1'));

            if ($item === '' || $unit === '' || $category === '') {
                throw new RuntimeException('Each set must include category, unit, and asset class.');
            }
            if ($accountCode === '' && !$propertyNumberOptionalFund && !$skipAccountAndProperty) {
                throw new RuntimeException('Account code is required for each set.');
            }
            $parIcsNumber = $getParIcsForCategory($category);

            $oldYear = trim((string)($currentRow['date_aquired'] ?? ''));
            if (preg_match('/^\d{4}/', $oldYear, $yearMatch)) {
                $oldYear = $yearMatch[0];
            }
            $propertyInputsChanged = $currentRow && (
                strtoupper(trim((string)($currentRow['account_code'] ?? ''))) !== $accountCode
                || strtoupper(trim((string)($currentRow['category'] ?? ''))) !== $category
                || strtoupper(trim((string)($currentRow['fund'] ?? ''))) !== $fund
                || $oldYear !== $year
                || trim((string)($currentRow['department_code'] ?? '')) !== $departmentCode
            );

            $employeeId = 0;
            if (strtolower($employeeRaw) === 'add_new_emp') {
                if ($newEmployeeName === '' || $newEmployeePosition === '') {
                    throw new RuntimeException('New employee name and position are required for all add-new rows.');
                }
                $createdEmployee = gso_create_employee_atomic($conn, $newEmployeeName, $departmentCode, $newEmployeePosition, 1, null);
                if (!$createdEmployee || !isset($createdEmployee['emp_id'])) {
                    throw new RuntimeException('Unable to create employee for one of the sets.');
                }
                $employeeId = (int)$createdEmployee['emp_id'];
            } elseif ($employeeRaw !== '') {
                $employeeId = (int)$employeeRaw;
            }

            $serial1Values = [];
            $serial2Values = [];
            for ($copyIndex = 1; $copyIndex <= $itemQuantity; $copyIndex++) {
                $serial1Values[$copyIndex] = strtoupper(trim($getPostedNestedMapValue('serial_number', $setKey, $copyIndex)));
                $serial2Values[$copyIndex] = strtoupper(trim($getPostedNestedMapValue('serial_number_2', $setKey, $copyIndex)));
            }

            $currentPropertyNumber = ($propertyNumberOptionalFund || $skipAccountAndProperty) ? '' : $propertyNumber;
            if ($propertyInputsChanged && !$propertyNumberOptionalFund && !$skipAccountAndProperty) {
                $currentPropertyNumber = '';
            }
            if (!$propertyNumberOptionalFund && !$skipAccountAndProperty && $currentPropertyNumber === '') {
                $currentPropertyNumber = $generateAvailablePropertyNumber($category, $accountCode, $existingId, $oldPropNum, [], $itemQuantity);
            }

            $firstPropertyForSet = '';
            for ($copyIndex = 1; $copyIndex <= $itemQuantity; $copyIndex++) {
                $copyExistingId = (int)($existingIds[$copyIndex - 1] ?? 0);
                $copyCurrentRow = ($copyExistingId > 0 && isset($currentById[$copyExistingId])) ? $currentById[$copyExistingId] : null;
                $copyOldPropNum = strtoupper(trim((string)($copyCurrentRow['property_number'] ?? '')));
                $copyNpidLink = $copyExistingId > 0 ? ('NPID:' . $copyExistingId) : '';
                $copyPropertyNumber = ($propertyNumberOptionalFund || $skipAccountAndProperty) ? '' : $currentPropertyNumber;
                if (!$propertyNumberOptionalFund && !$skipAccountAndProperty && $copyPropertyNumber !== '') {
                    $checkId = $copyExistingId > 0 ? $copyExistingId : 0;
                    $checkOld = $copyOldPropNum;
                    $guard = 0;
                    while (gso_new_purchase_property_number_in_use($conn, $copyPropertyNumber, $checkId, $checkOld)) {
                        $nextCandidate = strtoupper(trim($nextPropertyNumber($copyPropertyNumber)));
                        if ($nextCandidate === '' || $nextCandidate === $copyPropertyNumber) {
                            $copyPropertyNumber = $generateAvailablePropertyNumber($category, $accountCode, $checkId, $checkOld, [$copyPropertyNumber]);
                            break;
                        }
                        $copyPropertyNumber = $nextCandidate;
                        $checkId = 0;
                        $checkOld = '';
                        $guard++;
                        if ($guard >= 5000) {
                            throw new RuntimeException('Unable to allocate an available property number.');
                        }
                    }
                    if ($copyIndex === 1) {
                        $currentPropertyNumber = $copyPropertyNumber;
                    }
                }

                $serial1 = $serial1Values[$copyIndex] ?? '';
                $serial2 = $serial2Values[$copyIndex] ?? '';
                $serial1Db = $serial1 !== '' ? $serial1 : null;
                $serial2Db = $serial2 !== '' ? $serial2 : null;
                $supplierDb = $supplier !== '' ? $supplier : null;
                $parIcsDb = $parIcsNumber !== '' ? $parIcsNumber : null;
                $purchaseOrderDb = $purchaseOrder !== '' ? $purchaseOrder : null;
                $purchaseRequestDb = $purchaseRequest !== '' ? $purchaseRequest : null;
                $obrNumberDb = $obrNumber !== '' ? $obrNumber : null;
                $jevNumberDb = $jevNumber !== '' ? $jevNumber : null;
                $remarksDb = $remarks !== '' ? $remarks : null;
                $propertyNumberDb = $copyPropertyNumber !== '' ? $copyPropertyNumber : null;
                if ($copyIndex === 1) {
                    $firstPropertyForSet = $copyPropertyNumber;
                }

                if ($copyExistingId > 0 && $copyCurrentRow) {
                    $itemStmt->bind_param(
                        'sssssssdsssssssssssi',
                        $unit,
                        $item,
                        $model,
                        $description,
                        $serial1Db,
                        $serial2Db,
                        $propertyNumberDb,
                        $unitValue,
                        $year,
                        $accountCode,
                        $supplierDb,
                        $parIcsDb,
                        $purchaseOrderDb,
                        $purchaseRequestDb,
                        $obrNumberDb,
                        $jevNumberDb,
                        $remarksDb,
                        $fund,
                        $category,
                        $copyExistingId
                    );
                    if (!$itemStmt->execute()) {
                        throw new RuntimeException('Unable to update item #' . $copyExistingId . ': ' . $itemStmt->error);
                    }

                    $historyParNumber = $propertyNumberOptionalFund ? $copyNpidLink : ($copyPropertyNumber !== '' ? $copyPropertyNumber : $copyNpidLink);
                    if ($copyOldPropNum !== '') {
                        $historyUpdateWithOldStmt->bind_param('ssiiiss', $historyParNumber, $category, $departmentPk, $employeeId, $employeeId, $copyOldPropNum, $copyNpidLink);
                        if (!$historyUpdateWithOldStmt->execute()) {
                            throw new RuntimeException('Unable to update history for item #' . $copyExistingId . '.');
                        }
                    } else {
                        $historyUpdateLinkStmt->bind_param('ssiiis', $historyParNumber, $category, $departmentPk, $employeeId, $employeeId, $copyNpidLink);
                        if (!$historyUpdateLinkStmt->execute()) {
                            throw new RuntimeException('Unable to update history for item #' . $copyExistingId . '.');
                        }
                    }

                    if ($copyOldPropNum !== $copyPropertyNumber && $copyOldPropNum !== '') {
                        if ($copyPropertyNumber === '' && $bundleNullStmt) {
                            $bundleNullStmt->bind_param('ssss', $copyOldPropNum, $copyOldPropNum, $copyOldPropNum, $copyOldPropNum);
                            $bundleNullStmt->execute();
                        } elseif ($bundleMoveStmt) {
                            $bundleMoveStmt->bind_param('ssssss', $copyOldPropNum, $copyPropertyNumber, $copyOldPropNum, $copyPropertyNumber, $copyOldPropNum, $copyOldPropNum);
                            $bundleMoveStmt->execute();
                        }
                    }

                    $keptExistingIds[] = $copyExistingId;
                } else {
                    $createdAt = date('Y-m-d H:i:s');
                    $insertStmt->bind_param(
                        'sssssssssdssssssssss',
                        $fund,
                        $category,
                        $unit,
                        $item,
                        $model,
                        $description,
                        $serial1Db,
                        $serial2Db,
                        $propertyNumberDb,
                        $unitValue,
                        $year,
                        $accountCode,
                        $supplierDb,
                        $parIcsDb,
                        $purchaseOrderDb,
                        $purchaseRequestDb,
                        $obrNumberDb,
                        $jevNumberDb,
                        $remarksDb,
                        $createdAt
                    );
                    if (!$insertStmt->execute()) {
                        throw new RuntimeException('Unable to create a new set: ' . $insertStmt->error);
                    }
                    $newItemId = (int)$conn->insert_id;
                    $historyParNumber = $propertyNumberOptionalFund ? ('NPID:' . $newItemId) : $copyPropertyNumber;
                    $historyCreatedAt = $createdAt;
                    $historyStatus = 1;
                    $historyInsertStmt->bind_param('iississ', $employeeId, $departmentPk, $historyParNumber, $referenceNumber, $historyStatus, $category, $historyCreatedAt);
                    if (!$historyInsertStmt->execute()) {
                        throw new RuntimeException('Unable to save history for a new set.');
                    }
                }

                if (!$propertyNumberOptionalFund && $copyIndex < $itemQuantity) {
                    $currentPropertyNumber = strtoupper(trim($nextPropertyNumber($currentPropertyNumber)));
                }
            }
            $updatedParentItems[$setDisplayIndex] = [
                'property_number' => $firstPropertyForSet,
                'emp_id' => $employeeId,
                'category' => $category,
                'item_quantity' => $itemQuantity
            ];
        }

        $removeIds = array_diff(array_keys($currentById), array_unique($keptExistingIds));
        foreach ($removeIds as $removeId) {
            $removeId = (int)$removeId;
            if ($removeId <= 0 || !isset($currentById[$removeId])) {
                continue;
            }
            $oldPropNum = strtoupper(trim((string)($currentById[$removeId]['property_number'] ?? '')));
            $npidLink = 'NPID:' . $removeId;
            if ($oldPropNum !== '' && $clearBundleStmt) {
                $clearBundleStmt->bind_param('ss', $oldPropNum, $oldPropNum);
                $clearBundleStmt->execute();
            }
            if ($deleteHistoryStmt) {
                $deleteHistoryStmt->bind_param('ss', $oldPropNum, $npidLink);
                $deleteHistoryStmt->execute();
            }
            if ($deleteItemStmt) {
                $deleteItemStmt->bind_param('i', $removeId);
                $deleteItemStmt->execute();
            }
        }

        $bundleCleanupKeys = [];
        foreach ($currentRows as $row) {
            $existingParentProperty = strtoupper(trim((string)($row['property_number'] ?? '')));
            if ($existingParentProperty !== '' && !in_array($existingParentProperty, $bundleCleanupKeys, true)) {
                $bundleCleanupKeys[] = $existingParentProperty;
            }
        }
        foreach ($updatedParentItems as $parentInfo) {
            $existingParentProperty = strtoupper(trim((string)($parentInfo['property_number'] ?? '')));
            if ($existingParentProperty !== '' && !in_array($existingParentProperty, $bundleCleanupKeys, true)) {
                $bundleCleanupKeys[] = $existingParentProperty;
            }
        }
        foreach ($bundleCleanupKeys as $cleanupProperty) {
            if ($clearBundleStmt) {
                $clearBundleStmt->bind_param('ss', $cleanupProperty, $cleanupProperty);
                $clearBundleStmt->execute();
            }
        }

        $bundleRowsCount = max(
            count($bundleCategories),
            count($bundleUnits),
            count($bundleAssets),
            count($bundleModels),
            count($bundleDescs),
            count($bundleSerial1),
            count($bundleSerial2),
            count($bundleParentIndexes),
            count($bundlePropertyNumbers)
        );
        for ($bundleIndex = 0; $bundleIndex < $bundleRowsCount; $bundleIndex++) {
            $parentIndex = (int)trim((string)($bundleParentIndexes[$bundleIndex] ?? '0'));
            $bundleCategory = strtoupper(trim((string)($bundleCategories[$bundleIndex] ?? '')));
            $bundleUnit = strtoupper(trim((string)($bundleUnits[$bundleIndex] ?? '')));
            $bundleAsset = strtoupper(trim((string)($bundleAssets[$bundleIndex] ?? '')));
            $bundleModel = strtoupper(trim((string)($bundleModels[$bundleIndex] ?? '')));
            $bundleDescription = strtoupper(trim((string)($bundleDescs[$bundleIndex] ?? '')));
            $bundlePrimarySerial = strtoupper(trim((string)($bundleSerial1[$bundleIndex] ?? '')));
            $bundleSecondarySerial = strtoupper(trim((string)($bundleSerial2[$bundleIndex] ?? '')));
            $bundlePropertyNumber = strtoupper(trim((string)($bundlePropertyNumbers[$bundleIndex] ?? '')));

            if ($parentIndex < 1 && $bundleCategory === '' && $bundleUnit === '' && $bundleAsset === '' && $bundleModel === '' && $bundleDescription === '' && $bundlePrimarySerial === '' && $bundleSecondarySerial === '') {
                continue;
            }
            if (!isset($updatedParentItems[$parentIndex])) {
                throw new RuntimeException('Invalid bundle set selected.');
            }
            if ($bundleCategory === '' || !in_array($bundleCategory, ['PAR', 'ICS'], true)) {
                throw new RuntimeException('Bundle category must be PAR or ICS.');
            }
            if ($bundleUnit === '' || $bundleAsset === '') {
                throw new RuntimeException('Bundle unit and asset class are required.');
            }

            $parentInfo = $updatedParentItems[$parentIndex];
            $bundleWithNumber = $propertyNumberOptionalFund ? null : ($parentInfo['property_number'] !== '' ? $parentInfo['property_number'] : null);
            $bundlePropertyDb = $propertyNumberOptionalFund ? null : $bundleWithNumber;
            if (!$propertyNumberOptionalFund && $bundlePropertyDb === null) {
                throw new RuntimeException('Bundle property number is required.');
            }
            $bundleParIcsNumber = $getParIcsForCategory($bundleCategory);
            $bundleEmpId = isset($parentInfo['emp_id']) ? (int)$parentInfo['emp_id'] : 0;
            $bundlePrimarySerialDb = $bundlePrimarySerial !== '' ? $bundlePrimarySerial : null;
            $bundleSecondarySerialDb = $bundleSecondarySerial !== '' ? $bundleSecondarySerial : null;

            if ($bundleHasUnitColumn) {
                $bundleInsertStmt->bind_param(
                    'iissssssssss',
                    $departmentPk,
                    $bundleEmpId,
                    $bundlePropertyDb,
                    $bundleWithNumber,
                    $bundleCategory,
                    $bundleUnit,
                    $bundleAsset,
                    $bundleModel,
                    $bundleDescription,
                    $bundlePrimarySerialDb,
                    $bundleSecondarySerialDb,
                    $bundleParIcsNumber
                );
            } else {
                $bundleInsertStmt->bind_param(
                    'iisssssssss',
                    $departmentPk,
                    $bundleEmpId,
                    $bundlePropertyDb,
                    $bundleWithNumber,
                    $bundleCategory,
                    $bundleAsset,
                    $bundleModel,
                    $bundleDescription,
                    $bundlePrimarySerialDb,
                    $bundleSecondarySerialDb,
                    $bundleParIcsNumber
                );
            }
            if (!$bundleInsertStmt->execute()) {
                throw new RuntimeException('Unable to save bundle equipment.');
            }
        }

        $itemStmt->close();
        $insertStmt->close();
        $historyInsertStmt->close();
        $historyUpdateWithOldStmt->close();
        $historyUpdateLinkStmt->close();
        if ($bundleInsertStmt) { $bundleInsertStmt->close(); }
        if ($deleteItemStmt) { $deleteItemStmt->close(); }
        if ($deleteHistoryStmt) { $deleteHistoryStmt->close(); }
        if ($clearBundleStmt) { $clearBundleStmt->close(); }
        if ($bundleNullStmt) { $bundleNullStmt->close(); }
        if ($bundleMoveStmt) { $bundleMoveStmt->close(); }
        mysqli_commit($conn);
    } catch (Throwable $e) {
        if (isset($itemStmt) && $itemStmt instanceof mysqli_stmt) {
            $itemStmt->close();
        }
        if (isset($insertStmt) && $insertStmt instanceof mysqli_stmt) {
            $insertStmt->close();
        }
        if (isset($historyInsertStmt) && $historyInsertStmt instanceof mysqli_stmt) {
            $historyInsertStmt->close();
        }
        if (isset($historyUpdateWithOldStmt) && $historyUpdateWithOldStmt instanceof mysqli_stmt) {
            $historyUpdateWithOldStmt->close();
        }
        if (isset($historyUpdateLinkStmt) && $historyUpdateLinkStmt instanceof mysqli_stmt) {
            $historyUpdateLinkStmt->close();
        }
        if (isset($bundleInsertStmt) && $bundleInsertStmt instanceof mysqli_stmt) {
            $bundleInsertStmt->close();
        }
        if (isset($deleteItemStmt) && $deleteItemStmt instanceof mysqli_stmt) {
            $deleteItemStmt->close();
        }
        if (isset($deleteHistoryStmt) && $deleteHistoryStmt instanceof mysqli_stmt) {
            $deleteHistoryStmt->close();
        }
        if (isset($clearBundleStmt) && $clearBundleStmt instanceof mysqli_stmt) {
            $clearBundleStmt->close();
        }
        if (isset($bundleNullStmt) && $bundleNullStmt instanceof mysqli_stmt) {
            $bundleNullStmt->close();
        }
        if (isset($bundleMoveStmt) && $bundleMoveStmt instanceof mysqli_stmt) {
            $bundleMoveStmt->close();
        }
        mysqli_rollback($conn);
        echo json_encode(['status' => 500, 'message' => $e->getMessage()]);
        return false;
    }

    echo json_encode(['status' => 200, 'message' => 'Purchase details updated successfully.']);
    return false;
}

if (isset($_POST['save_item'])) {
    // ---- Idempotency & duplicate prevention start ----
    $submissionToken = isset($_POST['submission_token']) ? $_POST['submission_token'] : '';
    if(!isset($_SESSION['form_tokens']['add_item'][$submissionToken])) {
        echo json_encode(['status'=>409,'message'=>'Duplicate or invalid submission token.']);
        return false;
    }
    // Mark token as used immediately (idempotent) then unset it to prevent reuse
    unset($_SESSION['form_tokens']['add_item'][$submissionToken]);
    // Optional: garbage collect old tokens (>30 min)
    foreach($_SESSION['form_tokens']['add_item'] as $tk=>$ts){ if($ts < time()-1800){ unset($_SESSION['form_tokens']['add_item'][$tk]); } }
    // ---- Idempotency & duplicate prevention end ----

    $conditionRaw = strtoupper(trim((string)($_POST['condition'] ?? '')));
    if (!in_array($conditionRaw, ['NEW', 'EXISTING'], true)) {
        echo json_encode(['status' => 422, 'message' => 'Please select a valid condition (NEW or EXISTING).']);
        return false;
    }
    $noEndUser = ($conditionRaw === 'NEW' && isset($_POST['endUserNoneCheckBox']) && (string)$_POST['endUserNoneCheckBox'] === '1');

    $categoryInput = (isset($_POST['category']) && !is_array($_POST['category'])) ? (string)$_POST['category'] : '';
    $yearInput = isset($_POST['year']) ? (string)$_POST['year'] : '';
    $assetInput = (isset($_POST['asset']) && !is_array($_POST['asset'])) ? (string)$_POST['asset'] : '';
    $brandInput = (isset($_POST['brand']) && !is_array($_POST['brand'])) ? (string)$_POST['brand'] : '';
    $descriptionInput = (isset($_POST['description']) && !is_array($_POST['description'])) ? (string)$_POST['description'] : '';
    $remarksInput = (isset($_POST['remarks']) && !is_array($_POST['remarks'])) ? (string)$_POST['remarks'] : '';
    $fundInput = isset($_POST['fund']) ? (string)$_POST['fund'] : '';
    $unitInput = (isset($_POST['unit']) && !is_array($_POST['unit'])) ? (string)$_POST['unit'] : '';
    $unitvalueInput = (isset($_POST['unitvalue']) && !is_array($_POST['unitvalue'])) ? (string)$_POST['unitvalue'] : '0.00';

    $category = mysqli_real_escape_string($conn, strtoupper(trim($categoryInput)));
    $year = mysqli_real_escape_string($conn, trim($yearInput));
    $asset = mysqli_real_escape_string($conn, strtoupper(trim($assetInput)));
    $brand = mysqli_real_escape_string($conn, strtoupper(trim($brandInput)));
    $description = mysqli_real_escape_string($conn, strtoupper(trim($descriptionInput)));
    $remarks = trim($remarksInput) === '' ? 'NULL' : "'" . mysqli_real_escape_string($conn, strtoupper(trim($remarksInput))) . "'";
    $fund = mysqli_real_escape_string($conn, strtoupper(trim($fundInput)));
    $unit = trim($unitInput) === '' ? 'NULL' : "'" . mysqli_real_escape_string($conn, strtoupper(trim($unitInput))) . "'";
    // Quantity (bulk rows)
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
    if ($quantity < 1) { $quantity = 1; }
    if ($quantity > 100) {
        echo json_encode(['status' => 422, 'message' => 'A maximum of 100 item sets is allowed per submission.']);
        return false;
    }

    $itemUnitRows = (isset($_POST['unit']) && is_array($_POST['unit'])) ? $_POST['unit'] : null;
    $itemAssetRows = (isset($_POST['asset']) && is_array($_POST['asset'])) ? $_POST['asset'] : null;
    $itemBrandRows = (isset($_POST['brand']) && is_array($_POST['brand'])) ? $_POST['brand'] : null;
    $itemDescriptionRows = (isset($_POST['description']) && is_array($_POST['description'])) ? $_POST['description'] : null;
    $itemCategoryRows = (isset($_POST['category']) && is_array($_POST['category'])) ? $_POST['category'] : null;
    $itemQuantityRows = (isset($_POST['item_quantity']) && is_array($_POST['item_quantity'])) ? $_POST['item_quantity'] : null;
    $itemUnitvalueRows = (isset($_POST['unitvalue']) && is_array($_POST['unitvalue'])) ? $_POST['unitvalue'] : null;
    $itemRemarksRows = (isset($_POST['remarks']) && is_array($_POST['remarks'])) ? $_POST['remarks'] : null;
    $itemAccountCodeRows = (isset($_POST['account_code']) && is_array($_POST['account_code'])) ? $_POST['account_code'] : null;
    $itemPropertyNumberRows = (isset($_POST['property_numbers']) && is_array($_POST['property_numbers'])) ? $_POST['property_numbers'] : null;
    $itemNoAccountPropertyRows = (isset($_POST['item_no_account_property']) && is_array($_POST['item_no_account_property'])) ? $_POST['item_no_account_property'] : null;

    $resolveRowInput = function (?array $rows, int $rowIndex, string $fallback = ''): string {
        if ($rows === null) {
            return $fallback;
        }
        if (isset($rows[$rowIndex])) {
            return (string)$rows[$rowIndex];
        }
        if (isset($rows[(string)$rowIndex])) {
            return (string)$rows[(string)$rowIndex];
        }
        if (isset($rows[$rowIndex - 1])) {
            return (string)$rows[$rowIndex - 1];
        }
        if (isset($rows[(string)($rowIndex - 1)])) {
            return (string)$rows[(string)($rowIndex - 1)];
        }
        return $fallback;
    };
    $resolveRowUpper = function (?array $rows, int $rowIndex, string $fallback = '') use ($resolveRowInput): string {
        return strtoupper(trim($resolveRowInput($rows, $rowIndex, $fallback)));
    };
    $resolveRowNullableUpper = function (?array $rows, int $rowIndex, string $fallback = '') use ($resolveRowInput): ?string {
        $value = strtoupper(trim($resolveRowInput($rows, $rowIndex, $fallback)));
        return $value === '' ? null : $value;
    };
    $resolveRowMoney = function (?array $rows, int $rowIndex, string $fallback = '0.00') use ($resolveRowInput): float {
        return (float)preg_replace('/[^0-9.]/', '', $resolveRowInput($rows, $rowIndex, $fallback));
    };
    $resolveRowInt = function (?array $rows, int $rowIndex, int $fallback = 1) use ($resolveRowInput): int {
        $value = (int)trim($resolveRowInput($rows, $rowIndex, (string)$fallback));
        return $value > 0 ? $value : $fallback;
    };
    $resolveRowBool = function (?array $rows, int $rowIndex, bool $fallback = false): bool {
        if ($rows === null) {
            return $fallback;
        }
        $keys = [$rowIndex, (string)$rowIndex, $rowIndex - 1, (string)($rowIndex - 1)];
        foreach ($keys as $key) {
            if (isset($rows[$key])) {
                $value = strtoupper(trim((string)$rows[$key]));
                return in_array($value, ['1', 'ON', 'YES', 'TRUE'], true);
            }
        }
        return $fallback;
    };
    $resolveRowCategory = function (?array $rows, int $rowIndex, string $fallback = '') use ($resolveRowInput): string {
        $value = strtoupper(trim($resolveRowInput($rows, $rowIndex, $fallback)));
        return in_array($value, ['PAR', 'ICS'], true) ? $value : '';
    };
    $totalRequestedItems = 0;
    for ($rowIndex = 1; $rowIndex <= $quantity; $rowIndex++) {
        $totalRequestedItems += $resolveRowInt($itemQuantityRows, $rowIndex, 1);
    }
    if ($totalRequestedItems > 5000) {
        echo json_encode(['status' => 422, 'message' => 'A maximum of 5,000 total quantity is allowed per submission.']);
        return false;
    }
    // Determine PAR/ICS number input (manual only used when quantity==1)
    $par_ics_input_raw = isset($_POST['par_ics_no']) ? trim($_POST['par_ics_no']) : '';
    // Prepare serials: use per-row arrays if present; otherwise single values
    $serialArr = (isset($_POST['serial']) && is_array($_POST['serial'])) ? $_POST['serial'] : null;
    $serial2Arr = (isset($_POST['serial2']) && is_array($_POST['serial2'])) ? $_POST['serial2'] : null;
    $snid1Single = (isset($_POST['serial']) && !is_array($_POST['serial']) && $_POST['serial'] !== '') ? "'" . mysqli_real_escape_string($conn,strtoupper($_POST['serial'])) . "'" : 'NULL';
    $snid2Single = (isset($_POST['serial2']) && !is_array($_POST['serial2']) && $_POST['serial2'] !== '') ? "'" . mysqli_real_escape_string($conn,strtoupper($_POST['serial2'])) . "'" : 'NULL';
    $snid1SingleValue = (isset($_POST['serial']) && !is_array($_POST['serial'])) ? strtoupper(trim((string)$_POST['serial'])) : null;
    $snid2SingleValue = (isset($_POST['serial2']) && !is_array($_POST['serial2'])) ? strtoupper(trim((string)$_POST['serial2'])) : null;
    $resolveSerialValue = function (?array $rows, int $rowIndex, int $copyIndex) {
        if ($rows === null) { return null; }
        $rowKeys = [$rowIndex, (string)$rowIndex, $rowIndex - 1, (string)($rowIndex - 1)];
        $copyKeys = [$copyIndex, (string)$copyIndex, $copyIndex - 1, (string)($copyIndex - 1)];
        foreach ($rowKeys as $rowKey) {
            if (!isset($rows[$rowKey])) { continue; }
            $rowValue = $rows[$rowKey];
            if (is_array($rowValue)) {
                foreach ($copyKeys as $copyKey) {
                    if (!isset($rowValue[$copyKey])) { continue; }
                    $value = strtoupper(trim((string)$rowValue[$copyKey]));
                    return $value === '' ? null : $value;
                }
                return null;
            }
            if ($copyIndex === 1) {
                $value = strtoupper(trim((string)$rowValue));
                return $value === '' ? null : $value;
            }
            return null;
        }
        return null;
    };
    $unitvalue = $resolveRowMoney($itemUnitvalueRows, 1, $unitvalueInput);
    $accountCodeInput = (isset($_POST['account_code']) && !is_array($_POST['account_code'])) ? (string)$_POST['account_code'] : '';
    $accountCodeNormalized = strtoupper(trim($resolveRowInput($itemAccountCodeRows, 1, $accountCodeInput)));
    $omitAccountPropertyFirstRow = ($conditionRaw === 'NEW' && $resolveRowBool($itemNoAccountPropertyRows, 1, false));
    if ($accountCodeNormalized === '' && !$omitAccountPropertyFirstRow) {
        echo json_encode(['status' => 422, 'message' => 'Account code is required.']);
        return false;
    }
    $account_code = mysqli_real_escape_string($conn, $accountCodeNormalized);
    $purchaseOrderRaw = strtoupper(trim((string)($_POST['po'] ?? '')));
    if ($conditionRaw === 'NEW' && $purchaseOrderRaw === '') {
        echo json_encode(['status' => 422, 'message' => 'P.O is required.']);
        return false;
    }
    if ($conditionRaw === 'NEW' && !preg_match('/^\d{5,8}$/', $purchaseOrderRaw)) {
        echo json_encode(['status' => 422, 'message' => 'P.O must contain 5 to 8 digits for NEW items.']);
        return false;
    }
    $pr = empty($_POST['pr']) ? 'NULL' : "'" . mysqli_real_escape_string($conn,strtoupper($_POST['pr'])) . "'";
    $supplier = empty($_POST['supplier']) ? 'NULL' : "'" . mysqli_real_escape_string($conn,strtoupper($_POST['supplier'])) . "'";
    $po = $purchaseOrderRaw === '' ? 'NULL' : "'" . mysqli_real_escape_string($conn, $purchaseOrderRaw) . "'";
    $obr = empty($_POST['obr']) ? 'NULL' : "'" . mysqli_real_escape_string($conn,strtoupper($_POST['obr'])) . "'";
    $deptCode = strtoupper(trim((string)($_POST['dept'] ?? '')));
    if ($deptCode === '') {
        echo json_encode(['status' => 422, 'message' => 'Department is required.']);
        return false;
    }
    $dept = mysqli_real_escape_string($conn, $deptCode);
    $deptIdInt = null;
    if ($stmtDept = mysqli_prepare($conn, 'SELECT dept_id FROM department WHERE department_code = ? LIMIT 1')) {
        mysqli_stmt_bind_param($stmtDept, 's', $deptCode);
        mysqli_stmt_execute($stmtDept);
        $resDept = mysqli_stmt_get_result($stmtDept);
        if ($resDept && mysqli_num_rows($resDept) === 1) {
            $rowDept = mysqli_fetch_assoc($resDept);
            $deptIdInt = isset($rowDept['dept_id']) ? (int)$rowDept['dept_id'] : null;
        }
        mysqli_stmt_close($stmtDept);
    }
    if ($deptIdInt === null) {
        echo json_encode(['status' => 422, 'message' => 'Invalid department selected.']);
        return false;
    }
    $parEmpRaw = isset($_POST['parEmp']) ? $_POST['parEmp'] : '';
    $parEmpUpper = strtoupper(trim($parEmpRaw));
    $parEmpMulti = (isset($_POST['parEmp_multi']) && is_array($_POST['parEmp_multi'])) ? $_POST['parEmp_multi'] : null;
    $parEmpMultiNewName = (isset($_POST['parEmp_multi_new_name']) && is_array($_POST['parEmp_multi_new_name'])) ? $_POST['parEmp_multi_new_name'] : [];
    $parEmpMultiNewPos  = (isset($_POST['parEmp_multi_new_position']) && is_array($_POST['parEmp_multi_new_position'])) ? $_POST['parEmp_multi_new_position'] : [];
    // Normalize property number: textarea may contain comma-separated preview; use the first value only
    $par_number_raw = isset($_POST['par_number']) ? trim($_POST['par_number']) : '';
    if ($par_number_raw !== '' && strpos($par_number_raw, ',') !== false) {
        $par_number_raw = trim(explode(',', $par_number_raw)[0]);
    }
    $par_number = mysqli_real_escape_string($conn, $par_number_raw);
    $jev = empty($_POST['jev']) ? 'NULL' : "'" . mysqli_real_escape_string($conn,strtoupper($_POST['jev'])) . "'";
    $status = 1;
    $cityDepartment = 1; // CITY DEPARTMENT
    $institution = 2; // INSTITUTION
    $institutions = $institution; // alias for clarity / user wording

    // Determine employee id (create if sentinel ADD_NEW_EMP selected)
    if ($noEndUser) {
        $emp_id_final = null;
        $parEmpMulti = null;
    } elseif ($parEmpUpper === 'ADD_NEW_EMP') {
        $new_emp_name = mysqli_real_escape_string($conn, strtoupper(trim($_POST['new_emp'])));
        $position = mysqli_real_escape_string($conn, strtoupper(trim($_POST['position'])));
        // Duplicate name check (case-insensitive)
        $dupCheck = mysqli_query($conn, "SELECT 1 FROM employee WHERE UPPER(emp_name)= '$new_emp_name' LIMIT 1");
        if ($dupCheck && mysqli_num_rows($dupCheck) > 0) {
            echo json_encode(['status'=>422,'message'=>'Employee name already exists.']);
            return false;
        }
        $created = gso_create_employee_atomic($conn, $new_emp_name, $dept, $position, $status, null);
        if(!isset($created['ok']) || !$created['ok']){
            echo json_encode(['status'=>500,'message'=>$created['message'] ?? 'Error creating employee.']);
            return false;
        }
        $emp_id_final = (string)$created['emp_id'];
    } else {
        $emp_id_final = mysqli_real_escape_string($conn, $parEmpRaw);
    }

    // NEW purchase flow:
    // - Save only to NEW purchase tables:
    //   new_purchase, new_purchase_history, new_bundle_purchase
    // - Generate a single reference_number for printing
    if ($conditionRaw === 'NEW') {
        $referenceNumber = generateReferenceNumber($conn, 'new_purchase_history', 'reference_number');

        $fundNorm = strtoupper(trim((string)$fund));
        $isValidFund = in_array($fundNorm, ['GF', 'GENERAL FUND', 'SEF', 'SF', 'SPECIAL EDUCATION FUND', 'TRUST FUND', 'DONATION'], true);
        if (!$isValidFund) {
            echo json_encode(['status' => 422, 'message' => 'Invalid fund selected.']);
            return false;
        }
        $isTrustFund = ($fundNorm === 'TRUST FUND');
        $isDonation = ($fundNorm === 'DONATION');
        $propertyNumberOptionalFund = ($isTrustFund || $isDonation);

        $existsParNumber = function($num) use ($conn){
            if (trim((string)$num) === '') { return false; }
            $n = mysqli_real_escape_string($conn, (string)$num);
            $gf = mysqli_query($conn, "SELECT 1 FROM par_gen_fund WHERE par_number='$n' LIMIT 1");
            if ($gf && mysqli_num_rows($gf) > 0) return true;
            $sf = mysqli_query($conn, "SELECT 1 FROM property_sef WHERE property_number='$n' LIMIT 1");
            if ($sf && mysqli_num_rows($sf) > 0) return true;
            $np = mysqli_query($conn, "SELECT 1 FROM new_purchase WHERE property_number='$n' LIMIT 1");
            return ($np && mysqli_num_rows($np) > 0);
        };
        $nextParNumber = function($current){
            $parts = explode('-', (string)$current);
            if (count($parts) < 4) return (string)$current;
            $seqIdx = count($parts) - 2;
            $seq = $parts[$seqIdx];
            $len = strlen($seq);
            $num = (int)$seq + 1;
            $parts[$seqIdx] = str_pad((string)$num, $len, '0', STR_PAD_LEFT);
            return implode('-', $parts);
        };

        // PAR/ICS number generation (single MAX lookup per request)
        $manualProvided = ($par_ics_input_raw !== '' && strtoupper($par_ics_input_raw) !== 'NULL');
        $parIcsCodeByCategory = [];
        $getParIcsForCategory = function($rowCategory) use (&$parIcsCodeByCategory, $conn) {
            $rowCategory = strtoupper(trim((string)$rowCategory));
            if (!in_array($rowCategory, ['PAR', 'ICS'], true)) {
                throw new RuntimeException('Invalid category for PAR/ICS number generation.');
            }
            if (!isset($parIcsCodeByCategory[$rowCategory])) {
                $ym = date('Ym');
                $letter = ($rowCategory === 'ICS') ? 'I' : 'P';
                $prefix = $ym . '-' . $letter;
                $prefEsc = mysqli_real_escape_string($conn, $prefix);
                $max = 0;
                $sqlMax = "SELECT MAX(CAST(SUBSTRING(par_ics_number, LENGTH('$prefEsc') + 1) AS UNSIGNED)) AS max_sfx FROM new_purchase WHERE par_ics_number LIKE CONCAT('$prefEsc','%')";
                $resMax = mysqli_query($conn, $sqlMax);
                if ($resMax && mysqli_num_rows($resMax) === 1) {
                    $row = mysqli_fetch_assoc($resMax);
                    if ($row && $row['max_sfx'] !== null) { $max = max($max, (int)$row['max_sfx']); }
                }
                $parIcsCodeByCategory[$rowCategory] = $prefix . sprintf('%04d', ($max + 1));
            }
            return $parIcsCodeByCategory[$rowCategory];
        };

        $deptCode = strtoupper(trim((string)$dept));
        $deptIdInt = null;
        if ($stmtDept = mysqli_prepare($conn, 'SELECT dept_id FROM department WHERE department_code = ? LIMIT 1')) {
            mysqli_stmt_bind_param($stmtDept, 's', $deptCode);
            mysqli_stmt_execute($stmtDept);
            $resDept = mysqli_stmt_get_result($stmtDept);
            if ($resDept && mysqli_num_rows($resDept) === 1) {
                $rowDept = mysqli_fetch_assoc($resDept);
                $deptIdInt = isset($rowDept['dept_id']) ? (int)$rowDept['dept_id'] : null;
            }
            mysqli_stmt_close($stmtDept);
        }
        if ($deptIdInt === null) {
            echo json_encode(['status' => 422, 'message' => 'Invalid department selected.']);
            return false;
        }

        $statusActive = 1;
        $createdAt = $today;

        // Bundle equipment rows (admin/add-item.php)
        // These are optional. If present, they should be persisted alongside the main NEW purchase.
        $bundleCategories = (isset($_POST['bundle_category']) && is_array($_POST['bundle_category'])) ? $_POST['bundle_category'] : [];
        $bundleUnits = (isset($_POST['bundle_unit']) && is_array($_POST['bundle_unit'])) ? $_POST['bundle_unit'] : [];
        $bundleAssets = (isset($_POST['bundle_asset_class']) && is_array($_POST['bundle_asset_class'])) ? $_POST['bundle_asset_class'] : [];
        $bundleModels = (isset($_POST['bundle_brand_model']) && is_array($_POST['bundle_brand_model'])) ? $_POST['bundle_brand_model'] : [];
        $bundleDescs = (isset($_POST['bundle_description']) && is_array($_POST['bundle_description'])) ? $_POST['bundle_description'] : [];
        $bundleSerial1 = (isset($_POST['bundle_serial1']) && is_array($_POST['bundle_serial1'])) ? $_POST['bundle_serial1'] : [];
        $bundleSerial2 = (isset($_POST['bundle_serial2']) && is_array($_POST['bundle_serial2'])) ? $_POST['bundle_serial2'] : [];
        $bundleParentIndexes = (isset($_POST['bundle_parent_index']) && is_array($_POST['bundle_parent_index'])) ? $_POST['bundle_parent_index'] : [];

        $hasBundleRows = false;
        if (!empty($bundleCategories)) {
            foreach ($bundleCategories as $bc) {
                if (trim((string)$bc) !== '') { $hasBundleRows = true; break; }
            }
        }

        // Prepared inserts for NEW purchase tables
        $stmtNewPurchase = mysqli_prepare(
            $conn,
            'INSERT INTO new_purchase (fund,category,unit,item,model,description,serial_number,serial_number_2,property_number,unit_value,date_aquired,account_code,supplier,par_ics_number,purchase_order,purchase_request,obr_number,jev_number,remarks,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        if (!$stmtNewPurchase) {
            echo json_encode(['status' => 500, 'message' => 'Unable to prepare new purchase insert.']);
            return false;
        }
        $stmtNewPurchaseHist = mysqli_prepare(
            $conn,
            'INSERT INTO new_purchase_history (emp_id,dept_id,par_number,reference_number,status,category,created_at) VALUES (?,?,?,?,?,?,?)'
        );
        if (!$stmtNewPurchaseHist) {
            mysqli_stmt_close($stmtNewPurchase);
            echo json_encode(['status' => 500, 'message' => 'Unable to prepare new purchase history insert.']);
            return false;
        }

        $stmtNewBundlePurchase = null;
        if ($hasBundleRows) {
            $bundleHasUnitColumn = gso_column_exists($conn, 'new_bundle_purchase', 'unit');
            $bundleInsertSql = $bundleHasUnitColumn
                ? 'INSERT INTO new_bundle_purchase (dept_id, emp_id, property_number, bundle_with, category, unit, item, model, description, serial_number, serial_number_2, par_ics_number) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
                : 'INSERT INTO new_bundle_purchase (dept_id, emp_id, property_number, bundle_with, category, item, model, description, serial_number, serial_number_2, par_ics_number) VALUES (?,?,?,?,?,?,?,?,?,?,?)';
            $stmtNewBundlePurchase = mysqli_prepare($conn, $bundleInsertSql);
            if (!$stmtNewBundlePurchase) {
                mysqli_stmt_close($stmtNewPurchaseHist);
                mysqli_stmt_close($stmtNewPurchase);
                echo json_encode(['status' => 500, 'message' => 'Unable to prepare bundle purchase insert.']);
                return false;
            }
        }

        // If first par_number is already taken, bump to next available
        $guardNew = 0;
        while ($par_number !== '' && $existsParNumber($par_number) && $guardNew < 20000) { $par_number = $nextParNumber($par_number); $guardNew++; }

        $parentPropertyNumbers = [];
        $parentEmpIds = [];
        $insertedCount = 0;
        $printRefs = ['PAR' => [], 'ICS' => []];

        mysqli_begin_transaction($conn);
        try {
            for ($i=1; $i <= $quantity; $i++) {
                // Per-row employee handling (same logic as existing)
                $emp_for_row = $emp_id_final;
                if ($parEmpMulti !== null) {
                    $valRaw = '';
                    if (isset($parEmpMulti[$i])) { $valRaw = (string)$parEmpMulti[$i]; }
                    elseif (isset($parEmpMulti[(string)$i])) { $valRaw = (string)$parEmpMulti[(string)$i]; }
                    elseif (isset($parEmpMulti[$i-1])) { $valRaw = (string)$parEmpMulti[$i-1]; }
                    elseif (isset($parEmpMulti[(string)($i-1)])) { $valRaw = (string)$parEmpMulti[(string)($i-1)]; }
                    $valRaw = trim($valRaw);
                    if ($valRaw === '') {
                        throw new Exception('End user is required for row ' . $i . '.');
                    }
                    $valUp = strtoupper($valRaw);
                    if ($valUp === 'ADD_NEW_EMP') {
                        $nmRaw = '';
                        if (isset($parEmpMultiNewName[$i])) { $nmRaw = (string)$parEmpMultiNewName[$i]; }
                        elseif (isset($parEmpMultiNewName[(string)$i])) { $nmRaw = (string)$parEmpMultiNewName[(string)$i]; }
                        elseif (isset($parEmpMultiNewName[$i-1])) { $nmRaw = (string)$parEmpMultiNewName[$i-1]; }
                        elseif (isset($parEmpMultiNewName[(string)($i-1)])) { $nmRaw = (string)$parEmpMultiNewName[(string)($i-1)]; }
                        $psRaw = '';
                        if (isset($parEmpMultiNewPos[$i])) { $psRaw = (string)$parEmpMultiNewPos[$i]; }
                        elseif (isset($parEmpMultiNewPos[(string)$i])) { $psRaw = (string)$parEmpMultiNewPos[(string)$i]; }
                        elseif (isset($parEmpMultiNewPos[$i-1])) { $psRaw = (string)$parEmpMultiNewPos[$i-1]; }
                        elseif (isset($parEmpMultiNewPos[(string)($i-1)])) { $psRaw = (string)$parEmpMultiNewPos[(string)($i-1)]; }
                        $nm = strtoupper(trim($nmRaw));
                        $ps = strtoupper(trim($psRaw));
                        if ($nm === '') {
                            throw new Exception('New employee name is required for row ' . $i . '.');
                        }
                        if ($ps === '') { $ps = 'N/A'; }
                        $nmEsc = mysqli_real_escape_string($conn, $nm);
                        $dupId = null;
                        $dupRes = mysqli_query($conn, "SELECT emp_id FROM employee WHERE UPPER(emp_name)='{$nmEsc}' LIMIT 1");
                        if ($dupRes && mysqli_num_rows($dupRes) === 1) {
                            $dupRow = mysqli_fetch_assoc($dupRes);
                            $dupId = $dupRow ? (string)$dupRow['emp_id'] : null;
                        }
                        if ($dupId !== null && $dupId !== '') {
                            $emp_for_row = $dupId;
                        } else {
                            $createdRow = gso_create_employee_atomic($conn, $nm, $dept, $ps, $status, null);
                            if(!isset($createdRow['ok']) || !$createdRow['ok']){
                                throw new Exception($createdRow['message'] ?? ('Error creating employee for row '.$i));
                            }
                            $emp_for_row = (string)$createdRow['emp_id'];
                        }
                    } else {
                        $emp_for_row = mysqli_real_escape_string($conn, $valRaw);
                    }
                }

                if (!$noEndUser && ($emp_for_row === '' || $emp_for_row === null)) {
                    throw new Exception('End user is required.');
                }

                $categoryForRow = $resolveRowCategory($itemCategoryRows, $i, $categoryInput);
                if ($categoryForRow === '') {
                    throw new Exception('Category is required for row ' . $i . '.');
                }
                $omitAccountPropertyForRow = $resolveRowBool($itemNoAccountPropertyRows, $i, false);
                $skipPropertyNumberForRow = $propertyNumberOptionalFund || $omitAccountPropertyForRow;
                $accountCodeForRow = $resolveRowUpper($itemAccountCodeRows, $i, $accountCodeInput);
                if ($accountCodeForRow === '' && !$omitAccountPropertyForRow) {
                    throw new Exception('Account code is required for row ' . $i . '.');
                }
                if ($propertyNumberOptionalFund && $accountCodeForRow !== '' && !preg_match('/^[0-9-]{1,20}$/', $accountCodeForRow)) {
                    throw new Exception('Account code for TRUST FUND and DONATION must contain numbers and hyphen only, up to 20 characters (row ' . $i . ').');
                }

                $parNumberForSet = strtoupper(trim($resolveRowInput($itemPropertyNumberRows, $i, ($i === 1 ? $par_number_raw : ''))));
                if ($parNumberForSet === '' && !$skipPropertyNumberForRow) {
                    throw new Exception('Property number is required for row ' . $i . '.');
                }
                if ($omitAccountPropertyForRow) {
                    $accountCodeForRow = null;
                    $par_number = null;
                } elseif ($propertyNumberOptionalFund) {
                    $par_number = null;
                } else {
                    $par_number = mysqli_real_escape_string($conn, $parNumberForSet);
                    $guardSet = 0;
                    while ($existsParNumber($par_number) && $guardSet < 20000) { $par_number = $nextParNumber($par_number); $guardSet++; }
                }

                $itemForRow = $resolveRowUpper($itemAssetRows, $i, $assetInput);
                $brandForRow = $resolveRowUpper($itemBrandRows, $i, $brandInput);
                $descriptionForRow = $resolveRowUpper($itemDescriptionRows, $i, $descriptionInput);
                $unitForRow = $resolveRowNullableUpper($itemUnitRows, $i, $unitInput);
                $unitValueForRow = $resolveRowMoney($itemUnitvalueRows, $i, $unitvalueInput);
                $remarksForRow = $resolveRowNullableUpper($itemRemarksRows, $i, $remarksInput);
                $itemQuantityForRow = $resolveRowInt($itemQuantityRows, $i, 1);

                if ($itemForRow === '' || $brandForRow === '' || $descriptionForRow === '' || $unitForRow === null) {
                    throw new Exception('Item Information is incomplete for row ' . $i . '.');
                }

                $empForRowInt = $noEndUser ? null : (int)$emp_for_row;
                $firstParNumberForSet = null;

                for ($copyIndex = 1; $copyIndex <= $itemQuantityForRow; $copyIndex++) {
                    if (!$skipPropertyNumberForRow && !($i === 1 && $copyIndex === 1)) {
                        $guard2 = 0;
                        do { $par_number = $nextParNumber($par_number); $guard2++; } while ($existsParNumber($par_number) && $guard2 < 20000);
                    }

                    if ($firstParNumberForSet === null) {
                        $firstParNumberForSet = $par_number;
                    }

                    if ($manualProvided) {
                        $parIcsForRow = strtoupper($par_ics_input_raw);
                    } else {
                        $parIcsForRow = $getParIcsForCategory($categoryForRow);
                    }

                    $fundForPurchase = strtoupper(trim((string)($_POST['fund'] ?? '')));
                    $accountCodeForPurchase = $accountCodeForRow;
                    $unitForPurchase = $unitForRow;
                    $serial1ForPurchase = $resolveSerialValue($serialArr, $i, $copyIndex);
                    $serial2ForPurchase = $resolveSerialValue($serial2Arr, $i, $copyIndex);
                    if ($serial1ForPurchase === null && $copyIndex === 1 && $snid1SingleValue !== null && $snid1SingleValue !== '') { $serial1ForPurchase = $snid1SingleValue; }
                    if ($serial2ForPurchase === null && $copyIndex === 1 && $snid2SingleValue !== null && $snid2SingleValue !== '') { $serial2ForPurchase = $snid2SingleValue; }
                    $remarksForPurchase = $remarksForRow;
                    $supplierForPurchase = empty($_POST['supplier']) ? null : strtoupper(trim((string)$_POST['supplier']));
                    $poForPurchase = empty($_POST['po']) ? null : strtoupper(trim((string)$_POST['po']));
                    $prForPurchase = empty($_POST['pr']) ? null : strtoupper(trim((string)$_POST['pr']));
                    $obrForPurchase = empty($_POST['obr']) ? null : strtoupper(trim((string)$_POST['obr']));
                    $jevForPurchase = empty($_POST['jev']) ? null : strtoupper(trim((string)$_POST['jev']));

                    mysqli_stmt_bind_param(
                        $stmtNewPurchase,
                        'sssssssssdssssssssss',
                        $fundForPurchase,
                        $categoryForRow,
                        $unitForPurchase,
                        $itemForRow,
                        $brandForRow,
                        $descriptionForRow,
                        $serial1ForPurchase,
                        $serial2ForPurchase,
                        $par_number,
                        $unitValueForRow,
                        $year,
                        $accountCodeForPurchase,
                        $supplierForPurchase,
                        $parIcsForRow,
                        $poForPurchase,
                        $prForPurchase,
                        $obrForPurchase,
                        $jevForPurchase,
                        $remarksForPurchase,
                        $createdAt
                    );
                    if (!mysqli_stmt_execute($stmtNewPurchase)) {
                        throw new Exception('New purchase insert failed.');
                    }
                    $newPurchaseId = (int)mysqli_insert_id($conn);
                    $historyParNumber = $par_number;
                    if ($skipPropertyNumberForRow && $newPurchaseId > 0) {
                        $historyParNumber = 'NPID:' . $newPurchaseId;
                    }

                    mysqli_stmt_bind_param(
                        $stmtNewPurchaseHist,
                        'iississ',
                        $empForRowInt,
                        $deptIdInt,
                        $historyParNumber,
                        $referenceNumber,
                        $statusActive,
                        $categoryForRow,
                        $createdAt
                    );
                    if (!mysqli_stmt_execute($stmtNewPurchaseHist)) {
                        throw new Exception('New purchase history insert failed.');
                    }

                    $insertedCount++;
                    if (!in_array($referenceNumber, $printRefs[$categoryForRow], true)) {
                        $printRefs[$categoryForRow][] = $referenceNumber;
                    }
                }

                $parentPropertyNumbers[$i] = $firstParNumberForSet;
                $parentEmpIds[$i] = $empForRowInt;
            }

            // Save bundle equipment rows (if any)
            if ($hasBundleRows) {
                $rowsCount = max(count($bundleCategories), count($bundleUnits), count($bundleAssets), count($bundleDescs), count($bundleParentIndexes));
                for ($j = 0; $j < $rowsCount; $j++) {
                    $bCat = strtoupper(trim((string)($bundleCategories[$j] ?? '')));
                    $bUnit = strtoupper(trim((string)($bundleUnits[$j] ?? '')));
                    $bAsset = strtoupper(trim((string)($bundleAssets[$j] ?? '')));
                    $bModel = strtoupper(trim((string)($bundleModels[$j] ?? '')));
                    $bDesc = strtoupper(trim((string)($bundleDescs[$j] ?? '')));
                    $bS1 = strtoupper(trim((string)($bundleSerial1[$j] ?? '')));
                    $bS2 = strtoupper(trim((string)($bundleSerial2[$j] ?? '')));

                    // Skip completely empty row shells
                    if ($bAsset === '' && $bDesc === '' && $bModel === '' && $bCat === '' && $bUnit === '') {
                        continue;
                    }
                    if ($bCat === '' || !in_array($bCat, ['PAR','ICS'], true)) {
                        throw new Exception('Bundle category must be PAR or ICS (row '.($j+1).').');
                    }
                    if ($bUnit === '') {
                        throw new Exception('Bundle unit is required (row '.($j+1).').');
                    }
                    if ($bAsset === '') {
                        throw new Exception('Bundle asset class is required (row '.($j+1).').');
                    }

                    $parentIdxRaw = trim((string)($bundleParentIndexes[$j] ?? ''));
                    if ($parentIdxRaw === '') {
                        $parentIdxRaw = '1';
                    }
                    if (!ctype_digit($parentIdxRaw)) {
                        throw new Exception('Bundle set is invalid (row '.($j+1).').');
                    }
                    $parentIdx = (int)$parentIdxRaw;
                    if ($parentIdx < 1 || $parentIdx > $quantity) {
                        throw new Exception('Bundle set is out of range (row '.($j+1).').');
                    }
                    if (!$propertyNumberOptionalFund && (!isset($parentPropertyNumbers[$parentIdx]) || trim((string)$parentPropertyNumbers[$parentIdx]) === '')) {
                        throw new Exception('Unable to resolve parent item for bundle row '.($j+1).'.');
                    }
                    $bundleWithNumber = $propertyNumberOptionalFund ? null : (string)$parentPropertyNumbers[$parentIdx];

                    // Bundle uses the same property number as its parent
                    $bPar = $bundleWithNumber;

                    // Generate PAR/ICS number per bundle item
                    if ($manualProvided) {
                        $parIcsForBundle = strtoupper($par_ics_input_raw);
                    } else {
                        $parIcsForBundle = $getParIcsForCategory($bCat);
                    }
                    // (No inventory-table inserts for NEW condition)

                    $empBundleInt = $noEndUser ? null : (isset($parentEmpIds[$parentIdx]) ? (int)$parentEmpIds[$parentIdx] : (int)$emp_id_final);
                    $serial1ForPurchase = ($bS1 === '') ? null : $bS1;
                    $serial2ForPurchase = ($bS2 === '') ? null : $bS2;

                    // new_bundle_purchase insert (link bundle item -> parent and keep bundle details)
                    if ($bundleHasUnitColumn) {
                        mysqli_stmt_bind_param(
                            $stmtNewBundlePurchase,
                            'iissssssssss',
                            $deptIdInt,
                            $empBundleInt,
                            $bPar,
                            $bundleWithNumber,
                            $bCat,
                            $bUnit,
                            $bAsset,
                            $bModel,
                            $bDesc,
                            $serial1ForPurchase,
                            $serial2ForPurchase,
                            $parIcsForBundle
                        );
                    } else {
                        mysqli_stmt_bind_param(
                            $stmtNewBundlePurchase,
                            'iisssssssss',
                            $deptIdInt,
                            $empBundleInt,
                            $bPar,
                            $bundleWithNumber,
                            $bCat,
                            $bAsset,
                            $bModel,
                            $bDesc,
                            $serial1ForPurchase,
                            $serial2ForPurchase,
                            $parIcsForBundle
                        );
                    }
                    if (!mysqli_stmt_execute($stmtNewBundlePurchase)) {
                        throw new Exception('Bundle purchase link insert failed.');
                    }
                }
            }

            mysqli_commit($conn);

            mysqli_stmt_close($stmtNewPurchaseHist);
            mysqli_stmt_close($stmtNewPurchase);
            if ($stmtNewBundlePurchase) { mysqli_stmt_close($stmtNewBundlePurchase); }

            echo json_encode([
                'status' => 200,
                'message' => 'Added Successfully!',
                'count' => $insertedCount,
                'data' => [
                    'condition' => 'NEW',
                    'reference_number' => $referenceNumber,
                    'should_print' => true,
                    'par_refs' => $printRefs['PAR'],
                    'ics_refs' => $printRefs['ICS'],
                ]
            ]);
            return false;
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            mysqli_stmt_close($stmtNewPurchaseHist);
            mysqli_stmt_close($stmtNewPurchase);
            if (isset($stmtNewBundlePurchase) && $stmtNewBundlePurchase) { mysqli_stmt_close($stmtNewBundlePurchase); }
            echo json_encode(['status' => 500, 'message' => 'Property not saved. Error: ' . $e->getMessage()]);
            return false;
        }
    }

    // Helpers: increment property number string (keep padding), and existence checks
    $existsParNumber = function($num) use ($conn){
        $n = mysqli_real_escape_string($conn, $num);
        $gf = mysqli_query($conn, "SELECT 1 FROM par_gen_fund WHERE par_number='$n' LIMIT 1");
        if ($gf && mysqli_num_rows($gf) > 0) return true;
        $sf = mysqli_query($conn, "SELECT 1 FROM property_sef WHERE property_number='$n' LIMIT 1");
        if ($sf && mysqli_num_rows($sf) > 0) return true;
        $tf = mysqli_query($conn, "SELECT 1 FROM trust_fund WHERE property_number='$n' LIMIT 1");
        if ($tf && mysqli_num_rows($tf) > 0) return true;
        $dn = mysqli_query($conn, "SELECT 1 FROM donation WHERE property_number='$n' LIMIT 1");
        return ($dn && mysqli_num_rows($dn) > 0);
    };
    $nextParNumber = function($current){
        $parts = explode('-', $current);
        if (count($parts) < 4) return $current; // guard
        $seqIdx = count($parts) - 2;
        $seq = $parts[$seqIdx];
        $len = strlen($seq);
        $num = (int)$seq + 1;
        $parts[$seqIdx] = str_pad((string)$num, $len, '0', STR_PAD_LEFT);
        return implode('-', $parts);
    };

    $fundUpper = strtoupper($fund);
    $isGF = in_array($fundUpper, ['GF','GENERAL FUND'], true);
    $isSEF = in_array($fundUpper, ['SEF','SF','SPECIAL EDUCATION FUND'], true);
    $isTrustFund = ($fundUpper === 'TRUST FUND');
    $isDonation = ($fundUpper === 'DONATION');
    $propertyNumberOptionalFund = ($isTrustFund || $isDonation);
    if(!$isGF && !$isSEF && !$isTrustFund && !$isDonation){ echo json_encode(['status'=>422,'message'=>'Invalid fund selected.']); return false; }

    // Safety limits for very large submissions (server-side)
    if ($quantity > 5000) {
        echo json_encode(['status'=>422,'message'=>'Quantity is too large. Please add in smaller batches (max 5000 per submission).']);
        return false;
    }
    // PHP often defaults to max_input_vars=1000; per-row inputs (serials/multi end-user) can exceed this.
    $usesPerRowInputs = ($serialArr !== null || $serial2Arr !== null || $parEmpMulti !== null);
    if ($usesPerRowInputs && $quantity > 200) {
        echo json_encode(['status'=>422,'message'=>'For per-row Serial / Multiple End User mode, please submit in smaller batches (recommended ≤ 200).']);
        return false;
    }

    // Manual mode: if user provided a value, use it; duplicates allowed.
    // For quantity > 1, manual disables sequencing (others will be NULL).
    $manualProvided = ($par_ics_input_raw !== '' && strtoupper($par_ics_input_raw) !== 'NULL');
    $useManualParIcsForFirst = ($quantity === 1 && $manualProvided);

    // If first par_number is already taken, bump to next available
    $guard = 0;
    while ($existsParNumber($par_number) && $guard < 20000) { $par_number = $nextParNumber($par_number); $guard++; }

    // Improve stability for large quantity by extending time limit (best-effort)
    if ($quantity >= 250) { @set_time_limit(300); }

    // PAR/ICS generator optimization:
    // The old generateParIcsNumber() scans MAX() each call; for bulk adds this becomes very slow.
    $parIcsCodeByCategory = [];
    $getParIcsForCategory = function($rowCategory) use (&$parIcsCodeByCategory, $conn) {
        $rowCategory = strtoupper(trim((string)$rowCategory));
        if (!in_array($rowCategory, ['PAR', 'ICS'], true)) {
            throw new RuntimeException('Invalid category for PAR/ICS number generation.');
        }
        if (!isset($parIcsCodeByCategory[$rowCategory])) {
            $ym = date('Ym');
            $letter = ($rowCategory === 'ICS') ? 'I' : 'P';
            $prefix = $ym . '-' . $letter;
            $prefEsc = mysqli_real_escape_string($conn, $prefix);
            $max = 0;
            $sqlGf = "SELECT MAX(CAST(SUBSTRING(par_ics_number, LENGTH('$prefEsc') + 1) AS UNSIGNED)) AS max_sfx FROM par_gen_fund WHERE par_ics_number LIKE CONCAT('$prefEsc','%')";
            $resGf = mysqli_query($conn, $sqlGf);
            if ($resGf && mysqli_num_rows($resGf) === 1) {
                $row = mysqli_fetch_assoc($resGf);
                if ($row && $row['max_sfx'] !== null) { $max = max($max, (int)$row['max_sfx']); }
            }
            $sqlSef = "SELECT MAX(CAST(SUBSTRING(par_ics_number, LENGTH('$prefEsc') + 1) AS UNSIGNED)) AS max_sfx FROM property_sef WHERE par_ics_number LIKE CONCAT('$prefEsc','%')";
            $resSef = mysqli_query($conn, $sqlSef);
            if ($resSef && mysqli_num_rows($resSef) === 1) {
                $row = mysqli_fetch_assoc($resSef);
                if ($row && $row['max_sfx'] !== null) { $max = max($max, (int)$row['max_sfx']); }
            }
            foreach (['trust_fund', 'donation'] as $fundTable) {
                $sqlFund = "SELECT MAX(CAST(SUBSTRING(par_ics_number, LENGTH('$prefEsc') + 1) AS UNSIGNED)) AS max_sfx FROM {$fundTable} WHERE par_ics_number LIKE CONCAT('$prefEsc','%')";
                $resFund = mysqli_query($conn, $sqlFund);
                if ($resFund && mysqli_num_rows($resFund) === 1) {
                    $row = mysqli_fetch_assoc($resFund);
                    if ($row && $row['max_sfx'] !== null) { $max = max($max, (int)$row['max_sfx']); }
                }
            }
            $parIcsCodeByCategory[$rowCategory] = $prefix . sprintf('%04d', ($max + 1));
        }
        return $parIcsCodeByCategory[$rowCategory];
    };

    // Build and execute inserts per quantity using batching + transaction
    $batchSize = 100;
    $multi = '';
    $batchItems = 0;
    $execMulti = function($sql) use ($conn){
        if (trim((string)$sql) === '') { return true; }
        if (!mysqli_multi_query($conn, $sql)) { return false; }
        // Flush all results to avoid "commands out of sync" on next multi_query
        do {
            if ($res = mysqli_store_result($conn)) {
                mysqli_free_result($res);
            }
        } while (mysqli_more_results($conn) && mysqli_next_result($conn));
        return (mysqli_errno($conn) === 0);
    };

    mysqli_begin_transaction($conn);
    $nextManualFundId = function($mainTable, $historyTable) use ($conn) {
        $mainTable = preg_replace('/[^A-Za-z0-9_]/', '', (string)$mainTable);
        $historyTable = preg_replace('/[^A-Za-z0-9_]/', '', (string)$historyTable);
        $sql = "SELECT LEAST(COALESCE((SELECT MIN(id) FROM {$mainTable}), 0), COALESCE((SELECT MIN(id) FROM {$historyTable}), 0), 0) - 1 AS next_id";
        $res = mysqli_query($conn, $sql);
        if ($res && mysqli_num_rows($res) === 1) {
            $row = mysqli_fetch_assoc($res);
            return (int)($row['next_id'] ?? -1);
        }
        return -1;
    };
    $nextTrustFundId = $isTrustFund ? $nextManualFundId('trust_fund', 'trust_fund_history') : null;
    $nextDonationId = $isDonation ? $nextManualFundId('donation', 'donation_history') : null;
    $referenceNumberByCategory = [];
    $printRefs = ['PAR' => [], 'ICS' => []];
    $getReferenceNumberForCategory = function($rowCategory) use ($conn, &$referenceNumberByCategory) {
        $rowCategory = strtoupper(trim((string)$rowCategory));
        if (!in_array($rowCategory, ['PAR', 'ICS'], true)) {
            throw new RuntimeException('Invalid category for reference number generation.');
        }
        if (!isset($referenceNumberByCategory[$rowCategory])) {
            $referenceNumberByCategory[$rowCategory] = generateReferenceNumber($conn, 'general_fund_property_history', 'reference_number');
        }
        return $referenceNumberByCategory[$rowCategory];
    };
    $insertedCount = 0;
    for ($i=1; $i <= $quantity; $i++) {
        // per-row employee: handle add_new_emp with inline name/position
        $emp_for_row = $emp_id_final;
        if ($parEmpMulti !== null) {
            // Multi end-user mode: require an end user for every row.
            // Accept both 1-based and 0-based indexing (defensive).
            $valRaw = '';
            if (isset($parEmpMulti[$i])) { $valRaw = (string)$parEmpMulti[$i]; }
            elseif (isset($parEmpMulti[(string)$i])) { $valRaw = (string)$parEmpMulti[(string)$i]; }
            elseif (isset($parEmpMulti[$i-1])) { $valRaw = (string)$parEmpMulti[$i-1]; }
            elseif (isset($parEmpMulti[(string)($i-1)])) { $valRaw = (string)$parEmpMulti[(string)($i-1)]; }
            $valRaw = trim($valRaw);
            if ($valRaw === '') {
                echo json_encode(['status'=>422,'message'=>'End user is required for row '.$i.'.']);
                return false;
            }
            $valUp = strtoupper($valRaw);
            if ($valUp === 'ADD_NEW_EMP') {
                // Read per-row name/position (accept 1-based, 0-based, and string keys)
                $nmRaw = '';
                if (isset($parEmpMultiNewName[$i])) { $nmRaw = (string)$parEmpMultiNewName[$i]; }
                elseif (isset($parEmpMultiNewName[(string)$i])) { $nmRaw = (string)$parEmpMultiNewName[(string)$i]; }
                elseif (isset($parEmpMultiNewName[$i-1])) { $nmRaw = (string)$parEmpMultiNewName[$i-1]; }
                elseif (isset($parEmpMultiNewName[(string)($i-1)])) { $nmRaw = (string)$parEmpMultiNewName[(string)($i-1)]; }
                $psRaw = '';
                if (isset($parEmpMultiNewPos[$i])) { $psRaw = (string)$parEmpMultiNewPos[$i]; }
                elseif (isset($parEmpMultiNewPos[(string)$i])) { $psRaw = (string)$parEmpMultiNewPos[(string)$i]; }
                elseif (isset($parEmpMultiNewPos[$i-1])) { $psRaw = (string)$parEmpMultiNewPos[$i-1]; }
                elseif (isset($parEmpMultiNewPos[(string)($i-1)])) { $psRaw = (string)$parEmpMultiNewPos[(string)($i-1)]; }

                $nm = strtoupper(trim($nmRaw));
                $ps = strtoupper(trim($psRaw));
                if ($nm === '') {
                    echo json_encode(['status'=>422,'message'=>'New employee name is required for row '.$i.'.']);
                    return false;
                }
                if ($ps === '') { $ps = 'N/A'; }

                if ($nm !== '') {
                    $nmEsc = mysqli_real_escape_string($conn, $nm);
                    $psEsc = mysqli_real_escape_string($conn, $ps);
                    $deptEsc = mysqli_real_escape_string($conn, $dept);
                    // If name exists, reuse existing emp_id instead of inventing a new id
                    $dupId = null;
                    $dupRes = mysqli_query($conn, "SELECT emp_id FROM employee WHERE UPPER(emp_name)='{$nmEsc}' LIMIT 1");
                    if ($dupRes && mysqli_num_rows($dupRes) === 1) {
                        $dupRow = mysqli_fetch_assoc($dupRes);
                        $dupId = $dupRow ? (string)$dupRow['emp_id'] : null;
                    }
                    if ($dupId !== null && $dupId !== '') {
                        $emp_for_row = $dupId;
                    } else {
                        $createdRow = gso_create_employee_atomic($conn, $nm, $dept, $ps, $status, null);
                        if(!isset($createdRow['ok']) || !$createdRow['ok']){
                            echo json_encode(['status'=>500,'message'=>$createdRow['message'] ?? ('Error creating employee for row '.$i)]);
                            return false;
                        }
                        $emp_for_row = (string)$createdRow['emp_id'];
                    }
                }
            } else {
                $emp_for_row = mysqli_real_escape_string($conn, $valRaw);
            }
        }

        if ($emp_for_row === '' || $emp_for_row === null) {
            echo json_encode(['status'=>422,'message'=>'End user is required.']);
            return false;
        }

        $accountCodeForRow = $resolveRowUpper($itemAccountCodeRows, $i, $accountCodeInput);
        $categoryForRow = $resolveRowCategory($itemCategoryRows, $i, $categoryInput);
        if ($categoryForRow === '') {
            mysqli_rollback($conn);
            echo json_encode(['status'=>422,'message'=>'Category is required for row '.$i.'.']);
            return false;
        }
        if ($accountCodeForRow === '') {
            mysqli_rollback($conn);
            echo json_encode(['status'=>422,'message'=>'Account code is required for row '.$i.'.']);
            return false;
        }
        if (($isTrustFund || $isDonation) && !preg_match('/^[0-9-]{1,20}$/', $accountCodeForRow)) {
            mysqli_rollback($conn);
            echo json_encode(['status'=>422,'message'=>'Account code for Trust Fund and Donation must contain numbers and hyphen only, up to 20 characters (row '.$i.').']);
            return false;
        }

        $parNumberForSet = strtoupper(trim($resolveRowInput($itemPropertyNumberRows, $i, ($i === 1 ? $par_number_raw : ''))));
        if (!$propertyNumberOptionalFund && $parNumberForSet === '') {
            mysqli_rollback($conn);
            echo json_encode(['status'=>422,'message'=>'Property number is required for row '.$i.'.']);
            return false;
        }
        if ($propertyNumberOptionalFund) {
            $par_number = null;
        } else {
            $par_number = mysqli_real_escape_string($conn, $parNumberForSet);
            $guardSet = 0;
            while ($existsParNumber($par_number) && $guardSet < 20000) { $par_number = $nextParNumber($par_number); $guardSet++; }
        }

        $itemForRow = $resolveRowUpper($itemAssetRows, $i, $assetInput);
        $brandForRow = $resolveRowUpper($itemBrandRows, $i, $brandInput);
        $descriptionForRow = $resolveRowUpper($itemDescriptionRows, $i, $descriptionInput);
        $unitForRow = $resolveRowNullableUpper($itemUnitRows, $i, $unitInput);
        $unitValueForRow = $resolveRowMoney($itemUnitvalueRows, $i, $unitvalueInput);
        $remarksForRow = $resolveRowNullableUpper($itemRemarksRows, $i, $remarksInput);
        $itemQuantityForRow = $resolveRowInt($itemQuantityRows, $i, 1);

        if ($itemForRow === '' || $brandForRow === '' || $descriptionForRow === '' || $unitForRow === null) {
            mysqli_rollback($conn);
            echo json_encode(['status'=>422,'message'=>'Item Information is incomplete for row '.$i.'.']);
            return false;
        }

        $itemSql = mysqli_real_escape_string($conn, $itemForRow);
        $brandSql = mysqli_real_escape_string($conn, $brandForRow);
        $descriptionSql = mysqli_real_escape_string($conn, $descriptionForRow);
        $accountCodeSql = mysqli_real_escape_string($conn, $accountCodeForRow);
        $unitSql = "'" . mysqli_real_escape_string($conn, $unitForRow) . "'";
        $remarksSql = ($remarksForRow === null) ? 'NULL' : "'" . mysqli_real_escape_string($conn, $remarksForRow) . "'";

        for ($copyIndex = 1; $copyIndex <= $itemQuantityForRow; $copyIndex++) {
            if (!$propertyNumberOptionalFund && !($i === 1 && $copyIndex === 1)) {
                $guard2 = 0;
                do { $par_number = $nextParNumber($par_number); $guard2++; } while ($existsParNumber($par_number) && $guard2 < 20000);
            }

            if ($manualProvided) {
                $parIcsForRow = strtoupper($par_ics_input_raw);
            } else {
                $parIcsForRow = $getParIcsForCategory($categoryForRow);
            }
            $par_ics_sql = ($parIcsForRow === '' ? 'NULL' : "'" . mysqli_real_escape_string($conn, $parIcsForRow) . "'");
            $referenceNumberForRow = $getReferenceNumberForCategory($categoryForRow);
            $serial1ForCopy = $resolveSerialValue($serialArr, $i, $copyIndex);
            $serial2ForCopy = $resolveSerialValue($serial2Arr, $i, $copyIndex);
            if ($serial1ForCopy === null && $copyIndex === 1 && $snid1SingleValue !== null && $snid1SingleValue !== '') { $serial1ForCopy = $snid1SingleValue; }
            if ($serial2ForCopy === null && $copyIndex === 1 && $snid2SingleValue !== null && $snid2SingleValue !== '') { $serial2ForCopy = $snid2SingleValue; }
            $s1 = ($serial1ForCopy === null) ? 'NULL' : "'" . mysqli_real_escape_string($conn, $serial1ForCopy) . "'";
            $s2 = ($serial2ForCopy === null) ? 'NULL' : "'" . mysqli_real_escape_string($conn, $serial2ForCopy) . "'";

            if ($isGF) {
                $multi .= "INSERT INTO par_gen_fund(category,item,model,description,serial_number,serial_number_2,par_number,unit,unit_value,date_aquired,account_code,fund,supplier,par_ics_number,purchase_order,purchase_request,obr_number,jev_number,remarks) VALUES('".$categoryForRow."','".$itemSql."','".$brandSql."','".$descriptionSql."',$s1,$s2,'".$par_number."',$unitSql,'".$unitValueForRow."','".$year."','".$accountCodeSql."','".mysqli_real_escape_string($conn,$fund)."',$supplier,$par_ics_sql,$po,$pr,$obr,$jev,$remarksSql);";
                $multi .= "INSERT INTO general_fund_property_history(emp_id,dept_id,par_number,reference_number,status,category,created_at) VALUES('".$emp_for_row."','".$deptIdInt."','".$par_number."','".mysqli_real_escape_string($conn, $referenceNumberForRow)."','".$status."','".$categoryForRow."','".$today."');";
            } elseif ($isSEF) {
                $multi .= "INSERT INTO property_sef(category,item,model,description,serial_number,serial_number_2,property_number,unit,unit_value,date_aquired,account_code,fund,supplier,par_ics_number,purchase_order,purchase_request,obr_number,jev_number,remarks) VALUES('".$categoryForRow."','".$itemSql."','".$brandSql."','".$descriptionSql."',$s1,$s2,'".$par_number."',$unitSql,'".$unitValueForRow."','".$year."','".$accountCodeSql."','".mysqli_real_escape_string($conn,$fund)."',$supplier,$par_ics_sql,$po,$pr,$obr,$jev,$remarksSql);";
                $multi .= "INSERT INTO sef_property_history(emp_id,sch_id,property_number,reference_number,status,category,created_at) VALUES('".$emp_for_row."','".$dept."','".$par_number."','".mysqli_real_escape_string($conn, $referenceNumberForRow)."','".$status."','".$categoryForRow."','".$today."');";
            } elseif ($isTrustFund) {
                $fundRecordId = $nextTrustFundId;
                $nextTrustFundId--;
                $historyParNumber = mysqli_real_escape_string($conn, 'NPID:' . $fundRecordId);
                $multi .= "INSERT INTO trust_fund(id,fund,category,unit,item,model,description,serial_number,serial_number_2,property_number,unit_value,date_aquired,account_code,supplier,par_ics_number,purchase_order,purchase_request,obr_number,jev_number,remarks,created_at) VALUES('".$fundRecordId."','TRUST FUND','".$categoryForRow."',$unitSql,'".$itemSql."','".$brandSql."','".$descriptionSql."',$s1,$s2,NULL,'".$unitValueForRow."','".$year."','".$accountCodeSql."',$supplier,$par_ics_sql,$po,$pr,$obr,$jev,$remarksSql,'".$today."');";
                $multi .= "INSERT INTO trust_fund_history(id,emp_id,dept_id,par_number,reference_number,status,category,created_at) VALUES('".$fundRecordId."','".$emp_for_row."','".$deptIdInt."','".$historyParNumber."','".mysqli_real_escape_string($conn, $referenceNumberForRow)."','".$status."','".$categoryForRow."','".$today."');";
            } else {
                $fundRecordId = $nextDonationId;
                $nextDonationId--;
                $historyParNumber = mysqli_real_escape_string($conn, 'NPID:' . $fundRecordId);
                $multi .= "INSERT INTO donation(id,fund,category,unit,item,model,description,serial_number,serial_number_2,property_number,unit_value,date_aquired,account_code,supplier,par_ics_number,purchase_order,purchase_request,obr_number,jev_number,remarks,created_at) VALUES('".$fundRecordId."','DONATION','".$categoryForRow."',$unitSql,'".$itemSql."','".$brandSql."','".$descriptionSql."',$s1,$s2,NULL,'".$unitValueForRow."','".$year."','".$accountCodeSql."',$supplier,$par_ics_sql,$po,$pr,$obr,$jev,$remarksSql,'".$today."');";
                $multi .= "INSERT INTO donation_history(id,emp_id,dept_id,par_number,reference_number,status,category,created_at) VALUES('".$fundRecordId."','".$emp_for_row."','".$deptIdInt."','".$historyParNumber."','".mysqli_real_escape_string($conn, $referenceNumberForRow)."','".$status."','".$categoryForRow."','".$today."');";
            }

            if (!in_array($referenceNumberForRow, $printRefs[$categoryForRow], true)) {
                $printRefs[$categoryForRow][] = $referenceNumberForRow;
            }

            $batchItems++;
            $insertedCount++;
            if ($batchItems >= $batchSize) {
                if (!$execMulti($multi)) {
                    $err = mysqli_error($conn);
                    mysqli_rollback($conn);
                    echo json_encode(['status'=>500,'message'=>'Property not saved. Error: '.$err]);
                    return false;
                }
                $multi = '';
                $batchItems = 0;
            }
        }
    }

    if (!$execMulti($multi)) {
        $err = mysqli_error($conn);
        mysqli_rollback($conn);
        echo json_encode(['status'=>500,'message'=>'Property not saved. Error: '.$err]);
        return false;
    }
    mysqli_commit($conn);
    echo json_encode([
        'status' => 200,
        'message' => 'Added Successfully!',
        'count' => $insertedCount,
        'data' => [
            'condition' => 'EXISTING',
            'should_print' => true,
            'par_refs' => $printRefs['PAR'],
            'ics_refs' => $printRefs['ICS'],
        ]
    ]);
    return false;
}

//to fetch i.c.s general fund information
if(isset($_GET['getIcsParId'])){
    $getIcsId = mysqli_real_escape_string($conn,$_GET['getIcsParId']);

    $sql = "SELECT * FROM ics_gen_fund WHERE par_number = '$getIcsId' LIMIT 1";
    $query = mysqli_query($conn, $sql);

    if (mysqli_num_rows($query) == 1) {
        $returnItem = mysqli_fetch_array($query);

        $res = [
            'status' => 200,
            'message' => 'ics general fund id fetch successfully',
            'data' => $returnItem,
        ];
        echo json_encode($res);
        return false;
    } else {
        $res = [
            'status' => 422,
            'message' => 'No ics general fund id found',
        ];
        echo json_encode($res);
        return false;
    }

}


//add ics sef property
if (isset($_POST['save_sefics_item'])) {
    $enduser = mysqli_real_escape_string($conn, $_POST['enduser']);
    $position = mysqli_real_escape_string($conn, $_POST['position']);
    $department = mysqli_real_escape_string($conn, $_POST['department']);
    $pon = mysqli_real_escape_string($conn, $_POST['po']);
    $prn = mysqli_real_escape_string($conn, $_POST['pr']);
    $obrn = mysqli_real_escape_string($conn, $_POST['obr']);
    $supplier = mysqli_real_escape_string($conn, $_POST['supplier']);
    $date = mysqli_real_escape_string($conn, $_POST['date']);
    $refn = mysqli_real_escape_string($conn, $_POST['refno']);
    $category = "I.C.S";
    $status = 1;

    $sql = "INSERT INTO end_users (end_user,position,reference_number)
    VALUES ('$enduser','$position','$refn')";
    $query = mysqli_query($conn, $sql);
    if ($query) {
        $euid = $conn->insert_id;
        $sql2 =
            'INSERT INTO ics_property_sef (item,description,par_number,unit_value,date_aquired,account_code,supplier,purchase_order,obr_number,purchase_request,reference_number) VALUES ';
        $rows = [];
        for ($i = 0; $i < count($_POST['item']); $i++) {
            $category = mysqli_real_escape_string($conn, $_POST['item'][$i]);
            $description = mysqli_real_escape_string($conn,$_POST['description'][$i]);
            $pnum = mysqli_real_escape_string($conn,$_POST['property_number'][$i]);
            $price = mysqli_real_escape_string($conn, $_POST['uvalue'][$i]);
            $code = mysqli_real_escape_string($conn, $_POST['acode'][$i]);
            $rows[] = "('$category','$description','$pnum','$price','$date','$code','$supplier','$pon','$obrn','$prn','$refn')";
        }
        $sql2 .= implode(',', $rows);
        $query2 = mysqli_query($conn, $sql2);
        if ($query2) {
            $sql3 =
                'INSERT INTO general_fund_property_history (euid,school_code,property_number,reference_number,status,category) VALUES ';
            $rows2 = [];
            for ($i = 0; $i < count($_POST['item']); $i++) {
                $pnum = mysqli_real_escape_string($conn,$_POST['property_number'][$i]);
                $rows2[] = "('$euid','$department','$pnum','$refn',$status,'$category')";
            }
            $sql3 .= implode(',', $rows2);
            $query3 = mysqli_query($conn, $sql3);
            if ($query3) {
                $res = [
                    'status' => 200,
                    'message' => 'Added Successfully!',
                ];
                echo json_encode($res);
                return false;
            }
        }
    }
}

//insert and restore property
if (isset($_POST['save_retitem'])) {
    $rid = mysqli_real_escape_string($conn, $_POST['rid']);
    $par = mysqli_real_escape_string($conn, $_POST['par']);
    $enduser = mysqli_real_escape_string($conn, $_POST['enduser']);
    $dept = mysqli_real_escape_string($conn, $_POST['dept']);
    $stat = 1;

    $sql = "INSERT INTO item_history (par_number,end_user,department_code,status) VALUES ('$par','$enduser','$dept','$stat'); INSERT INTO general_fund SELECT * FROM return_item WHERE id = '$rid'; DELETE FROM return_item WHERE id = '$rid' ";
    $query = mysqli_multi_query($conn, $sql);

    if ($query) {
        $res = [
            'status' => 200,
            'message' => 'Succesfully restore!',
        ];
        echo json_encode($res);
        return false;
    } else {
        $res = [
            'status' => 500,
            'message' => 'opps..something went wrong..',
        ];
        echo json_encode($res);
        return false;
    }
}
//to view ics general fund details
if(isset($_POST['viewIcsBtn'])){
    $icsid = $_POST['icsnumberid'];
    $sql = "SELECT * FROM ics_gen_fund WHERE icsgf_id = '$icsid' ";
    $query = mysqli_query($conn, $sql);
    if (mysqli_num_rows($query) > 0) {
        foreach ($query as $row) {
            $amount = $row['unit_value'];
            $unitvalue ="₱".number_format($amount,2,".",",");
            echo $return =
        '<table class="table table-bordered">
            <tr>
                <td class="text-secondary" colspan="6"><b>Model/Brand</b></td>
                <td colspan="8"><b>'.$row['model'].'</b></td>
            </tr>
            <tr>
                <td class="text-secondary" colspan="6"><b>S/N (A)</b></td>
                <td colspan="4"><b>'.$row['serial_number'].'</b></td>
                <td class="text-secondary" colspan="2"><b>Description</b></td>
            </tr>
            <tr>
                <td class="text-secondary" colspan="6"><b>S/N (B)</b></td>
                <td colspan="4"><b>'.$row['serial_number_2'].'</b></td>
                <td rowspan="3"><b>'.$row['description'].'</b></td>
            </tr>
            <tr>
                <td class="text-secondary" colspan="6"><b>Unit Value</b></td>
                <td colspan="4"><b>'.$unitvalue.'</b></td>
            </tr>
            <tr>
                <td class="text-secondary" colspan="6"><b>Year Aquired</b></td>
                <td colspan="4"><b>'.date('Y',strtotime($row['date_aquired'])).'</b></td>
            </tr> 
            <tr>
                <td class="text-secondary" colspan="6"><b>Suppiler</b></td>
                <td colspan="8"><b>'.$row['supplier'].'</b></td>
            </tr>
            <tr>
                <td class="text-secondary" colspan="6"><b>Purchase Order</b></td>
                <td colspan="8"><b>'.$row['purchase_order'].'</b></td>
            </tr>
            <tr>
                <td class="text-secondary" colspan="6"><b>Purchase Request</b></td>
                <td colspan="8"><b>'.$row['purchase_request'].'</b></td>
            </tr>
            <tr>
                <td class="text-secondary" colspan="6"><b>O.B.R Number</b></td>
                <td colspan="8"><b>'.$row['obr_number'].'</b></td>
            </tr>
            <tr>
                <td class="text-secondary" colspan="6"><b>Account Code</b></td>
                <td colspan="8"><b>'.$row['account_code'].'</b></td>
            </tr>
        </table>';
        }
    }
}
//to view par general fund details
if (isset($_POST['viewProperBtn'])) {
    $parid = $_POST['parnumberid'];
    $actvty = "Viewed the data of ".$parid;
    $sql = "SELECT * FROM par_gen_fund WHERE par_number = '$parid' ";
    $query = mysqli_query($conn, $sql);
    if (mysqli_num_rows($query) > 0) {
        mysqli_query($conn, "INSERT INTO activity_log(admin_id,ip_address,activity) VALUES('$uid','$uip','$actvty')");
        foreach ($query as $row) {
            $amount = $row['unit_value'];
            $unitvalue ="₱".number_format($amount,2,".",",");
            echo $return =
        '<table class="table table-bordered">
            <tr>
                <td class="text-secondary" colspan="6"><b>Model/Brand</b></td>
                <td colspan="8"><b>'.$row['model'].'</b></td>
            </tr>
            <tr>
                <td class="text-secondary" colspan="6"><b>SNID NO.1</b></td>
                <td colspan="4"><b>'.$row['serial_number'].'</b></td>
                <td class="text-secondary" colspan="2"><b>Description</b></td>
            </tr>
            <tr>
                <td class="text-secondary" colspan="6"><b>SNID NO.2</b></td>
                <td colspan="4"><b>'.$row['serial_number_2'].'</b></td>
                <td rowspan="3"><b>'.$row['description'].'</b></td>
            </tr>
            <tr>
                <td class="text-secondary" colspan="6"><b>Unit Value</b></td>
                <td colspan="4"><b>'.$unitvalue.'</b></td>
            </tr>
            <tr>
                <td class="text-secondary" colspan="6"><b>Year Aquired</b></td>
                <td colspan="4"><b>'.date('Y',strtotime($row['date_aquired'])).'</b></td>
            </tr> 
            <tr>
                <td class="text-secondary" colspan="6"><b>Suppiler</b></td>
                <td colspan="8"><b>'.$row['supplier'].'</b></td>
            </tr>
            <tr>
                <td class="text-secondary" colspan="6"><b>Purchase Order</b></td>
                <td colspan="8"><b>'.$row['purchase_order'].'</b></td>
            </tr>
            <tr>
                <td class="text-secondary" colspan="6"><b>Purchase Request</b></td>
                <td colspan="8"><b>'.$row['purchase_request'].'</b></td>
            </tr>
            <tr>
                <td class="text-secondary" colspan="6"><b>O.B.R Number</b></td>
                <td colspan="8"><b>'.$row['obr_number'].'</b></td>
            </tr>
            <tr>
                <td class="text-secondary" colspan="6"><b>Account Code</b></td>
                <td colspan="8"><b>'.$row['account_code'].'</b></td>
            </tr>
        </table>';
        }
    }
}
//view i.c.s gen fund property history
if (isset($_POST['viewIcsHistoryBtn'])) {
    $icshistoryid = $_POST['icshnumberid'];
    $safeIcsHistoryId = mysqli_real_escape_string($conn, $icshistoryid);
    $sql = "SELECT general_fund_property_history.par_number,general_fund_property_history.emp_id,general_fund_property_history.dept_id,general_fund_property_history.status,general_fund_property_history.created_at,
    employee.department_code AS employee_department_code, employee.emp_id,CONCAT(employee.first_name,' ',employee.last_name) as name 
    FROM general_fund_property_history JOIN employee ON general_fund_property_history.emp_id = employee.emp_id 
    WHERE general_fund_property_history.par_number = '$safeIcsHistoryId' AND general_fund_property_history.status = '0' LIMIT 10 ";
    $query = mysqli_query($conn, $sql);
    $cnt = 1;
    if (mysqli_num_rows($query) > 0) {
        echo $return = '      
        <div class="row invoice-info mb-3 mt-3">
        <div class="col-sm-4 invoice-col">
          <strong>Property history</strong>
        </div>
      </div>

      <div class="row">
        <div class="col-12 table-responsive">
          <table class="table table-striped">
            <thead>
            <tr class="bg-dark text-light bg-gradient bg-opacity-150">
              <th>No.</th>
              <th>Previous user</th>
              <th>Department</th>
              <th>Date</th>
            </tr>
            </thead>
            <tbody>';

        foreach ($query as $result) {
            $departmentRow = gso_resolve_history_department($conn, $result['dept_id'] ?? '', $result['employee_department_code'] ?? '');
            echo $return =
                '<tr>
                    <td>'.$cnt.'</td>
                    <td>'.$result['name'].'</td>
                    <td>'.htmlspecialchars($departmentRow['department_name'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</td>
                    <td>'.date('M-d-Y',strtotime($result['created_at'])).'</td>
                </tr>
                ';
            $cnt++;
        }
        echo $return = '</tbody>
        </table>
      </div>
      <!-- /.col -->
    </div>
    <!-- /.row -->';
    } else {
        echo $return = ' <div class="row invoice-info mb-3 mt-3">
        <div class="col-sm-4 invoice-col">
          <strong>Property history</strong>
        </div>
      </div>

      <!-- Table row -->
      <div class="row">
        <div class="col-12 table-responsive">
          <table class="table table-striped">
            <thead>
            <tr class="bg-dark text-light bg-gradient bg-opacity-150">
              <th>No.</th>
              <th>Previous user</th>
              <th>Department</th>
              <th>Date</th>
            </tr>
            </thead>
            <tbody>
        
        <tr class="text-center">
        <td colspan="4" ><h6>No History For This Property</h6></td>
        </tr>
        
        </table>
      </div>
      <!-- /.col -->
    </div>
    <!-- /.row -->
        
        ';
    }
}
//to fetch p.a.r general fund information
if(isset($_GET['getParId'])) {
    $getParId = mysqli_real_escape_string($conn,$_GET['getParId']);

        // Try General Fund first
        // Include current custodian (dept_id, emp_id) from the active history row (status=1)
        $sqlGf = "SELECT
                                CONCAT('₱ ',FORMAT(unit_value,2)) as amount,
                                fund,
                                par_number,
                                date_aquired,
                                item,
                                model,
                                description,
                                serial_number,
                                serial_number_2,
                                account_code,
                                purchase_order,
                                obr_number,
                                purchase_request,
                                supplier,
                                par_ics_number,
                                jev_number,
                                remarks,
                                (
                                    SELECT g.dept_id
                                    FROM general_fund_property_history g
                                    WHERE g.par_number = par_gen_fund.par_number AND g.status = 1
                                    ORDER BY g.created_at DESC
                                    LIMIT 1
                                ) AS current_dept_id,
                                (
                                    SELECT g.emp_id
                                    FROM general_fund_property_history g
                                    WHERE g.par_number = par_gen_fund.par_number AND g.status = 1
                                    ORDER BY g.created_at DESC
                                    LIMIT 1
                                ) AS current_emp_id
                            FROM par_gen_fund
                            WHERE par_number = '$getParId'
                            LIMIT 1";
    $queryGf = mysqli_query($conn, $sqlGf);
    if ($queryGf && mysqli_num_rows($queryGf) == 1) {
        $returnItem = mysqli_fetch_array($queryGf);
        echo json_encode(['status'=>200,'message'=>'Property id fetch successfully','data'=>$returnItem]);
        return false;
    }
        // Fallback to SEF: property_sef has property_number; alias to expected field names
    $sqlSef = "SELECT 
                CONCAT('₱ ',FORMAT(unit_value,2)) as amount,
                fund,
                property_number AS par_number,
                date_aquired,
                item,
                model,
                description,
                serial_number,
                serial_number_2,
                account_code,
                purchase_order,
                obr_number,
                purchase_request,
                supplier,
                par_ics_number,
                jev_number,
                                remarks,
                                (
                                    SELECT sh.sch_id
                                    FROM sef_property_history sh
                                    WHERE sh.property_number = property_sef.property_number AND sh.status = 1
                                    ORDER BY sh.created_at DESC
                                    LIMIT 1
                                ) AS current_dept_id,
                                (
                                    SELECT sh.emp_id
                                    FROM sef_property_history sh
                                    WHERE sh.property_number = property_sef.property_number AND sh.status = 1
                                    ORDER BY sh.created_at DESC
                                    LIMIT 1
                                ) AS current_emp_id
              FROM property_sef WHERE property_number = '$getParId' LIMIT 1";
    $querySef = mysqli_query($conn, $sqlSef);
    if ($querySef && mysqli_num_rows($querySef) == 1) {
        $returnItem = mysqli_fetch_array($querySef);
        echo json_encode(['status'=>200,'message'=>'Property id fetch successfully','data'=>$returnItem]);
        return false;
    }
    echo json_encode(['status'=>422,'message'=>'No property id found']);
    return false;
}

// ==========================================================
// General Fund: Bundle-with helpers
// ==========================================================

// Lookup a General Fund property by property number (par_gen_fund only)
if (isset($_GET['gf_bundle_lookup'])) {
    if (!isset($_SESSION['alogin']) || trim((string)$_SESSION['alogin']) === '') {
        echo json_encode(['status'=>401,'message'=>'Unauthorized']);
        return false;
    }

    $par = strtoupper(trim((string)($_GET['par_number'] ?? '')));
    if ($par === '') {
        echo json_encode(['status'=>422,'message'=>'Property number is required']);
        return false;
    }

    $sql = "SELECT par_number, item, model, serial_number, serial_number_2
            FROM par_gen_fund
            WHERE par_number = ?
            LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        echo json_encode(['status'=>500,'message'=>'Server error']);
        return false;
    }
    mysqli_stmt_bind_param($stmt, 's', $par);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    if (!$row) {
        echo json_encode(['status'=>422,'message'=>'No matching property found']);
        return false;
    }
    echo json_encode(['status'=>200,'message'=>'OK','data'=>$row]);
    return false;
}

// Fetch bundle-with list for a viewed General Fund property number
if (isset($_GET['get_gf_bundle_with'])) {
    if (!isset($_SESSION['alogin']) || trim((string)$_SESSION['alogin']) === '') {
        echo json_encode(['status'=>401,'message'=>'Unauthorized']);
        return false;
    }
    $bundleWith = strtoupper(trim((string)($_GET['bundle_with'] ?? '')));
    if ($bundleWith === '') {
        echo json_encode(['status'=>422,'message'=>'bundle_with is required']);
        return false;
    }

        $bundleCols = gso_get_table_columns($conn, 'bundle_gen_fund');
        $itemExpr = isset($bundleCols['item']) ? 'b.item' : 'NULL';
        $modelExpr = isset($bundleCols['model']) ? 'b.model' : 'NULL';
        $sn1Expr = isset($bundleCols['serial_number']) ? 'b.serial_number' : 'NULL';
        $sn2Expr = isset($bundleCols['serial_number_2']) ? 'b.serial_number_2' : 'NULL';

        $sql = "SELECT
                            b.property_number,
                            COALESCE($itemExpr, p.item, '') AS item,
                            COALESCE($modelExpr, p.model, '') AS model,
                            COALESCE($sn1Expr, p.serial_number, '') AS serial_number,
                            COALESCE($sn2Expr, p.serial_number_2, '') AS serial_number_2
                        FROM bundle_gen_fund b
                        LEFT JOIN par_gen_fund p ON p.par_number = b.property_number
                        WHERE b.bundle_with = ?
                        ORDER BY b.property_number ASC";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        echo json_encode(['status'=>500,'message'=>'Server error']);
        return false;
    }
    mysqli_stmt_bind_param($stmt, 's', $bundleWith);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = [];
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $rows[] = $r;
        }
    }
    mysqli_stmt_close($stmt);

    echo json_encode(['status'=>200,'message'=>'OK','data'=>$rows]);
    return false;
}

// Save a bundle link (searched item -> viewed item)
if (isset($_POST['save_gf_bundle_link'])) {
    if (!isset($_SESSION['alogin']) || trim((string)$_SESSION['alogin']) === '') {
        echo json_encode(['status'=>401,'message'=>'Unauthorized']);
        return false;
    }

    $deptId = isset($_POST['dept_id']) ? (int)$_POST['dept_id'] : 0;
    $empId = isset($_POST['emp_id']) ? (int)$_POST['emp_id'] : 0;
    $propertyNumber = strtoupper(trim((string)($_POST['property_number'] ?? '')));
    $bundleWith = strtoupper(trim((string)($_POST['bundle_with'] ?? '')));

    if ($deptId <= 0 || $empId <= 0) {
        echo json_encode(['status'=>422,'message'=>'Missing dept_id or emp_id']);
        return false;
    }
    if ($propertyNumber === '' || $bundleWith === '') {
        echo json_encode(['status'=>422,'message'=>'Missing property numbers']);
        return false;
    }
    if ($propertyNumber === $bundleWith) {
        echo json_encode(['status'=>422,'message'=>'Cannot bundle an item with itself']);
        return false;
    }

    // Ensure both properties exist in General Fund
    $chkSql = "SELECT par_number FROM par_gen_fund WHERE par_number = ? LIMIT 1";
    $chk = mysqli_prepare($conn, $chkSql);
    if (!$chk) {
        echo json_encode(['status'=>500,'message'=>'Server error']);
        return false;
    }
    mysqli_stmt_bind_param($chk, 's', $propertyNumber);
    mysqli_stmt_execute($chk);
    $r1 = mysqli_stmt_get_result($chk);
    $ok1 = ($r1 && mysqli_fetch_assoc($r1));
    mysqli_stmt_close($chk);
    if (!$ok1) {
        echo json_encode(['status'=>422,'message'=>'Searched item not found in General Fund']);
        return false;
    }

    $chk2 = mysqli_prepare($conn, $chkSql);
    if (!$chk2) {
        echo json_encode(['status'=>500,'message'=>'Server error']);
        return false;
    }
    mysqli_stmt_bind_param($chk2, 's', $bundleWith);
    mysqli_stmt_execute($chk2);
    $r2 = mysqli_stmt_get_result($chk2);
    $ok2 = ($r2 && mysqli_fetch_assoc($r2));
    mysqli_stmt_close($chk2);
    if (!$ok2) {
        echo json_encode(['status'=>422,'message'=>'Viewed item not found in General Fund']);
        return false;
    }

    // Prevent duplicates
    $dupSql = "SELECT 1 FROM bundle_gen_fund WHERE property_number = ? AND bundle_with = ? LIMIT 1";
    $dup = mysqli_prepare($conn, $dupSql);
    if (!$dup) {
        echo json_encode(['status'=>500,'message'=>'Server error']);
        return false;
    }
    mysqli_stmt_bind_param($dup, 'ss', $propertyNumber, $bundleWith);
    mysqli_stmt_execute($dup);
    $dupRes = mysqli_stmt_get_result($dup);
    $exists = ($dupRes && mysqli_fetch_assoc($dupRes));
    mysqli_stmt_close($dup);
    if ($exists) {
        echo json_encode(['status'=>200,'message'=>'Already bundled']);
        return false;
    }

    $bundleCols = gso_ensure_bundle_transfer_columns($conn, 'bundle_gen_fund');
    if (!empty($bundleCols)) {
        $insertColumns = ['dept_id', 'emp_id', 'property_number', 'bundle_with'];
        $selectColumns = ['?', '?', 'p.par_number', '?'];
        $bindTypes = 'iis';
        $bindValues = [$deptId, $empId, $bundleWith];

        $optionalMap = [
            'category' => 'p.category',
            'unit' => 'p.unit',
            'item' => 'p.item',
            'model' => 'p.model',
            'description' => 'p.description',
            'serial_number' => 'p.serial_number',
            'serial_number_2' => 'p.serial_number_2',
            'par_ics_number' => 'p.par_ics_number',
            'unit_value' => 'p.unit_value',
            'date_aquired' => 'p.date_aquired',
            'account_code' => 'p.account_code',
            'fund' => 'p.fund',
            'supplier' => 'p.supplier',
            'purchase_order' => 'p.purchase_order',
            'purchase_request' => 'p.purchase_request',
            'obr_number' => 'p.obr_number',
            'jev_number' => 'p.jev_number',
            'remarks' => 'p.remarks',
        ];
        foreach ($optionalMap as $column => $expression) {
            if (isset($bundleCols[$column])) {
                $insertColumns[] = $column;
                $selectColumns[] = $expression;
            }
        }

        $insSql = "INSERT INTO bundle_gen_fund (" . implode(', ', $insertColumns) . ")
                   SELECT " . implode(', ', $selectColumns) . "
                   FROM par_gen_fund p
                   WHERE p.par_number = ?
                   LIMIT 1";
        $ins = mysqli_prepare($conn, $insSql);
        if (!$ins) {
            echo json_encode(['status'=>500,'message'=>'Server error']);
            return false;
        }
        mysqli_stmt_bind_param($ins, $bindTypes . 's', ...array_merge($bindValues, [$propertyNumber]));
    } else {
        $insSql = "INSERT INTO bundle_gen_fund (dept_id, emp_id, property_number, bundle_with)
                   VALUES (?,?,?,?)";
        $ins = mysqli_prepare($conn, $insSql);
        if (!$ins) {
            echo json_encode(['status'=>500,'message'=>'Server error']);
            return false;
        }
        mysqli_stmt_bind_param($ins, 'iiss', $deptId, $empId, $propertyNumber, $bundleWith);
    }
    $ok = mysqli_stmt_execute($ins);
    mysqli_stmt_close($ins);

    if (!$ok) {
        echo json_encode(['status'=>500,'message'=>'Failed to save bundle']);
        return false;
    }
    echo json_encode(['status'=>200,'message'=>'Bundled successfully']);
    return false;
}

// ==========================================================
// SEF: Bundle-with helpers
// Note: requires a table `bundle_sef` with columns:
//   dept_id INT, emp_id INT, property_number VARCHAR, bundle_with VARCHAR
// ==========================================================

if (isset($_GET['sef_bundle_lookup'])) {
    if (!isset($_SESSION['alogin']) || trim((string)$_SESSION['alogin']) === '') {
        echo json_encode(['status'=>401,'message'=>'Unauthorized']);
        return false;
    }

    $par = strtoupper(trim((string)($_GET['par_number'] ?? '')));
    if ($par === '') {
        echo json_encode(['status'=>422,'message'=>'Property number is required']);
        return false;
    }

    $sql = "SELECT property_number AS par_number, item, model, serial_number, serial_number_2
            FROM property_sef
            WHERE property_number = ?
            LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        echo json_encode(['status'=>500,'message'=>'Server error']);
        return false;
    }
    mysqli_stmt_bind_param($stmt, 's', $par);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    if (!$row) {
        echo json_encode(['status'=>422,'message'=>'No matching property found']);
        return false;
    }
    echo json_encode(['status'=>200,'message'=>'OK','data'=>$row]);
    return false;
}

if (isset($_GET['get_sef_bundle_with'])) {
    if (!isset($_SESSION['alogin']) || trim((string)$_SESSION['alogin']) === '') {
        echo json_encode(['status'=>401,'message'=>'Unauthorized']);
        return false;
    }

    $bundleWith = strtoupper(trim((string)($_GET['bundle_with'] ?? '')));
    if ($bundleWith === '') {
        echo json_encode(['status'=>422,'message'=>'bundle_with is required']);
        return false;
    }

        $bundleCols = gso_get_table_columns($conn, 'bundle_sef');
        $itemExpr = isset($bundleCols['item']) ? 'b.item' : 'NULL';
        $modelExpr = isset($bundleCols['model']) ? 'b.model' : 'NULL';
        $sn1Expr = isset($bundleCols['serial_number']) ? 'b.serial_number' : 'NULL';
        $sn2Expr = isset($bundleCols['serial_number_2']) ? 'b.serial_number_2' : 'NULL';

        $sql = "SELECT
                            b.property_number,
                            COALESCE($itemExpr, p.item, '') AS item,
                            COALESCE($modelExpr, p.model, '') AS model,
                            COALESCE($sn1Expr, p.serial_number, '') AS serial_number,
                            COALESCE($sn2Expr, p.serial_number_2, '') AS serial_number_2
                        FROM bundle_sef b
                        LEFT JOIN property_sef p ON p.property_number = b.property_number
                        WHERE b.bundle_with = ?
                        ORDER BY b.property_number ASC";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        echo json_encode(['status'=>500,'message'=>'Server error']);
        return false;
    }
    mysqli_stmt_bind_param($stmt, 's', $bundleWith);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = [];
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $rows[] = $r;
        }
    }
    mysqli_stmt_close($stmt);

    echo json_encode(['status'=>200,'message'=>'OK','data'=>$rows]);
    return false;
}

if (isset($_POST['save_sef_bundle_link'])) {
    if (!isset($_SESSION['alogin']) || trim((string)$_SESSION['alogin']) === '') {
        echo json_encode(['status'=>401,'message'=>'Unauthorized']);
        return false;
    }

    $deptId = isset($_POST['dept_id']) ? (int)$_POST['dept_id'] : 0;
    $empId = isset($_POST['emp_id']) ? (int)$_POST['emp_id'] : 0;
    $propertyNumber = strtoupper(trim((string)($_POST['property_number'] ?? '')));
    $bundleWith = strtoupper(trim((string)($_POST['bundle_with'] ?? '')));

    if ($deptId <= 0 || $empId <= 0) {
        echo json_encode(['status'=>422,'message'=>'Missing dept_id or emp_id']);
        return false;
    }
    if ($propertyNumber === '' || $bundleWith === '') {
        echo json_encode(['status'=>422,'message'=>'Missing property numbers']);
        return false;
    }
    if ($propertyNumber === $bundleWith) {
        echo json_encode(['status'=>422,'message'=>'Cannot bundle an item with itself']);
        return false;
    }

    // Ensure both properties exist in SEF
    $chkSql = "SELECT property_number FROM property_sef WHERE property_number = ? LIMIT 1";
    $chk = mysqli_prepare($conn, $chkSql);
    if (!$chk) {
        echo json_encode(['status'=>500,'message'=>'Server error']);
        return false;
    }
    mysqli_stmt_bind_param($chk, 's', $propertyNumber);
    mysqli_stmt_execute($chk);
    $r1 = mysqli_stmt_get_result($chk);
    $ok1 = ($r1 && mysqli_fetch_assoc($r1));
    mysqli_stmt_close($chk);
    if (!$ok1) {
        echo json_encode(['status'=>422,'message'=>'Searched item not found in SEF']);
        return false;
    }

    $chk2 = mysqli_prepare($conn, $chkSql);
    if (!$chk2) {
        echo json_encode(['status'=>500,'message'=>'Server error']);
        return false;
    }
    mysqli_stmt_bind_param($chk2, 's', $bundleWith);
    mysqli_stmt_execute($chk2);
    $r2 = mysqli_stmt_get_result($chk2);
    $ok2 = ($r2 && mysqli_fetch_assoc($r2));
    mysqli_stmt_close($chk2);
    if (!$ok2) {
        echo json_encode(['status'=>422,'message'=>'Viewed item not found in SEF']);
        return false;
    }

    // Prevent duplicates
    $dupSql = "SELECT 1 FROM bundle_sef WHERE property_number = ? AND bundle_with = ? LIMIT 1";
    $dup = mysqli_prepare($conn, $dupSql);
    if (!$dup) {
        echo json_encode(['status'=>500,'message'=>'Server error']);
        return false;
    }
    mysqli_stmt_bind_param($dup, 'ss', $propertyNumber, $bundleWith);
    mysqli_stmt_execute($dup);
    $dupRes = mysqli_stmt_get_result($dup);
    $exists = ($dupRes && mysqli_fetch_assoc($dupRes));
    mysqli_stmt_close($dup);
    if ($exists) {
        echo json_encode(['status'=>200,'message'=>'Already bundled']);
        return false;
    }

    $bundleCols = gso_ensure_bundle_transfer_columns($conn, 'bundle_sef');
    if (!empty($bundleCols)) {
        $insertColumns = ['dept_id', 'emp_id', 'property_number', 'bundle_with'];
        $selectColumns = ['?', '?', 'p.property_number', '?'];
        $bindTypes = 'iis';
        $bindValues = [$deptId, $empId, $bundleWith];

        $optionalMap = [
            'category' => 'p.category',
            'unit' => 'p.unit',
            'item' => 'p.item',
            'model' => 'p.model',
            'description' => 'p.description',
            'serial_number' => 'p.serial_number',
            'serial_number_2' => 'p.serial_number_2',
            'par_ics_number' => 'p.par_ics_number',
            'unit_value' => 'p.unit_value',
            'date_aquired' => 'p.date_aquired',
            'account_code' => 'p.account_code',
            'fund' => 'p.fund',
            'supplier' => 'p.supplier',
            'purchase_order' => 'p.purchase_order',
            'purchase_request' => 'p.purchase_request',
            'obr_number' => 'p.obr_number',
            'jev_number' => 'p.jev_number',
            'remarks' => 'p.remarks',
        ];
        foreach ($optionalMap as $column => $expression) {
            if (isset($bundleCols[$column])) {
                $insertColumns[] = $column;
                $selectColumns[] = $expression;
            }
        }

        $insSql = "INSERT INTO bundle_sef (" . implode(', ', $insertColumns) . ")
                   SELECT " . implode(', ', $selectColumns) . "
                   FROM property_sef p
                   WHERE p.property_number = ?
                   LIMIT 1";
        $ins = mysqli_prepare($conn, $insSql);
        if (!$ins) {
            echo json_encode(['status'=>500,'message'=>'Server error']);
            return false;
        }
        mysqli_stmt_bind_param($ins, $bindTypes . 's', ...array_merge($bindValues, [$propertyNumber]));
    } else {
        $insSql = "INSERT INTO bundle_sef (dept_id, emp_id, property_number, bundle_with)
                   VALUES (?,?,?,?)";
        $ins = mysqli_prepare($conn, $insSql);
        if (!$ins) {
            echo json_encode(['status'=>500,'message'=>'Server error']);
            return false;
        }
        mysqli_stmt_bind_param($ins, 'iiss', $deptId, $empId, $propertyNumber, $bundleWith);
    }
    $ok = mysqli_stmt_execute($ins);
    mysqli_stmt_close($ins);

    if (!$ok) {
        echo json_encode(['status'=>500,'message'=>'Failed to save bundle']);
        return false;
    }
    echo json_encode(['status'=>200,'message'=>'Bundled successfully']);
    return false;
}
//view p.a.r gen fund property history//
if (isset($_POST['viewHistoryBtn'])) {
    $parid = $_POST['parnumberid'];
    // Try GF history first
    $sql = "SELECT g.par_number,g.emp_id,g.dept_id,g.status,g.created_at,
    e.department_code AS employee_department_code, e.emp_id,e.emp_name as name 
    FROM general_fund_property_history AS g JOIN employee AS e ON g.emp_id = e.emp_id 
    WHERE g.par_number = '".mysqli_real_escape_string($conn,$parid)."' AND g.status = '0' LIMIT 10 ";
    $query = mysqli_query($conn, $sql);
    $cnt = 1;
    if ($query && mysqli_num_rows($query) > 0) {
        foreach ($query as $result) {
            $departmentRow = gso_resolve_history_department($conn, $result['dept_id'] ?? '', $result['employee_department_code'] ?? '');
            echo '<tr>'
                .'<td>'.$cnt.'</td>'
                .'<td>'.$result['name'].'</td>'
                .'<td>'.htmlspecialchars($departmentRow['department_name'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</td>'
                .'<td>'.date('M-d-Y',strtotime($result['created_at'])).'</td>'
                .'</tr>';
            $cnt++;
        }
        return false;
    }
    // Fallback to SEF history using property_number and sch_id
    $sql2 = "SELECT sh.property_number AS par_number, sh.emp_id, sh.sch_id AS dept_id, sh.status, sh.created_at,
                     d.department_code, d.department_name, e.emp_id, e.emp_name AS name
              FROM sef_property_history AS sh
              JOIN employee AS e ON sh.emp_id = e.emp_id
              JOIN department AS d ON sh.sch_id = d.department_code
              WHERE sh.property_number = '".mysqli_real_escape_string($conn,$parid)."' AND sh.status = '0' LIMIT 10";
    $q2 = mysqli_query($conn, $sql2);
    $cnt = 1;
    if ($q2 && mysqli_num_rows($q2) > 0) {
        foreach ($q2 as $result) {
            echo '<tr>'
                .'<td>'.$cnt.'</td>'
                .'<td>'.$result['name'].'</td>'
                .'<td>'.$result['department_name'].'</td>'
                .'<td>'.date('M-d-Y',strtotime($result['created_at'])).'</td>'
                .'</tr>';
            $cnt++;
        }
        return false;
    }
    echo '<tr class="text-center"><td colspan="4"><h6>No data</h6></td></tr>';
    return false;
}

//add account code information
if (isset($_POST['save_acct'])) {
    $atitles = mysqli_real_escape_string($conn,strtoupper($_POST['acctname']));
    $acode = mysqli_real_escape_string($conn,strtoupper($_POST['acctcode']));

    $sql = "INSERT INTO account_code (account_name,account_code) VALUES('$atitles','$acode')";
    $query = mysqli_query($conn, $sql);

    if ($query) {
        $res = [
            'status' => 200,
            'message' => 'Added successfully!.',
        ];
        echo json_encode($res);
        return false;
    } else {
        $res = [
            'status' => 500,
            'message' => 'opps..something went wrong..',
        ];
        echo json_encode($res);
        return false;
    }
}

//fetch account code details
if (isset($_GET['accntcode'])) {
    $accntcode = mysqli_real_escape_string($conn, $_GET['accntcode']);
    $sql = "SELECT * FROM account_code WHERE id = '$accntcode' LIMIT 1 ";
    $query = mysqli_query($conn, $sql);
    $actvty = "Viewed the data of ".$accntcode;

    if (mysqli_num_rows($query) == 1) {
        mysqli_query($conn, "INSERT INTO activity_log(admin_id,ip_address,activity) VALUES('$uid','$uip','$actvty')");
        $dept = mysqli_fetch_array($query);
        $res = [
            'status' => 200,
            'message' => 'Account code id fetch successfully',
            'data' => $dept,
        ];
        echo json_encode($res);
        return false;
    } else {
        $res = [
            'status' => 422,
            'message' => 'No Account code id found',
        ];
        echo json_encode($res);
        return false;
    }
}

//update account code
if (isset($_POST['update_acct'])) {
    $Id = mysqli_real_escape_string($conn,strtoupper($_POST['AccntId']));
    $acctname = mysqli_real_escape_string($conn,strtoupper($_POST['eacctname']));
    $acctcode = mysqli_real_escape_string($conn,strtoupper($_POST['eacctcode']));
  
    if ($acctname == null || $acctcode == null) {
        $res = [
            'status' => 422,
            'message' => 'All fields are required!.',
        ];
        echo json_encode($res);
        return false;
    }
   
    $sql = "UPDATE account_code SET account_name = '$acctname' , account_code = '$acctcode' WHERE id = '$Id' ";
    $query = mysqli_query($conn, $sql);

    if ($query) {
        $res = [
            'status' => 200,
            'message' => 'Updated succesfully!',
        ];
        echo json_encode($res);
        return false;
    } else {
        $res = [
            'status' => 500,
            'message' => 'opps..something went wrong..',
        ];
        echo json_encode($res);
        return false;
    }
}
//delete account code
if (isset($_POST['delete_acct'])) {
    $delacct = mysqli_real_escape_string($conn, $_POST['delacct']);

    $sql = "DELETE FROM account_code WHERE id = '$delacct' ";
    $query = mysqli_query($conn, $sql);

    if ($query) {
        $res = [
            'status' => 200,
            'message' => 'Deleted succesfully!',
        ];
        echo json_encode($res);
        return false;
    } else {
        $res = [
            'status' => 500,
            'message' => 'opps..something went wrong..',
        ];
        echo json_encode($res);
        return false;
    }
}

// General Fund - Account Code summary (PAR / ICS) - DataTables
if (isset($_POST['gf_account_codes_dt'])) {
    header('Content-Type: application/json');

    $category = strtoupper(trim((string)($_POST['category'] ?? 'PAR')));
    if (!in_array($category, ['PAR', 'ICS'], true)) { $category = 'PAR'; }

    $draw = isset($_POST['draw']) ? (int)$_POST['draw'] : 0;
    $start = isset($_POST['start']) ? max(0, (int)$_POST['start']) : 0;
    $length = isset($_POST['length']) ? (int)$_POST['length'] : 10;
    if ($length <= 0 || $length > 200) { $length = 10; }

    $search = '';
    if (isset($_POST['search']) && is_array($_POST['search']) && isset($_POST['search']['value'])) {
        $search = trim((string)$_POST['search']['value']);
    }

    $orderCol = 0;
    $orderDir = 'ASC';
    if (isset($_POST['order'][0]['column'])) {
        $orderCol = (int)$_POST['order'][0]['column'];
    }
    if (isset($_POST['order'][0]['dir']) && strtolower((string)$_POST['order'][0]['dir']) === 'desc') {
        $orderDir = 'DESC';
    }

    $colMap = [
        0 => 'a.account_code',
        1 => 'a.account_name',
        2 => 'total_value'
    ];
    $orderBy = isset($colMap[$orderCol]) ? $colMap[$orderCol] : 'a.account_code';

    $whereSql = " WHERE UPPER(TRIM(p.category)) = ? ";
    $whereTypes = 's';
    $whereParams = [$category];
    if ($search !== '') {
        $whereSql .= " AND (a.account_code LIKE ? OR a.account_name LIKE ?) ";
        $whereTypes .= 'ss';
        $likeSearch = '%' . $search . '%';
        $whereParams[] = $likeSearch;
        $whereParams[] = $likeSearch;
    }

    $totalRecords = 0;
    $row = gso_query_one($conn, "
        SELECT COUNT(DISTINCT a.id) AS cnt
        FROM account_code AS a
        INNER JOIN par_gen_fund AS p ON a.account_code = p.account_code
        WHERE UPPER(TRIM(p.category)) = ?
    ", 's', [$category]);
    $totalRecords = (int)($row['cnt'] ?? 0);

    $filteredRecords = $totalRecords;
    if ($search !== '') {
        $rowF = gso_query_one($conn, "
            SELECT COUNT(*) AS cnt FROM (
                SELECT a.id
                FROM account_code AS a
                INNER JOIN par_gen_fund AS p ON a.account_code = p.account_code
                {$whereSql}
                GROUP BY a.id
            ) AS x
        ", $whereTypes, $whereParams);
        $filteredRecords = (int)($rowF['cnt'] ?? 0);
    }

    $totalAmount = 0.0;
    $rowA = gso_query_one($conn, "
        SELECT COALESCE(SUM(p.unit_value), 0) AS total_amount
        FROM par_gen_fund AS p
        WHERE UPPER(TRIM(p.category)) = ?
    ", 's', [$category]);
    $totalAmount = (float)($rowA['total_amount'] ?? 0);

    $sql = "
        SELECT
            a.id,
            a.account_code,
            a.account_name,
            COALESCE(SUM(p.unit_value), 0) AS total_value
        FROM account_code AS a
        INNER JOIN par_gen_fund AS p ON a.account_code = p.account_code
        {$whereSql}
        GROUP BY a.id, a.account_code, a.account_name
        ORDER BY {$orderBy} {$orderDir}
        LIMIT ?, ?
    ";

    $data = [];
    $dataTypes = $whereTypes . 'ii';
    $dataParams = $whereParams;
    $dataParams[] = $start;
    $dataParams[] = $length;
    list($stmt, $rows) = gso_query_all($conn, $sql, $dataTypes, $dataParams);
    if ($stmt instanceof mysqli_stmt) { $stmt->close(); }
    foreach ($rows as $r) {
        $data[] = [
            'id' => (int)($r['id'] ?? 0),
            'account_code' => (string)($r['account_code'] ?? ''),
            'account_name' => (string)($r['account_name'] ?? ''),
            'total_value' => (float)($r['total_value'] ?? 0)
        ];
    }

    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => $totalRecords,
        'recordsFiltered' => $filteredRecords,
        'total_amount' => $totalAmount,
        'category' => $category,
        'data' => $data
    ]);
    return false;
}

// Special Education Fund (SEF) - Account Code summary (PAR / ICS) - DataTables
if (isset($_POST['sef_account_codes_dt'])) {
    header('Content-Type: application/json');

    $category = strtoupper(trim((string)($_POST['category'] ?? 'PAR')));
    if (!in_array($category, ['PAR', 'ICS'], true)) { $category = 'PAR'; }

    $draw = isset($_POST['draw']) ? (int)$_POST['draw'] : 0;
    $start = isset($_POST['start']) ? max(0, (int)$_POST['start']) : 0;
    $length = isset($_POST['length']) ? (int)$_POST['length'] : 10;
    if ($length <= 0 || $length > 200) { $length = 10; }

    $search = '';
    if (isset($_POST['search']) && is_array($_POST['search']) && isset($_POST['search']['value'])) {
        $search = trim((string)$_POST['search']['value']);
    }

    $orderCol = 0;
    $orderDir = 'ASC';
    if (isset($_POST['order'][0]['column'])) {
        $orderCol = (int)$_POST['order'][0]['column'];
    }
    if (isset($_POST['order'][0]['dir']) && strtolower((string)$_POST['order'][0]['dir']) === 'desc') {
        $orderDir = 'DESC';
    }

    $colMap = [
        0 => 'a.account_code',
        1 => 'a.account_name',
        2 => 'total_value'
    ];
    $orderBy = isset($colMap[$orderCol]) ? $colMap[$orderCol] : 'a.account_code';

    $whereSql = " WHERE UPPER(TRIM(s.category)) = ? ";
    $whereTypes = 's';
    $whereParams = [$category];
    if ($search !== '') {
        $whereSql .= " AND (a.account_code LIKE ? OR a.account_name LIKE ?) ";
        $whereTypes .= 'ss';
        $likeSearch = '%' . $search . '%';
        $whereParams[] = $likeSearch;
        $whereParams[] = $likeSearch;
    }

    $totalRecords = 0;
    $row = gso_query_one($conn, "
        SELECT COUNT(DISTINCT a.id) AS cnt
        FROM account_code AS a
        INNER JOIN property_sef AS s ON a.account_code = s.account_code
        WHERE UPPER(TRIM(s.category)) = ?
    ", 's', [$category]);
    $totalRecords = (int)($row['cnt'] ?? 0);

    $filteredRecords = $totalRecords;
    if ($search !== '') {
        $rowF = gso_query_one($conn, "
            SELECT COUNT(*) AS cnt FROM (
                SELECT a.id
                FROM account_code AS a
                INNER JOIN property_sef AS s ON a.account_code = s.account_code
                {$whereSql}
                GROUP BY a.id
            ) AS x
        ", $whereTypes, $whereParams);
        $filteredRecords = (int)($rowF['cnt'] ?? 0);
    }

    $totalAmount = 0.0;
    $rowA = gso_query_one($conn, "
        SELECT COALESCE(SUM(s.unit_value), 0) AS total_amount
        FROM property_sef AS s
        WHERE UPPER(TRIM(s.category)) = ?
    ", 's', [$category]);
    $totalAmount = (float)($rowA['total_amount'] ?? 0);

    $sql = "
        SELECT
            a.id,
            a.account_code,
            a.account_name,
            COALESCE(SUM(s.unit_value), 0) AS total_value
        FROM account_code AS a
        INNER JOIN property_sef AS s ON a.account_code = s.account_code
        {$whereSql}
        GROUP BY a.id, a.account_code, a.account_name
        ORDER BY {$orderBy} {$orderDir}
        LIMIT ?, ?
    ";

    $data = [];
    $dataTypes = $whereTypes . 'ii';
    $dataParams = $whereParams;
    $dataParams[] = $start;
    $dataParams[] = $length;
    list($stmt, $rows) = gso_query_all($conn, $sql, $dataTypes, $dataParams);
    if ($stmt instanceof mysqli_stmt) { $stmt->close(); }
    foreach ($rows as $r) {
        $data[] = [
            'id' => (int)($r['id'] ?? 0),
            'account_code' => (string)($r['account_code'] ?? ''),
            'account_name' => (string)($r['account_name'] ?? ''),
            'total_value' => (float)($r['total_value'] ?? 0)
        ];
    }

    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => $totalRecords,
        'recordsFiltered' => $filteredRecords,
        'total_amount' => $totalAmount,
        'category' => $category,
        'data' => $data
    ]);
    return false;
}

// add clearance type
if (isset($_POST['save_ct'])) {
    $ctname = mysqli_real_escape_string($conn, strtoupper($_POST['ctname']));
    $ctcode = mysqli_real_escape_string($conn, strtoupper($_POST['ctcode']));

    $sql = "INSERT INTO clearance_type (clearance_name,clearance_code) VALUES ('$ctname','$ctcode')";
    $query = mysqli_query($conn, $sql);

    if ($query) {
        $res = [
            'status' => 200,
            'message' => 'Added successfully!.',
        ];
        echo json_encode($res);
        return false;
    } else {
        $res = [
            'status' => 500,
            'message' => 'opps..something went wrong..',
        ];
        echo json_encode($res);
        return false;
    }
}

//fetch clearance type details
if (isset($_GET['ctid'])) {
    $ctid = mysqli_real_escape_string($conn, $_GET['ctid']);

    $sql = "SELECT * FROM clearance_type WHERE ctype_id = '$ctid' LIMIT 1 ";
    $query = mysqli_query($conn, $sql);

    if (mysqli_num_rows($query) == 1) {
        $dept = mysqli_fetch_array($query);

        $res = [
            'status' => 200,
            'message' => 'Clearance id fetch successfully',
            'data' => $dept,
        ];
        echo json_encode($res);
        return false;
    } else {
        $res = [
            'status' => 422,
            'message' => 'No Clearance id found',
        ];
        echo json_encode($res);
        return false;
    }
}

//update clearance type
if (isset($_POST['update_clearance'])) {
    $ctid = mysqli_real_escape_string($conn, strtoupper($_POST['CtId']));
    $ctname = mysqli_real_escape_string($conn, strtoupper($_POST['ectname']));
    $ctcode = mysqli_real_escape_string($conn, $_POST['ectcode']);

    $sql = "UPDATE clearance_type SET clearance_name = '$ctname' , clearance_code = '$ctcode' WHERE ctype_id = '$ctid' ";
    $query = mysqli_query($conn, $sql);

    if ($query) {
        $res = [
            'status' => 200,
            'message' => 'Updated succesfully!',
        ];
        echo json_encode($res);
        return false;
    } else {
        $res = [
            'status' => 500,
            'message' => 'opps..something went wrong..',
        ];
        echo json_encode($res);
        return false;
    }
}
//delete clearance type
if (isset($_POST['delete_ct'])) {
    $delct = mysqli_real_escape_string($conn, $_POST['delct']);

    $sql = "DELETE FROM clearance_type WHERE ctype_id = '$delct' ";
    $query = mysqli_query($conn, $sql);

    if ($query) {
        $res = [
            'status' => 200,
            'message' => 'Deleted succesfully!',
        ];
        echo json_encode($res);
        return false;
    } else {
        $res = [
            'status' => 500,
            'message' => 'opps..something went wrong..',
        ];
        echo json_encode($res);
        return false;
    }
}
//select employee from department
if(isset($_POST['departmentid'])){
    $department_id_raw = isset($_POST['departmentid']) ? $_POST['departmentid'] : '';
    $department_id = mysqli_real_escape_string($conn, strtoupper(trim($department_id_raw)));

    // Fallback compatibility: some datasets store the department NAME in employee.department_code
    // instead of the department CODE. Resolve name for the selected code and match either.
    $deptNameUp = '';
    if ($department_id !== '') {
        $dq = mysqli_query($conn, "SELECT department_name FROM department WHERE UPPER(TRIM(department_code))='$department_id' LIMIT 1");
        if ($dq && mysqli_num_rows($dq) === 1) {
            $dr = mysqli_fetch_assoc($dq);
            $deptNameUp = strtoupper(trim($dr['department_name'] ?? ''));
        }
    }
    $deptNameEsc = $deptNameUp !== '' ? mysqli_real_escape_string($conn, $deptNameUp) : '';

    // Initialize output buffer to avoid undefined variable notices that can slow down response
    $output = '';
    // Be resilient to mixed-case / padded values in DB (and name-vs-code storage)
    $where = "UPPER(TRIM(department_code)) = '$department_id'";
    if ($deptNameEsc !== '') { $where .= " OR UPPER(TRIM(department_code)) = '$deptNameEsc'"; }
    $sql = "SELECT emp_id,emp_name,position,department_code FROM employee WHERE ($where) ORDER BY emp_name ASC";
    $query = mysqli_query($conn,$sql);
    $output .= '<option value="">-SELECT-</option>';
    $output .= '<option value="add_new_emp"> + ADD NEW EMPLOYEE </option>';
    if ($query) {
        while($row = mysqli_fetch_assoc($query)){
            // Use mysqli_fetch_assoc to reduce overhead and be explicit with keys
            $empId = htmlspecialchars($row['emp_id']);
            $empName = htmlspecialchars($row['emp_name']);
            $pos = htmlspecialchars(trim($row['position'] ?? ''));
            $output .= '<option value="'.$empId.'" data-position="'.$pos.'">'.$empName.'</option>';
        }
    }
    echo $output;
    exit;
}
// fetch departments filtered by fund selection (AJAX)
// Business rule: GF/TRUST FUND -> agencies = 'CITY DEPARTMENT'; SEF -> agencies = 'INSTITUTION'
// restore fund-based department filtering endpoint
if(isset($_POST['fund_for_departments'])) {
    // Input guard: fund must be a short known token
    $fundVal = isset($_POST['fund_for_departments']) ? strtoupper(trim($_POST['fund_for_departments'])) : '';
    if ($fundVal === '' || strlen($fundVal) > 64) { echo '<option value="">-SELECT-</option>'; exit; }
    // Simple session cache to reduce DB hits across quick repeated requests
    if (!isset($_SESSION['cache'])) { $_SESSION['cache'] = []; }
    $cacheKey = 'departments_for_fund_v2_' . md5($fundVal);
    if (isset($_SESSION['cache'][$cacheKey])) {
        $cached = $_SESSION['cache'][$cacheKey];
        if (is_array($cached) && isset($cached['html']) && isset($cached['ts']) && (time() - $cached['ts'] < 120)) {
            echo $cached['html']; exit;
        }
    }
    // Accept multiple possible representations stored in department.agencies
    // For GENERAL FUND/TRUST FUND: may be stored as 'CITY DEPARTMENT', '1', 'GF', 'GENERAL FUND'
    // For SEF: may be stored as 'INSTITUTION', '2', 'SEF', 'SPECIAL EDUCATION FUND'
    $agencyCandidates = [];
        if (in_array($fundVal, ['GF','GENERAL FUND','TRUST FUND'], true)) {
            $agencyCandidates = ['CITY DEPARTMENT','1','GF','GENERAL FUND'];
        } elseif (in_array($fundVal, ['SEF','SF','SPECIAL EDUCATION FUND'])) {
            $agencyCandidates = ['INSTITUTION','2','SEF','SPECIAL EDUCATION FUND'];
        } elseif ($fundVal === 'DONATION') {
            $agencyCandidates = ['CITY DEPARTMENT','INSTITUTION','1','2','GF','GENERAL FUND','SEF','SF','SPECIAL EDUCATION FUND','TRUST FUND','DONATION'];
        }
    if (!count($agencyCandidates)) { echo '<option value="">-SELECT-</option>'; exit; }
    $conds = [];
    foreach ($agencyCandidates as $cand) {
        $conds[] = "agencies='" . mysqli_real_escape_string($conn, $cand) . "'";
    }
    // Also compare uppercased forms to be resilient (covers mixed-case rows)
    foreach ($agencyCandidates as $cand) {
        $conds[] = "UPPER(agencies)='" . mysqli_real_escape_string($conn, strtoupper($cand)) . "'";
    }
    $where = '(' . implode(' OR ', $conds) . ')';
    $sql = "SELECT department_name, department_code FROM department WHERE $where ORDER BY department_name ASC";
    $query = mysqli_query($conn, $sql);
    $options = '<option value="">-SELECT-</option>';
    if ($query) {
        while($row = mysqli_fetch_assoc($query)) {
            $dname = htmlspecialchars($row['department_name']);
            $dcode = htmlspecialchars($row['department_code']);
            $options .= "<option value=\"$dcode\">$dname</option>";
        }
    }
    // Cache the HTML for short period
    $_SESSION['cache'][$cacheKey] = ['html'=>$options, 'ts'=>time()];
    echo $options; exit;
}
// add employee applying for property clearance section
if (isset($_POST['save_pc'])) {
    // Business rules: auto print for TRAVEL ABROAD / MATERNITY / VACATION; manual for RETIREMENT/RESIGNATION if employee still has active accountabilities; others default to manual.
    $controlno = mysqli_real_escape_string($conn, $_POST['ctrlno']);
    $employeeRaw = isset($_POST['employee']) ? $_POST['employee'] : '';
    $employeeValue = strtoupper(trim($employeeRaw)); // may be existing emp_id or sentinel value
    $newEmployeeName = strtoupper(trim((string)($_POST['new_employee_name'] ?? '')));
    $position = mysqli_real_escape_string($conn, strtoupper($_POST['position'] ?? ''));
    $dept = mysqli_real_escape_string($conn, $_POST['dept'] ?? '');
    $clearanceCode = mysqli_real_escape_string($conn, $_POST['ctype'] ?? ''); // value is clearance_code
    $orNumber = mysqli_real_escape_string($conn, $_POST['ornumber'] ?? '');
    $address = mysqli_real_escape_string($conn, strtoupper($_POST['address'] ?? ''));
    $city = mysqli_real_escape_string($conn, strtoupper($_POST['city'] ?? ''));

    // Resolve clearance name from code to avoid hard-coded code assumptions
    $ctName = '';
    $ctq = mysqli_query($conn, "SELECT clearance_name FROM clearance_type WHERE clearance_code='$clearanceCode' LIMIT 1");
    if ($ctq && mysqli_num_rows($ctq) === 1) {
        $ctRow = mysqli_fetch_assoc($ctq);
        $ctName = strtoupper(trim($ctRow['clearance_name']));
    }

    // Apply employee status rule based on the selected clearance type
    $empStatusForEmployee = employee_status_from_clearance_name($ctName);

    $isNewEmployee = ($employeeValue === 'ADD_NEW_EMP' || $employeeValue === 'ADD_NEW_EMPLOYEE' || $employeeValue === 'ADD_NEW' || $employeeValue === '+ ADD NEW EMPLOYEE');
    if (!$isNewEmployee && strtolower($employeeRaw) === 'add_new_emp') { $isNewEmployee = true; }

    // Validation: Block duplicate in-process RESIGNATION/RETIREMENT for existing employees
    if (!$isNewEmployee && in_array($ctName, ['RETIREMENT','RESIGNATION'], true)) {
        $empIdCheck = mysqli_real_escape_string($conn, $employeeRaw);
        $pendingQ = mysqli_query($conn, "SELECT 1 FROM clearance_history ch JOIN clearance_type ct ON ch.ctype_id = ct.clearance_code WHERE ch.emp_id='$empIdCheck' AND ch.status='0' AND UPPER(ct.clearance_name) IN ('RETIREMENT','RESIGNATION') LIMIT 1");
        if ($pendingQ && mysqli_num_rows($pendingQ) > 0) {
            echo json_encode([
                'status' => 409,
                'message' => 'This employee already has an ongoing RESIGNATION/RETIREMENT clearance.'
            ]);
            return false;
        }
    }

    $shouldPrint = false; // front-end will decide to auto-open print window
    $statusValue = 0;     // default processing
    $isReadValue = 0;     // default unread / not released
    $empIdToUse = '';
    $multiSqlParts = [];

    if ($isNewEmployee) {
        if ($newEmployeeName === '') {
            echo json_encode(['status'=>422,'message'=>'Please enter the new employee name.']);
            return false;
        }
        if (gso_employee_name_exists($conn, $newEmployeeName)) {
            echo json_encode(['status'=>422,'message'=>'Employee name already exists.']);
            return false;
        }

        // Immediate release for newly added employees (covers R/R case as requested)
        $statusValue = 1;
        $isReadValue = 1;
        $shouldPrint = true;

        // Allocate emp_id on save (not on page load) to avoid duplicates under concurrent users
        $created = gso_create_employee_atomic($conn, $newEmployeeName, $dept, $position, $empStatusForEmployee, null);
        if(!isset($created['ok']) || !$created['ok']){
            echo json_encode(['status'=>500,'message'=>$created['message'] ?? 'Error creating employee.']);
            return false;
        }
        $empIdToUse = (string)$created['emp_id'];
    } else {
        // Existing employee id
        $empIdToUse = mysqli_real_escape_string($conn, $employeeRaw);
        // Update employee status (and position if provided) based on selected clearance type
        if ($position !== '') {
            $multiSqlParts[] = "UPDATE employee SET position = '$position', emp_status = $empStatusForEmployee WHERE emp_id = '$empIdToUse'";
        } else {
            $multiSqlParts[] = "UPDATE employee SET emp_status = $empStatusForEmployee WHERE emp_id = '$empIdToUse'";
        }

        // Note: keep a tolerant list to handle common spelling variants in the DB (e.g. 'VACTION LEAVE').
        $isAutoPrintType = in_array($ctName, ['TRAVEL ABROAD','MATERNITY LEAVE','VACATION LEAVE','VACTION LEAVE'], true);
        $isRetRes = in_array($ctName, ['RETIREMENT','RESIGNATION'], true);

        if ($isAutoPrintType) {
            // Immediate release for TRAVEL ABROAD / MATERNITY / VACATION
            $statusValue = 1;
            $isReadValue = 1;
            $shouldPrint = true;
        } elseif ($isRetRes) {
            // Manual approval required if there are active accountabilities
            $hasAccountability = false;
            $acctQuery = mysqli_query($conn, "SELECT 1 FROM general_fund_property_history WHERE emp_id='$empIdToUse' AND status='1' LIMIT 1");
            if ($acctQuery && mysqli_num_rows($acctQuery) > 0) { $hasAccountability = true; }
            if ($hasAccountability) {
                // Keep processing to allow property unit clearance
                $statusValue = 0;
                $isReadValue = 0;
                $shouldPrint = false;
            } else {
                // No active accountability, immediate release
                $statusValue = 1;
                $isReadValue = 1;
                $shouldPrint = true;
            }
        } else {
            // Other clearance categories default to manual processing (no auto print)
            $statusValue = 0;
            $isReadValue = 0;
            $shouldPrint = false;
        }
    }

    // Core inserts common to all branches
    $multiSqlParts[] = "INSERT INTO property_clearance (emp_id,ctype_id,dept_id,address,city,control_number,or_number) VALUES ('$empIdToUse', '$clearanceCode', '$dept', '$address', '$city', '$controlno', '$orNumber')";
    $multiSqlParts[] = "INSERT INTO clearance_history (emp_id,ctype_id,dept_id,control_number,or_number,status,is_read) VALUES ('$empIdToUse', '$clearanceCode', '$dept', '$controlno', '$orNumber', $statusValue, $isReadValue)";

    $finalSql = implode(';', $multiSqlParts) . ';';
    $query = mysqli_multi_query($conn, $finalSql);

    if ($query) {
        echo json_encode([
            'status' => 200,
            'message' => 'Form submitted successfully.',
            'control_number' => $controlno,
            'should_print' => $shouldPrint,
            'release_status' => $statusValue,
            'emp_id' => $empIdToUse
        ]);
        return false;
    } else {
        echo json_encode([
            'status' => 500,
            'message' => 'Error: ' . mysqli_error($conn),
        ]);
        return false;
    }
}

//save property clearance details (no status/printing changes)
if (isset($_POST['save_pc_details'])) {
    $cid = mysqli_real_escape_string($conn, $_POST['cid'] ?? '');
    if ($cid === '') {
        return json_response(422, 'Missing control number.');
    }

    if (pc_reprint_count($conn, $cid) >= 1) {
        return json_response(423, 'This clearance is locked after re-print.');
    }

    $empId = mysqli_real_escape_string($conn, $_POST['emp_id'] ?? '');
    $empName = mysqli_real_escape_string($conn, strtoupper($_POST['emp_name'] ?? ''));
    $position = mysqli_real_escape_string($conn, strtoupper($_POST['position'] ?? ''));
    $deptId = mysqli_real_escape_string($conn, $_POST['dept_id'] ?? '');
    $ctypeCode = mysqli_real_escape_string($conn, $_POST['ctype_id'] ?? '');
    $street = mysqli_real_escape_string($conn, strtoupper($_POST['address'] ?? ''));
    $city = mysqli_real_escape_string($conn, strtoupper($_POST['city'] ?? ''));
    $orNumber = mysqli_real_escape_string($conn, $_POST['or_number'] ?? '');

    if ($empId === '' || $empName === '' || $position === '' || $deptId === '' || $ctypeCode === '' || $street === '' || $city === '') {
        return json_response(422, 'Please complete all required fields.');
    }

    // Validate clearance code exists
    $ctChk = mysqli_query($conn, "SELECT 1 FROM clearance_type WHERE clearance_code='$ctypeCode' LIMIT 1");
    if (!$ctChk || mysqli_num_rows($ctChk) !== 1) {
        return json_response(422, 'Invalid clearance type.');
    }

    $ctypeName = '';
    $ctNameQ = mysqli_query($conn, "SELECT UPPER(clearance_name) AS clearance_name FROM clearance_type WHERE clearance_code='$ctypeCode' LIMIT 1");
    if ($ctNameQ && mysqli_num_rows($ctNameQ) === 1) {
        $ct = mysqli_fetch_assoc($ctNameQ);
        $ctypeName = isset($ct['clearance_name']) ? strtoupper(trim($ct['clearance_name'])) : '';
    }
    $empStatusForEmployee = employee_status_from_clearance_name($ctypeName);

    // Validate department code exists
    $dpChk = mysqli_query($conn, "SELECT 1 FROM department WHERE department_code='$deptId' LIMIT 1");
    if (!$dpChk || mysqli_num_rows($dpChk) !== 1) {
        return json_response(422, 'Invalid department.');
    }

    $agencies = employee_agency_from_department_code($deptId);
    $ok1 = mysqli_query($conn, "UPDATE employee SET emp_name='$empName', position='$position', agencies='$agencies', department_code='$deptId', emp_status=$empStatusForEmployee WHERE emp_id='$empId'");
    if (!$ok1) {
        return json_response(500, 'Failed to update employee.', ['error' => mysqli_error($conn)]);
    }

    $ok2 = mysqli_query($conn, "UPDATE property_clearance SET dept_id='$deptId', ctype_id='$ctypeCode', address='$street', city='$city', or_number='$orNumber' WHERE control_number='$cid'");
    if (!$ok2) {
        return json_response(500, 'Failed to update clearance.', ['error' => mysqli_error($conn)]);
    }

    $ok3 = mysqli_query($conn, "UPDATE clearance_history SET dept_id='$deptId', ctype_id='$ctypeCode', or_number='$orNumber' WHERE control_number='$cid'");
    if (!$ok3) {
        return json_response(500, 'Failed to update clearance history.', ['error' => mysqli_error($conn)]);
    }

    return json_response(200, 'Saved successfully.');
}

//update property clearance
if(isset($_POST['update_pc'])){
    $updatepcid = mysqli_real_escape_string($conn,$_POST['cid']);
    if ($updatepcid !== '' && pc_reprint_count($conn, $updatepcid) >= 1) {
        return json_response(423, 'This clearance is locked after re-print.');
    }
    $empId = mysqli_real_escape_string($conn, $_POST['emp_id'] ?? '');
    $empName = mysqli_real_escape_string($conn, strtoupper($_POST['emp_name'] ?? ''));
    $position = mysqli_real_escape_string($conn, strtoupper($_POST['position'] ?? ''));
    $deptId = mysqli_real_escape_string($conn, $_POST['dept_id'] ?? '');
    $ctypeCode = mysqli_real_escape_string($conn, $_POST['ctype_id'] ?? '');
    $street = mysqli_real_escape_string($conn, strtoupper($_POST['address'] ?? ''));
    $city = mysqli_real_escape_string($conn, strtoupper($_POST['city'] ?? ''));
    $orNumber = mysqli_real_escape_string($conn, $_POST['or_number'] ?? '');
    $today = date("Y-m-d H:i:s");

    $shouldPrint = true;
    $ctypeName = '';
    $empIdForRules = $empId;
    if ($empIdForRules === '') {
        $metaQ = mysqli_query($conn, "SELECT emp_id FROM property_clearance WHERE control_number='$updatepcid' LIMIT 1");
        if ($metaQ && mysqli_num_rows($metaQ) === 1) {
            $meta = mysqli_fetch_assoc($metaQ);
            $empIdForRules = isset($meta['emp_id']) ? mysqli_real_escape_string($conn, $meta['emp_id']) : '';
        }
    }
    if ($ctypeCode !== '') {
        $ctQ = mysqli_query($conn, "SELECT UPPER(clearance_name) AS clearance_name FROM clearance_type WHERE clearance_code='$ctypeCode' LIMIT 1");
        if ($ctQ && mysqli_num_rows($ctQ) === 1) {
            $ct = mysqli_fetch_assoc($ctQ);
            $ctypeName = isset($ct['clearance_name']) ? strtoupper(trim($ct['clearance_name'])) : '';
        }
    }
    if ($ctypeName === '') {
        $metaQ2 = mysqli_query($conn, "SELECT UPPER(ct.clearance_name) AS clearance_name FROM property_clearance pc JOIN clearance_type ct ON pc.ctype_id = ct.clearance_code WHERE pc.control_number='$updatepcid' LIMIT 1");
        if ($metaQ2 && mysqli_num_rows($metaQ2) === 1) {
            $meta2 = mysqli_fetch_assoc($metaQ2);
            $ctypeName = isset($meta2['clearance_name']) ? strtoupper(trim($meta2['clearance_name'])) : '';
        }
    }

    $empStatusForEmployee = employee_status_from_clearance_name($ctypeName);

    if (in_array($ctypeName, ['RETIREMENT', 'RESIGNATION'], true) && $empIdForRules !== '') {
        $blockQ = mysqli_query($conn, "SELECT 1 FROM general_fund_property_history WHERE emp_id='$empIdForRules' AND status='1' LIMIT 1");
        if ($blockQ && mysqli_num_rows($blockQ) > 0) {
            $shouldPrint = false;
        }
    }

    if ($empId !== '' && $empName !== '' && $position !== '' && $deptId !== '') {
        $agencies = employee_agency_from_department_code($deptId);
        mysqli_query($conn, "UPDATE employee SET emp_name='$empName', position='$position', agencies='$agencies', department_code='$deptId', emp_status=$empStatusForEmployee WHERE emp_id='$empId'");
    }

    $query = mysqli_query($conn, "UPDATE property_clearance SET dept_id='$deptId', ctype_id='$ctypeCode', address='$street', city='$city', or_number='$orNumber' WHERE control_number ='$updatepcid'");
    
    if ($query) {
        if ($shouldPrint) {
            mysqli_query($conn, "UPDATE clearance_history SET dept_id='$deptId', ctype_id='$ctypeCode', status ='$isPrinted', release_date='$today', or_number='$orNumber' WHERE control_number = '$updatepcid'");
        } else {
            mysqli_query($conn, "UPDATE clearance_history SET dept_id='$deptId', ctype_id='$ctypeCode', or_number='$orNumber' WHERE control_number = '$updatepcid'");
        }
        $res = [
            'status' => 200,
            'message' => $shouldPrint ? 'Form submitted successfully!' : 'Updated successfully, but printing is blocked (status=1 found).',
            'should_print' => $shouldPrint,
        ];
        echo json_encode($res);
        return false;
    } else {
        $res = [
            'status' => 500,
            'message' => 'opps..something went wrong..',
        ];
        echo json_encode($res);
        return false;
    }
}
//update authorization clearance
if(isset($_POST['update_ac'])){
    $updateacid = mysqli_real_escape_string($conn,$_POST['cid']);
    $address = mysqli_real_escape_string($conn,$_POST['address']);
    $city = mysqli_real_escape_string($conn,$_POST['city']);
    $ctype = mysqli_real_escape_string($conn,$_POST['ctype']);  
    $dateFrom = mysqli_real_escape_string($conn,$_POST['dateFrom']);
    $dateTo = mysqli_real_escape_string($conn,$_POST['dateTo']);
    $location = mysqli_real_escape_string($conn,strtoupper($_POST['location']));
    $remarks = mysqli_real_escape_string($conn,$_POST['remarks']);
    $today = date("Y-m-d H:i:s");

    $query = mysqli_query($conn, "UPDATE authorization_clearance SET ctype_id='$ctype',address='$address',city='$city',location='$location',from_date='$dateFrom',to_date='$dateTo',remarks='$remarks' WHERE control_number ='$updateacid' ");
    
    if ($query) {
        mysqli_query($conn, "UPDATE clearance_history SET status ='$isPrinted', release_date='$today' WHERE control_number = '$updateacid'");
        $res = [
            'status' => 200,
            'message' => 'Updated succesfully!',
        ];
        echo json_encode($res);
        return false;
    } else {
        $res = [
            'status' => 500,
            'message' => 'opps..something went wrong..',
        ];
        echo json_encode($res);
        return false;
    }
}
//update other clearance
if(isset($_POST['update_oc'])){
    $updateocid = mysqli_real_escape_string($conn,$_POST['cid']);
    $address = mysqli_real_escape_string($conn,$_POST['address']);
    $city = mysqli_real_escape_string($conn,$_POST['city']);
    $ctype = mysqli_real_escape_string($conn,$_POST['ctype']);  
    $remarks = mysqli_real_escape_string($conn,$_POST['remarks']);
    $today = date("Y-m-d H:i:s");

    $query = mysqli_query($conn, "UPDATE reference_clearance SET ctype_id='$ctype',address='$address',city='$city',remarks='$remarks' WHERE control_number ='$updateocid' ");
    
    if ($query) {
        mysqli_query($conn, "UPDATE clearance_history SET status ='$isPrinted', release_date='$today' WHERE control_number = '$updateocid'");
        $res = [
            'status' => 200,
            'message' => 'Updated succesfully!',
        ];
        echo json_encode($res);
        return false;
    } else {
        $res = [
            'status' => 500,
            'message' => 'opps..something went wrong..',
        ];
        echo json_encode($res);
        return false;
    }
}
//cancel authorization clearance
if (isset($_POST['cancelBtn_id'])) {
    $cid = mysqli_real_escape_string($conn, $_POST['cancelBtnClearance']);

    $sql = "UPDATE clearance_history SET status='$isCancel' WHERE control_number = '$cid' ";
    $query = mysqli_query($conn, $sql);

    if ($query) {
        $res = [
            'status' => 200,
            'message' => 'Cancelled successfully!',
        ];
        echo json_encode($res);
        return false;
    } else {
        $res = [
            'status' => 500,
            'message' => 'opps..something went wrong..',
        ];
        echo json_encode($res);
        return false;
    }
}
//cancel property clearance
if (isset($_POST['propertyClearanceCancelBtn_id'])) {
    $cid = mysqli_real_escape_string($conn, $_POST['propertyClearanceCancelBtn']);

    $sql = "UPDATE clearance_history SET status='$isCancel' WHERE control_number = '$cid' ";
    $query = mysqli_query($conn, $sql);

    if ($query) {
        $res = [
            'status' => 200,
            'message' => 'Cancelled successfully!',
        ];
        echo json_encode($res);
        return false;
    } else {
        $res = [
            'status' => 500,
            'message' => 'opps..something went wrong..',
        ];
        echo json_encode($res);
        return false;
    }
}
//cancel other clearance
if (isset($_POST['OtherClearanceCancelBtn_id'])) {
    $cid = mysqli_real_escape_string($conn, $_POST['OtherClearanceCancelBtn']);
    $query = mysqli_query($conn, "UPDATE clearance_history SET status='$isCancel' WHERE control_number = '$cid' ");

    if ($query) {
        $res = [
            'status' => 200,
            'message' => 'Cancelled successfully!',
        ];
        echo json_encode($res);
        return false;
    } else {
        $res = [
            'status' => 500,
            'message' => 'opps..something went wrong..',
        ];
        echo json_encode($res);
        return false;
    }
}
//fetch general fund property info subject for return to stock
if (isset($_GET['stockItems'])) {
    $par_number = mysqli_real_escape_string($conn, $_GET['stockItems']);
    $sql = "SELECT r.par_number,r.category,r.dept_id,d.department_code,r.status FROM return_history AS r 
    JOIN return_to_stock AS s ON r.par_number = s.par_number
    JOIN department AS d ON r.dept_id = d.department_code
    WHERE r.par_number = '$par_number' AND status = '2' LIMIT 1";
    $query = mysqli_query($conn, $sql);

    if (mysqli_num_rows($query) == 1) {
        $returnProperty = mysqli_fetch_array($query);

        $res = [
            'status' => 200,
            'message' => 'Property number fetch successfully',
            'data' => $returnProperty,
        ];
        echo json_encode($res);
        return false;
    } else {
        $res = [
            'status' => 422,
            'message' => 'No property number found',
        ];
        echo json_encode($res);
        return false;
    }
}
//to declare items as unserviceable
if (isset($_POST['unserviceable'])) {
    $par = mysqli_real_escape_string($conn, $_POST['parnum']);
    $dept = mysqli_real_escape_string($conn, $_POST['deptid']);
    $cat = mysqli_real_escape_string($conn, $_POST['cat']);
    $refnumber = mysqli_real_escape_string($conn, $_POST['refnumber']);

    // Pull from return_to_stock (the single staging table for both GF and SEF)
    $qFund = mysqli_query($conn, "SELECT id, COALESCE(fund,'') AS fund FROM return_to_stock WHERE par_number = '{$par}' LIMIT 1");
    if (!$qFund || mysqli_num_rows($qFund) !== 1) {
        echo json_encode(['status'=>422,'message'=>'Item not found in return_to_stock.']);
        return false;
    }
    $fundRow = mysqli_fetch_assoc($qFund);
    $returnStockId = (int)($fundRow['id'] ?? 0);
    if ($returnStockId <= 0) {
        echo json_encode(['status'=>500,'message'=>'Invalid id in return_to_stock.']);
        return false;
    }
    $fund = strtoupper(trim((string)($fundRow['fund'] ?? '')));
    $isSefFund = ($fund !== '' && (strpos($fund, 'SEF') !== false || strpos($fund, 'SPECIAL EDUCATION') !== false));

    // Explicit columns (no SELECT *) so auto-increment id works even if schemas diverge.
    $cols = "fund,category,item,model,description,serial_number,serial_number_2,par_number,unit_value,date_aquired,account_code,supplier,par_ics_number,purchase_order,purchase_request,obr_number,jev_number,remarks";

    mysqli_begin_transaction($conn);
    try {
        // Ensure the unserviceable list always has the item (upsert-ish).
        mysqli_query($conn, "DELETE FROM unserviceable_items WHERE par_number = '{$par}'");
        // Keep `unserviceable_items.id` aligned to the staging row's `return_to_stock.id`.
        $ins = mysqli_query($conn, "INSERT INTO unserviceable_items (id, {$cols}) SELECT {$returnStockId} AS id, {$cols} FROM return_to_stock WHERE par_number = '{$par}' LIMIT 1");
        if (!$ins) {
            throw new Exception('Insert unserviceable_items failed: ' . mysqli_error($conn));
        }

        $hist = mysqli_query($conn, "INSERT INTO unserviceable_items_history (dept_id,par_number,reference_number,category,created_at) VALUES ('{$dept}','{$par}','{$refnumber}','{$cat}','{$today}')");
        if (!$hist) {
            throw new Exception('Insert unserviceable_items_history failed: ' . mysqli_error($conn));
        }

        // Mark the last property history row inactive for the correct fund.
        if ($isSefFund) {
            $upd = mysqli_query($conn, "UPDATE sef_property_history SET status = '0' WHERE property_number = '{$par}'");
        } else {
            $upd = mysqli_query($conn, "UPDATE general_fund_property_history SET status = '0' WHERE par_number = '{$par}'");
        }
        if (!$upd) {
            throw new Exception('Update property history failed: ' . mysqli_error($conn));
        }

        $del = mysqli_query($conn, "DELETE FROM return_to_stock WHERE par_number = '{$par}'");
        if (!$del) {
            throw new Exception('Delete return_to_stock failed: ' . mysqli_error($conn));
        }

        mysqli_commit($conn);
        echo json_encode(['status'=>200,'message'=>'Successfully moved to Unserviceable.']);
        return false;
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        echo json_encode(['status'=>500,'message'=>'Unable to mark unserviceable.']);
        return false;
    }
}

// Unserviceable (Disposal) - DataTables: account-code summary
if (isset($_POST['unserviceable_account_codes_dt'])) {
    header('Content-Type: application/json');

        $latestHistoryJoin = "
            LEFT JOIN (
                SELECT h1.*
                FROM unserviceable_items_history AS h1
                INNER JOIN (
                    SELECT par_number, MAX(created_at) AS max_created_at
                    FROM unserviceable_items_history
                    GROUP BY par_number
                ) AS h2
                    ON h1.par_number = h2.par_number
                 AND h1.created_at = h2.max_created_at
            ) AS h ON h.par_number = u.par_number
        ";

    $draw = isset($_POST['draw']) ? (int)$_POST['draw'] : 0;
    $start = isset($_POST['start']) ? max(0, (int)$_POST['start']) : 0;
    $length = isset($_POST['length']) ? (int)$_POST['length'] : 10;
    if ($length <= 0 || $length > 200) { $length = 10; }

    $search = '';
    if (isset($_POST['search']) && is_array($_POST['search']) && isset($_POST['search']['value'])) {
        $search = trim((string)$_POST['search']['value']);
    }

    $orderCol = 0;
    $orderDir = 'ASC';
    if (isset($_POST['order'][0]['column'])) {
        $orderCol = (int)$_POST['order'][0]['column'];
    }
    if (isset($_POST['order'][0]['dir']) && strtolower((string)$_POST['order'][0]['dir']) === 'desc') {
        $orderDir = 'DESC';
    }

    $colMap = [
        0 => 'account_code',
        1 => 'account_name',
        2 => 'item_count',
        3 => 'total_value'
    ];
    $orderBy = isset($colMap[$orderCol]) ? $colMap[$orderCol] : 'account_code';

    $codeExpr = "COALESCE(NULLIF(TRIM(u.account_code), ''), '(NO ACCOUNT CODE)')";

    $row = gso_query_one($conn, "
        SELECT COUNT(DISTINCT {$codeExpr}) AS cnt
        FROM unserviceable_items AS u
        {$latestHistoryJoin}
        WHERE COALESCE(h.status, 0) = 0
    ");
    $totalRecords = (int)($row['cnt'] ?? 0);

    $whereSql = ' WHERE COALESCE(h.status, 0) = 0 ';
    $whereTypes = '';
    $whereParams = [];
    if ($search !== '') {
        $whereSql .= " AND ({$codeExpr} LIKE ? OR COALESCE(ac.account_name, '') LIKE ?) ";
        $likeSearch = '%' . $search . '%';
        $whereTypes = 'ss';
        $whereParams = [$likeSearch, $likeSearch];
    }

    $filteredRecords = $totalRecords;
    if ($search !== '') {
        $rowF = gso_query_one($conn, "
            SELECT COUNT(*) AS cnt FROM (
                SELECT {$codeExpr} AS code_key
                FROM unserviceable_items AS u
                {$latestHistoryJoin}
                LEFT JOIN account_code AS ac ON ac.account_code = u.account_code
                {$whereSql}
                GROUP BY code_key
            ) AS x
        ", $whereTypes, $whereParams);
        $filteredRecords = (int)($rowF['cnt'] ?? 0);
    }

    $sql = "
        SELECT
            {$codeExpr} AS account_code,
            COALESCE(ac.account_name, '') AS account_name,
            COUNT(*) AS item_count,
            COALESCE(SUM(u.unit_value), 0) AS total_value
        FROM unserviceable_items AS u
        {$latestHistoryJoin}
        LEFT JOIN account_code AS ac ON ac.account_code = u.account_code
        {$whereSql}
        GROUP BY account_code, account_name
        ORDER BY {$orderBy} {$orderDir}
        LIMIT ?, ?
    ";

    $data = [];
    $dataTypes = $whereTypes . 'ii';
    $dataParams = $whereParams;
    $dataParams[] = $start;
    $dataParams[] = $length;
    list($stmt, $rows) = gso_query_all($conn, $sql, $dataTypes, $dataParams);
    if ($stmt instanceof mysqli_stmt) { $stmt->close(); }
    foreach ($rows as $r) {
        $code = (string)($r['account_code'] ?? '');
        $data[] = [
            'account_code' => $code,
            'account_name' => (string)($r['account_name'] ?? ''),
            'item_count' => (int)($r['item_count'] ?? 0),
            'total_value' => (float)($r['total_value'] ?? 0)
        ];
    }

    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => $totalRecords,
        'recordsFiltered' => $filteredRecords,
        'data' => $data
    ]);
    return false;
}

// Unserviceable (Disposal) - DataTables: items by account code
if (isset($_POST['unserviceable_items_by_account_code_dt'])) {
    header('Content-Type: application/json');

    $code = isset($_POST['code']) ? trim((string)$_POST['code']) : '';
    if ($code === '') {
        echo json_encode(['draw'=>0,'recordsTotal'=>0,'recordsFiltered'=>0,'data'=>[]]);
        return false;
    }

    $draw = isset($_POST['draw']) ? (int)$_POST['draw'] : 0;
    $start = isset($_POST['start']) ? max(0, (int)$_POST['start']) : 0;
    $length = isset($_POST['length']) ? (int)$_POST['length'] : 10;
    if ($length <= 0 || $length > 200) { $length = 10; }

    $search = '';
    if (isset($_POST['search']) && is_array($_POST['search']) && isset($_POST['search']['value'])) {
        $search = trim((string)$_POST['search']['value']);
    }

    $orderCol = 0;
    $orderDir = 'DESC';
    if (isset($_POST['order'][0]['column'])) {
        $orderCol = (int)$_POST['order'][0]['column'];
    }
    if (isset($_POST['order'][0]['dir']) && strtolower((string)$_POST['order'][0]['dir']) !== 'desc') {
        $orderDir = 'ASC';
    }

    $colMap = [
        0 => 'par_number',
        1 => 'par_number',
        2 => 'particular',
        3 => 'serial_number',
        4 => 'serial_number_2',
        5 => 'par_number',
        6 => 'department_name',
        7 => 'last_update'
    ];
    $orderBy = isset($colMap[$orderCol]) ? $colMap[$orderCol] : 'last_update';

    $codeExpr = "COALESCE(NULLIF(TRIM(u.account_code), ''), '(NO ACCOUNT CODE)')";
    $whereCode = " WHERE {$codeExpr} = ? AND COALESCE(h.status, 0) = 0 ";
    $baseTypes = 's';
    $baseParams = [$code];

    $whereSearch = '';
    $searchTypes = '';
    $searchParams = [];
    if ($search !== '') {
        $whereSearch = " AND (u.par_number LIKE ? OR COALESCE(u.model,'') LIKE ? OR COALESCE(u.description,'') LIKE ? OR COALESCE(u.serial_number,'') LIKE ? OR COALESCE(u.serial_number_2,'') LIKE ? OR COALESCE(d.department_name,'') LIKE ?) ";
        $likeSearch = '%' . $search . '%';
        $searchTypes = 'ssssss';
        $searchParams = [$likeSearch, $likeSearch, $likeSearch, $likeSearch, $likeSearch, $likeSearch];
    }

    $row = gso_query_one($conn, "
                SELECT COUNT(*) AS cnt
                FROM unserviceable_items AS u
                LEFT JOIN (
                    SELECT h1.*
                    FROM unserviceable_items_history AS h1
                    INNER JOIN (
                        SELECT par_number, MAX(created_at) AS max_created_at
                        FROM unserviceable_items_history
                        GROUP BY par_number
                    ) AS h2
                        ON h1.par_number = h2.par_number
                     AND h1.created_at = h2.max_created_at
                ) AS h ON h.par_number = u.par_number
                {$whereCode}
        ", $baseTypes, $baseParams);
    $totalRecords = (int)($row['cnt'] ?? 0);

    $filteredRecords = $totalRecords;
    if ($whereSearch !== '') {
        $rowF = gso_query_one($conn, "
            SELECT COUNT(*) AS cnt
            FROM unserviceable_items AS u
            LEFT JOIN (
              SELECT h1.*
              FROM unserviceable_items_history AS h1
              INNER JOIN (
                SELECT par_number, MAX(created_at) AS max_created_at
                FROM unserviceable_items_history
                GROUP BY par_number
              ) AS h2
                ON h1.par_number = h2.par_number
               AND h1.created_at = h2.max_created_at
            ) AS h ON h.par_number = u.par_number
            LEFT JOIN department AS d ON h.dept_id = d.department_code
            {$whereCode}
            {$whereSearch}
        ", $baseTypes . $searchTypes, array_merge($baseParams, $searchParams));
        $filteredRecords = (int)($rowF['cnt'] ?? 0);
    }

    $sql = "
        SELECT
            u.par_number,
            COALESCE(h.status, 0) AS status,
            COALESCE(u.model, '') AS model,
            COALESCE(u.description, '') AS description,
            COALESCE(u.serial_number, '') AS serial_number,
            COALESCE(u.serial_number_2, '') AS serial_number_2,
            COALESCE(d.department_name, 'N/A') AS department_name,
            COALESCE(h.created_at, '') AS last_update,
            CONCAT(COALESCE(u.model,''), CASE WHEN COALESCE(u.model,'') <> '' AND COALESCE(u.description,'') <> '' THEN ' - ' ELSE '' END, COALESCE(u.description,'')) AS particular
        FROM unserviceable_items AS u
        LEFT JOIN (
          SELECT h1.*
          FROM unserviceable_items_history AS h1
          INNER JOIN (
            SELECT par_number, MAX(created_at) AS max_created_at
            FROM unserviceable_items_history
            GROUP BY par_number
          ) AS h2
            ON h1.par_number = h2.par_number
           AND h1.created_at = h2.max_created_at
        ) AS h
          ON h.par_number = u.par_number
        LEFT JOIN department AS d
          ON h.dept_id = d.department_code
        {$whereCode}
        {$whereSearch}
        ORDER BY {$orderBy} {$orderDir}
        LIMIT ?, ?
    ";

    $data = [];
    $dataTypes = $baseTypes . $searchTypes . 'ii';
    $dataParams = array_merge($baseParams, $searchParams, [$start, $length]);
    list($stmt, $rows) = gso_query_all($conn, $sql, $dataTypes, $dataParams);
    if ($stmt instanceof mysqli_stmt) { $stmt->close(); }
    foreach ($rows as $r) {
        $data[] = [
            'status' => (int)($r['status'] ?? 0),
            'particular' => (string)($r['particular'] ?? ''),
            'serial_number' => (string)($r['serial_number'] ?? ''),
            'serial_number_2' => (string)($r['serial_number_2'] ?? ''),
            'par_number' => (string)($r['par_number'] ?? ''),
            'department_name' => (string)($r['department_name'] ?? ''),
            'last_update' => (string)($r['last_update'] ?? '')
        ];
    }

    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => $totalRecords,
        'recordsFiltered' => $filteredRecords,
        'data' => $data
    ]);
    return false;
}

// Unserviceable - Bulk action: save to IIRUP + mark as FOR DISPOSAL (status=1)
if (isset($_POST['unserviceable_mark_for_disposal'])) {
    header('Content-Type: application/json');

    if (!function_exists('ensure_disposal_table')) {
        function ensure_disposal_table($conn) {
            $chk = mysqli_query($conn, "SHOW TABLES LIKE 'disposal'");
            if ($chk && mysqli_num_rows($chk) === 1) { return true; }
            $sql = "CREATE TABLE IF NOT EXISTS disposal (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                disposal_reference VARCHAR(20) NOT NULL,
                status TINYINT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_disposal_reference (disposal_reference)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            return mysqli_query($conn, $sql) ? true : false;
        }
    }

    if (!function_exists('get_active_disposal_reference')) {
        function get_active_disposal_reference($conn) {
            if (!ensure_disposal_table($conn)) { return null; }
            $q = mysqli_query($conn, "SELECT disposal_reference FROM disposal WHERE status = 0 ORDER BY created_at DESC, id DESC LIMIT 1");
            if ($q && mysqli_num_rows($q) === 1) {
                $r = mysqli_fetch_assoc($q);
                $ref = trim((string)($r['disposal_reference'] ?? ''));
                return $ref !== '' ? $ref : null;
            }
            return null;
        }
    }

    $raw = isset($_POST['par_numbers']) ? (string)$_POST['par_numbers'] : '[]';
    $pars = json_decode($raw, true);
    if (!is_array($pars)) {
        echo json_encode(['status'=>400,'message'=>'Invalid selection.']);
        return false;
    }

    // Keep it safe and fast.
    $pars = array_values(array_unique(array_filter(array_map(function($v){
        $s = trim((string)$v);
        return $s === '' ? null : $s;
    }, $pars))));

    if (count($pars) === 0) {
        echo json_encode(['status'=>422,'message'=>'No items selected.']);
        return false;
    }
    if (count($pars) > 300) {
        echo json_encode(['status'=>422,'message'=>'Too many items selected.']);
        return false;
    }

    // Ensure iirup_report_items exists / accessible.
    $chk = mysqli_query($conn, "SELECT 1 FROM iirup_report_items LIMIT 1");
    if ($chk === false) {
        echo json_encode(['status'=>500,'message'=>'Database error: ' . mysqli_error($conn)]);
        return false;
    }

    // Enforce a single active disposal reference for the whole disposal period.
    $activeDisposalRef = get_active_disposal_reference($conn);
    if (!$activeDisposalRef) {
        echo json_encode(['status'=>422,'message'=>'No active disposal activity. Please create one first in Disposal.']);
        return false;
    }

    $saved = 0;
    $updated = 0;
    $skipped = 0;

    try {
        foreach ($pars as $par) {
            $parSafe = mysqli_real_escape_string($conn, $par);

        $row = null;
        $q = mysqli_query($conn, "
            SELECT
              u.par_number,
              u.account_code,
                            u.fund,
              u.item,
              u.model,
              u.description,
              u.serial_number,
              u.serial_number_2,
              u.unit_value,
              u.date_aquired,
              u.remarks,
              COALESCE(h.status, 0) AS last_status,
              COALESCE(h.dept_id, 0) AS dept_id,
              COALESCE(h.reference_number, '') AS reference_number,
              COALESCE(h.category, '') AS category,
              COALESCE(d.department_name, '') AS department_name
            FROM unserviceable_items AS u
            LEFT JOIN (
              SELECT h1.*
              FROM unserviceable_items_history AS h1
              INNER JOIN (
                SELECT par_number, MAX(created_at) AS max_created_at
                FROM unserviceable_items_history
                GROUP BY par_number
              ) AS h2
                ON h1.par_number = h2.par_number
               AND h1.created_at = h2.max_created_at
            ) AS h
              ON h.par_number = u.par_number
            LEFT JOIN department AS d
              ON h.dept_id = d.department_code
            WHERE u.par_number = '{$parSafe}'
            LIMIT 1
        ");
        if ($q && mysqli_num_rows($q) === 1) {
            $row = mysqli_fetch_assoc($q);
        }
        if (!$row) {
            continue;
        }

        $lastStatus = (int)($row['last_status'] ?? 0);
        if ($lastStatus === 2) {
            // Already disposed; keep it out of this action.
            $skipped++;
            continue;
        }
        // Ensure row exists in iirup_report_items (insert once per PAR).
        $exists = false;
        $qExists = mysqli_query($conn, "SELECT 1 FROM iirup_report_items WHERE par_number = '{$parSafe}' LIMIT 1");
        if ($qExists && mysqli_num_rows($qExists) === 1) {
            $exists = true;
        }

        // If it already exists but appraisal is NULL, normalize to 0.00.
        if ($exists) {
            mysqli_query($conn, "UPDATE iirup_report_items SET appraise_value = 0.00 WHERE par_number = '{$parSafe}' AND appraise_value IS NULL LIMIT 1");
            mysqli_query($conn, "UPDATE iirup_report_items SET total_appraise_value = 0.00 WHERE par_number = '{$parSafe}' AND total_appraise_value IS NULL LIMIT 1");
            $fundNow = trim((string)($row['fund'] ?? ''));
            if ($fundNow !== '') {
                $fundSafe = mysqli_real_escape_string($conn, $fundNow);
                mysqli_query($conn, "UPDATE iirup_report_items SET fund = '{$fundSafe}' WHERE par_number = '{$parSafe}' AND (fund IS NULL OR fund = '') LIMIT 1");
            }

            // Always assign the current active disposal reference to newly-marked items.
            $refSafe = mysqli_real_escape_string($conn, $activeDisposalRef);
            mysqli_query($conn, "UPDATE iirup_report_items SET disposal_reference = '{$refSafe}' WHERE par_number = '{$parSafe}' LIMIT 1");
        }

        if (!$exists) {
            $item = trim((string)($row['item'] ?? ''));
            $model = trim((string)($row['model'] ?? ''));
            $sn1 = trim((string)($row['serial_number'] ?? ''));
            $sn2 = trim((string)($row['serial_number_2'] ?? ''));

            // Requirement: item + model + serial_number + serial_number_2 must be saved into `particulars`.
            $parts = array_values(array_filter([$item, $model, $sn1, $sn2], function($v){
                return trim((string)$v) !== '';
            }));
            $particulars = implode(' - ', $parts);

            $qty = 1;
            $unitCost = (float)($row['unit_value'] ?? 0);
            // Default appraisal value to 0.00 on first save; user can input later.
            $appraiseValue = 0.00;
            $totalAppraiseValue = 0.00;
            $fund = trim((string)($row['fund'] ?? ''));
            $disposalRef = $activeDisposalRef;
            $remarks = (string)($row['remarks'] ?? '');

            $insSql = "
                INSERT INTO iirup_report_items
                    (par_number, particulars, qty, unit_cost, appraise_value, total_appraise_value, fund, disposal_reference, remarks, created_at)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ";
            $stmt = mysqli_prepare($conn, $insSql);
            if (!$stmt) {
                throw new Exception('Insert prepare failed: ' . mysqli_error($conn));
            }
            mysqli_stmt_bind_param(
                $stmt,
                'ssidddsss',
                $par,
                $particulars,
                $qty,
                $unitCost,
                $appraiseValue,
                $totalAppraiseValue,
                $fund,
                $disposalRef,
                $remarks
            );
            if (!mysqli_stmt_execute($stmt)) {
                $err = mysqli_stmt_error($stmt);
                mysqli_stmt_close($stmt);
                throw new Exception('Insert execute failed: ' . $err);
            }
            mysqli_stmt_close($stmt);
            $saved++;
        }

        if ($lastStatus === 1) {
            // Already marked for disposal.
            $skipped++;
            continue;
        }

        // Update ONLY (no insert) the latest history row for this PAR to status=1.
        $qUpd = mysqli_query(
            $conn,
            "UPDATE unserviceable_items_history SET status = 1 WHERE par_number = '{$parSafe}' ORDER BY created_at DESC LIMIT 1"
        );
        if ($qUpd && mysqli_affected_rows($conn) > 0) {
            $updated++;
        }
        }
    } catch (Throwable $e) {
        echo json_encode([
            'status' => 500,
            'message' => 'Server error: ' . $e->getMessage(),
            'data' => ['updated' => $updated, 'skipped' => $skipped]
        ]);
        return false;
    }

    echo json_encode([
        'status' => 200,
        'message' => 'Saved to IIRUP: ' . $saved . ' | Marked for disposal: ' . $updated,
        'data' => ['saved_to_iirup' => $saved, 'updated' => $updated, 'skipped' => $skipped]
    ]);
    return false;
}

// Unserviceable - Undo: move one item back to its original inventory table.
if (isset($_POST['unserviceable_undo_item'])) {
    header('Content-Type: application/json');

    $par = trim((string)($_POST['par_number'] ?? ''));
    if ($par === '') { return json_response(422, 'Missing property number.'); }

    $stmt = mysqli_prepare($conn, "
        SELECT u.*, COALESCE(h.status, 0) AS last_status
        FROM unserviceable_items AS u
        LEFT JOIN (
            SELECT h1.*
            FROM unserviceable_items_history AS h1
            INNER JOIN (
                SELECT par_number, MAX(created_at) AS max_created_at
                FROM unserviceable_items_history
                GROUP BY par_number
            ) AS h2 ON h1.par_number = h2.par_number AND h1.created_at = h2.max_created_at
        ) AS h ON h.par_number = u.par_number
        WHERE u.par_number = ?
        LIMIT 1
    ");
    if (!$stmt) { return json_response(500, 'Unable to prepare undo lookup.'); }
    mysqli_stmt_bind_param($stmt, 's', $par);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $item = ($res && mysqli_num_rows($res) === 1) ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    if (!$item) { return json_response(404, 'Item not found in unserviceable list.'); }
    if ((int)($item['last_status'] ?? 0) !== 0) { return json_response(422, 'Only pending unserviceable items can be undone.'); }

    $fund = strtoupper(trim((string)($item['fund'] ?? '')));
    $isSef = (strpos($fund, 'SEF') !== false || strpos($fund, 'SPECIAL EDUCATION') !== false);
    $targetTable = $isSef ? 'property_sef' : 'par_gen_fund';
    $targetNumber = $isSef ? 'property_number' : 'par_number';
    $targetId = $isSef ? 'sef_id' : 'pargf_id';
    $historyTable = $isSef ? 'sef_property_history' : 'general_fund_property_history';
    $historyDept = $isSef ? 'sch_id' : 'dept_id';
    $historyNumber = $isSef ? 'property_number' : 'par_number';

    $cols = "fund,category,item,model,description,serial_number,serial_number_2,{$targetNumber},unit_value,date_aquired,account_code,supplier,par_ics_number,purchase_order,purchase_request,obr_number,jev_number,remarks,created_at";
    $selectCols = "fund,category,item,model,description,serial_number,serial_number_2,par_number,unit_value,date_aquired,account_code,supplier,par_ics_number,purchase_order,purchase_request,obr_number,jev_number,remarks,created_at";
    $needsId = gso_column_exists($conn, $targetTable, $targetId) && !gso_id_is_auto_increment($conn, $targetTable, $targetId);
    $nextId = $needsId ? gso_next_int_id($conn, $targetTable, $targetId) : 0;
    $insertSql = $needsId
        ? "INSERT INTO {$targetTable} ({$targetId},{$cols}) SELECT ?, {$selectCols} FROM unserviceable_items WHERE par_number = ? LIMIT 1"
        : "INSERT INTO {$targetTable} ({$cols}) SELECT {$selectCols} FROM unserviceable_items WHERE par_number = ? LIMIT 1";

    mysqli_begin_transaction($conn);
    try {
        $stmtInsert = mysqli_prepare($conn, $insertSql);
        if (!$stmtInsert) { throw new Exception('Unable to prepare restore insert.'); }
        if ($needsId) { mysqli_stmt_bind_param($stmtInsert, 'is', $nextId, $par); }
        else { mysqli_stmt_bind_param($stmtInsert, 's', $par); }
        if (!mysqli_stmt_execute($stmtInsert) || mysqli_stmt_affected_rows($stmtInsert) < 1) {
            throw new Exception('Unable to restore item.');
        }
        mysqli_stmt_close($stmtInsert);

        $stmtHistory = mysqli_prepare($conn, "UPDATE {$historyTable} SET status = 1, created_at = ? WHERE {$historyNumber} = ? AND status = 0 ORDER BY created_at DESC LIMIT 1");
        if (!$stmtHistory) { throw new Exception('Unable to prepare history restore.'); }
        mysqli_stmt_bind_param($stmtHistory, 'ss', $today, $par);
        if (!mysqli_stmt_execute($stmtHistory)) { throw new Exception('Unable to restore history.'); }
        $historyRestored = mysqli_stmt_affected_rows($stmtHistory) > 0;
        mysqli_stmt_close($stmtHistory);

        if (!$historyRestored) {
            $stmtReturn = mysqli_prepare($conn, "SELECT emp_id, dept_id, reference_number, category FROM return_history WHERE par_number = ? ORDER BY id DESC LIMIT 1");
            if (!$stmtReturn) { throw new Exception('Unable to prepare return history lookup.'); }
            mysqli_stmt_bind_param($stmtReturn, 's', $par);
            mysqli_stmt_execute($stmtReturn);
            $returnRes = mysqli_stmt_get_result($stmtReturn);
            $returnRow = ($returnRes && mysqli_num_rows($returnRes) === 1) ? mysqli_fetch_assoc($returnRes) : null;
            mysqli_stmt_close($stmtReturn);
            if ($returnRow) {
                $statusActive = 1;
                $returnEmp = (string)($returnRow['emp_id'] ?? '');
                $returnDept = (string)($returnRow['dept_id'] ?? '');
                $returnRef = (string)($returnRow['reference_number'] ?? '');
                $returnCategory = (string)($returnRow['category'] ?? '');
                $stmtNewHistory = mysqli_prepare($conn, "INSERT INTO {$historyTable} (emp_id,{$historyDept},{$historyNumber},reference_number,status,category,created_at) VALUES (?,?,?,?,?,?,?)");
                if (!$stmtNewHistory) { throw new Exception('Unable to prepare new active history.'); }
                mysqli_stmt_bind_param($stmtNewHistory, 'ssssiss', $returnEmp, $returnDept, $par, $returnRef, $statusActive, $returnCategory, $today);
                if (!mysqli_stmt_execute($stmtNewHistory)) { throw new Exception('Unable to create active history.'); }
                mysqli_stmt_close($stmtNewHistory);
            }
        }

        $stmtMark = mysqli_prepare($conn, "UPDATE unserviceable_items_history SET status = 3 WHERE par_number = ? ORDER BY created_at DESC LIMIT 1");
        if (!$stmtMark) { throw new Exception('Unable to prepare undo history update.'); }
        mysqli_stmt_bind_param($stmtMark, 's', $par);
        mysqli_stmt_execute($stmtMark);
        mysqli_stmt_close($stmtMark);

        $stmtDelete = mysqli_prepare($conn, "DELETE FROM unserviceable_items WHERE par_number = ? LIMIT 1");
        if (!$stmtDelete) { throw new Exception('Unable to prepare unserviceable delete.'); }
        mysqli_stmt_bind_param($stmtDelete, 's', $par);
        if (!mysqli_stmt_execute($stmtDelete)) { throw new Exception('Unable to remove unserviceable item.'); }
        mysqli_stmt_close($stmtDelete);

        mysqli_commit($conn);
        gso_log_activity($conn, $uid, $uip, "Restored unserviceable property {$par} to " . ($isSef ? 'SEF' : 'General Fund') . '.');
        return json_response(200, 'Item restored to ' . ($isSef ? 'SEF' : 'General Fund') . '.');
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        return json_response(500, 'Undo failed: ' . $e->getMessage());
    }
}

// Disposal Activities - DataTables list
if (isset($_POST['disposal_activities_dt'])) {
    header('Content-Type: application/json');

    if (!function_exists('ensure_disposal_table')) {
        function ensure_disposal_table($conn) {
            $chk = mysqli_query($conn, "SHOW TABLES LIKE 'disposal'");
            if ($chk && mysqli_num_rows($chk) === 1) { return true; }
            $sql = "CREATE TABLE IF NOT EXISTS disposal (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                disposal_reference VARCHAR(20) NOT NULL,
                status TINYINT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_disposal_reference (disposal_reference)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            return mysqli_query($conn, $sql) ? true : false;
        }
    }

    if (!ensure_disposal_table($conn)) {
        echo json_encode(['draw'=>0,'recordsTotal'=>0,'recordsFiltered'=>0,'data'=>[]]);
        return false;
    }

    $draw = isset($_POST['draw']) ? (int)$_POST['draw'] : 0;
    $start = isset($_POST['start']) ? max(0, (int)$_POST['start']) : 0;
    $length = isset($_POST['length']) ? (int)$_POST['length'] : 10;
    if ($length <= 0 || $length > 200) { $length = 10; }

    $search = '';
    if (isset($_POST['search']) && is_array($_POST['search']) && isset($_POST['search']['value'])) {
        $search = trim((string)$_POST['search']['value']);
    }

    $orderCol = isset($_POST['order'][0]['column']) ? (int)$_POST['order'][0]['column'] : 0;
    $orderDir = (isset($_POST['order'][0]['dir']) && strtolower((string)$_POST['order'][0]['dir']) === 'asc') ? 'ASC' : 'DESC';
    $colMap = [0=>'created_at',1=>'disposal_reference',2=>'status',3=>'created_at'];
    $orderBy = isset($colMap[$orderCol]) ? $colMap[$orderCol] : 'created_at';

    $row = gso_query_one($conn, "SELECT COUNT(*) AS cnt FROM disposal");
    $totalRecords = (int)($row['cnt'] ?? 0);

    $whereSql = '';
    $whereTypes = '';
    $whereParams = [];
    if ($search !== '') {
        $whereSql = " WHERE (disposal_reference LIKE ? OR DATE_FORMAT(created_at, '%Y-%m-%d') LIKE ?) ";
        $likeSearch = '%' . $search . '%';
        $whereTypes = 'ss';
        $whereParams = [$likeSearch, $likeSearch];
    }

    $filteredRecords = $totalRecords;
    if ($whereSql !== '') {
        $rf = gso_query_one($conn, "SELECT COUNT(*) AS cnt FROM disposal {$whereSql}", $whereTypes, $whereParams);
        $filteredRecords = (int)($rf['cnt'] ?? 0);
    }

    $sql = "SELECT id, disposal_reference, status, created_at FROM disposal {$whereSql} ORDER BY {$orderBy} {$orderDir} LIMIT ?, ?";
    $data = [];
    list($stmt, $rows) = gso_query_all($conn, $sql, $whereTypes . 'ii', array_merge($whereParams, [$start, $length]));
    if ($stmt instanceof mysqli_stmt) { $stmt->close(); }
    foreach ($rows as $row) {
        $data[] = [
            'id' => (int)($row['id'] ?? 0),
            'disposal_reference' => (string)($row['disposal_reference'] ?? ''),
            'status' => (int)($row['status'] ?? 0),
            'created_at' => (string)($row['created_at'] ?? '')
        ];
    }

    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => $totalRecords,
        'recordsFiltered' => $filteredRecords,
        'data' => $data
    ]);
    return false;
}

// Disposal Activities - Create (only one active at a time)
if (isset($_POST['disposal_create'])) {
    header('Content-Type: application/json');

    if (!function_exists('ensure_disposal_table')) {
        function ensure_disposal_table($conn) {
            $chk = mysqli_query($conn, "SHOW TABLES LIKE 'disposal'");
            if ($chk && mysqli_num_rows($chk) === 1) { return true; }
            $sql = "CREATE TABLE IF NOT EXISTS disposal (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                disposal_reference VARCHAR(20) NOT NULL,
                status TINYINT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_disposal_reference (disposal_reference)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            return mysqli_query($conn, $sql) ? true : false;
        }
    }

    if (!ensure_disposal_table($conn)) {
        echo json_encode(['status'=>500,'message'=>'Unable to initialize disposal table.']);
        return false;
    }

    $qActive = mysqli_query($conn, "SELECT id, disposal_reference FROM disposal WHERE status = 0 ORDER BY created_at DESC, id DESC LIMIT 1");
    if ($qActive && mysqli_num_rows($qActive) === 1) {
        $r = mysqli_fetch_assoc($qActive);
        echo json_encode(['status'=>200,'message'=>'Active disposal activity already exists.', 'data'=>['id'=>(int)$r['id'], 'disposal_reference'=>(string)$r['disposal_reference']]]);
        return false;
    }

    $prefix = date('Ym') . '-D';
    $like = mysqli_real_escape_string($conn, $prefix) . '%';
    $qLast = mysqli_query($conn, "SELECT disposal_reference FROM disposal WHERE disposal_reference LIKE '{$like}' ORDER BY disposal_reference DESC LIMIT 1");
    $next = 1;
    if ($qLast && mysqli_num_rows($qLast) === 1) {
        $lr = mysqli_fetch_assoc($qLast);
        $lastRef = (string)($lr['disposal_reference'] ?? '');
        if (preg_match('/^(\d{6})-D(\d{3})$/', $lastRef, $m)) {
            $next = ((int)$m[2]) + 1;
        }
    }
    if ($next < 1) { $next = 1; }
    if ($next > 999) { $next = 999; }
    $ref = $prefix . str_pad((string)$next, 3, '0', STR_PAD_LEFT);
    $refSafe = mysqli_real_escape_string($conn, $ref);

    $ok = mysqli_query($conn, "INSERT INTO disposal (disposal_reference, status, created_at) VALUES ('{$refSafe}', 0, NOW())");
    if (!$ok) {
        echo json_encode(['status'=>500,'message'=>'Failed to create disposal activity.']);
        return false;
    }

    echo json_encode(['status'=>200,'message'=>'Disposal activity created.', 'data'=>['disposal_reference'=>$ref]]);
    return false;
}

// Disposal Activities - Get active (status=0)
if (isset($_POST['disposal_get_active'])) {
    header('Content-Type: application/json');

    if (!function_exists('ensure_disposal_table')) {
        function ensure_disposal_table($conn) {
            $chk = mysqli_query($conn, "SHOW TABLES LIKE 'disposal'");
            if ($chk && mysqli_num_rows($chk) === 1) { return true; }
            $sql = "CREATE TABLE IF NOT EXISTS disposal (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                disposal_reference VARCHAR(20) NOT NULL,
                status TINYINT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_disposal_reference (disposal_reference)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            return mysqli_query($conn, $sql) ? true : false;
        }
    }

    if (!ensure_disposal_table($conn)) {
        echo json_encode(['status'=>500,'message'=>'Unable to initialize disposal table.']);
        return false;
    }

    $q = mysqli_query($conn, "SELECT id, disposal_reference, status, created_at FROM disposal WHERE status = 0 ORDER BY created_at DESC, id DESC LIMIT 1");
    if ($q && mysqli_num_rows($q) === 1) {
        $r = mysqli_fetch_assoc($q);
        echo json_encode(['status'=>200,'message'=>'OK','data'=>[
            'id'=>(int)($r['id'] ?? 0),
            'disposal_reference'=>(string)($r['disposal_reference'] ?? ''),
            'status'=>(int)($r['status'] ?? 0),
            'created_at'=>(string)($r['created_at'] ?? '')
        ]]);
        return false;
    }

    echo json_encode(['status'=>200,'message'=>'OK','data'=>null]);
    return false;
}

// Disposal Activities - Info summary
if (isset($_POST['disposal_get_info'])) {
    header('Content-Type: application/json');

    $ref = isset($_POST['disposal_reference']) ? trim((string)$_POST['disposal_reference']) : '';
    if ($ref === '') {
        echo json_encode(['status'=>422,'message'=>'Missing disposal reference.']);
        return false;
    }

    $refSafe = mysqli_real_escape_string($conn, $ref);

    $info = [
        'disposal_reference' => $ref,
        'status' => null,
        'created_at' => null,
        'items_count' => 0,
        'qty_total' => 0,
        'total_appraise_value' => 0.0
    ];

    $qHdr = mysqli_query($conn, "SELECT status, created_at FROM disposal WHERE disposal_reference = '{$refSafe}' LIMIT 1");
    if ($qHdr && mysqli_num_rows($qHdr) === 1) {
        $r = mysqli_fetch_assoc($qHdr);
        $info['status'] = (int)($r['status'] ?? 0);
        $info['created_at'] = (string)($r['created_at'] ?? '');
    }

    $qItems = mysqli_query($conn, "
        SELECT
            COUNT(*) AS items_count,
            COALESCE(SUM(COALESCE(qty, 1)), 0) AS qty_total,
            COALESCE(SUM(COALESCE(total_appraise_value, (COALESCE(qty, 1) * COALESCE(appraise_value, 0)))), 0) AS total_appraise_value
        FROM iirup_report_items
        WHERE COALESCE(disposal_reference,'') = '{$refSafe}'
    ");
    if ($qItems && ($ri = mysqli_fetch_assoc($qItems))) {
        $info['items_count'] = (int)($ri['items_count'] ?? 0);
        $info['qty_total'] = (int)($ri['qty_total'] ?? 0);
        $info['total_appraise_value'] = (float)($ri['total_appraise_value'] ?? 0);
    }

    echo json_encode(['status'=>200,'message'=>'OK','data'=>$info]);
    return false;
}

// IIRUP Info - Get (per disposal reference)
if (isset($_POST['iirup_info_get'])) {
    header('Content-Type: application/json');

    $ref = isset($_POST['disposal_reference']) ? trim((string)$_POST['disposal_reference']) : '';
    if ($ref === '') {
        echo json_encode(['status'=>422,'message'=>'Missing disposal reference.']);
        return false;
    }

    if (!function_exists('table_exists')) {
        function table_exists($conn, $tableName) {
            $t = mysqli_real_escape_string($conn, (string)$tableName);
            $q = mysqli_query($conn, "SHOW TABLES LIKE '{$t}'");
            return ($q && mysqli_num_rows($q) === 1);
        }
    }
    if (!function_exists('column_exists')) {
        function column_exists($conn, $tableName, $columnName) {
            $t = mysqli_real_escape_string($conn, (string)$tableName);
            $c = mysqli_real_escape_string($conn, (string)$columnName);
            $q = mysqli_query($conn, "SHOW COLUMNS FROM `{$t}` LIKE '{$c}'");
            return ($q && mysqli_num_rows($q) === 1);
        }
    }
    if (!function_exists('ensure_iirup_info_tables')) {
        function ensure_iirup_info_tables($conn) {
            // iirup_info
            if (!table_exists($conn, 'iirup_info')) {
                $sql = "CREATE TABLE IF NOT EXISTS iirup_info (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    disposal_reference VARCHAR(20) NOT NULL,
                    acccountable_officer VARCHAR(255) NOT NULL,
                    designation VARCHAR(255) NOT NULL,
                    station VARCHAR(255) NOT NULL,
                    disposal_chairperson VARCHAR(255) NOT NULL,
                    local_chief_executive VARCHAR(255) NOT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_iirup_info_ref (disposal_reference)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
                mysqli_query($conn, $sql);
            }
            // Backward-compat: if table exists but uses accountable_officer (double-c), keep both supported.
            if (table_exists($conn, 'iirup_info')) {
                if (!column_exists($conn, 'iirup_info', 'acccountable_officer') && column_exists($conn, 'iirup_info', 'accountable_officer')) {
                    // no-op; supported via runtime column selection
                } elseif (!column_exists($conn, 'iirup_info', 'acccountable_officer')) {
                    @mysqli_query($conn, "ALTER TABLE iirup_info ADD COLUMN acccountable_officer VARCHAR(255) NOT NULL DEFAULT '' AFTER disposal_reference");
                }
                $needCols = [
                    'designation' => "ALTER TABLE iirup_info ADD COLUMN designation VARCHAR(255) NOT NULL DEFAULT ''",
                    'station' => "ALTER TABLE iirup_info ADD COLUMN station VARCHAR(255) NOT NULL DEFAULT ''",
                    'disposal_chairperson' => "ALTER TABLE iirup_info ADD COLUMN disposal_chairperson VARCHAR(255) NOT NULL DEFAULT ''",
                    'local_chief_executive' => "ALTER TABLE iirup_info ADD COLUMN local_chief_executive VARCHAR(255) NOT NULL DEFAULT ''",
                ];
                foreach ($needCols as $col => $ddl) {
                    if (!column_exists($conn, 'iirup_info', $col)) {
                        @mysqli_query($conn, $ddl);
                    }
                }
            }

            // iirup_witness (single per ref)
            if (!table_exists($conn, 'iirup_witness')) {
                $sql = "CREATE TABLE IF NOT EXISTS iirup_witness (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    disposal_reference VARCHAR(20) NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    position VARCHAR(255) NOT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_iirup_witness_ref (disposal_reference)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
                mysqli_query($conn, $sql);
            }
            if (table_exists($conn, 'iirup_witness')) {
                $needCols = [
                    'name' => "ALTER TABLE iirup_witness ADD COLUMN name VARCHAR(255) NOT NULL DEFAULT ''",
                    'position' => "ALTER TABLE iirup_witness ADD COLUMN position VARCHAR(255) NOT NULL DEFAULT ''",
                ];
                foreach ($needCols as $col => $ddl) {
                    if (!column_exists($conn, 'iirup_witness', $col)) {
                        @mysqli_query($conn, $ddl);
                    }
                }
            }

            // iirup_inspector (up to 5 per ref)
            if (!table_exists($conn, 'iirup_inspector')) {
                $sql = "CREATE TABLE IF NOT EXISTS iirup_inspector (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    disposal_reference VARCHAR(20) NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    position VARCHAR(255) NOT NULL,
                    sort_order TINYINT UNSIGNED NOT NULL DEFAULT 1,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_iirup_inspector_ref (disposal_reference)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
                mysqli_query($conn, $sql);
            }
            if (table_exists($conn, 'iirup_inspector')) {
                // Ensure a usable reference column exists.
                $hasRef = column_exists($conn, 'iirup_inspector', 'disposal_reference') || column_exists($conn, 'iirup_inspector', 'iirup_reference') || column_exists($conn, 'iirup_inspector', 'reference_number');
                if (!$hasRef) {
                    @mysqli_query($conn, "ALTER TABLE iirup_inspector ADD COLUMN disposal_reference VARCHAR(20) NOT NULL DEFAULT ''");
                }
                // Support either (name, position) or legacy (inspector_name, inspector_position).
                $hasName = column_exists($conn, 'iirup_inspector', 'name') || column_exists($conn, 'iirup_inspector', 'inspector_name');
                $hasPos = column_exists($conn, 'iirup_inspector', 'position') || column_exists($conn, 'iirup_inspector', 'inspector_position');
                if (!$hasName) {
                    @mysqli_query($conn, "ALTER TABLE iirup_inspector ADD COLUMN name VARCHAR(255) NOT NULL DEFAULT ''");
                }
                if (!$hasPos) {
                    @mysqli_query($conn, "ALTER TABLE iirup_inspector ADD COLUMN position VARCHAR(255) NOT NULL DEFAULT ''");
                }
                if (!column_exists($conn, 'iirup_inspector', 'sort_order')) {
                    @mysqli_query($conn, "ALTER TABLE iirup_inspector ADD COLUMN sort_order TINYINT UNSIGNED NOT NULL DEFAULT 1");
                }
            }
            return true;
        }
    }

    ensure_iirup_info_tables($conn);

    $refSafe = mysqli_real_escape_string($conn, $ref);

    $data = [
        'exists' => false,
        'disposal_reference' => $ref,
        'accountable_officer' => '',
        'designation' => '',
        'station' => '',
        'disposal_chairperson' => '',
        'local_chief_executive' => '',
        'witness_name' => '',
        'witness_position' => '',
        'inspectors' => []
    ];

    // iirup_info columns can be either acccountable_officer or accountable_officer.
    $acctCol = column_exists($conn, 'iirup_info', 'acccountable_officer') ? 'acccountable_officer' : (column_exists($conn, 'iirup_info', 'accountable_officer') ? 'accountable_officer' : 'acccountable_officer');
    $infoPkCol = column_exists($conn, 'iirup_info', 'id') ? 'id' : null;
    $infoOrder = $infoPkCol ? " ORDER BY {$infoPkCol} DESC" : '';
    $qInfo = mysqli_query($conn, "SELECT {$acctCol} AS accountable_officer, designation, station, disposal_chairperson, local_chief_executive FROM iirup_info WHERE disposal_reference = '{$refSafe}'{$infoOrder} LIMIT 1");
    if ($qInfo && mysqli_num_rows($qInfo) === 1) {
        $r = mysqli_fetch_assoc($qInfo);
        $data['exists'] = true;
        $data['accountable_officer'] = (string)($r['accountable_officer'] ?? '');
        $data['designation'] = (string)($r['designation'] ?? '');
        $data['station'] = (string)($r['station'] ?? '');
        $data['disposal_chairperson'] = (string)($r['disposal_chairperson'] ?? '');
        $data['local_chief_executive'] = (string)($r['local_chief_executive'] ?? '');
    }

    $witPkCol = column_exists($conn, 'iirup_witness', 'id') ? 'id' : null;
    $witOrder = $witPkCol ? " ORDER BY {$witPkCol} DESC" : '';
    $qW = mysqli_query($conn, "SELECT name, position FROM iirup_witness WHERE disposal_reference = '{$refSafe}'{$witOrder} LIMIT 1");
    if ($qW && mysqli_num_rows($qW) === 1) {
        $w = mysqli_fetch_assoc($qW);
        $data['witness_name'] = (string)($w['name'] ?? '');
        $data['witness_position'] = (string)($w['position'] ?? '');
    }

    $inspRefCol = column_exists($conn, 'iirup_inspector', 'disposal_reference') ? 'disposal_reference' : (column_exists($conn, 'iirup_inspector', 'iirup_reference') ? 'iirup_reference' : (column_exists($conn, 'iirup_inspector', 'reference_number') ? 'reference_number' : 'disposal_reference'));
    $inspNameCol = column_exists($conn, 'iirup_inspector', 'name') ? 'name' : (column_exists($conn, 'iirup_inspector', 'inspector_name') ? 'inspector_name' : 'name');
    $inspPosCol = column_exists($conn, 'iirup_inspector', 'position') ? 'position' : (column_exists($conn, 'iirup_inspector', 'inspector_position') ? 'inspector_position' : 'position');
    $inspPkCol = column_exists($conn, 'iirup_inspector', 'id') ? 'id' : (column_exists($conn, 'iirup_inspector', 'iirup_ins_id') ? 'iirup_ins_id' : 'id');
    $qI = mysqli_query($conn, "SELECT {$inspNameCol} AS name, {$inspPosCol} AS position FROM iirup_inspector WHERE {$inspRefCol} = '{$refSafe}' ORDER BY sort_order ASC, {$inspPkCol} ASC LIMIT 5");
    if ($qI) {
        while ($i = mysqli_fetch_assoc($qI)) {
            $nm = trim((string)($i['name'] ?? ''));
            $pos = trim((string)($i['position'] ?? ''));
            if ($nm !== '' || $pos !== '') {
                $data['inspectors'][] = ['name' => $nm, 'position' => $pos];
            }
        }
    }

    // Fallback: if not yet migrated, show legacy officers as inspectors.
    if (count($data['inspectors']) === 0 && table_exists($conn, 'disposal_inspection_officers')) {
        $legacyPkCol = column_exists($conn, 'disposal_inspection_officers', 'id') ? 'id' : 'id';
        $qLegacy = mysqli_query($conn, "SELECT officer_name AS name, officer_position AS position FROM disposal_inspection_officers WHERE disposal_reference = '{$refSafe}' ORDER BY sort_order ASC, {$legacyPkCol} ASC LIMIT 5");
        if ($qLegacy) {
            while ($i = mysqli_fetch_assoc($qLegacy)) {
                $nm = trim((string)($i['name'] ?? ''));
                $pos = trim((string)($i['position'] ?? ''));
                if ($nm !== '' || $pos !== '') {
                    $data['inspectors'][] = ['name' => $nm, 'position' => $pos];
                }
            }
        }
    }

    echo json_encode(['status'=>200,'message'=>'OK','data'=>$data]);
    return false;
}

// IIRUP Info - Save (per disposal reference)
if (isset($_POST['iirup_info_save'])) {
    header('Content-Type: application/json');

    $ref = isset($_POST['disposal_reference']) ? trim((string)$_POST['disposal_reference']) : '';
    if ($ref === '') {
        echo json_encode(['status'=>422,'message'=>'Missing disposal reference.']);
        return false;
    }

    if (!function_exists('ensure_iirup_info_tables')) {
        // In case this endpoint is hit before iirup_info_get.
        function table_exists($conn, $tableName) {
            $t = mysqli_real_escape_string($conn, (string)$tableName);
            $q = mysqli_query($conn, "SHOW TABLES LIKE '{$t}'");
            return ($q && mysqli_num_rows($q) === 1);
        }
        function column_exists($conn, $tableName, $columnName) {
            $t = mysqli_real_escape_string($conn, (string)$tableName);
            $c = mysqli_real_escape_string($conn, (string)$columnName);
            $q = mysqli_query($conn, "SHOW COLUMNS FROM `{$t}` LIKE '{$c}'");
            return ($q && mysqli_num_rows($q) === 1);
        }
        function index_exists($conn, $tableName, $indexName) {
            $t = mysqli_real_escape_string($conn, (string)$tableName);
            $i = mysqli_real_escape_string($conn, (string)$indexName);
            $q = mysqli_query($conn, "SHOW INDEX FROM `{$t}` WHERE Key_name = '{$i}'");
            return ($q && mysqli_num_rows($q) >= 1);
        }
        function ensure_iirup_info_tables($conn) {
            if (!table_exists($conn, 'iirup_info')) {
                $sql = "CREATE TABLE IF NOT EXISTS iirup_info (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    disposal_reference VARCHAR(20) NOT NULL,
                    acccountable_officer VARCHAR(255) NOT NULL,
                    designation VARCHAR(255) NOT NULL,
                    station VARCHAR(255) NOT NULL,
                    disposal_chairperson VARCHAR(255) NOT NULL,
                    local_chief_executive VARCHAR(255) NOT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_iirup_info_ref (disposal_reference)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
                mysqli_query($conn, $sql);
            }
            if (table_exists($conn, 'iirup_info')) {
                if (!column_exists($conn, 'iirup_info', 'acccountable_officer') && column_exists($conn, 'iirup_info', 'accountable_officer')) {
                    // supported via runtime column selection
                } elseif (!column_exists($conn, 'iirup_info', 'acccountable_officer')) {
                    @mysqli_query($conn, "ALTER TABLE iirup_info ADD COLUMN acccountable_officer VARCHAR(255) NOT NULL DEFAULT '' AFTER disposal_reference");
                }
                $needCols = [
                    'designation' => "ALTER TABLE iirup_info ADD COLUMN designation VARCHAR(255) NOT NULL DEFAULT ''",
                    'station' => "ALTER TABLE iirup_info ADD COLUMN station VARCHAR(255) NOT NULL DEFAULT ''",
                    'disposal_chairperson' => "ALTER TABLE iirup_info ADD COLUMN disposal_chairperson VARCHAR(255) NOT NULL DEFAULT ''",
                    'local_chief_executive' => "ALTER TABLE iirup_info ADD COLUMN local_chief_executive VARCHAR(255) NOT NULL DEFAULT ''",
                ];
                foreach ($needCols as $col => $ddl) {
                    if (!column_exists($conn, 'iirup_info', $col)) {
                        @mysqli_query($conn, $ddl);
                    }
                }

                // Best-effort: ensure unique ref index exists (older installations may be missing it)
                if (!index_exists($conn, 'iirup_info', 'uq_iirup_info_ref')) {
                    @mysqli_query($conn, "ALTER TABLE iirup_info ADD UNIQUE KEY uq_iirup_info_ref (disposal_reference)");
                }
            }

            if (!table_exists($conn, 'iirup_witness')) {
                $sql = "CREATE TABLE IF NOT EXISTS iirup_witness (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    disposal_reference VARCHAR(20) NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    position VARCHAR(255) NOT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_iirup_witness_ref (disposal_reference)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
                mysqli_query($conn, $sql);
            }
            if (table_exists($conn, 'iirup_witness')) {
                $needCols = [
                    'name' => "ALTER TABLE iirup_witness ADD COLUMN name VARCHAR(255) NOT NULL DEFAULT ''",
                    'position' => "ALTER TABLE iirup_witness ADD COLUMN position VARCHAR(255) NOT NULL DEFAULT ''",
                ];
                foreach ($needCols as $col => $ddl) {
                    if (!column_exists($conn, 'iirup_witness', $col)) {
                        @mysqli_query($conn, $ddl);
                    }
                }

                // Best-effort: ensure unique ref index exists (older installations may be missing it)
                if (!index_exists($conn, 'iirup_witness', 'uq_iirup_witness_ref')) {
                    @mysqli_query($conn, "ALTER TABLE iirup_witness ADD UNIQUE KEY uq_iirup_witness_ref (disposal_reference)");
                }
            }

            if (!table_exists($conn, 'iirup_inspector')) {
                $sql = "CREATE TABLE IF NOT EXISTS iirup_inspector (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    disposal_reference VARCHAR(20) NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    position VARCHAR(255) NOT NULL,
                    sort_order TINYINT UNSIGNED NOT NULL DEFAULT 1,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_iirup_inspector_ref (disposal_reference)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
                mysqli_query($conn, $sql);
            }
            if (table_exists($conn, 'iirup_inspector')) {
                $hasName = column_exists($conn, 'iirup_inspector', 'name') || column_exists($conn, 'iirup_inspector', 'inspector_name');
                $hasPos = column_exists($conn, 'iirup_inspector', 'position') || column_exists($conn, 'iirup_inspector', 'inspector_position');
                if (!$hasName) {
                    @mysqli_query($conn, "ALTER TABLE iirup_inspector ADD COLUMN name VARCHAR(255) NOT NULL DEFAULT ''");
                }
                if (!$hasPos) {
                    @mysqli_query($conn, "ALTER TABLE iirup_inspector ADD COLUMN position VARCHAR(255) NOT NULL DEFAULT ''");
                }
                if (!column_exists($conn, 'iirup_inspector', 'sort_order')) {
                    @mysqli_query($conn, "ALTER TABLE iirup_inspector ADD COLUMN sort_order TINYINT UNSIGNED NOT NULL DEFAULT 1");
                }
            }
            return true;
        }
    }

    ensure_iirup_info_tables($conn);

    $fields = [
        'accountable_officer' => isset($_POST['accountable_officer']) ? trim((string)$_POST['accountable_officer']) : '',
        'designation' => isset($_POST['designation']) ? trim((string)$_POST['designation']) : '',
        'station' => isset($_POST['station']) ? trim((string)$_POST['station']) : '',
        'disposal_chairperson' => isset($_POST['disposal_chairperson']) ? trim((string)$_POST['disposal_chairperson']) : '',
        'local_chief_executive' => isset($_POST['local_chief_executive']) ? trim((string)$_POST['local_chief_executive']) : '',
        'witness_name' => isset($_POST['witness_name']) ? trim((string)$_POST['witness_name']) : '',
        'witness_position' => isset($_POST['witness_position']) ? trim((string)$_POST['witness_position']) : '',
    ];
    foreach ($fields as $k => $v) {
        if ($v === '') {
            echo json_encode(['status'=>422,'message'=>'Missing required field: ' . $k]);
            return false;
        }
    }

    $insJson = isset($_POST['inspectors_json']) ? (string)$_POST['inspectors_json'] : '[]';
    $inspectors = json_decode($insJson, true);
    if (!is_array($inspectors)) { $inspectors = []; }

    $clean = [];
    foreach ($inspectors as $o) {
        if (!is_array($o)) { continue; }
        $nm = trim((string)($o['name'] ?? ''));
        $pos = trim((string)($o['position'] ?? ''));
        if ($nm === '' && $pos === '') { continue; }
        $clean[] = ['name' => $nm, 'position' => $pos];
        if (count($clean) >= 5) { break; }
    }
    if (count($clean) < 1) {
        echo json_encode(['status'=>422,'message'=>'At least one inspector is required.']);
        return false;
    }
    foreach ($clean as $o) {
        if (trim((string)($o['name'] ?? '')) === '' || trim((string)($o['position'] ?? '')) === '') {
            echo json_encode(['status'=>422,'message'=>'Each inspector must have a name and position.']);
            return false;
        }
    }

    $acctCol = column_exists($conn, 'iirup_info', 'acccountable_officer') ? 'acccountable_officer' : (column_exists($conn, 'iirup_info', 'accountable_officer') ? 'accountable_officer' : 'acccountable_officer');
    $inspRefCol = column_exists($conn, 'iirup_inspector', 'disposal_reference') ? 'disposal_reference' : (column_exists($conn, 'iirup_inspector', 'iirup_reference') ? 'iirup_reference' : (column_exists($conn, 'iirup_inspector', 'reference_number') ? 'reference_number' : 'disposal_reference'));
    $inspNameCol = column_exists($conn, 'iirup_inspector', 'name') ? 'name' : (column_exists($conn, 'iirup_inspector', 'inspector_name') ? 'inspector_name' : 'name');
    $inspPosCol = column_exists($conn, 'iirup_inspector', 'position') ? 'position' : (column_exists($conn, 'iirup_inspector', 'inspector_position') ? 'inspector_position' : 'position');

    $refSafe = mysqli_real_escape_string($conn, $ref);

    mysqli_begin_transaction($conn);

    try {
        // iirup_info: update existing row (do not insert duplicates), else insert on first save.
        $infoPkCol = column_exists($conn, 'iirup_info', 'id') ? 'id' : null;
        $existingInfoId = null;
        if ($infoPkCol) {
            $qId = mysqli_query($conn, "SELECT {$infoPkCol} AS pk FROM iirup_info WHERE disposal_reference = '{$refSafe}' ORDER BY {$infoPkCol} DESC LIMIT 1");
            if ($qId && mysqli_num_rows($qId) === 1) {
                $row = mysqli_fetch_assoc($qId);
                $existingInfoId = (int)($row['pk'] ?? 0);
                if ($existingInfoId <= 0) { $existingInfoId = null; }
            }
        } else {
            $qAny = mysqli_query($conn, "SELECT 1 FROM iirup_info WHERE disposal_reference = '{$refSafe}' LIMIT 1");
            if ($qAny && mysqli_num_rows($qAny) === 1) { $existingInfoId = -1; }
        }

        if ($existingInfoId !== null) {
            if ($existingInfoId > 0 && $infoPkCol) {
                $sqlUpd = "UPDATE iirup_info SET `{$acctCol}` = ?, designation = ?, station = ?, disposal_chairperson = ?, local_chief_executive = ? WHERE {$infoPkCol} = ? LIMIT 1";
                $stmt = mysqli_prepare($conn, $sqlUpd);
                if (!$stmt) { throw new Exception('Prepare failed (iirup_info update): ' . mysqli_error($conn)); }
                mysqli_stmt_bind_param($stmt, 'sssssi', $fields['accountable_officer'], $fields['designation'], $fields['station'], $fields['disposal_chairperson'], $fields['local_chief_executive'], $existingInfoId);
                if (!mysqli_stmt_execute($stmt)) {
                    $err = mysqli_stmt_error($stmt);
                    mysqli_stmt_close($stmt);
                    throw new Exception('Execute failed (iirup_info update): ' . $err);
                }
                mysqli_stmt_close($stmt);

                // Clean up accidental duplicates for the same ref (keep latest id)
                mysqli_query($conn, "DELETE FROM iirup_info WHERE disposal_reference = '{$refSafe}' AND {$infoPkCol} <> {$existingInfoId}");
            } else {
                $sqlUpd = "UPDATE iirup_info SET `{$acctCol}` = ?, designation = ?, station = ?, disposal_chairperson = ?, local_chief_executive = ? WHERE disposal_reference = ?";
                $stmt = mysqli_prepare($conn, $sqlUpd);
                if (!$stmt) { throw new Exception('Prepare failed (iirup_info update-by-ref): ' . mysqli_error($conn)); }
                mysqli_stmt_bind_param($stmt, 'ssssss', $fields['accountable_officer'], $fields['designation'], $fields['station'], $fields['disposal_chairperson'], $fields['local_chief_executive'], $ref);
                if (!mysqli_stmt_execute($stmt)) {
                    $err = mysqli_stmt_error($stmt);
                    mysqli_stmt_close($stmt);
                    throw new Exception('Execute failed (iirup_info update-by-ref): ' . $err);
                }
                mysqli_stmt_close($stmt);
            }
        } else {
            $sqlIns = "INSERT INTO iirup_info (disposal_reference, `{$acctCol}`, designation, station, disposal_chairperson, local_chief_executive) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sqlIns);
            if (!$stmt) { throw new Exception('Prepare failed (iirup_info insert): ' . mysqli_error($conn)); }
            mysqli_stmt_bind_param($stmt, 'ssssss', $ref, $fields['accountable_officer'], $fields['designation'], $fields['station'], $fields['disposal_chairperson'], $fields['local_chief_executive']);
            if (!mysqli_stmt_execute($stmt)) {
                $err = mysqli_stmt_error($stmt);
                mysqli_stmt_close($stmt);
                throw new Exception('Execute failed (iirup_info insert): ' . $err);
            }
            mysqli_stmt_close($stmt);
        }

        // iirup_witness: update existing row (do not insert duplicates), else insert on first save.
        $witPkCol = column_exists($conn, 'iirup_witness', 'id') ? 'id' : null;
        $existingWitId = null;
        if ($witPkCol) {
            $qWid = mysqli_query($conn, "SELECT {$witPkCol} AS pk FROM iirup_witness WHERE disposal_reference = '{$refSafe}' ORDER BY {$witPkCol} DESC LIMIT 1");
            if ($qWid && mysqli_num_rows($qWid) === 1) {
                $row = mysqli_fetch_assoc($qWid);
                $existingWitId = (int)($row['pk'] ?? 0);
                if ($existingWitId <= 0) { $existingWitId = null; }
            }
        } else {
            $qAny = mysqli_query($conn, "SELECT 1 FROM iirup_witness WHERE disposal_reference = '{$refSafe}' LIMIT 1");
            if ($qAny && mysqli_num_rows($qAny) === 1) { $existingWitId = -1; }
        }

        if ($existingWitId !== null) {
            if ($existingWitId > 0 && $witPkCol) {
                $sqlUpdW = "UPDATE iirup_witness SET name = ?, position = ? WHERE {$witPkCol} = ? LIMIT 1";
                $stmtW = mysqli_prepare($conn, $sqlUpdW);
                if (!$stmtW) { throw new Exception('Prepare failed (iirup_witness update): ' . mysqli_error($conn)); }
                mysqli_stmt_bind_param($stmtW, 'ssi', $fields['witness_name'], $fields['witness_position'], $existingWitId);
                if (!mysqli_stmt_execute($stmtW)) {
                    $err = mysqli_stmt_error($stmtW);
                    mysqli_stmt_close($stmtW);
                    throw new Exception('Execute failed (iirup_witness update): ' . $err);
                }
                mysqli_stmt_close($stmtW);

                // Clean up accidental duplicates for the same ref (keep latest id)
                mysqli_query($conn, "DELETE FROM iirup_witness WHERE disposal_reference = '{$refSafe}' AND {$witPkCol} <> {$existingWitId}");
            } else {
                $sqlUpdW = "UPDATE iirup_witness SET name = ?, position = ? WHERE disposal_reference = ?";
                $stmtW = mysqli_prepare($conn, $sqlUpdW);
                if (!$stmtW) { throw new Exception('Prepare failed (iirup_witness update-by-ref): ' . mysqli_error($conn)); }
                mysqli_stmt_bind_param($stmtW, 'sss', $fields['witness_name'], $fields['witness_position'], $ref);
                if (!mysqli_stmt_execute($stmtW)) {
                    $err = mysqli_stmt_error($stmtW);
                    mysqli_stmt_close($stmtW);
                    throw new Exception('Execute failed (iirup_witness update-by-ref): ' . $err);
                }
                mysqli_stmt_close($stmtW);
            }
        } else {
            $sqlInsW = "INSERT INTO iirup_witness (disposal_reference, name, position) VALUES (?, ?, ?)";
            $stmtW = mysqli_prepare($conn, $sqlInsW);
            if (!$stmtW) { throw new Exception('Prepare failed (iirup_witness insert): ' . mysqli_error($conn)); }
            mysqli_stmt_bind_param($stmtW, 'sss', $ref, $fields['witness_name'], $fields['witness_position']);
            if (!mysqli_stmt_execute($stmtW)) {
                $err = mysqli_stmt_error($stmtW);
                mysqli_stmt_close($stmtW);
                throw new Exception('Execute failed (iirup_witness insert): ' . $err);
            }
            mysqli_stmt_close($stmtW);
        }

        // Replace inspectors
        mysqli_query($conn, "DELETE FROM iirup_inspector WHERE {$inspRefCol} = '{$refSafe}'");
        $stmtI = mysqli_prepare($conn, "INSERT INTO iirup_inspector ({$inspRefCol}, {$inspNameCol}, {$inspPosCol}, sort_order) VALUES (?, ?, ?, ?)");
        if (!$stmtI) { throw new Exception('Prepare failed (iirup_inspector).'); }
        for ($i = 0; $i < count($clean); $i++) {
            $order = $i + 1;
            $nm = (string)($clean[$i]['name'] ?? '');
            $pos = (string)($clean[$i]['position'] ?? '');
            mysqli_stmt_bind_param($stmtI, 'sssi', $ref, $nm, $pos, $order);
            if (!mysqli_stmt_execute($stmtI)) {
                $err = mysqli_stmt_error($stmtI);
                mysqli_stmt_close($stmtI);
                throw new Exception('Execute failed (iirup_inspector): ' . $err);
            }
        }
        mysqli_stmt_close($stmtI);

        mysqli_commit($conn);
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        echo json_encode(['status'=>500,'message'=>'Server error: ' . $e->getMessage()]);
        return false;
    }

    echo json_encode(['status'=>200,'message'=>'IIRUP information saved.']);
    return false;
}

// Disposal Activities - Details (IIRUP-style header/signatories)
if (isset($_POST['disposal_details_get'])) {
    header('Content-Type: application/json');

    $ref = isset($_POST['disposal_reference']) ? trim((string)$_POST['disposal_reference']) : '';
    if ($ref === '') {
        echo json_encode(['status'=>422,'message'=>'Missing disposal reference.']);
        return false;
    }

    $chk1 = mysqli_query($conn, "SHOW TABLES LIKE 'disposal_details'");
    if (!$chk1 || mysqli_num_rows($chk1) !== 1) {
        $sql1 = "CREATE TABLE IF NOT EXISTS disposal_details (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            disposal_reference VARCHAR(20) NOT NULL,
            accountable_officer_name VARCHAR(255) NOT NULL,
            designation VARCHAR(255) NOT NULL,
            station VARCHAR(255) NOT NULL,
            requested_by VARCHAR(255) NOT NULL,
            approved_by VARCHAR(255) NOT NULL,
            committee_chairperson VARCHAR(255) NOT NULL,
            witness VARCHAR(255) NOT NULL,
            witness_position VARCHAR(255) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_disp_details_ref (disposal_reference)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        mysqli_query($conn, $sql1);
    } else {
        // Backward-compat: add witness_position column if missing
        $colW = mysqli_query($conn, "SHOW COLUMNS FROM disposal_details LIKE 'witness_position'");
        if (!$colW || mysqli_num_rows($colW) !== 1) {
            @mysqli_query($conn, "ALTER TABLE disposal_details ADD COLUMN witness_position VARCHAR(255) NOT NULL DEFAULT '' AFTER witness");
        }
    }

    $chk2 = mysqli_query($conn, "SHOW TABLES LIKE 'disposal_inspection_officers'");
    if (!$chk2 || mysqli_num_rows($chk2) !== 1) {
        $sql2 = "CREATE TABLE IF NOT EXISTS disposal_inspection_officers (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            disposal_reference VARCHAR(20) NOT NULL,
            officer_name VARCHAR(255) NOT NULL,
            officer_position VARCHAR(255) NOT NULL DEFAULT '',
            sort_order TINYINT UNSIGNED NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_disp_officers_ref (disposal_reference)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        mysqli_query($conn, $sql2);
    } else {
        // Backward-compat: add officer_position column if missing
        $col = mysqli_query($conn, "SHOW COLUMNS FROM disposal_inspection_officers LIKE 'officer_position'");
        if (!$col || mysqli_num_rows($col) !== 1) {
            @mysqli_query($conn, "ALTER TABLE disposal_inspection_officers ADD COLUMN officer_position VARCHAR(255) NOT NULL DEFAULT '' AFTER officer_name");
        }
    }

    $refSafe = mysqli_real_escape_string($conn, $ref);

    $data = [
        'exists' => false,
        'disposal_reference' => $ref,
        'accountable_officer_name' => '',
        'designation' => '',
        'station' => '',
        'requested_by' => '',
        'approved_by' => '',
        'committee_chairperson' => '',
        'witness' => '',
        'witness_position' => '',
        'inspection_officers' => []
    ];

    $q = mysqli_query($conn, "SELECT accountable_officer_name, designation, station, requested_by, approved_by, committee_chairperson, witness, witness_position
                              FROM disposal_details WHERE disposal_reference = '{$refSafe}' LIMIT 1");
    if ($q && mysqli_num_rows($q) === 1) {
        $r = mysqli_fetch_assoc($q);
        $data['exists'] = true;
        $data['accountable_officer_name'] = (string)($r['accountable_officer_name'] ?? '');
        $data['designation'] = (string)($r['designation'] ?? '');
        $data['station'] = (string)($r['station'] ?? '');
        $data['requested_by'] = (string)($r['requested_by'] ?? '');
        $data['approved_by'] = (string)($r['approved_by'] ?? '');
        $data['committee_chairperson'] = (string)($r['committee_chairperson'] ?? '');
        $data['witness'] = (string)($r['witness'] ?? '');
        $data['witness_position'] = (string)($r['witness_position'] ?? '');
    }

    $qOff = mysqli_query($conn, "SELECT officer_name, officer_position FROM disposal_inspection_officers WHERE disposal_reference = '{$refSafe}' ORDER BY sort_order ASC, id ASC LIMIT 5");
    if ($qOff) {
        while ($o = mysqli_fetch_assoc($qOff)) {
            $nm = trim((string)($o['officer_name'] ?? ''));
            $pos = trim((string)($o['officer_position'] ?? ''));
            if ($nm !== '' || $pos !== '') {
                $data['inspection_officers'][] = ['name' => $nm, 'position' => $pos];
            }
        }
    }

    echo json_encode(['status'=>200,'message'=>'OK','data'=>$data]);
    return false;
}

if (isset($_POST['disposal_details_save'])) {
    header('Content-Type: application/json');

    $ref = isset($_POST['disposal_reference']) ? trim((string)$_POST['disposal_reference']) : '';
    if ($ref === '') {
        echo json_encode(['status'=>422,'message'=>'Missing disposal reference.']);
        return false;
    }

    $fields = [
        'accountable_officer_name' => isset($_POST['accountable_officer_name']) ? trim((string)$_POST['accountable_officer_name']) : '',
        'designation' => isset($_POST['designation']) ? trim((string)$_POST['designation']) : '',
        'station' => isset($_POST['station']) ? trim((string)$_POST['station']) : '',
        'approved_by' => isset($_POST['approved_by']) ? trim((string)$_POST['approved_by']) : '',
        'committee_chairperson' => isset($_POST['committee_chairperson']) ? trim((string)$_POST['committee_chairperson']) : '',
        'witness' => isset($_POST['witness']) ? trim((string)$_POST['witness']) : '',
        'witness_position' => isset($_POST['witness_position']) ? trim((string)$_POST['witness_position']) : '',
    ];

    foreach ($fields as $k => $v) {
        if ($v === '') {
            echo json_encode(['status'=>422,'message'=>'Missing required field: ' . $k]);
            return false;
        }
    }

    $offJson = isset($_POST['inspection_officers_json']) ? (string)$_POST['inspection_officers_json'] : '[]';
    $officers = json_decode($offJson, true);
    if (!is_array($officers)) { $officers = []; }
    $clean = [];
    foreach ($officers as $o) {
        // Backward-compat: allow string entries (name only)
        if (is_string($o)) {
            $nm = trim($o);
            if ($nm !== '') {
                $clean[] = ['name' => $nm, 'position' => ''];
            }
            if (count($clean) >= 5) { break; }
            continue;
        }
        if (!is_array($o)) { continue; }
        $nm = trim((string)($o['name'] ?? ''));
        $pos = trim((string)($o['position'] ?? ''));
        if ($nm === '' && $pos === '') { continue; }
        $clean[] = ['name' => $nm, 'position' => $pos];
        if (count($clean) >= 5) { break; }
    }
    if (count($clean) < 1) {
        echo json_encode(['status'=>422,'message'=>'At least one inspection officer is required.']);
        return false;
    }
    foreach ($clean as $o) {
        if (trim((string)($o['name'] ?? '')) === '' || trim((string)($o['position'] ?? '')) === '') {
            echo json_encode(['status'=>422,'message'=>'Each inspection officer must have a name and position.']);
            return false;
        }
    }

    // Ensure tables exist
    $chk1 = mysqli_query($conn, "SHOW TABLES LIKE 'disposal_details'");
    if (!$chk1 || mysqli_num_rows($chk1) !== 1) {
        $sql1 = "CREATE TABLE IF NOT EXISTS disposal_details (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            disposal_reference VARCHAR(20) NOT NULL,
            accountable_officer_name VARCHAR(255) NOT NULL,
            designation VARCHAR(255) NOT NULL,
            station VARCHAR(255) NOT NULL,
            requested_by VARCHAR(255) NOT NULL,
            approved_by VARCHAR(255) NOT NULL,
            committee_chairperson VARCHAR(255) NOT NULL,
            witness VARCHAR(255) NOT NULL,
            witness_position VARCHAR(255) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_disp_details_ref (disposal_reference)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        mysqli_query($conn, $sql1);
    } else {
        // Backward-compat: add witness_position column if missing
        $colW = mysqli_query($conn, "SHOW COLUMNS FROM disposal_details LIKE 'witness_position'");
        if (!$colW || mysqli_num_rows($colW) !== 1) {
            @mysqli_query($conn, "ALTER TABLE disposal_details ADD COLUMN witness_position VARCHAR(255) NOT NULL DEFAULT '' AFTER witness");
        }
    }
    $chk2 = mysqli_query($conn, "SHOW TABLES LIKE 'disposal_inspection_officers'");
    if (!$chk2 || mysqli_num_rows($chk2) !== 1) {
        $sql2 = "CREATE TABLE IF NOT EXISTS disposal_inspection_officers (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            disposal_reference VARCHAR(20) NOT NULL,
            officer_name VARCHAR(255) NOT NULL,
            officer_position VARCHAR(255) NOT NULL DEFAULT '',
            sort_order TINYINT UNSIGNED NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_disp_officers_ref (disposal_reference)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        mysqli_query($conn, $sql2);
    } else {
        // Backward-compat: add officer_position column if missing
        $col = mysqli_query($conn, "SHOW COLUMNS FROM disposal_inspection_officers LIKE 'officer_position'");
        if (!$col || mysqli_num_rows($col) !== 1) {
            @mysqli_query($conn, "ALTER TABLE disposal_inspection_officers ADD COLUMN officer_position VARCHAR(255) NOT NULL DEFAULT '' AFTER officer_name");
        }
    }

    $refSafe = mysqli_real_escape_string($conn, $ref);

    $stmt = mysqli_prepare(
        $conn,
                "INSERT INTO disposal_details (disposal_reference, accountable_officer_name, designation, station, requested_by, approved_by, committee_chairperson, witness, witness_position)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
           accountable_officer_name = VALUES(accountable_officer_name),
           designation = VALUES(designation),
           station = VALUES(station),
           requested_by = VALUES(requested_by),
           approved_by = VALUES(approved_by),
           committee_chairperson = VALUES(committee_chairperson),
                     witness = VALUES(witness),
                     witness_position = VALUES(witness_position)"
    );

    if (!$stmt) {
        echo json_encode(['status'=>500,'message'=>'Failed to prepare statement.']);
        return false;
    }

    $requestedBy = $fields['accountable_officer_name'];

    mysqli_stmt_bind_param(
        $stmt,
        'sssssssss',
        $ref,
        $fields['accountable_officer_name'],
        $fields['designation'],
        $fields['station'],
        $requestedBy,
        $fields['approved_by'],
        $fields['committee_chairperson'],
        $fields['witness'],
        $fields['witness_position']
    );

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        echo json_encode(['status'=>500,'message'=>'Failed to save disposal details.']);
        return false;
    }
    mysqli_stmt_close($stmt);

    // Replace inspection officers
    mysqli_query($conn, "DELETE FROM disposal_inspection_officers WHERE disposal_reference = '{$refSafe}'");
    $stmt2 = mysqli_prepare($conn, "INSERT INTO disposal_inspection_officers (disposal_reference, officer_name, officer_position, sort_order) VALUES (?, ?, ?, ?)");
    if ($stmt2) {
        for ($i = 0; $i < count($clean); $i++) {
            $order = $i + 1;
            $nm = (string)($clean[$i]['name'] ?? '');
            $pos = (string)($clean[$i]['position'] ?? '');
            mysqli_stmt_bind_param($stmt2, 'sssi', $ref, $nm, $pos, $order);
            mysqli_stmt_execute($stmt2);
        }
        mysqli_stmt_close($stmt2);
    }

    echo json_encode(['status'=>200,'message'=>'Disposal details saved.']);
    return false;
}

// Disposal Activities - Upload documents
if (isset($_POST['disposal_upload_documents'])) {
    header('Content-Type: application/json');

    $ref = isset($_POST['disposal_reference']) ? trim((string)$_POST['disposal_reference']) : '';
    if ($ref === '') {
        echo json_encode(['status'=>422,'message'=>'Missing disposal reference.']);
        return false;
    }
    if (!preg_match('/^\d{6}-D\d{3}$/', $ref)) {
        echo json_encode(['status'=>422,'message'=>'Invalid disposal reference.']);
        return false;
    }

    // Expect multiple files in docs[]
    if (!isset($_FILES['docs'])) {
        echo json_encode(['status'=>422,'message'=>'No files uploaded.']);
        return false;
    }

    // Create documents table if needed
    $chk = mysqli_query($conn, "SHOW TABLES LIKE 'disposal_documents'");
    if (!$chk || mysqli_num_rows($chk) !== 1) {
        $create = "CREATE TABLE IF NOT EXISTS disposal_documents (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            disposal_reference VARCHAR(20) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            stored_name VARCHAR(255) NOT NULL,
            mime_type VARCHAR(120) NOT NULL,
            file_size INT UNSIGNED NOT NULL DEFAULT 0,
            uploaded_by VARCHAR(64) NOT NULL DEFAULT '',
            uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_disposal_ref (disposal_reference)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        mysqli_query($conn, $create);
    }

    $baseDir = realpath(__DIR__ . '/..');
    if (!$baseDir) {
        echo json_encode(['status'=>500,'message'=>'Upload path error.']);
        return false;
    }
    $targetDir = $baseDir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'disposal' . DIRECTORY_SEPARATOR . $ref;
    if (!is_dir($targetDir)) {
        @mkdir($targetDir, 0775, true);
    }
    if (!is_dir($targetDir) || !is_writable($targetDir)) {
        echo json_encode(['status'=>500,'message'=>'Upload directory not writable.']);
        return false;
    }

    $allowedExt = ['pdf'];
    $saved = 0;
    $errors = [];

    $names = $_FILES['docs']['name'] ?? [];
    $tmp = $_FILES['docs']['tmp_name'] ?? [];
    $sizes = $_FILES['docs']['size'] ?? [];
    $types = $_FILES['docs']['type'] ?? [];
    $errs = $_FILES['docs']['error'] ?? [];

    if (!is_array($names)) {
        $names = [$names];
        $tmp = [$tmp];
        $sizes = [$sizes];
        $types = [$types];
        $errs = [$errs];
    }

    for ($i = 0; $i < count($names); $i++) {
        $orig = (string)($names[$i] ?? '');
        $t = (string)($tmp[$i] ?? '');
        $sz = (int)($sizes[$i] ?? 0);
        $mt = (string)($types[$i] ?? 'application/octet-stream');
        $er = (int)($errs[$i] ?? 0);

        if ($er !== UPLOAD_ERR_OK) {
            $errors[] = $orig !== '' ? ($orig . ': upload error') : 'Upload error';
            continue;
        }
        if ($orig === '' || $t === '') {
            $errors[] = 'Invalid file.';
            continue;
        }
        if ($sz <= 0) {
            $errors[] = $orig . ': empty file.';
            continue;
        }
        if ($sz > (25 * 1024 * 1024)) {
            $errors[] = $orig . ': too large (max 25MB).';
            continue;
        }

        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            $errors[] = $orig . ': invalid file type (PDF only).';
            continue;
        }

        $stored = date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $dest = $targetDir . DIRECTORY_SEPARATOR . $stored;
        if (!@move_uploaded_file($t, $dest)) {
            $errors[] = $orig . ': failed to save.';
            continue;
        }

        $refSafe = mysqli_real_escape_string($conn, $ref);
        $origSafe = mysqli_real_escape_string($conn, $orig);
        $storedSafe = mysqli_real_escape_string($conn, $stored);
        $mtSafe = mysqli_real_escape_string($conn, $mt);
        $by = isset($_SESSION['alogin']) ? (string)$_SESSION['alogin'] : '';
        $bySafe = mysqli_real_escape_string($conn, $by);

        mysqli_query(
            $conn,
            "INSERT INTO disposal_documents (disposal_reference, original_name, stored_name, mime_type, file_size, uploaded_by)
             VALUES ('{$refSafe}', '{$origSafe}', '{$storedSafe}', '{$mtSafe}', {$sz}, '{$bySafe}')"
        );
        $saved++;
    }

    echo json_encode([
        'status' => 200,
        'message' => $saved > 0 ? ('Uploaded ' . $saved . ' file(s).') : 'No files uploaded.',
        'data' => ['saved' => $saved, 'errors' => $errors]
    ]);
    return false;
}

// Disposal Activities - Close (finish)
if (isset($_POST['disposal_close'])) {
    header('Content-Type: application/json');

    $ref = isset($_POST['disposal_reference']) ? trim((string)$_POST['disposal_reference']) : '';
    if ($ref === '') {
        echo json_encode(['status'=>422,'message'=>'Missing disposal reference.']);
        return false;
    }

    if (!function_exists('ensure_disposal_table')) {
        function ensure_disposal_table($conn) {
            $chk = mysqli_query($conn, "SHOW TABLES LIKE 'disposal'");
            if ($chk && mysqli_num_rows($chk) === 1) { return true; }
            return false;
        }
    }
    if (!ensure_disposal_table($conn)) {
        echo json_encode(['status'=>500,'message'=>'Disposal table not found.']);
        return false;
    }

    $refSafe = mysqli_real_escape_string($conn, $ref);
    $ok = mysqli_query($conn, "UPDATE disposal SET status = 1 WHERE disposal_reference = '{$refSafe}' AND status = 0 LIMIT 1");
    if (!$ok) {
        echo json_encode(['status'=>500,'message'=>'Failed to close disposal activity.']);
        return false;
    }
    echo json_encode(['status'=>200,'message'=>'Disposal activity closed.']);
    return false;
}

// Disposal - DataTables: account-code summary (FOR DISPOSAL only)
if (isset($_POST['disposal_account_codes_dt'])) {
    header('Content-Type: application/json');

    $ref = isset($_POST['disposal_reference']) ? trim((string)$_POST['disposal_reference']) : '';
    if ($ref === '') {
        echo json_encode(['draw'=>0,'recordsTotal'=>0,'recordsFiltered'=>0,'data'=>[]]);
        return false;
    }
    $draw = isset($_POST['draw']) ? (int)$_POST['draw'] : 0;
    $start = isset($_POST['start']) ? max(0, (int)$_POST['start']) : 0;
    $length = isset($_POST['length']) ? (int)$_POST['length'] : 10;
    if ($length <= 0 || $length > 200) { $length = 10; }

    $search = '';
    if (isset($_POST['search']) && is_array($_POST['search']) && isset($_POST['search']['value'])) {
        $search = trim((string)$_POST['search']['value']);
    }

    $orderCol = isset($_POST['order'][0]['column']) ? (int)$_POST['order'][0]['column'] : 0;
    $orderDir = (isset($_POST['order'][0]['dir']) && strtolower((string)$_POST['order'][0]['dir']) === 'desc') ? 'DESC' : 'ASC';

    $colMap = [0=>'account_code',1=>'account_name',2=>'item_count',3=>'total_appraise_value'];
    $orderBy = isset($colMap[$orderCol]) ? $colMap[$orderCol] : 'account_code';

    $codeExpr = "COALESCE(NULLIF(TRIM(u.account_code), ''), '(NO ACCOUNT CODE)')";
    $latestHistoryJoin = "
      LEFT JOIN (
        SELECT h1.*
        FROM unserviceable_items_history AS h1
        INNER JOIN (
          SELECT par_number, MAX(created_at) AS max_created_at
          FROM unserviceable_items_history
          GROUP BY par_number
        ) AS h2
          ON h1.par_number = h2.par_number
         AND h1.created_at = h2.max_created_at
            ) AS h ON h.par_number = i.par_number
    ";

    $baseTypes = 's';
    $baseParams = [$ref];
    $row = gso_query_one($conn, "
        SELECT COUNT(DISTINCT {$codeExpr}) AS cnt
        FROM iirup_report_items AS i
        LEFT JOIN unserviceable_items AS u ON u.par_number = i.par_number
        {$latestHistoryJoin}
        WHERE COALESCE(h.status, 0) = 1
          AND COALESCE(i.disposal_reference,'') = ?
    ", $baseTypes, $baseParams);
    $totalRecords = (int)($row['cnt'] ?? 0);

    $whereSql = " WHERE COALESCE(h.status, 0) = 1 AND COALESCE(i.disposal_reference,'') = ? ";
    $whereTypes = $baseTypes;
    $whereParams = $baseParams;
    if ($search !== '') {
        $whereSql .= " AND ({$codeExpr} LIKE ? OR COALESCE(ac.account_name, '') LIKE ?) ";
        $likeSearch = '%' . $search . '%';
        $whereTypes .= 'ss';
        $whereParams[] = $likeSearch;
        $whereParams[] = $likeSearch;
    }

    $filteredRecords = $totalRecords;
    if ($search !== '') {
        $rf = gso_query_one($conn, "
            SELECT COUNT(*) AS cnt FROM (
                SELECT {$codeExpr} AS code_key
                FROM iirup_report_items AS i
                LEFT JOIN unserviceable_items AS u ON u.par_number = i.par_number
                {$latestHistoryJoin}
                LEFT JOIN account_code AS ac ON ac.account_code = u.account_code
                {$whereSql}
                GROUP BY code_key
            ) AS x
        ", $whereTypes, $whereParams);
        $filteredRecords = (int)($rf['cnt'] ?? 0);
    }

    $sql = "
        SELECT
            {$codeExpr} AS account_code,
            COALESCE(ac.account_name, '') AS account_name,
            COUNT(*) AS item_count,
            COALESCE(SUM(
                COALESCE(i.total_appraise_value, (COALESCE(i.qty, 1) * COALESCE(i.appraise_value, 0)))
            ), 0) AS total_appraise_value
        FROM iirup_report_items AS i
        LEFT JOIN unserviceable_items AS u ON u.par_number = i.par_number
        {$latestHistoryJoin}
        LEFT JOIN account_code AS ac ON ac.account_code = u.account_code
        {$whereSql}
        GROUP BY account_code, account_name
        ORDER BY {$orderBy} {$orderDir}
        LIMIT ?, ?
    ";

    $data = [];
    list($stmt, $rows) = gso_query_all($conn, $sql, $whereTypes . 'ii', array_merge($whereParams, [$start, $length]));
    if ($stmt instanceof mysqli_stmt) { $stmt->close(); }
    foreach ($rows as $row) {
        $code = (string)($row['account_code'] ?? '');
        $data[] = [
            'account_code' => $code,
            'account_name' => (string)($row['account_name'] ?? ''),
            'item_count' => (int)($row['item_count'] ?? 0),
            'total_appraise_value' => (float)($row['total_appraise_value'] ?? 0)
        ];
    }

    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => $totalRecords,
        'recordsFiltered' => $filteredRecords,
        'data' => $data
    ]);
    return false;
}

// Disposal - DataTables: items by account code (FOR DISPOSAL only)
if (isset($_POST['disposal_items_by_account_code_dt'])) {
    header('Content-Type: application/json');

    $code = isset($_POST['code']) ? trim((string)$_POST['code']) : '';
    $ref = isset($_POST['disposal_reference']) ? trim((string)$_POST['disposal_reference']) : '';
    if ($code === '') {
        echo json_encode(['draw'=>0,'recordsTotal'=>0,'recordsFiltered'=>0,'data'=>[]]);
        return false;
    }
    if ($ref === '') {
        echo json_encode(['draw'=>0,'recordsTotal'=>0,'recordsFiltered'=>0,'data'=>[]]);
        return false;
    }

    $draw = isset($_POST['draw']) ? (int)$_POST['draw'] : 0;
    $start = isset($_POST['start']) ? max(0, (int)$_POST['start']) : 0;
    $length = isset($_POST['length']) ? (int)$_POST['length'] : 10;
    if ($length <= 0 || $length > 200) { $length = 10; }

    $search = '';
    if (isset($_POST['search']) && is_array($_POST['search']) && isset($_POST['search']['value'])) {
        $search = trim((string)$_POST['search']['value']);
    }

    $orderCol = isset($_POST['order'][0]['column']) ? (int)$_POST['order'][0]['column'] : 4;
    $orderDir = (isset($_POST['order'][0]['dir']) && strtolower((string)$_POST['order'][0]['dir']) === 'asc') ? 'ASC' : 'DESC';
    // Column order (matches DataTable): FUND, CAT, QTY, PARTICULAR, PROPERTY NUMBER, UNIT COST, APPRAISAL VALUE, TOTAL
    $colMap = [0=>'fund',1=>'category',2=>'qty',3=>'particular',4=>'par_number',5=>'unit_cost',6=>'appraise_value',7=>'total_appraise_value'];
    $orderBy = isset($colMap[$orderCol]) ? $colMap[$orderCol] : 'par_number';

    $codeExpr = "COALESCE(NULLIF(TRIM(u.account_code), ''), '(NO ACCOUNT CODE)')";
    $where = " WHERE {$codeExpr} = ? AND COALESCE(h.status, 0) = 1 AND COALESCE(i.disposal_reference,'') = ? ";
    $baseTypes = 'ss';
    $baseParams = [$code, $ref];
    $whereSearch = '';
    $searchTypes = '';
    $searchParams = [];
    if ($search !== '') {
        $whereSearch = " AND (COALESCE(i.fund,'') LIKE ? OR COALESCE(u.category,'') LIKE ? OR i.par_number LIKE ? OR COALESCE(i.particulars,'') LIKE ?) ";
        $likeSearch = '%' . $search . '%';
        $searchTypes = 'ssss';
        $searchParams = [$likeSearch, $likeSearch, $likeSearch, $likeSearch];
    }

    $rt = gso_query_one($conn, "
        SELECT COUNT(*) AS cnt
                FROM iirup_report_items AS i
                LEFT JOIN unserviceable_items AS u ON u.par_number = i.par_number
        LEFT JOIN (
          SELECT h1.*
          FROM unserviceable_items_history AS h1
          INNER JOIN (
            SELECT par_number, MAX(created_at) AS max_created_at
            FROM unserviceable_items_history
            GROUP BY par_number
          ) AS h2
            ON h1.par_number = h2.par_number
           AND h1.created_at = h2.max_created_at
        ) AS h ON h.par_number = i.par_number
        {$where}
    ", $baseTypes, $baseParams);
    $totalRecords = (int)($rt['cnt'] ?? 0);

    $filteredRecords = $totalRecords;
    if ($whereSearch !== '') {
        $rf = gso_query_one($conn, "
            SELECT COUNT(*) AS cnt
                        FROM iirup_report_items AS i
                        LEFT JOIN unserviceable_items AS u ON u.par_number = i.par_number
            LEFT JOIN (
              SELECT h1.*
              FROM unserviceable_items_history AS h1
              INNER JOIN (
                SELECT par_number, MAX(created_at) AS max_created_at
                FROM unserviceable_items_history
                GROUP BY par_number
              ) AS h2
                ON h1.par_number = h2.par_number
               AND h1.created_at = h2.max_created_at
            ) AS h ON h.par_number = i.par_number
            {$where}
            {$whereSearch}
        ", $baseTypes . $searchTypes, array_merge($baseParams, $searchParams));
        $filteredRecords = (int)($rf['cnt'] ?? 0);
    }

    $sql = "
        SELECT
                    i.iirup_id,
                                        COALESCE(i.fund, '') AS fund,
                                        COALESCE(u.category, '') AS category,
                                        COALESCE(i.qty, 1) AS qty,
                    i.par_number,
                    COALESCE(i.particulars, '') AS particular,
                    COALESCE(i.unit_cost, 0) AS unit_cost,
                                        COALESCE(i.appraise_value, 0) AS appraise_value,
                                        (COALESCE(i.qty, 1) * COALESCE(i.appraise_value, 0)) AS total_appraise_value
                FROM iirup_report_items AS i
                LEFT JOIN unserviceable_items AS u ON u.par_number = i.par_number
        LEFT JOIN (
          SELECT h1.*
          FROM unserviceable_items_history AS h1
          INNER JOIN (
            SELECT par_number, MAX(created_at) AS max_created_at
            FROM unserviceable_items_history
            GROUP BY par_number
          ) AS h2
            ON h1.par_number = h2.par_number
           AND h1.created_at = h2.max_created_at
        ) AS h ON h.par_number = i.par_number
        {$where}
        {$whereSearch}
        ORDER BY {$orderBy} {$orderDir}
        LIMIT ?, ?
    ";

    $data = [];
    list($stmt, $rows) = gso_query_all($conn, $sql, $baseTypes . $searchTypes . 'ii', array_merge($baseParams, $searchParams, [$start, $length]));
    if ($stmt instanceof mysqli_stmt) { $stmt->close(); }
    foreach ($rows as $r) {
        $data[] = [
            'iirup_id' => (int)($r['iirup_id'] ?? 0),
            'fund' => (string)($r['fund'] ?? ''),
            'category' => (string)($r['category'] ?? ''),
            'qty' => (int)($r['qty'] ?? 1),
            'particular' => (string)($r['particular'] ?? ''),
            'par_number' => (string)($r['par_number'] ?? ''),
            'unit_cost' => (float)($r['unit_cost'] ?? 0),
            'appraise_value' => (float)($r['appraise_value'] ?? 0),
            'total_appraise_value' => (float)($r['total_appraise_value'] ?? 0)
        ];
    }

    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => $totalRecords,
        'recordsFiltered' => $filteredRecords,
        'data' => $data
    ]);
    return false;
}

// IIRUP - Update appraisal value (AJAX)
if (isset($_POST['iirup_update_appraise_value'])) {
    header('Content-Type: application/json');

    $id = isset($_POST['iirup_id']) ? (int)$_POST['iirup_id'] : 0;
    $rawVal = isset($_POST['appraise_value']) ? (string)$_POST['appraise_value'] : '';
    $val = (float)$rawVal;
    if ($id <= 0) {
        echo json_encode(['status'=>400,'message'=>'Missing iirup_id.']);
        return false;
    }
    if (!is_finite($val) || $val < 0) {
        $val = 0;
    }

    $stmt = mysqli_prepare($conn, "UPDATE iirup_report_items SET appraise_value = ?, total_appraise_value = (? * COALESCE(qty, 1)) WHERE iirup_id = ? LIMIT 1");
    if (!$stmt) {
        echo json_encode(['status'=>500,'message'=>'Database error: ' . mysqli_error($conn)]);
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'ddi', $val, $val, $id);
    $ok = mysqli_stmt_execute($stmt);
    $err = mysqli_stmt_error($stmt);
    mysqli_stmt_close($stmt);

    if (!$ok) {
        echo json_encode(['status'=>500,'message'=>'Database error: ' . $err]);
        return false;
    }
    echo json_encode(['status'=>200,'message'=>'Appraisal value saved.']);
    return false;
}
// AJAX validation for new employee name
if (isset($_POST['validate_employee_name'])) {
    header('Content-Type: application/json');
    $raw = isset($_POST['emp_name']) ? $_POST['emp_name'] : '';
    $trimmed = strtoupper(trim($raw));
    if ($trimmed === '' || strlen($trimmed) > 128) { echo json_encode(['exists'=>false]); return false; }
    // Session-cache common validations within 2 minutes
    if (!isset($_SESSION['cache'])) { $_SESSION['cache'] = []; }
    $ckey = 'emp_exists_' . md5($trimmed);
    if (isset($_SESSION['cache'][$ckey]) && (time() - $_SESSION['cache'][$ckey]['ts'] < 120)) {
        echo json_encode(['exists'=>$_SESSION['cache'][$ckey]['val']]);
        return false;
    }
    $emp_name = mysqli_real_escape_string($conn, $trimmed);
    $sql = "SELECT 1 FROM employee WHERE UPPER(emp_name) = '$emp_name' LIMIT 1";
    $query = mysqli_query($conn, $sql);
    $exists = ($query && mysqli_num_rows($query) > 0);
    $_SESSION['cache'][$ckey] = ['val'=>$exists, 'ts'=>time()];
    echo json_encode(['exists' => $exists]);
    return false;
}

// Generate a PAR/ICS number on demand (used by Add Item modal when 'auto generate' is checked)
if (isset($_POST['generate_par_ics_code'])) {
    header('Content-Type: application/json');
    $category = isset($_POST['category']) ? trim($_POST['category']) : '';
    $condition = strtoupper(trim((string)($_POST['condition'] ?? '')));
    if ($category !== 'PAR' && $category !== 'ICS') {
        echo json_encode(['status'=>400,'error'=>'Invalid or missing category.']);
        return false;
    }
    try {
        // Per requirement: for condition=NEW, PAR/ICS numbering must be based on new_purchase only.
        if ($condition === 'NEW') {
            $ym = date('Ym');
            $letter = ($category === 'ICS') ? 'I' : 'P';
            $prefix = $ym . '-' . $letter;
            $prefEsc = mysqli_real_escape_string($conn, $prefix);
            $max = 0;
            $sql = "SELECT MAX(CAST(SUBSTRING(par_ics_number, LENGTH('$prefEsc') + 1) AS UNSIGNED)) AS max_sfx FROM new_purchase WHERE par_ics_number LIKE CONCAT('$prefEsc','%')";
            $res = mysqli_query($conn, $sql);
            if ($res && mysqli_num_rows($res) === 1) {
                $row = mysqli_fetch_assoc($res);
                if ($row && $row['max_sfx'] !== null) { $max = (int)$row['max_sfx']; }
            }
            $code = $prefix . sprintf('%04d', ($max + 1));
        } else {
            $code = generateParIcsNumber($conn, $category);
        }
        echo json_encode(['status'=>200,'code'=>$code]);
    } catch (Throwable $e) {
        echo json_encode(['status'=>500,'error'=>'Failed to generate PAR/ICS No.']);
    }
    return false;
}

// Validate uniqueness of a PAR/ICS number (manual entry validation)
if (isset($_POST['validate_par_ics_unique'])) {
    header('Content-Type: application/json');
    $val = isset($_POST['par_ics_no']) ? strtoupper(trim($_POST['par_ics_no'])) : '';
    $condition = strtoupper(trim((string)($_POST['condition'] ?? '')));
    $po = strtoupper(trim((string)($_POST['purchase_order'] ?? '')));
    if ($val === '') {
        echo json_encode(['status'=>400,'error'=>'Missing PAR/ICS No.']);
        return false;
    }
    $safe = mysqli_real_escape_string($conn, $val);
    $poSafe = mysqli_real_escape_string($conn, $po);

    $exists = false;
    $poMatch = false;

    if ($condition === 'NEW') {
        $res = mysqli_query($conn, "SELECT purchase_order FROM new_purchase WHERE par_ics_number='{$safe}' LIMIT 1");
        if ($res && mysqli_num_rows($res) > 0) {
            $exists = true;
            $row = mysqli_fetch_assoc($res);
            $poMatch = ($po !== '' && strtoupper(trim($row['purchase_order'])) === $po);
        }
    } else {
        $tables = [
            ['table' => 'par_gen_fund', 'po' => 'purchase_order'],
            ['table' => 'property_sef', 'po' => 'purchase_order'],
            ['table' => 'trust_fund', 'po' => 'purchase_order'],
            ['table' => 'donation', 'po' => 'purchase_order'],
        ];
        foreach ($tables as $tbl) {
            $res = mysqli_query($conn, "SELECT {$tbl['po']} AS purchase_order FROM {$tbl['table']} WHERE par_ics_number='{$safe}' LIMIT 1");
            if ($res && mysqli_num_rows($res) > 0) {
                $exists = true;
                $row = mysqli_fetch_assoc($res);
                $poMatch = ($po !== '' && strtoupper(trim($row['purchase_order'])) === $po);
                break;
            }
        }
    }
    echo json_encode(['status'=>200, 'exists'=>$exists, 'po_match'=>$poMatch]);
    return false;
}

if (!function_exists('gso_infrastructure_catalog')) {
    function gso_infrastructure_catalog() {
        return [
            'OTHER STRUCTURE' => '1-07-04-990',
            'MARKET' => '1-07-04-040',
            'HOSPITALS & HEALTH CENTERS' => '1-07-04-030',
            'SCHOOL BUILDING' => '1-07-04-020',
            'OTHER BUILDINGS' => '1-07-04-010',
            'OTHER INFRASTRUCTURE ASSETS' => '1-07-03-990',
            'PARKS, PLAZAS, MONUMENTS' => '1-07-03-090',
            'POWER SUPPLY SYSTEM' => '1-07-04-990',
            'SEWER SYSTEMS' => '1-07-03-990',
            'FLOOD CONTROL SYSTEM' => '1-07-03-020',
            'ROAD NETWORKS' => '1-07-03-010',
            'OTHER LAND IMPROVEMENTS' => '1-07-02-990',
        ];
    }
}

if (!function_exists('gso_infrastructure_tables')) {
    function gso_infrastructure_tables($fund) {
        $normalized = strtoupper(trim((string)$fund));
        if ($normalized === 'GENERAL FUND' || $normalized === 'GF') {
            return [
                'main' => 'general_fund_infrastructure',
                'history' => 'general_fund_infrastructure_history',
                'prefix' => 'GFI',
            ];
        }
        if ($normalized === 'SPECIAL EDUCATION FUND' || $normalized === 'SEF') {
            return [
                'main' => 'sef_infrastructure',
                'history' => 'sef_infrastructure_history',
                'prefix' => 'SEFI',
            ];
        }
        return null;
    }
}

if (!function_exists('gso_generate_infrastructure_number')) {
    function gso_generate_infrastructure_number(mysqli $conn, $tableName, $prefix) {
        $prefix = strtoupper(trim((string)$prefix)) . '-' . date('Y') . '-';
        $sql = "SELECT infra_no FROM {$tableName} WHERE infra_no LIKE CONCAT(?, '%') ORDER BY infra_id DESC LIMIT 1";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Unable to prepare infrastructure number query.');
        }

        $stmt->bind_param('s', $prefix);
        $stmt->execute();
        $stmt->bind_result($lastInfraNo);
        $stmt->fetch();
        $stmt->close();

        $next = 1;
        if (!empty($lastInfraNo) && strpos((string)$lastInfraNo, $prefix) === 0) {
            $suffix = substr((string)$lastInfraNo, strlen($prefix));
            if (ctype_digit((string)$suffix)) {
                $next = ((int)$suffix) + 1;
            }
        }

        return $prefix . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
    }
}

if (isset($_POST['fetch_infrastructure_dt'])) {
    header('Content-Type: application/json; charset=utf-8');

    if (!isset($_SESSION['alogin']) || trim((string)$_SESSION['alogin']) === '') {
        return json_response(401, 'Unauthorized.');
    }

    $rows = [];
    $sql = "
        SELECT account_code, description
        FROM (
            SELECT account_code, description, created_at, infra_id FROM general_fund_infrastructure
            UNION ALL
            SELECT account_code, description, created_at, infra_id FROM sef_infrastructure
        ) AS infra
        ORDER BY account_code ASC, created_at DESC, infra_id DESC
    ";

    $query = mysqli_query($conn, $sql);
    if (!$query) {
        return json_response(500, 'Failed to fetch infrastructure records.', ['error' => mysqli_error($conn)]);
    }

    while ($row = mysqli_fetch_assoc($query)) {
        $rows[] = [
            'account_code' => (string)($row['account_code'] ?? ''),
            'description' => (string)($row['description'] ?? ''),
        ];
    }

    return json_response(200, 'OK', $rows);
}

if (isset($_POST['save_infrastructure'])) {
    header('Content-Type: application/json; charset=utf-8');

    if (!isset($_SESSION['alogin']) || trim((string)$_SESSION['alogin']) === '') {
        return json_response(401, 'Unauthorized.');
    }

    $fundCluster = trim((string)($_POST['fund_cluster'] ?? ''));
    $departmentCode = max(0, (int)($_POST['department_code'] ?? 0));
    $classification = strtoupper(trim((string)($_POST['classification'] ?? '')));
    $description = strtoupper(trim((string)($_POST['description'] ?? '')));
    $locationName = strtoupper(trim((string)($_POST['location_name'] ?? '')));
    $barangay = strtoupper(trim((string)($_POST['barangay'] ?? '')));
    $dateAcquired = trim((string)($_POST['date_acquired'] ?? ''));
    $yearAcquiredRaw = trim((string)($_POST['year_acquired'] ?? ''));
    $amountRaw = str_replace(',', '', trim((string)($_POST['amount'] ?? '0')));
    $conditionStatus = strtoupper(trim((string)($_POST['condition_status'] ?? 'SERVICEABLE')));
    $remarks = strtoupper(trim((string)($_POST['remarks'] ?? '')));

    $missing = [];
    if ($fundCluster === '') { $missing[] = 'fund'; }
    if ($classification === '') { $missing[] = 'classification'; }
    if ($description === '') { $missing[] = 'description'; }
    if ($locationName === '') { $missing[] = 'location'; }
    if ($yearAcquiredRaw === '') { $missing[] = 'year acquired'; }
    if ($amountRaw === '') { $missing[] = 'amount'; }

    if (!empty($missing)) {
        return json_response(422, 'Please complete: ' . implode(', ', $missing) . '.');
    }

    $tables = gso_infrastructure_tables($fundCluster);
    if (!$tables) {
        return json_response(422, 'Invalid fund selected.');
    }

    $catalog = gso_infrastructure_catalog();
    if (!isset($catalog[$classification])) {
        return json_response(422, 'Invalid classification selected.');
    }

    $accountCode = (string)$catalog[$classification];
    $postedAccountCode = trim((string)($_POST['account_code'] ?? ''));
    if ($postedAccountCode !== '' && strcasecmp($postedAccountCode, $accountCode) !== 0) {
        return json_response(422, 'Account code does not match the selected classification.');
    }

    if (!in_array($conditionStatus, ['SERVICEABLE', 'UNSERVICEABLE'], true)) {
        return json_response(422, 'Invalid condition selected.');
    }

    if (!preg_match('/^\d{4}$/', $yearAcquiredRaw)) {
        return json_response(422, 'Invalid year acquired.');
    }
    $yearAcquired = (int)$yearAcquiredRaw;

    if ($dateAcquired !== '') {
        $dt = DateTime::createFromFormat('Y-m-d', $dateAcquired);
        if (!$dt || $dt->format('Y-m-d') !== $dateAcquired) {
            return json_response(422, 'Invalid date acquired.');
        }
        if ((int)$dt->format('Y') !== $yearAcquired) {
            return json_response(422, 'Year acquired must match the selected date acquired.');
        }
    } else {
        $dateAcquired = null;
    }

    if (!is_numeric($amountRaw) || (float)$amountRaw < 0) {
        return json_response(422, 'Invalid amount.');
    }
    $amount = (float)$amountRaw;

    $adminId = (int)($_SESSION['alogin'] ?? 0);
    if ($adminId <= 0) {
        return json_response(401, 'Unauthorized.');
    }

    mysqli_begin_transaction($conn);

    try {
        $infraNo = gso_generate_infrastructure_number($conn, $tables['main'], $tables['prefix']);
        $recordStatus = 'ACTIVE';

        $insertMainSql = "INSERT INTO {$tables['main']} (
            infra_no,
            department_code,
            account_code,
            classification,
            description,
            location_name,
            barangay,
            date_acquired,
            year_acquired,
            amount,
            condition_status,
            remarks,
            record_status,
            created_by,
            updated_by
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        $stmtMain = $conn->prepare($insertMainSql);
        if (!$stmtMain) {
            throw new RuntimeException('Unable to prepare infrastructure insert.');
        }

        $stmtMain->bind_param(
            'sissssssidsssii',
            $infraNo,
            $departmentCode,
            $accountCode,
            $classification,
            $description,
            $locationName,
            $barangay,
            $dateAcquired,
            $yearAcquired,
            $amount,
            $conditionStatus,
            $remarks,
            $recordStatus,
            $adminId,
            $adminId
        );

        if (!$stmtMain->execute()) {
            throw new RuntimeException('Unable to save infrastructure record.');
        }

        $infraId = (int)$stmtMain->insert_id;
        $stmtMain->close();

        $referenceNumber = generateReferenceNumber($conn, $tables['history'], 'reference_number');
        $transactionType = 'REGISTERED';
        $changeReason = 'INITIAL REGISTRATION';

        $insertHistorySql = "INSERT INTO {$tables['history']} (
            infra_id,
            reference_number,
            transaction_type,
            effective_date,
            department_code,
            account_code,
            classification,
            description,
            location_name,
            barangay,
            date_acquired,
            year_acquired,
            amount,
            condition_status,
            remarks,
            record_status,
            change_reason,
            acted_by
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        $stmtHistory = $conn->prepare($insertHistorySql);
        if (!$stmtHistory) {
            throw new RuntimeException('Unable to prepare infrastructure history insert.');
        }

        $stmtHistory->bind_param(
            'isssissssssidssssi',
            $infraId,
            $referenceNumber,
            $transactionType,
            $dateAcquired,
            $departmentCode,
            $accountCode,
            $classification,
            $description,
            $locationName,
            $barangay,
            $dateAcquired,
            $yearAcquired,
            $amount,
            $conditionStatus,
            $remarks,
            $recordStatus,
            $changeReason,
            $adminId
        );

        if (!$stmtHistory->execute()) {
            throw new RuntimeException('Unable to save infrastructure history.');
        }

        $stmtHistory->close();
        mysqli_commit($conn);

        return json_response(200, 'Infrastructure saved successfully.', [
            'infra_no' => $infraNo,
            'reference_number' => $referenceNumber,
        ]);
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        return json_response(500, $e->getMessage());
    }
}

if (!function_exists('gso_land_options')) {
    function gso_land_options($type) {
        $items = [
            'fund' => ['GENERAL FUND', 'SEF', 'TRUST FUND'],
            'classification' => ['SOCIALIZED HOUSING', 'PUBLIC SCHOOL', 'PUBLIC HOSPITAL', 'OPEN SPACE', 'PUBLIC MARKET', 'ROAD LOT', 'EASEMENT', 'RIGHT OF WAY', 'DAYCARE CENTER', 'BARANGAY HALL', 'HEALTH CENTER', 'OTHER GOVERNMENT USE'],
            'yes_no' => ['YES', 'NO'],
            'tct_status' => ['ORIGINAL', 'NO', 'CERTIFIED TRUE COPY', 'PHOTOCOPY'],
            'document_status' => ['YES', 'NO', 'N/A', 'PHOTOCOPY', 'CERTIFIED COPY'],
            'transfer' => ['NO', 'TRANSFERRED'],
            'status' => ['ON PROCESS', 'INCOMPLETE DOCUMENTS', 'PENDING TRANSFER', 'TRANSFERRED'],
        ];
        return $items[$type] ?? [];
    }
}

if (!function_exists('gso_land_number')) {
    function gso_land_number(mysqli $conn) {
        $prefix = 'LAND-' . date('Y') . '-';
        $stmt = $conn->prepare("SELECT property_code FROM land_properties WHERE property_code LIKE CONCAT(?, '%') ORDER BY land_id DESC LIMIT 1");
        if (!$stmt) { throw new RuntimeException('Unable to prepare land property number query.'); }

        $stmt->bind_param('s', $prefix);
        $stmt->execute();
        $stmt->bind_result($lastCode);
        $stmt->fetch();
        $stmt->close();

        $next = 1;
        if (!empty($lastCode) && strpos((string)$lastCode, $prefix) === 0) {
            $suffix = substr((string)$lastCode, strlen($prefix));
            if (ctype_digit((string)$suffix)) { $next = ((int)$suffix) + 1; }
        }
        return $prefix . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('gso_land_money')) {
    function gso_land_money($value) {
        $raw = str_replace(',', '', trim((string)$value));
        return ($raw !== '' && is_numeric($raw) && (float)$raw >= 0) ? (float)$raw : null;
    }
}

if (!function_exists('gso_land_date_is_valid')) {
    function gso_land_date_is_valid($value) {
        $value = trim((string)$value);
        if (strtoupper($value) === 'N/A') { return true; }
        $dt = DateTime::createFromFormat('Y-m-d', $value);
        return $dt && $dt->format('Y-m-d') === $value;
    }
}

if (isset($_POST['fetch_land_property_dt'])) {
    header('Content-Type: application/json; charset=utf-8');

    if (!isset($_SESSION['alogin']) || trim((string)$_SESSION['alogin']) === '') {
        return json_response(401, 'Unauthorized.');
    }

    $rows = [];
    $sql = "SELECT land_id, property_code, fund_cluster, classification, declared_owner, tct_no, area_sqm, project_name, address, barangay, acquisition_cost, documentary_stamp_tax, capital_gains_tax, other_incidental_transfer_fees, total_amount, date_acquired, has_original_tct, tax_declaration_no, has_doas, has_dod, other_supporting_documents, transfer_status, current_status, remarks FROM land_properties ORDER BY created_at DESC, land_id DESC";
    $query = mysqli_query($conn, $sql);
    if (!$query) {
        return json_response(500, 'Failed to fetch land property records.', ['error' => mysqli_error($conn)]);
    }

    while ($row = mysqli_fetch_assoc($query)) {
        $rows[] = [
            'land_id' => (int)($row['land_id'] ?? 0),
            'property_code' => (string)($row['property_code'] ?? ''),
            'fund_cluster' => (string)($row['fund_cluster'] ?? ''),
            'classification' => (string)($row['classification'] ?? ''),
            'declared_owner' => (string)($row['declared_owner'] ?? ''),
            'tct_no' => (string)($row['tct_no'] ?? ''),
            'area_sqm' => (float)($row['area_sqm'] ?? 0),
            'project_name' => (string)($row['project_name'] ?? ''),
            'address' => (string)($row['address'] ?? ''),
            'barangay' => (string)($row['barangay'] ?? ''),
            'acquisition_cost' => (float)($row['acquisition_cost'] ?? 0),
            'documentary_stamp_tax' => (float)($row['documentary_stamp_tax'] ?? 0),
            'capital_gains_tax' => (float)($row['capital_gains_tax'] ?? 0),
            'other_incidental_transfer_fees' => (float)($row['other_incidental_transfer_fees'] ?? 0),
            'total_amount' => (float)($row['total_amount'] ?? 0),
            'date_acquired' => (string)($row['date_acquired'] ?? ''),
            'has_original_tct' => (string)($row['has_original_tct'] ?? ''),
            'tax_declaration_no' => (string)($row['tax_declaration_no'] ?? ''),
            'has_doas' => (string)($row['has_doas'] ?? ''),
            'has_dod' => (string)($row['has_dod'] ?? ''),
            'other_supporting_documents' => (string)($row['other_supporting_documents'] ?? ''),
            'transfer_status' => (string)($row['transfer_status'] ?? ''),
            'current_status' => (string)($row['current_status'] ?? ''),
            'remarks' => (string)($row['remarks'] ?? ''),
        ];
    }

    return json_response(200, 'OK', $rows);
}

if (isset($_POST['save_land_property'])) {
    header('Content-Type: application/json; charset=utf-8');

    if (!isset($_SESSION['alogin']) || trim((string)$_SESSION['alogin']) === '') {
        return json_response(401, 'Unauthorized.');
    }

    $fundCluster = strtoupper(trim((string)($_POST['fund_cluster'] ?? '')));
    $classification = strtoupper(trim((string)($_POST['classification'] ?? '')));
    $declaredOwner = strtoupper(trim((string)($_POST['declared_owner'] ?? '')));
    $tctNo = strtoupper(trim((string)($_POST['tct_no'] ?? '')));
    $areaSqm = gso_land_money($_POST['area_sqm'] ?? '');
    $projectName = strtoupper(trim((string)($_POST['project_name'] ?? '')));
    $address = strtoupper(trim((string)($_POST['address'] ?? '')));
    $barangay = strtoupper(trim((string)($_POST['barangay'] ?? '')));
    $acquisitionCost = gso_land_money($_POST['acquisition_cost'] ?? 0);
    $documentaryStampTax = gso_land_money($_POST['documentary_stamp_tax'] ?? 0);
    $capitalGainsTax = gso_land_money($_POST['capital_gains_tax'] ?? 0);
    $otherFees = gso_land_money($_POST['other_incidental_transfer_fees'] ?? 0);
    $dateAcquired = strtoupper(trim((string)($_POST['date_acquired'] ?? '')));
    $hasOriginalTct = strtoupper(trim((string)($_POST['has_original_tct'] ?? '')));
    $taxDeclarationNo = strtoupper(trim((string)($_POST['tax_declaration_no'] ?? '')));
    $hasDoas = strtoupper(trim((string)($_POST['has_doas'] ?? '')));
    $hasDod = strtoupper(trim((string)($_POST['has_dod'] ?? '')));
    $otherDocuments = strtoupper(trim((string)($_POST['other_supporting_documents'] ?? '')));
    $transferStatus = strtoupper(trim((string)($_POST['transfer_status'] ?? '')));
    $currentStatus = strtoupper(trim((string)($_POST['current_status'] ?? '')));
    $remarks = strtoupper(trim((string)($_POST['remarks'] ?? '')));

    $missing = [];
    if ($fundCluster === '') { $missing[] = 'fund cluster'; }
    if ($classification === '') { $missing[] = 'classification'; }
    if ($declaredOwner === '') { $missing[] = 'declared owner'; }
    if ($tctNo === '') { $missing[] = 'TCT no.'; }
    if ($areaSqm === null) { $missing[] = 'area'; }
    if ($address === '') { $missing[] = 'address'; }
    if ($barangay === '') { $missing[] = 'barangay'; }
    if ($dateAcquired === '') { $missing[] = 'date acquired'; }
    if ($hasOriginalTct === '') { $missing[] = 'original TCT'; }
    if ($hasDoas === '') { $missing[] = 'DOAS'; }
    if ($hasDod === '') { $missing[] = 'DOD'; }
    if ($transferStatus === '') { $missing[] = 'transfer status'; }
    if ($currentStatus === '') { $missing[] = 'progress status'; }
    if ($acquisitionCost === null || $documentaryStampTax === null || $capitalGainsTax === null || $otherFees === null) { $missing[] = 'valid amounts'; }

    if (!empty($missing)) {
        return json_response(422, 'Please complete: ' . implode(', ', $missing) . '.');
    }
    if (!in_array($fundCluster, gso_land_options('fund'), true)) { return json_response(422, 'Invalid fund cluster.'); }
    if (!in_array($classification, gso_land_options('classification'), true)) { return json_response(422, 'Invalid classification.'); }
    if (!in_array($hasOriginalTct, gso_land_options('tct_status'), true) || !in_array($hasDoas, gso_land_options('document_status'), true) || !in_array($hasDod, gso_land_options('document_status'), true)) {
        return json_response(422, 'Invalid document checklist value.');
    }
    if (!in_array($transferStatus, gso_land_options('transfer'), true)) { return json_response(422, 'Invalid transfer status.'); }
    if (!in_array($currentStatus, gso_land_options('status'), true)) { return json_response(422, 'Invalid progress status.'); }

    if (!gso_land_date_is_valid($dateAcquired)) { return json_response(422, 'Invalid date acquired.'); }

    $totalAmount = $acquisitionCost + $documentaryStampTax + $capitalGainsTax + $otherFees;
    $adminId = (int)($_SESSION['alogin'] ?? 0);
    if ($adminId <= 0) { return json_response(401, 'Unauthorized.'); }

    mysqli_begin_transaction($conn);
    try {
        $propertyCode = gso_land_number($conn);
        $insertMainSql = "INSERT INTO land_properties (property_code, fund_cluster, classification, declared_owner, tct_no, area_sqm, project_name, address, barangay, acquisition_cost, documentary_stamp_tax, capital_gains_tax, other_incidental_transfer_fees, total_amount, date_acquired, has_original_tct, tax_declaration_no, has_doas, has_dod, other_supporting_documents, transfer_status, current_status, remarks, created_by, updated_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $stmtMain = $conn->prepare($insertMainSql);
        if (!$stmtMain) { throw new RuntimeException('Unable to prepare land property insert: ' . $conn->error); }
        $stmtMain->bind_param('sssssdsssdddddsssssssssii', $propertyCode, $fundCluster, $classification, $declaredOwner, $tctNo, $areaSqm, $projectName, $address, $barangay, $acquisitionCost, $documentaryStampTax, $capitalGainsTax, $otherFees, $totalAmount, $dateAcquired, $hasOriginalTct, $taxDeclarationNo, $hasDoas, $hasDod, $otherDocuments, $transferStatus, $currentStatus, $remarks, $adminId, $adminId);
        if (!$stmtMain->execute()) { throw new RuntimeException('Unable to save land property record: ' . $stmtMain->error); }
        $landId = (int)$stmtMain->insert_id;
        $stmtMain->close();

        $referenceNumber = generateReferenceNumber($conn, 'land_property_history', 'reference_number');
        $eventType = 'REGISTERED';
        $actionDate = ($dateAcquired === 'N/A') ? date('Y-m-d') : $dateAcquired;
        $insertHistorySql = "INSERT INTO land_property_history (land_id, reference_number, event_type, action_date, fund_cluster, classification, declared_owner, tct_no, area_sqm, project_name, address, barangay, acquisition_cost, documentary_stamp_tax, capital_gains_tax, other_incidental_transfer_fees, total_amount, date_acquired, has_original_tct, tax_declaration_no, has_doas, has_dod, other_supporting_documents, transfer_status, current_status, remarks, acted_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $stmtHistory = $conn->prepare($insertHistorySql);
        if (!$stmtHistory) { throw new RuntimeException('Unable to prepare land history insert: ' . $conn->error); }
        $stmtHistory->bind_param('isssssssdsssdddddsssssssssi', $landId, $referenceNumber, $eventType, $actionDate, $fundCluster, $classification, $declaredOwner, $tctNo, $areaSqm, $projectName, $address, $barangay, $acquisitionCost, $documentaryStampTax, $capitalGainsTax, $otherFees, $totalAmount, $dateAcquired, $hasOriginalTct, $taxDeclarationNo, $hasDoas, $hasDod, $otherDocuments, $transferStatus, $currentStatus, $remarks, $adminId);
        if (!$stmtHistory->execute()) { throw new RuntimeException('Unable to save land property history: ' . $stmtHistory->error); }
        $stmtHistory->close();

        mysqli_commit($conn);
        return json_response(200, 'Land property saved successfully.', ['property_code' => $propertyCode, 'reference_number' => $referenceNumber]);
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        return json_response(500, $e->getMessage());
    }
}

if (isset($_POST['update_land_property'])) {
    header('Content-Type: application/json; charset=utf-8');

    if (!isset($_SESSION['alogin']) || trim((string)$_SESSION['alogin']) === '') {
        return json_response(401, 'Unauthorized.');
    }

    $landId = (int)($_POST['land_id'] ?? 0);
    if ($landId <= 0) { return json_response(422, 'Invalid land property record.'); }

    $fundCluster = strtoupper(trim((string)($_POST['fund_cluster'] ?? '')));
    $classification = strtoupper(trim((string)($_POST['classification'] ?? '')));
    $declaredOwner = strtoupper(trim((string)($_POST['declared_owner'] ?? '')));
    $tctNo = strtoupper(trim((string)($_POST['tct_no'] ?? '')));
    $areaSqm = gso_land_money($_POST['area_sqm'] ?? '');
    $projectName = strtoupper(trim((string)($_POST['project_name'] ?? '')));
    $address = strtoupper(trim((string)($_POST['address'] ?? '')));
    $barangay = strtoupper(trim((string)($_POST['barangay'] ?? '')));
    $acquisitionCost = gso_land_money($_POST['acquisition_cost'] ?? 0);
    $documentaryStampTax = gso_land_money($_POST['documentary_stamp_tax'] ?? 0);
    $capitalGainsTax = gso_land_money($_POST['capital_gains_tax'] ?? 0);
    $otherFees = gso_land_money($_POST['other_incidental_transfer_fees'] ?? 0);
    $dateAcquired = strtoupper(trim((string)($_POST['date_acquired'] ?? '')));
    $hasOriginalTct = strtoupper(trim((string)($_POST['has_original_tct'] ?? '')));
    $taxDeclarationNo = strtoupper(trim((string)($_POST['tax_declaration_no'] ?? '')));
    $hasDoas = strtoupper(trim((string)($_POST['has_doas'] ?? '')));
    $hasDod = strtoupper(trim((string)($_POST['has_dod'] ?? '')));
    $otherDocuments = strtoupper(trim((string)($_POST['other_supporting_documents'] ?? '')));
    $transferStatus = strtoupper(trim((string)($_POST['transfer_status'] ?? '')));
    $currentStatus = strtoupper(trim((string)($_POST['current_status'] ?? '')));
    $remarks = strtoupper(trim((string)($_POST['remarks'] ?? '')));

    $missing = [];
    if ($fundCluster === '') { $missing[] = 'fund cluster'; }
    if ($classification === '') { $missing[] = 'classification'; }
    if ($declaredOwner === '') { $missing[] = 'declared owner'; }
    if ($tctNo === '') { $missing[] = 'TCT no.'; }
    if ($areaSqm === null) { $missing[] = 'area'; }
    if ($address === '') { $missing[] = 'address'; }
    if ($barangay === '') { $missing[] = 'barangay'; }
    if ($dateAcquired === '') { $missing[] = 'date acquired'; }
    if ($hasOriginalTct === '') { $missing[] = 'original TCT'; }
    if ($hasDoas === '') { $missing[] = 'DOAS'; }
    if ($hasDod === '') { $missing[] = 'DOD'; }
    if ($transferStatus === '') { $missing[] = 'transfer status'; }
    if ($currentStatus === '') { $missing[] = 'progress status'; }
    if ($acquisitionCost === null || $documentaryStampTax === null || $capitalGainsTax === null || $otherFees === null) { $missing[] = 'valid amounts'; }

    if (!empty($missing)) { return json_response(422, 'Please complete: ' . implode(', ', $missing) . '.'); }
    if (!in_array($fundCluster, gso_land_options('fund'), true)) { return json_response(422, 'Invalid fund cluster.'); }
    if (!in_array($classification, gso_land_options('classification'), true)) { return json_response(422, 'Invalid classification.'); }
    if (!in_array($hasOriginalTct, gso_land_options('tct_status'), true) || !in_array($hasDoas, gso_land_options('document_status'), true) || !in_array($hasDod, gso_land_options('document_status'), true)) { return json_response(422, 'Invalid document checklist value.'); }
    if (!in_array($transferStatus, gso_land_options('transfer'), true)) { return json_response(422, 'Invalid transfer status.'); }
    if (!in_array($currentStatus, gso_land_options('status'), true)) { return json_response(422, 'Invalid progress status.'); }

    if (!gso_land_date_is_valid($dateAcquired)) { return json_response(422, 'Invalid date acquired.'); }

    $totalAmount = $acquisitionCost + $documentaryStampTax + $capitalGainsTax + $otherFees;
    $adminId = (int)($_SESSION['alogin'] ?? 0);
    if ($adminId <= 0) { return json_response(401, 'Unauthorized.'); }

    mysqli_begin_transaction($conn);
    try {
        $checkStmt = $conn->prepare('SELECT land_id FROM land_properties WHERE land_id = ? LIMIT 1');
        if (!$checkStmt) { throw new RuntimeException('Unable to prepare land property lookup.'); }
        $checkStmt->bind_param('i', $landId);
        $checkStmt->execute();
        $checkStmt->store_result();
        if ($checkStmt->num_rows <= 0) { throw new RuntimeException('Land property record not found.'); }
        $checkStmt->close();

        $updateSql = "UPDATE land_properties SET fund_cluster=?, classification=?, declared_owner=?, tct_no=?, area_sqm=?, project_name=?, address=?, barangay=?, acquisition_cost=?, documentary_stamp_tax=?, capital_gains_tax=?, other_incidental_transfer_fees=?, total_amount=?, date_acquired=?, has_original_tct=?, tax_declaration_no=?, has_doas=?, has_dod=?, other_supporting_documents=?, transfer_status=?, current_status=?, remarks=?, updated_by=? WHERE land_id=?";
        $stmtMain = $conn->prepare($updateSql);
        if (!$stmtMain) { throw new RuntimeException('Unable to prepare land property update: ' . $conn->error); }
        $stmtMain->bind_param('ssssdsssdddddsssssssssii', $fundCluster, $classification, $declaredOwner, $tctNo, $areaSqm, $projectName, $address, $barangay, $acquisitionCost, $documentaryStampTax, $capitalGainsTax, $otherFees, $totalAmount, $dateAcquired, $hasOriginalTct, $taxDeclarationNo, $hasDoas, $hasDod, $otherDocuments, $transferStatus, $currentStatus, $remarks, $adminId, $landId);
        if (!$stmtMain->execute()) { throw new RuntimeException('Unable to update land property record: ' . $stmtMain->error); }
        $stmtMain->close();

        $referenceNumber = generateReferenceNumber($conn, 'land_property_history', 'reference_number');
        $eventType = 'UPDATED';
        $actionDate = date('Y-m-d');
        $insertHistorySql = "INSERT INTO land_property_history (land_id, reference_number, event_type, action_date, fund_cluster, classification, declared_owner, tct_no, area_sqm, project_name, address, barangay, acquisition_cost, documentary_stamp_tax, capital_gains_tax, other_incidental_transfer_fees, total_amount, date_acquired, has_original_tct, tax_declaration_no, has_doas, has_dod, other_supporting_documents, transfer_status, current_status, remarks, acted_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $stmtHistory = $conn->prepare($insertHistorySql);
        if (!$stmtHistory) { throw new RuntimeException('Unable to prepare land update history: ' . $conn->error); }
        $stmtHistory->bind_param('isssssssdsssdddddsssssssssi', $landId, $referenceNumber, $eventType, $actionDate, $fundCluster, $classification, $declaredOwner, $tctNo, $areaSqm, $projectName, $address, $barangay, $acquisitionCost, $documentaryStampTax, $capitalGainsTax, $otherFees, $totalAmount, $dateAcquired, $hasOriginalTct, $taxDeclarationNo, $hasDoas, $hasDod, $otherDocuments, $transferStatus, $currentStatus, $remarks, $adminId);
        if (!$stmtHistory->execute()) { throw new RuntimeException('Unable to save land update history: ' . $stmtHistory->error); }
        $stmtHistory->close();

        mysqli_commit($conn);
        return json_response(200, 'Land property updated successfully.', ['reference_number' => $referenceNumber]);
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        return json_response(500, $e->getMessage());
    }
}

// ============================================================
// Update New Purchase Item
// ============================================================
if (!function_exists('gso_new_purchase_property_number_in_use')) {
    function gso_new_purchase_property_number_in_use($conn, $candidate, $newPurchaseId = 0, $oldPropertyNumber = '') {
        $candidate = strtoupper(trim((string)$candidate));
        $oldPropertyNumber = strtoupper(trim((string)$oldPropertyNumber));
        $newPurchaseId = (int)$newPurchaseId;

        if ($candidate === '') {
            return false;
        }

        if ($oldPropertyNumber !== '' && $candidate === $oldPropertyNumber) {
            return false;
        }

        $stmtDup = $conn->prepare('
            SELECT 1
            FROM (
                SELECT property_number AS prop FROM new_purchase WHERE property_number = ? AND id <> ?
                UNION ALL SELECT par_number AS prop FROM new_purchase_history WHERE par_number = ? AND par_number <> ?
                UNION ALL SELECT property_number AS prop FROM new_bundle_purchase WHERE property_number = ? AND property_number <> ?
                UNION ALL SELECT bundle_with AS prop FROM new_bundle_purchase WHERE bundle_with = ? AND bundle_with <> ?
                UNION ALL SELECT par_number AS prop FROM par_gen_fund WHERE par_number = ?
                UNION ALL SELECT property_number AS prop FROM property_sef WHERE property_number = ?
            ) AS duplicates
            LIMIT 1
        ');

        if (!$stmtDup) {
            throw new RuntimeException('Prepare failed: ' . $conn->error);
        }

        $stmtDup->bind_param(
            'sissssssss',
            $candidate,
            $newPurchaseId,
            $candidate,
            $oldPropertyNumber,
            $candidate,
            $oldPropertyNumber,
            $candidate,
            $oldPropertyNumber,
            $candidate,
            $candidate
        );
        $stmtDup->execute();
        $resDup = $stmtDup->get_result();
        $inUse = $resDup && $resDup->num_rows > 0;
        $stmtDup->close();

        return $inUse;
    }
}

if (isset($_POST['generate_new_purchase_edit_property_number'])) {
    header('Content-Type: application/json; charset=utf-8');

    if (!isset($_SESSION['alogin']) || trim((string)$_SESSION['alogin']) === '') {
        echo json_encode(['status' => 401, 'message' => 'Unauthorized.']);
        return;
    }

    $newPurchaseId = (int)($_POST['new_purchase_id'] ?? 0);
    $oldPropertyNumber = strtoupper(trim((string)($_POST['property_number'] ?? '')));
    $category = strtoupper(trim((string)($_POST['category'] ?? '')));
    $year = trim((string)($_POST['year'] ?? ''));
    $accountCode = strtoupper(trim((string)($_POST['account_code'] ?? '')));
    $dept = trim((string)($_POST['dept'] ?? ''));
    $fund = strtoupper(trim((string)($_POST['fund'] ?? '')));
    $itemQuantity = max(1, min(5000, (int)($_POST['item_quantity'] ?? 1)));
    $postedExcludedNumbers = isset($_POST['exclude_property_numbers']) && is_array($_POST['exclude_property_numbers'])
        ? $_POST['exclude_property_numbers']
        : [];

    if ($category === '' || $year === '' || $accountCode === '' || $dept === '' || $fund === '') {
        echo json_encode(['status' => 422, 'message' => 'Missing required property number details.']);
        return;
    }

    $exclude = $oldPropertyNumber !== '' ? [$oldPropertyNumber] : [];
    foreach ($postedExcludedNumbers as $excludedNumber) {
        $excludedNumber = strtoupper(trim((string)$excludedNumber));
        if ($excludedNumber !== '' && !in_array($excludedNumber, $exclude, true)) {
            $exclude[] = $excludedNumber;
        }
    }

    try {
        $nextPropertyNumber = function ($current) {
            $parts = explode('-', (string)$current);
            if (count($parts) < 4) { return (string)$current; }
            $seqIndex = count($parts) - 2;
            $seq = $parts[$seqIndex];
            $parts[$seqIndex] = str_pad((string)(((int)$seq) + 1), strlen($seq), '0', STR_PAD_LEFT);
            return implode('-', $parts);
        };

        for ($attempt = 0; $attempt < 50; $attempt++) {
            $gen = gso_generate_one_property_number($conn, $category, $year, $accountCode, $dept, $fund, $exclude);
            if (!isset($gen['ok']) || !$gen['ok']) {
                echo json_encode(['status' => 422, 'message' => $gen['error'] ?? 'Unable to generate property number.']);
                return;
            }

            $candidate = strtoupper(trim((string)($gen['property_number'] ?? '')));
            $candidateBlock = [];
            $blockNumber = $candidate;
            $blockAvailable = $candidate !== '';
            for ($copyIndex = 1; $copyIndex <= $itemQuantity && $blockAvailable; $copyIndex++) {
                $blockKey = strtoupper(trim((string)$blockNumber));
                $checkId = $copyIndex === 1 ? $newPurchaseId : 0;
                $checkOld = $copyIndex === 1 ? $oldPropertyNumber : '';
                if ($blockKey === '' || in_array($blockKey, $exclude, true) || gso_new_purchase_property_number_in_use($conn, $blockKey, $checkId, $checkOld)) {
                    $blockAvailable = false;
                    break;
                }
                $candidateBlock[] = $blockKey;
                $blockNumber = strtoupper(trim((string)$nextPropertyNumber($blockKey)));
            }

            if ($blockAvailable) {
                echo json_encode(['status' => 200, 'message' => 'Available property number generated.', 'data' => ['property_number' => $candidate]]);
                return;
            }

            foreach ($candidateBlock as $blockKey) {
                if (!in_array($blockKey, $exclude, true)) {
                    $exclude[] = $blockKey;
                }
            }
            if ($candidate !== '') {
                $exclude[] = $candidate;
            }
        }

        echo json_encode(['status' => 409, 'message' => 'Unable to allocate an available property number.']);
        return;
    } catch (Throwable $e) {
        echo json_encode(['status' => 500, 'message' => $e->getMessage()]);
        return;
    }
}

if (isset($_POST['update_new_purchase_item'])) {
    header('Content-Type: application/json; charset=utf-8');

    if (!isset($_SESSION['alogin']) || trim((string)$_SESSION['alogin']) === '') {
        echo json_encode(['status' => 401, 'message' => 'Unauthorized.']);
        return;
    }

    $newPurchaseId = (int)($_POST['new_purchase_id'] ?? 0);
    if ($newPurchaseId <= 0) {
        echo json_encode(['status' => 422, 'message' => 'Invalid item selected.']);
        return;
    }

    $propNum = strtoupper(trim((string)($_POST['property_number'] ?? '')));
    $newPropNum = strtoupper(trim((string)($_POST['new_property_number'] ?? $propNum)));

    $item        = strtoupper(trim((string)($_POST['item']             ?? '')));
    $model       = strtoupper(trim((string)($_POST['model']            ?? '')));
    $description = strtoupper(trim((string)($_POST['description']      ?? '')));
    $sn1         = strtoupper(trim((string)($_POST['serial_number']    ?? '')));
    $sn2         = strtoupper(trim((string)($_POST['serial_number_2']  ?? '')));
    $unitValue   = (float) preg_replace('/[^0-9.]/', '', $_POST['unit_value']    ?? '0');
    $dateAquired = trim((string)($_POST['date_aquired']     ?? ''));
    $accountCode = trim((string)($_POST['account_code']     ?? ''));
    $supplier    = strtoupper(trim((string)($_POST['supplier']         ?? '')));
    $parIcs      = strtoupper(trim((string)($_POST['par_ics_number']   ?? '')));
    $pr          = strtoupper(trim((string)($_POST['purchase_request'] ?? '')));
    $obr         = strtoupper(trim((string)($_POST['obr_number']       ?? '')));
    $jev         = strtoupper(trim((string)($_POST['jev_number']       ?? '')));
    $remarks     = strtoupper(trim((string)($_POST['remarks']          ?? '')));
    $fund        = strtoupper(trim((string)($_POST['fund']             ?? '')));
    $category    = strtoupper(trim((string)($_POST['category']         ?? '')));
    $propertyNumberOptionalFundForUpdate = in_array($fund, ['TRUST FUND', 'DONATION'], true);
    if ($propertyNumberOptionalFundForUpdate) {
        $newPropNum = '';
    }
    $yearForPropertyNumber = '';

    $stmtCurrent = $conn->prepare('SELECT property_number, category FROM new_purchase WHERE id = ? LIMIT 1');
    if (!$stmtCurrent) {
        echo json_encode(['status' => 500, 'message' => 'Prepare failed: ' . $conn->error]);
        return;
    }
    $stmtCurrent->bind_param('i', $newPurchaseId);
    $stmtCurrent->execute();
    $resCurrent = $stmtCurrent->get_result();
    $currentRow = $resCurrent ? $resCurrent->fetch_assoc() : null;
    $stmtCurrent->close();
    if (!$currentRow) {
        echo json_encode(['status' => 404, 'message' => 'New purchase item was not found.']);
        return;
    }

    $oldPropNum = strtoupper(trim((string)($currentRow['property_number'] ?? '')));
    $oldCategory = strtoupper(trim((string)($currentRow['category'] ?? '')));
    $yearForPropertyNumber = trim((string)($dateAquired !== '' ? $dateAquired : ''));
    if ($yearForPropertyNumber === '') {
        $yearForPropertyNumber = date('Y');
    } elseif (preg_match('/^\d{4}/', $yearForPropertyNumber, $yearMatch)) {
        $yearForPropertyNumber = $yearMatch[0];
    }
    if ($propNum !== '' && $oldPropNum !== '' && $propNum !== $oldPropNum) {
        echo json_encode(['status' => 409, 'message' => 'This item was changed by another user. Please refresh and try again.']);
        return;
    }

    if ($newPropNum !== '' && $newPropNum !== $oldPropNum) {
        try {
            $hasDuplicate = gso_new_purchase_property_number_in_use($conn, $newPropNum, $newPurchaseId, $oldPropNum);
        } catch (Throwable $e) {
            echo json_encode(['status' => 500, 'message' => $e->getMessage()]);
            return;
        }

        if ($hasDuplicate) {
            $deptForGeneration = '';
            if ($oldPropNum !== '') {
                $parts = explode('-', $oldPropNum);
                $deptForGeneration = trim((string)end($parts));
            }
            if ($deptForGeneration === '' && $newPropNum !== '') {
                $parts = explode('-', $newPropNum);
                $deptForGeneration = trim((string)end($parts));
            }
            if ($deptForGeneration === '' || $accountCode === '' || $category === '' || $fund === '') {
                echo json_encode(['status' => 409, 'message' => 'Property number already exists.']);
                return;
            }

            try {
                $exclude = array_values(array_filter([$oldPropNum, $newPropNum]));
                for ($attempt = 0; $attempt < 50; $attempt++) {
                    $generated = gso_generate_one_property_number($conn, $category, $yearForPropertyNumber, $accountCode, $deptForGeneration, $fund, $exclude);
                    if (!isset($generated['ok']) || !$generated['ok']) {
                        break;
                    }
                    $candidate = strtoupper(trim((string)($generated['property_number'] ?? '')));
                    if ($candidate === '') {
                        break;
                    }
                    if (!gso_new_purchase_property_number_in_use($conn, $candidate, $newPurchaseId, $oldPropNum)) {
                        $newPropNum = $candidate;
                        break;
                    }
                    $exclude[] = $candidate;
                }
            } catch (Throwable $e) {
                echo json_encode(['status' => 500, 'message' => $e->getMessage()]);
                return;
            }

            if ($newPropNum === '' || $newPropNum === strtoupper(trim((string)($_POST['new_property_number'] ?? $propNum)))) {
                echo json_encode(['status' => 409, 'message' => 'Property number already exists.']);
                return;
            }
        }
    }

    // Convert empty strings to NULL for nullable columns
    $sn1         = $sn1         !== '' ? $sn1         : null;
    $sn2         = $sn2         !== '' ? $sn2         : null;
    $supplier    = $supplier    !== '' ? $supplier    : null;
    $parIcs      = $parIcs      !== '' ? $parIcs      : null;
    $pr          = $pr          !== '' ? $pr          : null;
    $obr         = $obr         !== '' ? $obr         : null;
    $jev         = $jev         !== '' ? $jev         : null;
    $remarks     = $remarks     !== '' ? $remarks     : null;
    $dateAquired = $dateAquired !== '' ? $dateAquired : null;
    $newPropForDb = $newPropNum !== '' ? $newPropNum : null;

    $stmt = $conn->prepare(
        'UPDATE new_purchase
         SET item=?, model=?, description=?, serial_number=?, serial_number_2=?,
             unit_value=?, date_aquired=?, account_code=?, supplier=?,
             par_ics_number=?, purchase_request=?, obr_number=?, jev_number=?,
             remarks=?, fund=?, category=?, property_number=?
         WHERE id=?'
    );

    if (!$stmt) {
        echo json_encode(['status' => 500, 'message' => 'Prepare failed: ' . $conn->error]);
        return;
    }

    $stmt->bind_param(
        'sssssdsssssssssssi',
        $item, $model, $description, $sn1, $sn2,
        $unitValue, $dateAquired, $accountCode, $supplier,
        $parIcs, $pr, $obr, $jev, $remarks, $fund, $category,
        $newPropForDb, $newPurchaseId
    );

    if (!$stmt->execute()) {
        echo json_encode(['status' => 500, 'message' => 'Update failed: ' . $stmt->error]);
        return;
    }

    if ($propertyNumberOptionalFundForUpdate) {
        $historyLink = 'NPID:' . $newPurchaseId;
        if ($oldPropNum !== '') {
            $stmtHist = $conn->prepare('UPDATE new_purchase_history SET par_number = ?, category = ? WHERE par_number = ?');
            if ($stmtHist) {
                $stmtHist->bind_param('sss', $historyLink, $category, $oldPropNum);
                $stmtHist->execute();
                $stmtHist->close();
            }
            $stmtBundle = $conn->prepare('UPDATE new_bundle_purchase SET property_number = CASE WHEN property_number = ? THEN NULL ELSE property_number END, bundle_with = CASE WHEN bundle_with = ? THEN NULL ELSE bundle_with END WHERE property_number = ? OR bundle_with = ?');
            if ($stmtBundle) {
                $stmtBundle->bind_param('ssss', $oldPropNum, $oldPropNum, $oldPropNum, $oldPropNum);
                $stmtBundle->execute();
                $stmtBundle->close();
            }
        } else {
            $stmtHist = $conn->prepare('UPDATE new_purchase_history SET par_number = ?, category = ? WHERE (par_number = ? OR par_number IS NULL OR par_number = \'\') AND status = 1 ORDER BY (par_number = ?) DESC, id ASC LIMIT 1');
            if ($stmtHist) {
                $stmtHist->bind_param('ssss', $historyLink, $category, $historyLink, $historyLink);
                $stmtHist->execute();
                $stmtHist->close();
            }
        }
    } elseif ($newPropNum !== '' && $newPropNum !== $oldPropNum) {
        if ($oldPropNum !== '') {
            $stmtHist = $conn->prepare('UPDATE new_purchase_history SET par_number = ?, category = ? WHERE par_number = ?');
            if ($stmtHist) {
                $stmtHist->bind_param('sss', $newPropNum, $category, $oldPropNum);
                $stmtHist->execute();
                $stmtHist->close();
            }
            $stmtBundle = $conn->prepare('UPDATE new_bundle_purchase SET property_number = CASE WHEN property_number = ? THEN ? ELSE property_number END, bundle_with = CASE WHEN bundle_with = ? THEN ? ELSE bundle_with END WHERE property_number = ? OR bundle_with = ?');
            if ($stmtBundle) {
                $stmtBundle->bind_param('ssssss', $oldPropNum, $newPropNum, $oldPropNum, $newPropNum, $oldPropNum, $oldPropNum);
                $stmtBundle->execute();
                $stmtBundle->close();
            }
        } else {
            $historyCategory = $oldCategory !== '' ? $oldCategory : $category;
            $historyLink = 'NPID:' . $newPurchaseId;
            $stmtHist = $conn->prepare('UPDATE new_purchase_history SET par_number = ?, category = ? WHERE (par_number = ? OR par_number IS NULL OR par_number = \'\') AND category = ? AND status = 1 ORDER BY (par_number = ?) DESC, id ASC LIMIT 1');
            if ($stmtHist) {
                $stmtHist->bind_param('sssss', $newPropNum, $category, $historyLink, $historyCategory, $historyLink);
                $stmtHist->execute();
                $stmtHist->close();
            }
        }
    }

    echo json_encode(['status' => 200, 'message' => 'Item updated successfully.']);
    return;
}

if (!function_exists('gso_new_purchase_summary_rows')) {
    function gso_new_purchase_summary_rows($conn) {
        $sql = "
            SELECT
                MIN(np.id) AS row_id,
                NULLIF(TRIM(np.purchase_order), '') AS purchase_order,
                MAX(np.fund) AS fund,
                MAX(np.purchase_request) AS purchase_request,
                MAX(np.obr_number) AS obr_number,
                MAX(np.supplier) AS supplier,
                MAX(np.par_ics_number) AS par_ics_number,
                MAX(COALESCE(d_by_id.department_code, d_by_code.department_code, h.dept_id)) AS department_code,
                MAX(COALESCE(d_by_id.department_name, d_by_code.department_name)) AS department_name,
                SUM(COALESCE(np.unit_value, 0)) AS total_amount,
                MAX(np.created_at) AS created_at
            FROM new_purchase AS np
            LEFT JOIN new_purchase_history AS h
                ON (h.par_number = np.property_number OR h.par_number = CONCAT('NPID:', np.id)) AND h.status = 1
            LEFT JOIN department AS d_by_code ON d_by_code.department_code = h.dept_id
            LEFT JOIN department AS d_by_id ON d_by_id.dept_id = h.dept_id
            GROUP BY
                CASE
                    WHEN TRIM(COALESCE(np.purchase_order, '')) = '' THEN CONCAT('ID:', np.id)
                    ELSE CONCAT('PO:', UPPER(TRIM(np.purchase_order)))
                END
            ORDER BY MAX(np.created_at) DESC
        ";

        $stmt = $conn->prepare($sql);
        if (!$stmt) { return false; }
        $stmt->execute();
        return $stmt->get_result();
    }
}

if (!function_exists('gso_new_purchase_items')) {
    function gso_new_purchase_items($conn, $po = '', $id = 0) {
        $id = (int)$id;
        $po = trim((string)$po);

        if ($po === '' && $id <= 0) { return false; }

        $where = $id > 0 ? 'np.id = ?' : 'np.purchase_order = ?';
        $sql = "
            SELECT
                np.id,
                np.unit, np.item, np.model, np.description,
                np.serial_number, np.serial_number_2,
                COALESCE(
                    NULLIF(np.property_number, ''),
                    CASE
                        WHEN h.par_number LIKE 'NPID:%' THEN ''
                        ELSE COALESCE(h.par_number, '')
                    END
                ) AS property_number,
                np.fund, np.category, np.unit_value, np.date_aquired,
                np.account_code, np.supplier, np.par_ics_number,
                np.purchase_order, np.purchase_request, np.obr_number,
                np.jev_number, np.remarks,
                COALESCE(d_by_id.department_name, d_by_code.department_name) AS department_name,
                COALESCE(d_by_id.department_code, d_by_code.department_code) AS department_code,
                h.dept_id, h.emp_id, e.emp_name,
                h.category AS doc_type, h.reference_number
            FROM new_purchase AS np
            LEFT JOIN new_purchase_history AS h
                ON (h.par_number = np.property_number OR h.par_number = CONCAT('NPID:', np.id)) AND h.status = 1
            LEFT JOIN department AS d_by_id ON d_by_id.dept_id = h.dept_id
            LEFT JOIN department AS d_by_code ON d_by_code.department_code = h.dept_id
            LEFT JOIN employee AS e ON e.emp_id = h.emp_id
            WHERE {$where}
              AND (
                np.property_number IS NULL
                OR np.property_number = ''
                OR np.id = (
                    SELECT MIN(np2.id)
                    FROM new_purchase AS np2
                    WHERE np2.property_number = np.property_number
                )
              )
            ORDER BY np.id ASC
        ";

        $stmt = $conn->prepare($sql);
        if (!$stmt) { return false; }
        if ($id > 0) {
            $stmt->bind_param('i', $id);
        } else {
            $stmt->bind_param('s', $po);
        }
        $stmt->execute();
        return $stmt->get_result();
    }
}
?>
