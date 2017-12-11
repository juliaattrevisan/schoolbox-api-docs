<?php
/**
 * @author    Natalie Bakhtadze <natalie.bakhtadze@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

/**
 *
 * - Linking rubric marks
 * There is one simple rule: link a rubric mark to a feedback with the same or later published date.
 * Don't link rubric marks back, as we don't know if a feedback in the past
 * will make sense in the context of the linked rubric mark.
 *
 *
 * - Propagating rubric marks
 * Once participation gets a rubric mark, it becomes a no-return point and every following feedback
 * will need to have a rubric mark associated with it. If there is no rubric mark available to be linked
 * to such feedback in the historical data, the immediate previous rubric mark gets propagated forward
 * and gets linked to it.
 *
 *
 * - Orphan rubric marks
 * Orphan rubric marks will be created in cases like these:
 *
 * R:    [1]  [2]
 * --------------------> t
 * F:       (1)
 *
 * [1] gets linked to (1), leaving [2] having NULL as return_id
 *
 *
 * R: [1]  [2]    [3]
 * ---------------------> t
 * F:         (1)    (2)
 *
 * [2] gets linked to (1), [3] to (2), leaving [1] orphan.
 *
 *
 * - Deleted feedbacks
 * Currently there is no way to delete a rubric mark from SB.
 * There are cases where these rubric marks get linked to deleted feedbacks.
 * In the case below, users currently see the rubric mark and some other feedback from the past,
 * not associated with that mark. When we will eventually start displaying marks coupled their feedbacks,
 * those marks will disappear from the users views, since they are linked to deleted feedbacks.
 *
 * R:         [P]
 * ------------|------> t
 * F:    (P)  (D)
 *
 *
 * - Published dates
 * On linking rubric mark to feedback, published date is not updated for neither of them.
 * If a rubric mark gets propagated forward, it's published date is set to the linked feedback's published date.
 *
 *
 * - Drafts and published
 * Don't mix one with another, link published with published, drafts with drafts
 *
 *
 * - Combining descriptor records for drafts
 * We can't trust creation date 100%.
 * There are cases where records in return_mark table have different created_at date, but they still belong to the same feedback.
 * If those descriptors are close enough by creation date and don't share the same criterion,
 * it is safe to link them into the same feedback.
 *
 *
 * - Linking drafts
 * There should be only one draft feedback per participation at any given time.
 * If we have more than one draft rubric mark though, that will either mean
 * that record creation time differs for descriptor records of the same feedback, or that our data is broken.
 * We will try to check the first scenario and combine those records into one feedback.
 * If that fails, only the latest rubric mark draft will get linked to a latest draft feedback
 * if it was created at the same time or after rubric mark creation. If not, this rubric mark will become an orphan.
 *
 *
 */

use Alaress\Database\Connector\IConnector;
use Aura\Di;
use Psr\Log\LoggerInterface as Logger;
use React\EventLoop\LoopInterface;

require_once __DIR__ . '/../../../conf/core/require.php';

spl_autoload_call('\\Schoolbox\\Application\\Daemon');
$kernel = new Schoolbox\Application\Daemon\Kernel(
    'Link Rubric Marks to Responses',
    [
        'verbose' => [false, false, false],
        'errors'  => E_ALL,
    ]
);

$kernel->run(
/**
 * @param Di\Container  $di
 * @param LoopInterface $loop
 * @param Logger        $log
 * @param               $opts
 *
 * @throws Di\Exception\ServiceNotFound
 */
    function (Di\Container $di, LoopInterface $loop, Logger $log, $opts) {
        $db       = $di->get('databaseInternal');
        $migrator = new LinkRubricMarkToFeedback($db, $log);
        $migrator->run();
    });

class LinkRubricMarkToFeedback
{

    const TIME_DELTA = 120;

    private $countBrokenRubricMarks = 0;
    private $countPropagatedRubricMarks = 0;
    private $logPropagatedRubricMarks = '';
    private $countLinkedPublishedFeedbacks = 0;
    private $countLinkedDraftFeedbacks = 0;
    private $countCombinedDrafts = 0;

    /** @var IConnector */
    private $db;

