<?php
// +----------------------------------------------------------------------+
// | PEAR :: Mail :: Queue :: MDB Container                                       |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2002 The PHP Group                                |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.0 of the PHP license,       |
// | that is bundled with this package in the file LICENSE, and is        |
// | available at through the world-wide-web at                           |
// | http://www.php.net/license/2_02.txt.                                 |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Note: This is a MDB-oriented rewrite of Queue/Container/db.php       |
// +----------------------------------------------------------------------+
// | Author: Lorenzo Alberton <l.alberton at quipo.it>                    |
// +----------------------------------------------------------------------+
//
// $Id$


require_once 'MDB.php';
require_once 'Mail/Queue/Container.php';

/**
* PEAR/MDB Mail_Queue Container.
*
* NB: The field 'changed' has no meaning for the Cache itself. It's just there
* because it's a good idea to have an automatically updated timestamp
* field for debugging in all of your tables.
*
* A XML MDB-compliant schema example for the table needed is provided.
* Look at the file "mdb_mail_queue_schema.xml" for that.
*
* NB: Consider this first release as beta - YMMV!
*
* -------------------------------------------------------------------------
* A basic usage example:
* -------------------------------------------------------------------------
*
* $container_options = array(
*   'type'        => 'mdb',
*   'database'    => 'dbname',
*   'phptype'     => 'mysql',
*   'username'    => 'root',
*   'password'    => '',
*   'mail_table'  => 'mail_queue'
* );
*   //optionally, a 'dns' string can be provided instead of db parameters.
*   //look at MDB::connect() method or at MDB docs for details.
*
* $mail_options = array(
*   'driver'   => 'smtp',
*   'host'     => 'your_smtp_server.com',
*   'port'     => 25,
*   'auth'     => false,
*   'username' => '',
*   'password' => ''
* );
*
* $mail_queue =& new Mail_Queue($container_options, $mail_options);
* *****************************************************************
* // Here the code differentiates wrt you want to add an email to the queue
* // or you want to send the emails that already are in the queue.
* *****************************************************************
* // TO ADD AN EMAIL TO THE QUEUE
* *****************************************************************
* $from             = 'user@server.com';
* $from_name        = 'admin';
* $recipient        = 'recipient@other_server.com';
* $recipient_name   = 'recipient';
* $message          = 'Test message';
* $from_params      = empty($from_name) ? '"'.$from_name.'" <'.$from.'>' : '<'.$from.'>';
* $recipient_params = empty($recipient_name) ? '"'.$recipient_name.'" <'.$recipient.'>' : '<'.$recipient.'>';
* $hdrs = array( 'From'    => $from_params,
*                'To'      => $recipient_params,
*                'Subject' => "test message body"  );
* $mime =& new Mail_mime();
* $mime->setTXTBody($message);
* $body = $mime->get();
* $hdrs = $mime->headers($hdrs);
*
* // Put message to queue
* $mail_queue->put( $from, $recipient, $hdrs, $body );
* //Also you could put this msg in more advanced mode [look at Mail_Queue docs for details]
* $seconds_to_send = 3600;
* $delete_after_send = false;
* $id_user = 7;
* $mail_queue->put( $from, $recipient, $hdrs, $body, $seconds_to_send, $delete_after_send, $id_user );
*
* *****************************************************************
* // TO SEND EMAILS IN THE QUEUE
* *****************************************************************
* // How many mails could we send each time
* $max_ammount_mails = 50;
* $mail_queue =& new Mail_Queue($container_options, $mail_options);
* $mail_queue->sendMailsInQueue($max_ammount_mails);
* *****************************************************************
* // end usage example
* -------------------------------------------------------------------------
*
* @author   Lorenzo Alberton <l.alberton at quipo.it>
* @version  $Id$
* @package  Mail_Queue
*/

/**
* Mail_Queue_Container_mdb - Storage driver for fetching mail queue data
* from a PEAR_MDB database
*/
class Mail_Queue_Container_mdb extends Mail_Queue_Container {

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


    // {{{ Mail_Queue_Container_mdb()

    /**
     * Contructor
     *
     * Mail_Queue_Container_mdb:: Mail_Queue_Container_mdb()
     *
     * @param mixed $options    An associative array of option names and
     *                          their values. See MDB_common::setOption
     *                          for more information about connection options.
     *
     * @access public
     */
    function Mail_Queue_Container_mdb($options)
    {
        if (!is_array($options)) {
            return new Mail_Queue_Error(
                    'No options specified!', __FILE__, __LINE__);
        }
        if (isset($options['mail_table'])) {
            $this->mail_table = $options['mail_table'];
        }

        $this->db = &MDB::Connect($options);
        if(MDB::isError($this->db)) {
           return new Cache_Error('MDB::connect failed: '
                . $this->db->getMessage(), __FILE__, __LINE__);
        } else {
            $this->db->setFetchMode(MDB_FETCHMODE_ASSOC);
        }
        $this->setOption();
    }

