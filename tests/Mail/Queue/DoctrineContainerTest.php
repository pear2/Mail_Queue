<?php

class Mail_Queue_DoctrineContainerTest extends Mail_QueueDoctrineAbstract
{

    public function setUp()
    {
        parent::setUp();
    }

    public function testContainerGet()
    {
        $time_to_send = 3600;
        $id_user = 1;
        $sender = 'testsuite@example.org';
        $recipient = 'testcase@example.org';
        $headers = array('X-TestSuite' => 1);
        $body = 'Lorem ipsum';

        $mailId = $this->queue->put($sender, $recipient, $headers, $body, 0, true, $id_user);
        if (!is_numeric($mailId)) {
            $this->fail("Could not save email.");
            return;
        }

        $message = $this->queue->container->getMailById($mailId);
        $this->assertTrue(($message instanceof PEAR2\Mail\Queue\Body));

        $this->assertEquals($mailId, $message->getId());
        $this->assertEquals($id_user, $message->getIdUser());
        $this->assertEquals('', $message->getIp());
        $this->assertEquals($sender, $message->getSender());
        $this->assertEquals($recipient, $message->getRecipient());
        $this->assertEquals($headers, $message->getHeaders());
        $this->assertEquals($body, $message->getBody());
        $this->assertTrue($message->isDeleteAfterSend());
    }

}
