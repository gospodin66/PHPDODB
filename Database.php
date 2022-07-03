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

    private $stmt;
    private $pdo;

    /**
     * 
     * @return resource => new PDO instance
     */
    public function __construct() {
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
     * @param params => assoc. array of params to validate
     * @param join   => array of JOIN params => default empty array
     * @param limit  => limit query => default 0
     * 
     * @return array => array of matching records
     */
    public function __select(string $table, array $params = [], array $join = [], int $limit = 0) : array { 
        if(!empty($params)){
            $params_opts = $this->__initialize_opts($params);
            if(empty($params_opts)){
                return [];
            }
            if(!empty($params_opts)){
                $params = $this->__filter_vars($params, $params_opts[0]);
                if(empty($params)){
                    return [];
                }
            } 
        }

        $limit = is_numeric($limit) ? intval($limit) : 0;
        $table = filter_var($table, FILTER_UNSAFE_RAW);
        $select_params = '';

        if( ! empty($params)){
            $params_opts_copy = $params_opts;
            $opt_last = count($params_opts) -1;
            /**
             * craft query params strings
             */
            foreach($params as $k => $param){
                foreach($params_opts as $_k => $opt){
                    if($k === $opt['param']){
                        unset($params_opts[$_k]);
                        $select_params .= $opt['param'].'=:'.$opt['param'].
                                         ($_k !== $opt_last ? ' AND ' : '');
                        break;
                    }
                }
            }
        }
        
        if(is_array($join) && !empty($join)){ $joinstr = $this->joins($join); }

        $sql = "SELECT * FROM $table"
                .($joinstr ?? '')
                .(!empty($select_params) ? " WHERE $select_params" : '')
                .($limit > 1 ? " LIMIT {$limit}" : '');

        /**
         * re-initialize database if neccessary
         */
        if($this->pdo === null){
            $this->init_db();
        }

        try {
            $this->stmt = $this->pdo->prepare($sql);
            $this->bind_params($params=$params, $params_opts=($params_opts_copy ?? $params_opts ?? []));
            $this->stmt->execute();
            $result = $this->stmt->fetchAll();
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
     * @param table  => table to select from
     * @param params => assoc. array of params to insert
     * @param rcp    => assoc. array of ('record check params')
     * 
     * @return successful => number of successful inserts
     */
    public function __insert(string $table, array $params, array $rcp = []) : int {

        $successful = 0;
        
        /**
         * - if params contains arrays of params => multiple inserts
         */
        if(isset($params[0]) && is_array($params[0]) && is_numeric(key($params))){

            $vals_arr = $vals = [];
            
            /**
             * single array of options for all params => same for all types
             */
            $params_opts = $this->__initialize_opts($params);

            foreach ($params as $key => $p) {
                $p = $this->__filter_vars($p, $params_opts);
                if(!empty($params_opts) && empty($p)){
                    return 0;
                }
                $vals_arr[] = $p;
            }

            $rcparams_opts = $this->__initialize_opts($rcp);
            if(!empty($rcp) && empty($rcparams_opts)){
                echo  "** running without record-check-params\n";
            }

            $rcp = $this->__filter_vars($rcp, $rcparams_opts);
            if(!empty($rcparams_opts) && empty($rcp)){
                echo  "** running without record-check-params\n";
            }
            
            $table = filter_var($table, FILTER_UNSAFE_RAW);
            $table_params = implode(',', array_keys($params[0]));
        
            $vals = str_replace(',',',:',$table_params);
            $vals = substr($vals, 0, 0). ':' . substr($vals, 0);

            $vals_str = "";
            foreach ($vals_arr as $key => $v) {
                foreach ($v as $key => &$_v) {
                    $_v = '\''.$_v.'\'';
                }
                unset($_v);

                $val = implode(',', $v);
                $vals_str .= " ({$val}),";
            }

            $params = $vals_arr;
            
            $vals_str = substr($vals_str, 0, strlen($vals_str) -1);
            $vals_query = " VALUES $vals_str";
        }
        /**
         * if params is a single array => single insert
         */
        else {

            $params_opts = $this->__initialize_opts($params);
            if(empty($params_opts)){
                return 0;
            }

            if(!empty($params_opts)){
                $params = $this->__filter_vars($params, $params_opts[0]);
                if(empty($params)){
                    return 0;
                }
            } 

            if(!empty($rcp)){
                $rcparams_opts = $this->__initialize_opts($rcp);
                if(empty($rcparams_opts)){
                    return 0;
                }
                if(!empty($rcparams_opts)){
                    $rcp = $this->__filter_vars($rcp, $rcparams_opts);
                    if(empty($rcp)){
                        return 0;
                    }
                }
            }

            $table = filter_var($table, FILTER_UNSAFE_RAW);
            $table_params = implode(',', array_keys($params));
    
            $vals = str_replace(',',',:',$table_params);
            $vals = substr($vals, 0, 0). ':' . substr($vals, 0);

            $vals_query = " VALUES($vals)";
        }
    
        if(!empty($rcp)){

            $existing_record = $this->__select($table, $rcp);

            if(!empty($existing_record)){
                echo "Record already exists\n";
                print_r($existing_record);
                $this->clear_pdo_stmt();
                return 0;
            }
        }

        $date = date("Y-m-d H:i:s");
        $sql = "INSERT INTO $table($table_params){$vals_query}";

        /**
         * re-initialize database if neccessary
         */
        if($this->pdo === null){
            $this->init_db();
        }

        try {
            $this->stmt = $this->pdo->prepare($sql);
            $this->bind_params($params, $params_opts);
            $res = $this->stmt->execute();
            if($res){
                $successful = (isset($params[0]) && is_array($params[0])) ? count($params) : 1;
            }

        } catch (PDOException $e) {
            echo "Database error [{$e->getCode()}]: insert failed\n".
                          "err msg: {$e->getMessage()} on line: {$e->getLine()}\n".
                          "stack trace: {$e->getTraceAsString()}\n\n";
            $this->clear_pdo_stmt();
            return 0;
        }

        $this->stmt = null;
        return $successful;
    }


    /**
     * @param table  => table to select from
     * @param params => assoc. array of params to update
     * @param wqp    => assoc. array of 'WHERE' query params
     * @param limit  => limit query => default 0
     * 
     * @return int => num of affected rows
     */
    public function __update(string $table, array $params, array $wqp = [], int $limit = 0) : int {

        $params_opts = $this->__initialize_opts($params);
        if(empty($params_opts)){
            return false;
        }

        $params = $this->__filter_vars($params, $params_opts[0]);
        if(empty($params)){
            return false;
        } 

        if(!empty($wqp)){

            $wqp_opts = $this->__initialize_opts($wqp);
            if(empty($wqp_opts)){
                return false;
            }

            $wqp = $this->__filter_vars($wqp, $wqp_opts);
            if(empty($wqp)){
                return false;
            }

            $w_last = count($wqp) -1;
            $str_wqp = '';

            /** use underscore '_' to differentiate same keys */
            foreach($wqp as $k => $w){
                $numeric_k = array_search($k, array_keys($wqp));
                $str_wqp .= "{$k}=:_{$k}".($numeric_k !== $w_last ? ' AND ' : '');
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

        /**
         * re-initialize database if neccessary
         */
        if($this->pdo === null){
            $this->init_db();
        }

        try {
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

                $this->bind_params(array_merge($params, $_wqp), array_merge($params_opts, $wqp_opts));
            } else {
                $this->bind_params($params, $params_opts);
            }

            $res = $this->stmt->execute();

            if($res){ 
                $ret_rows = $this->stmt->rowCount();
            }
            
        } catch (PDOException $e) {
            echo "Database error [{$e->getCode()}]: update failed\n".
                          "err msg: {$e->getMessage()} on line: {$e->getLine()}\n".
                          "stack trace: {$e->getTraceAsString()}\n\n";
            $this->clear_pdo_stmt();
            return false;
        }

        $this->stmt = null;
        return $ret_rows;
    }

    /**
     * @param table  => table to select from
     * @param params => assoc. array of params to update
     * 
     * @return int => num of affected rows
     */
    public function __delete(string $table, array $params) : int {
        if(!empty($params) && ($params_opts = $this->__initialize_opts($params)) === false){
            return false;
        }
        if(!empty($params_opts) && ($params = $this->__filter_vars($params, $params_opts)) === false){
            return false;
        }

        $table = filter_var($table, FILTER_UNSAFE_RAW);
        $del_params = '';
        $opts_last = count($params_opts) -1;

        foreach($params as $k => $param){
            foreach($params_opts as $opt){
                if($k === $opt['param']){
                    $numeric_k = array_search($k, array_keys($params));
                    $del_params .= "$k=:$k".($numeric_k !== $opts_last ? ' AND ' : '');
                    break;
                }
            }
        }

        $date = date("Y-m-d H:i:s");
        $sql = "DELETE FROM $table WHERE $del_params";

        /**
         * re-initialize database if neccessary
         */
        if($this->pdo === null){
            $this->init_db();
        }

        try {
            $this->stmt = $this->pdo->prepare($sql);
            $this->bind_params($params, $params_opts);
            $res = $this->stmt->execute();
            if($res){ 
                $ret_rows = $this->stmt->rowCount();
            }

        } catch (PDOException $e) {
            echo "Database error [{$e->getCode()}]: delete failed\n".
                          "err msg: {$e->getMessage()} on line: {$e->getLine()}\n".
                          "stack trace: {$e->getTraceAsString()}\n\n";
            $this->clear_pdo_stmt();
            return false;
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
                $joinstr .= (!empty($j['type']) ? ' '.strtoupper($j['type']).' ' : ' ')
                            .'JOIN '.$j['table1'].' ON '
                            .$j['table1'].'.'.$j['param1']
                            .$j['operator']
                            .$j['table2'].'.'.$j['param2'];
            }
            unset($j);
        }
        return $joinstr;
    }

    /**
     * => bind values to parameters
     * 
     * @return void
     */
    private function bind_params(array $params, array $params_opts) : void {
        /**
         * array of entities
         */
        if(isset($params[0]) && is_array($params[0])){
            foreach ($params as $p_index => $p) {
                $this->loop_bind_params($p, $params_opts);
            }
            unset($p);
        }
        /**
         * single entity
         */
        else {
            $this->loop_bind_params($params, $params_opts);
        }
    }

    /**
     * => helper fnc
	 * 
	 * @return void
     */
    private function loop_bind_params(array $params, array $params_opts) : void {
        foreach($params as $param_name => &$param){
            if($param_name === 'password' || $param_name === 'pass' || $param_name === 'pwd' || $param_name === 'passwd'){
                $hashed_pass = password_hash($param, PASSWORD_BCRYPT, ["cost" => self::BCRYPT_COST]);
                $this->stmt->bindParam(":{$param_name}", $hashed_pass, PDO::PARAM_STR);
                unset($params[$param_name]);
                continue;
            }
            foreach($params_opts as $opt){
                if($param_name === $opt['param']){
                    $this->stmt->bindParam("{$param_name}", $param, $opt['pdo_param']);
                    break;
                }
            }
        }
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