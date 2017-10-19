<?php
/**
 * MUST BE EXECUTED AFTER SQL UPDATES!
 *
 * Soft-delete rubric levels that do not contain any descriptors.
 *
 * * If the level contains soft-deleted descriptors, set the level's soft-delete date to
 *   when the last descriptor was deleted.
 * * If the level contains no descriptors at all, set the level's soft-delete date to now.
 *
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Aura\Di;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;

$options = require __DIR__ . '/../../conf/core/require-cli.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel(
    'Soft-delete empty Rubric Levels',
    [
        'verbose' => isset($options['v']) ? $options['v'] : [false, false, false],
        'errors'  => E_ALL,
    ]
);

$kernel->run(function (
    /** @noinspection PhpUnusedParameterInspection */
    Di\Container $di,
    LoopInterface $loop,
    LoggerInterface $log
) {

    $db           = Database::getInstance();
    $successCount = 0;
    $failureCount = 0;
    $nowDate      = Alaress_Date_Format::now()->getDatabase();

    // Find all non-deleted levels that lack non-deleted descriptors
    $selectLevelsWithoutDescriptorsStatement = $db->prepare('
        SELECT rl.id FROM rubric_level rl
        LEFT JOIN (
            SELECT id, level_id FROM rubric_descriptor WHERE deleted_at IS NULL
        ) rd ON rd.level_id = rl.id
        WHERE rl.deleted_at IS NULL AND rd.id IS NULL;
    ');

    // Get when last descriptor was deleted (or null if no descriptors at all)
    $selectLastDescriptorDeletionQuery = $db->prepare('
        SELECT MAX(rd.deleted_at) AS max_deleted_at
        FROM rubric_level rl
        LEFT JOIN rubric_descriptor rd ON rd.level_id = rl.id
        WHERE rl.id = :level_id
        GROUP BY rl.id;
    ');

    // Set the level's deleted_at field.
    $updateLevelDeletion = $db->prepare('
        UPDATE rubric_level rl SET deleted_at = :deleted_at WHERE id = :level_id;
    ');

    $log->info('Searching for non-deleted RubricLevels without non-deleted descriptors...');
    $selectLevelsWithoutDescriptorsStatement->execute();

    $countEmptyLevels = $selectLevelsWithoutDescriptorsStatement->rowCount();
    if (!$countEmptyLevels) {
        $log->info('No empty rubric levels found');
    } else {
        $log->info("{$countEmptyLevels} empty rubric levels found");

        $levelIds = $selectLevelsWithoutDescriptorsStatement->fetchAll(PDO::FETCH_COLUMN);

        foreach ($levelIds as $levelId) {
            $selectLastDescriptorDeletionQuery->execute(['level_id' => $levelId]);
            $lastDeletionDate = $selectLastDescriptorDeletionQuery->fetchColumn();

            if ($lastDeletionDate) {
                $log->debug("Last descriptor deleted at '$lastDeletionDate'");
                $newDeleteDate = $lastDeletionDate;
            } else {
                $log->debug('Level has no descriptors at all. Setting deleted at to NOW.');
                $newDeleteDate = $nowDate;
            }
            $log->info(sprintf("Setting last deletion date for level (id: %d) to '%s'...", $levelId, $newDeleteDate));

            try {
                $updateLevelDeletion->execute([
                    'deleted_at' => $newDeleteDate,
                    'level_id'   => $levelId,
                ]);
                $successCount++;
            } catch (PDOException $e) {
                $log->error(sprintf("Could not update level (id: %d) deletion date to '%s'", $levelId, $newDeleteDate), [$e]);
                $log->warning($e->getMessage());
                $failureCount++;
            }
        }
    }
    $log->info(sprintf('%d rubric level deleted_at successfully updated. %d failures.', $successCount, $failureCount));
    exit($failureCount > 0 ? 1 : 0);
});
