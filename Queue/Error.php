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
* Pear Error class for mail queue management
*
* @version  $Revision$
* @author   Radek Maciaszek <chief@php.net>
*/

require_once "PEAR.php";

/**
* Mail_Queue_Error Error class
* 
* @package Mail_Queue
*/
class Mail_Queue_Error extends PEAR_Error {
  
  /**
  * Prefix of all error messages.
  * 
  * @var  string
  */
  var $error_message_prefix = 'Mail_Queue-Error: ';
  
  /**
  * Mail_Queue error object.
  * 
  * @param  string  error message
  * @param  string  file where the error occured
  * @param  string  linenumber where the error occured
  */
  function Mail_Queue_Error($msg, $file = __FILE__, $line = __LINE__) 
  {
    $this->PEAR_Error(sprintf("%s [%s on line %d].", $msg, $file, $line), null, PEAR_ERROR_PRINT);
  }
  
}
?>