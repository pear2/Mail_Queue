<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
/**
 * +----------------------------------------------------------------------+
 * | PEAR :: Mail :: Queue :: MDB2 Container                              |
 * +----------------------------------------------------------------------+
 * | Copyright (c) 1997-2008 Lorenzo Alberton                             |
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
 * | Author: Lorenzo Alberton <l.alberton at quipo.it>                    |
 * +----------------------------------------------------------------------+
 */

namespace PEAR2\Mail\Queue\Container;

use MDB2 as PearMDB2;
use PEAR2\Mail\Queue\Container;
use PEAR2\Mail\Queue\Exception;
use PEAR2\Mail\Queue;
use PEAR2\Mail\Queue\Body;

/**
 * Storage driver for fetching mail queue data from a MDB2::MDB2 database
 *
 * This storage driver can use all databases which are supported
 * by the PEAR MDB2 abstraction layer.
 *
 * @category Mail
 * @package  Mail_Queue
 * @author   Lorenzo Alberton <l dot alberton at quipo dot it>
 * @version  Release: @package_version@
 * @license  http://www.opensource.org/licenses/bsd-license.php The BSD License
 * @link     http://pear.php.net/package/Mail_Queue
 */
class mdb2 extends Container
{
    // {{{ class vars

    /**
     * Reference to the current database connection.
     * @var object MDB2::MDB2 instance
     */
    var $db = null;

    var $errorMsg = 'MDB2::query() failed: "%s", %s';

    /**
     * Table for sql database
     * @var  string
     */
    var $mail_table = 'mail_queue';

    /**
     * @var string  the name of the sequence for this table
     */
    var $sequence = null;

    // }}}
    // {{{ Mail_Queue_Container_mdb2()

    /**
     * Constructor
     *
     * Mail_Queue_Container_mdb2()
     *
     * @param array $options    An associative array of connection option.
     *
     * @return mdb2
     */
    public function __construct(array $options)
    {
        if (!isset($options['dsn'])) {
            throw new Exception(
                'No dns specified!',
                Queue::ERROR_NO_OPTIONS
            );
        }
        if (isset($options['mail_table'])) {
            $this->mail_table = $options['mail_table'];
        }
        $this->sequence = (isset($options['sequence']) ? $options['sequence'] : $this->mail_table);

        $dsn = $options['dsn'];
        $res = $this->_connect($dsn);
        if (PearMDB2::isError($res)) {
            throw new Exception(
                $res->getMessage(),
                $res->getCode()
            );
        }

        $this->setOption();
    }

    // }}}
    // {{{ _connect()

    /**
     * Connect to database by using the given DSN string
     *
     * @param mixed &$db DSN string | array | MDB2 object
     *
     * @return boolean
     * @throws Exception
     */
    protected function _connect(&$db)
    {
        if (is_object($db) && is_a($db, 'MDB2_Driver_Common')) {
            $this->db = &$db;
        } elseif (is_string($db) || is_array($db)) {
            include_once 'MDB2.php';
            $this->db = PearMDB2::connect($db);
        } elseif (is_object($db) && PearMDB2::isError($db)) {
            throw new Exception(
                'MDB2::connect failed: '. $this->_getErrorMessage($this->db),
                Queue::ERROR_CANNOT_CONNECT
            );
        } else {
            throw new Exception(
                'The given dsn was not valid in file '. __FILE__ . ' at line ' . __LINE__,
                Queue::ERROR_CANNOT_CONNECT
            );
        }
        if (PearMDB2::isError($this->db)) {
            throw new Exception(
                sprintf('DB Error: %s, %s', $this->db->getMessage(), $this->db->getUserInfo()),
                Queue::ERROR_CANNOT_CONNECT
            );
        }
        return true;
    }

    // }}}
    // {{{ _checkConnection()

    /**
     * Check if there's a valid db connection
     *
     * @return boolean
     * @throws Exception
     */
    protected function _checkConnection()
    {
        if (!is_object($this->db) || !is_a($this->db, 'MDB2_Driver_Common')) {
            $msg = 'MDB2::connect failed';
            if (PearMDB2::isError($this->db)) {
                $msg .= $this->_getErrorMessage($this->db);
            }
            throw new Exception($msg, Queue::ERROR_CANNOT_CONNECT);
        }
        return true;
    }

