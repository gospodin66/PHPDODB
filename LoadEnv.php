<?php

if(defined('ENV_PATH') === false){
    define('ENV_PATH', '.env');
}

if(file_exists(ENV_PATH) === false){
    throw new \Exception('.env not found');
}


ob_start();

if(file_exists(ENV_PATH)) {
    
    readfile(ENV_PATH);

    $envcontents = explode("\n", ob_get_contents());
    $envs = [];

    foreach ($envcontents as $v) {
        $key = substr($v, 0, strpos($v, '='));
        $val = substr($v, strpos($v, '=') +1, strlen($v));
        $envs[$key] = $val;
    }
}

ob_end_clean();

foreach ($envs as $k => $env) {
    if( ! putenv("$k=$env")){
        throw new \Exception('Failed to load putenv(): '.$k.'='.$env);
    }
}

if( ! function_exists('readenv')){
    function readenv(string $e, $default = null) {
        $val = getenv($e);
        return ($val !== false) ? $val : $default;
    }
}

?>