<?php
namespace PEAR2\Mail\Queue\Container;

use PEAR2\Mail\Queue\Container;
use PEAR2\Mail\Queue\Exception;
use PEAR2\Mail\Queue;
use PEAR2\Mail\Queue\Body;
use PEAR2\Mail\Queue\Entity\Mail;

use Doctrine\ORM\EntityManager;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Persistence\PersistentObject;
use Doctrine\ORM\Configuration;

use Doctrine\Common\Annotations\AnnotationReader;

class doctrine2 extends Container
{

    protected $em;

    protected $entity;

    protected $config;

    /**
     * Constructor
     *
     * Mail_Queue_Container_doctrine2()
     *
     * @param array $options    An associative array of connection option.
     *
     * @return hmmm
     */
    public function __construct(array $options)
    {

        if (!isset($options['doctrine'])) {
            throw new Exception(
                'No doctrine config specified!',
                Queue::ERROR_NO_OPTIONS
            );
        }

        $this->config = $options['doctrine'];

        if (isset($options['em'])) {
            $this->em = $options['em'];
        } else {
            $this->em = $this->getEntityManager();
        }

        $this->setOption();
    }

    /**
     * Setup Doctrine Class Loaders & EntityManager
     *
     * return void
     */
    protected function init()
    {

        $config = new Configuration();
        $cache = new $this->config['cacheImplementation'];
        $entityFolder = __DIR__ . '/../Entity';
        $driverImpl = $config->newDefaultAnnotationDriver($entityFolder);

        AnnotationReader::addGlobalIgnoredName('package_version');
        $annotationReader = new AnnotationReader;
        $cachedAnnotationReader = new \Doctrine\Common\Annotations\CachedReader(
            $annotationReader, // use reader
            $cache // and a cache driver
        );

        $annotationDriver = new \Doctrine\ORM\Mapping\Driver\AnnotationDriver(
            $cachedAnnotationReader, // our cached annotation reader
            array($entityFolder) // paths to look in
        );


        $config->setMetadataDriverImpl($annotationDriver);
        $config->setMetadataCacheImpl($cache);
        $config->setQueryCacheImpl($cache);
        $config->setProxyDir(__DIR__ . '/../Proxy');
        $config->setProxyNamespace($this->config['proxy']['namespace']);
        $config->setAutoGenerateProxyClasses($this->config['autoGenerateProxyClasses']);

        $connectionConfig = $this->config['connection'];

        $this->em = EntityManager::create(
            $connectionConfig,
            $config
        );

        PersistentObject::setObjectManager($this->em);
        return;
    }

    /**
     * Get the Doctrine EntityManager
     *
     * @return Doctrine\ORM\EntityManager
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
     */
    public function setEntityManager($em)
    {
        $this->em = $em;
        return $this;
    }

    protected function _preload()
    {
        // TODO: Implement _preload() method.
    }

    /**
     * Put new mail in queue.
     *
     * Mail_Queue_Container::put()
     *
     * @param string $time_to_send  When mail have to be send
     * @param integer $id_user  Sender id
     * @param string $ip  Sender ip
     * @param string $from  Sender e-mail
     * @param string $to  Reciepient e-mail
     * @param string $hdrs  Mail headers (in RFC)
     * @param string $body  Mail body (in RFC)
     *
     * @return bool True on success
     **/
    function put($time_to_send, $id_user, $ip, $from, $to, $hdrs, $body, $delete_after_send)
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

        // TODO: Implement put() method.
    }

    /**
     * Check how many times mail was sent.
     *
     * @param Body $mail object
     *
     * @return mixed  Integer or false if error.
     */
    function countSend(Body $mail)
    {
        // TODO: Implement countSend() method.
    }

    /**
     * Set mail as already sent.
     *
     * @param Body $mail object
     *
     * @return bool
     */
    function setAsSent(Body $mail)
    {
        // TODO: Implement setAsSent() method.
    }

    /**
     * Return mail by id $id (bypass mail_queue)
     *
     * @param integer $id  Mail ID
     *
     * @return mixed  Mail object or false on error.
     */
    function getMailById($id)
    {
        $repo = $this->em->getRepository('PEAR2\Mail\Queue\Entity\Mail');
        $mailRecord = $repo->find($id);

        if (NULL == $mailRecord) {
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

    function getQueueCount()
    {
        // TODO: Implement getQueueCount() method.
    }

    /**
     * Remove from queue mail with $id identifier.
     *
     * @param integer $id  Mail ID
     *
     * @return bool  True on success ale false.
     */
    function deleteMail($id)
    {
        // TODO: Implement deleteMail() method.
    }


}
