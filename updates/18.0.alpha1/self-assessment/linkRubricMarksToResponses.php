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
        // TODO decide if I should break the logic of the next step
        if (count($rubricMarks) == 1 && count($feedbacks) == 1) {

            linkReturnToMark($db, $log, $feedbacks[0], $rubricMarks[0]);

        }

        // One to multi or multi to multi, published
        elseif (count($rubricMarks) >= 1 && count($feedbacks) > 1) {
//            continue;
//
//            var_dump($rubricMarks);
//            var_dump($feedbacks);

            // try matching by date

            $propagate = false;
            $feedbackId = null;

#       RubricMarks     [1]   [0]
#       ------------------------------>
#       Feedbacks    (0)    (1)   (2)

            foreach ($rubricMarks as $rubricMark) {

                $rubricMarkPublishedDate = new Alaress_Date_Format($rubricMark['published_at'], Alaress_Date_Format::DATABASE_FORMAT);


                foreach ($feedbacks as $feedback) {

                    $feedbackPublishedDate = new Alaress_Date_Format($feedback['published_at'], Alaress_Date_Format::DATABASE_FORMAT);

                    if (($rubricMarkPublishedDate->compare($feedbackPublishedDate) <= 0) && !$propagate) {
                        linkReturnToMark($db, $log, $feedback, $rubricMark);
                        $propagate = true;
                        $feedbackId = $feedback['id'];
                    } elseif ($propagate && $feedbackId != $feedback['id']) {
                        propagateRubricMarkForward($db, $log, $rubricMark, $feedback);
                    } else {
                        // do nothing;
                    }

                }

            }

        }

        // Multi to one
        // Link latest rubric mark, forget about the rest.
        elseif (count($rubricMarks) > 1 && count($feedbacks) == 1) {
            continue;
            linkReturnToMark($db, $log, $feedbacks[0], $rubricMarks[0]);

        }


        // Only drafts are left there to deal with
        else {

        }

    }

});


// Id setter
// TODO: should I update publish times to clean the data?
function linkReturnToMark ($db, $log, $feedback, $rubricMark) {
    $db->executeQuery(
        '
                UPDATE rubric_mark rm
                JOIN rubric_descriptor rd ON rd.id = rm.descriptor_id
                JOIN rubric_level rl ON rl.id = rd.level_id
                SET return_id = :feedback_id
                WHERE rm.student_id = :student_id 
                AND rm.published_at = :published_at 
                AND rl.box_id = :box_id 
                AND rm.return_id IS NULL;
                ',
        [
            'feedback_id' => $feedback['id'],
            'student_id' => $rubricMark['student_id'],
            'published_at' => $rubricMark['published_at'],
            'box_id' => $rubricMark['box_id']
        ]
    );

}

function propagateRubricMarkForward ($db, $log, $rubricMark, $feedback) {

    $feedbackAlreadyLinked = $db->executeQuery(
        '
        SELECT id FROM rubric_mark
        WHERE return_id = :feedback_id
        ',
        [
            'feedback_id' => $feedback['id']
        ]
    )->fetchAll();

    if (count($feedbackAlreadyLinked) > 0) {
        return;
    }
    $descriptors = $db->executeQuery(
        '
        SELECT descriptor_id FROM rubric_mark rm
        JOIN rubric_descriptor rd ON rd.id = rm.descriptor_id
        JOIN rubric_level rl ON rl.id = rd.level_id
        WHERE rm.student_id = :student_id 
        AND rm.published_at = :published_at 
        AND rl.box_id = :box_id
        AND rm.return_id IS NULL;
        ',
        [
            'student_id' => $rubricMark['student_id'],
            'published_at' => $rubricMark['published_at'],
            'box_id' => $rubricMark['box_id']
        ]

    )->fetchAll();

//    var_dump($rubricMark);
//    var_dump($feedback);

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