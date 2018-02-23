<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Participant\Plan;

use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Parameter\Param;
use Alaress_Date_Format;
use Monolog\Logger;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper as DueWorkFeedbackMapper;
use Schoolbox\Assessment\Participant\Mapper as ParticipantMapper;
use Schoolbox\Collection;
use Schoolbox\Feed\SourceInterface;
use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Learning\Quiz\Attempt\Mapper as QuizFeedbackMapper;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Notification\Parameter\CategoryParam;
use Schoolbox\Realtime\Notification\Parameter\ChildParam;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundleInterface;
use Schoolbox\User;

/**
 * Notifier for participation plans.
 *
 * This functions as the notifier for all assessment types. This is because the
 * participation plan, not the assessment itself, holds the details for:
 * * who is participating in the assessment
 * * whether they're excluded or not
 * * when the assessment opens for each participant
 *
 * Notifications for the various feedback and response types are not handled by
 * this notifier.
 */
class Notifier extends AbstractModuleNotifier implements SourceInterface
{
    private const TRIGGER_OPEN     = 'open';
    private const TRIGGER_UPDATED  = 'updated';
    private const TRIGGER_UPCOMING = 'upcoming';
    private const TRIGGER_OVERDUE  = 'overdue';
    private const TRIGGER_CLOSED   = 'closed';

    /**
     * Hours in advance to inform a user that due work is upcoming.
     *
     * TODO make this configurable
     */
    private const DUEWORK_UPCOMING_WINDOW = 2;

    /**
     * Hours in advance to inform a user that quiz is upcoming.
     *
     * TODO make this configurable separately from DUEWORK_UPCOMING_WINDOW
     */
    private const QUIZ_UPCOMING_WINDOW = 2;

    /**
     * @var AssessmentMapper
     */
    private $assessmentMapper;

    /**
     * @var AssessmentDataSource
     */
    private $assessmentDataSource;

    /**
     * @var DueWorkFeedbackMapper
     */
    private $dueWorkFeedbackMapper;

    /**
     * @var QuizFeedbackMapper
     */
    private $quizFeedbackMapper;

    /**
     * Irrelevant as this could notify about different things, but must exist.
     *
     * @return string
     */
    protected function getSlug(): string
    {
        return NSMapper::NS_DUEWORK;
    }

    /**
     * Instantiate all your dependencies within yourself.
     *
     * @param mixed $dependencies
     * @throws \Aura\Di\Exception\ServiceNotFound if somehow my assessment data
     *                                            source wasn't found
     */
    public function loadDependencies($dependencies = null): void
    {
        if (empty($dependencies['assessmentMapper'])) {
            $dependencies['assessmentMapper'] = AssessmentMapper::getInstance();
        }
        if (empty($dependencies['assessmentDataSource'])) {
            // Remember that thing that under pain of death we're not to use?
            //
            //                             ,-.
            //        ___,---.__          /'|`\          __,---,___
            //     ,-'    \`    `-.____,-'  |  `-.____,-'    //    `-.
            //   ,'        |           ~'\     /`~           |        `.
            //  /      ___//              `. ,'          ,  , \___      \
            // |    ,-'   `-.__   _         |        ,    __,-'   `-.    |
            // |   /          /\_  `   .    |    ,      _/\          \   |
            // \  |           \ \`-.___ \   |   / ___,-'/ /           |  /
            //  \  \           | `._   `\\  |  //'   _,' |           /  /
            //   `-.\         /'  _ `---'' , . ``---' _  `\         /,-'
            //      ``       /     \    ,='/ \`=.    /     \       ''
            //              |__   /|\_,--.,-.--,--._/|\   __|
            //              /  `./  \\`\ |  |  | /,//' \,'  \
            //             /   /     ||--+--|--+-/-|     \   \
            //            |   |     /'\_\_\ | /_/_/`\     |   |
            //             \   \__, \_     `~'     _/ .__/   /
            //              `-._,-'   `-._______,-'   `-._,-'
            //
            //          +--------------------------------------+
            //          |       \Schoolbox\Mapper::getDi       |
            //          | Under pain of death DO NOT USE THIS! |
            //          +--------------------------------------+
            //
            // ... hold my beer
            $dependencies['assessmentDataSource'] =
                $dependencies['assessmentMapper']::getDi()->get('assessmentDataSource');
        }

        // Quiz and duework feedback mapper:
        // required for triggering notifs on feedback given when marks are made
        // visible.
        if (empty($dependencies['dueWorkFeedbackMapper'])) {
            $dependencies['dueWorkFeedbackMapper'] = DueWorkFeedbackMapper::getInstance();
        }
        if (empty($dependencies['quizFeedbackMapper'])) {
            $dependencies['quizFeedbackMapper'] = QuizFeedbackMapper::getInstance();
        }
        parent::loadDependencies($dependencies);
    }

