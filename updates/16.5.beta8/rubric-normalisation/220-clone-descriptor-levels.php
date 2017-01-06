<?php
/**
 * Copies rubric_levels from AssessmentSpec to AssessmentImpl
 *
 * There exists corrupt data such that:
 *   `descriptor`.`box_id` != `descriptor`.`level`.`box_id`
 *
 * This is thought to be caused by levels not being cloned during course import.
 *
 * The resolution is to:
 * * clone such levels,
 * * link the level to the right box, and
 * * link each descriptor to the new level
 *
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Psr\Log\LoggerInterface;
use Schoolbox\Mapper\Exception as MapperException;

require_once __DIR__ . '/../../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Clone Rubric Descriptor Levels', [
    'verbose' => [ false, false, false ],
    'errors'  => E_ALL,
]);

$kernel->run(function($di, $loop, LoggerInterface $log, $opts) {

    // Perform the database actions in hand-crafted SQL.
    // This is because the code may change between updates
    $db = Database::getInstance();

    // Group descriptors by box and level where `descriptor.level.box` != `descriptor.box`
    // Also get count to enable sanity check
    $selectDescriptorsByBoxLevel = $db->prepare("
        SELECT
          count(*)             'count',
          rd.submission_box_id 'rd.box_id',
          rd.level_id          'rl.id',
          rl.name              'rl.name',
          rl.sequence          'rl.sequence'
        FROM rubric_descriptor rd
        JOIN rubric_level rl ON rl.id = rd.level_id
        WHERE rl.box_id != rd.submission_box_id
        GROUP BY rd.submission_box_id, rd.level_id
    ");

    $insertLevelQuery = $db->prepare("
        INSERT INTO rubric_level (`box_id`, `name`, `sequence`)
        VALUES (:box_id, :name, :sequence )
    ");

    $selectDescriptors = $db->prepare("
        SELECT rd.id 'rd.id'
        FROM rubric_descriptor rd
        WHERE rd.submission_box_id = :box_id
        AND rd.level_id = :level_id
    ");

    $updateDescriptorLevelQuery = $db->prepare("UPDATE rubric_descriptor SET level_id = :level_id WHERE id = :descriptor_id");

    $successRubricLevelCount = 0;
    $warningRubricLevelCount = 0;
    $failureRubricLevelCount = 0;

    $successRubricDescriptorCount = 0;
    $warningRubricDescriptorCount = 0;
    $failureRubricDescriptorCount = 0;

    $selectDescriptorsByBoxLevel->execute();
    $log->info(sprintf('%d sets of descriptors found where "descriptor.box" != "descriptor.level.box"', $selectDescriptorsByBoxLevel->rowCount()));


    while ($row = $selectDescriptorsByBoxLevel->fetch(PDO::FETCH_ASSOC) ){
        $badDescriptorCount = intval($row['count']);
        $descriptorBoxId    = intval($row['rd.box_id']);
        $oldLevelId         = intval($row['rl.id']);
        $levelName          = $row['rl.name'];
        $levelSequence      = intval($row['rl.sequence']);

        $log->debug(sprintf('Creating level. Name: "%s", BoxId: "%d", Sequence: "%s"...', $levelName, $descriptorBoxId, $levelSequence));

        // Create new level
        try {
            $insertLevelQuery->execute([
                'box_id'   => $descriptorBoxId,
                'name'     => $levelName,
                'sequence' => $levelSequence,
            ]);
            $newLevelId = $db->lastInsertId();
            $log->info(sprintf('Level created. Id: "%s"...', $newLevelId));
            $successRubricLevelCount++;

            // Update all descriptors to point to new level
            $selectDescriptors->execute([
                'box_id'   => $descriptorBoxId,
                'level_id' => $oldLevelId,
            ]);
            $descriptorsCount = $selectDescriptors->rowCount();

            // Is this count equal to the count when we filtered for mismatched levels ?
            // ie, in the first outermost query we got the number of descriptors per box and level where the level was corrupt.
            // we have now retrieved a set of descriptors for the same box and level, and wish to ensure this set does
            // not contain descriptors with non-corrupt levels.
            if($badDescriptorCount != $descriptorsCount){
                $log->warning(sprintf('Descriptor count. Box: "%d", Level: "%d",  BadCount: "%d", AllCount: "%d"',
                    $descriptorBoxId, $oldLevelId, $badDescriptorCount, $descriptorsCount));
            }

            // TODO - Consider, should we continue anyway if counts don't match?

            while ($row = $selectDescriptors->fetch(PDO::FETCH_ASSOC) ){
                $descriptorId = intval($row['rd.id']);

                $log->debug(sprintf('Updating descriptor (id: %d) `level_id` from "%d" to "%d" ...', $descriptorId, $oldLevelId, $newLevelId));

                try{
                    // Update descriptor to point to new level
                    $updateDescriptorLevelQuery->execute([
                        'level_id'      => $newLevelId,
                        'descriptor_id' => $descriptorId,
                    ]);
                    $successRubricDescriptorCount++;
                    $log->info(sprintf('Descriptor (id: %d) `level_id` updated from "%d" to "%d"', $descriptorId, $oldLevelId, $newLevelId));
                } catch (PDOException $e) {
                    $log->error(sprintf('Unable to update descriptor (id: %d) `level_id` from "%d" to "%d"',
                        $descriptorId, $oldLevelId, $newLevelId), [$e]);
                    $log->warning($e->getMessage());
                    $failureRubricLevelCount++;
                }
            }

        } catch (PDOException $e) {
            $log->error(sprintf('Level could not be created!. Name: "%s", BoxId: "%d", Sequence: "%s"',
                $levelName, $descriptorBoxId, $levelSequence), [$e]);
            $log->warning($e->getMessage());
            $failureRubricLevelCount++;
        }
    }

    $log->info(sprintf('%d rubric_levels created (%d warnings), %d failures',
        $successRubricLevelCount, $warningRubricLevelCount, $failureRubricLevelCount));
    $log->info(sprintf('%d rubric_descriptors updated (%d warnings), %d failures',
        $successRubricDescriptorCount, $warningRubricDescriptorCount, $failureRubricDescriptorCount));

    exit(($failureRubricLevelCount + $failureRubricDescriptorCount) > 0 ? 1 : 0);
});
