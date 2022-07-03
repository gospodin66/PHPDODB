<?php

    require './Autoloader.php';

    Autoloader::register_autoload();

    $t = 'clients';
    $start = microtime(1);
    
    $database = new Database;

    $insert_params = [
        'user_id' => 1,
        'ip' => '246.90.125.89',
        'port' => 1283,
        'proxy' => json_encode(['proxy' => ['p1' => '177.77.77.77']]),
        'note' => json_encode(['note' => ['note0' => 'Dummy Note.', 'note1' => 7271]]),
        'blacklist' => 0,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ];

    $insert_multiple_params = [];
    for($i = 0; $i < 30; $i++){
        $insert_multiple_params[] = $insert_params;
    }

    $update_params = [
        'user_id' => 1,
        'ip' => '1.1.1.1',
        'port' => 1111,
        'proxy' => json_encode(['proxy' => ['p1' => '1.1.1.1']]),
        'note' => json_encode(['note' => ['note0' => '', 'note1' => 0]]),
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
        'port' => '>=',
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

    $start_update = microtime(1);
    $update = $database->__update($t,$update_params,$where_params,$update_operators);
    echo "time update(): ".(microtime(1) - $start_update)."[s]\n";

    $start_delete = microtime(1);
    $delete = $database->__delete($t,$delete_params,$delete_operators);
    echo "time delete(): ".(microtime(1) - $start_delete)."[s]\n";

    $start_insert = microtime(1);
    $insert = $database->__insert($t,$insert_multiple_params,$where_params,$select_operators);
    echo "time insert(): ".(microtime(1) - $start_insert)."[s]\n";

    $start_select = microtime(1);
    $select = $database->__select($table=$join[0]['table2'],$where_params,$select_operators,$join);
    echo "time select(): ".(microtime(1) - $start_select)."[s]\n";

    echo "update: \n";
    var_dump($update);
    echo "delete: \n";
    var_dump($delete);
    echo "insert: \n";
    var_dump($insert);
    echo "select: \n";
    // print_r($select);
    var_dump(count($select));

    $database->clear_pdo_stmt();
    Autoloader::unregister_autoload();
    echo "Total time ".(microtime(1) - $start)."[s]\n";
?>