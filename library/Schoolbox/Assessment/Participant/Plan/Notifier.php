<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Participant\Plan;

use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Parameter\Param;
use Alaress\Async\Notification\Parameter\ParameterInterface;
use Alaress_Date_Format;
use Monolog\Logger;
use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;
use Schoolbox\Learning\Assessment\DataModel\Model as AssessmentModel;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Notification\Parameter\CategoryParam;
use Schoolbox\Realtime\Notification\Parameter\ChildParam;
use Schoolbox\Realtime\Notification\Parameter\UserParam;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundleInterface;
use Schoolbox\User;

/**
 * Class Notifier
 */
class Notifier extends AbstractModuleNotifier
{
    private const TRIGGER_DUEWORK_OPEN     = 'dueWorkOpen';
    private const TRIGGER_DUEWORK_UPDATED  = 'dueWorkUpdated';
    private const TRIGGER_DUEWORK_UPCOMING = 'dueWorkUpcoming';
    private const TRIGGER_DUEWORK_OVERDUE  = 'dueWorkOverdue';
    private const TRIGGER_DUEWORK_CLOSED   = 'dueWorkClosed';

    // Don't think these are even used
//    private const TRIGGER_DUEWORK_DATECHANGED = 'dueWorkDatechanged';
//    private const TRIGGER_DUEWORK_RENAMED     = 'dueWorkRenamed';

    /**
     * Days in advance to inform a user that due work is upcoming.
     *
     * TODO make this configurable
     */
    private const DUEWORK_UPCOMING_WINDOW = 2;

//    private const TRIGGER_QUIZ_CLOSED   = 'quizClosed';
//    private const TRIGGER_QUIZ_OPEN     = 'quizOpen';
//    private const TRIGGER_QUIZ_OVERDUE  = 'quizOverdue';
//    private const TRIGGER_QUIZ_UPCOMING = 'quizUpcoming';
//    private const TRIGGER_QUIZ_UPDATED  = 'quizUpdated';

    /**
     * @var AssessmentMapper
     */
    private $assessmentMapper;

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

