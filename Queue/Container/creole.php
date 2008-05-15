<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
/**
 * +----------------------------------------------------------------------+
 * | PEAR :: Mail :: Queue :: CREOLE Container                            |
 * +----------------------------------------------------------------------+
 * | Copyright (c) 1997-2008 Randy Syring                                 |
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
 * Storage driver for fetching mail queue data from a CREOLE database
 *
 * This storage driver can use all databases which are supported
 * by the CREOLE abstraction layer.
 *
 * PHP Version 5
 *
 * @category Mail
 * @package  Mail_Queue
 * @author   Randy Syring <randy at rcs-comp dot com>
 * @version  CVS: $Id$
 * @license  http://www.opensource.org/licenses/bsd-license.php The BSD License
 * @link     http://pear.php.net/package/Mail_Queue
 */
require_once 'creole/Creole.php';
require_once 'Mail/Queue/Container.php';

/**
 * Mail_Queue_Container_creole
 *
 * @category Mail
 * @package  Mail_Queue
 * @author   Randy Syring <randy at rcs-comp dot com>
 * @version  Release: @package_version@
 * @license  http://www.opensource.org/licenses/bsd-license.php The BSD License
 * @link     http://pear.php.net/package/Mail_Queue
 */
class Mail_Queue_Container_creole extends Mail_Queue_Container
{
    // {{{ class vars

