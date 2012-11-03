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

namespace PEAR2\Mail\Queue;

use PEAR2\Mail\Queue;

/**
 * Mail_Queue_Container - base class for MTA queue.
 * Define methods for all storage containers.
 *
 * @category Mail
 * @package  Mail_Queue
 * @author   Radek Maciaszek <chief@php.net>
 * @author   Lorenzo Alberton <l dot alberton at quipo dot it>
 * @license  http://www.opensource.org/licenses/bsd-license.php The BSD License
 * @version  Release: @package_version@
 * @link     http://pear.php.net/package/Mail_Queue
 */
abstract class Container
{
    // {{{ class vars

    /**
     * Array for mails in queue
     *
     * @var array
     */
    public $queue_data = array();

    /**
     * Key for current mail in queue
     *
     * @var integer
     */
    protected $_current_item = 0;

    /**
     * Key for last mail in queue
     *
     * @var integer
     */
    protected $_last_item = 0;

    /**
     * Options
     */
    public $limit;
    public $offset;
    public $try;
    public $force_preload;
    public $buffer_size = 10; //number of mails in the queue

    // }}}
    // {{{ get()

    /**
     * Get next mail from queue. When exclude first time preload all queue
     *
     * @return Body
     * @throws Exception
     */
    public function get()
    {
        $err = $this->preload();
        if ($err !== true) {
            // limit met
            throw new Exception(
                'Cannot preload items: limit',
                Queue::ERROR_CANNOT_INITIALIZE
            );
        }

        if (empty($this->queue_data)) {
            return false;
        }
        if (!isset($this->queue_data[$this->_current_item])) {
            //unlikely...
            throw new Exception(
                'No item: '.$this->_current_item.' in queue!',
                Queue::ERROR_CANNOT_INITIALIZE
            );
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
     **/
    abstract function put($time_to_send, $id_user, $ip, $from, $to, $hdrs, $body, $delete_after_send);

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
     */
    public function setOption($limit = Queue::ALL, $offset = Queue::START,
                        $try = Queue::RETRY, $force_preload = false)
    {
        $this->limit         = $limit;
        $this->offset        = $offset;
        $this->try           = $try;
        $this->force_preload = $force_preload;
    }

    // }}}
    // {{{ countSend()

    /**
     * Check how many times mail was sent.
     *
     * @param Body $mail object
     *
     * @return mixed  Integer or false if error.
     */
    abstract function countSend(Body $mail);

    // }}}
    // {{{ setAsSent()

    /**
     * Set mail as already sent.
     *
     * @param Body $mail object
     *
     * @return bool
     */
    abstract function setAsSent(Body $mail);

    // }}}
    // {{{ getMailById()

    /**
     * Return mail by id $id (bypass mail_queue)
     *
     * @param integer $id  Mail ID
     * @return mixed  Mail object or false on error.
     */
    abstract function getMailById($id);

    abstract function getQueueCount();

    // }}}
    // {{{ deleteMail()

    /**
     * Remove from queue mail with $id identifier.
     *
     * @param integer $id  Mail ID
     * @return bool  True on success ale false.
     */
    abstract function deleteMail($id);

    // }}}
    // {{{ preload()

    /**
     * Preload mail to queue.
     * The buffer size can be set in the options.
     *
     * @return boolean True on success, false when the limit is met
     * @throws Exception
     */
    protected function preload()
    {
        if (!empty($this->queue_data)) {
            return true;
        }

        if (!$this->limit) {
            return false; //limit reached
        }

        $bkp_limit = $this->limit;

        //set buffer size
        if ($bkp_limit == Queue::ALL) {
            $this->limit = $this->buffer_size;
        } else {
            $this->limit = min($this->buffer_size, $this->limit);
        }

        $this->_preload();

        //restore limit
        if ($bkp_limit == Queue::ALL) {
            $this->limit = Queue::ALL;
        } else {
            $this->limit = $bkp_limit - count($this->queue_data);
        }

        //set buffer pointers
        $this->_current_item = 0;
        $this->_last_item    = count($this->queue_data)-1;

        return true;
    }

    // }}}
    // {{{ _isSerialized()

    /**
     * Check if the string is a regular string or a serialized array
     *
     * @param string $string
     * @return boolean
     */
    protected function _isSerialized($string)
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
