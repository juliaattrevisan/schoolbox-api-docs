<?php
/**
 * @author    Natalie Bakhtadze <natalie.bakhtadze@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Alaress\Database\Connector\IConnector;
use Aura\Di;
use Psr\Log\LoggerInterface as Logger;
use React\EventLoop\LoopInterface;
use Alaress_Date_Format;

require_once __DIR__ . '/../../../conf/core/require.php';

spl_autoload_call('\\Schoolbox\\Application\\Daemon');
$kernel = new Schoolbox\Application\Daemon\Kernel(
    'Link Rubric Marks to Responses',
    [
        'verbose' => [false, false, false],
        'errors'  => E_ALL,
    ]
);

$kernel->run(/**
 * @param Di\Container  $di
 * @param LoopInterface $loop
 * @param Logger        $log
 *
 * @throws Di\Exception\ServiceNotFound
 */
    function (Di\Container $di, LoopInterface $loop, Logger $log) {

    /** @var IConnector $db */
    $db = $di->get('databaseInternal');

    // Get all participants with marks

    $participants = $db->executeQuery(
        '
            SELECT rm.student_id, rl.box_id
            FROM rubric_mark rm
            JOIN rubric_descriptor rd ON rd.id = rm.descriptor_id
            JOIN rubric_level rl ON rl.id = rd.level_id
            GROUP BY rm.student_id, rl.box_id;
            '
    )->fetchAll();

    // Dealing with published only for now
    // Loop participants and find published descriptor sets
    foreach ($participants as  $i => $participant) {
        $rubricMarks = [];
        $feedbacks = [];


        // All rubric marks, newest to oldest
        $rubricMarks = $db->executeQuery(
            '
            SELECT COUNT(rm.id), rm.student_id, rl.box_id, rm.created_at, rm.published_at
            FROM rubric_mark rm
            JOIN rubric_descriptor rd ON rd.id = rm.descriptor_id
            JOIN rubric_level rl ON rl.id = rd.level_id
            JOIN rubric_criterion rc ON rc.id = rd.criterion_id
            WHERE rm.student_id = :student_id
            AND rm.published_at IS NOT NULL
            AND rl.box_id = :box_id
            GROUP BY rm.published_at
            ORDER BY rm.published_at DESC;
            ',
            [
                'student_id'  => $participant['student_id'],
                'box_id' => $participant['box_id']
            ]
        )->fetchAll();


        // All possible feedbacks, deleted or not, oldest to newest
        $feedbacks = $db->executeQuery(
            '
            SELECT sr.id, sr.created_at, sr.updated_at, sr.deleted_at AS "DELETED", sr.published_at
            FROM submission_return sr
            JOIN submission_box sb ON sb.id = sr.box_id
            WHERE sr.owner = :student_id AND sr.box_id = :box_id AND sr.published_at IS NOT NULL
            ORDER BY sr.published_at ASC;
            ;
            ',
            [
                'student_id'  => $participant['student_id'],
                'box_id' => $participant['box_id']
            ]
        )->fetchAll();

        // One to one, published
        if (count($rubricMarks) == 1 && count($feedbacks) == 1) {

            linkReturnToMark($db, $log, $feedbacks[0], $rubricMarks[0]);

        }

        // One to multi, published
        elseif (count($rubricMarks) == 1 && count($feedbacks) > 1) {

            // try matching by date



//            $rubricMarkPublishedDate = new Alaress_Date_Format($rubricMarks[0]['published_at'], Alaress_Date_Format::DATABASE_FORMAT);
//
//            $latest = true;
//
            $propagate = false;

            foreach ($feedbacks as $feedback) {

                if (($feedback['published_at'] == $rubricMarks[0]['published_date']) && !$propagate) {
                    linkReturnToMark($db, $log, $feedback, $rubricMarks[0]);
                    $propagate = true;
                }
                elseif ($propagate) {
                    propagateRubricMarkForward($db, $log, $rubricMarks[0], $feedback);
                }

                else {
                    // do nothing;
                }




//
//                $feedbackPublishedDate = new Alaress_Date_Format($feedback['published_at'], Alaress_Date_Format::DATABASE_FORMAT);
//
//
//                if ($feedbackPublishedDate->compare($rubricMarkPublishedDate) >= 0) {
//
//                    if ($latest) {
//                        linkReturnToMark($db, $log, $feedback, $rubricMarks[0]);
//                        $latest = false;
//                        continue;
//                    }
//
//                    var_dump($rubricMarks);
//                    var_dump($feedbacks);
//
//                    propagateRubricMarkForward($db, $log, $rubricMarks[0], $feedback);
//
//                } else {
//                    continue;
//                }



//            $feedbacksMatched = array_values(array_filter($feedbacks, function($row) use ($rubricMarkPublished) { return $row['published_at'] == $published; }));
//
//            if (count($feedbacksMatched) == 1) {
//                linkReturnToMark($db, $log, $feedbacksMatched[0], $rubricMarks[0]);
//            }
//
//            // More than one feedback have matched for any reason? Try to link to any non-deleted
//            elseif (count($feedbacksMatched) > 1) {
//                $linked = false;
//                foreach ($feedbacksMatched as $feedback) {
//                    if ($feedback['deleted_at'] == NULL) {
//                        linkReturnToMark($db, $log, $feedback, $rubricMarks[0]);
//                        $linked = true;
//                        continue;
//                    }
//                }
//                // No luck? Link to any deleted and forget about it.
//                if (!$linked) {
//                    linkReturnToMark($db, $log, $feedbacksMatched[0], $rubricMarks[0]);
//                }
//            }
//
//            // No feedback matched by publish date? Link to latest non-deleted.
//            // TODO: Should I bother finding the closest by the publish date?
//            else {
//                $linked = false;
//                foreach ($feedbacks as $feedback) {
//                    if (!$feedback['deleted_at']) {
//                        linkReturnToMark($db, $log, $feedback, $rubricMarks[0]);
//                        $linked = true;
//                        continue;
//                    }
//                }
//                // No luck? Link to latest deleted and forget about it.
//                if (!$linked) {
//                    linkReturnToMark($db, $log, $feedbacksMatched[0], $rubricMarks[0]);
//                }
//            }

            }
        }

        // Multi to one
        // Link latest rubric mark, forget about the rest.
        elseif (count($rubricMarks) > 1 && count($feedbacks) == 1) {
continue;
            linkReturnToMark($db, $log, $feedbacks[0], $rubricMarks[0]);

        }

        // Multi to multi
        elseif (count($rubricMarks) > 1 && count($feedbacks) > 1) {
continue;
            $linkedDates = [];
            // Try finding match by publish date
            foreach ($rubricMarks as $r => $rubricMark) {
                foreach ($feedbacks as $f => $feedback) {
                    if ($rubricMark['published_at'] == $feedback['published_at']) {
                        linkReturnToMark($db, $log, $feedback, $rubricMark);
                        unset($rubricMarks[$r]);
                        unset($feedbacks[$f]);
                        $linkedDates[] = $feedback['published_at'];

                    }
                }
            }

            if (empty($rubricMarks)) {
                continue;
            }
            elseif (count($rubricMarks) == 1 || count($feedbacks) == 1) {
//                var_dump($linkedDates);
//
//                var_dump($rubricMarks);
//                var_dump($feedbacks);


            }
//            var_dump($rubricMarks);
//            var_dump($feedbacks);
        }

        // Only drafts are left there to deal with
        else {

        }

    }

});