    // }}}
    // {{{ _preload()

    /**
     * Preload mail to queue.
     *
     * @param integer  $limit   Optional - Number of mails loaded to queue
     * @param integer  $offset  Optional - You could also specify offset
     * @param boolean  $force_preload  Optional - FIXME
     * @return mixed   True on success else Mail_Queue_Error object.
     * @access private
     */
    function _preload()
    {
        if($this->_preloaded) return true;
        $this->_preloaded = true;
        $query = 'SELECT id FROM ' . $this->mail_table
                .' WHERE sent_time IS NULL AND try_sent < '. $this->try
                .' AND time_to_send < '.$this->db->getTimestampValue(date("Y-m-d H:i:s"))
                .' ORDER BY time_to_send';
        if($this->limit != MAILQUEUE_ALL) {
            $res = $this->db->limitQuery($query, null, $this->offset, $this->limit);
        } else {
            $res = $this->db->query($query);
        }

        if (MDB::isError($res)) {
            return new Mail_Queue_Error('MDB::query failed: ' .$query
                . $res->getMessage(), __FILE__, __LINE__);
        }

        while($row = $this->db->fetchInto($res, MDB_FETCHMODE_ASSOC)) {
            if(is_array($row)) {
                $this->_last_item = count($this->queue_data);
                if(MDB::isError($this->queue_data[$this->_last_item] =
                    $this->getMailById($row['id']))) {
                        return new Mail_Queue_Error('Mail_Queue_mbd:: '
                               . 'Error in method getMailById()' , __FILE__, __LINE__);
                }
            } else {
                return new Mail_Queue_Error('MDB: query failed'
                    . $res->getMessage(), __FILE__, __LINE__);
            }
        }
        @$this->db->freeResult($res);
        return true;
    }

    // }}}
    // {{{ put()

    /**
     * Put new mail in queue and save in database.
     *
     * Mail_Queue_Container::put()
     *
     * @param string  $time_to_send  When mail have to be send
     * @param integer $id_user  Sender id
     * @param string  $ip  Sender ip
     * @param string  $from  Sender e-mail
     * @param string  $to  Recipient e-mail
     * @param string  $hdrs  Mail headers (in RFC)
     * @param string  $body  Mail body (in RFC)
     * @param bool    $delete_after_send  Delete or not mail from db after send
     * @return bool   True on success
     * @access public
     **/
    function put( $time_to_send, $id_user, $ip, $sender,
                $recipient, $headers, $body, $delete_after_send = true )
    {

        $id = $this->db->nextId($this->mail_table);
        if(empty($id)) {
            return new Mail_Queue_Error('Cannot create id in: '
                     .$this->mail_table, __FILE__, __LINE__);
        }
        $query = 'INSERT INTO '. $this->mail_table
                .' (id, create_time, time_to_send, id_user, ip'
                .', sender, recipient, delete_after_send) VALUES ('
                .       $this->db->getIntegerValue($id)
                .', ' . $this->db->getTimestampValue(date("Y-m-d H:i:s"))
                .', ' . $this->db->getTimestampValue($time_to_send)
                .', ' . $this->db->getIntegerValue($id_user)
                .', ' . $this->db->getTextValue($ip)
                .', ' . $this->db->getTextValue($sender)
                .', ' . $this->db->getTextValue($recipient)
                .', ' . ($delete_after_send ? 1 : 0)
                .')';
        $res = $this->db->query($query);

        if (MDB::isError($res)) {
            return new Mail_Queue_Error('MDB::query failed: ' . $query
                . $res->getMessage(), __FILE__, __LINE__);
        }
        foreach(array('headers','body') as $field) {
            $query = 'UPDATE ' . $this->mail_table .' SET ' .$field. '=?'
                    .' WHERE id=' . $this->db->getIntegerValue($id);
            if($prepared_query = $this->db->prepareQuery($query)) {
                $char_lob = array(  'Error' => '',
                                    'Type'  => 'data',
                                    'Data'  => $$field );
                if(!MDB::isError($clob = $this->db->createLob($char_lob))) {
                    $this->db->setParamClob($prepared_query,1,$clob,$field);
                    if(MDB::isError($error = $this->db->executeQuery($prepared_query))) {
                        return new Mail_Queue_Error('MDB::query failed: ' . $query
                                . $error->getMessage(), __FILE__, __LINE__);
                    }
                    $this->db->destroyLob($clob);
                } else {
                    //creation of the handler object failed
                    return new Mail_Queue_Error('MDB::query failed: ' . $query
                              . $clob->getMessage(), __FILE__, __LINE__);
                }
                $this->db->freePreparedQuery($prepared_query);
            } else {
                //prepared query failed
                return new Mail_Queue_Error('MDB::query failed: ' . $query
                       . $clob->getMessage(), __FILE__, __LINE__);
            }

        }

        $this->_last_item = count($this->queue_data);
        $this->queue_data[$this->_last_item] = new Mail_Queue_Body(
                    $id, date("d-m-y G:i:s"),
                    $time_to_send, null, $id_user,
                    $ip, $sender, $recipient, unserialize($headers),
                    unserialize($body), $delete_after_send, 0 );
        return true;
    }

