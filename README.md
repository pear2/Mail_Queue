# Mail_Queue

A PHP5 port of the [Mail_Queue](http://pear.php.net/package/Mail_Queue) package.

The API is very similar except for:

 * PHP 5.3 minimum (`new \PEAR2\Mail\Queue(...);`)
 * visibility of all methods is enforced
 * no dependency on PEAR
 * throws exceptions in most cases
 * non-fatal error are still with `hasErrors()` and `getErrors()`

## Install

Until I get to PEAR2, just use composer.

## WIP

topics/doctrine

 * add a container for Doctrine2 ORM
 * run CI testing on travis

## TODO

 * add support for Swiftmailer
 * investigate better queues than RDBMS
