<?php

class Filter {

    /**
     * @param p => entity name
     * @param v => mixed => entity value
     * 
     * @return array => array of options (name, filter, pdo::param)
     */
    protected function set_opts(string $p, $v, bool $verbose) : array {
        $opts = ['param' => $p];
        switch (gettype($v))
        {
            case 'boolean':
                if($verbose){
                    echo "USING bool FILTER for param {$opts['param']}: \n";
                }
                $opts += [
                    'pdo_param' => PDO::PARAM_BOOL,
                    'filter' => FILTER_VALIDATE_BOOLEAN
                ];
                break;
            case 'integer':
                if($verbose){
                    echo "USING int FILTER for param {$opts['param']}: \n";
                }
                $opts += [
                    'pdo_param' => PDO::PARAM_INT,
                    'filter' => FILTER_SANITIZE_NUMBER_INT
                ];
                break;
            case 'double':
                if($verbose){
                    echo "USING double FILTER for param {$opts['param']}: \n";
                }
                $opts += [
                    'pdo_param' => PDO::PARAM_STR,
                    'filter' => FILTER_SANITIZE_NUMBER_FLOAT
                ];
                break;
            case 'string':
                $opts += ['pdo_param' => PDO::PARAM_STR];

                if(preg_match('/^email$/', $opts['param']) === 1){
                    if($verbose){
                        echo "USING email [string] FILTER for param {$opts['param']}: \n";
                    }
                    $opts += ['filter' => FILTER_VALIDATE_EMAIL];
                } else if(preg_match('/^(url|path)$/', $opts['param']) === 1){
                    if($verbose){
                        echo "USING url [string] FILTER for param {$opts['param']}: \n";
                    }
                    $opts += ['filter' => FILTER_VALIDATE_URL];
                } else if(preg_match('/^(ip){1}(_{1}(addr){1}(ess){0,1}){0,1}$/', $opts['param']) === 1){
                    if($verbose){
                        echo "USING ip [string] FILTER for param {$opts['param']}: \n";
                    }
                    $opts += ['filter' => FILTER_VALIDATE_IP];
                } else {
                    if($verbose){
                        echo "USING default [string] FILTER for param {$opts['param']}: \n";
                    }
                    $opts += ['filter' => FILTER_UNSAFE_RAW];
                }
                break;
            case 'NULL':
                $opts += [
                    'pdo_param' => PDO::PARAM_NULL,
                    'filter' => null
                ];
                break;
            case 'array':
                $opts = [
                    'pdo_param' => null,
                    'filter' => null
                ];
                break;
            case 'object':
                $opts = [
                    'pdo_param' => null,
                    'filter' => null
                ];
                break;
            case 'resource':
                $opts = [
                    'pdo_param' => null,
                    'filter' => null
                ];
                break;
            case 'resource (closed)':
                $opts = [
                    'pdo_param' => null,
                    'filter' => null
                ];
                break;
            default:
                $opts = [
                    'pdo_param' => null,
                    'filter' => null
                ];
                break;
        }
        return $opts;
    }

    /**
     * @param vars => pointer to array => values to filter
     * @param opts => array => options (name, filter, pdo::param)
     * 
     * @return array => array of filtered vars (name, filter, pdo::param)
     * @return false => indicates invalid format
     */
    protected function __filter_vars(array &$vars, array $opts) : array {
        if(count($vars) === count($opts)){
            
            $optsk = 0;

            foreach($vars as $k => &$var){
                /**
                 * FILTER_SANITIZE_STRING is getting deprecated
                 * FILTER_UNSAFE_RAW does nothing without flags
                 * ::>> use htmlspecialchars
                 */
                if($opts[$optsk]['filter'] === FILTER_UNSAFE_RAW){
                    /**
                     * json & date formats are filtered differently
                     */
                    if($this->var_is_json($var) || is_int(strtotime($var))){
                        if(($var = filter_var($var, $opts[$optsk]['filter'])) === false || $var === null){
                            return [];
                        }
                    }
                    else {
                        if(($var = htmlspecialchars($var, ENT_QUOTES, 'utf-8')) === ''){
                            return [];
                        }
                    }
                } else {
                    if(($var = filter_var($var,
                                 ($opts[$optsk]['filter'] !== null
                                 ? $opts[$optsk]['filter']
                                 : FILTER_UNSAFE_RAW)
                               )
                        ) === false || $var === null)
                    {
                        return [];
                    }
                }
                $optsk++;
            }
            unset($var);
        }

        return $vars;
    }

    /**
     * 
     * => helper fnc
     * 
     * @return is_json
     */
    private function var_is_json(string $var) : bool {
        try {
            if(($var = json_decode($json=$var,$flags=JSON_THROW_ON_ERROR)) === null || $var === false){
                return false;
            }
            if(is_numeric($var)){
                return false;
            }
        } catch (JsonException $e) {
            return false;
        }
        return true;
    }


    /**
     * @param params => array
     * 
     * @return opts  => array => param options (name, filter, pdo::param)
     * @return false => indicates malformed options
     */
    protected function __initialize_opts(array $params, bool $verbose) : array {
        $optsk = 0;
        $opts = [];
        foreach($params as $k => $p){
            $opts[$optsk] = $this->set_opts($k, $p, $verbose);
            if( ! is_int($opts[$optsk]['pdo_param']) || ! is_int($opts[$optsk]['filter'])){
                return [];
            }
            $optsk++;
        }
        return $opts;
    }
}

?>