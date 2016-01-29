<?php
/**
 * Restores rubric descriptors to submission boxes in course builders that had
 * them stripped.
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
    AND   importedBox.deleted = 0
    AND   baseBox.deleted = 0
    GROUP BY importedBoxId, baseBoxId, importedBoxFolderId, baseBoxFolderId, importedBox.created
    ORDER BY importedBox.created ASC
");
$taskQuery->execute();

$logger->info(sprintf('%d submission boxes imported from a course in 2016', $taskQuery->rowCount()));
foreach ($taskQuery as $row) {
    $logger->info(sprintf('Checking imported box #%d (base box #%d)...', $row['importedBoxId'], $row['baseBoxId']));

    // Check descriptors
    if ($row['baseDescriptorCount'] == $row['importedDescriptorCount']) {
        $logger->info(sprintf('Base and imported box both have %d descriptors - no change required', $row['baseDescriptorCount']));
    } else {
        $logger->warning('Base and imported box descriptor counts do not match - attempting repair!');
        if ($row['baseDescriptorCount'] == 0) {
            $logger->info('Base box has no descriptors - moving them from imported box');

            $logger->info('Copying descriptors back from imported box to base box');
            SubmissionBox\Rubric\DescriptorMapper::getInstance()
                ->copyFromCopiedSubmissionBox($row['importedBoxId'], $row['baseBoxId'], true);

            $logger->info('Copying usages from base box to imported box');
            SubmissionBox\Rubric\UsageMapper::getInstance()
                ->copyFromCopiedSubmissionBox($row['baseBoxId'], $row['importedBoxId']);

            $logger->info('Copying levels from base box to imported box');
            SubmissionBox\Rubric\LevelMapper::getInstance()
                ->copyFromCopiedSubmissionBox($row['baseBoxId'], $row['importedBoxId']);

        } else {
            $logger->warning('Base box already has descriptors - no repair will be attempted!');
        }
    }
}

// Tell them how to undo this
$logger->info('Cleanup completed!');
