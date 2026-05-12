<?php
// Utility: Generate a unique PTR (Property Transfer Report) number.
// Format: YYMM-NNN where:
// - YYMM = last two digits of year + month (e.g., 2510 for Oct 2025)
// - NNN  = 3-digit zero-padded sequence per month (001, 002, ...)
// Uniqueness scope: items_user_history.ptr_number per month prefix (YYMM-)

if (!function_exists('generatePtrNumber')) {
    function generatePtrNumber(mysqli $conn): string {
        $ym = date('ym'); // YYMM
        $prefix = $ym . '-';

        // Determine current max suffix for this month from items_user_history
        $prefEsc = mysqli_real_escape_string($conn, $prefix);
        $sql = "SELECT MAX(CAST(SUBSTRING_INDEX(ptr_number, '-', -1) AS UNSIGNED)) AS max_sfx
                FROM items_user_history
                WHERE ptr_number LIKE CONCAT('$prefEsc','%')";
        $res = mysqli_query($conn, $sql);
        $max = 0;
        if ($res && mysqli_num_rows($res) === 1) {
            $row = mysqli_fetch_assoc($res);
            if ($row && isset($row['max_sfx']) && $row['max_sfx'] !== null) {
                $max = (int)$row['max_sfx'];
            }
        }

        // Generate next available candidate and validate uniqueness by checking existence
        $next = $max + 1;
        $tries = 0;
        while (true) {
            $suffix = sprintf('%03d', $next);
            $candidate = $prefix . $suffix; // e.g., 2510-001
            $candEsc = mysqli_real_escape_string($conn, $candidate);
            $chk = mysqli_query($conn, "SELECT 1 FROM items_user_history WHERE ptr_number='$candEsc' LIMIT 1");
            if ($chk && mysqli_num_rows($chk) === 0) {
                return $candidate;
            }
            $next++;
            $tries++;
            if ($tries > 10000) {
                // Extreme fallback: append seconds to avoid blocking
                return $prefix . date('His');
            }
        }
    }
}

?>