    // }}}
    // {{{ countSend()

    /**
     * Check how many times mail was sent.
     *
     * @param object  Mail_Queue_Body
     * @return mixed  Integer or Mail_Queue_Error class if error.
     * @access public
     */
    function countSend($mail)
    {
        if( !is_object($mail) || get_class($mail) != 'mail_queue_body' ) {
            return new Mail_Queue_Error('Expected: Mail_Queue_Body class' ,
                                        __FILE__, __LINE__);
        }
        $count = $mail->try();
        $query = 'UPDATE ' . $this->mail_table
                .' SET try_sent = ' . $this->db->getIntegerValue($count)
                .' WHERE id = '     . $this->db->getIntegerValue($mail->getId());
        $res = $this->db->query($query);

        if (MDB::isError($res)) {
            return new Mail_Queue_Error('MDB::query failed: '
                . $res->getMessage(), __FILE__, __LINE__);
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
    function setAsSent($mail) {
        if(!is_object($mail) || get_class($mail) != 'mail_queue_body') {
            return new Mail_Queue_Error('Expected: Mail_Queue_Body class' ,
                                        __FILE__, __LINE__);
        }
        $query = 'UPDATE ' . $this->mail_table
                .' SET sent_time = '.$this->db->getTimestampValue(date("Y-m-d H:i:s"))
                .' WHERE id = '. $this->db->getIntegerValue($mail->getId());

        $res = $this->db->query($query);

        if (MDB::isError($res)) {
            return new Mail_Queue_Error('MDB::query failed: '
                . $res->getMessage(), __FILE__, __LINE__);
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
    function getMailById($id) {
        $query = 'SELECT id, create_time, time_to_send, sent_time'
                .', id_user, ip, sender, recipient, delete_after_send'
                .', try_sent FROM ' . $this->mail_table
                .' WHERE id = '     . $this->db->getTextValue($id);
        $res = $this->db->query($query);
        if (MDB::isError($res)) {
            return new Mail_Queue_Error('MDB::query failed: '
                . $res->getMessage(), __FILE__, __LINE__);
        }
        $row = $this->db->fetchRow($res, MDB_FETCHMODE_ASSOC);
        if(is_array($row)) {
            //now fetch lobs...
            foreach(array('headers','body') as $field) {
                $query = 'SELECT '.$field.' FROM ' . $this->mail_table
                        .' WHERE id=' . $this->db->getIntegerValue($id);
                if($res = $this->db->query($query)) {
                    if($this->db->endOfResult($res)) {
                        //no rows returned
                        $row[$field] = '';
                    } else {
                        $clob = $this->db->fetchClob($res,0,$field);
                        if(!MDB::isError($clob)) {
                            $row[$field] = '';
                            while(!$this->db->endOfLOB($clob)) {
                                if(MDB::isError($error =
                                            $this->db->readLob($clob,$data,8000)<0)) {
                                    return new Mail_Queue_Error('MDB::query failed: '
                                            . $error->getMessage(), __FILE__, __LINE__);
                                }
                                $row[$field] .= $data;
                            }
                            unset($data);
                            $this->db->destroyLob($clob);
                            //$this->db->freeResult($res);
                        } else {
                            return new Mail_Queue_Error('MDB::query failed: '
                                     . $clob->getMessage(), __FILE__, __LINE__);
                        }
                    }
                } else {
                    //return new Mail_Queue_Error('MDB::query failed: '
                    //          . $result->getMessage(), __FILE__, __LINE__);
                    $row[$field] = ''; //Not sure if this is better than raising the error...
                }
            }

            $this->_last_item = count($this->queue_data);
            return new Mail_Queue_Body( $row['id'], $row['create_time'],
                    $row['time_to_send'], $row['sent_time'], $row['id_user'],
                    $row['ip'], $row['sender'], $row['recipient'],
                    unserialize($row['headers']), unserialize($row['body']),
                    $row['delete_after_send'], $row['try_sent'] );
        } else {
            return new Mail_Queue_Error('MDB:: error in query: ' . $query
                . $res->getMessage(), __FILE__, __LINE__);
        }
    }

    // }}}
    // {{{ deleteMail()

    /**
     * Remove from queue mail with $id identifier.
     *
     * @param integer $id  Mail ID
     * @return bool  True on success else Mail_Queue_Error class
     * @access public
     */
    function deleteMail($id) {
        $query = 'DELETE FROM ' . $this->mail_table
                .' WHERE id = ' . $this->db->getTextValue($id);
        $res = $this->db->query($query);
        if (MDB::isError($res)) {
            return new Mail_Queue_Error('MDB::query failed: '
                . $res->getMessage(), __FILE__, __LINE__);
        }
        return true;
    }
}
?>