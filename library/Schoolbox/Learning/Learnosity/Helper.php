<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity;

use LearnositySdk\Exceptions\ValidationException;
use Psr\Log\LoggerInterface;
use Schoolbox\Learning\Assessment\Quiz\Attempt\Model as QuizResponse;
use Schoolbox\Learning\Assessment\Quiz\Model as QuizDomainModel;
use Schoolbox\Learning\Learnosity\Feedback\SanitiseScores;
use Schoolbox\Learning\Quiz\Attempt\Mapper;
use Schoolbox\Learning\Quiz\Attempt\Model;
use Schoolbox\Mapper\Exception;
use Schoolbox\User;

/**
 * A shortcut to simple operations that involve multiple steps
 *
 * Sometimes items are in an Archived state but its activities
 * are in a Published state, making the whole thing unable to
 * archive due to a bug in Learnosity.
 */
class Helper
{
    /**
     * The "published" state of an Activity/Item
     */
    public const LEARNOSITY_ITEM_STATUS_PUBLISHED = 'published';


    /**
     * The "unpublished" state of an Activity/Item
     */
    public const LEARNOSITY_ITEM_STATUS_UNPUBLISHED = 'unpublished';


    /**
     * The "archived" state of an Activity/Item
     *
     * Softdeleting is equivalent to Learnosity's "archiving"
     */
    public const LEARNOSITY_ITEM_STATUS_ARCHIVED = 'archived';


    /**
     * Possible attempt statuses.
     *
     * @see https://reference.learnosity.com/data-api/endpoints/sessions_endpoints#scores
     */

    /**
     * Attempt Status is sometimes Incomplete when students don't conclude
     * their own attempts officially.
     */
    public const LEARNOSITY_ATTEMPT_STATUS_INCOMPLETE = 'Incomplete';

    /**
     * The attempt has been scored.
     */
    public const LEARNOSITY_ATTEMPT_STATUS_COMPLETED = 'Completed';

    /**
     * The attempt has been discarded: not sure how this happens, but it is
     * documented within Learnosity's API.
     */
    public const LEARNOSITY_ATTEMPT_STATUS_DISCARDED = 'Discarded';

    /**
     * Attempt Status is sometimes Pending Scoring when Learnosity hasn't
     * marked the attempt yet: in this case, set up a callback to mark this item
     * later.
     */
    public const LEARNOSITY_ATTEMPT_STATUS_PENDING_SCORING = 'Pending Scoring';


    /**
     * Possible statuses for something in Schoolbox which is reliant upon
     * Learnosity having run a process and returned a result.
     * * PENDING: Learnosity hasn't started its process yet; it must be started,
     *   and will then be set to PROCESSING
     * * PROCESSING: Learnosity is currently running the process; its status
     *   must be checked and set to either COMPLETED or FAILED
     * * COMPLETED: the Learnosity process ran successfully
     * * FAILED: the Learnosity process did not run successfully; its status may
     *   be set back to PENDING to try again
     * * UNPROCESSABLE: the Learnosity process either could not start, or it has
     *   been determined it will never run successfully
     *
     * Possible Learnosity processes:
     * * duplicating a quiz: this involves creating a Learnosity job
     * * auto-marking: this involves sending marks to Learnosity and possibly
     *   waiting for Learnosity to complete said job
     */
    public const LEARNOSITY_PROCESS_STATUS_PENDING = 'pending';
    public const LEARNOSITY_PROCESS_STATUS_PROCESSING = 'processing';
    public const LEARNOSITY_PROCESS_STATUS_COMPLETED = 'completed';
    public const LEARNOSITY_PROCESS_STATUS_FAILED = 'failed';
    public const LEARNOSITY_PROCESS_STATUS_UNPROCESSABLE = 'unprocessable';

    /**
     * The default number of times the quiz attempt may be polled for completion
     * before being marked as failed.
     */
    public const LEARNOSITY_ATTEMPT_COMPLETION_MAX_POLLS = 10;

