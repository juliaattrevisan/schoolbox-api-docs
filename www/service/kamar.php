<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

use Schoolbox\External\Connector\Kamar\Base\Endpoint\Profile\Receiver;

header('Content-type: application/xml');

$dir = (new Config())->get('stream_dir');

/* Consume data from stream
 */
$data = file_get_contents('php://input');
//$data = file_get_contents('/vagrant/data/cache/stream/20191217133402.xml');

(new Receiver($dir))->receive($data);

exit();
