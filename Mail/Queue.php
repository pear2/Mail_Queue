<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
/**
 * +----------------------------------------------------------------------+
 * | PEAR :: Mail :: Queue                                                |
 * +----------------------------------------------------------------------+
 * | Copyright (c) 1997-2008 Radek Maciaszek, Lorenzo Alberton            |
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
 * @author   Lorenzo Alberton <l.alberton@quipo.it>
 * @license  http://www.opensource.org/licenses/bsd-license.php The BSD License
 * @version  CVS: $Id$
 * @link     http://pear.php.net/package/Mail_Queue
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

/**
 * MAILQUEUE_ERROR constants
 */
define('MAILQUEUE_ERROR',                    -1);
define('MAILQUEUE_ERROR_NO_DRIVER',          -2);
define('MAILQUEUE_ERROR_NO_CONTAINER',       -3);
define('MAILQUEUE_ERROR_CANNOT_INITIALIZE',  -4);
define('MAILQUEUE_ERROR_NO_OPTIONS',         -5);
define('MAILQUEUE_ERROR_CANNOT_CONNECT',     -6);
define('MAILQUEUE_ERROR_QUERY_FAILED',       -7);
define('MAILQUEUE_ERROR_UNEXPECTED',         -8);
define('MAILQUEUE_ERROR_CANNOT_SEND_MAIL',   -9);
define('MAILQUEUE_ERROR_NO_RECIPIENT',      -10);
define('MAILQUEUE_ERROR_UNKNOWN_CONTAINER', -11);

/**
 * PEAR
 * @ignore
 */
require_once 'PEAR.php';

/**
 * Mail
 * @ignore
 */
require_once 'Mail.php';

/**
 * Mail_mime
 * @ignore
 */
require_once 'Mail/mime.php';

/**
 * Mail_Queue_Error
 */
require_once 'Mail/Queue/Error.php';


/**
 * Mail_Queue - base class for mail queue managment.
 *
 * @category Mail
 * @package  Mail_Queue
 * @author   Radek Maciaszek <chief@php.net>
 * @author   Lorenzo Alberton <l.alberton@quipo.it>
 * @license  http://www.opensource.org/licenses/bsd-license.php The BSD License
 * @version  Release: @package_version@
 * @link     http://pear.php.net/package/Mail_Queue
 */
class Mail_Queue extends PEAR
{
    // {{{ Class vars

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
     * Pear error mode (when raiseError is called)
     * (see PEAR doc)
     *
     * @var int $_pearErrorMode
     * @access private
     */
    var $pearErrorMode = PEAR_ERROR_RETURN;

    /**
     * To catch errors in construct
     * @var array
     * @see self::Mail_Queue()
     * @see self::factory()
     * @see self::hasErrors()
     * @access private
     */
    var $_initErrors = array();

    // }}}
    // {{{ __construct

    function __construct($container_options, $mail_options)
    {
        return $this->Mail_Queue($container_options, $mail_options);
    }

    // }}}
    // {{{ Mail_Queue

    /**
     * Mail_Queue constructor
     *
     * @param  array $container_options  Mail_Queue container options
     * @param  array $mail_options  How send mails.
     *
     * @return Mail_Queue
     *
     * @access public
     * @deprecated
     */
    function Mail_Queue($container_options, $mail_options)
    {
        $this->PEAR();
        if (isset($mail_options['pearErrorMode'])) {
            $this->pearErrorMode = $mail_options['pearErrorMode'];
            // ugly hack to propagate 'pearErrorMode'
            $container_options['pearErrorMode'] = $mail_options['pearErrorMode'];
        }

        if (!is_array($mail_options) || !isset($mail_options['driver'])) {
            array_push($this->_initErrors, new Mail_Queue_Error(MAILQUEUE_ERROR_NO_DRIVER,
                $this->pearErrorMode, E_USER_ERROR, __FILE__, __LINE__));
        }
        $this->mail_options = $mail_options;

        if (!is_array($container_options) || !isset($container_options['type'])) {
            array_push($this->_initErrors, new Mail_Queue_Error(MAILQUEUE_ERROR_NO_CONTAINER,
                $this->pearErrorMode, E_USER_ERROR, __FILE__, __LINE__));
        }
        $container_type      = strtolower($container_options['type']);
        $container_class     = 'Mail_Queue_Container_' . $container_type;
        $container_classfile = $container_type . '.php';

        // Attempt to include a custom version of the named class, but don't treat
        // a failure as fatal.  The caller may have already included their own
        // version of the named class.
        if (!class_exists($container_class)) {
            include_once 'Mail/Queue/Container/' . $container_classfile;
        }
        if (!class_exists($container_class)) {
            array_push($this->_initErrors, new Mail_Queue_Error(MAILQUEUE_ERROR_UNKNOWN_CONTAINER,
                $this->pearErrorMode, E_USER_ERROR, __FILE__, __LINE__));
        } else {

            unset($container_options['type']);

            $this->container = new $container_class($container_options);
            if(PEAR::isError($this->container)) {
                array_push($this->_initErrors, new Mail_Queue_Error(MAILQUEUE_ERROR_CANNOT_INITIALIZE,
                    $this->pearErrorMode, E_USER_ERROR, __FILE__, __LINE__));
            }
        }
    }

