<?php
// Query helpers shared by the DataTable endpoint and read-only regression checks.
function gso_np_summary_group_key(string $alias = 'np'): string {
    return "CASE WHEN TRIM(COALESCE($alias.purchase_order, '')) = '' THEN CONCAT('ID:', $alias.id)
        ELSE CONCAT('PO:', UPPER(TRIM($alias.purchase_order))) END";
}

function gso_np_summary_query(mysqli $conn, string $sql, array $params = []): array {
    $stmt = $conn->prepare($sql);
    if (!$stmt) { throw new RuntimeException('Unable to prepare purchase summary.'); }
    try {
        if ($params) { $stmt->bind_param(str_repeat('s', count($params)), ...$params); }
        if (!$stmt->execute()) { throw new RuntimeException('Unable to load purchase summary.'); }
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } finally {
        $stmt->close();
    }
}

/** Resolve departments only for purchases in the requested groups. */
function gso_np_summary_departments(mysqli $conn, ?array $groupKeys = null): array {
    if ($groupKeys === []) { return []; }
    $departments = gso_np_summary_query($conn, 'SELECT dept_id, department_code, department_name FROM department');
    $byId = [];
    $byCode = [];
    foreach ($departments as $department) {
        $byId[(string)$department['dept_id']] = $department;
        $byCode[(string)$department['department_code']] = $department;
    }
    $key = gso_np_summary_group_key();
    $where = $groupKeys === null ? '' : ' WHERE ' . $key . ' IN (' . implode(',', array_fill(0, count($groupKeys), '?')) . ')';
    // Point lookups avoid materializing every active history twice. MAX preserves
    // the handling of multiple active histories for the same property.
    $rows = gso_np_summary_query($conn, "SELECT $key AS group_key,
        COALESCE(
            (SELECT MAX(h.dept_id) FROM new_purchase_history h WHERE h.par_number = np.property_number AND h.status = 1),
            (SELECT MAX(h.dept_id) FROM new_purchase_history h WHERE h.par_number = CONCAT('NPID:', np.id) AND h.status = 1)
        ) AS dept_id FROM new_purchase np$where", $groupKeys ?? []);
    $groups = [];
    foreach ($rows as $row) {
        $groupKey = $row['group_key'];
        $raw = (string)($row['dept_id'] ?? '');
        $department = $byId[$raw] ?? $byCode[$raw] ?? null;
        $code = (string)($department['department_code'] ?? $raw);
        $name = (string)($department['department_name'] ?? '');
        if (!isset($groups[$groupKey])) { $groups[$groupKey] = ['department_code' => '', 'department_name' => '']; }
        if (strcasecmp($code, $groups[$groupKey]['department_code']) > 0) { $groups[$groupKey]['department_code'] = $code; }
        if (strcasecmp($name, $groups[$groupKey]['department_name']) > 0) { $groups[$groupKey]['department_name'] = $name; }
    }
    return $groups;
}

function gso_np_summary_response(mysqli $conn, array $request): array {
    $draw = max(0, (int)($request['draw'] ?? 0));
    $start = max(0, (int)($request['start'] ?? 0));
    $length = max(1, min(100, (int)($request['length'] ?? 10)));
    $search = trim((string)($request['search']['value'] ?? ''));
    $key = gso_np_summary_group_key();
    $base = "SELECT $key AS group_key, MIN(np.id) AS row_id,
        MAX(NULLIF(TRIM(np.purchase_order), '')) AS purchase_order,
        MAX(np.fund) AS fund, MAX(np.purchase_request) AS purchase_request,
        MAX(np.obr_number) AS obr_number, MAX(np.supplier) AS supplier,
        MAX(np.par_ics_number) AS par_ics_number, SUM(COALESCE(np.unit_value, 0)) AS total_amount,
        MAX(np.created_at) AS created_at FROM new_purchase np GROUP BY $key";
    $total = gso_np_summary_query($conn, "SELECT COUNT(*) AS total FROM (SELECT $key FROM new_purchase np GROUP BY $key) groups_total")[0]['total'];
    $departmentGroups = null;
    $where = [];
    $params = [];
    if ($search !== '') {
        // Searches need the displayed department names for all groups. Ordinary
        // page loads resolve only the visible groups. Search values remain bound.
        $departmentGroups = gso_np_summary_departments($conn);
        preg_match_all('/"([^"]+)"|(\S+)/u', $search, $tokens, PREG_SET_ORDER);
        $terms = array_map(function ($token) { return $token[1] !== '' ? $token[1] : $token[2]; }, $tokens);
        foreach (array_slice($terms, 0, 10) as $term) {
            $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term) . '%';
            $matches = [];
            foreach ($departmentGroups as $groupKey => $department) {
                $name = $department['department_name'] !== '' ? $department['department_name'] : '-';
                $position = function_exists('mb_stripos') ? mb_stripos($name, $term) : stripos($name, $term);
                if ($position !== false) { $matches[] = $groupKey; }
            }
            $parts = [];
            foreach (["COALESCE(NULLIF(TRIM(purchase_order), ''), 'NULL')", "COALESCE(NULLIF(TRIM(purchase_request), ''), 'NULL')",
                "COALESCE(NULLIF(TRIM(obr_number), ''), 'NULL')", "COALESCE(NULLIF(TRIM(supplier), ''), '-')",
                "CONCAT('₱ ', FORMAT(total_amount, 2))", 'CAST(total_amount AS CHAR)'] as $column) {
                $parts[] = "$column LIKE ? ESCAPE '!'";
                $params[] = $like;
            }
            if ($matches) {
                $parts[] = 'group_key IN (' . implode(',', array_fill(0, count($matches), '?')) . ')';
                $params = array_merge($params, $matches);
            }
            $where[] = '(' . implode(' OR ', $parts) . ')';
        }
    }
    $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $filtered = $where ? gso_np_summary_query($conn, "SELECT COUNT(*) AS total FROM ($base) summary$whereSql", $params)[0]['total'] : $total;
    if (!empty($request['select_all'])) {
        $rows = gso_np_summary_query($conn, "SELECT row_id, purchase_order FROM ($base) summary$whereSql", $params);
    } else {
        $rows = gso_np_summary_query($conn, "SELECT * FROM ($base) summary$whereSql ORDER BY created_at DESC, row_id DESC LIMIT $length OFFSET $start", $params);
        if ($departmentGroups === null) {
            $departmentGroups = gso_np_summary_departments($conn, array_column($rows, 'group_key'));
        }
        foreach ($rows as &$row) {
            $row = array_merge($row, $departmentGroups[$row['group_key']] ?? ['department_code' => '', 'department_name' => '']);
            $row['row_id'] = (int)$row['row_id'];
            $row['total_amount'] = (float)$row['total_amount'];
            foreach (['purchase_order', 'fund', 'purchase_request', 'obr_number', 'supplier', 'par_ics_number', 'created_at'] as $column) {
                $row[$column] = (string)($row[$column] ?? '');
            }
            unset($row['group_key']);
        }
        unset($row);
    }
    return ['draw' => $draw, 'recordsTotal' => (int)$total, 'recordsFiltered' => (int)$filtered, 'data' => $rows];
}
