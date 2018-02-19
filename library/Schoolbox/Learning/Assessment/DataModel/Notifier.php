<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DataModel;

use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Parameter\Param;
use Alaress\Async\Notification\Parameter\ParameterInterface;
use Monolog\Logger;
use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;
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
//        if (empty($dependencies['userAccessMapper'])) {
//            $dependencies['userAccessMapper'] = UserAccessMapper::getInstance();
//        }
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
        // What kind of object was saved?
        /** @var Model $participant */
        $assessment = $bundle->getEvent()->getObject();

        if ($assessment->getQuiz()) {
            // Proc quiz notifs to this participant and parents
            // May need to bundle these together with notifs to teachers that things are overdue
            return;
        }

        if ($assessment->getDueWork()) {
            // Is this a dynamic participation?
            // Trigger notifs to all except excluded or otherwise included
            // TODO figure out where the base due date's at
            $bundle->attach(
                self::TRIGGER_DUEWORK_OPEN,
                $assessment->getOpenDate()->getUnixTimeStamp()
            );
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
     * @param Model $assessment
     * @param User  $effector
     *
     * @throws \OutOfBoundsException     If severity is not set
     * @throws \InvalidArgumentException If a required property was not set
     * @return Envelope
     */
    public function notifyDueWorkOpen(Model $assessment, User $effector): Envelope
    {
        $envelope = new Envelope();
        $bob      = new Builder();

        // SOMEHOW: I need the swamp here!
        // It's the only reliable method for fetching assessment participants
        // GUESS WHAT I'M GOING TO DO UNDER PAIN OF DEATH
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
        /** @var MapperProxy $assessmentDataSource */
        /** @var IAssessable $assessmentDomainObject */
        $assessmentDataSource   = $this->mapper::getDi()->get('assessmentDataSource');
        $assessmentDomainObject = $assessmentDataSource->findByIdForReview($assessment->getId(), true);

//        // Only do this for dynamic plans; all others have participants assigned manually
//        if (!$plan->isDynamic()) {
//            return $envelope;
//        }

//        // FFFFFFFUUUUUUUUUUUUUUUUUUU~~~~~~~~~
//        $plan = $this->mapper->findById($plan->getId());

        // Hopefully I don't have to hydrate this myself
        // (Looks like I do)
        // (I probably have to hydrate participation manually too -_-)
        $folder      = $assessmentDomainObject->getBaseContext()->getFolder();
//        $this->log->warning(get_class($assessmentDomainObject));
        if (!$assessmentDomainObject instanceof DueWork) {
            // I'm in the wrong place
            return $envelope;
        }

//        $assessment = $this->assessmentMapper->findById($plan->getAssessment()->getId());
//        $this->log->warning(get_class($assessment->getDueWork()));
        if (!$assessment->getDueWork()) {
            // I'm in the wrong place
            return $envelope;
        }
        $boxId = $assessment->getDueWork()->getId();


        $workType = $assessmentDomainObject->getWorkType();
        $boxType  = mb_strtolower($workType ? $workType->getName() : '');

        // Go over each plan
        foreach ($assessmentDomainObject->getPlans() as $plan) {

            // Pull from plan in preference to assessment
            $boxName  = $plan->getName() ?? $assessmentDomainObject->getTitle();
            $dueDate  = $plan->getDueDate() ?? $assessmentDomainObject->getCloseAt();
            $description = $plan->getDescription() ?? $assessmentDomainObject->getDescription();

            $indefArticle = $boxType ?
                in_array(strtolower($boxType[0]), ['a', 'e', 'i', 'o', 'u'], true) ? 'an' : 'a'
                : '';

            $bob->setTitle(":user opened $indefArticle $boxType :box in :cat")
                ->setParams([
                    'user' => new UserParam($effector, ParameterInterface::STACKING_REPLACE),
                    'cat'  => new CategoryParam($folder),
                ])
                ->setIcon("/portrait?" . http_build_query(['userId' => $effector->getExternalId(), 'size' => $this->iconSize,]))
                ->setNamespace(NSMapper::NS_DUEWORK)
                ->addTopic("duework.{$boxId}.open")
                ->addFilter('roletype', [Role::STUDENT])
                ->setBody($description)
                ->setByline("Due {$dueDate->getCalendarDateTime()}")
                ->setSeverity(Logger::NOTICE);

            // Target to each user
            foreach ($plan->getParticipants() as $participant) {
                if ($participant->isExcluded()) {
                    continue;
                }
                $href = $this->buildParticipantRoute($assessmentDomainObject, $participant->getUser());
                $bob
                    ->setHref($href)
                    ->setParam('box', new Param($boxName, $href));
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
}
