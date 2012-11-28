<?php

namespace PEAR2\Mail\Queue\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(  name="mail_table")
 * use repository for handy tree functions
 * @ORM\Entity(repositoryClass="PEAR2\Mail\Queue\Entity\Repository\MailRepository")
 */
class Mail
{

    /**
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue
     */
    private $id;

    /**
     * @var \DateTime $createTime
     *
     * @ORM\Column(name="create_time", type="datetime")
     */
    private $createTime;


    /**
     * @var \DateTime $timeToSend
     *
     * @ORM\Column(name="time_to_send", type="datetime")
     */
    private $timeToSend;

    /**
     * @var \DateTime $timeToSend
     *
     * @ORM\Column(name="sent_time", type="datetime", nullable=true)
     */
    private $sentTime;

    /**
     * @ORM\Column(name="id_user", type="integer")
     */
    private $idUser;

    /**
     * @ORM\Column(name="ip", type="string")
     */
    private $ip;

    /**
     * @ORM\Column(name="sender", type="string")
     */
    private $sender;

    /**
     * @ORM\Column(name="recipient", type="string")
     */
    private $recipient;

    /**
     * @ORM\Column(name="headers", type="text")
     */
    private $headers;

    /**
     * @ORM\Column(name="body", type="text")
     */
    private $body;

    /**
     * @ORM\Column(name="delete_after_send", type="boolean")
     */
    private $deleteAfterSend;

    /**
     * @ORM\Column(name="try_sent", type="integer", options={"default" = 0})
     */
    private $trySent;

    /**
     * construct
     */
    public function __construct()
    {
        $this->trySent = 0;
    }

    /**
     * Magic getter to expose protected properties.
     *
     * @param string $property ''
     *
     * @return mixed
     */
    public function __get($property)
    {
        return $this->$property;
    }

    /**
     * Magic setter to save protected properties.
     *
     * @param string $property ''
     * @param mixed  $value    ''
     *
     * @return void
     */
    public function __set($property, $value)
    {
        $this->$property = $value;
        return $this;
    }

    /**
     * Convert the object to an array.
     *
     * @return array
     */
    public function getArrayCopy()
    {
        return get_object_vars($this);
    }


    /**
     * get time queue item was created
     *
     * @return \DateTime
     */
    public function getCreateTime()
    {
        return $this->createTime;
    }

    /**
     * get ID
     *
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * get User ID
     *
     * @return mixed
     */
    public function getIdUser()
    {
        return $this->idUser;
    }

    /**
     * get IP
     *
     * @return mixed
     */
    public function getIp()
    {
        return $this->ip;
    }

    /**
     * get recipient
     *
     * @return mixed
     */
    public function getRecipient()
    {
        return $this->recipient;
    }

    /**
     * get sender
     *
     * @return mixed
     */
    public function getSender()
    {
        return $this->sender;
    }

    /**
     * get time mail is scheduled for
     *
     * @return \DateTime
     */
    public function getTimeToSend()
    {
        return $this->timeToSend;
    }

    /**
     * get time mail was sent
     *
     * @return \DateTime
     */
    public function getSentTime()
    {
        return $this->sentTime;
    }


    /**
     * get body
     *
     * @return mixed
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * get delete after send
     *
     * @return bool
     */
    public function getDeleteAfterSend()
    {
        return (boolean)$this->deleteAfterSend;
    }

    /**
     * get headers
     *
     * @return mixed
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * get number of tries sending
     *
     * @return mixed
     */
    public function getTrySent()
    {
        return $this->trySent;
    }
}