    /**
     * The configuration used for the regions to show in the Learnosity player
     *
     * Currently used for both Items API and Assess API initialisations but should be the same for both
     * Items: https://reference.learnosity.com/items-api/initialization/config.regions
     * Assess: https://reference.learnosity.com/assess-api/initialization/regions
     */
    public const LEARNOSITY_CONFIG_PLAYER_REGIONS = [
        'top-left' => [
            ['type' => 'title_element'],
        ],
        'top-right' => [
            ['type' => 'itemcount_element'],
            ['type' => 'reading_timer_element'],
            ['type' => 'timer_element'],
        ],
        'items' => [
            ['type' => 'progress_element'],
            ['type' => 'slider_element']
        ],
        'right' => [
            ['type' => 'save_button'],
            ['type' => 'reviewscreen_button'],
            ['type' => 'resource_button'],
            ['type' => 'flagitem_button'],
            ['type' => 'masking_button'],
            ['type' => 'verticaltoc_element'],
        ],
        'bottom-left' => [
            [
                'type' => 'previous_button',
                'show_label_option' => true
            ],
        ],
        'bottom-right' => [
            ['type' => 'next_button'],
        ]
    ];


    /**
     * Constructor.
     *
     * @param Api $api
     * @param LoggerInterface|null $logger
     */
    public function __construct(private Api $api, private ?LoggerInterface $logger = null)
    {
    }


    /**
     * Get the number of items (questions) in a particular Learnosity Activity
     *
     * @param string $learnosityReference
     * @return int
     * @throws ApiException
     * @throws ValidationException
     * @throws \JsonException
     */
    public function getActivityItemCount(string $learnosityReference): int
    {
        $activities = $this->api->dataGetRequest('/itembank/activities', [
            'references' => [
                $learnosityReference
            ]
        ]);

        /* $activities['data'] is a one-element stack in this usage.
         * For activities with no items, the items attribute is not present.
         */
        return count($activities['data'][0]['data']['items'] ?? []);
    }


    /**
     * Archive (soft-delete) a particular Learnosity Activity
     * Activities must have at least one published item before an activity can be archived
     *
     * If you can't delete an activity because it tells you that you need a published item,
     * also check any Welcome/Exit screens - those are Items too
     *
     * @param string $learnosityReference
     * @return void
     * @throws ValidationException
     * @throws \JsonException
     * @throws \RuntimeException
     */
    public function archive(string $learnosityReference): void
    {
        $this->changeState($learnosityReference, self::LEARNOSITY_ITEM_STATUS_ARCHIVED);
    }


    /**
     * Restore an Activity to Published state
     *
     * @param string $learnosityReference
     * @return void
     * @throws ValidationException
     * @throws \RuntimeException
     */
    public function restore(string $learnosityReference): void
    {
        $this->changeState($learnosityReference, self::LEARNOSITY_ITEM_STATUS_PUBLISHED);
    }


