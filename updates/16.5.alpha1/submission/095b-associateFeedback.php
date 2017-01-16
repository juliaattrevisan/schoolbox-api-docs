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
    $boxesQuery = $database->prepare("
        SELECT DISTINCT box_id, marker, owner
        FROM submission_return
        WHERE student_submission_id IS NULL
        AND uuid IS NULL
        ORDER BY box_id
    ");

    $boxesQuery->execute();

    $boxes = $boxesQuery->fetchAll(\PDO::FETCH_ASSOC);

    $allBoxIds = [];

    foreach ($boxes as $currentBox) {
        $allBoxIds[] = $currentBox['box_id'];
    }

    $log->debug(sprintf('%d submission_box records exist which contain submission_returns missing uuid values', $boxesQuery->rowCount()));
    $log->debug(sprintf('(%s)', implode(', ', array_unique($allBoxIds))));

    /* Find all the student_submissions and submission_return records
     * that require deeper inspection
     */
    foreach ($boxes as $currentBox) {

        /* Only obtain submissions for the current (box_id, owner) tuple
         *
         * We only care if:
         * + it has a crocodoc uuid
         * + it is not a draft,
         * + it has a submitted_at date,
         * + it isn't soft-deleted
         */
        $submissionsQuery = $database->prepare("
            SELECT 'submission' AS type, id, owner, uuid, submitted_at AS timestamp
            FROM student_submission
            WHERE box_id = :boxId
            AND owner = :owner
            AND uuid IS NOT NULL
            AND draft = 0
            AND submitted_at IS NOT NULL
            AND deleted_at IS NULL
        ");

        $submissionsQuery->execute([
            'boxId' => $currentBox['box_id'],
            'owner' => $currentBox['owner'],
        ]);

        $submissions = $submissionsQuery->fetchAll(\PDO::FETCH_ASSOC);

        /* Only obtain returns for the current (box_id, owner) tuple
         *
         * We only care if:
         * + it's missing a crocodoc uuid
         * + it is not a confirmation of submission,
         * + it has a published_at date,
         * + it isn't soft-deleted
         */
        $returnsQuery = $database->prepare("
            SELECT 'return' AS type, id, owner, published_at AS timestamp
            FROM submission_return
            WHERE box_id = :boxId
            AND owner = :owner
            AND uuid IS NULL
            AND is_confirmation_of_submission = 0
            AND deleted_at IS NULL
        ");

        $returnsQuery->execute([
            'boxId' => $currentBox['box_id'],
            'owner' => $currentBox['owner'],
        ]);

        $returns = $returnsQuery->fetchAll(\PDO::FETCH_ASSOC);

        $log->info(sprintf('Box %d, owner %d: chronologically resequencing %d submissions, %d returns ', $currentBox['box_id'], $currentBox['owner'], $submissionsQuery->rowCount(), $returnsQuery->rowCount()));

        /* Sequence everything based on time so that we can infer uuid assignations
         */
        $timeline = new \Schoolbox\Collection(array_merge($submissions, $returns));
        $timeline->sortByCallback(function($a, $b) {
            $dateA = new \Alaress_Date_Format($a['timestamp'], \Alaress_Date_Format::DATABASE_FORMAT);
            $dateB = new \Alaress_Date_Format($b['timestamp'], \Alaress_Date_Format::DATABASE_FORMAT);

            return $dateA->gt($dateB);
        });

        /* Infer and assign a uuid to a return
         */
        $uuid = false;

        foreach ($timeline as $event) {

            /* Remember the most recently encountered uuid and assign it to all returns that follow,
             * until another submission is encountered
             */
            if ($event['type'] == 'submission') {
                $uuid = $event['uuid'];
                continue;
            }

            /* Apply uuid to returns, inferring that they are associated
             */
            if (($event['type'] == 'return') && $uuid) {
                $log->debug(sprintf('Box %d, owner %d: assigning %s to return %d ', $currentBox['box_id'], $currentBox['owner'], $uuid, $event['id']));
            }
        }
    }

    exit(1);
});