    /**
     * Copypasted from pastoral comment notifier.
     *
     * I prefer this way anyway, it divorces notifiers from role type.
     *
     * TODO try to remove this and play within rules of the standard callback system
     *
     * @param string $label
     *
     * @return array
     */
    public function retrieveCallbacks($label): array
    {
        return [
            [$this, "notify" . ucfirst($label)],
        ];
    }

    /**
     * If created, raise a notification. No conditions, no other functions. That's it.
     *
     * @param TriggerBundleInterface $bundle
     */
    public function bundleTriggers($bundle): void
    {
        /** @var Model $plan */
        $plan = $bundle->getEvent()->getObject();

        // Trigger different notifs depending on what assessment type was saved
        $assessment = $plan->getAssessment();

        // Projects cause too many problems to not shortcut out immediately
        if ($assessment->getProject()) {
            return;
        }

        $method = $bundle->getMethod();
        if ($method === Mapper::METHOD_DELETE) {
            $bundle->detachEach([
                self::TRIGGER_OPEN,
                self::TRIGGER_UPCOMING,
                self::TRIGGER_UPDATED,
                self::TRIGGER_OVERDUE,
                self::TRIGGER_CLOSED,
            ]);

        } else {
            // Bundle triggers

            // I need a close date to determine if I should trigger anything:
            // if it's in the past, trigger nothing except update.
            $closeDate = $this->coalesceDates($plan->getCloseDate(), $assessment->getCloseDate());
            if ($closeDate && $closeDate->isFuture()) {

                // Open is now always bundled, as long as it is set
                // However, if its open date is in the past don't bundle if this is an update
                $openDate = $this->coalesceDates($plan->getOpenDate(), $assessment->getOpenDate());
                if ($openDate && ($method === Mapper::METHOD_CREATE || $openDate->isFuture())) {
                    $bundle->attach(self::TRIGGER_OPEN, $openDate->getUnixTimeStamp());
                }

                // Due and upcoming: always bundled as long as due date is set
                $dueDate = $this->coalesceDates($plan->getDueDate(), $assessment->getDueDate());
                if ($dueDate) {
                    // Quiz and Due Work have different logic for when notification happens
                    // Quiz: 2 hours before
                    // Due Work: 2 days before
                    $upcomingDate = null;
                    if ($assessment->getQuiz()) {
                        $upcomingDate = (clone $dueDate)->addHours(-self::QUIZ_UPCOMING_WINDOW);
                    } elseif ($assessment->getDueWork()) {
                        $upcomingDate = (clone $dueDate)->addHours(-self::DUEWORK_UPCOMING_WINDOW);
                    }
                    // if the upcoming date is before the open date, push upcoming to 1 minute after open,
                    // rounded to the nearest 5 minutes?
                    if ($upcomingDate) {
                        if ($upcomingDate->lt($openDate)) {
                            $upcomingDate = (clone $openDate)->addMinutes(1);
                        }
                        if ($upcomingDate->isFuture()) {
                            $bundle->attach(self::TRIGGER_UPCOMING, $upcomingDate->getUnixTimeStamp());
                        }
                    }
                    $bundle->attach(self::TRIGGER_OVERDUE, $dueDate->getUnixTimeStamp());
                }

                // Close: always bundled
                $bundle->attach(self::TRIGGER_CLOSED, $closeDate->getUnixTimeStamp());
            }

            // Generic updated trigger
            if ($method === Mapper::METHOD_UPDATE) {
                $publishDate = $this->coalesceDates($plan->getPublishDate(), $assessment->getPublishDate());
                if ($publishDate && $publishDate->isPast()) {
                    $bundle->attach(self::TRIGGER_UPDATED, self::IMMEDIATELY);
                }
            }
        }
    }