    /**
     * Change the state of an Activity and all its Items inside to $status
     *
     * @param string $learnosityReference
     * @param string $status
     * @return void
     * @throws ValidationException
     * @throws \JsonException
     * @throws \RuntimeException
     */
    public function changeState(string $learnosityReference, string $status): void
    {
        /* List of Items
         */
        $activities = $this->api->dataGetRequest('/itembank/activities', [
            'references' => [
                $learnosityReference
            ]
        ]);

        /* Find all the items associated with the activity
         */
        $activityPayload = [
            'activities' => []
        ];

        /* $activities['data'] is a one-element stack in this usage
         */
        foreach ($activities['data'] as $activity) {
            /* Note that sometimes you get an error message like this from Learnosity
             * due to a bug in their system: https://help.learnosity.com/hc/requests/23138
             */

            /* Shank the activity into the Archived state
             */
            $updatedActivity = $activity;
            $updatedActivity['status'] = $status;
            $activityPayload['activities'][] = $updatedActivity;

            $activityArchiveResult = $this->api->dataSetRequest('/itembank/activities', $activityPayload);

            /* Then shank its items into the $status state
             */
            $itemReferences = [];

            /* This quiz might not have any questions (items) in it
             */
            if (isset($activity['data']['items'])) {
                foreach ($activity['data']['items'] as $itemReference) {
                    /* Due to learnosity behaviour https://help.learnosity.com/hc/en-us/requests/23258
                     * we have to accommodate $itemReference being a string (lol) as well as an array (lol)
                     */
                    if (!is_array($itemReference)) {
                        $itemReferences[] = $itemReference;
                    } else {
                        $itemReferences[] = $itemReference['reference'];
                    }
                }
            }

            /* Get all the items' details so we can change their status
             * and all its related pieces
             *
             * Even thought he activity didn't have items in it,
             * there might be orphaned data in learnosity; we
             * can check anyway
             */
            $this->logger?->debug(sprintf(
                'Fetching items for activity %s',
                $activity['reference']
            ));

            /* No items? No trabajo
             */
            if (\count($itemReferences)) {
                /* Change status for all the items collated in $references
                 * If this is empty, it returns ALL ITEMS which isn't what you want.
                 */
                $items = $this->api->dataGetRequest('/itembank/items', ['references' => $itemReferences]);

                $itemsPayload = [
                    'items' => []
                ];

                foreach ($items['data'] as $item) {
                    $this->logger?->debug(sprintf(
                        'Setting item %s status to %s in payload',
                        $item['reference'],
                        $status
                    ));

                    $updatedItem = $item;
                    $updatedItem['status'] = $status;

                    $itemsPayload['items'][] = $updatedItem;
                }

                /* Shank them into the Archived state
                 */
                $this->logger?->debug(sprintf(
                    'Update items to status %s for activity %s',
                    $status,
                    $activity['reference']
                ));

                $itemArchiveResult = $this->api->dataSetRequest('/itembank/items', $itemsPayload);
            } else {
                $this->logger?->debug(sprintf(
                    'No items for activity %s; continuing onwards',
                    $activity['reference']
                ));

                return;
            }
        }
    }


    /**
     * Extract the response object which matches `$existingAttempt` out of the
     * student's participation record.
     *
     * @param QuizDomainModel $assessment
     * @param User $student
     * @param Model $existingAttempt
     * @return QuizResponse|null
     * @throws \UnexpectedValueException
     */
    public function extractExistingResponse(
        QuizDomainModel $assessment,
        User $student,
        Model $existingAttempt,
    ): ?QuizResponse {
        $response = $assessment->getParticipant($student)->getAllResponses()->getBy('id', $existingAttempt->getId());

        return $response instanceof QuizResponse ? $response : null;
    }


    /**
     * Assign normalised and human-readable marks to `$attempt`.
     *
     * @param Model             $attempt
     * @param QuizResponse      $responseDomainObject The domain-level object corresponding to `$attempt`: required for
     *                                                deriving a human-readable mark.
     * @param QuizDomainModel   $assessment           The assessment to which `$attempt` belongs: required for deriving
     *                                                a human-readable mark.
     * @param null|float        $mark
     * @param null|float        $maxMark
     * @return void
     */
    public function assignMarksToAttempt(
        Model $attempt,
        QuizResponse $responseDomainObject,
        QuizDomainModel $assessment,
        ?float $mark,
        ?float $maxMark
    ): void {
        /* Update normalised and human-readable marks
         */
        $normalisedMark = $attempt->calculateNormalisedMark($mark, $maxMark);

        /* Update the domain object synonymously which will desync from $attempt
         * so we need to sync them manually...
         *
         * This is required for getMarkScheme() to calculate human-readable mark
         */
        $responseDomainObject->setNormalisedMark($normalisedMark);
        $responseDomainObject->setMarksAvailable($maxMark);

        $humanReadableMark = $assessment->getMarkScheme()->getHumanReadableMark($normalisedMark, $responseDomainObject);

        /* Log data that seems suspiciously broken
         */
        (new Feedback\SuspiciousLogger())->logZeroScore(
            'assignMarksToAttempt() after calculating marks',
            $attempt->getLearnosityReference(),
            $mark,
            $maxMark,
            $normalisedMark,
            $humanReadableMark
        );

        /* Update the columns with newly-derived values
         */
        $attempt
            ->setMark($humanReadableMark)
            ->setNormalisedMark($normalisedMark)
            ->setMaxMark($maxMark);
    }


