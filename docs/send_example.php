<?php
require_once __DIR__ . "/base.php";

// How many mails could we send each time
$max_ammount_mails = 50;

$queue = new PEAR2\Mail\Queue($db_options, $mail_options);
$queue->sendMailsInQueue($max_ammount_mails);
