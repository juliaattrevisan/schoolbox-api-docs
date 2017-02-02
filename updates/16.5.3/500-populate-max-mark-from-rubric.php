<?php
/**
 * For whatever reason, we have schools with DueWork with max_mark = 0, this is not a valid state and is blowing up
 * the mark scheme factory.
 *
 * It appears to exist for DueWork that is using a rubric. Presumably at some point this value was not being copied
 * to the dueWork. Solution is to do so now.
 *
 * If box is not using a rubric, or no rubric is found, or the rubric has no descriptors with numeric values
 * (ie they only have text descriptions), then change the markType to 'None'.
 *
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Psr\Log\LoggerInterface;
use Schoolbox\Mapper\Exception as MapperException;
use Monolog\Logger;

require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Insert Missing SubmissionBox max_mark', [
    'verbose' => [ false, false, false ],
    'errors'  => E_ALL,
]);

$kernel->run(function($di, $loop, LoggerInterface $log, $opts) {

    $db = Database::getInstance();

    // Note the COALESCE - we treat no value as 0 for the purpose of this script.
    // Note we need to include 'sb.id' in the group by cater for when the LEFT JOIN to rubric_level matches nothing
    $selectBoxesWithZeroMaxMark = $db->prepare("
        SELECT 
            `sb.id`,
            `sb.name`,
            `sb.use_rubric`,
            sum(`rd.max_value`) AS 'rd.sum_value'
        FROM (
            SELECT
                sb.id AS 'sb.id',
                sb.name as 'sb.name',
                sb.use_rubric AS 'sb.use_rubric',
                max(COALESCE(rd.value, 0)) AS 'rd.max_value'
            FROM submission_box sb
            JOIN submission_mark_type mt ON mt.mark_type_id = sb.mark_type
            LEFT JOIN rubric_level rl ON rl.box_id = sb.id
            LEFT JOIN rubric_descriptor rd ON rd.level_id = rl.id
            WHERE sb.max_mark = 0
            AND mt.slug = 'raw'
            AND rl.deleted_at IS NULL
            AND rd.deleted_at IS NULL
            GROUP BY sb.id, rd.criterion_id
        ) t 
        GROUP BY `sb.id`
    ");

    $updateMaxMark = $db->prepare("
        UPDATE submission_box
        SET max_mark = :max_mark
        WHERE id = :id
    ");

    $updateMarkType = $db->prepare("
        UPDATE submission_box
        SET mark_type = (SELECT mark_type_id FROM submission_mark_type WHERE slug = 'none')
        WHERE id = :id
    ");

    $successMaxMarkCount = 0;
    $warningMaxMarkCount = 0;
    $failureMaxMarkCount = 0;

    $successMarkTypeCount = 0;
    $warningMarkTypeCount = 0;
    $failureMarkTypeCount = 0;

    $selectBoxesWithZeroMaxMark->execute();
    $log->info(sprintf('%d Submission Boxes found with `max_mark` = "0" and `mark_type` = "max_mark" ', $selectBoxesWithZeroMaxMark->rowCount()));

    while ($row = $selectBoxesWithZeroMaxMark->fetch(PDO::FETCH_ASSOC) ){
        $boxId      = intval($row['sb.id']);
        $boxName    = $row['sb.name'];
        $useRubric  = boolval($row['sb.use_rubric']);
        $sumDescVal = intval($row['rd.sum_value']);

        // Note that we cast the sum to an int, even though it may have a fractional component.
        // This matches the logic currently implemented in the UI when copying a rubric's total mark
        // to the box.max_mark input field.

        $log->debug(sprintf('Box (id: "%d") "%s" is%s using a rubric and has rubric total of "%d"', $boxId, $boxName, $useRubric ? '' : ' NOT', $sumDescVal));

        // Is this box using a rubric and the rubric exists and has a valid mark?
        if($useRubric && $sumDescVal >= 1){
            // SET `max_mark` to valid value
            try{
                $updateMaxMark->execute([
                    'max_mark' => $sumDescVal,
                    'id'       => $boxId
                ]);
                $successMaxMarkCount++;
                $log->info(sprintf('Set max_mark to "%d" for "%s" (id: "%d")', $sumDescVal, $boxName, $boxId));
            } catch (PDOException $e) {
                $failureMaxMarkCount++;
                $log->error(sprintf('Failed to set max_mark to "%d" for "%s" (id: "%d")', $sumDescVal, $boxName, $boxId), [$e]);
                $log->warning($e->getMessage());
            }
        } else {
            // SET `mark_type` to  'none'
            try{
                $updateMarkType->execute([
                    'id'       => $boxId
                ]);
                $successMarkTypeCount++;
                $log->info(sprintf('Set `max_mark` to "None" for "%s" (id: "%d")', $boxName, $boxId));
            } catch (PDOException $e) {
                $log->error(sprintf('Failed to set `max_mark` to "None" for "%s" (id: "%d")', $boxName, $boxId), [$e]);
                $log->warning($e->getMessage());
                $failureMarkTypeCount++;
            }
        }
    }

    // Log 'MaxMark' results at appropriate level
    $logMessage = sprintf('%d `max_mark` values updated. (%d warnings), %d failures', $successMaxMarkCount, $warningMaxMarkCount, $failureMaxMarkCount);
    $logLevel = $failureMaxMarkCount ? Logger::ERROR : ($warningMaxMarkCount ? Logger::WARNING : Logger::INFO);
    $log->log($logLevel, $logMessage);

    // Log 'MarkType' results at appropriate level
    $logMessage = sprintf('%d rubric_descriptors updated (%d warnings), %d failures', $successMarkTypeCount, $warningMarkTypeCount, $failureMarkTypeCount);
    $logLevel = $failureMarkTypeCount ? Logger::ERROR : ($warningMarkTypeCount ? Logger::WARNING : Logger::INFO);
    $log->log($logLevel, $logMessage);

    // Any SQL failures ?
    $failuresDetected = ($failureMaxMarkCount + $failureMarkTypeCount) > 0;

    exit($failuresDetected ? 1 : 0);
});
