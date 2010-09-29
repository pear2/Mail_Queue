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
        }

        $this->dsn = 'sqlite:///' . __DIR__ . "/{$this->db}?mode=0644";

        $this->setUpDatabase($this->dsn);

        $container_opts = array(
            'type'       => 'mdb2',
            'dsn'        => $this->dsn,
            'mail_table' => $this->table,
        );

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

        $this->queue->container->db->disconnect();
        unset($this->queue);

        @unlink(__DIR__ . "/{$this->db}");
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
        if (MDB2::isError($mdb2)) {
            $this->fail($mdb2->getDebugInfo());
        }

        /**
         * @desc An associative array with col/type and index.
         */
        $cols = array(
            'id INTEGER'                => 'PRIMARY KEY',
            'create_time DATETIME'      => '',
            'time_to_send DATETIME'     => 'KEY',
            'sent_time DATETIME'        => '',
            'id_user INTEGER'           => 'KEY',
            'ip VARCHAR'                => '',
            'sender VARCHAR'            => '',
            'recipient TEXT'            => '',
            'headers TEXT'              => '',
            'body TEXT'                 => '',
            'try_sent INTEGER'          => '',
            'delete_after_send INTEGER' => '',
        );

        $sql  = "CREATE TABLE '{$this->table}' (";
        foreach ($cols as $col => $idx) {
            $sql .= trim("{$col} {$idx}");
            $sql .= ',';
        }
        $sql = substr($sql, 0, -1) . ");";

        $status = $mdb2->exec($sql);
        if (MDB2::isError($status)) {
            $this->fail($status->getDebugInfo());
        }

        $mdb2->disconnect();
    }
}
