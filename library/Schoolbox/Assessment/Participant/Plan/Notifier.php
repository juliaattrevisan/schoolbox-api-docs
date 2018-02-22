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
use Schoolbox\Learning\Assessment\DataModel\Model as AssessmentModel;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
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
     * @var AssessmentMapper
     */
    private $assessmentMapper;

    /**
     * @var DueWorkFeedbackMapper
     */
    private $dueWorkFeedbackMapper;

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
        if (empty($dependencies['dueWorkFeedbackMapper'])) {
            $dependencies['dueWorkFeedbackMapper'] = DueWorkFeedbackMapper::getInstance();
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
        if ($assessment->getQuiz()) {
            // Proc quiz notifs to this participant and parents
            // May need to bundle these together with notifs to teachers that things are overdue
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
                    $upcomingDate = (clone $dueDate)->addDays(-self::DUEWORK_UPCOMING_WINDOW);
                    // if the upcoming date is before the open date, push upcoming to 1 minute after open,
                    // rounded to the nearest 5 minutes?
                    if ($upcomingDate->lt($openDate)) {
                        $upcomingDate = (clone $openDate)->addMinutes(1);
                    }
                    $bundle->attach(self::TRIGGER_UPCOMING, $upcomingDate->getUnixTimeStamp());
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
        if ($roletype === Role::PARENT && $this->conf->get('restrict_parent_duework_access')) {
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
     * Proxies to `notify{$type}Open`.
     *
     * @param Model $plan
     * @param User  $effector
     * @return Envelope
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    public function notifyOpen(Model $plan, User $effector): Envelope
    {
        $assessmentDomainObject = $this->retrieveAssessmentDomainModel($plan);
        if ($assessmentDomainObject instanceof DueWork) {
            return $this->notifyDueWorkOpen($assessmentDomainObject, $plan, $effector);
        }

        return new Envelope();
    }


    /**
     * Participant gets notified when a due work opens.
     *
     * Returns an array where the keys are the messages to send, and the values
     * are arrays of topic on which those messages are to be published.
     *
     * @param DueWork $dueWork
     * @param Model   $plan
     * @param User    $effector
     * @return Envelope
     */
    private function notifyDueWorkOpen(DueWork $dueWork, Model $plan, User $effector): Envelope
    {
        $envelope = new Envelope();

        // Still need the submission box's ID for generating the topic
        $boxId = $this->retrieveAssessmentDueWorkId($plan);
        if (!$boxId) {
            return $envelope;
        }

        // Build generic builder
        $workType     = $dueWork->getWorkType();
        $boxType      = mb_strtolower($workType ? $workType->getName() : '');
        $indefArticle = $boxType ?
            in_array(strtolower($boxType[0]), ['a', 'e', 'i', 'o', 'u'], true) ? 'an' : 'a'
            : '';

        $bob = $this->buildGenericBuilder($dueWork, $effector)
            ->setTitle(":author opened $indefArticle $boxType :box in :cat")
            ->setParam('author', new UserParam($effector, ParameterInterface::STACKING_REPLACE))
            ->addTopic("duework.{$boxId}.open")
            ->setSeverity(Logger::NOTICE);

        // Customise and send to every participant in this plan only
        foreach ($this->retrieveParticipantsFromPlan($plan, $dueWork) as $participant) {
            if ($participant->isExcluded()) {
                continue;
            }

            // build for student
            $bob = $this->targetDueWorkBuilderToStudent($bob, $dueWork, $participant)
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
        $assessmentDomainObject = $this->retrieveAssessmentDomainModel($plan);
        if ($assessmentDomainObject instanceof DueWork) {
            return $this->notifyDueWorkUpcoming($assessmentDomainObject, $plan);
        }

        return new Envelope();
    }


    /**
     * Participant and parents get notified when a due work is upcoming.
     *
     * @param DueWork $dueWork
     * @param Model   $plan
     * @return Envelope
     */
    private function notifyDueWorkUpcoming(DueWork $dueWork, Model $plan): Envelope
    {
        $envelope = new Envelope();

        // Still need the submission box's ID for generating the topic
        $boxId = $this->retrieveAssessmentDueWorkId($plan);
        if (!$boxId) {
            return $envelope;
        }

        // Doesn't accept online? return
        if ($dueWork->isAcceptNone()) {
            return $envelope;
        }

        $bob = $this->buildGenericBuilder($dueWork)
                    ->setSeverity(Logger::WARNING)
                    ->addTopic("duework.$boxId.upcoming");

        // Customise and send to every participant in this plan only
        foreach ($this->retrieveParticipantsFromPlan($plan, $dueWork) as $participant) {
            if ($participant->isExcluded()) {
                continue;
            }

            // Has participant already submitted?
            if (!$participant->isResponseRequired()) {
                continue;
            }

            $when = $participant->getDueDate()->getRelativeTime(Alaress_Date_Format::now()->addMinutes(-5), true);

            // build for student
            $bob = $this->targetDueWorkBuilderToStudent($bob, $dueWork, $participant)
                        ->setTitle(":box is due $when from now in :cat")
                        ->setBody($participant->getDescription());
            $envelope->targetToUser($participant->getUser(), $bob->build());

            // build for parent, only if they're allowed
            if ($this->conf->get('restrict_student_submissions')) {
                continue;
            }
            $bob = $this->targetDueWorkBuilderToParents($bob, $dueWork, $participant)
                        ->setTitle(":child has not yet submitted work for :box, which is due $when from now in :cat");
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
        $assessmentDomainObject = $this->retrieveAssessmentDomainModel($plan);
        if ($assessmentDomainObject instanceof DueWork) {
            return $this->notifyDueWorkOverdue($assessmentDomainObject, $plan);
        }

        return new Envelope();
    }


    /**
     * Participant and parents get notified when a due work is overdue.
     *
     * @param DueWork $dueWork
     * @param Model   $plan
     * @return Envelope
     */
    private function notifyDueWorkOverdue(DueWork $dueWork, Model $plan): Envelope
    {
        $envelope = new Envelope();

        // Still need the submission box's ID for generating the topic
        $boxId = $this->retrieveAssessmentDueWorkId($plan);
        if (!$boxId) {
            return $envelope;
        }

        // Doesn't accept online? return
        if ($dueWork->isAcceptNone()) {
            return $envelope;
        }

        $bob = $this->buildGenericBuilder($dueWork)
                    ->setSeverity(Logger::ERROR)
                    ->addTopic("duework.$boxId.overdue");

        // Customise and send to every participant in this plan only
        foreach ($this->retrieveParticipantsFromPlan($plan, $dueWork) as $participant) {
            if ($participant->isExcluded()) {
                continue;
            }

            // Has participant already submitted?
            if (!$participant->isResponseRequired()) {
                continue;
            }

            // build for student
            $bob = $this->targetDueWorkBuilderToStudent($bob, $dueWork, $participant)
                        ->setTitle(":box is now overdue in :cat")
                        ->setBody($participant->getDescription());
            $envelope->targetToUser($participant->getUser(), $bob->build());

            // build for parent, only if they're allowed
            if ($this->conf->get('restrict_student_submissions')) {
                continue;
            }
            $bob = $this->targetDueWorkBuilderToParents($bob, $dueWork, $participant)
                        ->setTitle(':child did not submit work for :box in :cat');
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
        $assessmentDomainObject = $this->retrieveAssessmentDomainModel($plan);
        if ($assessmentDomainObject instanceof DueWork) {
            return $this->notifyDueWorkClosed($assessmentDomainObject, $plan);
        }

        return new Envelope();
    }


    /**
     * Staff get notified when a due work closes and one or more students didn't submit.
     *
     * @param DueWork $dueWork
     * @param Model   $plan
     * @return Envelope
     */
    private function notifyDueWorkClosed(DueWork $dueWork, Model $plan): Envelope
    {
        $envelope = new Envelope();

        // Still need the submission box's ID for generating the topic
        $boxId = $this->retrieveAssessmentDueWorkId($plan);
        if (!$boxId) {
            return $envelope;
        }

        // Doesn't accept online? return
        if ($dueWork->isAcceptNone()) {
            return $envelope;
        }

        // Send a single notification for total # students not submitted
        $href = $this->buildAssessmentRoute($dueWork);
        $bob  = $this->buildGenericBuilder($dueWork)
                     ->setTitle(":box in :cat has closed and :number did not submit work")
                     ->setHref($href)
                     ->setParam('box', new Param($dueWork->getTitle(), $href))
                     ->setSeverity(Logger::ERROR)
                     ->addTopic("duework.$boxId.closed");

        // Figure out how many need to submit
        $totalUnsubmitted = 0;
        foreach ($this->retrieveParticipantsFromPlan($plan, $dueWork) as $participant) {
            if ($participant->isExcluded()) {
                continue;
            }
            if (!$participant->isResponseRequired()) {
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
                $dueWork->getBaseContext()->getFolder(),
                UserAccess::ACCESS_WRITE,
                $bob->build()
            );
        }

        return $envelope;
    }


    /**
     * Participant gets notified when an assessment is updated.
     *
     * Proxies to `notify{$type}Updated`.
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
        $assessmentDomainObject = $this->retrieveAssessmentDomainModel($plan);
        if ($assessmentDomainObject instanceof DueWork) {
            return $this->notifyDueWorkUpdated($assessmentDomainObject, $plan, $effector, $diff);
        }

        return new Envelope();
    }


    /**
     * Students and parents get notified when a due work is updated.
     *
     * The actual notification depends on what the diff is.
     *
     * @param DueWork    $dueWork
     * @param Model      $plan
     * @param User       $effector
     * @param array|null $diff
     * @return Envelope
     */
    private function notifyDueWorkUpdated(DueWork $dueWork, Model $plan, User $effector, array $diff = null): Envelope
    {
        $envelope = new Envelope();

        // Still need the submission box's ID for generating the topic
        $boxId = $this->retrieveAssessmentDueWorkId($plan);
        if (!$boxId) {
            return $envelope;
        }

        // Build a box type
        $workType = $dueWork->getWorkType();
        $boxType  = mb_strtolower($workType ? $workType->getName() : '');

        // All updated notifs have the same icon, namespace, severity
        $bob = $this->buildGenericBuilder($dueWork, $effector)
                    ->setSeverity(Logger::ERROR);

        // Figure out who's actually targeted
        foreach ($this->retrieveParticipantsFromPlan($plan, $dueWork) as $participant) {
            if ($participant->isExcluded()) {
                continue;
            }

            // Is this open for this student yet?
            if ($participant->getOpenDate()->isFuture()) {
                continue;
            }

            $notificationRaisedForParticipant = false;

            // INCREASE in mark visibility (box can be closed in this instance)
            // doesn't actually create messages, just triggers for others to be created
            if (isset($diff['assessment']['markVisibility'])) {
                [$new, $old] = $diff['assessment']['markVisibility'];
                if ($new > $old) {
                    // Trigger notification about this item
                    $authoritativeFeedback = $dueWork->getAuthoritativeFeedback($participant, true);
                    if ($authoritativeFeedback) {
                        $dueWorkFeedback = $this->dueWorkFeedbackMapper->findById($authoritativeFeedback->getId());
                        $this->raiseNotification(
                            $authoritativeFeedback->getReviewer(),
                            \Schoolbox\Mapper::METHOD_CREATE,
                            $dueWorkFeedback,
                            $this->dueWorkFeedbackMapper
                        );
                        $notificationRaisedForParticipant = true;
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
                $bob = $this->targetDueWorkBuilderToStudent($bob, $dueWork, $participant)
                            ->setTitle(':author renamed :oldname to :box in :cat')
                            ->setParam('author', new UserParam($effector))
                            ->setParam('oldname', new Param($oldName))
                            ->setTopics(["duework.{$boxId}.renamed"]);
                $envelope->targetToUser($participant->getUser(), $bob->build());

                // Parents
                $bob = $this->targetDueWorkBuilderToParents($bob, $dueWork, $participant)
                            ->setTitle(":child's $boxType was renamed from :oldname to :box in :cat")
                            ->unsetParam('author');
                $envelope->targetToParent($participant->getUser(), $bob->build());

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
                $bob = $this->targetDueWorkBuilderToStudent($bob, $dueWork, $participant)
                            ->setTitle(":author $verb :box to :date in :cat")
                            ->setParam('author', new UserParam($effector))
                            ->setParam('date', new Param($dateformat))
                            ->setTopics(["duework.{$boxId}.datechanged"]);
                $envelope->targetToUser($participant->getUser(), $bob->build());

                // Parents
                $bob = $this->targetDueWorkBuilderToParents($bob, $dueWork, $participant)
                            ->setTitle(":child's $boxType :box closing date was $verb to :date in :cat")
                            ->unsetParam('author');
                $envelope->targetToParent($participant->getUser(), $bob->build());

                $notificationRaisedForParticipant = true;
            }

            // accept online
            if (!empty($diff['assessment']['acceptOnline'])) {
                // Students
                $m = $dueWork->isAcceptNone()
                    ? 'You no longer need to submit online for :box in :cat'
                    : 'You now need to submit online for :box in :cat';
                $bob = $this->targetDueWorkBuilderToStudent($bob, $dueWork, $participant)
                            ->setTitle($m)
                            ->setTopics(["duework.{$boxId}.updated"]);
                $envelope->targetToUser($participant->getUser(), $bob->build());

                // Parents
                $m = $dueWork->isAcceptNone()
                    ? ':child no longer need to submit online for :box in :cat'
                    : ':child needs to submit online for :box in :cat';
                $bob = $this->targetDueWorkBuilderToParents($bob, $dueWork, $participant)
                            ->setTitle($m);
                $envelope->targetToParent($participant->getUser(), $bob->build());

                $notificationRaisedForParticipant = true;
            }

            // generic: only if no others raised
            // Not sure if there's any point to this notif, but raise it anyway
            if (!$notificationRaisedForParticipant) {
                // Students
                $bob = $this->targetDueWorkBuilderToStudent($bob, $dueWork, $participant)
                            ->setTitle(':author updated :box in :cat')
                            ->setParam('author', new UserParam($effector))
                            ->setTopics(["duework.{$boxId}.updated"]);
                $envelope->targetToUser($participant->getUser(), $bob->build());

                // Parents
                $bob = $this->targetDueWorkBuilderToParents($bob, $dueWork, $participant)
                            ->setTitle(":child's $boxType :box was updated in :cat")
                            ->unsetParam('author');
                $envelope->targetToParent($participant->getUser(), $bob->build());
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
    private function buildAssessmentRoute(IAssessable $assessment)
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
    private function buildParticipantRoute(IAssessable $assessment, User $student)
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
    protected function setDueWorkFeedbackMapper(DueWorkFeedbackMapper $dueWorkFeedbackMapper)
    {
        $this->dueWorkFeedbackMapper = $dueWorkFeedbackMapper;
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
     * Fetches the assessment data model for this plan.
     *
     * @param Model $plan
     * @return AssessmentModel
     */
    private function retrieveAssessmentDataModel(Model $plan): AssessmentModel
    {
        return $this->assessmentMapper->findById($plan->getAssessment()->getId());
    }

    /**
     * Fetches the due work ID for this plan; this is required for targeting
     * notifications to the due work's mailbox.
     *
     * @param Model $plan
     * @return int|null
     */
    private function retrieveAssessmentDueWorkId(Model $plan): ?int
    {
        $assessment = $this->retrieveAssessmentDataModel($plan);
        if (!$assessment->getDueWork()) {
            return null;
        }
        return $assessment->getDueWork()->getId();
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
    private function targetDueWorkBuilderToStudent(Builder $bob, IAssessable $assessment, Participant $participant): Builder
    {
        $href = $this->buildParticipantRoute($assessment, $participant->getUser());
        return $bob
            ->setParam('box', new Param($assessment->getTitle(), $href))
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
    private function targetDueWorkBuilderToParents(Builder $bob, IAssessable $assessment, Participant $participant): Builder
    {
        $href = $this->buildParticipantRoute($assessment, $participant->getUser());
        return $bob
            ->setParam('box', new Param($assessment->getTitle(), $href))
            ->setParam('child', new ChildParam($participant->getUser(), Param::STACKING_REPLACE))
            ->setHref($href);
    }


}