    /** @var Logger */
    private $log;

    /**
     * @param IConnector $db
     * @param Logger     $log
     */
    public function __construct(IConnector $db, Logger $log)
    {
        $this->db  = $db;
        $this->log = $log;
    }

    /**
     * Run the migration
     */
    public function run()
    {
        $this->migrateByParticipation();
        $this->printLog();
    }

    /**
     * Gets participation records per student per submission box
     *
     * @return array
     */
    private function getParticipation()
    {
        $participants = $this->db->executeQuery(
            '
            SELECT rm.student_id, rl.box_id
            FROM rubric_mark rm
            JOIN rubric_descriptor rd ON rd.id = rm.descriptor_id
            JOIN rubric_level rl ON rl.id = rd.level_id
            GROUP BY rm.student_id, rl.box_id
            '
        )->fetchAll();

        return $participants;
    }

    /**
     * Links published, then drafts for each participation
     */
    private function migrateByParticipation()
    {
        // Dealing with published only for now
        // Loop participants and find published descriptor sets
        foreach ($this->getParticipation() as $i => $participant) {

            $this->migratePublished($participant);
            $this->migrateDrafts($participant);

        }
    }

    /**
     * Links published rubric marks to published feedbacks
     *
     * @param $participant
     */
    private function migratePublished($participant)
    {
        // All rubric marks, newest to oldest
        $rubricMarks = $this->db->executeQuery(
            '
            SELECT COUNT(rm.id) AS records, rm.student_id, rm.return_id, rl.box_id, rm.created_at, rm.published_at, COUNT(DISTINCT rd.criterion_id) AS criterion
            FROM rubric_mark rm
            JOIN rubric_descriptor rd ON rd.id = rm.descriptor_id
            JOIN rubric_level rl ON rl.id = rd.level_id
            WHERE rm.student_id = :student_id
            AND rm.published_at IS NOT NULL
            AND rl.box_id = :box_id
            GROUP BY rm.published_at
            ORDER BY rm.published_at DESC;
            ',
            [
                'student_id' => $participant['student_id'],
                'box_id'     => $participant['box_id'],
            ]
        )->fetchAll();

        // All possible feedbacks, deleted or not, oldest to newest
        $feedbacks = $this->db->executeQuery(
            '
            SELECT sr.id, rm.return_id, sr.created_at, sr.updated_at, sr.deleted_at AS "DELETED", sr.published_at
            FROM submission_return sr
            JOIN submission_box sb ON sb.id = sr.box_id
            LEFT JOIN rubric_mark rm ON sr.id = rm.return_id
            WHERE sr.owner = :student_id AND sr.box_id = :box_id AND sr.published_at IS NOT NULL
            ORDER BY sr.published_at ASC;
            ',
            [
                'student_id' => $participant['student_id'],
                'box_id'     => $participant['box_id'],
            ]
        )->fetchAll();

        $this->log->info(
            sprintf(
                'Student:box %d:%d: %d published marks, %d published feedbacks',
                $participant['student_id'],
                $participant['box_id'],
                count($rubricMarks),
                count($feedbacks)
            )
        );

        foreach ($rubricMarks as $rubricMark) {
            // Validate your descriptors sets
            if (!$this->validateDescriptorSet($rubricMark)) {
                continue;
            }

            // For each rubric mark, get all feedbacks that have same or later published date
            $candidateFeedbacks = array_filter($feedbacks, function ($feedback) use ($rubricMark) {

                $rubricMarkPublishedDate = new Alaress_Date_Format($rubricMark['published_at'], Alaress_Date_Format::DATABASE_FORMAT);
                $feedbackPublishedDate   = new Alaress_Date_Format($feedback['published_at'], Alaress_Date_Format::DATABASE_FORMAT);
                $this->log->debug(
                    sprintf(
                        'Mark published %s; Feedback published %s',
                        $rubricMark['published_at'],
                        $feedback['published_at']
                    )
                );
                if ($rubricMarkPublishedDate->compare($feedbackPublishedDate) <= 0) {
                    return $feedback;
                }
            });
            // Reindex candidates
            $candidateFeedbacks = array_values($candidateFeedbacks);
            foreach ($candidateFeedbacks as $k => $candidateFeedback) {

                // Only link feedbacks if they haven't been linked yet
                // Link the first (oldest) one
                if ($k == 0 &&  $candidateFeedback['return_id'] == null) {
                    $this->linkPublished($candidateFeedback, $rubricMark);
                }
                // Propagate for the rest
                elseif ($k > 0 &&  $candidateFeedback['return_id'] == null) {
                    $this->log->debug('Propagating mark to feedback');
                    $this->propagateRubricMarkForward($candidateFeedback, $rubricMark);
                }
                else {
                    continue;
                }
            }
        }
    }

