<?php
/**
 * Prior to Jan 2016, rubrics did not have descriptors + levels, instead they derived these from a ResultSet.
 * Code was added in Jan to convert such ResultSets to descriptors + levels when such a rubric was loaded.
 *
 * This script essentially applies the run-time rubric-load logic to all matching rubrics in the database.
 *
 * A Box qualifies if:
 * * box.rubric_result_set is not 0
 * * box uses 1+ criteria (ie is exists in rubric_usage table)
 * * box does not have any rubric_descriptors (even deleted ones)
 *
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Psr\Log\LoggerInterface;
use Schoolbox\Mapper\Exception as MapperException;
use Monolog\Logger;

require_once __DIR__ . '/../../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Insert Rubric Descriptor Levels', [
    'verbose' => [ false, false, false ],
    'errors'  => E_ALL,
]);

const INVALID = -1;

$kernel->run(function($di, $loop, LoggerInterface $log, $opts) {

    $db = Database::getInstance();

    // Find Boxes which have a RubricResultSet, Usages but no Descriptors
    // TODO - note the ORDER BY value DESC. This puts higher levels first. Is this still canonical?
    $selectQuery = $db->prepare("
        SELECT
          sb.id           AS 'sb.id',
          sb.category     AS 'sb.category',
          rr.label        AS 'rr.label',
          rr.value        AS 'rr.value',
          ru.criterion_id AS 'ru.criterion_id',
          rc.parent_id    AS 'rc.parent_id'
        FROM submission_box sb
        JOIN rubric_result rr ON rr.resultset_id = sb.rubric_result_set 
        LEFT JOIN rubric_descriptor rd ON rd.submission_box_id = sb.id
        JOIN rubric_usage ru ON ru.submission_box_id = sb.id
        JOIN rubric_criterion rc ON rc.id = ru.criterion_id
        WHERE sb.rubric_result_set != 0
        AND rd.submission_box_id IS NULL
        ORDER BY sb.id, rr.value DESC
    ");

    $insertLevelQuery = $db->prepare("
        INSERT INTO rubric_level (`box_id`, `name`, `sequence`)
        VALUES (:box_id, :name, :sequence )
    ");

    $insertDescriptorQuery = $db->prepare("
        INSERT INTO rubric_descriptor (`criterion_id`, `level_id`, `value`, `description`, `valid`)
        VALUES (:criterion_id, :level_id, :value, :description, :valid )
    ");


    $successRubricLevelCount = 0;
    $warningRubricLevelCount = 0;
    $failureRubricLevelCount = 0;

    $successRubricDescriptorCount = 0;
    $warningRubricDescriptorCount = 0;
    $failureRubricDescriptorCount = 0;

    $selectQuery->execute();
    $log->info(sprintf('%d descriptors missing for boxes without descriptors but have usages and non-0 resultSets', $selectQuery->rowCount()));

    // Each time we hit a new boxId + value combo, we need to create a new level
    $previousBoxId = INVALID;
    $previousValue = INVALID;
    $sequence      = INVALID;
    $levelId       = INVALID;

    $mismatchFolderCount = 0;

    while ($row = $selectQuery->fetch(PDO::FETCH_ASSOC) ){
        $boxId       = intval($row['sb.id']);
        $label       = $row['rr.label'];         // => descriptor.description
        $value       = intval($row['rr.value']); // => descriptor.value
        $criterionId = $row['ru.criterion_id'];

        // extra sanity check
        $boxCategory = intval($row['sb.category']);
        $crtCategory = intval($row['rc.parent_id']);
        if($boxCategory != $crtCategory){
            $log->warning(sprintf('Corrupt data detected. BoxFolder: "%d" != CriterionFolder: "%d"!', $boxCategory, $crtCategory));
            $mismatchFolderCount++;
        }


        // Have we hit a new box ?
        if($boxId != $previousBoxId){
            $previousBoxId = $boxId;
            $sequence = 1; // reset level sequence
        }
        // Have we hit a new level ?
        if($value != $previousValue){
            $previousValue = $value;
            $levelName = '';  // Note empty $name. The system detects all-empty level names and omits header row to reduce visual clutter
            $log->debug(sprintf('Creating level. Name: "%s", BoxId: "%d", Sequence: "%s"...', $levelName, $boxId, $sequence));

            try{
                $insertLevelQuery->execute([
                    'box_id'   => $boxId,
                    'name'     => $levelName,
                    'sequence' => $sequence,
                ]);
                $levelId = $db->lastInsertId();
                $log->info(sprintf('Level created. Id: "%s", Name: "%s", BoxId: "%d", Sequence: "%s"', $levelId, $levelName, $boxId, $sequence));
                $successRubricLevelCount++;
            } catch (PDOException $e) {
                $levelId = INVALID;
                $log->error(sprintf('Level could not be created!. Name: "%s", BoxId: "%d", Sequence: "%s"', $levelName, $boxId, $sequence), [$e]);
                $log->warning($e->getMessage());
                $failureRubricLevelCount++;
            }
            $sequence++;
        }

        // Create Descriptor

        // Did the level creation fail? If so, log it but skip level's descriptors
        if(INVALID == $levelId){
            $log->warning(sprintf('No level. Skipping descriptor. Criterion: "%d", Level: "%d", Value: "%d", Description: "%s", Valid: "%d"...', $criterionId, $levelId, $value, $label, true));
        } else {
            $log->debug(sprintf('Creating descriptor. Criterion: "%d", Level: "%d", Value: "%d", Description: "%s", Valid: "%d"...', $criterionId, $levelId, $value, $label, true));

            try{
                // Update descriptor to point to new level
                $insertDescriptorQuery->execute([
                    'criterion_id' => $criterionId,
                    'level_id'     => $levelId,
                    'value'        => $value,
                    'description'  => $label,
                    'valid'        => true,
                ]);
                $successRubricDescriptorCount++;
                $descriptorId = $db->lastInsertId();
                $log->info(sprintf('Descriptor created (id: "%d"). Criterion: "%d", Level: "%d", Value: "%d", Description: "%s", Valid: "%d"...',
                    $descriptorId, $criterionId, $levelId, $value, $label, true));
            } catch (PDOException $e) {
                $log->error(sprintf('Unable to create descriptor. Criterion: "%d", Level: "%d", Value: "%d", Description: "%s", Valid: "%d"...',
                    $criterionId, $levelId, $value, $label, true), [$e]);
                $log->warning($e->getMessage());
                $failureRubricDescriptorCount++;
            }
        }
    }

    // Did we detect cases where box.category != usage.criterion.parent_id ?
    $misMatchedFoldersDetected = $mismatchFolderCount > 0;
    if($misMatchedFoldersDetected){
        $log->warning(sprintf('%d instances where box.category != usage.criterion.parent_id', $mismatchFolderCount));
    }

    // Log RubricLevel results at appropriate level
    $rubricLevelMessage = sprintf('%d rubric_levels created (%d warnings), %d failures', $successRubricLevelCount, $warningRubricLevelCount, $failureRubricLevelCount);
    $level = $failureRubricLevelCount ? Logger::ERROR : ($warningRubricLevelCount ? Logger::WARNING : Logger::INFO);
    $log->log($level, $rubricLevelMessage);

    // Log RubricDescriptor results at appropriate level
    $rubricDescriptorMessage = sprintf('%d rubric_descriptors updated (%d warnings), %d failures', $successRubricDescriptorCount, $warningRubricDescriptorCount, $failureRubricDescriptorCount);
    $level = $failureRubricDescriptorCount ? Logger::ERROR : ($warningRubricDescriptorCount ? Logger::WARNING : Logger::INFO);
    $log->log($level, $rubricDescriptorMessage);

    // Any SQL failures ?
    $failuresDetected = ($failureRubricLevelCount + $failureRubricDescriptorCount) > 0;

    exit(($misMatchedFoldersDetected || $failuresDetected) ? 1 : 0);
});
