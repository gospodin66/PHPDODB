<?php

class Autoloader {

    public static function register_autoload() : void {

        $dirs = ['.'];
        $class_whitelist = [
            'LoadEnv.php',
            'Database.php',
            'Filter.php'
        ];
        $classes = $_classes = [];
        $env_path = './'.$class_whitelist[0];

        foreach ($dirs as $dir) {
            $classes[$dir] = array_diff(scandir($dir), ['.','..']);
        }

        foreach ($classes as $key => $class_list) {
            foreach ($class_list as $class) {
                if(in_array($class, $class_whitelist)){
                    $_classes[] = "$key/$class";
                }
            }
        }

        // LoadEnv.php always loads 1st
        $env_key = array_search($env_path, $_classes);
        $temp = $_classes[0];
        
        $_classes[0] = $_classes[$env_key];
        $_classes[$env_key] = $temp;

        unset($temp,$env_key);
        self::register_autoload_functions($_classes);
    }

    
    private static function register_autoload_functions(array $classes) : void {
        foreach ($classes as $c) {
            spl_autoload_register(
                function() use($c) : void {
                    if(file_exists($c)){
                        require_once $c;
                    }
                    else {
                        throw new \Exception("Error registering {$c} class");
                    }
                }
            );
        }
    }
    
    public static function unregister_autoload() : void {
        
        $functions = spl_autoload_functions();
    
        foreach ($functions as $f) {
            spl_autoload_unregister($f);
        }
    }
}
?>