    /**
     * @inheritdoc
     */
    public function getFeed($context, User $user, $limit = self::HARD_LIMIT, $offset = 0)
    {
        $c = new Collection();

        // if parents aren't allowed to see due work, return an empty collection
        if ($user->getRole()->isParent() && !$this->isParentTargetable()) {
            return $c;
        }

        // Forgive my usage of getInstance, or don't;
        // it's going to happen regardless.
        $access  = Access::getInstance();
        $mutator = new Participant\AssessmentListMutator($user, new Visibility($access), Alaress_Date_Format::now(), true);

        /** @var IAssessable[] $assessments */
        $assessments = $this->assessmentDataSource->findAllByFolderIdForReview($context, $user)
            ->map([$mutator, 'map'])
            ->filter([$mutator, 'filter'])
            ->slice($offset, $limit);

        foreach ($assessments as $assessment) {
            // Go thru each plan and raise in THAT context
            foreach ($assessment->getPlans() as $domainModelPlan) {
                $openDate = $domainModelPlan->getOpenDate() ?? $assessment->getOpenAt();
                $dueDate  = $domainModelPlan->getDueDate()  ?? $assessment->getDueAt();

                // Finagle the domain-object plan to a model-object plan,
                // so it can be used in notifiers
                /** @var Model $dataModelPlan */
                $dataModelPlan = $this->mapper->createModel($domainModelPlan->getId());
                $dataModelPlan
                    ->setName($domainModelPlan->getName())
                    ->setDescription($domainModelPlan->getDescription())
                    ->setIsDynamic($domainModelPlan->getIsDynamic())
                    ->setPublishDate($domainModelPlan->getPublishDate())
                    ->setOpenDate($domainModelPlan->getOpenDate())
                    ->setDueDate($domainModelPlan->getDueDate())
                    ->setCloseDate($domainModelPlan->getDueDate());

                // Finagle participants too
                foreach ($domainModelPlan->getParticipants() as $participant) {
                    $dataModelParticipant = ParticipantMapper::getInstance()->createModel($participant->getId());
                    $dataModelParticipant
                        ->setUser($participant->getUser())
                        ->setExcludedAt($participant->getExcludedAt())
                    ;
                    $dataModelPlan->getParticipants()->setOffset($participant->getUser()->getId(), $dataModelParticipant);
                }

                $c = $c
                    ->merge(new Collection($this->internalNotifyOpen($assessment, $dataModelPlan, $assessment->getCreator())
                                                ->getAll($openDate)))
                    ->merge(new Collection($this->internalNotifyClosed($assessment, $dataModelPlan)
                                                ->getAll($dueDate)));
            }
        }

        return $c;
    }


    /**
     * Participant gets notified when an assessment opens.
     *
     * This encapsulates both quiz and duework open notifier, as they're
     * identical apart from topic.
     *
     * @param Model $plan
     * @return Envelope
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    public function notifyOpen(Model $plan): Envelope
    {
        return $this->internalNotifyOpen($this->retrieveAssessmentDomainModel($plan), $plan, $effector);
    }


    /**
     * Internal method for notifying on open, implemented so that feed can use
     * it too.
     *
     * @param IAssessable $assessment
     * @param Model       $plan
     * @param User        $effector
     * @return Envelope
     */
    private function internalNotifyOpen(IAssessable $assessment, Model $plan, User $effector): Envelope
    {
        $envelope = new Envelope();
        $topicStub  = $this->retrieveAssessmentTopicStub($assessment);
        if (!$topicStub) {
            return $envelope;
        }
        if (!$this->isNotifiableAtAll($assessment, self::TRIGGER_OPEN)) {
            return $envelope;
        }

        // Customise and send to every participant in this plan only
        foreach ($this->retrieveParticipantsFromPlan($plan, $assessment) as $participant) {
            if (!$this->isParticipantNotifiable($participant, self::TRIGGER_OPEN)) {
                continue;
            }
            $this->internalNotifyOpen($envelope, $participant, $topicStub);
        }

        return $envelope;
    }


    /**
     * Internal method for building open assessment message.
     *
     * @param Envelope    $envelope
     * @param Participant $participant
     * @param string      $topicStub
     */
    private function internalNotifyOpen(Envelope $envelope, Participant $participant, string $topicStub): void
    {
        // Get assessment and build
        $assessment = $participant->getAssessable();
        $boxType    = $assessment->getTypeName();
        if ($assessment instanceof DueWork) {
            $topicSuffix = "open";
        } elseif ($assessment instanceof Quiz) {
            $topicSuffix = "upcoming";
        } else {
            return;
        }

        $bob = $this->buildGenericBuilder($assessment)
                    ->setTitle("$boxType :assessment opened in :cat")
                    ->addTopic("$topicStub.$topicSuffix")
                    ->setSeverity(Logger::NOTICE);
        $bob = $this->targetBuilderToStudent($bob, $assessment, $participant)
                    ->setBody($participant->getDescription())
                    ->setByline("Due {$participant->getDueDate()->getCalendarDateTime()}");
        $envelope->targetToUser($participant->getUser(), $bob->build());
    }


