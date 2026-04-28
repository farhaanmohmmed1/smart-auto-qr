<?php
require_once '../config/config.php';
requireAdmin();

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="smart_auto_import_template.csv"');

$rows = [
    ['auto_number', 'reg_number', 'driver_name', 'phone', 'license_number', 'permit_number', 'area', 'stand'],
    ['AUTO-101', 'TS09EA9999', 'Vijay Kumar',  '9876500001', 'TS14DL2021099', 'HYD/PERMIT/2024/101', 'Ameerpet',   'Ameerpet Bus Stand'],
    ['AUTO-102', 'TS09EB8888', 'Ravi Sharma',  '9876500002', 'TS14DL2021100', 'HYD/PERMIT/2024/102', 'Kukatpally', 'KPHB Colony Stand'],
    ['AUTO-103', 'TS09EC7777', 'Srinivas Rao', '9876500003', 'TS14DL2021101', 'HYD/PERMIT/2024/103', 'LB Nagar',   'LB Nagar Stand'],
];

$out = fopen('php://output', 'w');
foreach ($rows as $row) {
    fputcsv($out, $row);
}
fclose($out);
exit;
