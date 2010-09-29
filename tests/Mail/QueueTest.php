<?php
/**
 * So painful.
 */
class Mail_QueueTest extends Mail_QueueAbstract
{
    public function testPutGet()
    {
        $this->markTestIncomplete("Not yet done.");

        $time_to_send = 3600;
        $id_user      = 1;
        $ip           = '127.0.0.1';
        $sender       = 'testsuite@example.org';
        $recipient    = 'testcase@example.org';
        $headers      = array('X-TestSuite' => 1);
        $body         = 'Lorem ipsum';

        $mailId = $this->queue->put(
            $time_to_send,
            $id_user,
            $ip,
            $sender,
            $recipient,
            $headers,
            $body
        );

        $this->assertEquals(1, $mailId); // it's the first email, after all :-)
        $this->assertTrue((count($this->queue->getQueueCount()) > 0));
    }

    /**
     * This should return a MDB2_Error
     */
    public function testSendMailByIdWithInvalidId()
    {
        $randomId = rand(1, 12);
        $status   = $this->queue->sendMailById($randomId);

        $this->assertContains('no such message', $status->getDebugInfo());
        $this->assertTrue(($status instanceof Mail_Queue_Error));
    }
}
