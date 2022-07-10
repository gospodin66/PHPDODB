<?php

$insert_params = [
    'user_id' => 1,
    'ip' => '246.90.125.89',
    'port' => 1283,
    'proxy' => json_encode(['proxy' => ['p1' => '177.77.77.77']]),
    'note' => json_encode(['note' => ['note0' => 'Dummy Note.']]),
    'blacklist' => 0,
    'created_at' => date('Y-m-d H:i:s'),
    'updated_at' => date('Y-m-d H:i:s'),
];

$insert_multiple_params = [];
for($i = 0; $i < 5; $i++){
    $insert_multiple_params[] = $insert_params;
}

$update_params = [
    'user_id' => 1,
    'ip' => '1.1.1.1',
    'port' => 1111,
    'proxy' => json_encode(['proxy' => ['p1' => '1.1.1.1']]),
    'note' => json_encode(['note' => ['note0' => 'Altered Note.']]),
    'blacklist' => 1,
    'created_at' => date('Y-m-d H:i:s'),
    'updated_at' => date('Y-m-d H:i:s'),
];

/**
 *
 * params for 'where' query in mysql:
 * -> update(record-to-update)
 * -> insert(if-record-exists)
 * -> select(where-selector)
 * 
 */
$where_params = [
    'ip' => '246.90.125.89',
    'port' => 1283,
];
$select_operators = [
    'ip' => '=',
    'port' => '=',
];
$update_operators = [
    'ip' => '=',
    'port' => '=',
];
// delete record
$delete_params = [
    'ip' => '1.1.1.1',
    'port' => 1111,
];
$delete_operators = [
    'ip' => '=',
    'port' => '=',
]; 

$join = [
    [
        'type' => 'INNER',
        'operator' => '=',
        'table1' => 'users',
        'param1' => 'id',
        'table2' => 'clients',
        'param2' => 'user_id',
    ],
];
?>