    /**
     * Participant gets notified when an assessment is upcoming.
     *
     * Proxies to `notify{$type}Upcoming`.
     *
     * @param Model $plan
     * @return Envelope
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    public function notifyUpcoming(Model $plan): Envelope
    {
        $assessment = $this->retrieveAssessmentDomainModel($plan);
        $envelope   = new Envelope();
        $topicStub  = $this->retrieveAssessmentTopicStub($assessment);
        if (!$topicStub) {
            return $envelope;
        }
        if (!$this->isNotifiableAtAll($assessment, self::TRIGGER_UPCOMING)) {
            return $envelope;
        }

        $bob = $this->buildGenericBuilder($assessment)
                    ->setSeverity(Logger::WARNING)
                    ->addTopic("$topicStub.upcoming");

        // Customise and send to every participant in this plan only
        foreach ($this->retrieveParticipantsFromPlan($plan, $assessment) as $participant) {
            if (!$this->isParticipantNotifiable($participant, self::TRIGGER_UPCOMING)) {
                continue;
            }

            // Title varies for due work and quiz
            if ($assessment instanceof DueWork) {
                $when         = $participant->getDueDate()->getRelativeTime(Alaress_Date_Format::now()->addMinutes(-5), true);
                $studentTitle = ":assessment is due $when from now in :cat";
                $parentTitle  = ":child has not yet submitted work for :assessment, which is due $when from now in :cat";

            } elseif ($assessment instanceof Quiz) {
                $when         = self::QUIZ_UPCOMING_WINDOW . " hour" . (self::QUIZ_UPCOMING_WINDOW === 1 ? '' : 's');
                $studentTitle = ":assessment is due in $when in :cat";
                $parentTitle  = ":child has not yet attempted :assessment in :cat";
            } else {
                continue;
            }

            // build for student
            $bob = $this->targetBuilderToStudent($bob, $assessment, $participant)
                        ->setTitle($studentTitle)
                        ->setBody($participant->getDescription());
            $envelope->targetToUser($participant->getUser(), $bob->build());

            // build for parent, only if they're allowed
            if (!$this->isParentTargetable()) {
                continue;
            }
            $bob = $this->targetBuilderToParents($bob, $assessment, $participant)
                        ->setTitle($parentTitle);
            $envelope->targetToParent($participant->getUser(), $bob->build());
        }

        return $envelope;
    }


    /**
     * Participant gets notified when an assessment is overdue.
     *
     * Proxies to `notify{$type}Overdue`.
     *
     * @param Model $plan
     * @return Envelope
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    public function notifyOverdue(Model $plan): Envelope
    {
        $assessment = $this->retrieveAssessmentDomainModel($plan);
        $envelope   = new Envelope();
        $topicStub  = $this->retrieveAssessmentTopicStub($assessment);
        if (!$topicStub) {
            return $envelope;
        }
        if (!$this->isNotifiableAtAll($assessment, self::TRIGGER_OVERDUE)) {
            return $envelope;
        }

        $bob = $this->buildGenericBuilder($assessment)
                    ->setSeverity(Logger::ERROR)
                    ->addTopic("$topicStub.overdue");

        // Customise and send to every participant in this plan only
        foreach ($this->retrieveParticipantsFromPlan($plan, $assessment) as $participant) {
            if (!$this->isParticipantNotifiable($participant, self::TRIGGER_OVERDUE)) {
                continue;
            }

            // TODO figure out if overdue should really be different for quiz and duework
            if ($assessment instanceof DueWork) {
                $studentTitle = ":assessment is now overdue in :cat";
                $parentTitle  = ":child did not submit work for :assessment in :cat";
            } elseif ($assessment instanceof Quiz) {
                $studentTitle = ":assessment has closed in :cat";
                $parentTitle  = ":child did not attempt :assessment in :cat";
            } else {
                continue;
            }

            // build for student
            $bob = $this->targetBuilderToStudent($bob, $assessment, $participant)
                        ->setTitle($studentTitle)
                        ->setBody($participant->getDescription());
            $envelope->targetToUser($participant->getUser(), $bob->build());

            // build for parent, only if they're allowed
            if (!$this->isParentTargetable()) {
                continue;
            }
            $bob = $this->targetBuilderToParents($bob, $assessment, $participant)
                        ->setTitle($parentTitle);
            $envelope->targetToParent($participant->getUser(), $bob->build());
        }

        return $envelope;
    }


    /**
     * Participant gets notified when an assessment closes.
     *
     * Proxies to `notify{$type}Closed`.
     *
     * @param Model $plan
     * @return Envelope
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    public function notifyClosed(Model $plan): Envelope
    {
        return $this->internalNotifyClosed($this->retrieveAssessmentDomainModel($plan), $plan);
    }


    /**
     * Internal method for notifying on close, implemented so that feed can use
     * it too.
     *
     * @param IAssessable $assessment
     * @param Model       $plan
     * @return Envelope
     */
    private function internalNotifyClosed(IAssessable $assessment, Model $plan): Envelope
    {
        $envelope  = new Envelope();
        $topicStub = $this->retrieveAssessmentTopicStub($assessment);
        if (!$topicStub) {
            return $envelope;
        }
        if (!$this->isNotifiableAtAll($assessment, self::TRIGGER_CLOSED)) {
            return $envelope;
        }

        // Send a single notification for total # students not submitted
        $href = $this->buildAssessmentRoute($assessment);
        $bob  = $this->buildGenericBuilder($assessment)
                     ->setTitle(":assessment in :cat has closed and :number did not submit work")
                     ->setHref($href)
                     ->setParam('assessment', new Param($assessment->getTitle(), $href))
                     ->setSeverity(Logger::ERROR)
                     ->addTopic("$topicStub.closed");

        // Figure out how many need to submit
        $totalUnsubmitted = 0;
        foreach ($this->retrieveParticipantsFromPlan($plan, $assessment) as $participant) {
            if (!$this->isParticipantNotifiable($participant, self::TRIGGER_CLOSED)) {
                continue;
            }
            $totalUnsubmitted++;
        }
        if ($totalUnsubmitted > 0) {
            $bob->setParam(
                'number',
                new Param(sprintf('%s student%s', $totalUnsubmitted, $totalUnsubmitted === 1 ? '' : 's'))
            );
            $envelope->broadcastToCategory(
                $assessment->getBaseContext()->getFolder(),
                UserAccess::ACCESS_WRITE,
                $bob->build()
            );
        }

        return $envelope;
    }

