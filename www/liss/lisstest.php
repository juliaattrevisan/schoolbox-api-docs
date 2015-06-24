<?php
require_once(dirname(__FILE__) . '/../include/Bootstrap.php');

include dirname(__FILE__) . "/../vendor/phpxmlrpc/phpxmlrpc/lib/xmlrpc.inc";     // from http://phpxmlrpc.sourceforge.net/
include dirname(__FILE__) . "/../vendor/phpxmlrpc/phpxmlrpc/lib/xmlrpcs.inc";    // from http://phpxmlrpc.sourceforge.net/

$client = new xmlrpc_client("http://localhost/liss.php");
$client->return_type = 'phpvals';

$message = new xmlrpcmsg("liss.hello");

$resp = $client->send($message);

if ($resp->faultCode()) {
	echo 'KO. Error: '.$resp->faultString();
}
else {
	echo 'OK: got '.$resp->value();
}

var_dump($resp);