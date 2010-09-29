<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
/**
 * +----------------------------------------------------------------------+
 * | PEAR :: Mail :: Queue :: DB Container                                |
 * +----------------------------------------------------------------------+
 * | Copyright (c) 1997-2004 The PHP Group                                |
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
 */

/**
 * Storage driver for fetching mail queue data from a PEAR_DB database
 *
 * This storage driver can use all databases which are supported
 * by the PEAR DB abstraction layer.
 *
 * PHP Version 4 and 5
 *
 * @category Mail
 * @package  Mail_Queue
 * @author   Radek Maciaszek <chief@php.net>
 * @author   Lorenzo Alberton <l dot alberton at quipo dot it>
 * @version  CVS: $Id$
 * @license  http://www.opensource.org/licenses/bsd-license.php The BSD License
 * @link     http://pear.php.net/package/Mail_Queue
 */
require_once 'DB.php';
require_once 'Mail/Queue/Container.php';

/**
 * Mail_Queue_Container_db - Storage driver for fetching mail queue data
 * from a PEAR_DB database
 *
 * @category Mail
 * @package  Mail_Queue
 * @author   Radek Maciaszek <chief@php.net>
 * @author   Lorenzo Alberton <l dot alberton at quipo dot it>
 * @license  http://www.opensource.org/licenses/bsd-license.php The BSD License
 * @version  Release: @package_version@
 * @link     http://pear.php.net/package/Mail_Queue
 */
class Mail_Queue_Container_db extends Mail_Queue_Container
{
    // {{{ class vars

    /**
     * Reference to the current database connection.
     * @var object PEAR_DB
     */
    var $db;

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
    // {{{ __construct()

    function __construct($options)
    {
        return $this->Mail_Queue_Container_db($options);
    }

    // }}}
    // {{{ Mail_Queue_Container_db()

    /**
     * Constructor
     *
     * Mail_Queue_Container_db()
     *
     * @param mixed $options    An associative array of option names and
     *                          their values. See DB_common::setOption
     *                          for more information about connection options.
     *
     * @access public
     */
    function Mail_Queue_Container_db($options)
    {
        if (!is_array($options) || !isset($options['dsn'])) {
            return new Mail_Queue_Error(MAILQUEUE_ERROR_NO_OPTIONS,
                $this->pearErrorMode, E_USER_ERROR, __FILE__, __LINE__,
                'No dns specified!');
        }
        if (isset($options['mail_table'])) {
            $this->mail_table = $options['mail_table'];
        }
        $this->sequence = (isset($options['sequence']) ? $options['sequence'] : $this->mail_table);

        if (!empty($options['pearErrorMode'])) {
            $this->pearErrorMode = $options['pearErrorMode'];
        }
        $dsn = array_key_exists('dsn', $options) ? $options['dsn'] : $options;
        $this->db =& DB::connect($dsn, true);
        if (PEAR::isError($this->db)) {
            return new Mail_Queue_Error(MAILQUEUE_ERROR_CANNOT_CONNECT,
                $this->pearErrorMode, E_USER_ERROR, __FILE__, __LINE__,
                'DB::connect failed: '. DB::errorMessage($this->db));
        } else {
            $this->db->setFetchMode(DB_FETCHMODE_ASSOC);
        }
        $this->setOption();
    }

    // }}}
    // {{{ _preload()