    /**
     * Reference to the current database connection.
     * @var object PEAR::MDB2 instance
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

    var $constructor_error = null;

    // }}}
    // {{{ Mail_Queue_Container_Creole()

    /**
     * Constructor
     *
     * Mail_Queue_Container_creole()
     *
     * @param mixed $options    An associative array of connection option.
     *
     * @access public
     */
    function Mail_Queue_Container_creole($options)
    {
        if (!is_array($options) || (!isset($options['dsn']) && !isset( $options['connection']))) {
            $this->constructor_error =  new Mail_Queue_Error(MAILQUEUE_ERROR_NO_OPTIONS,
                $this->pearErrorMode, E_USER_ERROR, __FILE__, __LINE__,
                'No dns specified!');
            return;
        }
        if (isset($options['mail_table'])) {
            $this->mail_table = $options['mail_table'];
        }

        if (!empty($options['pearErrorMode'])) {
            $this->pearErrorMode = $options['pearErrorMode'];
        }
        try {
            $this->db = isset($options['connection']) ? $options['connection'] : Creole::getConnection($options['dsn']);
        } catch (SQLException $e) {
            $this->constructor_error = new Mail_Queue_Error(MAILQUEUE_ERROR_CANNOT_CONNECT,
                $this->pearErrorMode, E_USER_ERROR, __FILE__, __LINE__,
                'CREOLE::connect failed: '. $e->getMessage());
            return;
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
        $sql = "SELECT * FROM {$this->mail_table} WHERE sent_time IS NULL
                            AND try_sent < ?
                            AND ? > time_to_send
                            ORDER BY time_to_send";
        try {
            $stmt = $this->db->prepareStatement( $sql );
            $stmt->setLimit($this->limit);

            $stmt->setString( 1, $this->try );
            $stmt->setTimeStamp( 2, time() );
            $res = $stmt->executeQuery();

            $this->_last_item = 0;
            $this->queue_data = array(); //reset buffer
            while ($res->next()) {
                $row = $res->getRow();
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
                    $row['delete_after_send'],
                    $row['try_sent']
                );
                $this->_last_item++;
            }
        } catch (SQLException $e ) {
            return new Mail_Queue_Error(MAILQUEUE_ERROR_QUERY_FAILED,
                $this->pearErrorMode, E_USER_ERROR, __FILE__, __LINE__,
                'CREOLE::query failed - "'.$sql.'" - '.$e->getMessage());
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
     */
    function put($time_to_send, $id_user, $ip, $sender,
                $recipient, $headers, $body, $delete_after_send=true)
    {
        $query = 'INSERT INTO '. $this->mail_table
                .' (create_time, time_to_send, id_user, ip'
                .', sender, recipient, headers, body, delete_after_send) VALUES ('
                .str_repeat('? ,', 8)
                .'?)';
        try {
            $idgen = $this->db->getIdGenerator();
            $stmt = $this->db->prepareStatement($query);
            $stmt->setTimestamp(1, time());
            $stmt->setTimestamp(2, $time_to_send);
            $stmt->setInt(3, $id_user);
            $stmt->setString(4, $ip );
            $stmt->setString(5, $sender );
            $stmt->setString(6, $recipient);
            $stmt->setString(7, $headers);
            $stmt->setString(8, $body);
            $stmt->setInt(9, $delete_after_send ? 1 : 0 );
            $stmt->executeUpdate();
        } catch (SQLException $e ) {
            return new Mail_Queue_Error(MAILQUEUE_ERROR_QUERY_FAILED,
                $this->pearErrorMode, E_USER_ERROR, __FILE__, __LINE__,
                'CREOLE::query failed - "'.$query.'" - '.$e->getMessage());
        }
        return $idgen->getId();
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
            return new Mail_Queue_Error('Expected: Mail_Queue_Body class',
                __FILE__, __LINE__);
        }
        $count = $mail->_try();
        $sql = 'UPDATE '. $this->mail_table
                .' SET try_sent = ?'
                .' WHERE id = ?';
        try {
            $stmt = $this->db->prepareStatement( $sql );
            $stmt->setInt( 1, $count );
            $stmt->setInt( 2, $mail->getId() );
            $stmt->executeUpdate();
        } catch (SQLException $e ) {
            return new Mail_Queue_Error(MAILQUEUE_ERROR_QUERY_FAILED,
                $this->pearErrorMode, E_USER_ERROR, __FILE__, __LINE__,
                'CREOLE::query failed - "'.$sql.'" - '.$e->getMessage());
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

        $sql = 'UPDATE '. $this->mail_table
                .' SET sent_time = ?'
                .' WHERE id = ?';
        try {
            $stmt = $this->db->prepareStatement( $sql );
            $stmt->setTimeStamp( 1, time() );
            $stmt->setInt( 2, $mail->getId() );
            $stmt->executeUpdate();
        } catch (SQLException $e ) {
            return new Mail_Queue_Error(MAILQUEUE_ERROR_QUERY_FAILED,
                $this->pearErrorMode, E_USER_ERROR, __FILE__, __LINE__,
                'CREOLE::query failed - "'.$sql.'" - '.$e->getMessage());
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
        $sql = 'SELECT * FROM '. $this->mail_table
                .' WHERE id = ?';

        try {
            $stmt = $this->db->prepareStatement( $sql );
            $stmt->setInt( 1, $mail->getId() );
            $res = $stmt->executeQuery();

            $res->next();
            $row = $res->getRow();
        } catch (SQLException $e ) {
            return new Mail_Queue_Error(MAILQUEUE_ERROR_QUERY_FAILED,
                $this->pearErrorMode, E_USER_ERROR, __FILE__, __LINE__,
                'CREOLE::query failed - "'.$sql.'" - '.$e->getMessage());
        }

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
            $row['delete_after_send'],
            $row['try_sent']
        );
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
        $sql = 'DELETE FROM '. $this->mail_table
                .' WHERE id = ?';

        try {
            $stmt = $this->db->prepareStatement( $sql );
            $stmt->setInt( 1, $id );
            $stmt->executeUpdate();
        } catch (SQLException $e ) {
            return new Mail_Queue_Error(MAILQUEUE_ERROR_QUERY_FAILED,
                $this->pearErrorMode, E_USER_ERROR, __FILE__, __LINE__,
                'CREOLE::query failed - "'.$sql.'" - '.$e->getMessage());
        }
        return true;
    }
    
    // }}}
    // {{{ queueSize()

    /**
     * Ge the size of the queue.
     *
     * @return int|Mail_Queue_Error Queue size on success, Mail_Queue_Error on failure
     *
     * @access private
     */
    function queueSize()
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->mail_table} WHERE sent_time IS NULL
                            AND try_sent < ?
                            AND ? > time_to_send
                            ORDER BY time_to_send";
        try {
            $stmt = $this->db->prepareStatement( $sql );
            $stmt->setString( 1, $this->try );
            $stmt->setTimeStamp( 2, time() );
            $res = $stmt->executeQuery();

            $res->next();
            $result = $res->getRow();
        } catch ( SQLException $e ) {
            return new Mail_Queue_Error(MAILQUEUE_ERROR_QUERY_FAILED,
                $this->pearErrorMode, E_USER_ERROR, __FILE__, __LINE__,
                'CREOLE::query failed - "'.$sql.'" - '.$e->getMessage());
        }

        return $result['count'];
    }

    // }}}
}
?>
