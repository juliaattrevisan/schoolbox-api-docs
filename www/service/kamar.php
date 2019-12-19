<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

use Schoolbox\Config\Loader;
use Schoolbox\External\Connector\Kamar\Base\Endpoint\Profile\Receiver;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

header('Content-type: application/xml');

/* Consume data from stream
 */
$data = file_get_contents('php://input');

/* Testing independently of a Kamar push
 *
 * You can test Receiver's processing by loading from a local xml
 * $data = file_get_contents('/vagrant/data/cache/stream/20191217133402.xml');
 */
(new Receiver(Loader::getInstance()->get('stream_dir')))->receive($data);

exit();
