<?php

require_once ( "base.php" );

// How many mails could we send each time
$max_ammount_mails = 50;

$mail_queue =& new Mail_Queue($db_options, $mail_options);

$mail_queue->sendMailsInQueue($max_ammount_mails);

?>