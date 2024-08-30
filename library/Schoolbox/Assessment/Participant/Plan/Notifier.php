<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Participant\Plan;

use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Instance\Instance;
use Alaress\Async\Notification\Parameter\Param;
use Alaress_Date_Format;
use Monolog\Logger;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper as DueWorkFeedbackMapper;
use Schoolbox\Assessment\Participant\Mapper as ParticipantMapper;
use Schoolbox\Collection;
use Schoolbox\Feed\SourceInterface;
use Schoolbox\GenericCollection;
use Schoolbox\External\User as ExternalUser;
use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Assessment\Participant\Plan\Model as Plan;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Learning\Assessment\WorkType\Mapper as WorkTypeMapper;
use Schoolbox\Learning\Quiz\Attempt\Mapper as QuizFeedbackMapper;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception\Deleted as DeletedException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Notification\Parameter\ChildParam;
use Schoolbox\Realtime\Notification\Parameter\SimpleParam;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundleInterface;
use Schoolbox\User;

use function array_intersect;
use function array_keys;
use function array_key_exists;
use function count;

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

    private const PATTERN_OPEN_STUDENT_TITLE = ':boxType :assessment opened in :cat';
    private const PATTERN_OPEN_PARENT_TITLE = ':boxType :assessment opened in :cat for :child';

    private const PATTERN_UPCOMING_STUDENT_TITLE = 'You have :when left to :verb :assessment in :cat';
    private const PATTERN_UPCOMING_PARENT_TITLE = ':child has :when left to :verb :assessment in :cat';

    private const PATTERN_OVERDUE_STUDENT_TITLE = ':assessment :overdue in :cat';
    private const PATTERN_OVERDUE_PARENT_TITLE = ':child did not :submit :assessment in :cat';

    private const PATTERN_RENAMED_STUDENT_TITLE = ':boxType was renamed from :oldname to :assessment in :cat';
    private const PATTERN_RENAMED_PARENT_TITLE = ':child\'s :boxType was renamed from :oldname to :assessment in :cat';

    private const PATTERN_DATE_CHANGED_STUDENT_TITLE = ':boxType :assessment closing date was :verb to :date in :cat';
    private const PATTERN_DATE_CHANGED_PARENT_TITLE = ':child\'s :boxType :assessment closing date was :verb to :date in :cat';

    private const PATTERN_NO_SUBMIT_STUDENT_TITLE = 'You no longer need to submit online for :assessment in :cat';
    private const PATTERN_ACCEPT_ONLINE_STUDENT_TITLE = 'You now need to submit online for :assessment in :cat';

    private const PATTERN_NO_SUBMIT_PARENT_TITLE = ':child no longer need to submit online for :assessment in :cat';
    private const PATTERN_ACCEPT_ONLINE_PARENT_TITLE = ':child needs to submit online for :assessment in :cat';

    private const PATTERN_UPDATE_STUDENT_TITLE = ':boxType :assessment was updated in :cat';
    private const PATTERN_UPDATE_PARENT_TITLE = ':child\'s :boxType :assessment was updated in :cat';

    /**
     * Constants used for date comparison
     */
    private const PAST_TO_FUTURE = 1;
    private const FUTURE_TO_PAST = 2;
    private const ANY = 3;

    private const DATE_OPEN = 'openDate';
    private const DATE_DUE = 'dueDate';
    private const DATE_CLOSE = 'closeDate';

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
     * @var WorkTypeMapper;
     */
    private $workTypeMapper;

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
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
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
        if (empty($dependencies['workTypeMapper'])) {
            $dependencies['workTypeMapper'] = WorkTypeMapper::getInstance();
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
     * This is called for each of assessment's plans.
     * If there is one custom plan in an assessment, this is called twice
     * one for the dynamic plan and the other for the custom plan.
     * The date change for the assessment is cannot be found in $diff['plan']
     *
     * @param TriggerBundleInterface $bundle
     */
    public function bundleTriggers($bundle): void
    {
        // TODO: handle the case where a plan has been changed for a student.

        // give enough time to make sure all the database persistence has completed before sending out
        $now = time() + 5;

        /** @var Plan $plan */
        $plan = $bundle->getEvent()->getObject();

        // Trigger different notifs depending on what assessment type was saved
        $assessment = $plan->getAssessment();

        // Quiz and due work will trigger notification.
        if (!$assessment->getQuiz() && !$assessment->getDueWork()) {
            return;
        }

        $method = $bundle->getMethod();

        // $diff has 'assessment', 'plan', 'participants' as its keys
        $diff = $bundle->getEvent()->getDiff();

        if ($plan->isDynamic() && isset($diff['assessment'])) {
            $planDiff = $diff['assessment'];
        } elseif (isset($diff['plan'][$plan->getId()])) {
            $planDiff = $diff['plan'][$plan->getId()];
        } else {
            $planDiff = [];
        }

        /**
         * Deleted
         */
        if ($method === Mapper::METHOD_DELETE) {
            $bundle->detachEach([
                self::TRIGGER_OPEN,
                self::TRIGGER_UPCOMING,
                self::TRIGGER_UPDATED,
                self::TRIGGER_OVERDUE,
                self::TRIGGER_CLOSED,
            ]);
            // trigger to retract existing notifications
            $bundle->attach(self::TRIGGER_DELETED, $now);
            return;
        }

        /**
         * No Change
         */
        if ($diff === null || count($diff) === 0) {
            return;
        }

        /**
         * Newly created assessment is isStaffOrInstructorVisibleOnly or closed
         */
        $isCurrentHidden = false;
        try {
            $isCurrentHidden = $assessment->getWorkType() === null ? false :
                $this->workTypeMapper->findById($assessment->getWorkType()->getId())->isStaffOrInstructorVisibleOnly();
        } catch (BaseMapper\Exception $e) {
            // noop;
        }
        if ($method === Mapper::METHOD_CREATE && $isCurrentHidden) {
            return;
        }
        $closeDate = $this->coalesceDates($plan->getCloseDate(), $assessment->getCloseDate());
        $isCurrentClosed = $closeDate && $closeDate->isPast();
        if ($method === Mapper::METHOD_CREATE && $isCurrentClosed) {
            return;
        }

        /**
         * Changed to isStaffOrInstructorVisibleOnly
         */
        $isPreviousHidden = false;
        if (
            $method === Mapper::METHOD_UPDATE
            && array_key_exists('assessment', $diff)
            && array_key_exists('workType', $diff['assessment'])
        ) {
            try {
                $isPreviousHidden = $diff['assessment']['workType'][1] === null ? false :
                    $this->workTypeMapper->findById($diff['assessment']['workType'][1])->isStaffOrInstructorVisibleOnly();
                if ($isPreviousHidden && $isCurrentHidden) {
                    // no notification
                    return;
                }
            } catch (BaseMapper\Exception $e) {
                // noop;
            }
        }

        if (!$isCurrentClosed && !$isPreviousHidden && $isCurrentHidden) {
            $bundle->detachEach([
                self::TRIGGER_OPEN,
                self::TRIGGER_UPCOMING,
                self::TRIGGER_UPDATED,
                self::TRIGGER_OVERDUE,
                self::TRIGGER_CLOSED,
            ]);
            // trigger to retract existing notifications
            $bundle->attach(self::TRIGGER_DELETED, $now);
            return;
        }

        /**
         * Get dates.
         */
        // Open is now always bundled, as long as it is set
        // However, if its open date is in the past don't bundle if this is an update
        $openDate = $this->coalesceDates($plan->getOpenDate(), $assessment->getOpenDate());
        // Due and upcoming: always bundled as long as due date is set
        $dueDate = $this->coalesceDates($plan->getDueDate(), $assessment->getDueDate());
        if (!$openDate || !$dueDate) {
            // this date shouldn't be null
            return;
        }

        $triggerOpenTime = $openDate->isPast() ? $now : $openDate->getUnixTimeStamp();

        // Quiz and Due Work have different logic for when notification happens
        // Quiz: 2 hours before
        // Due Work: 2 days before <- ??
        // if the upcoming date is before the open date, push upcoming to 1 minute after open,
        // rounded to the nearest 5 minutes?
        $upcomingDate = null;
        if ($assessment->getQuiz()) {
            $upcomingDate = (clone $dueDate)->addHours(-self::QUIZ_UPCOMING_WINDOW);
        } elseif ($assessment->getDueWork()) {
            $upcomingDate = (clone $dueDate)->addHours(-self::DUEWORK_UPCOMING_WINDOW);
        } else {
            // this shouldn't happen
            return;
        }
        if ($upcomingDate->lt($openDate)) {
            $upcomingDate = (clone $openDate)->addMinutes(1);
        }

        /**
         * Trigger.
         */
        if (!$isCurrentClosed && ($method === Mapper::METHOD_CREATE || ($isPreviousHidden && !$isCurrentHidden))) {
            /**
             * New or just switched from is isStaffOrInstructorVisibleOnly to visible status
             */
            $bundle->attach(self::TRIGGER_OPEN, $triggerOpenTime);
            $bundle->attach(self::TRIGGER_UPCOMING, $upcomingDate->getUnixTimeStamp());
            $bundle->attach(self::TRIGGER_OVERDUE, $dueDate->getUnixTimeStamp());
            $bundle->attach(self::TRIGGER_CLOSED, $closeDate->getUnixTimeStamp());
        } elseif ($this->dateChanged($planDiff, self::DATE_CLOSE, self::FUTURE_TO_PAST)) {
            /**
             * Manually closed.
             */
            $bundle->detachEach([
                self::TRIGGER_OPEN,
                self::TRIGGER_UPCOMING,
                self::TRIGGER_UPDATED,
                self::TRIGGER_OVERDUE,
            ]);
            $bundle->attach(self::TRIGGER_CLOSED, $now);
        } elseif ($this->dateChanged($planDiff, self::DATE_CLOSE, self::PAST_TO_FUTURE)) {
            /**
             * Re-opened.
             */
            $bundle->attach(self::TRIGGER_OPEN, $triggerOpenTime);
            $bundle->attach(self::TRIGGER_UPCOMING, $upcomingDate->getUnixTimeStamp());
            if ($dueDate->isFuture() && $this->dateChanged($planDiff, self::DATE_DUE, self::ANY)) {
                $bundle->attach(self::TRIGGER_OVERDUE, $dueDate->getUnixTimeStamp());
            }
            $bundle->attach(self::TRIGGER_CLOSED, $closeDate->getUnixTimeStamp());
        } else {
            /**
             * Open date changed.
             * Ignoring publishDate as handling openDate should be enough.
             */
            if ($this->dateChanged($planDiff, self::DATE_OPEN, self::FUTURE_TO_PAST)) {
                $bundle->detach(self::TRIGGER_OPEN);
            } elseif ($openDate->isFuture() && $this->dateChanged($planDiff, self::DATE_OPEN, self::ANY)) {
                $bundle->attach(self::TRIGGER_OPEN, $triggerOpenTime);
            }
            /**
             * Due date changed.
             */
            if ($this->dateChanged($planDiff, self::DATE_DUE, self::FUTURE_TO_PAST)) {
                $bundle->detach(self::TRIGGER_OVERDUE);
                $bundle->detach(self::TRIGGER_UPCOMING);
            } elseif ($dueDate->isFuture() && $this->dateChanged($planDiff, self::DATE_DUE, self::ANY)) {
                $bundle->attach(self::TRIGGER_OVERDUE, $dueDate->getUnixTimeStamp());
                $bundle->attach(self::TRIGGER_UPCOMING, $upcomingDate->getUnixTimeStamp());
            }
            if ($openDate->isPast()) {
                $bundle->attach(self::TRIGGER_UPDATED, $now);
            }
            /**
             * Close date changed.
             */
            if ($this->dateChanged($planDiff, self::DATE_CLOSE, self::ANY)) {
                $bundle->attach(self::TRIGGER_CLOSED, $closeDate->getUnixTimeStamp());
            }
        }
    }

    /**
     * @inheritdoc
     * @throws BaseMapper\Exception
     */
    public function getFeed($context, User $user, $limit = self::HARD_LIMIT, $offset = 0)
    {
        // if parents aren't allowed to see due work, return an empty collection
        if ($user->getRole()->isParent() && !$this->isParentTargetable()) {
            return new Collection();
        }

        // Forgive my usage of getInstance, or don't;
        // it's going to happen regardless.
        $now     = Alaress_Date_Format::now();
        $access  = Access::getInstance();
        $mutator = new Participant\AssessmentListMutator(
            $user,
            new Visibility($access),
            new ExternalUser(),
            $now,
            true
        );

        // Write-users see all participants, others see only themselves
        $contextUser = $user;
        if ($access->getFolderAccess($user, (int)$context) >= UserAccess::ACCESS_WRITE) {
            $contextUser = null;
        }

        $assessments = $this->assessmentDataSource->findAllByFolderIdForReview($context, $contextUser)
            ->map([$mutator, 'map'])
            ->filter([$mutator, 'filter']);

        $notifsForPlans = [];
        foreach ($assessments as $assessment) {
            // Go thru each plan and raise in THAT context
            foreach ($assessment->getPlans() as $domainModelPlan) {
                $openDate = $this->coalesceDates($domainModelPlan->getOpenDate(), $assessment->getOpenAt());
                $dueDate  = $this->coalesceDates($domainModelPlan->getDueDate(), $assessment->getDueAt());

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
                $dataModelPlan->setParticipants(new GenericCollection(\Schoolbox\Assessment\Participant\Model::class));
                foreach ($domainModelPlan->getParticipants() as $participant) {
                    $dataModelParticipant = ParticipantMapper::getInstance()->createModel($participant->getId());
                    $dataModelParticipant
                        ->setUser($participant->getUser())
                        ->setExcludedAt($participant->getExcludedAt())
                    ;
                    $dataModelPlan->getParticipants()->setOffset($participant->getUser()->getId(), $dataModelParticipant);
                }

                $key = "{$assessment->getId()}.{$domainModelPlan->getId()}";
                $notifsForPlans["$key.open"] = $this
                    ->internalNotifyOpen($assessment, $dataModelPlan, true)
                    ->getAll($openDate?->getUnixTimeStamp());
                $notifsForPlans["$key.closed"] = $this
                    ->internalNotifyClosed($assessment, $dataModelPlan, true)
                    ->getAll($dueDate?->getUnixTimeStamp());
            }
        }


        // Determine which notifications are actually relevant to $user.
        // This includes:
        // * targeted directly to $user
        // * targeted to one of $user's children
        // * targeted to a folder which $user is a part of
        $childIds = [];
        foreach ($user->getChildren() as $child) {
            $childIds[$child->getId()] = true;
        }

        $ts     = $now->getUnixTimeStamp();
        $filter = function (Instance $item, $key) use ($user, $access, $childIds, $ts) {

            // For some reason I get notifications for future events?
            // Filter those out.
            if ($item->getTime() > $ts) {
                return false;
            }

            // notify.user.$userId: am I this user?
            if (preg_match('/^notify\\.user\\.(?P<userId>\\d+)/', $key, $matches)) {
                $userId = (int)$matches['userId'];
                return $userId === $user->getId();
            }

            // notify.parent.$userId: am I a parent of this user?
            if (preg_match('/^notify\\.parent\\.(?P<childId>\\d+)/', $key, $matches)) {
                $childId = (int)$matches['childId'];
                return $childIds[$childId] ?? false;
            }

            // notify.service.1.$folderId.$level: do I have $access to $folderId at $level?
            if (preg_match('/^notify\\.service\\.1\\.(?P<folderId>\\d+).(?P<level>\\d+)/', $key, $matches)) {
                $folderId = (int)$matches['folderId'];
                $level    = (int)$matches['level'];
                return $access->getFolderAccess($user, $folderId) >= $level;
            }

            // None of the above: filter out
            return false;
        };

        $c = new Collection();
        foreach ($notifsForPlans as $notifsForPlan) {
            foreach ($notifsForPlan as $key => $item) {
                if ($filter($item, $key)) {
                    $c->add($item);
                }
            }
        }
        return $c->sortBy('time', 'desc')
                 ->slice($offset, $limit);
    }


    /**
     * Participant gets notified when an assessment opens.
     *
     * This encapsulates both quiz and duework open notifier, as they're
     * identical apart from topic.
     *
     * @param Model $plan
     * @return Envelope
     * @throws DeletedException
     */
    public function notifyOpen(Model $plan): Envelope
    {
        return $this->internalNotifyOpen($this->retrieveAssessmentDomainModel($plan), $plan, false);
    }


    /**
     * Internal method for notifying on open, implemented so that feed can use
     * it too.
     *
     * @param IAssessable $assessment
     * @param Model       $plan
     * @param bool        $forFeed Is this for the feed? If so, notify parents and staff
     * @return Envelope
     */
    private function internalNotifyOpen(IAssessable $assessment, Model $plan, bool $forFeed): Envelope
    {
        $envelope = new Envelope();
        if (!$this->isNotifiableAtAll($assessment, self::TRIGGER_OPEN)) {
            return $envelope;
        }

        if ($assessment instanceof DueWork) {
            $topicSuffix = "open";
        } elseif ($assessment instanceof Quiz) {
            $topicSuffix = "upcoming";
        } else {
            return $envelope;
        }

        $topicStub = $this->retrieveAssessmentTopicStub($assessment);
        if (!$topicStub) {
            return $envelope;
        }

        $boxType = $assessment->getTypeName();

        // Build generic builder
        $bob = $this->buildGenericBuilder($assessment)
                    ->addTopic("$topicStub.$topicSuffix")
                    ->setSeverity(Logger::NOTICE);

        // Customise and send to every participant in this plan only
        $participantCount = 0;
        foreach ($this->retrieveParticipantsFromPlan($plan, $assessment) as $participant) {
            if (!$this->isParticipantNotifiable($participant, self::TRIGGER_OPEN)) {
                continue;
            }

            $participantCount++;

            // build for student
            $this->internalNotifyStudentOpen($envelope, $participant, $topicStub);

            // For feed only: build for parent
            if (!($forFeed && $this->isParentTargetable())) {
                continue;
            }
            $bob = $this->targetBuilderToParents($bob, $assessment, $participant)
                        ->setTitle(self::PATTERN_OPEN_PARENT_TITLE)
                        ->setParam('boxType', new SimpleParam($boxType));
            $envelope->targetToParent($participant->getUser(), $bob->build());
        }

        // For feed only: build for staff
        if ($forFeed && $participantCount > 0) {
            $title = sprintf(
                ':assessment has opened for %d student%s',
                $participantCount,
                $participantCount === 1 ? '' : 's'
            );
            $href = $this->buildAssessmentRoute($assessment, $plan);
            $bob  = $this->buildGenericBuilder($assessment)
                         ->addTopic("$topicStub.$topicSuffix")
                         ->setSeverity(Logger::NOTICE)
                         ->setTitle($title)
                         ->setHref($href)
                         ->unsetParam('cat')
                         ->setParam('assessment', new Param($assessment->getTitle(), $href))
                         ->setSeverity(Logger::ERROR);

            // Dedupe messages for plans
            if (!$plan->isDynamic()) {
                $bob->setTitle($title . ' in :plan')
                    ->setParam('plan', new Param($plan->getName()));
            }

            $envelope->broadcastToCategory(
                $assessment->getBaseContext()->getFolder(),
                UserAccess::ACCESS_WRITE,
                $bob->build()
            );
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
    private function internalNotifyStudentOpen(Envelope $envelope, Participant $participant, string $topicStub): void
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
                    ->setTitle(self::PATTERN_OPEN_STUDENT_TITLE)
                    ->setParam('boxType', new SimpleParam($boxType))
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
     * @throws DeletedException
     */
    public function notifyUpcoming(Model $plan): Envelope
    {
        $assessment = $this->retrieveAssessmentDomainModel($plan);
        $envelope   = new Envelope();
        if (!$this->isNotifiableAtAll($assessment, self::TRIGGER_UPCOMING)) {
            return $envelope;
        }

        if ($assessment instanceof DueWork) {
            $verb = 'submit';
        } elseif ($assessment instanceof Quiz) {
            $verb = 'attempt';
        } else {
            return $envelope;
        }
        $topicStub  = $this->retrieveAssessmentTopicStub($assessment);
        if (!$topicStub) {
            return $envelope;
        }

        $bob = $this->buildGenericBuilder($assessment)
                    ->setSeverity(Logger::WARNING)
                    ->addTopic("$topicStub.upcoming")
                    ->setParam('verb', new SimpleParam($verb));

        // Customise and send to every participant in this plan only
        foreach ($this->retrieveParticipantsFromPlan($plan, $assessment) as $participant) {
            if (!$this->isParticipantNotifiable($participant, self::TRIGGER_UPCOMING)) {
                continue;
            }

            // Title varies for due work and quiz
            $when = $participant->getDueDate()->getRelativeTime(Alaress_Date_Format::now()->addMinutes(-5), true);

            // build for student
            $bob = $this->targetBuilderToStudent($bob, $assessment, $participant)
                        ->setTitle(self::PATTERN_UPCOMING_STUDENT_TITLE)
                        ->setParam('when', new Param($when))
                        ->setBody($participant->getDescription());
            $envelope->targetToUser($participant->getUser(), $bob->build());

            // build for parent, only if they're allowed
            if (!$this->isParentTargetable()) {
                continue;
            }
            $bob = $this->targetBuilderToParents($bob, $assessment, $participant)
                        ->setTitle(self::PATTERN_UPCOMING_PARENT_TITLE)
                        ->setParam('when', new Param($when));
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
     * @throws DeletedException
     */
    public function notifyOverdue(Model $plan): Envelope
    {
        $envelope   = new Envelope();
        $assessment = $this->retrieveAssessmentDomainModel($plan);
        if (!$this->isNotifiableAtAll($assessment, self::TRIGGER_OVERDUE)) {
            return $envelope;
        }

        // TODO figure out if overdue should really be different for quiz and duework
        if ($assessment instanceof DueWork) {
            $overdue = 'is now overdue';
            $submit = 'submit work for';
        } elseif ($assessment instanceof Quiz) {
            $overdue = 'has closed';
            $submit = 'attempt';
        } else {
            return $envelope;
        }

        $topicStub  = $this->retrieveAssessmentTopicStub($assessment);
        if (!$topicStub) {
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

            // build for student
            $bob = $this->targetBuilderToStudent($bob, $assessment, $participant)
                        ->setTitle(self::PATTERN_OVERDUE_STUDENT_TITLE)
                        ->setParam('overdue', new SimpleParam($overdue))
                        ->unsetParam('submit')
                        ->setBody($participant->getDescription());
            $envelope->targetToUser($participant->getUser(), $bob->build());

            // build for parent, only if they're allowed
            if (!$this->isParentTargetable()) {
                continue;
            }
            $bob = $this->targetBuilderToParents($bob, $assessment, $participant)
                        ->setTitle(self::PATTERN_OVERDUE_PARENT_TITLE)
                        ->setParam('submit', new SimpleParam($submit))
                        ->unsetParam('overdue');
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
     * @throws DeletedException
     */
    public function notifyClosed(Model $plan): Envelope
    {
        return $this->internalNotifyClosed($this->retrieveAssessmentDomainModel($plan), $plan, false);
    }


    /**
     * Internal method for notifying on close, implemented so that feed can use
     * it too.
     *
     * @param IAssessable $assessment
     * @param Model       $plan
     * @param bool        $forFeed Is this for the feed? If so, notify parents and students
     * @return Envelope
     */
    private function internalNotifyClosed(IAssessable $assessment, Model $plan, bool $forFeed): Envelope
    {
        $envelope  = new Envelope();
        if (!$this->isNotifiableAtAll($assessment, self::TRIGGER_CLOSED)) {
            return $envelope;
        }
        $topicStub = $this->retrieveAssessmentTopicStub($assessment);
        if (!$topicStub) {
            return $envelope;
        }

        $bob = $this->buildGenericBuilder($assessment)
                    ->setSeverity(Logger::ERROR)
                    ->addTopic("$topicStub.closed");

        // Figure out how many need to submit
        $totalUnsubmitted = 0;
        $allowsResponse   = ($assessment instanceof DueWork && !$assessment->isAcceptNone());
        foreach ($this->retrieveParticipantsFromPlan($plan, $assessment) as $participant) {
            if (!$this->isParticipantNotifiable($participant, self::TRIGGER_CLOSED)) {
                continue;
            }

            // Is this a due work which doesn't accept online?
            // Don't count $participant as unsubmitted
            if ($allowsResponse && $participant->isResponseRequired()) {
                $totalUnsubmitted++;
            }

            // Feed only
            if ($forFeed) {
                // build for student
                $bob = $this->targetBuilderToStudent($bob, $assessment, $participant)
                            ->setTitle(":assessment in :cat has closed");
                $envelope->targetToUser($participant->getUser(), $bob->build());

                // build for parent, only if they're allowed
                if (!$this->isParentTargetable()) {
                    continue;
                }
                $bob = $this->targetBuilderToParents($bob, $assessment, $participant)
                            ->setTitle(":assessment in :cat has closed for :child");
                $envelope->targetToParent($participant->getUser(), $bob->build());
            }
        }

        // build for staff
        $href = $this->buildAssessmentRoute($assessment, $plan);
        $bob  = $this->buildGenericBuilder($assessment)
                     ->setHref($href)
                     ->setParam('assessment', new Param($assessment->getTitle(), $href))
                     ->setSeverity(Logger::ERROR)
                     ->addTopic("$topicStub.closed");

        // Build a title depending on plan
        $title = ':assessment';

        // :cat is irrelevant for feed
        if ($forFeed) {
            $bob->unsetParam('cat');
        } else {
            $title .= ' in :cat';
        }

        $title .= ' has closed';

        // Mention the plan if it's not the default
        if (!$plan->isDynamic()) {
            $title .= ' for plan :plan';
            $bob->setParam('plan', new Param($plan->getName()));
        }

        // How many students didn't submit?
        if ($totalUnsubmitted > 0) {
            $title .= sprintf(
                " and %d student%s did not submit work",
                $totalUnsubmitted,
                $totalUnsubmitted === 1 ? '' : 's'
            );
        }

        // Broadcast
        $envelope->broadcastToCategory(
            $assessment->getBaseContext()->getFolder(),
            UserAccess::ACCESS_WRITE,
            $bob->setTitle($title)->build()
        );

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
     * @throws DeletedException
     * @throws BaseMapper\Exception
     */
    public function notifyUpdated(Model $plan, User $effector, array $diff = null): Envelope
    {
        $assessment = $this->retrieveAssessmentDomainModel($plan);
        $envelope   = new Envelope();
        if (!$this->isNotifiableAtAll($assessment, self::TRIGGER_UPDATED)) {
            return $envelope;
        }

        $topicStub  = $this->retrieveAssessmentTopicStub($assessment);
        if (!$topicStub) {
            return $envelope;
        }

        // Build a box type
        $boxType       = $assessment->getTypeName();
        $parentBoxType = mb_strtolower($boxType);

        // Figure out which mapper to use
        $feedbackMapper = null;
        if ($assessment instanceof DueWork) {
            $feedbackMapper = $this->dueWorkFeedbackMapper;
        } elseif ($assessment instanceof Quiz) {
            $feedbackMapper = $this->quizFeedbackMapper;
        }

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
                        // and raise a notification against it
                        if ($feedbackMapper instanceof BaseMapper) {
                            $this->raiseNotification(
                                $authoritativeFeedback->getReviewer(),
                                BaseMapper::METHOD_UPDATE,
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
                               ->setTitle(self::PATTERN_RENAMED_STUDENT_TITLE)
                               ->setParam('oldname', new Param($oldName))
                               ->setParam('boxType', new SimpleParam($boxType))
                               ->setTopics(["$topicStub.$suffix"]);
                $envelope->targetToUser($participant->getUser(), $bob->build());

                // Parents
                if ($this->isParentTargetable()) {
                    $bob = $this->targetBuilderToParents($bob, $assessment, $participant)
                                ->setTitle(self::PATTERN_RENAMED_PARENT_TITLE)
                                ->setParam('boxType', new SimpleParam($parentBoxType));
                    $envelope->targetToParent($participant->getUser(), $bob->build());
                }

                $bob->unsetParam('oldname');
                $bob->unsetParam('boxType');
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
                        $this->internalNotifyStudentOpen($envelope, $participant, $topicStub);
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
                        $dateformat = $participant->getDueDate()->getTimeOnly() . ' on ' . $dateformat;
                    }

                    // Students
                    // Topic depends on type of assessment
                    $suffix = $assessment instanceof Quiz ? 'updated' : 'datechanged';
                    $bob    = $this->targetBuilderToStudent($bob, $assessment, $participant)
                                   ->setTitle(self::PATTERN_DATE_CHANGED_STUDENT_TITLE)
                                   ->setParam('date', new Param($dateformat))
                                   ->setParam('boxType', new Param($boxType))
                                   ->setParam('verb', new Param($verb, null, Param::STACKING_APPEND))
                                   ->setTopics(["$topicStub.$suffix"]);
                    $envelope->targetToUser($participant->getUser(), $bob->build());

                    // Parents
                    if ($this->isParentTargetable()) {
                        $bob = $this->targetBuilderToParents($bob, $assessment, $participant)
                                    ->setTitle(self::PATTERN_DATE_CHANGED_PARENT_TITLE)
                                    ->setParam('boxType', new Param($parentBoxType))
                                    ->setParam('verb', new Param($verb, null, Param::STACKING_APPEND));
                        $envelope->targetToParent($participant->getUser(), $bob->build());
                    }

                    $bob->unsetParam('date');
                    $bob->unsetParam('verb');
                    $bob->unsetParam('boxType');
                }
            }


            // accept online
            if ($assessment instanceof DueWork && !empty($assessmentDiff['acceptOnline'])) {
                // Students
                $m = $assessment->isAcceptNone()
                    ? self::PATTERN_NO_SUBMIT_STUDENT_TITLE
                    : self::PATTERN_ACCEPT_ONLINE_STUDENT_TITLE;
                $bob = $this->targetBuilderToStudent($bob, $assessment, $participant)
                            ->setTitle($m)
                            ->setTopics(["$topicStub.updated"]);
                $envelope->targetToUser($participant->getUser(), $bob->build());

                // Parents
                if ($this->isParentTargetable()) {
                    $m   = $assessment->isAcceptNone()
                        ? self::PATTERN_NO_SUBMIT_PARENT_TITLE
                        : self::PATTERN_ACCEPT_ONLINE_PARENT_TITLE;
                    $bob = $this->targetBuilderToParents($bob, $assessment, $participant)
                                ->setTitle($m);
                    $envelope->targetToParent($participant->getUser(), $bob->build());
                }
            }
            unset($assessmentDiff['acceptOnline']);


            // generic: only if there's something left in the diff.
            // Not sure if there's any point to this notif, but raise it anyway
            if (array_intersect(array_keys($assessmentDiff), ['type', 'weight', 'workType'])) {
                // Students
                $bob = $this->targetBuilderToStudent($bob, $assessment, $participant)
                            ->setTitle(self::PATTERN_UPDATE_STUDENT_TITLE)
                            ->setParam('boxType', new Param($boxType))
                            ->setTopics(["$topicStub.updated"]);
                $envelope->targetToUser($participant->getUser(), $bob->build());

                // Parents
                if ($this->isParentTargetable()) {
                    $bob = $this->targetBuilderToParents($bob, $assessment, $participant)
                                ->setTitle(self::PATTERN_UPDATE_PARENT_TITLE)
                                ->setParam('boxType', new Param($parentBoxType));
                    $envelope->targetToParent($participant->getUser(), $bob->build());
                }
                $bob->unsetParam('boxType');
            }
        }

        return $envelope;
    }

    /**
     * Retracts a deleted/hidden plan.
     *
     * @param Model $plan
     * @return Envelope
     */
    public function notifyDeleted(Model $plan): Envelope
    {
        $envelope = new Envelope();
        return $this->retractEventObject($envelope, $plan);
    }

    /**
     * Convenience helper to build a route to the status page.
     *
     * @param IAssessable $assessment
     * @param Model       $plan       Required for deduping notifications
     * @return string
     */
    private function buildAssessmentRoute(IAssessable $assessment, Model $plan): string
    {
        return sprintf('/learning/assessments/%s/status#plan-%d', $assessment->getId(), $plan->getId());
    }

    /**
     * Convenience helper to build a route to the participant's summary page.
     *
     * @param IAssessable $assessment
     * @param User        $student
     * @return string
     */
    private function buildParticipantRoute(IAssessable $assessment, User $student): string
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
     * @param WorkTypeMapper $workTypeMapper
     * @return static
     */
    protected function setWorkTypeMapper(WorkTypeMapper $workTypeMapper): self
    {
        $this->workTypeMapper = $workTypeMapper;
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
    private function coalesceDates(?Alaress_Date_Format ...$dates): ?Alaress_Date_Format
    {
        foreach ($dates as $date) {
            if ($date instanceof Alaress_Date_Format) {
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
     * @throws DeletedException if item I'm getting notifs for is deleted
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
        try {
            $dataModel = $this->assessmentMapper->findById($assessment->getId());
        } catch (BaseMapper\Exception\Deleted $e) {
            $dataModel = $e->getDeletedObject();
        } catch (BaseMapper\Exception $e) {
            return null;
        }
        /** @var \Schoolbox\Learning\Assessment\DataModel\Model $dataModel */
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
        $icon   = "/images/icon.php?" . http_build_query(['size' => $this->iconSize, 'icon' => NSMapper::NS_DUEWORK]);

        // Namespace depends on kind of assessment
        $namespace = null;
        if ($assessment instanceof DueWork) {
            $namespace = NSMapper::NS_DUEWORK;
        } elseif ($assessment instanceof Quiz) {
            $namespace = NSMapper::NS_ONLINE_ASSESS;
        }

        $bob->setParam('cat', new SimpleParam($folder->getName()))
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
            ->setParam('child', new ChildParam($participant->getUser()))
            ->setStudentExternalIdMeta($participant->getUser()->getExternalId())
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
        // If the assessment is visible to staff only, do not trigger notification at all even to staff,
        // since notifications for staff here are to notify them participation status of students.
        if ($assessment->isStaffOrInstructorVisibleOnly()) {
            return false;
        }

        switch ($label) {
            case self::TRIGGER_OPEN:
            case self::TRIGGER_UPDATED:
            case self::TRIGGER_CLOSED:
                // Open, updated and closed notifs are always sent
                return true;

            case self::TRIGGER_UPCOMING:
            case self::TRIGGER_OVERDUE:
            default:
                // All other notifs are not sent for due works that don't
                // accept online, because action isn't necessary for them.
                // It is possible, but not mandatory, for students to
                // acknowledge response, though.
                return !($assessment instanceof DueWork && $assessment->isAcceptNone());
        }
    }


    /**
     * Is this participant notifiable for this label?
     *
     * @param Participant $participant
     * @param string      $label
     * @return bool
     */
    private function isParticipantNotifiable(Participant $participant, string $label): bool
    {
        switch ($label) {
            case self::TRIGGER_OPEN:
            case self::TRIGGER_CLOSED:
                // Open and closed notifs are always sent to non-excluded
                return !$participant->isExcluded();

            case self::TRIGGER_UPDATED:
                // Updated: send if not excluded and isn't open yet
                return !$participant->isExcluded() && !$participant->getOpenDate()->isFuture();

            case self::TRIGGER_UPCOMING:
            case self::TRIGGER_OVERDUE:
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


    /**
     * Check whether the given date has been changed in the given way.
     * @param array $planDiff
     * @param string $dateType
     * @param int $transition
     * @return bool
     */
    private function dateChanged(array $planDiff, string $dateType, int $transition): bool
    {
        if (!isset($planDiff[$dateType])) {
            return false;
        }

        $current = new Alaress_Date_Format($planDiff[$dateType][0], Alaress_Date_Format::DATABASE_FORMAT);
        $previous = new Alaress_Date_Format($planDiff[$dateType][1], Alaress_Date_Format::DATABASE_FORMAT);

        switch ($transition) {
            case self::ANY:
                return !$previous->eq($current);
            case self::PAST_TO_FUTURE:
                return $previous->isPast() && $current->isFuture();
            case self::FUTURE_TO_PAST:
                return $previous->isFuture() && $current->isPast();
            default:
                return false;
        }
    }
}
