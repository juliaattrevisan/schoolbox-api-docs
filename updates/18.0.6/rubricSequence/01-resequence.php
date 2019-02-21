<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

use Alaress\Database\Connector\IConnector;
use Aura\Di;
use Psr\Log\LoggerInterface as Logger;
use React\EventLoop\LoopInterface;

require_once __DIR__ . '/../../../conf/core/require.php';
$kernel = new Schoolbox\Application\Daemon\Kernel(
    'Re-sequence Rubric Levels',
    [
        'verbose' => [false, false, false],
        'errors'  => E_ALL,
    ]
);

/** @noinspection PhpUnhandledExceptionInspection */
$kernel->run(
    /**
     * @param Di\Container  $di
     * @param LoopInterface $loop
     * @param Logger        $log
     * @param               $opts
     *
     * @throws Di\Exception\ServiceNotFound
     */
    function (Di\Container $di, LoopInterface $loop, Logger $log, $opts) {
        /** @var IConnector $db */
        $db = $di->get('databaseInternal');

        // Find all extant rubric levels whose sequence is 0
        $selectQuery = $db->prepareQuery("
            SELECT DISTINCT a.id, a.title
            FROM rubric_level rl
            INNER JOIN submission_box sb ON rl.box_id = sb.id
            INNER JOIN assessment     a  ON sb.assessment_id = a.id
            WHERE rl.sequence = 0 
            AND   rl.deleted_at IS NULL
            ORDER BY id ASC
        ");

        // Increment all of them
        $updateQuery = $db->prepareQuery("
            UPDATE rubric_level rl
            INNER JOIN submission_box sb ON rl.box_id = sb.id
            INNER JOIN assessment     a  ON sb.assessment_id = a.id
            SET rl.sequence = rl.sequence + 1
            WHERE a.id = :assessmentId
        ");

        $selectQuery->execute();
        $log->notice(
            sprintf(
                "%d due work items found with rubric levels that require re-sequence",
                $selectQuery->rowCount()
            )
        );

        foreach ($selectQuery->fetchAll() as $row) {
            $log->info(
                sprintf(
                    "Re-sequencing levels for assessment #%d (%s)...",
                    $row['id'],
                    $row['title']
                )
            );

            $updateQuery->execute(['assessmentId' => $row['id']]);
            $log->debug(sprintf("%d levels re-sequenced", $updateQuery->rowCount()));
        }
    }
);
