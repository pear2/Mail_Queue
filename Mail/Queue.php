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
 * PHP Version 5.3
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
 * Mail_Queue_Exception
 */
require_once 'Mail/Queue/Exception.php';

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
class Mail_Queue
{
    /**
     * This is special constant define start offset for limit sql queries to
     * get mails.
     */
    const START = 0;

    /**
     * You can specify how many mails will be loaded to
     * queue else object use this constant for load all mails from db.
     */
    const ALL = -1;

    /**
     * When you put new mail to queue you could specify user id who send e-mail.
     * Else you could use system id: Mail_Queue::SYSTEM or user unknown id: Mail_Queue::UNKNOWN
     */
    const SYSTEM = -1;
    const UNKNOWN = -2;

    /**
     * This constant tells Mail_Queue how many times should try
     * to send mails again if was any errors before.
     */
    const RETRY = 25;

    /**
     * Mail_Queue::ERROR constants
     */
    const ERROR                   = -1;
    const ERROR_NO_DRIVER         = -2;
    const ERROR_NO_CONTAINER      = -3;
    const ERROR_CANNOT_INITIALIZE = -4;
    const ERROR_NO_OPTIONS        = -5;
    const ERROR_CANNOT_CONNECT    = -6;
    const ERROR_QUERY_FAILED      = -7;
    const ERROR_UNEXPECTED        = -8;
    const ERROR_CANNOT_SEND_MAIL  = -9;
    const ERROR_NO_RECIPIENT      = -10;
    const ERROR_UNKNOWN_CONTAINER = -11;

    // {{{ Class vars

    /**
     * Mail options: smtp, mail etc. see Mail::factory
     *
     * @var array
     */
    var $mail_options;

    /**
     * @var Mail_Queue_Container
     */
    var $container;

    /**
     * Reference to Pear_Mail object
     *
     * @var Mail
     */
    var $send_mail;


    /**
     * Errors from Mail_Queue at runtime. Most likely not fatal enough. :)
     * @var array
     */
    protected $_initErrors = array();

    // {{{ Mail_Queue

    /**
     * Mail_Queue constructor
     *
     * @param  array $container_options  Mail_Queue container options
     * @param  array $mail_options  How send mails.
     *
     * @return Mail_Queue
     */
    public function __construct(array $container_options, array $mail_options)
    {
        if (!isset($mail_options['driver'])) {
            throw new Mail_Queue_Exception(
                '$mail_options missing driver.',
                self::ERROR_NO_DRIVER
            );
        }

        $this->mail_options = $mail_options;

        if (!isset($container_options['type'])) {
            throw new Mail_Queue_Exception(
                '$container_options missing type.',
                self::ERROR_NO_CONTAINER
            );
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
            throw new Mail_Queue_Exception(
                "Unknown container '$container_type'",
                selfg::ERROR_UNKNOWN_CONTAINER
            );
        }

        unset($container_options['type']);
        $this->container = new $container_class($container_options);
    }

    // }}}
    // {{{ __destruct()

