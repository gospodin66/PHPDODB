<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('CET');

class Database extends Filter {
    
    private static string $host;
    private static int $port;
    private static string $user;
    private static string $password;
    private static string $database;
    private static string $charset;
    private static string $dsn;

    private const BCRYPT_COST = 10;
    private const PASSWORD_POSSIBLE_COLUMNS = ['password','pass','passwd','pwd'];

    private $stmt;
    private $pdo;

    /**
     * 
     * @return resource => new PDO instance
     */
    public function __construct(bool $verbose) {
        $this->verbose = $verbose;
        self::$host = readenv('DB_HOST');
        self::$port = intval(readenv('DB_PORT'));
        self::$user = readenv('DB_USER');
        self::$password = readenv('DB_PASSWORD');
        self::$database = readenv('DB_DATABASE');
        self::$charset = readenv('DB_CHARSET');
        self::$dsn = 'mysql:host='.self::$host.';
                            dbname='.self::$database.';
                            charset='.self::$charset;
        $this->init_db();
    }

    /**
     * 
     */
    private function init_db() : void {
        try {
            $this->pdo = new PDO(
                'mysql:host='.self::$host.
                     ';dbname='.self::$database.
                     ';charset='.self::$charset,
                self::$user,
                self::$password
            );
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);   
            $this->pdo->setAttribute(PDO::ATTR_ORACLE_NULLS, PDO::NULL_EMPTY_STRING);   
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);   
            $this->pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, true);   
        } catch (PDOException $e) {
            echo "Database error [{$e->getCode()}]: connection failed\n".
                          "err msg: {$e->getMessage()} on line: {$e->getLine()}\n".
                          "stack trace: {$e->getTraceAsString()}\n\n";
            $this->clear_pdo_stmt();
        }
    }

    /**
     * @param table  => table to select from
     * @param select_columns => indexed array of columns-to-select 
     * @param params => assoc. array of params to validate ('WHERE' params)
     * @param operators => assoc. array of corresponding operators where (key = column)
     * @param join   => array of JOIN params => default empty array
     * @param limit  => limit query => default 0
     * 
     * @return array => array of matching records
     */
    public function __select(
        string $table,
        ?array $select_columns = [],
        ?array $params         = [],
        ?array $operators      = [],
        ?array $join           = [],
        ?int $limit            = 0,
    ) : array
    {
        if( ! empty($params)){
            if(count($params) !== count($operators)){
                echo "select-error: params len doesn't match operators len.\n";
                return [];
            }
            if( ! empty($params) && ($params_opts = $this->__initialize_opts($params, $this->verbose)) === []){
                echo "select-error: init opts failed.\n";
                return [];
            }
            if( ! empty($params_opts) && ($params = $this->__filter_vars($params, $params_opts)) === []){
                echo "select-error: filtering vars failed.\n";
                return [];
            }
        }

        $limit = is_numeric($limit) ? intval($limit) : 0;
        $table = filter_var($table, FILTER_UNSAFE_RAW);
        $select_where_params = '';

        if( ! empty($params) && ! empty($operators)){
            $params_opts_copy = $params_opts;
            $opt_last = count($params_opts) -1;
            /**
             * craft query params strings
             */
            foreach($params as $k => $param){
                foreach($params_opts as $_k => $opt){
                    if($k === $opt['param']){
                        unset($params_opts[$_k]);                        
                        $select_where_params .= (strtoupper($operators[$k]) === 'LIKE')
                                              ? "{$k} {$operators[$k]} CONCAT('%', :{$k}, '%')"
                                              : "{$k}{$operators[$k]}:{$k}";
                        $select_where_params .= ($_k !== $opt_last ? ' AND ' : '');
                        break;
                    }
                }
            }
        }
        
        if(is_array($join) && !empty($join)){ $joinstr = $this->joins($join); }

        $sql = "SELECT "
                .($select_columns !== [] ? implode(',', $select_columns) : '*')
                ." FROM {$table}"
                .($joinstr ?? '')
                .( ! empty($select_where_params) ? " WHERE $select_where_params" : '')
                .($limit > 1 ? " LIMIT {$limit}" : '');
    
        if($this->verbose){
            echo "QUERY: {$sql}\n";
        }
        /**
         * re-initialize database if neccessary
         */
        if($this->pdo === null){
            $this->init_db();
        }

        try {
            $this->stmt = $this->pdo->prepare($sql);
            $this->bind_params(params: $params, params_opts: ($params_opts_copy ?? $params_opts ?? []));

            if($this->verbose){
                echo "[+] EXECUTING QUERY..\n";
            }

            $select_res = $this->stmt->execute();
            $result = $select_res ? $this->stmt->fetchAll() : 0;
            
            if($this->verbose){
                echo "[+] QUERY SUCCESSFULY EXECUTED!\n";
            }
        } catch (PDOException $e) {
            echo "Database error [{$e->getCode()}]: select failed\n".
                          "err msg: {$e->getMessage()} on line: {$e->getLine()}\n".
                          "stack trace: {$e->getTraceAsString()}\n\n";
            $this->clear_pdo_stmt();
            return [];
        }
    
        $this->stmt = null;
        return $result;
    }

    /**
     * @param table         => table to select from
     * @param params        => assoc. array of params to insert
     * @param rcp           => assoc. array of ('record check params')
     * @param rcp_operators => assoc. array of corresponding operators where (key = column) 
     * 
     * @return int          => number of successful inserts
     */
    public function __insert(
        string $table,
        array $params,
        ?array $rcp           = [],
        ?array $rcp_operators = [],
    ) : int
    {
        $successful = 0;
        $table = filter_var($table, FILTER_UNSAFE_RAW);

        /**
         * if params is array of arrays of params => multiple inserts
         */
        if(isset($params[0]) && is_array($params[0])){            
            /**
             * single array of options for all params => same for all types
             */
            if( ! empty($params[0]) && ($params_opts = $this->__initialize_opts($params[0], $this->verbose)) === []){
                echo "insert-error: init opts failed.\n";
                return 0;
            }

            foreach($params as $key => &$p){
                if(($p = $this->__filter_vars($p, $params_opts)) === []){
                    echo "insert-error: filtering vars failed.\n";
                    return 0;
                }
            }
            unset($p);

            $table_params = implode(',', array_keys($params[0]));
            $insert_vals_str = '';
            $_seq = '';
            $vals = '';
            
            foreach ($params as $key => $param) {
                $_seq .= '_';
                /**
                 * switch quotesfrom " to '
                 */
                foreach ($param as $p_key => &$_p) {
                    $_p = '\''.$_p.'\'';
                }
                unset($_p);

                $vals = str_replace(',',",:{$_seq}",$table_params);
                /**
                 * first element does not get into str_replace
                 */
                $vals_first = substr($vals, 0, strpos($vals, ','));
                $vals = ":{$_seq}{$vals_first}".strstr($vals, ',');

                $insert_vals_str .= "({$vals}),";
            }
            /**
             * final chunk of 'VALUES({values})'
             */
            $insert_vals_query = substr($insert_vals_str, 0, strlen($insert_vals_str) -1);
        }
        /**
         * params is a single array => single insert
         */
        else {

            if( ! empty($params) && ($params_opts = $this->__initialize_opts($params, $this->verbose)) === []){
                echo "insert-error: init opts failed.\n";
                return 0;
            }
            if( ! empty($params_opts) && ($params = $this->__filter_vars($params, $params_opts)) === []){
                echo "insert-error: filtering vars failed.\n";
                return 0;
            }

            $table_params = implode(',', array_keys($params));
    
            $vals = str_replace(',',',:',$table_params);
            $vals = substr($vals, 0, 0). ':' . substr($vals, 0);
            /**
             * final chunk of 'VALUES({values})'
             */
            $insert_vals_query = "({$vals})";
        }

        /**
         * check if record already exists
         */
        if( ! empty($rcp) && ! empty($rcp_operators)){
            if((count($rcp) !== count($rcp_operators))){
                echo "insert-error: select: rcp params len doesn't match rcp operators len.\n";
                return 0;
            }
            if( ! empty($rcp) && ($rcparams_opts = $this->__initialize_opts($rcp, $this->verbose)) === []){
                echo "insert-error: init rcp opts failed.\n";
                return 0;
            }
            if( ! empty($rcparams_opts) && ($rcp = $this->__filter_vars($rcp, $rcparams_opts)) === []){
                echo "insert-error: filtering rcp vars failed.\n";
                return 0;
            }

            $existing_record = $this->__select(table: $table, params: $rcp, operators: $rcp_operators, limit: 1);

            if( ! empty($existing_record)){
                echo "insert-error: record already exists.\n";
                print_r($existing_record);
                $this->clear_pdo_stmt();
                return 0;
            }
        }

        $sql = "INSERT INTO {$table} ({$table_params}) VALUES {$insert_vals_query}";
        if($this->verbose){
            echo "QUERY: {$sql}\n";
        }
        /**
         * re-initialize database if neccessary
         */
        if($this->pdo === null){
            $this->init_db();
        }

        try {
            /** Begin a transaction, turning off autocommit */
            $this->pdo->beginTransaction();
            $this->stmt = $this->pdo->prepare($sql);
            
            $_seq = '';
            $_po = [];
            $_qp = [];

            /**
             * only if multiple inserts:
             * => modify keys to diff params placeholders in prepared statements
             */
            if(isset($params[0]) && is_array($params[0])){
                foreach($params as $pk => $pv){
                    $_seq .= '_';
    
                    foreach($pv as $pvk => $pvv){
                        $_qp[$pk][":{$_seq}{$pvk}"] = $params[$pk][$pvk];
                    }
    
                    foreach($params_opts as $key => $opt){
                        $_po[$pk][$key] = [
                           'param' =>  ":{$_seq}{$opt['param']}",
                           'pdo_param' => $opt['pdo_param'],
                           'filter' => $opt['filter']
                        ];
                    }
    
                    unset($params[$pk]);
                }
            } else {
                $_po = $params_opts;
                $_qp = $params;
            }
            
            $this->bind_params(params: $_qp, params_opts: $_po);

            if($this->verbose){
                echo "[+] EXECUTING QUERY..\n";
            }

            $res = $this->stmt->execute();

            if($res){
                $successful = (isset($_qp[0]) && is_array($_qp[0])) ? count($_qp) : 1;
            }
            
            /** Commit the changes */
            if( ! $this->pdo->commit()){
                echo "Error on commiting insert transaction\nExecuting rollback";
                $this->rollback_transaction();
                return 0;
            }

            if($this->verbose){
                echo "[+] QUERY SUCCESSFULY EXECUTED!\n";
            }

        } catch (PDOException $e) {
            echo "Database error [{$e->getCode()}]: insert failed\n".
                          "err msg: {$e->getMessage()} on line: {$e->getLine()}\n".
                          "stack trace: {$e->getTraceAsString()}\n\n";
            $this->rollback_transaction();
            $this->clear_pdo_stmt();
            return 0;
        }

        $this->stmt = null;
        return $successful;
    }

    /**
     * @param table         => table to select from
     * @param params        => assoc. array of params to update
     * @param wqp           => assoc. array of 'WHERE' query params
     * @param wqp_operators => assoc. array of corresponding operators where (key = column)
     * @param limit         => limit query => default 0
     * 
     * @return int          => num of updated rows
     */
    public function __update(
        string $table,
        array $params,
        ?array $wqp           = [],
        ?array $wqp_operators = [],
        ?int $limit           = 0,
    ) : int
    {
        if( ! empty($params) && ($params_opts = $this->__initialize_opts($params, $this->verbose)) === []){
            echo "update-error: init opts failed.\n";
            return 0;
        }
        if( ! empty($params_opts) && ($params = $this->__filter_vars($params, $params_opts[0])) === []){
            echo "update-error: filtering vars failed.\n";
            return 0;
        }

        if( ! empty($wqp) && ! empty($wqp_operators)){

            if(count($wqp) !== count($wqp_operators)){
                echo "update-error: params len doesn't match operators len.\n";
                return 0;
            }
            if( ! empty($wqp) && ($wqp_opts = $this->__initialize_opts($wqp, $this->verbose)) === []){
                echo "update-error: init opts failed.\n";
                return 0;
            }
            if( ! empty($wqp_opts) && ($wqp = $this->__filter_vars($wqp, $wqp_opts)) === []){
                echo "update-error: filtering vars failed.\n";
                return 0;
            }

            $w_last = count($wqp) -1;
            $str_wqp = '';

            /** use underscore '_' to differentiate same keys */
            foreach($wqp as $k => $w){
                $numeric_k = array_search($k, array_keys($wqp));
                $str_wqp .= (strtoupper($wqp_operators[$k]) === 'LIKE')
                             ? "$k {$wqp_operators[$k]} CONCAT('%', :_{$k}, '%')"
                             : "$k{$wqp_operators[$k]}:_{$k}";
                $str_wqp .= ($numeric_k !== $w_last ? ' AND ' : '');

            }

        }

        $table = filter_var($table, FILTER_UNSAFE_RAW);
        $set_params = '';
        $p_last = count($params) -1;

        foreach($params as $k => $p){
            $numeric_k = array_search($k, array_keys($params));
            $set_params .= "{$k}=:{$k}".($numeric_k !== $p_last ? ',' : '');
        }
        
        $date = date("Y-m-d H:i:s");
        $sql = "UPDATE $table SET $set_params".
               (isset($str_wqp) && ! empty($str_wqp) ? " WHERE $str_wqp" : '').
               ($limit > 1 ? ' LIMIT '.$limit : '');
        if($this->verbose){
            echo "QUERY: {$sql}\n";
        }
        /**
         * re-initialize database if neccessary
         */
        if($this->pdo === null){
            $this->init_db();
        }

        try {
            /* Begin a transaction, turning off autocommit */
            $this->pdo->beginTransaction();
            $this->stmt = $this->pdo->prepare($sql);

            if( ! empty($wqp) && ! empty($wqp_opts)){
                $_wqp = [];
                foreach($wqp as $k => $wqparam){
                    $_wqp[":_{$k}"] = $wqp[$k];
                    unset($wqp[$k]);
                }
                foreach($wqp_opts as $key => &$wqopt){
                    $wqopt['param'] = ':_'.$wqopt['param'];
                }
                unset($wqopt);
                $this->bind_params(params: array_merge($params, $_wqp), params_opts: array_merge($params_opts, $wqp_opts));
            } else {
                $this->bind_params(params: $params, params_opts: $params_opts);
            }
            if($this->verbose){
                echo "[+] EXECUTING QUERY..\n";
            }

            $res = $this->stmt->execute();

            if($res){ 
                $ret_rows = $this->stmt->rowCount();
            }
            
            /* Commit the changes */
            if( ! $this->pdo->commit()){
                echo "Error on commiting update transaction\nExecuting rollback";
                $this->rollback_transaction();
                return 0;
            }

            if($this->verbose){
                echo "[+] QUERY SUCCESSFULY EXECUTED!\n";
            }
            
        } catch (PDOException $e) {
            echo "Database error [{$e->getCode()}]: update failed\n".
                          "err msg: {$e->getMessage()} on line: {$e->getLine()}\n".
                          "stack trace: {$e->getTraceAsString()}\n\n";
            $this->rollback_transaction();
            $this->clear_pdo_stmt();
            return 0;
        }

        $this->stmt = null;
        return $ret_rows;
    }

    /**
     * @param table     => table to select from
     * @param params    => assoc. array of params to update
     * @param operators => assoc. array of corresponding operators where (key = column)
     * 
     * @return int      => num of deleted rows
     */
    public function __delete(
        string $table,
        ?array $params    = [],
        ?array $operators = []
    ) : int
    {
        $del_params = '';

        if( ! empty($params)){

            if(count($params) !== count($operators)){
                return 0;
            }
            if( ! empty($params) && ($params_opts = $this->__initialize_opts($params, $this->verbose)) === []){
                echo "delete-error: init opts failed.\n";
                return 0;
            }
            if( ! empty($params_opts) && ($params = $this->__filter_vars($params, $params_opts)) === []){
                echo "delete-error: filtering vars failed.\n";
                return 0;
            }

            $opts_last = count($params_opts) -1;

            foreach($params as $k => $param){
                foreach($params_opts as $opt){
                    if($k === $opt['param']){
                        $numeric_k = array_search($k, array_keys($params));
                        $del_params .= (strtoupper($operators[$k]) === 'LIKE')
                                     ? "{$k} {$operators[$k]} CONCAT('%', :{$k}, '%')"
                                     : "{$k}{$operators[$k]}:{$k}";
                        $del_params .= ($numeric_k !== $opts_last ? ' AND ' : '');
                        break;
                    }
                }
            }
            
        } else {
            echo "no arguments provided to delete: deleting all..\n";
        }

        $table = filter_var($table, FILTER_UNSAFE_RAW);
        

        $date = date("Y-m-d H:i:s");
        $sql = "DELETE FROM $table".(!empty($params) && !empty($params_opts) ? " WHERE {$del_params}" : '');
        if($this->verbose){
            echo "QUERY: {$sql}\n";
        }
        /**
         * re-initialize database if neccessary
         */
        if($this->pdo === null){
            $this->init_db();
        }

        try {
            /**  Begin a transaction, turning off autocommit */
            $this->pdo->beginTransaction();
            $this->stmt = $this->pdo->prepare($sql);
            if( ! empty($params) && ! empty($params_opts)){
                $this->bind_params(params: $params, params_opts: $params_opts);
            }
            if($this->verbose){
                echo "[+] EXECUTING QUERY..\n";
            }

            $res = $this->stmt->execute();

            if($res){ 
                $ret_rows = $this->stmt->rowCount();
            }
            /* Commit the changes */
            if( ! $this->pdo->commit()){
                echo "Error on commiting delete transaction\nExecuting rollback";
                $this->rollback_transaction();
                return 0;
            }

            if($this->verbose){
                echo "[+] QUERY SUCCESSFULY EXECUTED!\n";
            }
            
        } catch (PDOException $e) {
            echo "Database error [{$e->getCode()}]: delete failed\n".
                          "err msg: {$e->getMessage()} on line: {$e->getLine()}\n".
                          "stack trace: {$e->getTraceAsString()}\n\n";
            $this->rollback_transaction();
            $this->clear_pdo_stmt();
            return 0;
        }

        $this->stmt = null;
        return $ret_rows;
    }
    
    /**
     * @param join => assoc array of join params => type,operator,table1,param1,table2,param2
     * 
     * @return string => JOIN query part
     */
    private function joins(array $join) : string {
        $joinstr = '';
        $f = [];
        if(is_array($join) && !empty($join))
        {
            foreach($join as &$j)
            {
                $keys = array_keys($j);
                foreach($keys as $key){ $f[$key] = FILTER_UNSAFE_RAW; }
                $j = filter_var_array($j, $f);
                $joinstr .= (!empty($j['type']) ? ' '.strtoupper($j['type']).' ' : ' ').
                            "JOIN {$j['table1']} ON ".
                            "{$j['table1']}.{$j['param1']}".
                            "{$j['operator']}".
                            "{$j['table2']}.{$j['param2']}";
            }
            unset($j);
        }
        return $joinstr;
    }

    /**
     * => bind values to parameters
     * @param params => assoc. array params to bind (can be array of arrays)
     * @param params_opts => assoc. array of corresponding opts & filters
     * 
     * @return void
     */
    private function bind_params(array $params, array $params_opts) : void {
        // $this->loop_bind_params($params, $params_opts);

        if(isset($params[0]) && is_array($params[0])){
            foreach ($params as $p_index => $p) {
                $this->loop_bind_params(params: $p, params_opts: $params_opts);
            }
            unset($p);
        }
        else {
            $this->loop_bind_params(params: $params, params_opts: $params_opts);
        }
    }

    /**
     * => helper fnc
     * @param params => assoc. array params to bind (can be array of arrays)
     * @param params_opts => assoc. array of corresponding opts & filters
     * 
     * @return void
     */
    private function loop_bind_params(array $params, array $params_opts) : void {

        foreach($params as $param_name => &$param){
            
            if(in_array($param_name, self::PASSWORD_POSSIBLE_COLUMNS)){
                $hashed_pass = password_hash($param, PASSWORD_BCRYPT, ["cost" => self::BCRYPT_COST]);
                if($this->verbose){
                    echo "BINDING PASSWORD PARAM: {$param_name}\n";
                }
                $this->stmt->bindParam(":{$param_name}", $hashed_pass, PDO::PARAM_STR);
                unset($params[$param_name]);
                continue;
            }

            if(isset($params_opts[0]) && is_array($params_opts[0]) && is_numeric(key($params_opts[0]))){
                foreach ($params_opts as $pok => $param_opt) {
                    foreach($param_opt as $opt){
                        if($param_name === $opt['param']){
                            if($this->verbose){
                                echo "BINDING PARAM: {$param_name}\n";
                            }
                            $this->stmt->bindParam("{$param_name}", $param, $opt['pdo_param']);
                            break 2;
                        }
                    }
                }
            } else {
                foreach($params_opts as $opt){
                    if($param_name === $opt['param']){
                        if($this->verbose){
                            echo "BINDING PARAM: {$param_name}\n";
                        }
                        $this->stmt->bindParam("{$param_name}", $param, $opt['pdo_param']);
                        break;
                    }
                }
            }
        }
    }


    /**
     * 
     * => rollbacks a transaction
     * 
     * @return void
     */
    private function rollback_transaction() : bool {
        return $this->pdo->rollback();
    }


    /**
     * => clears pdo and stmt objects
     * 
     * @return void
     */
    public function clear_pdo_stmt() : void {
        $this->pdo = null;
        $this->stmt = null;
    }

    /**
     * => prints pdo|stmt|pdo-obj error|code
     * 
     * @return void
     */
    public function __debug() : void {
        echo "DEBUG: \r\n";
        echo "\$pdo obj: \r\n";
        var_dump($this->pdo);
        echo "\$stmt obj: \r\n";
        var_dump($this->stmt);
        if($this->pdo !== null){
            echo "PDO errors: \r\n";
            var_dump(
                $this->pdo->errorCode(),
                $this->pdo->errorInfo()
            );
        }
        if($this->stmt !== null){
            echo "stmt params: \r\n";
            var_dump($this->stmt->debugDumpParams());
        }
        echo "\r\n";
    }

}
?>