        if ($assessment->getDueWork()) {
            if ($bundle->getMethod() === Mapper::METHOD_DELETE) {
                $bundle->detachEach([
                    self::TRIGGER_DUEWORK_OPEN,
                    self::TRIGGER_DUEWORK_UPCOMING,
                    self::TRIGGER_DUEWORK_UPDATED,
                    self::TRIGGER_DUEWORK_OVERDUE,
                    self::TRIGGER_DUEWORK_CLOSED,
                ]);

            } else {
                // Bundle triggers

                // Open is now always bundled, not just on create
                $openDate = $this->coalesceDates($plan->getOpenDate(), $assessment->getOpenDate());
                if ($openDate) {
                    $bundle->attach(self::TRIGGER_DUEWORK_OPEN, $openDate->getUnixTimeStamp());
                }

                // Due and upcoming: always bundled
                $dueDate = $this->coalesceDates($plan->getDueDate(), $assessment->getDueDate());
                if ($dueDate) {
                    $upcomingDate = (clone $dueDate)->addDays(-self::DUEWORK_UPCOMING_WINDOW);
                    $bundle->attach(self::TRIGGER_DUEWORK_UPCOMING, $upcomingDate->getUnixTimeStamp());
                    $bundle->attach(self::TRIGGER_DUEWORK_OVERDUE, $dueDate->getUnixTimeStamp());
                }

//                // Close: always bundled
//                $closeDate = $this->coalesceDates($plan->getCloseDate(), $assessment->getCloseDate());
//                if ($closeDate) {
//                    $bundle->attach(self::TRIGGER_DUEWORK_CLOSED, $closeDate->getUnixTimeStamp());
//                }
//
//                // Generic updated trigger
//                if ($bundle->getMethod() === Mapper::METHOD_UPDATE) {
//                    $bundle->attach(self::TRIGGER_DUEWORK_UPDATED, self::IMMEDIATELY);
//                }
            }
            return;
        }
    }


    /**
     * Participant gets notified when a due work opens.
     *
     * Returns an array where the keys are the messages to send, and the values
     * are arrays of topic on which those messages are to be published.
     *
     * @param Model $plan
     * @param User  $effector
     *
     * @throws \Aura\Di\Exception\ServiceNotFound if for whatever reason I couldn't find the data source?
     *                                            Should never happen.
     * @return Envelope
     */
    public function notifyDueWorkOpen(Model $plan, User $effector): Envelope
    {
        $envelope = new Envelope();
        $bob      = new Builder();

        $assessmentDomainObject = $this->retrieveAssessmentDomainModel($plan);

        // This notifier only gets triggered for due works.
        $folder = $assessmentDomainObject->getBaseContext()->getFolder();
        if (!$assessmentDomainObject instanceof DueWork) {
            return $envelope;
        }

        // Still need the submission box's ID for generating the topic
        $assessment = $this->retrieveAssessmentDataModel($plan);
        if (!$assessment->getDueWork()) {
            return $envelope;
        }
        $boxId = $assessment->getDueWork()->getId();


        // Build generic builder
        $workType     = $assessmentDomainObject->getWorkType();
        $boxType      = mb_strtolower($workType ? $workType->getName() : '');
        $indefArticle = $boxType ?
            in_array(strtolower($boxType[0]), ['a', 'e', 'i', 'o', 'u'], true) ? 'an' : 'a'
            : '';

        $bob->setTitle(":user opened $indefArticle $boxType :box in :cat")
            ->setParams([
                'user' => new UserParam($effector, ParameterInterface::STACKING_REPLACE),
                'cat'  => new CategoryParam($folder),
            ])
            ->setIcon(
                "/portrait?" . http_build_query(['userId' => $effector->getExternalId(), 'size' => $this->iconSize,])
            )
            ->setNamespace(NSMapper::NS_DUEWORK)
            ->addTopic("duework.{$boxId}.open")
            ->setSeverity(Logger::NOTICE);


        // Customise and send to every participant in this plan only
        foreach ($assessmentDomainObject->getPlans() as $assessmentPlan) {
            if ($assessmentPlan->getId() !== $plan->getId()) {
                continue;
            }
            foreach ($assessmentPlan->getParticipants() as $participant) {
                if ($participant->isExcluded()) {
                    continue;
                }
                $href = $this->buildParticipantRoute($assessmentDomainObject, $participant->getUser());
                $bob->setHref($href)
                    ->setBody($participant->getDescription())
                    ->setByline("Due {$participant->getDueDate()->getCalendarDateTime()}")
                    ->setParam('box', new Param($assessmentDomainObject->getTitle(), $href));
                $envelope->targetToUser($participant->getUser(), $bob->build());
            }
        }

        return $envelope;
    }

    /**
     * Participant and parents get notified when a due work is upcoming.
     *
     * @param Model $plan
     * @return Envelope
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function notifyDueWorkUpcoming(Model $plan): Envelope
    {
        $envelope = new Envelope();
        $bob      = new Builder();
        $assessmentDomainObject = $this->retrieveAssessmentDomainModel($plan);

        // This notifier only gets triggered for due works.
        $folder = $assessmentDomainObject->getBaseContext()->getFolder();
        if (!$assessmentDomainObject instanceof DueWork) {
            return $envelope;
        }

        // Still need the submission box's ID for generating the topic
        $assessment = $this->retrieveAssessmentDataModel($plan);
        if (!$assessment->getDueWork()) {
            return $envelope;
        }
        $boxId = $assessment->getDueWork()->getId();

        // Doesn't accept online? return
        if ($assessmentDomainObject->isAcceptNone()) {
            return $envelope;
        }

        $bob->setParam('cat', new CategoryParam($folder))
            ->setIcon("/images/icon.php?" . http_build_query(['size' => $this->iconSize, 'icon' => 'work']))
            ->setNamespace(NSMapper::NS_DUEWORK)
            ->setSeverity(Logger::WARNING)
            ->addTopic("duework.$boxId.upcoming");

        // Customise and send to every participant in this plan only
        foreach ($assessmentDomainObject->getPlans() as $assessmentPlan) {
            if ($assessmentPlan->getId() !== $plan->getId()) {
                continue;
            }
            foreach ($assessmentPlan->getParticipants() as $participant) {
                if ($participant->isExcluded()) {
                    continue;
                }

                // Has participant already submitted?
                if (!$participant->isResponseRequired()) {
                    continue;
                }

                $href = $this->buildParticipantRoute($assessmentDomainObject, $participant->getUser());
                $when = $participant->getDueDate()->getRelativeTime(Alaress_Date_Format::now()->addMinutes(-5), true);

                // build for student
                $bob->setTitle(":box is due $when from now in :cat")
                    ->setHref($href)
                    ->setBody($participant->getDescription())
                    ->setParam('box', new Param($assessmentDomainObject->getTitle(), $href));
                $envelope->targetToUser($participant->getUser(), $bob->build());

                // build for parent, only if they're allowed
                if ($this->conf->get('restrict_student_submissions')) {
                    continue;
                }
                $bob->setTitle(":child has not yet submitted work for :box, which is due $when from now in :cat")
                    ->setParam('child', new ChildParam($participant->getUser(), ParameterInterface::STACKING_REPLACE));
                $envelope->targetToParent($participant->getUser(), $bob->build());

                // Clear out the child param, as user doesn't have it
                $bob->unsetParam('child');
            }
        }

        return $envelope;
    }

    /**
     * Participant and parents get notified when a due work is overdue.
     *
     * @param Model $plan
     * @return Envelope
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function notifyDueWorkOverdue(Model $plan): Envelope
    {
        $envelope = new Envelope();
        $bob      = new Builder();
        $assessmentDomainObject = $this->retrieveAssessmentDomainModel($plan);

        // This notifier only gets triggered for due works.
        $folder = $assessmentDomainObject->getBaseContext()->getFolder();
        if (!$assessmentDomainObject instanceof DueWork) {
            return $envelope;
        }

        // Still need the submission box's ID for generating the topic
        $assessment = $this->retrieveAssessmentDataModel($plan);
        if (!$assessment->getDueWork()) {
            return $envelope;
        }
        $boxId = $assessment->getDueWork()->getId();

        // Doesn't accept online? return
        if ($assessmentDomainObject->isAcceptNone()) {
            return $envelope;
        }

        $bob->setParam('cat', new CategoryParam($folder))
            ->setIcon("/images/icon.php?" . http_build_query(['size' => $this->iconSize, 'icon' => 'work']))
            ->setNamespace(NSMapper::NS_DUEWORK)
            ->setSeverity(Logger::ERROR)
            ->addTopic("duework.$boxId.overdue");

        // Customise and send to every participant in this plan only
        foreach ($assessmentDomainObject->getPlans() as $assessmentPlan) {
            if ($assessmentPlan->getId() !== $plan->getId()) {
                continue;
            }
            foreach ($assessmentPlan->getParticipants() as $participant) {
                if ($participant->isExcluded()) {
                    continue;
                }

                // Has participant already submitted?
                if (!$participant->isResponseRequired()) {
                    continue;
                }

                $href = $this->buildParticipantRoute($assessmentDomainObject, $participant->getUser());

                // build for student
                $bob->setTitle(":box is now overdue in :cat")
                    ->setHref($href)
                    ->setBody($participant->getDescription())
                    ->setParam('box', new Param($assessmentDomainObject->getTitle(), $href));
                $envelope->targetToUser($participant->getUser(), $bob->build());

                // build for parent, only if they're allowed
                if ($this->conf->get('restrict_student_submissions')) {
                    continue;
                }
                $bob->setTitle(':child did not submit work for :box in :cat')
                    ->setParam('child', new ChildParam($participant->getUser(), ParameterInterface::STACKING_REPLACE));
                $envelope->targetToParent($participant->getUser(), $bob->build());

                // Clear out the child param, as user doesn't have it
                $bob->unsetParam('child');
            }
        }

        return $envelope;
    }


    /**
     * Convenience helper to build a route to the Participant Summary page
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
     * @throws \Aura\Di\Exception\ServiceNotFound if for whatever reason I couldn't find the data source?
     *                                            Should never happen.
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
}
