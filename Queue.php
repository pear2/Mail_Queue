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
// | Authors: Radek Maciaszek <chief@php.net>                             |
// +----------------------------------------------------------------------+
//
// $Id$

/**
* Class for handle mail queue managment.
* Wrapper for Pear::Mail and Pear::DB.
* Could load, save and send saved mails in background
* and also backup some mails.
*
* Mail queue class put mails in a temporary
* container waiting to be fed to the MTA (Mail Transport Agent)
* and send them later (eg. every few minutes) by crontab or in other way. 
*
* -------------------------------------------------------------------------
* A basic usage example:
* -------------------------------------------------------------------------
*
* $container_options = array(
*   'type'        => 'db',
*   'database'    => 'dbname',
*   'phptype'     => 'mysql',
*   'username'    => 'root',
*   'password'    => '',
*   'mail_table'  => 'mail_queue'
* );
*   //optionally, a 'dns' string can be provided instead of db parameters.
*   //look at DB::connect() method or at DB or MDB docs for details.
*   //you could also use mdb container instead db
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
*
* // for more examples look to docs directory
*
* // end usage example
* -------------------------------------------------------------------------
*
* @version $Revision$
* $Id$
* @author Radek Maciaszek <chief@php.net>
*/

/**
 * This is special constant define start offset for limit sql queries to
 * get mails.
 */
define('MAILQUEUE_START', 0);

/**
 * You can specify how many mails will be loaded to 
 * queue else object use this constant for load all mails from db.
 */
define('MAILQUEUE_ALL', -1);

/**
 * When you put new mail to queue you could specify user id who send e-mail.
 * Else you could use system id: MAILQUEUE_SYSTEM or user unknown id: MAILQUEUE_UNKNOWN
 */
define('MAILQUEUE_SYSTEM', -1);
define('MAILQUEUE_UNKNOWN', -2);

/**
 * This constant tells Mail_Queue how many times should try
 * to send mails again if was any errors before.
 */
define('MAILQUEUE_TRY', 25);

require_once 'PEAR.php';

require_once 'Mail.php';
require_once 'Mail/mime.php';

require_once 'Mail/Queue/Error.php';

/**
* Mail_Queue - base class for mail queue managment.
*
* @author   Radek Maciaszek <wodzu@tonet.pl>
* @version  $Id$
* @package  Mail_Queue
* @access   public
*/
class Mail_Queue extends PEAR {

    /**
    * Mail options: smtp, mail etc. see Mail::factory
    *
    * @var array
    */
    var $mail_options;

    /**
    * Mail_Queue_Container
    *
    * @var object
    */
    var $container;

    /**
    * Reference to Pear_Mail object
    *
    * @var object
    */
    var $send_mail;

    /**
     * Mail_Queue constructor
     *
     * @param  string $container Container type
     * @param  array $container_options  Mail_Queue container options
     * @param  array $mail_options  How send mails.
     * 
     * @return mixed  True on success else PEAR error class.
     * 
     * @access public
     */
    function Mail_Queue($container_options, $mail_options)
    {
        $this->PEAR();
        if (!is_array($mail_options) || !isset($mail_options['driver'])) {
            return new Mail_Queue_Error('No mail driver specified!', __FILE__, __LINE__);
        }
        $this->mail_options = $mail_options;

        if (!is_array($container_options) || !isset($container_options['type'])) {
            return new Mail_Queue_Error('No container specified!', __FILE__, __LINE__);
        }
        $container_type = strtolower($container_options['type']);
        $container_class = 'Mail_Queue_Container_' . $container_type;
        $container_classfile = $container_type . '.php';

        include_once 'Mail/Queue/Container/' . $container_classfile;
        $this->container = new $container_class($container_options);
        if(PEAR::isError($this->container)) {
            return new Mail_Queue_Error('Cannot initialize container!', __FILE__, __LINE__);
        }
        return true;
    }

    /**
     * Mail_Queue desctructor
     *
     * @return void
     * @access public
     */
    function _Mail_Queue()
    {
        unset($this);
    }

    /**
     * Provides an interface for generating Mail:: objects of various
     * types see Mail::factory()
     *
     * @return void
     * 
     * @access public
     */
    function factorySendMail()
    {
        $this->send_mail =& Mail::factory( $this->mail_options['driver'], 
                                $this->mail_options );
    }

    /**
     * Send mails fom queue.
     * 
     * Mail_Queue::sendMailsInQueue()
     * 
     * @param integer $limit     Optional - max limit mails send. 
     *                           This is the max number of emails send by 
     *                           this function.
     * @param integer $offset    Optional - you could load mails from $offset (by id)
     * @param integer $try       Optional - hoh many times mailqueu should try send
     *                           each mail. If mail was sent succesful it will be delete
     *                           from Mail_Queue.
     * @return mixed  True on success else Mail_Queue_Error object.
     **/
    function sendMailsInQueue( $limit = MAILQUEUE_ALL, $offset = MAILQUEUE_START,
                                $try = MAILQUEUE_TRY )
    {
        $this->container->setOption($limit, $offset, $try);
        while( $mail = $this->get() ) {
            $this->container->countSend( $mail );
            
            $result = $this->sendMail( $mail );
            
            if(!PEAR::isError($result)) {
                $this->container->setAsSent( $mail );
                if($mail->isDeleteAfterSend()) {
                    $this->deleteMail( $mail->getId() );
                }
            } else {
                return new Mail_Queue_Error('Error in sending mail: '.$result->getMessage(), __FILE__, __LINE__);
            }
        }
        return true;
    }

     /**
     * Send Mail by $id identifier. (bypass Mail_Queue)
     *
     * @param integer $id  Mail identifier
     * @return bool  True on success else false
     * 
     * @access public
     */
    function sendMailById( $id )
    {
        $mail =& $this->container->getMailById( $id );
        return $this->sendMail( $mail );
    }

     /**
     * Send mail from MailBody object
     *
     * @param object  MailBody object
     * @return mixed  True on success else pear error class
     * 
     * @access public
     */
    function sendMail( $mail )
    {
        $recipient = $mail->getRecipient();
        $hdrs = $mail->getHeaders();
        $body = $mail->getBody();
        if(empty($this->send_mail)) {
            $this->factorySendMail();
        }
        return $this->send_mail->send($recipient, $hdrs, $body);
    }

     /**
     * Get next mail from queue. When run first time preload all queue
     *
     * @return    object   Mail_Queue_Container or error object
     * @throw     Mail_Queue_Error
     * @access    public
     */
    function get()
    {
        return $this->container->get();
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
     * 
     * @access public
     **/
    function put( $from, $to, $hdrs, $body, $sec_to_send = 0, $delete_after_send = true, $id_user = MAILQUEUE_SYSTEM )
    {
        $ip = getenv('REMOTE_ADDR');
        $time_to_send = date("Y-m-d G:i:s", time() + $sec_to_send);
        return $this->container->put( $time_to_send, $id_user,
                            $ip, $from, $to, serialize($hdrs), 
                            serialize($body), $delete_after_send );
    }

     /**
     * Delete mail from queue database
     *
     * @param integer $id  Maila identifier
     * @return boolean
     * 
     * @access private
     */
    function deleteMail( $id )
    {
        return $this->container->deleteMail( $id );
    }

}

?>