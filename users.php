<?php

$insert_params = [
    'role_id' => 1,
    'username' => 'tester',
    'email' => 'test_user@email.com',
    'password' => 'tester123',
    'config' => json_encode(['config' => ['conf0' => 'ls -ltr']]),
    'active' => 0,
    'remember_token' => bin2hex(openssl_random_pseudo_bytes(16)),
    'avatar' => 'image.png',
    'email_verified_at' => date('Y-m-d H:i:s'),
    'created_at' => date('Y-m-d H:i:s'),
    'updated_at' => date('Y-m-d H:i:s'),
];


/**
 * 
 * AKO KOMBINIRAMO 2 ZNAKA: _ i -
 * znaci da bi mogli imati 2^119 kombinacija???
 * 
 */

$insert_multiple_params = [];
for($i = 0; $i < 119; $i++){
    $insert_params['username'] = $insert_params['username'].$i;
    $insert_multiple_params[] = $insert_params;
}

$update_params = [
    'role_id' => 2 ,
    'username' => 'test',
    'email' => 'test@test.test',
    'password' => 'test',
    'config' => json_encode(['config' => ['conf0' => 'test']]),
    'active' => 1,
    'remember_token' => 'N/A',
    'avatar' => 'test.test',
    'email_verified_at' => date('Y-m-d H:i:s'),
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
    'username' => 'test',
    // 'email' => 'test_user@email.com',
];
$select_operators = [
    'username' => 'LIKE',
    // 'email' => '=',
];
$update_operators = [
    'username' => 'LIKE',
    // 'email' => '=',
];
// delete record
$delete_params = [
    'username' => 'test',
    // 'email' => 'test@test.test',
];
$delete_operators = [
    'username' => 'LIKE',
    // 'email' => '=',
]; 
$select_columns = [
    'users.id',
    'users.username',
    'users.email',
    'users.created_at',
];
$join = [
    [
        'type' => 'INNER',
        'operator' => '=',
        'table1' => 'roles',
        'param1' => 'id',
        'table2' => 'users',
        'param2' => 'role_id',
    ],
];
?>