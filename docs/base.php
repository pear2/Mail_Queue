<?php

require_once "Mail/Queue.php";

$db_options['type']       = 'db';
$db_options['dsn']        = 'mysql://user:password@host/database';
$db_options['mail_table'] = 'mail_queue';

$mail_options['driver']   = 'smtp';
$mail_options['host']     = 'your_server_smtp.com';
$mail_options['port']     = 25;
$mail_options['auth']     = false;
$mail_options['username'] = '';
$mail_options['password'] = '';

?>