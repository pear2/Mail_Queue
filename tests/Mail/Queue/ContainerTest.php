<?php
/**
 * So painful.
 */
class Mail_Queue_ContainerTest extends Mail_QueueAbstract
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

        $message = $this->queue->container->getMailById($mailId);
        var_dump($message);
    }
}
