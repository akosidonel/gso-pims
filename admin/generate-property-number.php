<?php
include_once('../config/session.php');

$category = $_POST['category'] ?? '';
$year = $_POST['year'] ?? '';
$account_code = strtoupper(trim((string)($_POST['account_code'] ?? '')));
$dept = $_POST['dept'] ?? '';
$fund = $_POST['fund'] ?? '';
$year_short = substr($year, -2);

// Sub account mapping
$par_sub = [
    '1-07-05-030' => '05-030',// IT Equipment
    '1-07-05-020' => '05-020',// Office Equipment
    '1-07-06-010' => '06-010',// Motor Vehicle
    '1-07-07-020' => '07-020',// Books
    '1-07-07-010' => '07-010',// Furniture and Fixtures
    '1-07-05-070' => '05-070',// Communication Equipment
    '1-07-05-990' => '05-990',// Other Machineries and Equipment
    '1-07-05-090' => '05-090',// Disaster, Response and Rescue Equipment
    '1-07-05-080' => '05-080',// Construction and Heavy Equipment
    '1-07-05-100' => '05-100',// Military and Police Equipment
    '1-09-01-020' => '01-020',// Computer Software
    '1-07-99-990' => '99-990',// Other Property, Plant and Equipment
    '1-07-05-110' => '05-110',// Medical Equipment
    '5-02-03-080' => '03-080',// Medical, Dental and Laboratory Supplies Expenses
    '1-07-05-130' => '05-130',// Sports Equipment
    '1-07-05-140' => '05-140',// Technical and Scientific Equipment
    '1-07-06-040' => '06-040',// Watercraft
    '5-02-03-990' => '03-990',// Other Supplies
    '5-02-03-010' => '03-010',// Office Supplies Expenses
    '5-02-99-990' => '99-990' // Other Maintenance and Operating Expenses
];
$ics_sub = [
    '1-07-05-030' => '223',// IT Equipment
    '1-07-05-020' => '221',// Office Equipment
    '1-07-06-010' => '241',// Motor Vehicle
    '1-07-07-020' => '224',// Books
    '1-07-07-010' => '222',// Furniture and Fixtures
    '1-07-05-070' => '229',// Communication Equipment
    '1-07-05-990' => '240',// Other Machineries and Equipment
    '1-07-05-090' => '231',// Disaster, Response and Rescue Equipment
    '1-07-05-080' => '230',// Construction and Heavy Equipment
    '1-07-05-100' => '234',// Military and Police Equipment
    '1-09-01-020' => '323',// Computer Software
    '1-07-99-990' => '250',// Other Property, Plant and Equipment
    '1-07-05-110' => '233',// Medical Equipment
    '5-02-03-080' => '760',// Medical, Dental and Laboratory Supplies Expenses
    '1-07-05-130' => '235',// Sports Equipment
    '1-07-05-140' => '236', // Technical and Scientific Equipment
    '1-07-06-040' => '244',// Watercraft
    '5-02-03-990' => '878',// Other Supplies
    '5-02-03-010' => '755', // Office Supplies Expenses
    '5-02-99-990' => '779' // Other Maintenance and Operating Expenses
];

// Department code mapping
$dept_map = [
    '04' => '04', // Accounting Office
    '47' => '47', // General Services Office
    '55' => '55', // Library
];

$dept_code = $dept_map[$dept] ?? $dept;

// Decide target table/column based on fund (General vs SEF)
$is_sef = (strtoupper(trim($fund)) === 'SPECIAL EDUCATION FUND');

if ($category == 'PAR') {
    $prefix = $year;
    $sub = $par_sub[$account_code] ?? '';
    $seq_length = 4;
    $table = $is_sef ? 'property_sef' : 'par_gen_fund';
    $col = $is_sef ? 'property_number' : 'par_number';
} elseif ($category == 'ICS') {
    $prefix = $year_short;
    $sub = $ics_sub[$account_code] ?? '';
    $seq_length = 3;
    $table = $is_sef ? 'property_sef' : 'par_gen_fund';
    $col = $is_sef ? 'property_number' : 'par_number';
} else {
    echo json_encode(['success' => false]);
    exit;
}

// Build property number pattern for SQL LIKE
$pattern = "$prefix-$sub-%-$dept_code";

// Get the last sequence used
$stmt = $conn->prepare("SELECT $col FROM $table WHERE $col LIKE ? ORDER BY $col DESC LIMIT 1");
$stmt->bind_param('s', $pattern);
$stmt->execute();
$stmt->bind_result($last_prop);
$stmt->fetch();
$stmt->close();

if ($last_prop) {
    $parts = explode('-', $last_prop);
    $seq = (int)$parts[count($parts)-2] + 1;
} else {
    $seq = 1;
}
$seq_str = str_pad($seq, $seq_length, '0', STR_PAD_LEFT);

if ($category == 'PAR') {
    $property_number = "$year-$sub-$seq_str-$dept_code";
} else {
    $property_number = "$year_short-$sub-$seq_str-$dept_code";
}

echo json_encode(['success' => true, 'pr_number' => $property_number]);

?>