    /**
     * Participant gets notified when an assessment is updated.
     *
     * @noinspection PhpUnusedParameterInspection
     *
     * @param Model      $plan
     * @param User       $effector
     * @param array|null $diff
     * @return Envelope
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    public function notifyUpdated(Model $plan, User $effector, array $diff = null): Envelope
    {
        $assessment = $this->retrieveAssessmentDomainModel($plan);
        $envelope   = new Envelope();
        $topicStub  = $this->retrieveAssessmentTopicStub($assessment);
        if (!$topicStub) {
            return $envelope;
        }
        if (!$this->isNotifiableAtAll($assessment, self::TRIGGER_UPDATED)) {
            return $envelope;
        }

        // Build a box type
        $boxType       = $assessment->getTypeName();
        $parentBoxType = mb_strtolower($boxType);

        // All updated notifs have the same icon, namespace, severity
        $bob = $this->buildGenericBuilder($assessment)
                    ->setSeverity(Logger::ERROR);

        // Figure out who's actually targeted
        foreach ($this->retrieveParticipantsFromPlan($plan, $assessment) as $participant) {
            if (!$this->isParticipantNotifiable($participant, self::TRIGGER_UPDATED)) {
                continue;
            }

            // Most tests ara against the assessment diff
            $assessmentDiff = $diff['assessment'] ?? [];


            // INCREASE in mark visibility (box can be closed in this instance)
            // doesn't actually create messages, just triggers for others to be created
            if (isset($assessmentDiff['markVisibility'])) {
                [$new, $old] = $assessmentDiff['markVisibility'];
                if ($new > $old) {

                    // Trigger notification about this item
                    $authoritativeFeedback = $assessment->getAuthoritativeFeedback($participant, true);
                    if ($authoritativeFeedback) {

                        // Figure out which mapper to use
                        $feedbackMapper = null;
                        if ($assessment instanceof DueWork) {
                            $feedbackMapper = $this->dueWorkFeedbackMapper;
                        } elseif ($assessment instanceof Quiz) {
                            $feedbackMapper = $this->quizFeedbackMapper;
                        }

                        // and raise a notification against it
                        if ($feedbackMapper instanceof BaseMapper) {
                            $this->raiseNotification(
                                $authoritativeFeedback->getReviewer(),
                                BaseMapper::METHOD_CREATE,
                                $feedbackMapper->findById($authoritativeFeedback->getId()),
                                $feedbackMapper
                            );
                        }
                    }
                }
            }
            unset($assessmentDiff['markVisibility']);


            // Due date is passed: trigger no more
            if ($participant->getDueDate()->isPast()) {
                continue;
            }


            // renamed
            if (!empty($assessmentDiff['title'])) {
                [, $oldName] = $assessmentDiff['title'];

                // Students
                // Topic depends on type of assessment
                $suffix = $assessment instanceof Quiz ? 'updated' : 'renamed';
                $bob    = $this->targetBuilderToStudent($bob, $assessment, $participant)
                               ->setTitle("$boxType was renamed from :oldname to :assessment in :cat")
                               ->setParam('oldname', new Param($oldName))
                               ->setTopics(["$topicStub.$suffix"]);
                $envelope->targetToUser($participant->getUser(), $bob->build());

                // Parents
                if ($this->isParentTargetable()) {
                    $bob = $this->targetBuilderToParents($bob, $assessment, $participant)
                                ->setTitle(":child's $parentBoxType was renamed from :oldname to :assessment in :cat");
                    $envelope->targetToParent($participant->getUser(), $bob->build());
                }

                $bob->unsetParam('oldname');
            }
            unset($assessmentDiff['title']);


            // Participation include/exclude or due date change:
            // When participant is included, trigger notifyOpen
            // Otherwise if due date changes, notify appropriately
            $participantDiff = $diff['participants'][$participant->getUser()->getId()] ?? [];
            if (!empty($participantDiff['excluded'])) {
                [$isExcluded, ] = $participantDiff['excluded'];
                if (!$isExcluded) {
                    // Same as notifyOpen
                    if ($this->isParticipantNotifiable($participant, self::TRIGGER_OPEN)) {
                        $this->internalNotifyOpen($envelope, $participant, $topicStub);
                    }
                }

            } elseif (!empty($participantDiff['dueDate'])) {
                [$newStamp, $oldStamp] = $participantDiff['dueDate'];
                if ($newStamp) {
                    $oldDate = strtotime((string)$oldStamp);
                    $newDate = strtotime((string)$newStamp);
                    $verb    = 'changed';
                    if ($oldDate !== false && $newDate !== false && $newDate > $oldDate) {
                        $verb = 'extended';
                    }

                    $dateformat = $participant->getDueDate()->getLongDateOnly();
                    if (!$participant->getDueDate()->isAtEndOfDay()) {
                        $dateformat = $participant->getDueDate()->getTimeOnly() .' on '. $dateformat;
                    }

                    // Students
                    // Topic depends on type of assessment
                    $suffix = $assessment instanceof Quiz ? 'updated' : 'datechanged';
                    $bob    = $this->targetBuilderToStudent($bob, $assessment, $participant)
                                   ->setTitle("$boxType :assessment closing date was $verb to :date in :cat")
                                   ->setParam('date', new Param($dateformat))
                                   ->setTopics(["$topicStub.$suffix"]);
                    $envelope->targetToUser($participant->getUser(), $bob->build());

                    // Parents
                    if ($this->isParentTargetable()) {
                        $bob = $this->targetBuilderToParents($bob, $assessment, $participant)
                                    ->setTitle(":child's $parentBoxType :assessment closing date was $verb to :date in :cat");
                        $envelope->targetToParent($participant->getUser(), $bob->build());
                    }

                    $bob->unsetParam('date');
                }
            }


            // accept online
            if ($assessment instanceof DueWork && !empty($assessmentDiff['acceptOnline'])) {
                // Students
                $m = $assessment->isAcceptNone()
                    ? 'You no longer need to submit online for :assessment in :cat'
                    : 'You now need to submit online for :assessment in :cat';
                $bob = $this->targetBuilderToStudent($bob, $assessment, $participant)
                            ->setTitle($m)
                            ->setTopics(["$topicStub.updated"]);
                $envelope->targetToUser($participant->getUser(), $bob->build());

                // Parents
                if ($this->isParentTargetable()) {
                    $m   = $assessment->isAcceptNone()
                        ? ':child no longer need to submit online for :assessment in :cat'
                        : ':child needs to submit online for :assessment in :cat';
                    $bob = $this->targetBuilderToParents($bob, $assessment, $participant)
                                ->setTitle($m);
                    $envelope->targetToParent($participant->getUser(), $bob->build());
                }
            }
            unset($assessmentDiff['acceptOnline']);


            // generic: only if there's something left in the diff.
            // Not sure if there's any point to this notif, but raise it anyway
            if (!empty($assessmentDiff)) {
                // Students
                $bob = $this->targetBuilderToStudent($bob, $assessment, $participant)
                            ->setTitle("$boxType :assessment was updated in :cat")
                            ->setTopics(["$topicStub.updated"]);
                $envelope->targetToUser($participant->getUser(), $bob->build());

                // Parents
                if ($this->isParentTargetable()) {
                    $bob = $this->targetBuilderToParents($bob, $assessment, $participant)
                                ->setTitle(":child's $parentBoxType :assessment was updated in :cat");
                    $envelope->targetToParent($participant->getUser(), $bob->build());
                }
            }
        }

        return $envelope;
    }


    /**
     * Convenience helper to build a route to the status page.
     *
     * @param IAssessable $assessment
     * @return string
     */
    private function buildAssessmentRoute(IAssessable $assessment): string
    {
        return sprintf('/learning/assessments/%s/status', $assessment->getId());
    }

