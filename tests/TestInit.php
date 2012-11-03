<?php
// we run from within a checkout
set_include_path(realpath(__DIR__ . '/../src/') . ':' . get_include_path());

if (version_compare(PHP_VERSION, '5.3.0', '<')) {
    echo "Sorry, you need 5.3.0+ to run this test suite.";
    exit(1);
}

require_once 'MDB2.php';

class MailQueueTestInit
{
    public static function autoload($className)
    {
        if (strpos($className, 'PEAR2\Mail\Queue') !== 0 && strpos($className, 'Mail') !== 0) {
            return;
        }
        $file = str_replace('_', '/', $className) . '.php';
        $file = str_replace('\\', '/', $file);

        return include $file;
    }
}
spl_autoload_register(array('MailQueueTestInit', 'autoload'));
