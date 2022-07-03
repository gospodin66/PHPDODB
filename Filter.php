<?php

class Filter {

    /**
     * @param p => entity name
     * @param v => mixed => entity value
     * 
     * @return array => array of options (name, filter, pdo::param)
     */
    protected function set_opts(string $p, $v) : array {
        $opts = ['param' => $p];
        switch (gettype($v))
        {
            case 'boolean':
                $opts += [
                    'pdo_param' => PDO::PARAM_BOOL,
                    'filter' => FILTER_VALIDATE_BOOLEAN
                ];
                break;
            case 'integer':
                $opts += [
                    'pdo_param' => PDO::PARAM_INT,
                    'filter' => FILTER_SANITIZE_NUMBER_INT
                ];
                break;
            case 'double':
                $opts += [
                    'pdo_param' => PDO::PARAM_STR,
                    'filter' => FILTER_SANITIZE_NUMBER_FLOAT
                ];
                break;
            case 'string':
                $opts += ['pdo_param' => PDO::PARAM_STR];
                if($opts['param'] === 'email'){
                    $opts += ['filter' => FILTER_VALIDATE_EMAIL];
                }
                else if($opts['param'] === 'url'
                     || $opts['param'] === 'path'){
                    $opts += ['filter' => FILTER_VALIDATE_URL];
                }
                else if($opts['param'] === 'ip'
                     || $opts['param'] === 'ip_address'
                     || $opts['param'] === 'ip_addr'){
                    $opts += ['filter' => FILTER_VALIDATE_IP];
                }
                else {
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
    protected function __filter_vars(&$vars, array $opts) : array {
        if(count($vars) === count($opts))
        {
            $optsk = 0;
            foreach($vars as $k => &$var)
            {
                if(($var = filter_var($var,
                             ($opts[$optsk]['filter'] !== null
                             ? $opts[$optsk]['filter']
                             : FILTER_UNSAFE_RAW)
                           )
                    ) === false || $var === null)
                {
                    return [];
                }
                $optsk++;
            }
            unset($var);
        }
        return $vars;
    }

    /**
     * @param params => array
     * 
     * @return opts  => array => param options (name, filter, pdo::param)
     * @return false => indicates malformed options
     */
    protected function __initialize_opts(array $params) : array {
        $optsk = 0;
        $opts = [];
        foreach($params as $k => $p)
        {
            $opts[$optsk] = $this->set_opts($k, $p);
            if(false === is_int($opts[$optsk]['pdo_param']) || false === is_int($opts[$optsk]['filter'])){
                return [];
            }
            $optsk++;
        }
        return $opts;
    }
}

?>