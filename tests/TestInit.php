<?php
// we run from within a checkout
set_include_path(realpath(__DIR__ . '/../') . ':' . get_include_path());

if (version_compare(PHP_VERSION, '5.3.0', '<')) {
    echo "Sorry, you need 5.3.0+ to run this test suite.";
    exit(1);
}

require_once 'MDB2.php';

class TestInit
{
    public static function autoload($className)
    {
        $file = str_replace('_', '/', $className) . '.php';
        return include $file;
    }
}
spl_autoload_register(array('TestInit', 'autoload'));
