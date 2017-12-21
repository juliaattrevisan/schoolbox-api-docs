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
 * if it was updated at the same time or after rubric mark creation. If not, this rubric mark will become an orphan.
 *
 *
 * - Valid descriptor
 * Validity of a descriptor is not taken into the consideration.
 *
 *
 * - Student submission vs floating
 * Rubric marks are linked and propagated to any type of feedback,
 * whether it is linked to any student submission or is a floating feedback.
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
    private $logPropagatedRubricMarks = [];
    private $countLinkedPublishedFeedbacks = 0;
    private $countLinkedDeletedFeedbacks = 0;
    private $countLinkedDraftFeedbacks = 0;
    private $countCombinedDrafts = 0;
    private $logCombinedDrafts = [];

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
        $this->checkLatestMarkVisibility();
        $this->printLog();
    }

    /**
     * Gets participation records per student per submission box
     *
     * @return array
     */
    private function getParticipations()
    {
        $participations = $this->db->executeQuery(
            '
            SELECT rm.student_id, rl.box_id
            FROM rubric_mark rm
            JOIN rubric_descriptor rd ON rd.id = rm.descriptor_id
            JOIN rubric_level rl ON rl.id = rd.level_id
            GROUP BY rm.student_id, rl.box_id
            '
        )->fetchAll();

        return $participations;
    }

    /**
     * Links published, then drafts for each participation
     */
    private function migrateByParticipation()
    {
        // Dealing with published only for now
        // Loop participations and find published descriptor sets
        foreach ($this->getParticipations() as $i => $participation) {

            $this->migratePublished($participation);
            $this->migrateDrafts($participation);

        }
    }

    /**
     * Links published rubric marks to published feedbacks
     *
     * @param $participation
     */
    private function migratePublished($participation)
    {
        // All rubric marks, newest to oldest
        $rubricMarks = $this->fetchPublishedRubricMarks($participation);

        // All possible feedbacks, deleted or not, oldest to newest
        $feedbacks = $this->fetchPublishedFeedbacks($participation);

        $this->log->info(
            sprintf(
                'Student:box %d:%d: %d published marks, %d published feedbacks',
                $participation['student_id'],
                $participation['box_id'],
                count($rubricMarks),
                count($feedbacks)
            )
        );

        foreach ($rubricMarks as $rubricMark) {

            // Validate your descriptors sets
            if (!$this->validateDescriptorSet($rubricMark)) {
                continue;
            }

            $propagate               = false;
            $rubricMarkPublishedDate = new Alaress_Date_Format($rubricMark['published_at'], Alaress_Date_Format::DATABASE_FORMAT);

            foreach ($feedbacks as $k => $feedback) {
                // This feedback is already linked, skip
                if ($feedback['return_id'] > 0) {
                    if ($feedback['return_id'] == $rubricMark['return_id']) {
                        $propagate = true;
                    }
                    $this->log->debug('Published feedback has already been linked, skipping');
                    continue;
                }

                $feedbackPublishedDate = new Alaress_Date_Format($feedback['published_at'], Alaress_Date_Format::DATABASE_FORMAT);

                $this->log->debug(
                    sprintf(
                        'Mark published %s, Feedback published %s',
                        $rubricMark['published_at'],
                        $feedback['published_at']
                    )
                );

                if (($rubricMarkPublishedDate->compare($feedbackPublishedDate) <= 0) && !$propagate) {
                    $this->log->debug('Linking mark to feedback: subsequent feedbacks for this mark will be propagated to');
                    $this->linkPublished($feedback, $rubricMark);
                    $propagate = true;
                    // Mark feedback as linked
                    $feedbacks[$k]['return_id'] = $feedback['id'];
                } elseif ($propagate) {
                    $this->log->debug('Propagating mark to feedback');
                    $this->propagateRubricMarkForward($feedback, $rubricMark);
                    // Mark feedback as linked
                    $feedbacks[$k]['return_id'] = $feedback['id'];
                } else {
                    $this->log->debug('Not linking or propagating mark: mark is published after feedback');
                    continue;
                }
            }
        }
    }

    /**
     * Links draft rubric marks to draft feedbacks
     *
     * @param $participation
     */
    private function migrateDrafts($participation)
    {
        $draftRubricMarks = $this->fetchDraftRubricMarks($participation);

        if (count($draftRubricMarks) > 0) {
            $this->log->info(
                sprintf(
                    'Student:box %d:%d: %d draft marks',
                    $participation['student_id'],
                    $participation['box_id'],
                    count($draftRubricMarks)
                )
            );

            // There is not much sense in getting multiple draft feedbacks to link the rubric mark to,
            // as it is invalid to have multiple draft feedbacks simultaneously.
            // Get latest feedback and check if it has been created with or after the rubric mark
            $draftFeedbacks = $this->fetchDraftFeedbacks($participation);

            $this->log->info(
                sprintf(
                    'Student:box %d:%d: %d combined draft marks, %d draft feedbacks',
                    $participation['student_id'],
                    $participation['box_id'],
                    count($draftRubricMarks),
                    count($draftFeedbacks)
                )
            );

            if (!empty($draftFeedbacks[0])) {

                if ($draftRubricMarks[0]['return_id'] != null || $draftFeedbacks[0]['return_id'] != null) {
                    // Draft rubric mark or feedback has already been linked, get out
                    $this->log->debug('Latest draft rubric mark or feedback have already been linked, skipping');

                    return;
                }

                if (count($draftRubricMarks) > 1) {
                    // See what can be combined
                    $combinedDrafts = $this->combineDrafts($draftRubricMarks);
                    if (!empty($combinedDrafts)) {
                        $draftRubricMarks = $combinedDrafts;
                        $this->log->notice('More than 1 draft record appears to belong to the same feedback');
                    } else {
                        $this->log->warning('More than 1 draft found for the participation. Skipping!');

                        return;
                    }
                }

                // In case of combined drafts, use the earliest create date
                $draftRubricMarkCreatedDate = new Alaress_Date_Format(end($draftRubricMarks)['created_at'], Alaress_Date_Format::DATABASE_FORMAT);
                $draftFeedbackCreatedDate   = new Alaress_Date_Format($draftFeedbacks[0]['updated_at'], Alaress_Date_Format::DATABASE_FORMAT);

                $this->log->debug(
                    sprintf(
                        'Mark created %s, Feedback updated %s',
                        end($draftRubricMarks)['created_at'],
                        $draftFeedbacks[0]['updated_at']
                    )
                );

                if ($draftRubricMarkCreatedDate->compare($draftFeedbackCreatedDate) <= 0) {
                    //Link your rubric marks (single or combined) to that feedback
                    $this->log->debug('Linking mark to feedback');
                    $this->countLinkedDraftFeedbacks++;
                    foreach ($draftRubricMarks as $draftRubricMark) {
                        $this->linkDrafts($draftFeedbacks[0], $draftRubricMark);
                    }
                } else {
                    $this->log->debug('Not linking mark: mark was created after feedback last updated');
                }

            } else {
                $this->log->notice('No draft feedbacks to link to marks');
            }
        }
    }

    /**
     * Return all published rubric marks for a participation, order from latest to oldest
     *
     * @param $participation
     *
     * @return array
     */
    private function fetchPublishedRubricMarks($participation)
    {
        $rubricMarks = $this->db->executeQuery(
            '
            SELECT COUNT(rm.id) AS records, rm.student_id, rm.return_id, rl.box_id, rm.published_at, COUNT(DISTINCT rd.criterion_id) AS criterion
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
                'student_id' => $participation['student_id'],
                'box_id'     => $participation['box_id'],
            ]
        )->fetchAll();

        return $rubricMarks;
    }

    /**
     * Return all published rubric marks for a participation, order from oldest to latest
     *
     * @param $participation
     *
     * @return array
     */
    private function fetchPublishedFeedbacks($participation)
    {
        $feedbacks = $this->db->executeQuery(
            '
            SELECT DISTINCT sr.id, rm.return_id, sr.created_at, sr.updated_at, sr.deleted_at AS "DELETED", sr.published_at
            FROM submission_return sr
            JOIN submission_box sb ON sb.id = sr.box_id
            LEFT JOIN rubric_mark rm ON sr.id = rm.return_id
            WHERE sr.owner = :student_id AND sr.box_id = :box_id AND sr.published_at IS NOT NULL
            ORDER BY sr.published_at ASC;
            ',
            [
                'student_id' => $participation['student_id'],
                'box_id'     => $participation['box_id'],
            ]
        )->fetchAll();

        return $feedbacks;
    }

    /**
     * Return all draft rubric marks for a participation, order from latest to oldest
     *
     * @param $participation
     *
     * @return array
     */
    private function fetchDraftRubricMarks($participation)
    {

        $draftRubricMarks = $this->db->executeQuery(
            '
                SELECT COUNT(rm.id) AS records, rm.student_id, rm.return_id, rl.box_id, rm.created_at, COUNT(DISTINCT rd.criterion_id) AS criterion
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
                'student_id' => $participation['student_id'],
                'box_id'     => $participation['box_id'],
            ]
        )->fetchAll();

        return $draftRubricMarks;
    }

    /**
     * Return all feedbacks for a participation order from latest to oldest
     *
     * @param $participation
     *
     * @return array
     */
    private function fetchDraftFeedbacks($participation)
    {
        $draftFeedbacks = $this->db->executeQuery(
            '
                    SELECT sr.id, rm.return_id, sr.updated_at
                    FROM submission_return sr
                    JOIN submission_box sb ON sb.id = sr.box_id
                    LEFT JOIN rubric_mark rm ON sr.id = rm.return_id
                    WHERE sr.owner = :student_id AND sr.box_id = :box_id AND sr.published_at IS NULL
                    ORDER BY sr.updated_at DESC
                    LIMIT 1;
                ',
            [
                'student_id' => $participation['student_id'],
                'box_id'     => $participation['box_id'],
            ])->fetchAll();

        return $draftFeedbacks;
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

        $this->checkVisibilityChange($feedback);

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
                AND rm.published_at IS NULL
                AND rm.return_id IS NULL;
                ',
            [
                'feedback_id' => $feedback['id'],
                'student_id'  => $rubricMark['student_id'],
                'created_at'  => $rubricMark['created_at'],
                'box_id'      => $rubricMark['box_id'],
            ]
        );

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
        $propagatedRecords = [];

        $descriptors = $this->db->executeQuery(
            '
                SELECT descriptor_id, valid FROM rubric_mark rm
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
                    (:return_id, :student_id, :descriptor_id, :created_at, :published_at, :valid);
                    ',
                [
                    'return_id'     => $feedback['id'],
                    'student_id'    => $rubricMark['student_id'],
                    'descriptor_id' => $descriptor['descriptor_id'],
                    'created_at'    => $feedback['created_at'],
                    'published_at'  => $feedback['published_at'],
                    'valid'         => $descriptor['valid'],

                ]
            );
            $propagatedRecords[] = $this->db->lastInsertId();
        }

        $this->checkVisibilityChange($feedback);

        $this->logPropagatedRubricMarks[] = [
            'feedbackId' => $feedback['id'],
            'markIds'    => $propagatedRecords,
        ];
        $this->countPropagatedRubricMarks++;
        $this->countLinkedPublishedFeedbacks++;

        return (true);
    }


    private function checkVisibilityChange($feedback) {
        if ($feedback['deleted_at'] !== null) {
            $this->countLinkedDeletedFeedbacks++;
            $this->log->notice('Rubric mark is linked to the soft-deleted feedback %d', $feedback['id']);
        }
    }

    /**
     * Combines records that differ by time but still belong to the same feedback
     * Feedbacks shouldn't share the same criterions
     *
     * @param $draftRubricMarks
     *
     * @return array
     */
    private function combineDrafts($draftRubricMarks)
    {
        $combinedDrafts     = [];
        $combinedCriterions = [];

        foreach ($draftRubricMarks as $i => $draftRubricMark) {
            // Validate descriptors within same rubric mark.
            // If the latest draft rubric mark is broken, give up on linking this participation drafts, the data is too messed up.
            $rubricMarkCriterions = $this->getDraftRubricMarkCriterions($draftRubricMark);

            if (!$this->validateDescriptorSet($draftRubricMark)) {
                // Stop there, return what you've already got, if you've got
                break;
            }

            if ($i == 0) {
                // We've got at least one draft
                $combinedDrafts[]     = $draftRubricMark;
                $combinedCriterions = array_merge($combinedCriterions, $rubricMarkCriterions);
                $logDates[] = $draftRubricMark['created_at'];
                $latestCreateDate    = new Alaress_Date_Format($draftRubricMarks[0]['created_at'], Alaress_Date_Format::DATABASE_FORMAT);
            } else {
                $createdDate = new Alaress_Date_Format($draftRubricMark['created_at'], Alaress_Date_Format::DATABASE_FORMAT);

                if ($this->canCombineByDate($createdDate, $latestCreateDate) &&
                    $this->canCombineByCriterion($rubricMarkCriterions, $combinedCriterions)) {

                    $combinedDrafts[]     = $draftRubricMark;
                    $combinedCriterions = array_merge($combinedCriterions, $rubricMarkCriterions);
                    $logDates[] = $draftRubricMark['created_at'];

                }
                else {
                    // Stop there, return what you've already got
                    break;
                }
            }
        }

        if (count($combinedDrafts) > 1) {
            $this->countCombinedDrafts ++;
            $this->logCombinedDrafts[] = sprintf('Student:box %d:%d created_at %s', $combinedDrafts[0]['student_id'], $combinedDrafts[0]['box_id'], implode($logDates, ', '));
        }

        return $combinedDrafts;
    }

    /**
     * Check if the drafts you want to combine are close enough by creation date
     *
     * @param Alaress_Date_Format $currentDraftCreateDate
     * @param Alaress_Date_Format $latestDraftCreateDate
     *
     * @return bool
     */
    private function canCombineByDate(Alaress_Date_Format $currentDraftCreateDate, Alaress_Date_Format $latestDraftCreateDate) {
        return $currentDraftCreateDate->diffInSeconds($latestDraftCreateDate) <= self::TIME_DELTA;
    }


    /**
     * Check if the drafts you want to combine are not conflicting by criterion
     *
     * @param array $rubricMarkCriterions
     * @param array $combinedCriterions
     *
     * @return bool
     */
    private function canCombineByCriterion($rubricMarkCriterions, $combinedCriterions) {

        foreach ($rubricMarkCriterions as $rubricMarkCriterion) {
            if (in_array($rubricMarkCriterion, $combinedCriterions)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if descriptors you think belong to the same feedback are not of the same criterion
     *
     * @param $rubricMark
     *
     * @return bool
     */
    private function validateDescriptorSet($rubricMark)
    {
        if ($rubricMark['criterion'] != $rubricMark['records']) {
            $this->log->warning(
                sprintf(
                    'Invalid descriptors for student_id %d box_id %d and published date %s - Skipping!',
                    $rubricMark['student_id'],
                    $rubricMark['box_id'],
                    $rubricMark['published_at']
                )
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
     * @param $rubricMark
     *
     * @return int[]
     */
    private function getDraftRubricMarkCriterions($rubricMark)
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
                'student_id' => $rubricMark['student_id'],
                'created_at' => $rubricMark['created_at'],
                'box_id'     => $rubricMark['box_id'],
            ]
        )->fetchAll(PDO::FETCH_COLUMN);

        return $criterions;
    }

    private function checkLatestMarkVisibility() {

        foreach ($this->getParticipations() as $i => $participation) {
            $rubricMarks = $this->db->executeQuery(
                '
                SELECT COUNT(rm.id) AS records, rm.student_id, rm.return_id, sr.box_id, rm.published_at, sr.deleted_at
                FROM rubric_mark rm
                LEFT JOIN submission_return sr ON rm.return_id = sr.id
                WHERE rm.student_id = :student_id
                AND sr.box_id = :box_id
                AND rm.published_at IS NOT NULL
                GROUP BY rm.return_id
                ORDER BY rm.published_at DESC
                ',
                    [
                        'student_id' => $participation['student_id'],
                        'box_id'     => $participation['box_id'],
                    ]
                )->fetchAll();

            if (!empty($rubricMarks[0]) && $rubricMarks[0]['deleted_at'] !== null) {
                $this->log->warning(
                    sprintf(
                        'Latest rubric mark visibility is lost for student:%d box:%d, it is linked to soft-deleted feedback %d',
                        $rubricMarks[0]['student_id'],
                        $rubricMarks[0]['box_id'],
                        $rubricMarks[0]['return_id']
                    )
                );
            }
        }
    }

    /**
     * Print log
     */
    private function printLog()
    {
        $this->log->notice(sprintf('Broken descriptor sets: %d', $this->countBrokenRubricMarks));
        $this->log->notice(sprintf('Total propagated rubric marks: %d', $this->countPropagatedRubricMarks));

        // Report the first added mark
        $markIdSets = array_column($this->logPropagatedRubricMarks, 'markIds');
        $markIds    = array_reduce($markIdSets, 'array_merge', []);
        if (count($markIds)) {
            $this->log->notice(sprintf('First added rubric mark: #%d', min($markIds)));
        }

        // Report every mark added
        foreach ($this->logPropagatedRubricMarks as $propagatedRubricMark) {
            $this->log->debug(
                sprintf(
                    'Feedback %d: [%s]',
                    $propagatedRubricMark['feedbackId'],
                    implode($propagatedRubricMark['markIds'], ',')
                )
            );
        }
        $this->log->notice(sprintf('Total published feedbacks linked: %d', $this->countLinkedPublishedFeedbacks));
        $this->log->notice(sprintf('Total draft feedbacks linked: %d', $this->countLinkedDraftFeedbacks));
        $this->log->notice(sprintf('Total combined draft occurrences: %d', $this->countCombinedDrafts));
        if ($this->logCombinedDrafts) {
            $this->log->notice(sprintf('Combined drafts details: %s', implode($this->logCombinedDrafts, ', ')));
        }
    }
}
