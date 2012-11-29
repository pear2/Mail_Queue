<?php

use PEAR2\Mail\Queue;
use Doctrine\ORM\EntityManager;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Persistence\PersistentObject;
use Doctrine\ORM\Configuration;
use Doctrine\Common\Annotations\AnnotationReader;

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

        $config = new Configuration();
        $cache = new \Doctrine\Common\Cache\ArrayCache();
        $config->newDefaultAnnotationDriver();

        AnnotationReader::addGlobalIgnoredName('package_version');
        $annotationReader = new AnnotationReader;
        $cachedAnnotationReader = new \Doctrine\Common\Annotations\CachedReader(
            $annotationReader, // use reader
            $cache // and a cache driver
        );

        $annotationDriver = new \Doctrine\ORM\Mapping\Driver\AnnotationDriver(
            $cachedAnnotationReader//, // our cached annotation reader
            //array($entityFolder) // paths to look in
        );

        //$mappingDriverChain->addDriver($annotationDriver, 'PEAR2\Mail\Queue\Entity');

        $config->setMetadataDriverImpl($annotationDriver);
        $config->setMetadataCacheImpl($cache);
        $config->setQueryCacheImpl($cache);
        $config->setProxyDir(__DIR__ . '/../Proxy');
        $config->setProxyNamespace('testProxy');
        $config->setAutoGenerateProxyClasses(1);

        $connectionConfig = array(
            'driver'   => 'pdo_sqlite',
                'dbname'   => '',
                'user'     => '',
                'host'     => '',
                'password' => '',
                'memory'    => true

        );

        $this->em = EntityManager::create(
            $connectionConfig,
            $config
        );

        $doctrineDriver = new PEAR2\Mail\Queue\Container\doctrine2(array('doctrine_em' => $this->em));
        $this->container = $doctrineDriver;

        $this->em = $doctrineDriver->getEntityManager();

        $this->initDoctrineTestSetup();


        $container_opts = array(
            'type' => 'doctrine2',
            'doctrine_em' => $this->em
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
