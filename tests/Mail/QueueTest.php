<?php
/**
 * So painful.
 */
class Mail_QueueTest extends Mail_QueueAbstract
{
    /**
     * This should return a MDB2_Error
     */
    public function testSendMailByIdWithInvalidId()
    {
        $randomId = rand(0, 12);
        $status   = $this->queue->sendMailById($randomId);
        $this->assertTrue(($status instanceof Mail_Queue_Error));
    }
}
