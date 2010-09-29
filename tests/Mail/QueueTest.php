<?php
/**
 * So painful.
 */
class Mail_QueueTest extends Mail_QueueAbstract
{
    public function testPut()
    {
        $time_to_send = 3600;
        $id_user      = 1;
        $ip           = '127.0.0.1';
        $sender       = 'testsuite@example.org';
        $recipient    = 'testcase@example.org';
        $headers      = array('X-TestSuite' => 1);
        $body         = 'Lorem ipsum';

        $mailId = $this->queue->put($sender, $recipient, $headers, $body, $time_to_send=0, true, $id_user);

        $this->assertEquals(1, $mailId); // it's the first email, after all :-)
        $this->assertEquals(1, count($this->queue->getQueueCount()));
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

    /**
     * Queue two emails - to be send right away.
     *
     * @return void
     */
    public function testSendMailsInQueue()
    {
        $this->markTestIncomplete("Doesn't send yet, need to doublecheck my table definition.");

        $id_user      = 1;
        $sender       = 'testsuite@example.org';
        $recipient    = 'testcase@example.org';
        $headers      = array('X-TestSuite' => 1);
        $body         = 'Lorem ipsum';

        $mailId1 = $this->queue->put($sender, $recipient, $headers, $body);

        $id_user      = 1;
        $sender       = 'testsuite@example.org';
        $recipient    = 'testcase@example.org';
        $headers      = array('X-TestSuite' => 2);
        $body         = 'Lorem ipsum sit dolor';

        $mailId2 = $this->queue->put($sender, $recipient, $headers, $body);

        $this->assertEquals(2, $this->queue->getQueueCount());

        $status = $this->queue->sendMailsInQueue();
        if (Pear::isError($status)) {
            $this->fail("Error sending emails: {$status->getMessage()}.");
        }
        $this->assertTrue($status);
        $this->assertEquals(0, $this->queue->getQueueCount());
    }
}
