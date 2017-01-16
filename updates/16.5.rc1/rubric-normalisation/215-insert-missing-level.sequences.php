<?php
/**
 * In the previous script, may have inserted levels with sequence set to -1, meaning 'Unknown'.
 *
 * The level sequence increases LTR, but the descriptors may increase LTR or RTL.
 *
 * This script will consider the former case $inverse = FALSE, and the latter as $inverse = TRUE
 *
 * This script populates that field by examining surrounding data. In order of preference:
 * 1. Adjacent Levels on the same box
 * 2. Adjacent Boxes in same class
 * 3. Boxes in this school
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

$kernel = new Schoolbox\Application\Daemon\Kernel('Update missing Rubric Level Sequences', [
    'verbose' => [ false, false, false ],
    'errors'  => E_ALL,
]);

$kernel->run(function($di, $loop, LoggerInterface $log, $opts) {

    $db = Database::getInstance();

    /**
     * Determine whether a set of points indicates positive or negative gradient using linear regression
     *
     * @param array $points Maps x(level.sequence) > y(average(level.descriptor.values))
     * @return float
     */
    $slopeFunction = function(array $points){
        $xAvg = array_sum(array_keys($points)) / count($points);
        $yAvg = array_sum($points) / count($points);

        $numerator = $denominator = 0;
        foreach($points as $x => $y){
            $numerator += ($x - $xAvg) * ($y - $yAvg);
            $denominator += pow($x - $xAvg, 2);
        }
        return $numerator / $denominator;
    };


    /**
     * Gets an gradient from a database result set
     *
     * @param array[] $assocRows
     * @var array $assocRows[]
     * @var int $assocRows[]['sequence']
     * @var float $assocRows[]['value']
     * @return float[]
     */
    $getGradientFromResults = function(array $assocRows) use ($slopeFunction){
        $points = [];
        foreach($assocRows as $row){
            $points[$row['sequence']] = $row['value'];
        }
        return $slopeFunction($points);
    };


    // Get Boxes that contain 1 or more levels with unknown sequences and are not deleted.
    // Box must have 2 or more levels.
    $selectBoxes = $db->prepare("
        SELECT sb.id, sb.category
        FROM submission_box sb JOIN rubric_level rl ON rl.box_id = sb.id
        WHERE sb.id IN (SELECT DISTINCT box_id FROM rubric_level WHERE sequence = -1 ORDER BY box_id)
        AND sb.deleted IS NOT TRUE
        GROUP BY sb.id
        HAVING count(sb.id) > 1
    ");

    // Get all levels for specified box, along with average value
    $selectAllBoxLevels = $db->prepare("
        SELECT rl.id, rl.name, rl.sequence, AVG(rd.value) 'value'
        FROM rubric_level rl
        LEFT JOIN rubric_descriptor rd ON rd.level_id = rl.id
        WHERE rl.box_id = :box_id
        AND rd.value IS NOT NULL
        GROUP BY rl.id
        ORDER BY value ASC
    ");



    // level.sequence vs avg(level.descriptors.value) for a box
    $selectBoxLevels = $db->prepare("
        SELECT rl.sequence, AVG(rd.value) 'value'
        FROM rubric_level rl
        JOIN rubric_descriptor rd ON rd.level_id = rl.id 
        WHERE box_id = :box_id
        AND rl.sequence != -1
        AND rd.value IS NOT NULL
        AND rd.valid IS NOT FALSE
        GROUP BY rl.sequence
        ORDER BY rl.sequence ASC
    ");

    // level.sequence vs avg(level.descriptors.value) for a folder (ie class)
    $selectClassLevels = $db->prepare("
        SELECT rl.sequence, AVG(rd.value) 'value'
        FROM submission_box sb
        JOIN rubric_level rl ON rl.box_id = sb.id
        JOIN rubric_descriptor rd ON rd.level_id = rl.id
        WHERE sb.category = :folder_id
        AND rl.sequence != -1
        AND rd.value IS NOT NULL
        AND rd.valid IS NOT FALSE
        GROUP BY rl.sequence
        ORDER BY rl.sequence ASC
    ");

    // level.sequence vs avg(level.descriptors.value) for the whole school
    $selectSchoolLevels = $db->prepare("
        SELECT rl.sequence, AVG(rd.value) 'value'
        FROM rubric_level rl
        JOIN rubric_descriptor rd ON rd.level_id = rl.id
        WHERE rl.sequence != -1
        AND rd.value IS NOT NULL
        AND rd.valid IS NOT FALSE
        GROUP BY rl.sequence
        ORDER BY rl.sequence ASC
    ");


    /* Cache the results for the school and classes so we don't need to re-execute.
     * Because we may get a NULL answer for this query, need to store as a flag so we don't re-run.
     */
    $schoolQueryExecuted = false;
    $schoolGradient = null;

    /**
     * A value of int => NULL means we have executed the query for some folderId, but were unable to get a result.
     *
     * @var float[] Indexed by folder.id
     */
    $folderGradients = [];


    $updateLevelQuery = $db->prepare("
        UPDATE rubric_level
        SET sequence = :sequence
        WHERE id = :level_id;
    ");

    // Statistics for Boxes
    $successBoxCount = 0;
    $warningBoxCount = 0;
    $failureBoxCount = 0;

    // Statistics for Levels
    $successLevelCount = 0;
    $warningLevelCount = 0;
    $failureLevelCount = 0;

    $selectBoxes->execute();
    $log->info(sprintf('%d Submission Boxes found containing 1 or more levels with unknown sequence', $selectBoxes->rowCount()));


    // For each box with 1 or more levels that need their sequences determined
    foreach($selectBoxes->fetchAll(PDO::FETCH_ASSOC) as $boxRow)
    {
        $boxId     = intval($boxRow['id']);
        $folderId  = intval($boxRow['category']);

        // Determine gradient for this box, defaulting to box, then class average, then school average
        $gradient  = null;

        // Does this box have other levels that we can inspect ?
        $log->debug(sprintf('Retrieving known-sequenced levels for box (id: "%d") ... ', $boxId));
        $selectBoxLevels->execute(['box_id' => $boxId]);


        // Did we find adjacent levels on this box ?
        if($selectBoxLevels->rowCount() > 1){
            // Use this box's other levels
            $gradient = $getGradientFromResults($selectBoxLevels->fetchAll(PDO::FETCH_ASSOC));
            $log->info(sprintf('Box (id: "%d") has 2+ other levels with known sequences, gradient: "%.3f"', $boxId, $gradient));
        } else {
            // No adjacent Levels on box. Fall back to class
            $log->debug(sprintf('Box (id: "%d") has 1- other levels with known sequences, falling back to class average ...', $boxId));

            // Have we tried to get the gradient for this class yet?
            if(!array_key_exists($folderId, $folderGradients)){
                // No, try to load folder
                $log->debug(sprintf('Retrieving known-sequenced levels for class folder (id: "%d") ... ', $folderId));
                $selectClassLevels->execute(['folder_id' => $folderId]);

                // Did we find levels for this class ?
                if($selectClassLevels->rowCount() > 1){
                    $folderGradients[$folderId] = $getGradientFromResults($selectClassLevels->fetchAll(PDO::FETCH_ASSOC));
                    $log->info(sprintf('Folder (id: "%d") gradient calculated as: "%.3f" ', $folderId, $folderGradients[$folderId]));
                } else {
                    $folderGradients[$folderId] = null;
                    $log->warning(sprintf('Folder (id: "%d") gradient unable to be determined', $folderId));
                }
            }

            // Does class have a average gradient ?
            if(!is_null($folderGradients[$folderId])){
                // Yes, use this folder's average gradient.
                $gradient = $folderGradients[$folderId];
                $log->info(sprintf('Using folder (id: "%d") gradient calculated as: "%.3f" ', $folderId, $folderGradients[$folderId]));
            } else {
                // No, fall back to school average
                $log->warning(sprintf('No gradient available for class folder (id: "%d"), falling back to school average', $folderId));

                // Have we tried to get School average yet?
                if(!$schoolQueryExecuted) {
                    $log->debug(sprintf('Retrieving known-sequenced levels for whole school. Be patient ... :) '));
                    $selectSchoolLevels->execute();
                    $schoolQueryExecuted = true;

                    // Were we unable to get a gradient for the school?
                    if($selectSchoolLevels->rowCount() > 1) {
                        $schoolGradient = $getGradientFromResults($selectSchoolLevels->fetchAll(PDO::FETCH_ASSOC));
                        $log->info(sprintf('School gradient calculated as: "%.3f" ', $schoolGradient));
                    } else {
                        $log->warning(sprintf('School gradient unable to be determined'));
                    }
                }

                // Do we have a school average to fall back to?
                if(!is_null($schoolGradient)) {
                    $gradient = $schoolGradient;
                    $log->info(sprintf('Using school gradient calculated as: "%.3f" ', $schoolGradient));
                } else {
                    // Log error and continue to next box
                    $log->error(sprintf('No school gradient to fall back to, skipping box...'));
                    continue;
                }
            }
        }

        // Unlikely, but theoretically possible, consider [2,3,2] which has m == 0
        if($gradient == 0){
            // Gradient cannot be 0,
            $log->error(sprintf('Gradient cannot be 0, skipping box...'));
            continue;
        }

        // Get all levels for box
        $selectAllBoxLevels->execute(['box_id' => $boxId]);
        $countLevels = $selectAllBoxLevels->rowCount();

        // If positive relationship, re-sequence from 1 to N,
        // Else if negative, re-sequence from N down to 1.
        if($gradient > 0){
            $initial   = 1;
            $increment = 1;
        } else {
            $initial   = $countLevels;
            $increment = -1;
        }
        $newSequence = $initial;

        $isBoxFailed = $isBoxWarned = false;

        while($row = $selectAllBoxLevels->fetch(PDO::FETCH_ASSOC)){
            $levelId      = intval($row['id']);
            $levelName    = $row['name'];
            $oldSequence  = intval($row['sequence']);
            $avgDescValue = floatval($row['value']);

            try{
                $updateLevelQuery->execute([
                    'sequence' => $newSequence,
                    'level_id' => $levelId,
                ]);
                $log->info(sprintf('Level updated. Id: "%d", Avg. Desc. Val: "%.3f", Old Sequence: "%d", New Sequence: "%d", Name: "%s"',  $levelId, $avgDescValue, $oldSequence, $newSequence, $levelName));
                $successLevelCount++;
            } catch (PDOException $e) {
                $log->error(sprintf('Unable to update level. Id: "%d", Avg. Desc. Val: "%.3f", Old Sequence: "%d", New Sequence: "%d", Name: "%s"',  $levelId, $avgDescValue, $oldSequence, $newSequence, $levelName), [$e]);
                $log->warning($e->getMessage());
                $failureLevelCount++;
                $isBoxFailed = true;
            }
            $newSequence += $increment;
        }

        // How did we go overall for this box?
        if($isBoxFailed){
            $failureBoxCount++;
        } elseif($isBoxWarned){
            $warningBoxCount++;
        } else{
            $successBoxCount++;
        }
    }

    // Log RubricLevel results at appropriate level
    $levelMessage = sprintf('%d rubric_levels updated (%d warnings), %d failures', $successLevelCount, $warningLevelCount, $failureLevelCount);
    $levelLevel = $failureLevelCount ? Logger::ERROR : ($warningLevelCount ? Logger::WARNING : Logger::INFO);
    $log->log($levelLevel, $levelMessage);


    // Log Box results at appropriate level
    $boxMessage = sprintf('%d boxes updated (%d warnings), %d failures', $successBoxCount, $warningBoxCount, $failureBoxCount);
    $boxLevel = $failureBoxCount ? Logger::ERROR : ($warningBoxCount ? Logger::WARNING : Logger::INFO);
    $log->log($boxLevel, $boxMessage);


    exit($failureLevelCount > 0 ? 1 : 0);
});
