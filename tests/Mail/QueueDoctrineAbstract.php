<?php

use PEAR2\Mail\Queue;

/**
 * Base class for all Doctrine related tests.
 */
abstract class Mail_QueueDoctrineAbstract extends PHPUnit_Framework_TestCase
{
    protected $container;
    /**
     * @var Doctrine\ORM\EntityManager
     */
    protected $em;

    /**
     * @var Mail_Queue $queue
     */
    protected $queue;

    /**
     * Initializes the Mail_Queue in {@link self::$queue}.
     *
     * @return void
     */
    public function setUp()
    {

        $doctrineConfig = array(
            'cacheImplementation' => 'Doctrine\Common\Cache\ArrayCache',
            'connection' => array(
                'driver' => 'pdo_sqlite',
                'dbname' => '',
                'user' => '',
                'host' => '',
                'password' => '',
                'memory' => true
            ),
            'autoGenerateProxyClasses' => 1,
            'proxy' => array('namespace' => 'Proxy')
        );

        $doctrineDriver = new PEAR2\Mail\Queue\Container\doctrine2(array('doctrine' => $doctrineConfig));
        $this->container = $doctrineDriver;

        $this->em = $doctrineDriver->getEntityManager();

        $this->initDoctrineTestSetup();


        $container_opts = array(
            'type' => 'doctrine2',
            'doctrine' => $doctrineConfig,
            'em'    => $this->em
        );

        /**
         * @see Mail_mock
         */
        $mail_opts = array('driver' => 'mock');

        $this->queue = new Queue($container_opts, $mail_opts);
        if ($this->queue->hasErrors()) {
            $errors = $this->queue->getErrors();
            $fail = "The following errors occurred:\n";
            foreach ($errors as $error) {
                $fail .= $error->getMessage() . "\n";
            }
            $this->fail($fail);
        }
    }


    protected function initDoctrineTestSetup()
    {

        $tool = new \Doctrine\ORM\Tools\SchemaTool($this->em);
        $classes = array(
            $this->em->getClassMetadata('PEAR2\Mail\Queue\Entity\Mail'),
        );

        $tool->createSchema($classes);
    }

    /**
     * Remove the table/artifacts.
     *
     * @return void
     * @see    self::setUpDatabase()
     */
    public function tearDown()
    {
        $tool = new \Doctrine\ORM\Tools\SchemaTool($this->em);
        $tool->dropDatabase();
        unset($this->em);
        parent::tearDown();


        //$this->queue->container->db->disconnect();
        unset($this->queue);
    }

    public function getContainer(){
           return $this->container;
    }
}
