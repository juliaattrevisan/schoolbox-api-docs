<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Participant\Plan;

use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Instance\Instance;
use Alaress\Async\Notification\Parameter\Param;
use Alaress\Async\Notification\Parameter\ParameterInterface;
use Alaress_Date_Format;
use Monolog\Logger;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper as DueWorkFeedbackMapper;
use Schoolbox\Collection;
use Schoolbox\Feed\FeedableMapperInterface;
use Schoolbox\Feed\SourceInterface;
use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\Quiz\Attempt\Mapper as QuizFeedbackMapper;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Notification\Parameter\CategoryParam;
use Schoolbox\Realtime\Notification\Parameter\ChildParam;
use Schoolbox\Realtime\Notification\Parameter\UserParam;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundleInterface;
use Schoolbox\Role;
use Schoolbox\User;

/**
 * Class Notifier
 */
class Notifier extends AbstractModuleNotifier implements SourceInterface
{
    private const TRIGGER_OPEN     = 'open';
    private const TRIGGER_UPDATED  = 'updated';
    private const TRIGGER_UPCOMING = 'upcoming';
    private const TRIGGER_OVERDUE  = 'overdue';
    private const TRIGGER_CLOSED   = 'closed';

    /**
     * Days in advance to inform a user that due work is upcoming.
     *
     * TODO make this configurable
     */
    private const DUEWORK_UPCOMING_WINDOW = 2;

    /**
     * *Hours* in advance to inform a user that quiz is upcoming.
     *
     * TODO make this configurable separately from DUEWORK_UPCOMING_WINDOW
     */
    private const QUIZ_UPCOMING_WINDOW = 2;

    /**
     * @var AssessmentMapper
     */
    private $assessmentMapper;

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
     */
    public function loadDependencies($dependencies = null): void
    {
        if (empty($dependencies['assessmentMapper'])) {
            $dependencies['assessmentMapper'] = AssessmentMapper::getInstance();
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
                        $upcomingDate = (clone $dueDate)->addDays(-self::DUEWORK_UPCOMING_WINDOW);
                    }
                    // if the upcoming date is before the open date, push upcoming to 1 minute after open,
                    // rounded to the nearest 5 minutes?
                    if ($upcomingDate) {
                        if ($upcomingDate->lt($openDate)) {
                            $upcomingDate = (clone $openDate)->addMinutes(1);
                        }
                        $bundle->attach(self::TRIGGER_UPCOMING, $upcomingDate->getUnixTimeStamp());
                    }
                    $bundle->attach(self::TRIGGER_OVERDUE, $dueDate->getUnixTimeStamp());
                }

