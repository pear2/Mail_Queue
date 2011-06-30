<?php
/**
 * Base class for all tests.
 */
abstract class Mail_QueueAbstract extends PHPUnit_Framework_TestCase
{
    /**
     * @var string $db Name of the database file.
     */
    protected $db = 'mailqueueTestSuite';

    /**
     * @var string $dsn String to connect to sqlite.
     */
    protected $dsn;

    /**
     * @var Mail_Queue $queue
     */
    protected $queue;

    /**
     * @var string $table The table name.
     */
    protected $table = 'mail_queue';

    /**
     * Initializes the Mail_Queue in {@link self::$queue}.
     *
     * @return void
     * @uses   MDB2_Driver_SQLite
     */
    public function setUp()
    {
        if (!extension_loaded('sqlite')) {
            $this->markTestSkipped("You need ext/sqlite to run this test suite.");
            return;
        }

        $this->dsn = 'sqlite:///' . __DIR__ . "/{$this->db}?mode=0644";

        $this->setUpDatabase($this->dsn);

        $container_opts = array(
            'type'       => 'mdb2',
            'dsn'        => $this->dsn,
            'mail_table' => $this->table,
        );

        /**
         * @see Mail_mock
         */
        $mail_opts = array('driver' => 'mock');

        $this->queue = new Mail_Queue($container_opts, $mail_opts);
        if ($this->queue->hasErrors()) {
            $errors = $this->queue->getErrors();
            $fail   = "The following errors occurred:\n";
            foreach ($errors as $error) {
                $fail .= $error->getMessage() . "\n";
            }
            $this->fail($fail);
        }
    }

    /**
     * Remove the table/artifacts.
     *
     * @return void
     * @see    self::setUpDatabase()
     */
    public function tearDown()
    {
        $mdb2 = MDB2::connect($this->dsn);
        $mdb2->query("DROP TABLE '{$this->table}'");
        $mdb2->disconnect();

        //$this->queue->container->db->disconnect();
        unset($this->queue);

        unlink(__DIR__ . "/{$this->db}");
    }

    /**
     * Create the table.
     *
     * @param string $dsn The DSN.
     *
     * @return void
     * @see    self::setUp()
     * @see    self::tearDown()
     */
    protected function setUpDatabase($dsn)
    {
        $mdb2 = MDB2::connect($dsn);
        $this->handlePearError($mdb2, "DB connection init");

        $status = $mdb2->loadModule('Manager');
        $this->handlePearError($status, "Loading manager module");

        $cols = array(
            'id' => array(
                 'type'   => 'integer',
                 'length' => 2,
            ),
            'create_time' => array(
                'type' => 'timestamp',
            ),
            'time_to_send' => array(
                'type'    => 'timestamp',
                'notnull' => 1,
                'default' => 0,
            ),
            'sent_time' => array(
                'type' => 'timestamp',
            ),
            'id_user' => array(
                'type' => 'integer',
            ),
            'ip' => array(
                'type'   => 'text',
                'length' => '15',
            ),
            'sender' => array(
                'type'   => 'text',
                'length' => 100,
            ),
            'recipient' => array(
                'type' => 'text',
            ),
            'headers' => array(
                'type' => 'text',
            ),
            'body' => array(
                'type' => 'text',
            ),
            'try_sent' => array(
                'type'    => 'integer',
                'length'  => 2,
                'notnull' => 1,
                'default' => 0,
            ),
            'delete_after_send' => array(
                'type'    => 'integer',
                'length'  => '1',
                'notnull' => 1,
                'default' => 0,
            ),
        );

        $status = $mdb2->manager->createTable($this->table, $cols);
        $this->handlePearError($status, "Create table");

        $status = $mdb2->manager->createConstraint(
            $this->table,
            'idx',
            array(
                'primary' => 0,
                'fields'  => array(
                    'id' => array(),
                ),
            )
        );
        $this->handlePearError($status, "Create primary key");

        $status = $mdb2->manager->createIndex(
            $this->table,
            't2s',
            array('fields' => array(
                'time_to_send' => array())
            )
        );
        $this->handlePearError($status, "Index on time_to_send");

        $status = $mdb2->manager->createIndex(
            $this->table,
            'idu',
            array('fields' => array(
                'id_user' => array())
            )
        );
        $this->handlePearError($status, 'Index on id_user');
        $mdb2->disconnect();
    }

    /**
     * A small wrapper to handle PEAR_Error, possibly.
     *
     * @param mixed  $err    PEAR_Error, or MDB2_OK
     * @param string $action Whatever we were doing to make {@link self::fail()}
     *                       more descriptive.
     *
     * @return void
     */
    protected function handlePearError($err, $action)
    {
        if (PEAR::isError($err)) {
            $this->fail("{$action}: {$status->getDebugInfo()}");
            return;
        }
    }
}
