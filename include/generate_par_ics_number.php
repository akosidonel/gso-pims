<?php
// Utility: Generate a sequential PAR/ICS number for an item
// Format: YYYYMM-XNNNN where X is I for ICS and P for PAR
// Rule: Find the current max NNNN for the prefix across existing-record tables, then return next.
// Duplicates are allowed by DB; we do not enforce uniqueness.

if (!function_exists('generateParIcsNumber')) {
    function generateParIcsNumber(mysqli $conn, string $category): string {
        $ym = date('Ym');
        $cat = strtoupper(trim($category));
        $letter = (strpos($cat, 'ICS') !== false) ? 'I' : 'P';
        $prefix = $ym . '-' . $letter; // e.g., 202512-P

        // Compute max suffix across existing-record tables for this prefix
        $prefEsc = mysqli_real_escape_string($conn, $prefix);
        $max = 0;
        foreach (['par_gen_fund', 'property_sef', 'trust_fund', 'donation'] as $table) {
            $sql = "SELECT MAX(CAST(SUBSTRING(par_ics_number, LENGTH('$prefEsc') + 1) AS UNSIGNED)) AS max_sfx FROM {$table} WHERE par_ics_number LIKE CONCAT('$prefEsc','%')";
            $res = mysqli_query($conn, $sql);
            if ($res && mysqli_num_rows($res) === 1) {
                $row = mysqli_fetch_assoc($res);
                if ($row && $row['max_sfx'] !== null) { $max = max($max, (int)$row['max_sfx']); }
            }
        }

        $next = $max + 1;
        return $prefix . sprintf('%04d', $next);
    }
}

?>
