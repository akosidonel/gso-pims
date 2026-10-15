<?php
// A transfer belongs to the current assignment only when its reference and recipient match.
function gso_inventory_transfer_id_sql(string $fund = 'GF'): string {
    $property = $fund === 'SEF' ? 'sh.property_number' : 'g.par_number';
    $assignment = $fund === 'SEF' ? 'sh' : 'g';
    return "(SELECT transfer.id FROM items_user_history AS transfer
             WHERE transfer.par_number = {$property}
               AND transfer.reference_number = {$assignment}.reference_number
               AND TRIM(COALESCE(transfer.reference_number, '')) <> ''
               AND transfer.new_user = {$assignment}.emp_id
             ORDER BY transfer.id DESC LIMIT 1)";
}

function gso_fetch_general_fund_inventory_print_row(mysqli $conn, string $par): ?array {
    return gso_fetch_inventory_print_row($conn, $par, 'GF');
}

function gso_fetch_inventory_print_row(mysqli $conn, string $par, string $fund = 'GF'): ?array {
    if (!in_array($fund, ['GF', 'SEF'], true)) {
        throw new InvalidArgumentException('Unknown inventory fund.');
    }
    $isSef = $fund === 'SEF';
    $historyTable = $isSef ? 'sef_property_history' : 'general_fund_property_history';
    $propertyTable = $isSef ? 'property_sef' : 'par_gen_fund';
    $alias = $isSef ? 'sh' : 'g';
    $propertyColumn = $isSef ? 'property_number' : 'par_number';
    $deptColumn = $isSef ? 'sch_id' : 'dept_id';
    $transferIdSql = gso_inventory_transfer_id_sql($fund);
    $sql = "SELECT p.*, {$alias}.{$propertyColumn} AS par_number, {$alias}.reference_number, {$alias}.emp_id,
                   {$alias}.emp_id AS new_user, {$alias}.{$deptColumn} AS new_dept,
                   UPPER(REPLACE(TRIM(COALESCE(NULLIF({$alias}.category, ''), p.category)), '.', '')) AS category,
                   e.emp_name AS user,
                   d.department_code, d.department_name,
                   COALESCE(i.previous_user, '') AS previous_user,
                   COALESCE(i.previous_dept, '') AS previous_dept,
                   (i.id IS NOT NULL) AS has_active_transfer,
                   'OLD' AS source
            FROM {$historyTable} AS {$alias}
            JOIN {$propertyTable} AS p ON p.{$propertyColumn} = {$alias}.{$propertyColumn}
            JOIN employee AS e ON e.emp_id = {$alias}.emp_id
            LEFT JOIN department AS d ON (
                d.dept_id = {$alias}.{$deptColumn} OR (
                    d.department_code = {$alias}.{$deptColumn} AND NOT EXISTS (
                        SELECT 1 FROM department AS exact_dept WHERE exact_dept.dept_id = {$alias}.{$deptColumn}
                    )
                )
            )
            LEFT JOIN items_user_history AS i ON i.id = {$transferIdSql}
            WHERE {$alias}.{$propertyColumn} = ? AND {$alias}.status = 1
            ORDER BY {$alias}.id DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $par);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}