    /**
     * Preload mail to queue.
     *
     * @return mixed  True on success else Mail_Queue_Error object.
     * @access private
     */
    function _preload()
    {
        if (!is_object($this->db) || !is_a($this->db, 'DB_Common')) {
            $msg = 'DB::connect failed';
            if (PEAR::isError($this->db)) {
                $msg .= ': '.DB::errorMessage($this->db);
            }
            return new Mail_Queue_Error(MAILQUEUE_ERROR_CANNOT_CONNECT,
                $this->pearErrorMode, E_USER_ERROR, __FILE__, __LINE__, $msg);
        }
        $query = sprintf("SELECT * FROM %s
                           WHERE sent_time IS NULL
                             AND try_sent < %d
                             AND time_to_send <= %s
                        ORDER BY time_to_send",
                         $this->mail_table,
                         $this->try,
                         $this->db->quote(date('Y-m-d H:i:s'))
                         );
        $res = $this->db->limitQuery($query, $this->offset, $this->limit);
        if (PEAR::isError($res)) {
            return new Mail_Queue_Error(MAILQUEUE_ERROR_QUERY_FAILED,
                $this->pearErrorMode, E_USER_ERROR, __FILE__, __LINE__,
                'DB::query failed - "'.$query.'" - '.$res->toString());
        }

        $this->_last_item = 0;
        $this->queue_data = array(); //reset buffer
        while ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
            if (!is_array($row)) {
                return new Mail_Queue_Error(MAILQUEUE_ERROR_QUERY_FAILED,
                    $this->pearErrorMode, E_USER_ERROR, __FILE__, __LINE__,
                    'DB::query failed - "'.$query.'" - '.$res->toString());
            }

            $delete_after_send = (bool) $row['delete_after_send'];

            $this->queue_data[$this->_last_item] = new Mail_Queue_Body(
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
     * @return mixed  ID of the record where this mail has been put
     *                or Mail_Queue_Error on error
     * @access public
     **/
    function put($time_to_send, $id_user, $ip, $sender,
                $recipient, $headers, $body, $delete_after_send=true)
    {
        if (!is_object($this->db) || !is_a($this->db, 'DB_Common')) {
            $msg = 'DB::connect failed';
            if (PEAR::isError($this->db)) {
                $msg .= ': '.DB::errorMessage($this->db);
            }
            return new Mail_Queue_Error(MAILQUEUE_ERROR_CANNOT_CONNECT,
                $this->pearErrorMode, E_USER_ERROR, __FILE__, __LINE__, $msg);
        }
        $id = $this->db->nextId($this->sequence);
        if (empty($id) || PEAR::isError($id)) {
            return new Mail_Queue_Error(MAILQUEUE_ERROR,
                $this->pearErrorMode, E_USER_ERROR, __FILE__, __LINE__,
                'Cannot create id in: '.$this->sequence);
        }
        $query = sprintf("INSERT INTO %s (id, create_time, time_to_send, id_user, ip,
                        sender, recipient, headers, body, delete_after_send)
                        VALUES(%d, '%s', '%s', %d, '%s', '%s', '%s', '%s', '%s', %d)",
                         $this->mail_table,
                         $id,
                         addslashes(date('Y-m-d H:i:s')),
                         addslashes($time_to_send),
                         addslashes($id_user),
                         addslashes($ip),
                         addslashes($sender),
                         addslashes($recipient),
                         addslashes($headers),
                         addslashes($body),
                         $delete_after_send
        );

        $res = $this->db->query($query);
        if (PEAR::isError($res)) {
            return new Mail_Queue_Error(MAILQUEUE_ERROR_QUERY_FAILED,
                $this->pearErrorMode, E_USER_ERROR, __FILE__, __LINE__,
                'DB::query failed - "'.$query.'" - '.$res->toString());
        }
        return $id;
    }

    // }}}
    // {{{ countSend()

    /**
     * Check how many times mail was sent.
     *
     * @param object   Mail_Queue_Body
     * @return mixed  Integer or Mail_Queue_Error class if error.
     * @access public
     */
    function countSend($mail)
    {
        if (!is_object($mail) || !is_a($mail, 'mail_queue_body')) {
            return new Mail_Queue_Error(MAILQUEUE_ERROR_UNEXPECTED, __FILE__, __LINE__);
        }
        $count = $mail->_try();
        $query = sprintf("UPDATE %s SET try_sent = %d WHERE id = %d",
                         $this->mail_table,
                         $count,
                         $mail->getId()
        );

        $res = $this->db->query($query);
        if (PEAR::isError($res)) {
            return new Mail_Queue_Error(MAILQUEUE_ERROR_QUERY_FAILED,
                $this->pearErrorMode, E_USER_ERROR, __FILE__, __LINE__,
                'DB::query failed - "'.$query.'" - '.$res->toString());
        }
        return $count;
    }

    // }}}
    // {{{ setAsSent()

    /**
     * Set mail as already sent.
     *
     * @param object Mail_Queue_Body object
     * @return bool
     * @access public
     */
    function setAsSent($mail)
    {
        if (!is_object($mail) || !is_a($mail, 'mail_queue_body')) {
            return new Mail_Queue_Error(MAILQUEUE_ERROR_UNEXPECTED,
                $this->pearErrorMode, E_USER_ERROR, __FILE__, __LINE__,
                'Expected: Mail_Queue_Body class');
        }
        $query = sprintf("UPDATE %s SET sent_time = '%s' WHERE id = %d",
                         $this->mail_table,
                         addslashes(date('Y-m-d H:i:s')),
                         $mail->getId()
        );

        $res = $this->db->query($query);
        if (PEAR::isError($res)) {
            return new Mail_Queue_Error(MAILQUEUE_ERROR_QUERY_FAILED,
                $this->pearErrorMode, E_USER_ERROR, __FILE__, __LINE__,
                'DB::query failed - "'.$query.'" - '.$res->toString());
        }
        return true;
    }

    // }}}
    // {{{ getMailById()

    /**
     * Return mail by id $id (bypass mail_queue)
     *
     * @param integer $id  Mail ID
     * @return mixed  Mail object or false on error.
     * @access public
     */
    function getMailById($id)
    {
        $query = sprintf("SELECT * FROM %s WHERE id = %d",
                         $this->mail_table,
                         (int)$id
        );
        $row = $this->db->getRow($query, null, DB_FETCHMODE_ASSOC);
        if (PEAR::isError($row) || !is_array($row)) {
            return new Mail_Queue_Error(MAILQUEUE_ERROR_QUERY_FAILED,
                $this->pearErrorMode, E_USER_ERROR, __FILE__, __LINE__,
                'DB::query failed - "'.$query.'" - '.$row->toString());
        }

        $delete_after_send = (bool) $row['delete_after_send'];

        return new Mail_Queue_Body(
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
     * @return mixed An int, or Mail_Queue_Error on failure.
     */
    function getQueueCount()
    {
        $res = $this->_checkConnection();
        if (PEAR::isError($res)) {
            return $res;
        }
        $query = 'SELECT count(*) FROM ' . $this->mail_table;
        $count = $this->db->getOne($query);
        if (PEAR::isError($count)) {
            return new Mail_Queue_Error(MAILQUEUE_ERROR_QUERY_FAILED,
                $this->pearErrorMode, E_USER_ERROR, __FILE__, __LINE__,
                'MDB2: query failed - "'.$query.'" - '.$row->getMessage());
        }
        return (int) $count;
    }

    // }}}


    /**
     * Check if there's a valid db connection
     *
     * @access private
     *
     * @return boolean|PEAR_Error on error
     * @since  1.2.4
     */
    function _checkConnection()
    {
        if (!is_object($this->db) || !is_a($this->db, 'DB_common')) {
            $msg = 'DB::connect failed';
            if (PEAR::isError($this->db)) {
                $msg .= ': '.$this->db->getMessage();
            }
            return new Mail_Queue_Error(MAILQUEUE_ERROR_CANNOT_CONNECT,
                $this->pearErrorMode, E_USER_ERROR, __FILE__, __LINE__, $msg);
        }
        return true;
    }

    // {{{ deleteMail()

    /**
     * Remove from queue mail with $id identifier.
     *
     * @param integer $id  Mail ID
     * @return bool  True on success else Mail_Queue_Error class
     *
     * @access public
     */
    function deleteMail($id)
    {
        $query = sprintf("DELETE FROM %s WHERE id = %d",
                         $this->mail_table,
                         (int)$id
        );
        $res = $this->db->query($query);

        if (PEAR::isError($res)) {
            return new Mail_Queue_Error(MAILQUEUE_ERROR_QUERY_FAILED,
                $this->pearErrorMode, E_USER_ERROR, __FILE__, __LINE__,
                'DB::query failed - "' . $query.'" - ' . $res->getMessage());
        }
        return true;
    }

    // }}}
}
?>