    /**
     * Convenience helper to build a route to the participant's summary page.
     *
     * @param IAssessable $assessment
     * @param User        $student
     * @return string
     */
    private function buildParticipantRoute(IAssessable $assessment, User $student) :string
    {
        return sprintf('/learning/assessments/%s/%s', $assessment->getId(), $student->getId());
    }

    /**
     * @param AssessmentMapper $assessmentMapper
     * @return static
     */
    protected function setAssessmentMapper(AssessmentMapper $assessmentMapper): self
    {
        $this->assessmentMapper = $assessmentMapper;
        return $this;
    }

    /**
     * @param AssessmentDataSource $assessmentDataSource
     * @return static
     */
    protected function setAssessmentDataSource(AssessmentDataSource $assessmentDataSource)
    {
        $this->assessmentDataSource = $assessmentDataSource;
        return $this;
    }

    /**
     * @param DueWorkFeedbackMapper $dueWorkFeedbackMapper
     * @return static
     */
    protected function setDueWorkFeedbackMapper(DueWorkFeedbackMapper $dueWorkFeedbackMapper): self
    {
        $this->dueWorkFeedbackMapper = $dueWorkFeedbackMapper;
        return $this;
    }

    /**
     * @param QuizFeedbackMapper $quizFeedbackMapper
     * @return static
     */
    protected function setQuizFeedbackMapper(QuizFeedbackMapper $quizFeedbackMapper): self
    {
        $this->quizFeedbackMapper = $quizFeedbackMapper;
        return $this;
    }

