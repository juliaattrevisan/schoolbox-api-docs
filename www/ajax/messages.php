<?php
/**
 * AJAX-handler for long-polling of non-WebSocket-implemented browsers
 * What this does is accept a simple request from the JS, construct a WebSocket requests, establish a socket to the
 * WS server (on localhost), then act as a proxy for the AJAX client. An honestly simpler solution than trying to setup
 * WebSocket fallbacks on the JS end.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once(__DIR__ . '/../../include/Bootstrap.php');

if(isset($this)) {
	error(2); // no sitepages here!
}

header('Content-Type: application/json');

session_start();
$config = new \Schoolbox\ConfigLoader();
$factory = new \Alaress\Session\Factory($_COOKIE, $config, $_SERVER['REQUEST_TIME']);
$session = $factory->build();

// check the session is valid before continuing
if(!$session->isAuthSessionAlive()) {
    die(json_encode(array(
        'error' => 'Unauthorized',
        'code'  => 401,
    )));
}

$protocol = empty($_SERVER['HTTPS']) ? 'ws' : 'wss';
$path = Cfg::get('websocket_path') ?: '/';

if(empty($_SERVER['HTTPS'])) {
	$port = Cfg::get('websocket_port') ?: 8080;
}
else {
	$port = Cfg::get('websocket_port_ssl') ?: 8081;
}

try {
	$loop = \React\EventLoop\Factory::create();
	$log = new \Zend\Log\Logger();
	$log->addWriter(new \Zend\Log\Writer\Null());
	$client = new \Devristo\Phpws\Client\WebSocket("$protocol://127.0.0.1:{$port}{$path}", $loop, $log);

	$transaction = new Alaress\WorkerCrew\Transaction\Transaction($loop,
		function($timer) use ($client, $loop) {
			$data = $timer->getData();
			$method = $data['_method'];
			unset($data['_method']);
			echo json_encode(array($method => $data));
			$client->close();
			$loop->stop();
		}
	);

	$client->on("request", function($request) {
		/** @var \Zend\Http\Request $request */
		$request->getHeaders()->addHeaderLine("Cookie", $_SERVER['HTTP_COOKIE']);
	});

	$client->on("handshake", function($handshake) {
		// noop
	});

	$client->on("connect", function() use ($client) {
		// noop
	});

	$client->on("message", function($message) use ($client, $transaction) {
		/** @var \Devristo\Phpws\Messaging\WebSocketMessage $message */

		$data = each(json_decode($message->getData(), true));

		// this replicates the javascript functionality...ish...
		switch($data['key']) {

			case 'subscribe':
				$client->send(json_encode(array('fetch' => 5)));
				break;

			case 'fetch':
			case 'push':
				$messages = $data['value'];
				foreach($messages as $amqpId => $msg) {
					$transaction->put($amqpId, $msg);
				}
				break;

			case 'ack':
				$messages = $data['value'];
				foreach($messages as $ack) {
					$transaction->put($transaction->getCount(), $ack);
				}
				break;

		}

		// make the transaction still fire if there are no messages
		$transaction->put('_method', $data['key']);
	});

	$client->open(1)->promise()->then(
		function($transport) use ($client) {
			if(isset($_POST['data'])) {
				$data = json_decode($_POST['data'], true);
				$client->send(json_encode(array('ack' => $data['ack'])));
			}
			else {
				$client->send(json_encode(array('subscribe' => true)));
			}
		}
	);

	// I'm going to count to 5...
	$loop->addTimer(5, function($timer) use ($loop) {
		echo json_encode(array("timeout" => true));
		$loop->stop();
	});

	// last call
	$loop->run();
}
// catch all the exceptions
catch(Exception $e) {
	die(json_encode(array($e->getCode() => $e->getMessage())));
}

