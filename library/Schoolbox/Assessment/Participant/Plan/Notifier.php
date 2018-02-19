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
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Notification\Parameter\CategoryParam;
use Schoolbox\Realtime\Notification\Parameter\UserParam;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundle;
use Schoolbox\Role;
use Schoolbox\User;

/**
 * Class Notifier
 */
class Notifier extends AbstractModuleNotifier
{
    private const TRIGGER_DUEWORK_OPEN = 'dueWorkOpen';
//    private const TRIGGER_DUEWORK_UPDATED     = 'dueWorkUpdated';
//    private const TRIGGER_DUEWORK_UPCOMING    = 'dueWorkUpcoming';
//    private const TRIGGER_DUEWORK_OVERDUE     = 'dueWorkOverdue';
//    private const TRIGGER_DUEWORK_CLOSED      = 'dueWorkClosed';
//    private const TRIGGER_DUEWORK_DATECHANGED = 'dueWorkDatechanged';
//    private const TRIGGER_DUEWORK_RENAMED     = 'dueWorkRenamed';
//
//    private const DUEWORK_UPCOMING_WINDOW = 2;
//
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
     * @var UserAccessMapper
     */
    private $userAccessMapper;

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
        // Dunno what dependencies this has yet
        if (empty($dependencies['userAccessMapper'])) {
            $dependencies['userAccessMapper'] = UserAccessMapper::getInstance();
        }
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
     * @param TriggerBundle $bundle
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
            $openDate = $this->coalesceDates($plan->getOpenDate(), $assessment->getOpenDate());
            if ($openDate) {
                $bundle->attach(self::TRIGGER_DUEWORK_OPEN, $openDate->getUnixTimeStamp());
            }
//            $bundle->attachEach([
//                self::TRIGGER_DUEWORK_OPEN     => ($participant->getPlan()->getOpenDate() ?? $assessment->getOpenDate())->getUnixTimeStamp(),
//                self::TRIGGER_DUEWORK_OVERDUE  => $participant->getPlan()->getDueDate(),
//                self::TRIGGER_DUEWORK_UPCOMING => $participant->getPlan()->getDueDate()->addDays(-self::DUEWORK_UPCOMING_WINDOW),
//                self::TRIGGER_DUEWORK_CLOSED   => $participant->getPlan()->getCloseDate(),
//            ]);
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
     * @throws \OutOfBoundsException     If severity is not set
     * @throws \InvalidArgumentException If a required property was not set
     * @return Envelope
     */
    public function notifyDueWorkOpen(Model $plan, User $effector): Envelope
    {
        $envelope = new Envelope();
        $bob      = new Builder();

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
        /** @var IAssessable $assessmentDomainObject */
        $assessmentDataSource   = $this->mapper::getDi()->get('assessmentDataSource');
        $assessmentDomainObject = $assessmentDataSource->findByIdForReview($plan->getAssessment()->getId(), true);


        // This notifier only gets triggered for due works.
        $folder = $assessmentDomainObject->getBaseContext()->getFolder();
        if (!$assessmentDomainObject instanceof DueWork) {
            return $envelope;
        }

        // Still need the submission box's ID for generating the topic
        $assessment = $this->assessmentMapper->findById($plan->getAssessment()->getId());
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

        // Pull from plan in preference to assessment where the settings can be customised per plan
        $dueDate     = $plan->getDueDate()     ?? $assessmentDomainObject->getCloseAt();
        $description = $plan->getDescription() ?? $assessmentDomainObject->getDescription();

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
            ->addFilter('roletype', [Role::STUDENT])
            ->setBody($description)
            ->setByline("Due {$dueDate->getCalendarDateTime()}")
            ->setSeverity(Logger::NOTICE);


        // Customise and send to every participant in this plan only
        foreach ($assessmentDomainObject->getPlans() as $assessmentPlan) {
            if ($assessmentPlan->getId() != $plan->getId()) {
                continue;
            }
            foreach ($assessmentPlan->getParticipants() as $participant) {
                if ($participant->isExcluded()) {
                    continue;
                }
                $href = $this->buildParticipantRoute($assessmentDomainObject, $participant->getUser());
                $bob->setHref($href)
                    ->setParam('box', new Param($assessmentDomainObject->getTitle(), $href));
                $envelope->targetToUser($participant->getUser(), $bob->build());
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
     * @param UserAccessMapper $userAccessMapper
     * @return static
     */
    protected function setUserAccessMapper(UserAccessMapper $userAccessMapper): self
    {
        $this->userAccessMapper = $userAccessMapper;
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
                return $date;
            }
        }
        return null;
    }
}
