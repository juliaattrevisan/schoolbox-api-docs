#!/usr/bin/php
<?php

use Alaress\Scheduler\Trigger\TriggerInterface;
use Alaress\WorkerCrew\Pooling\DefaultDispatchStrategy;

/**
 * Tool for assessing the contents of the pqueue (SQL table `scheduleq` and determining any reason as to why the
 * records contained there cannot be processed)
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\Daemon\Kernel(__FILE__);

$d = new \Alaress\Lumberjack\Logger(STDOUT, STDERR, Monolog\Logger::DEBUG);

$stmt = Database::getInstance()->prepare("
	SELECT *
	FROM scheduleq
");

$stmt->execute();

while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    try {
        $hash = $r['id_hash'];
        $command = $r['command'];
        $encodedIdentifier = $r['identifier'];
        $encodedData = $r['data'];

        $d->info("[$hash] Command is '$command'");

        $identifier = unserialize($encodedIdentifier);
        $data = unserialize(unserialize($encodedData));

        if ($identifier === false) {
            $d->info("[$hash] Failed to unserialize identifier");
            throw new Exception();
        } else {
            switch (gettype($identifier)) {
                case 'object':
                    $d->info("[$hash] Identifier is instance of " . get_class($identifier), $identifier instanceof TriggerInterface ? '+' : '*');
                    break;
                case 'resource':
                    $d->error("[$hash] Identifier is resource");
                    throw new Exception();
                default:
                    $d->info("[$hash] Identifier is " . gettype($identifier) . ": '$identifier'", '*');
                    $err = true;
            }
        }




        if ($data === false) {
            $d->info("[$hash] Failed to unserialize data");
            throw new Exception();
        }

        if ($identifier instanceof TriggerInterface) {
            // data should be an array
            $dataType = gettype($data);

            $msg = "[$hash] Data is $dataType";

            if ($dataType != 'array') {
                $d->error($msg);
                throw new Exception();
            }

            $d->info($msg);

            if (in_array($command, array(DefaultDispatchStrategy::TASK_PUBLISH, DefaultDispatchStrategy::TASK_DIGEST))) {
                if (count($data) != 4) {
                    $d->info("[$hash] Datacount for publication must be 4, got " . count($data), '*');
                    throw new Exception();
                }

                if (is_callable($data[0])) {
                    $d->info("[$hash] First argument is callable", array('char' => '+'));
                } else {
                    $d->error("[$hash] First argument is NOT callable");
                }

                if (is_object($data[1])) {
                    $d->info("[$hash] Second argument is object, instance of " . get_class($data[1]), '+');
                } else {
                    $d->error("[$hash] First argument is NOT object");
                }

                if ($data[2] instanceof \Schoolbox\User) {
                    $d->info("[$hash] Third argument is instance of Schoolbox\\User", array('char' => '+'));
                } else {
                    $d->error("[$hash] Third argument is NOT instance of Schoolbox\\User");
                }

                if (is_null($data[3]) || is_array($data[3])) {
                    $d->info("[$hash] Fourth argument is an acceptable diff", array('char' => '+'));
                } else {
                    $d->error("[$hash] Fourth argument is NOT an acceptable diff");
                }
            }
        }

        $d->info("[$hash] Probably can process");
    } catch (Exception $e) {
        $d->critical("[$hash] Probably cannot process, should delete");
    }
}
