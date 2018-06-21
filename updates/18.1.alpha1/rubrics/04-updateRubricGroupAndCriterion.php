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
use Schoolbox\Application\Daemon;

$opts = require __DIR__ . '/../../../conf/core/require-cli.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Daemon\Kernel('Update rubric group and criterion tables', [
    'verbose' => $opts['v'] ?? [1, 2, 3],
    'errors'  => E_ALL,
    'fields'  => array_keys($opts),
]);

/** @noinspection PhpUnhandledExceptionInspection */
$kernel->run(
    /**
     * @param Di\Container  $di
     * @param LoopInterface $loop
     * @param Logger        $log
     *
     * @throws Di\Exception\ServiceNotFound
     */
    function (Di\Container $di, LoopInterface $loop, Logger $log) {
        /** @var IConnector $db */
        $db = $di->get('databaseInternal');

        // Query for finding criteria
        // If and only if the criteria grouping has only deleted criteria:
        // the grouping is deleted at the time its most recently deleted
        // criterion was deleted.
        // It is not deleted if it has any non-deleted criteria.
        $selectCriteriaQuery = $db->prepareQuery("
            SELECT DISTINCT
                `grouping`,
                `parent_id`,
                `parent_type`,
                IF (MAX(`deleted_at` IS NULL) = 0, MAX(`deleted_at`), NULL) AS `deleted_at`
            FROM `rubric_criterion`
            WHERE `rubric_group_id` IS NULL
            GROUP BY `grouping`, `parent_id`, `parent_type`
            ORDER BY `parent_type`, `parent_id`, `grouping`
        ");

        // Insert a new rubric group
        $insertRubricGroupQuery = $db->prepareQuery("
            INSERT INTO rubric_group
                (`name`, `parent_id`, `parent_type`, `deleted_at`)
            VALUES (:name, :parentId, :parentType, :deletedAt)
        ");

        // Associate rubric criteria with their group
        $associateRubricCriteriaQuery = $db->prepareQuery("
            UPDATE `rubric_criterion`
            SET `rubric_group_id` = :rubricGroupId
            WHERE grouping = :grouping AND parent_id = :parentId AND parent_type = :parentType
        ");


        // Run update
        $db->beginTransaction();
        $selectCriteriaQuery->execute();
        $criteriaCount = $selectCriteriaQuery->rowCount();
        $log->info(sprintf('Migrating %d distinct rubric_criterion rows...', $criteriaCount));
        foreach ($selectCriteriaQuery->getIterator() as $i => $row) {

            // Insert new rubric_group rows
            $log->info(
                sprintf(
                    'Migrating rubric_criterion %d/%d (%s #%d: %s) to rubric_group...',
                    $i+1,
                    $criteriaCount,
                    $row['parent_type'],
                    $row['parent_id'],
                    $row['grouping']
                )
            );
            $insertRubricGroupQuery->execute([
                'name'       => $row['grouping'],
                'parentId'   => $row['parent_id'],
                'parentType' => $row['parent_type'],
                'deletedAt'  => $row['deleted_at'],
            ]);
            $rubricGroupId = $db->lastInsertId();
            $log->debug(sprintf('rubric_group #%d inserted', $rubricGroupId));


            // Associate existing rubric_criterion rows with new rubric_group
            $associateRubricCriteriaQuery->execute([
                'grouping'      => $row['grouping'],
                'parentId'      => $row['parent_id'],
                'parentType'    => $row['parent_type'],
                'rubricGroupId' => $rubricGroupId,
            ]);
            $log->debug(
                sprintf(
                    '%d rubric_criterion rows associated with rubric_group #%d',
                    $associateRubricCriteriaQuery->rowCount(),
                    $rubricGroupId
                )
            );
        }

        $db->commit();
    }
);
