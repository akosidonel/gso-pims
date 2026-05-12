<?php
// Utility: Generate a sequential PAR/ICS number for an item
// Format: YYYYMM-XNNNN where X is I for ICS and P for PAR
// Rule: Find the current max NNNN for the prefix across GF and SEF, then return next.
// Duplicates are allowed by DB; we do not enforce uniqueness.

if (!function_exists('generateParIcsNumber')) {
    function generateParIcsNumber(mysqli $conn, string $category): string {
        $ym = date('Ym');
        $cat = strtoupper(trim($category));
        $letter = (strpos($cat, 'ICS') !== false) ? 'I' : 'P';
        $prefix = $ym . '-' . $letter; // e.g., 202512-P

        // Compute max suffix across both tables for this prefix
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

        $next = $max + 1;
        return $prefix . sprintf('%04d', $next);
    }
}

?>
