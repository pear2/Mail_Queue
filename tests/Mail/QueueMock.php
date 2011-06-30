<?php
class Mail_QueueMock extends Mail_QueueAbstract
{
    public function setUp()
    {
        parent::setUp();

        $container_opts = array(
            'type'       => 'mdb2',
            'dsn'        => $this->dsn,
            'mail_table' => $this->table,
        );

        /**
         * @see Mail_mock
         */
        $mail_opts = array('driver' => 'mock');

        $this->queueMock = $this->getMock(
            'Mail_Queue',
            array('get',),
            array($container_opts, $mail_opts,)
        );

        $this->queueMock->expects($this->once())
            ->method('get')
            ->will($this->returnValue(Pear::raiseError("OH NOEZ")));
    }

    /**
     * Make sure that an error doesn't cause an infinite loop.
     *
     * @return void
     */
    public function testErrorInSendMailsInQueue()
    {
        $this->queueMock->sendMailsinQueue();
        $this->assertTrue($this->queueMock->hasErrors());

        $errors = $this->queueMock->getErrors();
        $this->assertInternalType('array', $errors);

        $err = $errors[0];
        $this->assertEquals("OH NOEZ", $err->getMessage());
    }
}
