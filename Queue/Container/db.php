<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
// +----------------------------------------------------------------------+
// | PHP Version 4                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2003 The PHP Group                                |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.0 of the PHP license,       |
// | that is bundled with this package in the file LICENSE, and is        |
// | available at through the world-wide-web at                           |
// | http://www.php.net/license/2_02.txt.                                 |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Authors: Radek Maciaszek <chief@php.net>                             |
// +----------------------------------------------------------------------+
//
// $Id$

/**
 * Storage driver for fetching mail queue data from a PEAR_DB database
 *
 * This storage driver can use all databases which are supported
 * by the PEAR DB abstraction layer.
 *
 * @author   Radek Maciaszek <chief@php.net>
 * @package  Mail_Queue
 * @version  $Revision$
 */

require_once 'DB.php';
require_once 'Mail/Queue/Container.php';

/**
* Mail_Queue_Container_db - Storage driver for fetching mail queue data
* from a PEAR_DB database
*
* @author   Radek Maciaszek <chief@php.net>
* @version  $Id$
* @package  Mail_Queue
* @access   public
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
    // {{{ Mail_Queue_Container_db()

    /**
     * Contructor
     *
     * Mail_Queue_Container_db:: Mail_Queue_Container_db()
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
        if (isset($options['sequence'])) {
            $this->sequence = $options['sequence'];
        } else {
            $this->sequence = $this->mail_table;
        }
        if (!empty($options['pearErrorMode'])) {
            $this->pearErrorMode = $options['pearErrorMode'];
        }
        $this->db =& DB::connect($options['dsn'], true);
        if (DB::isError($this->db)) {
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
     * @param integer  $limit  Optional - Number of mails loaded to queue
     * @param integer  $offset  Optional - You could also specify offset
     * @param boolean  $force_preload  Optional - FIXME
     *
     * @return mixed  True on success else Mail_Queue_Error object.
     *
     * @access private
     */
    function _preload()
    {
        if ($this->_preloaded) {
            return true;
        }
        $this->_preloaded = true;
        $query = sprintf("SELECT * FROM %s WHERE sent_time IS NULL
                            AND try_sent < %d
                            AND now() > time_to_send
                            ORDER BY time_to_send",
                         $this->mail_table,
                         $this->try
                         );
        if ($this->limit != MAILQUEUE_ALL) {
            $query .= " LIMIT $this->offset, $this->limit";
        }
        $res = $this->db->query($query);

        if (DB::isError($res)) {
            return new Mail_Queue_Error(MAILQUEUE_ERROR_QUERY_FAILED,
                        $this->pearErrorMode, E_USER_ERROR, __FILE__, __LINE__,
                        'DB::query failed - "'.$query.'" - '.DB::errorMessage($res));
        }
        while ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
            if (is_array($row)) {
                $this->_last_item = count($this->queue_data);
                $this->queue_data[$this->_last_item] =
                    new Mail_Queue_Body( $row['id'], $row['create_time'],
                        $row['time_to_send'], $row['sent_time'], $row['id_user'],
                        $row['ip'], $row['sender'], $row['recipient'],
                        unserialize($row['headers']),
                        unserialize($row['body']), $row['delete_after_send'], $row['try_sent'] );
            } else {
                return new Mail_Queue_Error(MAILQUEUE_ERROR_QUERY_FAILED,
                        $this->pearErrorMode, E_USER_ERROR, __FILE__, __LINE__,
                        'DB::query failed - "'.$query.'" - '.DB::errorMessage($res));
            }
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

        $id = $this->db->nextId($this->sequence);
        if (empty($id)) {
            return new Mail_Queue_Error(MAILQUEUE_ERROR,
                        $this->pearErrorMode, E_USER_ERROR, __FILE__, __LINE__,
                        'Cannot create id in: '.$this->sequence);
        }
        $query = sprintf("INSERT INTO %s(id, create_time, time_to_send, id_user, ip,
                        sender, recipient, headers, body, delete_after_send)
                        VALUES('%s', now(), '%s', %d, '%s', '%s', '%s', '%s', '%s', %d )",
                         $this->mail_table,
                         $id,
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

        if (DB::isError($res)) {
            return new Mail_Queue_Error(MAILQUEUE_ERROR_QUERY_FAILED,
                        $this->pearErrorMode, E_USER_ERROR, __FILE__, __LINE__,
                        'DB::query failed - "'.$query.'" - '.DB::errorMessage($res));
        }
        $this->_last_item = count($this->queue_data);
		$this->queue_data[$this->_last_item] = new Mail_Queue_Body( $id, date("d-m-y G:i:s"),
                    $time_to_send, null, $id_user,
                    $ip, $sender, $recipient, unserialize($headers),
                    unserialize($body), $delete_after_send, 0 );
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
        if (!is_object($mail) || get_class($mail) != 'mail_queue_body') {
            return new Mail_Queue_Error('Expected: Mail_Queue_Body class' , __FILE__, __LINE__);
        }
        $count = $mail->try();
        $query = sprintf("UPDATE %s SET try_sent = %d WHERE id = %d",
                         $this->mail_table,
                         $count,
                         $mail->getId()
                        );

        $res = $this->db->query($query);

        if (DB::isError($res)) {
            return new Mail_Queue_Error(MAILQUEUE_ERROR_QUERY_FAILED,
                        $this->pearErrorMode, E_USER_ERROR, __FILE__, __LINE__,
                        'DB::query failed - "'.$query.'" - '.DB::errorMessage($res));
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
        if (!is_object($mail) || get_class($mail) != 'mail_queue_body') {
            return new Mail_Queue_Error(MAILQUEUE_ERROR_UNEXPECTED,
                        $this->pearErrorMode, E_USER_ERROR, __FILE__, __LINE__,
                        'Expected: Mail_Queue_Body class');
        }
        $query = sprintf("UPDATE %s SET sent_time = now() WHERE id = %d",
                         $this->mail_table,
                         $mail->getId()
                        );

        $res = $this->db->query($query);

        if (DB::isError($res)) {
            return new Mail_Queue_Error(MAILQUEUE_ERROR_QUERY_FAILED,
                        $this->pearErrorMode, E_USER_ERROR, __FILE__, __LINE__,
                        'DB::query failed - "'.$query.'" - '.DB::errorMessage($res));
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
                         addslashes($id)
                         );
        $res = $this->db->query($query);

        if (DB::isError($res)) {
            return new Mail_Queue_Error(MAILQUEUE_ERROR_QUERY_FAILED,
                        $this->pearErrorMode, E_USER_ERROR, __FILE__, __LINE__,
                        'DB::query failed - "'.$query.'" - '.DB::errorMessage($res));
        }
        $row = $res->fetchRow(DB_FETCHMODE_ASSOC);
        if (is_array($row)) {
            $this->_last_item = count($this->queue_data);
            return new Mail_Queue_Body( $row['id'], $row['create_time'],
                    $row['time_to_send'], $row['sent_time'], $row['id_user'],
                    $row['ip'], $row['sender'], $row['recipient'], unserialize($row['headers']),
                    unserialize($row['body']), $row['delete_after_send'], $row['try_sent'] );
        } else {
            return new Mail_Queue_Error(MAILQUEUE_ERROR_QUERY_FAILED,
                        $this->pearErrorMode, E_USER_ERROR, __FILE__, __LINE__,
                        'DB::query failed - "'.$query.'" - '.DB::errorMessage($res));
        }
    }

    // }}}
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
                         addslashes($id)
                         );
        $res = $this->db->query($query);

        if (DB::isError($res)) {
            return new Mail_Queue_Error(MAILQUEUE_ERROR_QUERY_FAILED,
                        $this->pearErrorMode, E_USER_ERROR, __FILE__, __LINE__,
                        'DB::query failed - "'.$query.'" - '.DB::errorMessage($res));
        }
        return true;
    }

    // }}}
}
?>