    /**
     * Get the first non-null date in `$dates`, or null otherwise.
     *
     * Currently only needed for triggering a bundle for a plan.
     *
     * @param Alaress_Date_Format[]|null[] ...$dates
     * @return Alaress_Date_Format|null
     */
    private function coalesceDates(?Alaress_Date_Format... $dates): ?Alaress_Date_Format
    {
        foreach ($dates as $date) {
            if ($date instanceof Alaress_Date_Format && !$date->isNull()) {
                return clone $date;
            }
        }
        return null;
    }

    /**
     * Fetches the assessment domain model for this plan.
     *
     * @param Model $plan
     * @return IAssessable
     * @throws \Aura\Di\Exception\ServiceNotFound  if for whatever reason I couldn't find the data source?
     *                                             Should never happen.
     * @throws \Schoolbox\Mapper\Exception\Deleted if item I'm getting notifs for is deleted
     */
    private function retrieveAssessmentDomainModel(Model $plan): IAssessable
    {
        return $this->assessmentDataSource->findByIdForReview($plan->getAssessment()->getId());
    }

    /**
     * Fetches the topic stub for this assessment; this is required for
     * targeting notifications to the assessment's mailbox.
     *
     * This action requires access to the assessment data-level mapper.
     *
     * @param IAssessable $assessment
     * @return string|null
     */
    private function retrieveAssessmentTopicStub(IAssessable $assessment): ?string
    {
        $dataModel = $this->assessmentMapper->findById($assessment->getId());
        if ($dataModel->getDueWork()) {
            return "duework." . $dataModel->getDueWork()->getId();
        }
        if ($dataModel->getQuiz()) {
            return "assess." . $dataModel->getQuiz()->getId();
        }
        return null;
    }