                // Close: always bundled
                $bundle->attach(self::TRIGGER_CLOSED, $closeDate->getUnixTimeStamp());
            }

            // Generic updated trigger
            if ($method === Mapper::METHOD_UPDATE) {
                $bundle->attach(self::TRIGGER_UPDATED, self::IMMEDIATELY);
            }
        }
    }


    /**
     * Generate n Instances, from the perspective of casual observation (rather than targeted at a particular user).
     *
     * @param int    $context  - most likely a category ID or ignored
     * @param string $roletype - the roletype of the user for whom this feed is being retrieved
     * @param int    $limit    - how many messages to retrieve
     * @param int    $offset   - by default, most recent
     * @throws \OutOfBoundsException     If severity is not set
     * @throws \InvalidArgumentException If a required property was not set
     * @return Collection|Instance[]
     */
    public function getFeed($context, $roletype, $limit = self::HARD_LIMIT, $offset = 0)
    {
        $c = new Collection();

        // if parents aren't allowed to see due work, return an empty collection
        if ($roletype === Role::PARENT && !$this->isParentTargetable()) {
            return $c;
        }

        // be sure the target method exists
        // TODO implement FMI on participant or assessment
        if (!$this->getMapper() instanceof FeedableMapperInterface) {
            return $c;
        }

        $boxes = $this->getMapper()->findForFeed($context, $limit, $offset);

        foreach ($boxes as $box) {
            $c = $c
                ->merge(new Collection($this->notifyStudentOpen($box, $box->getOwner())->getAll($box->getOpenDate()->getUnixTimeStamp())))
                ->merge(new Collection($this->notifyFeedClosed($box, $box->getOwner())->getAll($box->getDueDate()->getUnixTimeStamp())));
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
     * @param User  $effector
     * @return Envelope
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    public function notifyOpen(Model $plan, User $effector): Envelope
    {
        $assessment = $this->retrieveAssessmentDomainModel($plan);
        $envelope   = new Envelope();
        if ($assessment instanceof DueWork) {
            $topicSuffix = "open";
        } elseif ($assessment instanceof Quiz) {
            $topicSuffix = "upcoming";
        }  else {
            return $envelope;
        }

        $topicStub = $this->retrieveAssessmentTopicStub($assessment);
        if (!$topicStub) {
            return $envelope;
        }
        if (!$this->isNotifiableAtAll($assessment, self::TRIGGER_OPEN)) {
            return $envelope;
        }

        // Build generic builder
        $workType     = $assessment->getWorkType();
        $boxType      = mb_strtolower($workType ? $workType->getName() : '');
        $indefArticle = $boxType ?
            in_array(strtolower($boxType[0]), ['a', 'e', 'i', 'o', 'u'], true) ? 'an' : 'a'
            : '';

        $bob = $this->buildGenericBuilder($assessment, $effector)
                    ->setTitle(":author opened $indefArticle $boxType :assessment in :cat")
                    ->setParam('author', new UserParam($effector, ParameterInterface::STACKING_REPLACE))
                    ->addTopic("$topicStub.$topicSuffix")
                    ->setSeverity(Logger::NOTICE);

        // Customise and send to every participant in this plan only
        foreach ($this->retrieveParticipantsFromPlan($plan, $assessment) as $participant) {
            if (!$this->isParticipantNotifiable($participant, self::TRIGGER_OPEN)) {
                continue;
            }

            // build for student
            $bob = $this->targetBuilderToStudent($bob, $assessment, $participant)
                        ->setBody($participant->getDescription())
                        ->setByline("Due {$participant->getDueDate()->getCalendarDateTime()}");
            $envelope->targetToUser($participant->getUser(), $bob->build());
        }

        return $envelope;
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
        $assessment = $this->retrieveAssessmentDomainModel($plan);
        $envelope   = new Envelope();
        $topicStub  = $this->retrieveAssessmentTopicStub($assessment);
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
                     ->setParam('box', new Param($assessment->getTitle(), $href))
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
        $workType = $assessment->getWorkType();
        $boxType  = mb_strtolower($workType ? $workType->getName() : '');

        // All updated notifs have the same icon, namespace, severity
        $bob = $this->buildGenericBuilder($assessment, $effector)
                    ->setSeverity(Logger::ERROR);

        // Figure out who's actually targeted
        foreach ($this->retrieveParticipantsFromPlan($plan, $assessment) as $participant) {
            if (!$this->isParticipantNotifiable($participant, self::TRIGGER_UPDATED)) {
                continue;
            }

            $notificationRaisedForParticipant = false;

            // INCREASE in mark visibility (box can be closed in this instance)
            // doesn't actually create messages, just triggers for others to be created
            if (isset($diff['assessment']['markVisibility'])) {
                [$new, $old] = $diff['assessment']['markVisibility'];
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
                            $notificationRaisedForParticipant = true;
                        }
                    }
                }
            }

            // Due date is passed: trigger no more
            if ($participant->getDueDate()->isPast()) {
                continue;
            }


            // renamed
            if (!empty($diff['assessment']['title'])) {
                [, $oldName] = $diff['assessment']['title'];

                // Students
                // TODO iterate particiapnts and students separately?
                $bob = $this->targetBuilderToStudent($bob, $assessment, $participant)
                            ->setTitle(':author renamed :oldname to :assessment in :cat')
                            ->setParam('author', new UserParam($effector))
                            ->setParam('oldname', new Param($oldName))
                            ->setTopics(["$topicStub.renamed"]);
                $envelope->targetToUser($participant->getUser(), $bob->build());

                // Parents
                if ($this->isParentTargetable()) {
                    $bob = $this->targetBuilderToParents($bob, $assessment, $participant)
                                ->setTitle(":child's $boxType was renamed from :oldname to :assessment in :cat")
                                ->unsetParam('author');
                    $envelope->targetToParent($participant->getUser(), $bob->build());
                }

                $notificationRaisedForParticipant = true;
            }

            // due date change
            if (!empty($diff['participants'][$participant->getUser()->getId()]['dueDate'])) {
                $oldDate = strtotime($diff['participants'][$participant->getUser()->getId()]['dueDate'][1]);
                $newDate = strtotime($diff['participants'][$participant->getUser()->getId()]['dueDate'][0]);
                $verb = $newDate > $oldDate ? 'extended' : 'changed';

                $dateformat = $participant->getDueDate()->getLongDateOnly();
                if (!$participant->getDueDate()->isAtEndOfDay()) {
                    $dateformat = $participant->getDueDate()->getTimeOnly() .' on '. $dateformat;
                }

                // Students
                $bob = $this->targetBuilderToStudent($bob, $assessment, $participant)
                            ->setTitle(":author $verb :assessment to :date in :cat")
                            ->setParam('author', new UserParam($effector))
                            ->setParam('date', new Param($dateformat))
                            ->setTopics(["$topicStub.datechanged"]);
                $envelope->targetToUser($participant->getUser(), $bob->build());

                // Parents
                if ($this->isParentTargetable()) {
                    $bob = $this->targetBuilderToParents($bob, $assessment, $participant)
                                ->setTitle(":child's $boxType :assessment closing date was $verb to :date in :cat")
                                ->unsetParam('author');
                    $envelope->targetToParent($participant->getUser(), $bob->build());
                }

                $notificationRaisedForParticipant = true;
            }

            // accept online
            if ($assessment instanceof DueWork && !empty($diff['assessment']['acceptOnline'])) {
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

                $notificationRaisedForParticipant = true;
            }

            // generic: only if no others raised
            // Not sure if there's any point to this notif, but raise it anyway
            if (!$notificationRaisedForParticipant) {
                // Students
                $bob = $this->targetBuilderToStudent($bob, $assessment, $participant)
                            ->setTitle(':author updated :assessment in :cat')
                            ->setParam('author', new UserParam($effector))
                            ->setTopics(["$topicStub.updated"]);
                $envelope->targetToUser($participant->getUser(), $bob->build());

                // Parents
                if ($this->isParentTargetable()) {
                    $bob = $this->targetBuilderToParents($bob, $assessment, $participant)
                                ->setTitle(":child's $boxType :assessment was updated in :cat")
                                ->unsetParam('author');
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
        /** @var MapperProxy $assessmentDataSource */
        $assessmentDataSource = $this->mapper::getDi()->get('assessmentDataSource');
        return $assessmentDataSource->findByIdForReview($plan->getAssessment()->getId());
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
     * @param null|User   $effector   The user whose portrait to show as the icon, or null for a generic icon
     * @return Builder
     */
    private function buildGenericBuilder(IAssessable $assessment, ?User $effector = null): Builder
    {
        $bob    = new Builder();
        $folder = $assessment->getBaseContext()->getFolder();
        $icon   = $effector
            ? "/portrait?" . http_build_query(['userId' => $effector->getExternalId(), 'size' => $this->iconSize,])
            : "/images/icon?" . http_build_query(['size' => $this->iconSize, 'icon' => 'work']);

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
