<?php
/**
 * +----------------------------------------------------------------------+
 * | PEAR :: Mail :: Queue :: Doctrine2 Container                              |
 * +----------------------------------------------------------------------+
 * | Copyright (c) 2012 Leander Damme                             |
 * +----------------------------------------------------------------------+
 * | All rights reserved.                                                 |
 * |                                                                      |
 * | Redistribution and use in source and binary forms, with or without   |
 * | modification, are permitted provided that the following conditions   |
 * | are met:                                                             |
 * |                                                                      |
 * | * Redistributions of source code must retain the above copyright     |
 * |   notice, this list of conditions and the following disclaimer.      |
 * | * Redistributions in binary form must reproduce the above copyright  |
 * |   notice, this list of conditions and the following disclaimer in    |
 * |   the documentation and/or other materials provided with the         |
 * |   distribution.                                                      |
 * | * The names of its contributors may be used to endorse or promote    |
 * |   products derived from this software without specific prior written |
 * |   permission.                                                        |
 * |                                                                      |
 * | THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS  |
 * | "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT    |
 * | LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS    |
 * | FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE       |
 * | COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,  |
 * | INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, |
 * | BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;     |
 * | LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER     |
 * | CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT   |
 * | LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN    |
 * | ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE      |
 * | POSSIBILITY OF SUCH DAMAGE.                                          |
 * +----------------------------------------------------------------------+
 * | Author: Leander Damme <leander at wesrc.com>                    |
 * +----------------------------------------------------------------------+
 *
 * PHP Version 5.3
 *
 * @category Mail
 * @package  Mail_Queue
 * @author   Leander Damme <leander@wesrc.com>
 * @license  http://www.opensource.org/licenses/bsd-license.php The BSD License
 * @link     http://pear.php.net/package/Mail_Queue
 */

namespace PEAR2\Mail\Queue\Container;

use PEAR2\Mail\Queue\Container;
use PEAR2\Mail\Queue\Exception;
use PEAR2\Mail\Queue;
use PEAR2\Mail\Queue\Body;
use PEAR2\Mail\Queue\Entity\Mail;
use PEAR2\Mail\Queue\Entity\Repository\MailRepository;

/**
 * Storage driver for fetching mail queue data with Doctrine2
 *
 * This storage driver can use all databases which are supported
 * by the DoctrineORM abstraction layer.
 *
 * @category Mail
 * @package  Mail_Queue
 * @author   Leander Damme <leander@wesrc.com>
 * @license  http://www.opensource.org/licenses/bsd-license.php The BSD License
 * @version  Release: @package_version@
 * @link     http://pear.php.net/package/Mail_Queue
 */

class Doctrine2 extends Container
{

    var $errorMsg = 'doctrine failed: "%s", %s';

    /**
     * @var Doctrine\ORM\EntityManager
     */
    protected $em;

    /**
     * @var Mail Mail
     */
    protected $entity;

    /**
     * @var MailRepository MailRepository
     */
    protected $repo;

    /**
     * @var
     */
    protected $config;

    /**
     * Constructor
     *
     * Mail_Queue_Container_doctrine2()
     *
     * @param array $options An associative array of connection option.
     *
     */
    public function __construct(array $options)
    {

        if (!isset($options['doctrine_em'])) {
            throw new Exception(
                'No doctrine entity manager specified!',
                Queue::ERROR_NO_OPTIONS
            );
        }
        $this->em = $options['doctrine_em'];

        $this->setOption();
        $this->repo = $this->em->getRepository('PEAR2\Mail\Queue\Entity\Mail');
    }

    /**
     * Get the Doctrine EntityManager
     *
     * @return \Doctrine\ORM\EntityManager
     */
    public function getEntityManager()
    {
        if (isset($this->em)) {
            return $this->em;
        }
        $this->init();
        return $this->em;
    }

    /**
     * Get the Doctrine EntityManager
     *
     * @param Doctrine\ORM\EntityManager $em EntityManager
     *
     * @return \PEAR2\Mail\Queue\Container\doctrine2
     */
    public function setEntityManager($em)
    {
        $this->em = $em;
        return $this;
    }

    /**
     * Preload mail to queue.
     *
     * @return true
     * @throws Exception
     */
    protected function _preload()
    {
        $queueCollection = $this->repo->preload($this->limit, $this->offset,$this->try);

        $this->_last_item = 0;
        $this->queue_data = array(); //reset buffer

        foreach($queueCollection as $mail){
            $delete_after_send = (bool) $mail->__get('deleteAfterSend');

            $this->queue_data[$this->_last_item] = new Body(
                $mail->getId(),
                $mail->getCreateTime(),
                $mail->getTimeToSend(),
                $mail->getSentTime(),
                $mail->getIdUser(),
                $mail->getIp(),
                $mail->getSender(),
                $this->_isSerialized($mail->getRecipient()) ? unserialize($mail->getRecipient()) : $mail->getRecipient(),
                unserialize($mail->getHeaders()),
                unserialize($mail->getBody()),
                $delete_after_send,
                $mail->getTrySent()
            );
            $this->_last_item++;
        }

        return true;
    }

