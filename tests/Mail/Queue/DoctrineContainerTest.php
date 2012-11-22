<?php
namespace Mail\Queue;

use Doctrine\Common\ClassLoader;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;

use Mail\Queue;

require 'Doctrine/Common/ClassLoader.php';

class DoctrineContainerTest extends \PHPUnit_Framework_TestCase
{
    protected $dbal;

    public function setUp()
    {
        $classLoader = new ClassLoader('Doctrine', '/Users/till/Documents/pear/share/pear');
        $classLoader->register();

        $params = array(
            'dbname' => ':MEMORY:',
            'driver' => 'pdo_sqlite',
        );

        $this->dbal = DriverManager::getConnection($params, new Configuration);
    }

    public function testInit()
    {
        $container_opts = array(
            'type'       => 'doctrine',
            'dsn'        => $this->dbal,
            'mail_table' => 'mail_queue',
        );
    }
}