    /**
     * Links draft rubric marks to draft feedbacks
     *
     * @param $participant
     */
    private function migrateDrafts($participant)
    {
        // Get me drafts if there are any, newest to oldest
        $draftRubricMarks = $this->db->executeQuery(
            '
                SELECT COUNT(rm.id), rm.student_id, rl.box_id, rm.created_at, COUNT(DISTINCT rd.criterion_id)
                FROM rubric_mark rm
                JOIN rubric_descriptor rd ON rd.id = rm.descriptor_id
                JOIN rubric_level rl ON rl.id = rd.level_id
                WHERE rm.student_id = :student_id
                AND rm.published_at IS NULL
                AND rl.box_id = :box_id
                GROUP BY rm.created_at
                ORDER BY rm.created_at DESC;
                ',
            [
                'student_id' => $participant['student_id'],
                'box_id'     => $participant['box_id'],
            ]

        )->fetchAll();

        if (count($draftRubricMarks) > 0) {

            $this->log->info(
                sprintf(
                    'Student:box %d:%d: %d draft marks',
                    $participant['student_id'],
                    $participant['box_id'],
                    count($draftRubricMarks)
                )
            );

            if (count($draftRubricMarks) > 1) {

                $combineDrafts = $this->combineDrafts($draftRubricMarks);
                if (!empty($combineDrafts)) {
                    $draftRubricMarks = $combineDrafts;
                    $this->log->notice('More than 1 draft record appears to belong to the same feedback');
                } else {
                    $this->log->warning('More than 1 draft found for the participation. Skipping!');

                    return;
                }
            }

            // There is not much sense in getting multiple draft feedbacks to link the rubric mark to,
            // as it is invalid to have multiple draft feedbacks simultaneously.
            // Get latest feedback and check if it has been created with or after the rubric mark
            $draftFeedbacks = $this->db->executeQuery(
                '
                    SELECT sr.id, sr.created_at
                    FROM submission_return sr
                    JOIN submission_box sb ON sb.id = sr.box_id
                    WHERE sr.owner = :student_id AND sr.box_id = :box_id AND sr.published_at IS NULL
                    ORDER BY sr.created_at DESC
                    LIMIT 1;
                ',
                [
                    'student_id' => $participant['student_id'],
                    'box_id'     => $participant['box_id'],
                ])->fetchAll();

            $this->log->info(
                sprintf(
                    'Student:box %d:%d: %d combined draft marks, %d draft feedbacks',
                    $participant['student_id'],
                    $participant['box_id'],
                    count($draftRubricMarks),
                    count($draftFeedbacks)
                )
            );

            if (!empty($draftFeedbacks[0])) {

                $draftRubricMarkCreatedDate = new Alaress_Date_Format($draftRubricMarks[0]['created_at'], Alaress_Date_Format::DATABASE_FORMAT);
                $draftFeedbackCreatedDate   = new Alaress_Date_Format($draftFeedbacks[0]['created_at'], Alaress_Date_Format::DATABASE_FORMAT);

                $this->log->debug(
                    sprintf(
                        'Mark created %s, Feedback created %s',
                        $draftRubricMarks[0]['created_at'],
                        $draftFeedbacks[0]['created_at']
                    )
                );

                // Respect that delta you allowed for combined drafts
                if ($draftFeedbackCreatedDate->diffInSeconds($draftRubricMarkCreatedDate) <= self::TIME_DELTA) {

                    //Link your rubric marks (single or combined) to that feedback
                    $this->log->debug('Linking mark to feedback');
                    foreach ($draftRubricMarks as $draftRubricMark) {

                        $this->linkDrafts($draftFeedbacks[0], $draftRubricMark);

                    }
                } else {
                    $this->log->debug('Not linking mark: mark was created after feedback');
                }
            } else {
                $this->log->notice('No draft feedbacks to link to marks');
            }
        }
    }