    /**
     * Process whether an attempt has been completed, by retrieving its score
     * from Learnosity and checking that score's status.
     *
     * If Learnosity reports that the score's status is
     * `self::LEARNOSITY_PROCESS_STATUS_COMPLETED`: set the score from
     * Learnosity into `$existingAttempt`.
     *
     * Uses the (learnosityReference, attemptId) tuple so you can't
     * randomly shut down some other dude's attempt; knowing their
     * learnosityReference should not be easily guessed
     *
     * @param Mapper $attemptMapper
     * @param QuizDomainModel $assessment
     * @param Model $existingAttempt
     * @return void
     * @throws ApiException
     * @throws ValidationException
     * @throws \JsonException
     * @throws \PDOException
     * @throws Exception
     * @throws \UnexpectedValueException
     */
    public function processAttemptCompletion(
        \Schoolbox\Learning\Quiz\Attempt\Mapper $attemptMapper,
        QuizDomainModel $assessment,
        Model $existingAttempt
    ): void {
        // Has this already been reviewed or marked?
        // If so, mark this as complete and don't query Learnosity:
        // the quiz attempt can already be considered as marked.
        if ($existingAttempt->getReviewed() || $existingAttempt->getMark() !== null) {
            $existingAttempt->setLearnosityStatus(self::LEARNOSITY_PROCESS_STATUS_COMPLETED);
            $attemptMapper->save($existingAttempt);
            return;
        }

        // Has the attempt already failed to mark too many times?
        // Mark it as failed and don't query Learnosity.
        if ($existingAttempt->getLearnosityPollCount() >= self::LEARNOSITY_ATTEMPT_COMPLETION_MAX_POLLS) {
            $existingAttempt->setLearnosityStatus(self::LEARNOSITY_PROCESS_STATUS_FAILED);
            $attemptMapper->save($existingAttempt);
            return;
        }

        $student = $existingAttempt->getUser();

        $payload = [
            'session_id' => [
                $existingAttempt->getLearnosityReference()
            ]
        ];

        // Find out what they scored by the automated system
        $result = $this->api->dataGetRequest('/sessions/scores', $payload);

        /* Log payload data, because we're dropping attempts randomly and nobody knows why.
         * Hopefully this init payload will helps hed light on things in SA-17302
         */
        (new Feedback\SuspiciousLogger())->logPayload(
            'processAttemptCompletion() $result payload from learnosity /sessions/scores',
            $payload,
            $result,
            $existingAttempt
        );

        /* The learnosityReference posted here doesn't exist
         * TODO What kind of error should we present to users? It's asynchronous
         */
        if (!\count($result['data'])) {
            throw new \UnexpectedValueException('Empty result payload for attempt.');
        }

        // Set status depending on the result from Learnosity.
        // If still pending scoring or incomplete:
        // set it to processing, that will act as a signal to set up a poll.
        $status = match ($result['data'][0]['status']) {
            self::LEARNOSITY_ATTEMPT_STATUS_COMPLETED       => self::LEARNOSITY_PROCESS_STATUS_COMPLETED,
            self::LEARNOSITY_ATTEMPT_STATUS_PENDING_SCORING => self::LEARNOSITY_PROCESS_STATUS_PROCESSING,
            self::LEARNOSITY_ATTEMPT_STATUS_DISCARDED       => self::LEARNOSITY_PROCESS_STATUS_FAILED,
            self::LEARNOSITY_ATTEMPT_STATUS_INCOMPLETE      => self::LEARNOSITY_PROCESS_STATUS_PROCESSING,
            default                                         => null,
        };
        $existingAttempt
            ->setLearnosityStatus($status)
            ->setLearnosityPollCount(($existingAttempt->getLearnosityPollCount() ?? 0) + 1)
        ;

        // grab and save the max_mark if it exists
        // attempts that were timed out and the student didn't complete all questions, will never have a status of (completed)
        // these broken attempts appear to have a status of 'Incomplete' and we should set a max_mark of something usable, so the teacher can mark the attempt correctly.
        // the attempt mark may exist, but we will leave that out of the equation for now.
        if ($status === self::LEARNOSITY_PROCESS_STATUS_PROCESSING) {
            $resultData = $result['data'][0];
            $sanitisedScore = (new SanitiseScores())->sanitise($resultData['score'], $resultData['max_score']);

            $maxMark = $sanitisedScore['max_score'];

            /* Fabricate an IResponse instance for existing mark scheme
             *
             * Note that $existingResponse might well be null for broken data somehow,
             * but we have no means to recover from that...and it shouldn't really
             * occur naturally
             */
            $existingResponse = $this->extractExistingResponse($assessment, $student, $existingAttempt);

            // TODO What kind of error should we present to users? It's asynchronous
            if (null === $existingResponse) {
                return;
            }

            $this->assignMarksToAttempt($existingAttempt, $existingResponse, $assessment, null, $maxMark);
        }

        // If the process hasn't completed yet: don't set mark
        if ($status === self::LEARNOSITY_PROCESS_STATUS_COMPLETED) {
            /* Arbitrarily max out $mark if it somehow exceeds the maximum limit
             * ($mark must never exceed $maxMark) and/or otherwise sanitise data
             */
            $resultData = $result['data'][0];
            $sanitisedScore = (new SanitiseScores())->sanitise($resultData['score'], $resultData['max_score']);

            $mark = $sanitisedScore['score'];
            $maxMark = $sanitisedScore['max_score'];

            /* Fabricate an IResponse instance for existing mark scheme
             *
             * Note that $existingResponse might well be null for broken data somehow,
             * but we have no means to recover from that...and it shouldn't really
             * occur naturally
             */
            $existingResponse = $this->extractExistingResponse($assessment, $student, $existingAttempt);

            // TODO What kind of error should we present to users? It's asynchronous
            if (null === $existingResponse) {
                return;
            }

            $this->assignMarksToAttempt($existingAttempt, $existingResponse, $assessment, $mark, $maxMark);
        }

        /* The finish time might already have been set if their timer elapsed;
         * If it did, just use that datetime. Otherwise, you'll be recording
         * the datetime of when the user clicked past the modal at the end of
         * every attempt, which can record a later datetime than intended.
         */
        if (!$existingAttempt->getFinish()) {
            $existingAttempt->setFinish(\Alaress_Date_Format::now());
        }

        $attemptMapper->save($existingAttempt);
    }


