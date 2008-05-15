<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
/**
 * +----------------------------------------------------------------------+
 * | PEAR :: Mail :: Queue :: Container                                   |
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

/**
 * Mail_Queue_Body
 */
require_once 'Mail/Queue/Body.php';

/**
 * Mail_Queue_Container - base class for MTA queue.
 * Define methods for all storage containers.
 *
 * @abstract
 * @category Mail
 * @package  Mail_Queue
 * @author   Radek Maciaszek <chief@php.net>
 * @author   Lorenzo Alberton <l dot alberton at quipo dot it>
 * @license  http://www.opensource.org/licenses/bsd-license.php The BSD License
 * @version  Release: @package_version@
 * @link     http://pear.php.net/package/Mail_Queue
 */
class Mail_Queue_Container
{
    // {{{ class vars

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
     * Options
     */
    var $limit;
    var $offset;
    var $try;
    var $force_preload;
    var $buffer_size = 10; //number of mails in the queue

    /**
     * Pear error mode (see PEAR doc)
     *
     * @var int $pearErrorMode
     * @access private
     */
    var $pearErrorMode = PEAR_ERROR_RETURN;

    // }}}
    // {{{ get()

    /**
     * Get next mail from queue. When exclude first time preload all queue
     *
     * @return mixed  MailBody object on success else Mail_Queue_Error
     * @access    public
     */
    function get()
    {
        if (PEAR::isError($err = $this->preload())) {
            return $err;
        }
        if (empty($this->queue_data)) {
            return false;
        }
        if (!isset($this->queue_data[$this->_current_item])) {
            //unlikely...
            return new Mail_Queue_Error(MAILQUEUE_ERROR_CANNOT_INITIALIZE,
                $this->pearErrorMode, E_USER_ERROR, __FILE__, __LINE__,
                'No item: '.$this->_current_item.' in queue!');
        }

        $object = $this->queue_data[$this->_current_item];
		unset($this->queue_data[$this->_current_item]);
		$this->_current_item++;
		return $object;
    }

    // }}}
    // {{{ skip()

    /**
     * Remove the current (problematic) mail from the buffer, but don't delete
     * it from the db: it might be a temporary issue.
     */
    function skip()
    {
        if (!empty($this->queue_data)) {
            if (isset($this->queue_data[$this->_current_item])) {
                unset($this->queue_data[$this->_current_item]);
                $this->_current_item++;
            }
        }
    }

    // }}}
    // {{{ put()

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
/*
    function put($time_to_send, $id_user, $ip, $from, $to, $hdrs, $body, $delete_after_send)
    {
        $this->_last_item = count($this->queue_data);
		$this->queue_data[$this->_last_item] = new Mail_Queue_Body($id, date("d-m-y G:i:s"),
                    $time_to_send, null, $id_user,
                    $ip, $sender, $recipient, unserialize($headers),
                    unserialize($body), $delete_after_send, 0);
        return true;
    }
*/
    // }}}
    // {{{ setOption()

    /**
     * Set common option
     *
     * Mail_Queue_Container::setOption()
     *
     * @param integer  $limit  Optional - Number of mails loaded to queue
     * @param integer  $offset Optional - You could also specify offset
     * @param integer  $try  Optional - how many times should system try sent
     *                       each mail
     * @param boolean  $force_preload  Optional - FIXME
     * @return void
     *
     * @access public
     **/
    function setOption($limit = MAILQUEUE_ALL, $offset = MAILQUEUE_START,
                        $try = MAILQUEUE_TRY, $force_preload = false)
    {
        $this->limit = $limit;
        $this->offset = $offset;
        $this->try = $try;
        $this->force_preload = $force_preload;
    }

    // }}}
    // {{{ countSend()

    /**
     * Check how many times mail was sent.
     *
     * @param object   MailBody
     * @return mixed  Integer or false if error.
     * @access public
     */
    function countSend($mail)
    {
        return false;
    }

    // }}}
    // {{{ setAsSent()

    /**
     * Set mail as already sent.
     *
     * @param object MailBody object
     * @return bool
     * @access public
     */
    function setAsSent($mail)
    {
        return false;
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
        return false;
    }

    // }}}
    // {{{ deleteMail()

    /**
     * Remove from queue mail with $id identifier.
     *
     * @param integer $id  Mail ID
     * @return bool  True on success ale false.
     * @access public
     */
    function deleteMail($id)
    {
        return false;
    }

    // }}}
    // {{{ preload()

    /**
     * Preload mail to queue.
     * The buffer size can be set in the options.
     *
     * @return mixed  True on success else Mail_Queue_Error object.
     * @access private
     */
    function preload()
    {
        if (!empty($this->queue_data)) {
            return true;
        }

        if (!$this->limit) {
            return true;   //limit reached
        }

        $bkp_limit = $this->limit;

        //set buffer size
        if ($bkp_limit == MAILQUEUE_ALL) {
            $this->limit = $this->buffer_size;
        } else {
            $this->limit = min($this->buffer_size, $this->limit);
        }

        if (Mail_Queue::isError($err = $this->_preload())) {
            return $err;
        }

        //restore limit
        if ($bkp_limit == MAILQUEUE_ALL) {
            $this->limit = MAILQUEUE_ALL;
        } else {
            $this->limit = $bkp_limit - count($this->queue_data);
        }

        //set buffer pointers
        $this->_current_item = 0;
        $this->_last_item = count($this->queue_data)-1;

        return true;
    }

    // }}}
    // {{{ _isSerialized()

    /**
     * Check if the string is a regular string or a serialized array
     *
     * @param string $string
     * @return boolean
     * @access protected
     */
    function _isSerialized($string)
    {
        if (!is_string($string) || strlen($string) < 4) {
            return false;
        }
        // serialized integer?
        if (preg_match('/^i:\d+;$/', $string)) {
            return true;
        }
        // serialized float?
        if (preg_match('/^d:\d(\.\d+)?;$/', $string)) {
            return true;
        }
        // serialized string?
        if (preg_match('/^s:\d+\:\"(.*)\";$/', $string)) {
            return true;
        }
        //serialized array?
        return preg_match('/^a:\d+\:\{(.*)\}$/', $string);
    }

    // }}}
}
?>
