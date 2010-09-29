<?php
/**
 * So painful.
 */
class Mail_Queue_ContainerTest extends Mail_QueueAbstract
{
    public function testPutGet()
    {
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

        $message = $this->queue->container->getMailById($mailId);
        var_dump($message);
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
