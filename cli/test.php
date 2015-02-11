<?php

if (PHP_SAPI !== 'cli') { print "NO!\n"; exit; }
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once(dirname(dirname(__FILE__)) . '/lib.php');

define('HMN_TESTING', 1);

$longopts = array(
    'function:',
);
$opts = (object)getopt(null, $longopts);

$function = $opts->function;
switch($function) {
case 'helpmenow_email_messages':
    $function();
    break;
default:
    print "Unexpected --function: $opts->function\n";
    exit;
}