    /**
     * Put new mail in queue.
     *
     * Mail_Queue_Container::put()
     *
     * @param string  $time_to_send      When mail have to be send
     * @param integer $id_user           Sender id
     * @param string  $ip                Sender ip
     * @param string  $from              Sender e-mail
     * @param string  $to                Reciepient e-mail
     * @param string  $hdrs              Mail headers (in RFC)
     * @param string  $body              Mail body (in RFC)
     * @param bool    $delete_after_send (not sure wether in RFC)
     *
     * @return bool True on success
     **/
    public function put($time_to_send, $id_user, $ip, $from, $to, $hdrs, $body, $delete_after_send)
    {
        $queueRecord = new Mail();

        $queueRecord
            ->__set('createTime', new \DateTime())
            ->__set('timeToSend', new \DateTime($time_to_send))
            ->__set('idUser', $id_user)
            ->__set('ip', $ip)
            ->__set('sender', $from)
            ->__set('recipient', $to)
            ->__set('headers', $hdrs)
            ->__set('body', $body)
            ->__set('deleteAfterSend', ($delete_after_send ? 1 : 0));

        $this->em->persist($queueRecord);
        $this->em->flush();

        return $queueRecord->__get('id');
    }

    /**
     * Check how many times mail was sent.
     *
     * @param Body $mail object
     *
     * @return mixed  Integer or false if error.
     */
    public function countSend(Body $mail)
    {
        $count = $mail->_try();

        $mailRecord = $this->repo->find($mail->getId());

        if (null == $mailRecord) {
            throw new Exception(
                sprintf($this->errorMsg, $mail->getId(), 'no message with id'),
                Queue::ERROR_QUERY_FAILED
            );
        }

        $mailRecord->__set('trySent',$count);
        $this->em->persist($mailRecord);
        $this->em->flush();


        return $count;

        // TODO: Implement countSend() method.
    }

    /**
     * Set mail as already sent.
     *
     * @param Body $mail object
     *
     * @return bool
     */
    public function setAsSent(Body $mail)
    {
        $mailRecord = $this->repo->find($mail->getId());

        if (null == $mailRecord) {
            throw new Exception(
                sprintf($this->errorMsg, $mail->getId(), 'no message with id'),
                Queue::ERROR_QUERY_FAILED
            );
        }

        $now = new \DateTime();
        $mailRecord->__set('sentTime',$now);

        $this->em->persist($mailRecord);
        $this->em->flush();

        return true;

    }

    /**
     * Return mail by id $id (bypass mail_queue)
     *
     * @param integer $id Mail ID
     *
     * @return mixed  Mail object or false on error.
     */
    public function getMailById($id)
    {
        $mailRecord = $this->repo->find($id);

        if (null == $mailRecord) {
            throw new Exception(
                sprintf($this->errorMsg, $id, 'no message with id'),
                Queue::ERROR_QUERY_FAILED
            );
        }

        return new Body(
            $mailRecord->getId(),
            $mailRecord->getCreateTime(),
            $mailRecord->getTimeToSend(),
            $mailRecord->getTimeToSend(),
            $mailRecord->getIdUser(),
            $mailRecord->getIp(),
            $mailRecord->getSender(),
            $this->_isSerialized($mailRecord->getRecipient()) ? unserialize($mailRecord->getRecipient()) : $mailRecord->getRecipient(),
            unserialize($mailRecord->getHeaders()),
            unserialize($mailRecord->getBody()),
            $mailRecord->getDeleteAfterSend(),
            $mailRecord->getTrySent()
        );
    }

    /**
     * Return the number of emails currently in the queue.
     *
     * @return int
     * @throws Exception
     */
    public function getQueueCount()
    {
        $count = $this->repo->getQueueCount();
        return (int) $count;

        // TODO: Implement getQueueCount() method.
    }

    /**
     * Remove from queue mail with $id identifier.
     *
     * @param integer $id Mail ID
     *
     * @return bool  True on success ale false.
     */
    public function deleteMail($id)
    {
        $mailRecord = $this->repo->find($id);

        if (null == $mailRecord) {
            throw new Exception(
                sprintf($this->errorMsg, $id, 'no message with id'),
                Queue::ERROR_QUERY_FAILED
            );
        }

        $this->em->remove($mailRecord);
        $this->em->flush();

        return true;
    }
}
