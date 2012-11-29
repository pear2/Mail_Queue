<?php
namespace PEAR2\Mail\Queue\Entity\Repository;

use Doctrine\ORM\EntityRepository;

/**
 * MailRepository
 */
class MailRepository extends EntityRepository
{
    public function preload($limit, $offset, $try)
    {
        $query = $this->_em->createQuery(
            'SELECT m FROM PEAR2\Mail\Queue\Entity\Mail m'
                . ' WHERE'
                . ' m.sentTime is NULL AND'
                . ' m.trySent < :try AND'

                . ' m.timeToSend <= :timeToSend'
                . ' ORDER BY m.timeToSend'
        );

        if($limit){
          $query->setMaxResults($limit);
        }

        if($offset){
            $query->setFirstResult($offset);
        }

        $query
          ->setParameter('timeToSend', new \DateTime())
          ->setParameter('try', $try);

        return $query->getResult();

    }

    public function getQueueCount()
    {

        $query = $this->_em->createQuery('SELECT COUNT(m.id) FROM PEAR2\Mail\Queue\Entity\Mail m');
        $count = $query->getSingleScalarResult();

        return $count;
    }
}
