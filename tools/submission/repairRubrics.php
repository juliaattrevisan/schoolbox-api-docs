<?php
/**
 * Rubric repair script
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\Assessment\Component\SubmissionBoxMapper;

require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('Schoolbox\\Application\\Daemon');
new \Schoolbox\Application\Daemon\Kernel("Rubric Repair");

$options = getopt('', ['dry-run', 'porcelain']);
$db      = Database::getInstance();
$logger  = new Alaress\Lumberjack\Logger(STDOUT, null, Monolog\Logger::INFO, [], isset($options['porcelain']));

// This query get boxes with a descriptor whose box ID doesn't match their level's box ID.
$boxQuery = $db->prepare("
    SELECT DISTINCT 
        rd.submission_box_id boxId
    FROM rubric_descriptor rd
    INNER JOIN submission_box sb ON rd.submission_box_id = sb.id AND sb.deleted IS FALSE
    INNER JOIN rubric_level   rl ON rd.level_id = rl.id
    WHERE rl.box_id != rd.submission_box_id
    AND   sb.created >= '2016-01-01'
    ORDER BY
        rd.submission_box_id ASC
");

// This query get descriptors whose box ID doesn't match their level's box ID.
$descriptorQuery = $db->prepare("
    SELECT DISTINCT
        rl.id       levelId,
        rl.sequence levelSequence,
        rl.name     levelName,
        rl.box_id   levelBoxId
    FROM rubric_descriptor rd
    INNER JOIN submission_box sb ON rd.submission_box_id = sb.id AND sb.deleted IS FALSE
    INNER JOIN rubric_level   rl ON rd.level_id = rl.id
    WHERE rl.box_id != rd.submission_box_id
    AND   rd.submission_box_id = :boxId
    ORDER BY
        rl.sequence ASC
");

$obtainIncorrectLevelsQuery = $db->prepare("
    SELECT DISTINCT l.id, l.sequence
    FROM rubric_descriptor d
    LEFT JOIN rubric_level l ON d.level_id = l.id
    WHERE submission_box_id = 876
");

$obtainCorrectLevelsQuery = $db->prepare("
    SELECT id, sequence
    FROM rubric_level l
    WHERE box_id = :boxId
");

$insertCorrectLevelQuery = $db->prepare("
    INSERT INTO rubric_level
    (box_id, name, sequence)
    VALUES
    (:boxId, :levelName, :levelSequence)
");

$updateDescriptorQuery = $db->prepare("
    UPDATE rubric_descriptor
    SET level_id = :correctLevelId
    WHERE submission_box_id = :boxId
    AND level_id = :incorrectLevelId
");


$affectedBoxes    = [];
$fixedBoxes       = [];
$stillBrokenBoxes = [];

$boxQuery->execute();
$boxCount = $boxQuery->rowCount();
foreach ($boxQuery->fetchAll() as $i => $box) {
    $boxId                 = $box['boxId'];
    $affectedBoxes[$boxId] = true;
    $logger->info(sprintf('Fixing submission box #%d (%d/%d)', $boxId, $i+1, $boxCount));

    // Get borked descriptors for this box
    $descriptorQuery->execute(['boxId' => $boxId]);
    $allDescriptors = $descriptorQuery->fetchAll();

    // Fix either all or no descriptors for a box
    $db->beginTransaction();
    try {
        foreach ($allDescriptors as $row) {
            $logger->info(
                sprintf(
                    'Fixing level %d (%s) for submission box #%d',
                    $row['levelSequence'],
                    $row['levelName'] ?: 'no name',
                    $boxId
                )
            );

            $map = [];

            // obtain references
            $obtainIncorrectLevelsQuery->execute([ 'boxId' => $boxId ]);
            while ($r = $obtainIncorrectLevelsQuery->fetch(PDO::FETCH_ASSOC)) {
                $map[$r['sequence']]['bad'] = $r['id'];
            }

            $obtainCorrectLevelsQuery->execute([ 'boxId' => $boxId ]);
            if ($obtainCorrectLevelsQuery->rowCount()) {
                while ($r = $obtainCorrectLevelsQuery->fetch(PDO::FETCH_ASSOC)) {
                    $map[$r['sequence']]['good'] = $r['id'];
                }
            } else {
                $logger->info("Correct level does not exist - adding it");
                $insertCorrectLevelQuery->execute([
                    'boxId'         => $boxId,
                    'levelName'     => $row['levelName'],
                    'levelSequence' => $row['levelSequence'],
                ]);
                $map[$r['sequence']]['good'] = $db->lastInsertId();
            }

            // now update the descriptors
            foreach ($map as $sequence => $changes) {
                if ($changes['bad'] && $changes['good']) {
                    $logger->info(sprintf("Remapping descriptor level IDs: %d -> %d", $changes['bad'], $changes['good']));
                    $updateDescriptorQuery->execute([ 'boxId' => $boxId, 'correctLevelId' => $changes['good'], 'incorrectLevelId' => $changes['bad'] ]);
                } else {
                    // This is more serious than a notice, it requires user intervention.
                    throw new UnexpectedValueException(
                        "Could not remap descriptor levels, data missing: ".json_encode($changes)
                    );
                }
            }
        }
        $fixedBoxes[$boxId] = true;
        $logger->info(sprintf("Submission box #%d fixed!", $boxId));
        if ($options['dry-run']) {
            $db->rollBack();
        } else {
            $db->commit();
        }
    } catch (Exception $e) {
        $logger->warning(sprintf("Fixing submission box #%d failed - %s", $boxId, $e->getMessage()));
        $stillBrokenBoxes[$boxId] = true;
        $db->rollBack();
    }
}

// Alert about affected boxes
$affectedBoxes    = array_keys($affectedBoxes);
$fixedBoxes       = array_keys($fixedBoxes);
$stillBrokenBoxes = array_keys($stillBrokenBoxes);
$logger->info(
    sprintf(
        '%d potentially broken submission boxes were found - %d automatically repaired, %d were not.',
        count($affectedBoxes),
        count($fixedBoxes),
        count($stillBrokenBoxes)
    )
);
if (count($fixedBoxes)) {
    $logger->info('The following boxes have been automatically repaired - they will need to be re-marked:');
    foreach ($fixedBoxes as $boxId) {
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

// Alert about still broken boxes too
if (count($stillBrokenBoxes)) {
    $logger->warning('The following boxes are still broken - they will need to be re-saved and re-marked:');
    foreach ($stillBrokenBoxes as $boxId) {
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
        $logger->warning(
            sprintf(
                '#%d (%s), in folder #%d (%s)',
                $box->getId(),
                $box->getName(),
                $folder->getId(),
                $folderName
            )
        );
        $logger->warning(
            sprintf(
                '(Direct link: http://%s.%s/submission/createSubmission/modify/%d)',
                Config::get('site_hostname'),
                Config::get('site_domain'),
                $box->getId()
            )
        );
    }

    // Still broken boxes are an error state
    exit(1);
}
exit(0);