    /**
     * Create a more useful error message from DB related errors.
     *
     * @param PEAR_Error $errorObj A PEAR_Error object.
     *
     * @return string
     */
    protected function _getErrorMessage($errorObj)
    {
        if (!PearMDB2::isError($errorObj)) {
            return '';
        }
        $msg   = ': ' . $errorObj->getMessage();
        $debug = $errorObj->getDebugInfo();

        if (!empty($debug)) {
            $msg .= ", DEBUG: {$debug}";
        }
        return $msg;
    }

    // }}}
    // {{{ _preload()

    /**
     * Preload mail to queue.
     *
     * @return true
     * @throws Exception
     */
    protected function _preload()
    {
        $this->_checkConnection();

        $query = 'SELECT * FROM ' . $this->mail_table
                . ' WHERE'
                . ' sent_time is NULL AND'
                . ' try_sent < '. $this->db->quote($this->try, 'integer') . ' AND'
                . ' time_to_send <= '.$this->db->quote(date('Y-m-d H:i:s'), 'timestamp')
                . ' ORDER BY time_to_send';

        $this->db->setLimit($this->limit, $this->offset);
        $res = $this->db->query($query);
        if (PearMDB2::isError($res)) {
            throw new Exception(
                sprintf($this->errorMsg, $query, $this->_getErrorMessage($res)),
                Queue::ERROR_QUERY_FAILED
            );
        }

        $this->_last_item = 0;
        $this->queue_data = array(); //reset buffer

        while ($row = $res->fetchRow(\MDB2_FETCHMODE_ASSOC)) {
            if (!is_array($row)) {
                throw new Exception(
                    sprintf($this->errorMsg, $query, $this->_getErrorMessage($res)),
                    Queue::ERROR_QUERY_FAILED
                );
            }

            $delete_after_send = (bool) $row['delete_after_send'];

            $this->queue_data[$this->_last_item] = new Body(
                $row['id'],
                $row['create_time'],
                $row['time_to_send'],
                $row['sent_time'],
                $row['id_user'],
                $row['ip'],
                $row['sender'],
                $this->_isSerialized($row['recipient']) ? unserialize($row['recipient']) : $row['recipient'],
                unserialize($row['headers']),
                unserialize($row['body']),
                $delete_after_send,
                $row['try_sent']
            );
            $this->_last_item++;
        }

        return true;
    }

    // }}}
    // {{{ put()

    /**
     * Put new mail in queue and save in database.
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
     * @param bool $delete_after_send  Delete or not mail from db after send
     *
     * @return mixed  ID of the record where this mail has been put.
     */
    public function put($time_to_send, $id_user, $ip, $sender,
                $recipient, $headers, $body, $delete_after_send=true)
    {
        $this->_checkConnection();

        $id = $this->db->nextID($this->sequence);
        if (empty($id) || PearMDB2::isError($id)) {
            throw new Exception(
                'Cannot create id in: ' . $this->sequence,
                Queue::ERROR
            );
        }

        $query = 'INSERT INTO '. $this->mail_table
                .' (id, create_time, time_to_send, id_user, ip'
                .', sender, recipient, headers, body, delete_after_send) VALUES ('
                .       $this->db->quote($id, 'integer')
                .', ' . $this->db->quote(date('Y-m-d H:i:s'), 'timestamp')
                .', ' . $this->db->quote($time_to_send, 'timestamp')
                .', ' . $this->db->quote($id_user, 'integer')
                .', ' . $this->db->quote($ip, 'text')
                .', ' . $this->db->quote($sender, 'text')
                .', ' . $this->db->quote($recipient, 'text')
                .', ' . $this->db->quote($headers, 'text')   //clob
                .', ' . $this->db->quote($body, 'text')      //clob
                .', ' . ($delete_after_send ? 1 : 0)
                .')';
        $res = $this->db->query($query);
        if (PearMDB2::isError($res)) {
            throw new Exception(
                sprintf($this->errorMsg, $query, $this->_getErrorMessage($res)),
                Queue::ERROR_QUERY_FAILED
            );
        }
        return $id;
    }

