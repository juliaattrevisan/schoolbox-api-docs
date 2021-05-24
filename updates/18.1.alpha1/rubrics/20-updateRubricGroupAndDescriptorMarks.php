#!/usr/bin/php
<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Alaress\Database\Connector\IConnector;
use Aura\Di;
use Psr\Log\LoggerInterface as Logger;
use React\EventLoop\LoopInterface;
use Schoolbox\Application\Daemon;

require __DIR__ . '/../../../conf/core/require.php';

$kernel = new Daemon\Kernel(
    'Populate rubric_group_mark and associate to rubric_descriptor_mark',
    [
        Daemon\Kernel::OPTION_LEVEL  => Monolog\Logger::DEBUG,
        Daemon\Kernel::OPTION_ERRORS => E_ALL,
    ]
);

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

        // Query for finding descriptor marks.
        $selectDescriptorMarksQuery = $db->prepareQuery("
            SELECT
                GROUP_CONCAT(rdm.id) AS ids,
                rc.rubric_group_id,
                rdm.return_id,
                SUM(rdm.value) AS value,
                -- Assume that the group mark is valid only if all the descriptor marks are
                MAX(rdm.valid) AS valid
            FROM rubric_descriptor_mark rdm
            INNER JOIN rubric_descriptor rd ON rdm.descriptor_id=rd.id
            INNER JOIN rubric_criterion  rc ON rd.criterion_id=rc.id
            GROUP BY rdm.return_id, rc.rubric_group_id
            ORDER BY rdm.id
        ");

        // Insert a new rubric group mark
        $insertRubricGroupMarkQuery = $db->prepareQuery("
            INSERT INTO rubric_group_mark
                (`return_id`, `rubric_group_id`, `value`, `valid`)
            VALUES (:returnId, :rubricGroupId, :value, :valid)
        ");

        // Associate rubric descriptor marks with their group mark
        $associateRubricDescriptorMarkQuery = $db->prepareQuery("
            UPDATE `rubric_descriptor_mark`
            SET `rubric_group_mark_id` = :rubricGroupMarkId
            WHERE id = :id
        ");

        // Run update
        $db->beginTransaction();
        $selectDescriptorMarksQuery->execute();
        $descriptorMarkCount = $selectDescriptorMarksQuery->rowCount();
        $log->info(sprintf('Migrating %d distinct groups of rubric_descriptor_mark rows...', $descriptorMarkCount));
        foreach ($selectDescriptorMarksQuery->getIterator() as $i => $row) {
            // Insert new rubric_group rows
            $log->info(
                sprintf(
                    'Migrating rubric_descriptor_mark group %d/%d (rubric_group_id #%d, return_id #%d) to rubric_group...',
                    $i + 1,
                    $descriptorMarkCount,
                    $row['rubric_group_id'],
                    $row['return_id']
                )
            );
            $insertRubricGroupMarkQuery->execute([
                'returnId'      => $row['return_id'],
                'rubricGroupId' => $row['rubric_group_id'],
                'value'         => $row['value'],
                'valid'         => $row['valid'],
            ]);
            $rubricGroupMarkId = $db->lastInsertId();
            $log->debug(sprintf('rubric_group_mark #%d inserted', $rubricGroupMarkId));


            // Associate existing rubric_criterion rows with new rubric_group
            if (!empty($row['ids'])) {
                $ids = explode(',', $row['ids']);
                foreach ($ids as $id) {
                    $associateRubricDescriptorMarkQuery->execute([
                        'id'                => $id,
                        'rubricGroupMarkId' => $rubricGroupMarkId,
                    ]);
                }
                $log->debug(
                    sprintf(
                        'rubric_descriptor_mark rows %s associated with rubric_group #%d',
                        $row['ids'],
                        $rubricGroupMarkId
                    )
                );
            }
        }

        $db->commit();
    }
);
