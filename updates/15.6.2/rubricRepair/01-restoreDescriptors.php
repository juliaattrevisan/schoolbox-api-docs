<?php
/**
 * Restores rubric descriptors to submission boxes in course builders that had
 * them stripped.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

require_once __DIR__ . '/../../../include/Bootstrap.php';

$db     = Database::getInstance();
$logger = new Alaress\Lumberjack\Logger();

// This query fetches submission boxes that are a part of a course outline,
// along with their base box from the course builder,
// and the number of descriptors that the imported and base box have.
$taskQuery = $db->prepare("
    SELECT
        importedBox.id AS importedBoxId,
        baseBox.id AS baseBoxId,
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
    LEFT  JOIN rubric_descriptor baseDescriptor     ON baseBox.id = baseDescriptor.submission_box_id
    LEFT  JOIN rubric_level      baseLevel          ON baseBox.id = baseLevel.box_id
    WHERE importedBox.task_instance_id IS NOT NULL
    AND   importedBox.created >= '2016-01-01'
    GROUP BY importedBoxId, baseBoxId, importedBox.created
    ORDER BY importedBox.created DESC
");
$taskQuery->execute();

// This query copies all descriptors from importedBoxId into baseBoxId.
$fixDescriptorQuery = $db->prepare("
    INSERT INTO rubric_descriptor
        (submission_box_id, criterion_id, level_id, value, description, valid)
    SELECT
        :baseBoxId,
        rd.criterion_id,
        rd.level_id,
        rd.value,
        rd.description,
        rd.valid
    FROM rubric_descriptor rd
    WHERE rd.submission_box_id = :importedBoxId
");

// This query copies all levels from baseBoxId to importedBoxId.
$fixLevelQuery = $db->prepare("
    INSERT INTO rubric_level
        (box_id, name, sequence)
    SELECT
        :importedBoxId,
        rl.name,
        rl.sequence
    FROM rubric_level rl
    WHERE rl.box_id = :baseBoxId
");

$fixedDescriptors = [];
$fixedLevels      = [];
$logger->info(sprintf('%d submission boxes imported from a course in 2016', $taskQuery->rowCount()));
foreach ($taskQuery as $row) {
    $logger->info(sprintf('Checking imported box #%d (base box #%d)...', $row['importedBoxId'], $row['baseBoxId']));
    if ($row['baseDescriptorCount'] == $row['importedDescriptorCount']) {
        $logger->info(sprintf('Base and imported box both have %d descriptors - no change required', $row['baseDescriptorCount']));
    } else {
        $logger->warning('Base and imported box descriptor counts do not match - attempting repair!');
        if ($row['baseDescriptorCount'] == 0) {
            $logger->info('Base box has no descriptors - copying back from imported box');
            $fixDescriptorQuery->execute([
                'baseBoxId'     => $row['baseBoxId'],
                'importedBoxId' => $row['importedBoxId'],
            ]);
            $fixedDescriptors[] = $row['baseBoxId'];
        } else {
            $logger->warning('Base box already has descriptors - no repair will be attempted!');
        }
    }
}

// Tell them how to undo this
$logger->info('Cleanup completed!');
if (!empty($fixedDescriptors)) {
    $logger->info('If you need to undo, run the following query:');
    $logger->info('DELETE FROM rubric_descriptor WHERE submission_box_id IN ('.implode(',', $fixedDescriptors).')');
};
