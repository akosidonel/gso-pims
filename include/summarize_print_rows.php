<?php
/**
 * Summarize print rows by grouping identical items that have no serial numbers.
 * Items with the same model + description (and no serial numbers) are merged
 * into a single row with aggregated qty and total_value.
 * Items with serial numbers are always kept as individual rows.
 */

/**
 * Collapse a list of property numbers into a compact range string.
 * Sequential numbers sharing the same prefix and suffix are rendered as
 * "PREFIX-NNNN-SUFFIX to PREFIX-MMMM-SUFFIX".
 * Non-sequential or unparseable numbers are listed as-is, separated by newlines.
 *
 * Works for both ICS format (e.g. "XX-XXX-XXX-XXX") and
 * PAR format (e.g. "XXXX-XX-XXX-XXXX-XXX").
 */
function collapsePropertyNumbers(array $propertyNumbers): string {
    $propertyNumbers = array_values(array_filter(array_map(function ($v) {
        return trim((string)$v);
    }, $propertyNumbers), function ($v) {
        return $v !== '';
    }));

    if (count($propertyNumbers) === 0) {
        return '';
    }

    // De-dupe while preserving first occurrence order
    $seen    = [];
    $deduped = [];
    foreach ($propertyNumbers as $pn) {
        if (isset($seen[$pn])) { continue; }
        $seen[$pn] = true;
        $deduped[] = $pn;
    }

    // Group by prefix + suffix + serial-width, then range-collapse sequential serials.
    $groups   = [];
    $unparsed = [];
    foreach ($deduped as $pn) {
        $parts = explode('-', $pn);
        if (count($parts) < 3) {
            $unparsed[] = $pn;
            continue;
        }
        $suffix      = $parts[count($parts) - 1];
        $serialSeg   = $parts[count($parts) - 2];
        $prefixParts = array_slice($parts, 0, count($parts) - 2);
        $prefix      = implode('-', $prefixParts);

        if ($prefix === '' || $suffix === '' || $serialSeg === '' || !ctype_digit($serialSeg)) {
            $unparsed[] = $pn;
            continue;
        }

        $serialWidth = strlen($serialSeg);
        $serialInt   = (int)$serialSeg;
        $groupKey    = $prefix . '|' . $suffix . '|' . $serialWidth;
        if (!isset($groups[$groupKey])) {
            $groups[$groupKey] = [
                'prefix'      => $prefix,
                'suffix'      => $suffix,
                'serialWidth' => $serialWidth,
                'serials'     => [],
            ];
        }
        $groups[$groupKey]['serials'][$serialInt] = true;
    }

    $outParts = [];

    foreach ($unparsed as $pn) {
        $outParts[] = $pn;
    }

    // Stable ordering: by prefix then suffix
    $groupList = array_values($groups);
    usort($groupList, function ($a, $b) {
        return strcmp($a['prefix'] . '|' . $a['suffix'], $b['prefix'] . '|' . $b['suffix']);
    });

    foreach ($groupList as $g) {
        $serials = array_keys($g['serials']);
        sort($serials, SORT_NUMERIC);
        if (count($serials) === 0) { continue; }

        $fmt = function (int $serial) use ($g): string {
            $seg = str_pad((string)$serial, (int)$g['serialWidth'], '0', STR_PAD_LEFT);
            return $g['prefix'] . '-' . $seg . '-' . $g['suffix'];
        };

        $rangeStart = $serials[0];
        $prev       = $serials[0];
        for ($i = 1; $i < count($serials); $i++) {
            $cur = $serials[$i];
            if ($cur === $prev + 1) {
                $prev = $cur;
                continue;
            }
            $outParts[] = ($rangeStart === $prev) ? $fmt($rangeStart) : $fmt($rangeStart) . ' to ' . $fmt($prev);
            $rangeStart = $cur;
            $prev       = $cur;
        }
        $outParts[] = ($rangeStart === $prev) ? $fmt($rangeStart) : $fmt($rangeStart) . ' to ' . $fmt($prev);
    }

    return implode("\n", $outParts);
}

function summarizePrintRows(array $rows): array {
    $grouped = [];

    foreach ($rows as $row) {
        $row['qty'] = 1;
        $row['total_value'] = (float)($row['unit_value'] ?? 0);
        $row['par_numbers'] = [$row['par_number'] ?? ''];
        $row['serial_numbers'] = [trim((string)($row['serial_number'] ?? ''))];
        $row['serial_numbers_2'] = [trim((string)($row['serial_number_2'] ?? ''))];

        $endUser = strtoupper(trim((string)($row['emp_id'] ?? ($row['user'] ?? ''))));
        $model   = strtoupper(trim((string)($row['model'] ?? '')));
        $desc    = strtoupper(trim((string)($row['description'] ?? '')));
        $unit    = strtoupper(trim((string)($row['unit'] ?? '')));
        
        // Group by user, model, description, and unit only
        // Serial numbers will be aggregated in arrays
        $key     = $endUser . '|' . $model . '|' . $desc . '|' . $unit;

        if (!isset($grouped[$key])) {
            $grouped[$key] = $row;
        } else {
            $grouped[$key]['qty']++;
            $grouped[$key]['total_value'] += (float)($row['unit_value'] ?? 0);
            $grouped[$key]['par_numbers'][] = $row['par_number'] ?? '';
            // Accumulate serial numbers from all grouped items
            $grouped[$key]['serial_numbers'][] = trim((string)($row['serial_number'] ?? ''));
            $grouped[$key]['serial_numbers_2'][] = trim((string)($row['serial_number_2'] ?? ''));
        }
    }

    return array_values($grouped);
}
