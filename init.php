<?php

    require './Autoloader.php';

    Autoloader::register_autoload();

    $start = microtime(1);
    
    $verbose = (isset($argv[1]) && (intval($argv[1]) === 1)) ? $argv[1] : 0;
    $database = new Database($verbose);

    $t = (isset($argv[2]) && ($argv[2] === 'clients')) ? $argv[2] : 'users';
    if($t === 'clients'){
        include_once "{$t}.php";
    } else {
        include_once "{$t}.php";
    }


    $start_update = microtime(1);
    if($database->verbose){
        echo "\nUPDATE {$t}\n";
    }
    $update = $database->__update(
        table: $t,
        params: $update_params,
        wqp: $where_params,
        wqp_operators: $update_operators
    );
    if($database->verbose){
        echo "time update(): ".(microtime(1) - $start_update)."[s]\n";
    }


    $start_delete = microtime(1);
    if($database->verbose){
        echo "\nDELETE {$t}\n";
    }
    $delete = $database->__delete(
        table: $t,
        params: $delete_params,
        operators: $delete_operators
    );
    if($database->verbose){
        echo "time delete(): ".(microtime(1) - $start_delete)."[s]\n";
    }


    $start_insert = microtime(1);
    if($database->verbose){
        echo "\nINSERT {$t}\n";
    }
    $insert = $database->__insert(
        table: $t,
        params: $insert_multiple_params,
        rcp: $where_params,
        rcp_operators: $select_operators
    );
    if($database->verbose){
        echo "time insert(): ".(microtime(1) - $start_insert)."[s]\n";
    }


    $start_select = microtime(1);
    if($database->verbose){
        echo "\nSELECT {$t}\n";
    }
    $select = $database->__select(
        table: $join[0]['table2'],
        select_columns: $select_columns,
        params: $where_params,
        operators: $select_operators,
        join: $join,
        limit: 5,
    );
    if($database->verbose){
        echo "time select(): ".(microtime(1) - $start_select)."[s]\n";
    }


    echo "update: \n";
    var_dump($update);
    echo "delete: \n";
    var_dump($delete);
    echo "insert: \n";
    var_dump($insert);
    echo "select: \n";
    print_r($select);
    var_dump(count($select));

    $database->clear_pdo_stmt();
    Autoloader::unregister_autoload();
    echo "Total time ".(microtime(1) - $start)."[s]\n";
?>