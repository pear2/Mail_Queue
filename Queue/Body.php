<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
/**
 * +----------------------------------------------------------------------+
 * | PEAR :: Mail :: Queue :: Body                                        |
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
 * @license  http://www.opensource.org/licenses/bsd-license.php The BSD License
 * @version  CVS: $Id$
 * @link     http://pear.php.net/package/Mail_Queue
 */

/**
 * Mail_Queue_Body contains mail data
 *
 * @category Mail
 * @package  Mail_Queue
 * @author   Radek Maciaszek <chief@php.net>
 * @author   Lorenzo Alberton <l dot alberton at quipo dot it>
 * @license  http://www.opensource.org/licenses/bsd-license.php The BSD License
 * @version  Release: @package_version@
 * @link     http://pear.php.net/package/Mail_Queue
*/
class Mail_Queue_Body {

    /**
     * Ident
     *
     * @var integer
     */
    var $id;

    /**
     * Create time
     *
     * @var string
     */
    var $create_time;

    /**
     * Time to send mail
     *
     * @var string
     */
    var $time_to_send;

    /**
     * Time when mail was sent
     *
     * @var string
     */
    var $sent_time = null;

    /**
     * User id - who send mail
     * MAILQUEUE_UNKNOWN - not login user (guest)
     * MAILQUEUE_SYSTEM - mail send by system
     *
     * @var string
     */
    var $id_user = MAILQUEUE_SYSTEM;

    /**
     * use IP
     *
     * @var string
     */
    var $ip;

    /**
     * Sender email
     *
     * @var string
     */
    var $sender;

    /**
     * Reciepient email
     *
     * @var string
     */
    var $recipient;

    /**
     * Email headers (in RFC)
     *
     * @var string
     */
    var $headers;

    /**
     * Email body (in RFC) - could have attachments etc
     *
     * @var string
     */
    var $body;

    /**
     * How many times mail was sent
     *
     * @var integer
     */
    var $try_sent = 0;

    /**
     * Delete mail from database after success send
     *
     * @var bool
     */
    var $delete_after_send = true;

    /**
     * Mail_Queue_Body::Mail_Queue_Body() constructor
     *
     * @param integer $id Mail ident
     * @param string $create_time  Create time
     * @param strine $time_to_send  Time to send
     * @param string $sent_time  Sent time
     * @param integer $id_user  Sender user id (who sent mail)
     * @param string $ip Sender user ip
     * @param strine $sender  Sender e-mail
     * @param string $recipient Reciepient e-mail
     * @param string $headers Mail headers (in RFC)
     * @param string $body Mail body (in RFC)
     * @param integer $try_sent  How many times mail was sent
     *
     * @return void
     *
     * @access public
     */
    function Mail_Queue_Body($id, $create_time, $time_to_send, $sent_time, $id_user,
                       $ip, $sender, $recipient, $headers, $body,
                       $delete_after_send=true, $try_sent=0)
    {
        $this->id                = $id;
        $this->create_time       = $create_time;
        $this->time_to_send      = $time_to_send;
        $this->sent_time         = $sent_time;
        $this->id_user           = $id_user;
        $this->ip                = $ip;
        $this->sender            = $sender;
        $this->recipient         = $recipient;
        $this->headers           = $headers;
        $this->body              = $body;
        $this->delete_after_send = $delete_after_send;
        $this->try_sent          = $try_sent;
    }

    /**
     * Mail_Queue_Body::getId()
     *
     * @return integer  Sender id
     * @access public
     */
    function getId()
    {
        return $this->id;
    }

    /**
     * Return mail create time.
     *
     * Mail_Queue_Body::getCreateTime()
     *
     * @return string  Mail create time
     * @access public
     */
    function getCreateTime()
    {
        return $this->create_time;
    }

    /**
     * Return time to send mail.
     *
     * Mail_Queue_Body::getTimeToSend()
     *
     * @return string  Time to send
     * @access public
     */
    function getTimeToSend()
    {
        return $this->time_to_send;
    }

    /**
     * Return mail sent time (if sended) else false.
     *
     * Mail_Queue_Body::getSentTime()
     *
     * @return mixed  String sent time or false if mail not was sent yet
     * @access public
     */
    function getSentTime()
    {
        return empty($this->sent_time) ? false : $this->sent_time;
    }

    /**
     * Return sender id.
     *
     * Mail_Queue_Body::getIdUser()
     *
     * @return integer  Sender id
     * @access public
     */
    function getIdUser()
    {
        return $this->id_user;
    }

    /**
     * Return sender ip.
     *
     * Mail_Queue_Body::getIp()
     *
     * @return string  IP
     * @access public
     */
    function getIp()
    {
        return stripslashes($this->ip);
    }

    /**
     * Return sender e-mail.
     *
     * Mail_Queue_Body::getSender()
     *
     * @return string E-mail
     * @access public
     */
    function getSender()
    {
        return stripslashes($this->sender);
    }

    /**
     * Return recipient e-mail.
     *
     * Mail_Queue_Body::getRecipient()
     *
     * @return string|array E-mail(s)
     * @access public
     */
    function getRecipient()
    {
        if (is_array($this->recipient)) {
            $tmp_recipients = array();
            foreach ($this->recipient as $key => $value) {
                $tmp_recipients[$key] = stripslashes($value);
            }
            return $tmp_recipients;
        }
        return stripslashes($this->recipient);
    }

    /**
     * Return mail headers (in RFC)
     *
     * Mail_Queue_Body::getHeaders()
     *
     * @return mixed array|string headers
     * @access public
     */
    function getHeaders()
    {
        if (is_array($this->headers)) {
            $tmp_headers = array();
            foreach ($this->headers as $key => $value) {
                $tmp_headers[$key] = stripslashes($value);
            }
            return $tmp_headers;
        }
        return stripslashes($this->headers);
    }

    /**
     * Return mail body (in RFC)
     *
     * Mail_Queue_Body::getBody()
     *
     * @return string  Body
     * @access public
     */
    function getBody()
    {
        return stripslashes($this->body);
    }

    /**
     * Return how many times mail was try to sent.
     *
     * Mail_Queue_Body::getTrySent()
     *
     * @return integer  How many times mail was sent
     * @access public
     */
    function getTrySent()
    {
        return $this->try_sent;
    }

    /**
     * Return true if mail must be delete after send from db.
     *
     * MailBody::isDeleteAfterSend()
     *
     * @return bool  True if must be delete else false.
     * @access public
     */
    function isDeleteAfterSend()
    {
        return $this->delete_after_send;
    }

    /**
     * Increase and return try_sent
     *
     * Mail_Queue_Body::_try()
     *
     * @return integer  How many times mail was sent
     * @access public
     */
    function _try()
    {
        return ++$this->try_sent;
    }
}
?>
