<?php
/**
 * Continuation of `updates/15.6.9/rubricRepair/01-notifyOfBrokenSubmissionBoxes.php`
 *
 * That script detected broken boxes, this script fixes them.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\Assessment\Component\SubmissionBoxMapper;

require_once __DIR__ . '/../../../conf/core/require.php';
spl_autoload_call('Schoolbox\\Application\\Daemon');
new \Schoolbox\Application\Daemon\Kernel("15.6.10 Rubric Level Restore");


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

$updateLevelQuery = $db->prepare("
    UPDATE rubric_level
    SET box_id = :boxId
    WHERE id = :levelId
");

$obtainIncorrectLevelsQuery = $db->prepare("
    SELECT id, sequence
    FROM rubric_level
    WHERE id IN (
      SELECT DISTINCT level_id
      FROM rubric_descriptor d
      LEFT JOIN rubric_level l ON d.level_id = l.id
      WHERE submission_box_id = :boxId
    )
");

$obtainCorrectLevelsQuery = $db->prepare("
    SELECT id, sequence
    FROM rubric_level l
    WHERE box_id = :boxId
");

$updateDescriptorQuery = $db->prepare("
    UPDATE rubric_descriptor
    SET level_id = :correctLevelId
    WHERE submission_box_id = :boxId
    AND level_id = :incorrectLevelId
");


$affectedBoxes = [];
$descriptorQuery->execute();
foreach ($descriptorQuery as $row) {
    $affectedBoxes[$row['boxId']] = true;
//    $logger->info(
//        sprintf(
//            'Fixing level %d (%s) for submission box #%d',
//            $row['levelSequence'],
//            $row['levelName'] ?: 'no name',
//            $row['boxId']
//        )
//    );
//
//    $map = [];
//
//    // obtain references
//    $obtainIncorrectLevelsQuery->execute([ 'boxId' => $row['boxId'] ]);
//    while ($r = $obtainIncorrectLevelsQuery->fetch(PDO::FETCH_ASSOC)) {
//        $map[$r['sequence']]['bad'] = $r['id'];
//    }
//
//    $obtainCorrectLevelsQuery->execute([ 'boxId' => $row['boxId'] ]);
//    while ($r = $obtainCorrectLevelsQuery->fetch(PDO::FETCH_ASSOC)) {
//        $map[$r['sequence']]['good'] = $r['id'];
//    }
//
//    // update the levels
//    $updateLevelQuery->execute(['boxId' => $row['boxId'], 'levelId' => $row['levelId']]);
//
//    // now update the descriptors
//    foreach ($map as $sequence => $changes) {
//
//        if ($changes['bad'] && $changes['good']) {
//            $logger->info(sprintf("Remapping descriptor level IDs: %d -> %d", $changes['bad'], $changes['good']));
//            $updateDescriptorQuery->execute([ 'boxId' => $row['boxId'], 'correctLevelId' => $changes['good'], 'incorrectLevelId' => $changes['bad'] ]);
//        } else {
//            $logger->notice("Could not remap descriptor levels, data missing: ".json_encode($changes));
//        }
//
//    }

}

// Alert about affected boxes
$affectedBoxes = array_keys($affectedBoxes);
$logger->info(sprintf('%d potentially broken submission boxes were found.', count($affectedBoxes)));
if (count($affectedBoxes)) {
    $logger->info('The following boxes should be re-saved and re-marked:');
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
        $logger->info(
            sprintf(
                '(Direct link: http://%s.%s/submission/createSubmission/modify/%d)',
                Config::get('site_hostname'),
                Config::get('site_domain'),
                $box->getId()
            )
        );
    }
}
