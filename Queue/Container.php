<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
// +----------------------------------------------------------------------+
// | PHP Version 4                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997, 1998, 1999, 2000, 2001 The PHP Group             |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.0 of the PHP license,       |
// | that is bundled with this package in the file LICENSE, and is        |
// | available at through the world-wide-web at                           |
// | http://www.php.net/license/2_02.txt.                                 |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Authors: Radek Maciaszek <wodzu@tonet.pl>                            |
// +----------------------------------------------------------------------+
//
// $Id$

/**
* Container base class for MTA queue.
* Define methods for all storage containers.
*
* @version  $Revision$
* @author   Radek Maciaszek <chief@php.net>
*/

require_once "Mail/Queue/Error.php";
require_once "Mail/Queue/Body.php";

/**
* Mail_Queue_Container - base class
*
* @author   Radek Maciaszek <chief@php.net>
* @package  Mail_Queue
* @access   public
* @abstract
*/
class Mail_Queue_Container {

    /**
    * Array for mails in queue
    *
    * @var array
    */
    var $queue_data = array();

    /**
    * Key for current mail in queue
    *
    * @var integer
    * @access private
    */
    var $_current_item = 0;

    /**
    * Key for last mail in queue
    *
    * @var integer
    * @access private
    */
	var $_last_item = 0;

    /**
     * Variable to check if mails are loaded to queue
     * @var  boolean
     * @access private
     */
    var $_preloaded = false;

    /**
     * Options
     */
    var $limit;
    var $offset;
    var $try;
    var $forse_preload;
    
     /**
     * Get next mail from queue. When exclude first time preload all queue
     *
     * @return mixed  MailBody object on success else Mail_Queue_Error
     * @access    public
     */
    function get() 
    {
        $this->_preload();
        if( !empty($this->queue_data) && !$this->isEmpty() ) {
            if(!isset($this->queue_data[$this->_current_item])) {
                return new Mail_Queue_Error('No item: '.$this->_current_item
                    . ' in queue!', __FILE__, __LINE__);
            }
			$object = $this->queue_data[$this->_current_item];
			unset($this->queue_data[$this->_current_item]);
			$this->_current_item++;
			return $object;
		} else {
			return false;
		}
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
     * @return bool True on success
     * @access public
     **/
    function put($time_to_send, $id_user, $ip, $from, $to, $hdrs, $body, $delete_after_send) 
    {
        $this->_last_item = count($this->queue_data);
		$this->queue_data[$this->_last_item] = new Mail_Queue_Body( $id, date("d-m-y G:i:s"),
                    $time_to_send, null, $id_user,
                    $ip, $sender, $recipient, unserialize($headers),
                    unserialize($body), $delete_after_send, 0 );
        return true;
    }

    /**
     * Set common option
     * 
     * Mail_Queue_Container::setOption()
     * 
     * @param integer  $limit  Optional - Number of mails loaded to queue
     * @param integer  $offset  Optional - You could also specify offset
     * @param integer  $try  Optional - how many times should system try sent
     *                       each mail
     * @param boolean  $force_preload  Optional - FIXME
     * @return void
     * 
     * @access public
     **/
    function setOption($limit = MAILQUEUE_ALL, $offset = MAILQUEUE_START,
                        $try = MAILQUEUE_TRY, $forse_preload = false)
    {
        $this->limit = $limit;
        $this->offset = $offset;
        $this->try = $try;
        $this->forse_preload = $forse_preload;
    }
    
     /**
     * Check if queue is empty.
     *
     * @return boolean  True if empty else false.
     * @access public
     */
    function isEmpty() 
    {
		if($this->_current_item > $this->_last_item)
			return true;
		else
			return false;
    }

     /**
     * Check how many times mail was sent.
     *
     * @param object   MailBody
     * @return mixed  Integer or false if error.
     * @access public
     */
    function countSend( $mail ) 
    {
        return false;
    }

     /**
     * Set mail as already sent.
     *
     * @param object MailBody object
     * @return bool
     * @access public
     */
    function setAsSent( $mail ) 
    {
        return false;
    }

     /**
     * Return mail by id $id (bypass mail_queue)
     *
     * @param integer $id  Mail ID
     * @return mixed  Mail object or false on error.
     * @access public
     */
    function getMailById( $id ) 
    {
        return false;
    }

     /**
     * Remove from queue mail with $id identifier.
     *
     * @param integer $id  Mail ID
     * @return bool  True on success ale false.
     * @access public
     */
    function deleteMail( $id ) 
    {
        return false;
    }

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
    function _preload($limit = MAILQUEUE_ALL, $offset = MAILQUEUE_START,
                        $try = MAILQUEUE_TRY, $forse_preload = false) 
    {
        if($this->_preloaded) return true;
        
        $this->_preloaded = true;
        $this->_last_item = count($this->queue_data);
        return true;
    }

}

?>