// Id setter
// TODO: should I update publish times to clean the data?
function linkReturnToMark ($db, $log, $feedback, $descriptorsSet) {
    $db->executeQuery(
        '
                UPDATE rubric_mark rm
                JOIN rubric_descriptor rd ON rd.id = rm.descriptor_id
                JOIN rubric_level rl ON rl.id = rd.level_id
                SET return_id = :feedback_id
                WHERE rm.student_id = :student_id AND rm.published_at = :published_at AND rl.box_id = :box_id;
                ',
        [
            'feedback_id' => $feedback['id'],
            'student_id' => $descriptorsSet['student_id'],
            'published_at' => $descriptorsSet['published_at'],
            'box_id' => $descriptorsSet['box_id']
        ]
    );
}

function propagateRubricMarkForward ($db, $log, $rubricMark, $feedback) {

    $descriptors = $db->executeQuery(
        '
        SELECT descriptor_id FROM rubric_mark rm
        JOIN rubric_descriptor rd ON rd.id = rm.descriptor_id
        JOIN rubric_level rl ON rl.id = rd.level_id
        WHERE rm.student_id = :student_id AND rm.published_at = :published_at AND rl.box_id = :box_id;
        ',
        [
            'student_id' => $rubricMark['student_id'],
            'published_at' => $rubricMark['published_at'],
            'box_id' => $feedback['box_id']
        ]
    )->fetchAll();
var_dump($descriptors);
    foreach ($descriptors as $descriptor) {
        $db->executeQuery(
          '
          INSERT INTO rubric_mark (return_id, student_id, descriptor_id, created_at, published_at, valid)
          VALUES 
          (:return_id, :student_id, :descriptor_id, :created_at, :published_at, 1)
          ',
          [
              'return_id' => $feedback['id'],
              'student_id' => $rubricMark['student_id'],
              'descriptor_id' => $descriptor['descriptor_id'],
              'created_at' => $feedback['created_at'],
              'published_at' => $feedback['published_at']
          ]
        );
    }

}

function checkIfDeletedIsValid ($db, $log) {
    $db->executeQuery(
        '
            SELECT * FROM rubric_mark rm
            JOIN submission_return sr ON rm.return_id = sr.id
            JOIN submission_box sb ON sb.id = sr.box_id
            WHERE rm.return_id IS NOT NULL 
            AND sr.deleted_at IS NOT NULL
            AND sb.deleted IS NULL;
        '
    );
}