    /**
     * Sets return_id for published
     *
     * @param $feedback
     * @param $rubricMark
     */
    private function linkPublished($feedback, $rubricMark)
    {
        $this->db->executeQuery(
            '
                UPDATE rubric_mark rm
                JOIN rubric_descriptor rd ON rd.id = rm.descriptor_id
                JOIN rubric_level rl ON rl.id = rd.level_id
                SET rm.return_id = :feedback_id
                WHERE rm.student_id = :student_id
                AND rm.published_at = :published_at
                AND rl.box_id = :box_id
                AND rm.return_id IS NULL;
                ',
            [
                'feedback_id'  => $feedback['id'],
                'student_id'   => $rubricMark['student_id'],
                'published_at' => $rubricMark['published_at'],
                'box_id'       => $rubricMark['box_id'],
            ]
        );

        $this->countLinkedPublishedFeedbacks++;
    }

    /**
     * Sets return_id for drafts
     *
     * @param $feedback
     * @param $rubricMark
     */
    private function linkDrafts($feedback, $rubricMark)
    {
        $this->db->executeQuery(
            '
                UPDATE rubric_mark rm
                JOIN rubric_descriptor rd ON rd.id = rm.descriptor_id
                JOIN rubric_level rl ON rl.id = rd.level_id
                SET rm.return_id = :feedback_id
                WHERE rm.student_id = :student_id
                AND rm.created_at = :created_at
                AND rl.box_id = :box_id
                AND rm.return_id IS NULL;
                ',
            [
                'feedback_id' => $feedback['id'],
                'student_id'  => $rubricMark['student_id'],
                'created_at'  => $rubricMark['created_at'],
                'box_id'      => $rubricMark['box_id'],
            ]
        );

        $this->countLinkedDraftFeedbacks++;
    }

    /**
     * Propagates rubric mark forward and links feadback to it
     *
     * @param $rubricMark
     * @param $feedback
     *
     * @return bool
     */
    private function propagateRubricMarkForward($feedback, $rubricMark)
    {
        $propogatedRecords = [];

        $descriptors = $this->db->executeQuery(
            '
                SELECT descriptor_id FROM rubric_mark rm
                JOIN rubric_descriptor rd ON rd.id = rm.descriptor_id
                JOIN rubric_level rl ON rl.id = rd.level_id
                WHERE rm.student_id = :student_id
                AND rm.published_at = :published_at
                AND rl.box_id = :box_id
                AND rm.return_id IS NOT NULL;
                ',
            [
                'student_id'   => $rubricMark['student_id'],
                'published_at' => $rubricMark['published_at'],
                'box_id'       => $rubricMark['box_id'],
            ]

        )->fetchAll();

        foreach ($descriptors as $descriptor) {

            $this->db->executeQuery(
                '
                    INSERT INTO rubric_mark (return_id, student_id, descriptor_id, created_at, published_at, valid)
                    VALUES
                    (:return_id, :student_id, :descriptor_id, :created_at, :published_at, 1);
                    ',
                [
                    'return_id'     => $feedback['id'],
                    'student_id'    => $rubricMark['student_id'],
                    'descriptor_id' => $descriptor['descriptor_id'],
                    'created_at'    => $feedback['created_at'],
                    'published_at'  => $feedback['published_at'],
                ]
            );
            $propogatedRecords[] = $this->db->lastInsertId();
        }
        $this->logPropagatedRubricMark = sprintf('[%d: %s]', $feedback['id'], implode($propogatedRecords, ','));
        $this->countPropagatedRubricMarks++;
        $this->countLinkedPublishedFeedbacks++;
        return (true);
    }