    /**
     * Mail_Queue desctructor
     *
     * @return void
     * @access public
     */
    public function __destruct()
    {
        unset($this);
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
    public function factorySendMail()
    {
        $options = $this->mail_options;
        unset($options['driver']);

        /**
         * Duplicate code from {@link Mail::factory()} to work around E_STRICT.
         */
        $driver = strtolower($this->mail_options['driver']);
        @include_once 'Mail/' . $driver . '.php';
        $class = 'Mail_' . $driver;
        if (!class_exists($class)) {
            throw new Mail_Queue_Exception("Could not send email via '$driver'.");
        }

        $mailer          = new $class($options);
        $this->send_mail = $mailer;
    }

    /**
     * Returns the number of emails currently in the queue.
     *
     * @return int
     * @throws Mail_Queue_Exception
     */
    public function getQueueCount()
    {
        if (!is_a($this->container, 'mail_queue_container')) {
            throw new Mail_Queue_Exception(
                "Configuration error: no container found",
                Mail_Queue::ERROR_NO_CONTAINER
            );
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
     * @return mixed  True on success else Mail_Queue::ERROR object.
     */
    function sendMailsInQueue($limit = Mail_Queue::ALL, $offset = Mail_Queue::START,
                              $try = Mail_Queue::RETRY, $callback = null)
    {
        if (!is_int($limit) || !is_int($offset) || !is_int($try)) {
            throw new Mail_Queue_Exception(
                "sendMailsInQueue(): limit, offset and try must be integer.",
                Mail_Queue::ERROR_UNEXPECTED
            );
        }

        if ($callback !== null) {
            if (!is_array($callback) && !is_string($callback)) {
                throw new Mail_Queue_Exception(
                    "sendMailsInQueue(): callback must be a string or an array.",
                    Mail_Queue::ERROR_UNEXPECTED
                );
            }
        }

        $this->container->setOption($limit, $offset, $try);
        while ($mail = $this->get()) {

            if (false === $mail) {
                break;
            }

            $this->container->countSend($mail);

            $result = $this->sendMail($mail, true);
            if ($result instanceof PEAR_Error) {
                //remove the problematic mail from the buffer, but don't delete
                //it from the db: it might be a temporary issue.
                $this->container->skip();

                array_push(
                    $this->_initErrors,
                    'Error in sending mail: ' . $result->getMessage()
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
                    array(
                        'id'        => $mail->getId(),
                        'queued_as' => $queued_as,
                        'greeting'  => $greeting,
                    )
                );
            }

            // delete email from queue?
            if ($mail->isDeleteAfterSend()) {
                try {
                    $status = $this->deleteMail($mail->getId());
                } catch (Mail_Queue_Exception $e) {
                    array_push(
                        $this->_initErrors,
                        $e->getMessage()
                    );
                }
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
        $mail = $this->container->getMailById($id);
        if ($mail instanceof PEAR_Error) {
            return $mail;
        }
        return $this->sendMail($mail, $set_as_sent);
    }

    // }}}
    // {{{ sendMail()

    /**
     * Send mail from {@link Mail_Queue_Body} object
     *
     * @param  Mail_Queue_Body $mail object
     * @param  bool   $set_as_sent
     *
     * @return mixed  True on success else pear error class
     * @throws Mail_Queue_Exception
     * @see    self::sendMailById()
     */
    public function sendMail(Mail_Queue_Body $mail, $set_as_sent=true)
    {
        $recipient = $mail->getRecipient();
        if (empty($recipient)) {
            throw new Mail_Queue_Exception(
                'Recipient cannot be empty.',
                Mail_Queue::ERROR_NO_RECIPIENT
            );
        }

        $hdrs = $mail->getHeaders();
        $body = $mail->getBody();

        if (empty($this->send_mail)) {
            $this->factorySendMail();
        }
        if ($this->send_mail instanceof PEAR_Error) {
            throw new Mail_Queue_Exception(
                "Failed Mail object: {$this->send_mail->getMessage()}"
            );
        }
        $sent = $this->send_mail->send($recipient, $hdrs, $body);
        if (($sent instanceof PEAR_Error) && $sent && $set_as_sent) {
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
     * @return object Mail_Queue_Container
     */
    public function get()
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
     *
     * @return mixed  ID of the record where this mail has been put
     * @throws Mail_Queue_Exception
     */
    public function put($from, $to, $hdrs, $body, $sec_to_send=0, $delete_after_send=true, $id_user=Mail_Queue::SYSTEM)
    {
        $ip = getenv('REMOTE_ADDR');

        $time_to_send = date("Y-m-d H:i:s", time() + $sec_to_send);

        $id = $this->container->put(
            $time_to_send,
            $id_user,
            $ip,
            $from,
            serialize($to),
            serialize($hdrs),
            serialize($body),
            $delete_after_send
        );
        if (is_numeric($id)) {
            return $id;
        }

        $msg = "Could not save email: {$id->getMessage()}";

        if (is_callable($id, 'getUserInfo')) {
            $msg .= ", " . $id->getUserInfo();
        }

        throw new Mail_Queue_Exception($msg, $id->getCode());
    }

    // }}}
    // {{{ deleteMail()

    /**
     * Delete mail from queue database
     *
     * @param integer $id  Maila identifier
     * @return boolean
     */
    protected function deleteMail($id)
    {
        return $this->container->deleteMail($id);
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
    public static function errorMessage($value)
    {
        static $errorMessages;
        if (!isset($errorMessages)) {
            $errorMessages = array(
                Mail_Queue::ERROR                    => 'unknown error',
                Mail_Queue::ERROR_NO_DRIVER          => 'No mail driver specified',
                Mail_Queue::ERROR_NO_CONTAINER       => 'No container specified',
                Mail_Queue::ERROR_CANNOT_INITIALIZE  => 'Cannot initialize container',
                Mail_Queue::ERROR_NO_OPTIONS         => 'No container options specified',
                Mail_Queue::ERROR_CANNOT_CONNECT     => 'Cannot connect to database',
                Mail_Queue::ERROR_QUERY_FAILED       => 'db query failed',
                Mail_Queue::ERROR_UNEXPECTED         => 'Unexpected class',
                Mail_Queue::ERROR_CANNOT_SEND_MAIL   => 'Cannot send email',
            );
        }

        if ($value instanceof PEAR_Error) {
            $value = $value->getCode();
        }

        return isset($errorMessages[$value]) ?
           $errorMessages[$value] : $errorMessages[Mail_Queue::ERROR];
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
}