    /**
     * Factory is used to initialize Mail_Queue, this is necessary to catch possible
     * errors during the initialization.
     *
     * @param array $container_options Options for the container.
     * @param array $mail_options Options for mail.
     *
     * @return mixed Mail_Queue|Mail_Queue_Error
     * @see self::Mail_Queue()
     * @since 1.2.3
     */
    function factory($container_options, $mail_options)
    {
        $obj = new Mail_Queue($container_options, $mail_options);
        if ($obj->hasErrors()) {
            /**
             * @see self::getErrors()
             */
            return new Mail_Queue_Error(MAILQUEUE_ERROR,
                $this->pearErrorMode, E_USER_ERROR, __FILE__, __LINE__);
        }
        return $obj;
    }
    // }}}
    // {{{ _Mail_Queue()

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

    // }}}
    // {{{ __destruct

    function __destruct()
    {
        $this->_Mail_Queue();
    }

    // }}}
    // {{{ factorySendMail()

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
        $options = $this->mail_options;
        unset($options['driver']);

        $this->send_mail =& Mail::factory($this->mail_options['driver'], $options);
    }

    /**
     * Returns the number of emails currently in the queue.
     *
     * @return int
     */
    function getQueueCount()
    {
        if (!is_a($this->container, 'mail_queue_container')) {
            array_push(
                $this->_initErrors,
                new Mail_Queue_Error(
                    MAILQUEUE_ERROR_NO_CONTAINER,
                    $this->pearErrorMode,
                    E_USER_ERROR,
                    __FILE__,
                    __LINE__
                )
            );
            return 0;
        }
        return $this->container->getQueueCount();
    }

    // }}}
    // {{{ setBufferSize()

    /**
     * Keep memory usage under control. You can set the max number
     * of mails that can be in the preload buffer at any given time.
     * It won't limit the number of mails you can send, just the
     * internal buffer size.
     *
     * @param integer $size  Optional - internal preload buffer size
     */
    function setBufferSize($size = 10)
    {
        $this->container->buffer_size = $size;
    }


    // }}}
    // {{{ sendMailsInQueue()

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
     *                           each mail. If mail was sent succesful it will be
     *                           deleted from Mail_Queue.
     * @param mixed   $callback  Optional, a callback (string or array) to save the
     *                           SMTP ID and the SMTP greeting.
     *
     * @return mixed  True on success else MAILQUEUE_ERROR object.
     */
    function sendMailsInQueue($limit = MAILQUEUE_ALL, $offset = MAILQUEUE_START,
                              $try = MAILQUEUE_TRY, $callback = null)
    {
        if (!is_int($limit) || !is_int($offset) || !is_int($try)) {
            return Mail_Queue::raiseError(
                "sendMailsInQueue(): limit, offset and try must be integer.",
                MAILQUEUE_ERROR_UNEXPECTED
            );
        }

        if ($callback !== null) {
            if (!is_array($callback) && !is_string($callback)) {
                return Mail_Queue::raiseError(
                    "sendMailsInQueue(): callback must be a string or an array.",
                    MAILQUEUE_ERROR_UNEXPECTED
                );
            }
        }

        $this->container->setOption($limit, $offset, $try);
        while ($mail = $this->get()) {
            $this->container->countSend($mail);

            $result = $this->sendMail($mail, true);
            if (PEAR::isError($result)) {
                //remove the problematic mail from the buffer, but don't delete
                //it from the db: it might be a temporary issue.
                $this->container->skip();
                PEAR::raiseError(
                    'Error in sending mail: '.$result->getMessage(),
                    MAILQUEUE_ERROR_CANNOT_SEND_MAIL, PEAR_ERROR_TRIGGER,
                    E_USER_NOTICE
                );
                continue;
            }

            //take care of callback first, as it may need to retrieve extra data
            //from the mail_queue table.
            if ($callback !== null) {
                $queued_as = null;
                $greeting  = null;
                if (isset($this->queued_as)) {
                    $queued_as = $this->queued_as;
                }
                if (isset($this->greeting)) {
                    $greeting = $this->greeting;
                }
                call_user_func($callback,
                    array('id' => $mail->getId(),
                          'queued_as' => $queued_as,
                          'greeting'  => $greeting));
            }

            // delete email from queue?
            if ($mail->isDeleteAfterSend()) {
                $status = $this->deleteMail($mail->getId());
            }

            unset($mail);

            if (isset($this->mail_options['delay'])
                && $this->mail_options['delay'] > 0) {
                sleep($this->mail_options['delay']);
            }
        }
        if (!empty($this->mail_options['persist']) && is_object($this->send_mail)) {
            $this->send_mail->disconnect();
        }
        return true;
    }

    // }}}
    // {{{ sendMailById()

    /**
     * Send Mail by $id identifier. (bypass Mail_Queue)
     *
     * @param integer $id  Mail identifier
     * @param  bool   $set_as_sent
     * @return mixed  boolean: true on success else false, or PEAR_Error
     *
     * @access public
     * @uses   self::sendMail()
     */
    function sendMailById($id, $set_as_sent=true)
    {
        $mail =& $this->container->getMailById($id);
        if (PEAR::isError($mail)) {
            return $mail;
        }
        return $this->sendMail($mail, $set_as_sent);
    }

    // }}}
    // {{{ sendMail()

    /**
     * Send mail from {@link Mail_Queue_Body} object
     *
     * @param object  Mail_Queue_Body object
     * @return mixed  True on success else pear error class
     * @param  bool   $set_as_sent
     *
     * @access public
     * @see    self::sendMailById()
     */
    function sendMail($mail, $set_as_sent=true)
    {
        if (!is_a($mail, 'Mail_Queue_Body')) {
            if (is_a($mail, 'Mail_Queue_Error')) {
                return $mail;
            }
            return Mail_Queue_Error(
                "Unknown object/type: " . get_class($mail),
                MAILQUEUE_ERROR_UNEXPECTED
            );
        }
        $recipient = $mail->getRecipient();
        if (empty($recipient)) {
            return new Mail_Queue_Error('Recipient cannot be empty.',
                MAILQUEUE_ERROR_NO_RECIPIENT);
        }

        $hdrs = $mail->getHeaders();
        $body = $mail->getBody();

        if (empty($this->send_mail)) {
            $this->factorySendMail();
        }
        if (PEAR::isError($this->send_mail)) {
            return $this->send_mail;
        }
        $sent = $this->send_mail->send($recipient, $hdrs, $body);
        if (!PEAR::isError($sent) && $sent && $set_as_sent) {
            $this->container->setAsSent($mail);
        }
        if (isset($this->send_mail->queued_as)) {
            $this->queued_as = $this->send_mail->queued_as;
        }
        if (isset($this->send_mail->greeting)) {
            $this->greeting = $this->send_mail->greeting;
        }
        return $sent;
    }

    // }}}
    // {{{ get()

    /**
     * Get next mail from queue. The emails are preloaded
     * in a buffer for better performances.
     *
     * @return    object Mail_Queue_Container or error object
     * @throw     MAILQUEUE_ERROR
     * @access    public
     */
    function get()
    {
        return $this->container->get();
    }

    // }}}
    // {{{ put()

    /**
     * Put new mail in queue.
     *
     * @see Mail_Queue_Container::put()
     *
     * @param string  $time_to_send  When mail have to be send
     * @param integer $id_user  Sender id
     * @param string  $ip    Sender ip
     * @param string  $from  Sender e-mail
     * @param string|array  $to    Reciepient(s) e-mail
     * @param string  $hdrs  Mail headers (in RFC)
     * @param string  $body  Mail body (in RFC)
     * @return mixed  ID of the record where this mail has been put
     *                or Mail_Queue_Error on error
     *
     * @access public
     */
    function put($from, $to, $hdrs, $body, $sec_to_send=0, $delete_after_send=true, $id_user=MAILQUEUE_SYSTEM)
    {
        $ip = getenv('REMOTE_ADDR');

        $time_to_send = date("Y-m-d H:i:s", time() + $sec_to_send);

        return $this->container->put(
            $time_to_send,
            $id_user,
            $ip,
            $from,
            serialize($to),
            serialize($hdrs),
            serialize($body),
            $delete_after_send
        );
    }

    // }}}
    // {{{ deleteMail()

    /**
     * Delete mail from queue database
     *
     * @param integer $id  Maila identifier
     * @return boolean
     *
     * @access private
     */
    function deleteMail($id)
    {
        return $this->container->deleteMail($id);
    }

    // }}}
    // {{{ isError()

    /**
     * Tell whether a result code from a Mail_Queue method is an error
     *
     * @param   int       $value  result code
     * @return  boolean   whether $value is an MAILQUEUE_ERROR
     * @access public
     */
    function isError($value)
    {
        return (is_object($value) && is_a($value, 'pear_error'));
    }

    // }}}
    // {{{ errorMessage()

    /**
     * Return a textual error message for a MDB error code
     *
     * @param   int     $value error code
     * @return  string  error message, or false if the error code was
     *                  not recognized
     * @access public
     */
    function errorMessage($value)
    {
        static $errorMessages;
        if (!isset($errorMessages)) {
            $errorMessages = array(
                MAILQUEUE_ERROR                    => 'unknown error',
                MAILQUEUE_ERROR_NO_DRIVER          => 'No mail driver specified',
                MAILQUEUE_ERROR_NO_CONTAINER       => 'No container specified',
                MAILQUEUE_ERROR_CANNOT_INITIALIZE  => 'Cannot initialize container',
                MAILQUEUE_ERROR_NO_OPTIONS         => 'No container options specified',
                MAILQUEUE_ERROR_CANNOT_CONNECT     => 'Cannot connect to database',
                MAILQUEUE_ERROR_QUERY_FAILED       => 'db query failed',
                MAILQUEUE_ERROR_UNEXPECTED         => 'Unexpected class',
                MAILQUEUE_ERROR_CANNOT_SEND_MAIL   => 'Cannot send email',
            );
        }

        if (Mail_Queue::isError($value)) {
            $value = $value->getCode();
        }

        return isset($errorMessages[$value]) ?
           $errorMessages[$value] : $errorMessages[MAILQUEUE_ERROR];
    }

    // }}}

    /**
     * hasErrors() returns true/false, if self::$_initErrors are populated.
     *
     * @return boolean
     * @see self::Mail_Queue
     * @see self::$_initErrors
     * @see self::getErrors()
     * @since 1.2.3
     */
    function hasErrors()
    {
        if (count($this->_initErrors) > 0) {
            return true;
        }
        return false;
    }

    /**
     * getErrors() returns the errors.
     *
     * @return array
     * @see self::Mail_Queue
     * @see self::$_initErrors
     * @see self::hasErrors()
     * @since 1.2.3
     */
    function getErrors()
    {
        return $this->_initErrors;
    }

/*
    function raiseError($msg, $code = null, $file = null, $line = null, $mode = null)
    {
        if ($file !== null) {
            $err = PEAR::raiseError(sprintf("%s [%s on line %d].", $msg, $file, $line), $code, $mode);
        } else {
            $err = PEAR::raiseError(sprintf("%s", $msg), $code, $mode);
        }
        return $err;
    }
*/
}
?>