    /**
     * Retrieves an iterable over the participants in this plan.
     *
     * This requires the assessment's cooperation, as it stores the actual
     * participants.
     *
     * @param IAssessable $assessment
     * @param Model       $plan
     * @return \Generator|Participant[]
     */
    private function retrieveParticipantsFromPlan(Model $plan, IAssessable $assessment)
    {
        foreach ($assessment->getPlans() as $assessmentPlan) {
            if ($assessmentPlan->getId() !== $plan->getId()) {
                continue;
            }
            foreach ($assessmentPlan->getParticipants() as $participant) {
                yield $participant;
            }
        }
    }

    /**
     * Builds a builder with generic parameters set.
     *
     * @param IAssessable $assessment The assessment to build a builder for.
     * @return Builder
     */
    private function buildGenericBuilder(IAssessable $assessment): Builder
    {
        $bob    = new Builder();
        $folder = $assessment->getBaseContext()->getFolder();
        $icon   = "/images/icon?" . http_build_query(['size' => $this->iconSize, 'icon' => 'work']);

        // Namespace depends on kind of assessment
        $namespace = null;
        if ($assessment instanceof DueWork) {
            $namespace = NSMapper::NS_DUEWORK;
        } elseif ($assessment instanceof Quiz) {
            $namespace = NSMapper::NS_ONLINE_ASSESS;
        }

        $bob->setParam('cat', new CategoryParam($folder))
            ->setIcon($icon)
            ->setNamespace($namespace);
        return $bob;
    }

    /**
     * Targets $bob toward the student.
     *
     * @param Builder     $bob
     * @param IAssessable $assessment
     * @param Participant $participant
     * @return Builder
     */
    private function targetBuilderToStudent(Builder $bob, IAssessable $assessment, Participant $participant): Builder
    {
        $href = $this->buildParticipantRoute($assessment, $participant->getUser());
        return $bob
            ->setParam('assessment', new Param($assessment->getTitle(), $href))
            ->unsetParam('child')
            ->setHref($href);
    }

    /**
     * Targets $bob toward the parents of the student.
     *
     * @param Builder     $bob
     * @param IAssessable $assessment
     * @param Participant $participant
     * @return Builder
     */
    private function targetBuilderToParents(Builder $bob, IAssessable $assessment, Participant $participant): Builder
    {
        $href = $this->buildParticipantRoute($assessment, $participant->getUser());
        return $bob
            ->setParam('assessment', new Param($assessment->getTitle(), $href))
            ->setParam('child', new ChildParam($participant->getUser(), Param::STACKING_REPLACE))
            ->setHref($href);
    }


    /**
     * Does this assessment send notifications for the given label at all?
     *
     * @param IAssessable $assessment
     * @param string      $label
     * @return bool
     */
    private function isNotifiableAtAll(IAssessable $assessment, string $label): bool
    {
        switch ($label) {
            case self::TRIGGER_OPEN:
            case self::TRIGGER_UPDATED:
                // Open and updated notifs are always sent
                return true;

            case self::TRIGGER_UPCOMING:
            case self::TRIGGER_OVERDUE:
            case self::TRIGGER_CLOSED:
            default:
                // All other notifs are not sent for due works that don't
                // accept online, because action isn't necessary for them.
                // It is possible, but not mandatory, for students to
                // acknowledge response, though.
                return !($assessment instanceof DueWork && $assessment->isAcceptNone());
        }
    }

    private function isParticipantNotifiable(Participant $participant, string $label): bool
    {
        switch ($label) {
            case self::TRIGGER_OPEN:
                // Open notifs are always sent to non-excluded
                return !$participant->isExcluded();

            case self::TRIGGER_UPDATED:
                // Updated: send if not excluded and isn't open yet
                return !$participant->isExcluded() && !$participant->getOpenDate()->isFuture();

            case self::TRIGGER_UPCOMING:
            case self::TRIGGER_OVERDUE:
            case self::TRIGGER_CLOSED:
            default:
                // All other notifs:
                // participant is included, and still needs to respond
                return !$participant->isExcluded() && $participant->isResponseRequired();
        }
    }

    /**
     * Are notifications targetable to parents?
     *
     * The rules are nebulous, but I'm going to assume no if either of the
     * below configs are on:
     * * `config.restrict_student_submissions`
     * * `config.restrict_parent_duework_access`
     *
     * @return bool
     */
    private function isParentTargetable(): bool
    {
        return !($this->conf->get('restrict_student_submissions') || $this->conf->get('restrict_parent_duework_access'));
    }
}
