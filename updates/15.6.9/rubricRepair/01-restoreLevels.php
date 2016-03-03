<?php
/**
 * Rubric repair part n
 *
 * As of 15.6.8 rubrics still have the following issues:
 *
 * Course outline submission box:
 * * rubric_descriptor:
 *   * level_id: still points to the builder's level (for newly imported courses)
 *
 * This prevents a student's rubric from ever being saved.
 *
 * Fix:
 * # update rubric_descriptor.level_id to point to outline submission_box_id instead of builder
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\Assessment\Component\SubmissionBoxMapper;

require_once __DIR__ . '/../../../include/Bootstrap.php';

$db     = Database::getInstance();
$logger = new Alaress\Lumberjack\Logger();

// This query get descriptors whose box ID doesn't match their level's box ID.
$descriptorQuery = $db->prepare("
    SELECT DISTINCT
        rd.submission_box_id boxId,
        rl.id                levelId,
        rl.sequence          levelSequence,
        rl.name              levelName,
        rl.box_id            levelBoxId
    FROM rubric_descriptor rd
    INNER JOIN submission_box sb ON rd.submission_box_id = sb.id AND sb.deleted IS FALSE
    INNER JOIN rubric_level   rl ON rd.level_id = rl.id
    WHERE rl.box_id != rd.submission_box_id
    AND   sb.created >= '2016-01-01'
    ORDER BY
        rd.submission_box_id ASC,
        rl.sequence          ASC
");

$updateQuery = $db->prepare("
    UPDATE rubric_level
    SET box_id = :boxId
    WHERE id = :levelId
");

$affectedBoxes = [];
$descriptorQuery->execute();
foreach ($descriptorQuery as $row) {
    $affectedBoxes[$row['boxId']] = true;
    $logger->info(
        sprintf(
            'Fixing level %d (%s) for submission box #%d',
            $row['levelSequence'],
            $row['levelName'] ?: 'no name',
            $row['boxId']
        )
    );
    $updateQuery->execute(['boxId' => $row['boxId'], 'levelId' => $row['levelId']]);
}

// Alert about affected boxes
$affectedBoxes = array_keys($affectedBoxes);
$logger->info(sprintf('%d potentially broken submission boxes were found.', count($affectedBoxes)));
if (count($affectedBoxes)) {
    $logger->info('The following boxes will need to be re-marked:');
    foreach ($affectedBoxes as $boxId) {
        try {
            $box    = SubmissionBoxMapper::getInstance()->findById($boxId);
            $folder = Schoolbox\Resource\Folder\Mapper::getInstance()->findById($box->getCategory()->getId());
        } catch (\Schoolbox\Mapper\Exception $e) {
            // Not much I can do
            continue;
        }
        $folderName = $folder->getName();
        $codeString = $folder->getCodeString();
        if ($codeString) {
            $folderName .= ' (' . $codeString . ')';
        }
        $logger->info(
            sprintf(
                '#%d (%s), in folder #%d (%s)',
                $box->getId(),
                $box->getName(),
                $folder->getId(),
                $folderName
            )
        );
    }
}