    /**
     * Find the current available marks, based on a learnosity quiz's items' max_score aggregation
     * We need to do this because we need to know how many marks are available for those who haven't
     * got an attempt yet (that's where it would have been cached...)
     *
     * @param string $learnosityReference
     * @return float
     * @throws ApiException
     * @throws ValidationException
     * @throws \JsonException
     */
    public function findAvailableMarks(string $learnosityReference): float
    {
        $available = 0.00;

        $itemReferences = $this->api->dataGetRequest('/itembank/activities', [
            'references' => [
                $learnosityReference
            ]
        ])['data'][0]['data']['items'] ?? null;

        if (null !== $itemReferences) {
            /* Due to learnosity behaviour https://help.learnosity.com/hc/en-us/requests/23258
             * we have to accommodate $itemReference being a string (lol) as well as an array (lol)
             */
            if (is_array($itemReferences[0])) {
                /* Forcibly mutate this, because learnosity insanity
                 */
                $itemReferenceList = array_column($itemReferences, 'id');
            } else {
                /* Presume it was a list of reference strings already
                 */
                $itemReferenceList = $itemReferences;
            }

            /* Maybe this max_score isn't available everywhere, due to the Short Answer fiasco
             * where validation.score isn't the same as max_score. It *seems* to still just work though?
             */
            $items = $this->api->dataGetRequest('/itembank/items', [
                'references' => $itemReferenceList,
                'include' => [
                    'items' => ['max_score']
                ]
            ])['data'] ?? null;

            /* Add it all up for the guys who haven't attempted yet
             */
            foreach ($items as $item) {
                $available += $item['max_score'];
            }
        }

        return $available;
    }
}
