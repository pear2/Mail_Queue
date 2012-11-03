<?php
require_once __DIR__ .'/base.php';

$queue = new \PEAR2\Mail\Queue($db_options, $mail_options);

$from             = 'user@server.com';
$from_name        =  'Chief';
$recipient        = 'user2@server.com';
$recipient_name   = 'admin';
$message          = 'Hi! This is test message!! :)';
$from_params      = !empty($from_name) ? '"'.$from_name.'" <'.$from.'>' : '<'.$from.'>';
$recipient_params = !empty($recipient_name) ? '"'.$recipient_name.'" <'.$recipient.'>' : '<'.$recipient.'>';
$hdrs             = array(
    'From'    => $from_params,
    'To'      => $recipient_params,
    'Subject' => 'test message body',
);

$mime = new \Mail_mime;
$mime->setTXTBody($message);
$body = $mime->get();
$hdrs = $mime->headers($hdrs);


/* Put message to queue */
$queue->put($from, $recipient, $hdrs, $body);


/* Also you could put this msg in more advanced mode */

// how many seconds wait to send mail
$seconds_to_send = 3600;

// delete mail from db after send?
$delete_after_send = false;

// if you backup some mails in db you could group them later by the user identifier, for example
$id_user = 7;

$queue->put($from, $recipient, $hdrs, $body, $seconds_to_send, $delete_after_send, $id_user);
