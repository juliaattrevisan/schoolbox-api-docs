<?php
/**
 * Restores rubric descriptors to submission boxes in course builders that had
 * them stripped.
 *
 * 15.6 rubrics have the following issues when imported from course builder:
 *
 * Course builder submission box:
 * * rubric_descriptor:
 *   * does not exist (moved from outline to builder)
 * * rubric_criterion
 *   * not touched
 * * rubric_level
 *   * not touched
 *
 * Course outline submission box:
 * * rubric_descriptor:
 *   * criterion_id: points to the outline's criterion
 *   * level_id:     points to the builder's level
 * * rubric_criterion:
 *   * imported okay
 * * rubric_level:
 *   * does not exist
 *
 * Fix:
 * # update rubric_descriptor.submission_box_id to point to builder instead of outline
 * # update rubric_descriptor.criterion_id to point to builder criterion
 * # re-copy descriptors and levels from builder to outline
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\Assessment\Component\SubmissionBox;

require_once __DIR__ . '/../../../include/Bootstrap.php';

$db     = Database::getInstance();
$logger = new Alaress\Lumberjack\Logger();

// This query fetches submission boxes that are a part of a course outline,
// along with their base box from the course builder,
// and the number of descriptors that the imported and base box have.
$taskQuery = $db->prepare("
    SELECT
        importedBox.id AS importedBoxId,
        importedBox.category AS importedBoxFolderId,
        baseBox.id AS baseBoxId,
        baseBox.category AS baseBoxFolderId,
        baseFolder.parent AS baseBoxParentFolderId,
        importedBox.created,
        COUNT(DISTINCT importedDescriptor.id) AS importedDescriptorCount,
        COUNT(DISTINCT baseDescriptor.id) AS baseDescriptorCount,
        COUNT(DISTINCT importedLevel.id) AS importedLevelCount,
        COUNT(DISTINCT baseLevel.id) AS baseLevelCount
    FROM submission_box importedBox
    LEFT  JOIN rubric_descriptor importedDescriptor ON importedBox.id = importedDescriptor.submission_box_id
    LEFT  JOIN rubric_level      importedLevel      ON importedBox.id = importedLevel.box_id
    INNER JOIN task_instance     importedTask       ON importedBox.task_instance_id = importedTask.id
    INNER JOIN task              baseTask           ON importedTask.task_id = baseTask.id
    INNER JOIN submission_box    baseBox            ON baseTask.id = baseBox.task_id
    INNER JOIN category          baseFolder         ON baseBox.category = baseFolder.id
    LEFT  JOIN rubric_descriptor baseDescriptor     ON baseBox.id = baseDescriptor.submission_box_id
    LEFT  JOIN rubric_level      baseLevel          ON baseBox.id = baseLevel.box_id
    WHERE importedBox.task_instance_id IS NOT NULL
    AND   importedBox.created >= '2016-01-01'
    AND   importedBox.deleted = 0
    AND   baseBox.deleted = 0
    GROUP BY importedBoxId, baseBoxId, importedBoxFolderId, baseBoxFolderId, importedBox.created
    ORDER BY importedBox.created ASC
");
$taskQuery->execute();

$updateDescriptorQuery = $db->prepare("
    UPDATE rubric_descriptor
    SET    submission_box_id = :baseBoxId,
           criterion_id      = (
            SELECT baseCriterion.id
            FROM rubric_criterion baseCriterion
            LEFT JOIN rubric_criterion importedCriterion
                   ON baseCriterion.grouping = importedCriterion.grouping
                  AND baseCriterion.name     = importedCriterion.name
            WHERE baseCriterion.parent_id     IN (:baseBoxFolderId, :baseBoxParentFolderId)
            AND   importedCriterion.parent_id = :importedBoxFolderId
            LIMIT 1
           )
    WHERE  submission_box_id = :importedBoxId
");

$updatedBoxes = [];
$logger->info(sprintf('%d submission boxes imported from a course in 2016', $taskQuery->rowCount()));
foreach ($taskQuery as $row) {
    $logger->info(
        sprintf(
            'Checking imported box #%d, folder #%d (base box #%d, folder #%d)...',
            $row['importedBoxId'],
            $row['importedBoxFolderId'],
            $row['baseBoxId'],
            $row['baseBoxFolderId']
        )
    );

    if ($row['baseDescriptorCount'] == $row['importedDescriptorCount']) {
        $logger->info(sprintf('Base and imported box both have %d descriptors - no change required', $row['baseDescriptorCount']));
    } else {
        $logger->warning('Base and imported box descriptor counts do not match - attempting repair!');
        if ($row['baseDescriptorCount'] == 0) {
            $logger->info('Base box has no descriptors - moving them from imported box');
            $updateDescriptorQuery->execute([
                'baseBoxId'             => $row['baseBoxId'],
                'importedBoxId'         => $row['importedBoxId'],
                'importedBoxFolderId'   => $row['importedBoxFolderId'],
                'baseBoxFolderId'       => $row['baseBoxFolderId'],
                'baseBoxParentFolderId' => $row['baseBoxParentFolderId'],
            ]);
            $updatedBoxes[] = $row;
        } else {
            $logger->warning('Base box already has descriptors - no repair will be attempted!');
        }
    }
}

foreach ($updatedBoxes as $row) {
    $logger->info(
        sprintf(
            'Copying descriptors from base box #%d to imported box #%d...',
            $row['baseBoxId'],
            $row['importedBoxId']
        )
    );
    $logger->info('Copying descriptors back from imported box to base box');
    SubmissionBox\Rubric\DescriptorMapper::getInstance()
        ->copyFromCopiedSubmissionBox($row['baseBoxId'], $row['importedBoxId'], true);

    $logger->info('Copying usages from base box to imported box');
    SubmissionBox\Rubric\UsageMapper::getInstance()
        ->copyFromCopiedSubmissionBox($row['baseBoxId'], $row['importedBoxId']);

    $logger->info('Copying levels from base box to imported box');
    SubmissionBox\Rubric\LevelMapper::getInstance()
        ->copyFromCopiedSubmissionBox($row['baseBoxId'], $row['importedBoxId']);
}

// Tell them how to undo this
$logger->info('Cleanup completed!');
