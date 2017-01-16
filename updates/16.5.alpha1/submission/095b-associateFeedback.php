<?php
/**
 * Generate a timeline and infer which submission_return records belong
 * to student_submission records based on time
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use Psr\Log\LoggerInterface;

require_once __DIR__ . '/../../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Associate Feedback via Timeline', [
    'verbose' => [ false, false, false ],
    'errors'  => E_ALL,
]);

$kernel->run(function($di, $loop, LoggerInterface $log, $opts) {
    $database = Database::getInstance();

    /* Find all the boxes that contain returns that don't have student_submission_id values and
     * no uuid values - need to determine whether they should, depending on a timeline of events
     */
    $query = "
        SELECT box_id, marker, owner
        FROM submission_return
        WHERE student_submission_id IS NULL
        AND uuid IS NULL
        ORDER BY box_id
    ";

    $boxes = $database->prepare($query);
    $boxes->execute();

    $allBoxIds = [];

    foreach ($boxes as $currentBox) {
        $allBoxIds[] = $currentBox['box_id'];
    }

    $log->debug(sprintf('%d submission_box records exist which contain submission_returns missing uuid values', $boxes->rowCount()));
    $log->debug(sprintf('(%s)', implode(', ', array_unique($allBoxIds))));

    /* Find all the student_submissions and submission_return records
     * that require deeper inspection
     */
    foreach ($allBoxIds as $currentBoxId) {
        $submissionsQuery = "
            SELECT id, owner, uuid, submitted_at, created_at, updated_at, deleted_at
            FROM student_submission
            WHERE box_id = :boxId
        ";
    }

    exit(1);
});
