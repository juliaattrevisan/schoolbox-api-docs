<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once(dirname(__FILE__) . '/../include/Bootstrap.php');

include dirname(__FILE__) . "/../vendor/phpxmlrpc/phpxmlrpc/lib/xmlrpc.inc";     // from http://phpxmlrpc.sourceforge.net/
include dirname(__FILE__) . "/../vendor/phpxmlrpc/phpxmlrpc/lib/xmlrpcs.inc";    // from http://phpxmlrpc.sourceforge.net/

function hello() {
	$struct = 'ggggggg';

	return new xmlrpcresp(new xmlrpcval($struct, 'string'));
}

// Declare our signature and provide some documentation.
// (The PHP server supports remote introspection. Nifty!)
new xmlrpc_server(array(
	'liss.hello' => array(
		'function' => 'hello',
		'docstring' => 'Add and subtract two numbers'
	)
));
