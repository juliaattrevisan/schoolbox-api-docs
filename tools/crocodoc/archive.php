<?php
/**
 * Archives Crocodoc documents.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Aura\Di;
use Psr\Log\LoggerInterface as Logger;
use React\EventLoop\LoopInterface;

require_once __DIR__ . '/../../library/Bootstrap.php';

$options = getopt('v', ['skip-duplicates', 'enable-eportfolio', 'dry-run']);
$kernel = new Schoolbox\Application\Daemon\Kernel(
    'Crocodoc Archiver',
    [
        'verbose' => isset($options['v']) ? $options['v'] : null,
        'errors'  => E_ALL,
    ]
);

$kernel->run(function (Di\Container $di, LoopInterface $loop, Logger $log, array $options) {
//    $log->notice("Crocodoc Archive Script");
});