    // }}}
    // {{{ countSend()

    /**
     * Check how many times mail was sent.
     *
     * @param Body $mail
     *
     * @return mixed  Integer
     */
    public function countSend(Body $mail)
    {
        $this->_checkConnection();

        $count = $mail->_try();
        $query = 'UPDATE ' . $this->mail_table
                .' SET try_sent = ' . $this->db->quote($count, 'integer')
                .' WHERE id = '     . $this->db->quote($mail->getId(), 'integer');
        $res = $this->db->query($query);
        if (PearMDB2::isError($res)) {
            throw new Exception(
                sprintf($this->errorMsg, $query, $this->_getErrorMessage($res)),
                Queue::ERROR_QUERY_FAILED
            );
        }

        return $count;
    }

    // }}}
    // {{{ setAsSent()

    /**
     * Set mail as already sent.
     *
     * @param Body $mail object
     *
     * @return bool
     * @throws Exception
     */
    public function setAsSent(Body $mail)
    {
        $this->_checkConnection();

        $query = 'UPDATE ' . $this->mail_table
                .' SET sent_time = '.$this->db->quote(date('Y-m-d H:i:s'), 'timestamp')
                .' WHERE id = '. $this->db->quote($mail->getId(), 'integer');

        $res = $this->db->query($query);
        if (PearMDB2::isError($res)) {
            throw new Exception(
                sprintf($this->errorMsg, $query, $this->_getErrorMessage($res)),
                Queue::ERROR_QUERY_FAILED
            );
        }

        return true;
    }

    // }}}
    // {{{ getMailById()

    /**
     * Return mail by id $id (bypass mail_queue)
     *
     * @param integer $id  Mail ID
     *
     * @return Body
     * @throws Exception
     */
    public function getMailById($id)
    {
        $this->_checkConnection();

        $query = sprintf(
            'SELECT * FROM %s WHERE id = %d',
            $this->mail_table,
            (int) $id
        );

        $row = $this->db->queryRow($query, null, MDB2_FETCHMODE_ASSOC);
        if (PearMDB2::isError($row)) {
            throw new Exception(
                sprintf($this->errorMsg, $query, $this->_getErrorMessage($row)),
                Queue::ERROR_QUERY_FAILED
            );
        }

        if (!is_array($row)) {
            throw new Exception(
                sprintf($this->errorMsg, $query, 'no such message'),
                Queue::ERROR_QUERY_FAILED
            );
        }

        $delete_after_send = (bool) $row['delete_after_send'];

        return new Body(
            $row['id'],
            $row['create_time'],
            $row['time_to_send'],
            $row['sent_time'],
            $row['id_user'],
            $row['ip'],
            $row['sender'],
            $this->_isSerialized($row['recipient']) ? unserialize($row['recipient']) : $row['recipient'],
            unserialize($row['headers']),
            unserialize($row['body']),
            $delete_after_send,
            $row['try_sent']
        );
    }

    /**
     * Return the number of emails currently in the queue.
     *
     * @return int
     * @throws Exception
     */
    function getQueueCount()
    {
        $this->_checkConnection();

        $query = 'SELECT count(*) FROM ' . $this->mail_table;
        $count = $this->db->queryOne($query);
        if (PearMDB2::isError($count)) {
            throw new Exception(
                sprintf($this->errorMsg, $query, $this->_getErrorMessage($count)),
                Queue::ERROR_QUERY_FAILED
            );
        }

        return (int) $count;
    }

    // }}}
    // {{{ deleteMail()

    /**
     * Remove from queue mail with $id identifier.
     *
     * @param integer $id  Mail ID
     *
     * @return bool
     * @throws Exception
     */
    public function deleteMail($id)
    {
        $this->_checkConnection();

        $query = 'DELETE FROM ' . $this->mail_table
                .' WHERE id = ' . $this->db->quote($id, 'text');
        $res = $this->db->query($query);

        if (PearMDB2::isError($res)) {
            throw new Exception(
                sprintf($this->errorMsg, $query, $this->_getErrorMessage($res)),
                Queue::ERROR_QUERY_FAILED
            );
        }

        return true;
    }
    // }}}
}