    /**
     * Combines records that differ by time but still belong to the same feedback
     *
     * @param $draftRubricMarks
     *
     * @return array
     */
    private function combineDrafts($draftRubricMarks)
    {
        $combinedDrafts     = [];
        $combinedCriterions = [];

        $latestCreatedDate = new Alaress_Date_Format($draftRubricMarks[0]['created_at'], Alaress_Date_Format::DATABASE_FORMAT);

        foreach ($draftRubricMarks as $i => $draftRubricMark) {

            // Validate descriptors within same rubric mark.
            // If the latest draft rubric mark is broken, give up on linking this participation drafts, the data is too messed up.

            $rubricMarkCriterions = $this->validateDescriptorCombination($draftRubricMark);

            if ($rubricMarkCriterions == false) {
                // Stop there, return what you've already got
                break;
            } elseif ($i == 0) {
                //We've got at least one good draft
                $combinedDrafts[]     = $draftRubricMark;
                $combinedCriterions[] = $rubricMarkCriterions;
            } else {
                $createdDate = new Alaress_Date_Format($draftRubricMark['created_at'], Alaress_Date_Format::DATABASE_FORMAT);

                // Check if creation dates are relatively close
                if ($createdDate->diffInSeconds($latestCreatedDate) > self::TIME_DELTA) {
                    break;
                }

                // Check if criterions won't overlap
                foreach ($rubricMarkCriterions as $rubricMarkCriterion) {
                    if (in_array($rubricMarkCriterion, $combinedCriterions)) {
                        break;
                    }
                }

                // All good? Group them together
                $combinedDrafts[] = $draftRubricMark;
            }
        }

        $this->countCombinedDrafts++;
        return $combinedDrafts;
    }

    /**
     * Check if descriptors you think belong to the same feedback are not of the same criterion
     * @param $rubricMark
     *
     * @return bool
     */
    private function validateDescriptorSet($rubricMark) {
        if ($rubricMark['criterion'] != $rubricMark['records']) {
            $this->log->warning(sprintf('Invalid descriptors for student_id %d box_id %d and published date %s
                            - Skipping!',
                    $rubricMark['student_id'], $rubricMark['box_id'], $rubricMark['published_at'])
            );
            $this->countBrokenRubricMarks++;
            return false;
        }
        return true;
    }

    /**
     * Check if descriptors you think belong to the same feedback don't belong to the same criterion
     *
     * @param $db
     * @param $log
     * @param $rubricMarks
     *
     * @return array|bool
     */
    private function validateDescriptorCombination($rubricMarks)
    {
        $criterions = $this->db->executeQuery(
            '
            SELECT rd.criterion_id FROM rubric_mark rm
            JOIN rubric_descriptor rd ON rd.id = rm.descriptor_id
            JOIN rubric_level rl ON rl.id = rd.level_id
            WHERE rm.student_id = :student_id
            AND rm.created_at = :created_at
            AND rl.box_id = :box_id;
            ',
            [
                'student_id' => $rubricMarks['student_id'],
                'created_at' => $rubricMarks['created_at'],
                'box_id'     => $rubricMarks['box_id'],
            ]
        )->fetchAll(PDO::FETCH_COLUMN);

        $uniqueCriterions = array_map("unserialize", array_unique(array_map("serialize", $criterions)));

        if (count($uniqueCriterions) != count($criterions)) {

            $this->log->warning(sprintf('Records for student_id %d, box_id %d, created at %s can not be combined into one draft',
                    $rubricMarks['student_id'], $rubricMarks['box_id'], $rubricMarks['created_at'])
            );

            return false;

        }

        return $criterions;
    }

    /**
     * Print log
     */
    private function printLog()
    {
        // TODO need more logging
        $this->log->notice(sprintf('Broken descriptor sets: %d', $this->countBrokenRubricMarks));
        $this->log->notice(sprintf('Total propagated rubric marks: %d %s', $this->countPropagatedRubricMarks, $this->logPropagatedRubricMarks));
        $this->log->notice(sprintf('Total published feedbacks linked: %d', $this->countLinkedPublishedFeedbacks));
        $this->log->notice(sprintf('Total draft feedbacks linked: %d', $this->countLinkedDraftFeedbacks));
        $this->log->notice(sprintf('Total combined draft records: %d', $this->countCombinedDrafts));

    }
}
