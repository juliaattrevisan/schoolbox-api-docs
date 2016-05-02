<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Aura\Di;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;

require_once __DIR__ . '/../../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Portrait Restoration', array(
    'verbose' => [ false, false, false ],
    'errors'  => E_ALL,
));


$kernel->run(function($di, $loop, $log, $opts) {
    /**
     * @var Di\Container    $di
     * @var LoopInterface   $loop
     * @var LoggerInterface $log
     * @var array           $opts
     */

    $portraitDir = $di->get('config')->get('portrait_dir');

    foreach (glob("$portraitDir/*jpg") as $filename) {

        // hash the file contents
        $hash = sha1_file($filename);

        try {
            // obtain the storage record
            $storage = Schoolbox\Resource\Storage\Mapper::getInstance()->findByHash($hash);

            // verify that the file is absent on disk
            if ($storage->exists()) {
                // this file exists, not going to attempt overwrite
                $log->debug("Portrait file '$filename' already in storage; skipping.");
                continue;
            }

            try {
                // portrait is registered, but has been deleted from disk: create
                $storage->writeStream($fp = fopen($filename, 'r'));
                fclose($fp);
                $log->info("Portrait file '$filename' copied back into storage; hash '$hash'.");

            } catch (League\Flysystem\Exception $e) {
                $log->warning('Attempted portrait copy caused ' . get_class($e) . ': ' . $e->getMessage() . '.', [ $e ]);
            }

        } catch (Schoolbox\Mapper\Exception $e) {
            // noop: hash was not found; this has never existed in storage
            $log->debug("Portrait file '$filename' has no storage record; skipping.");
        }

    }

});
