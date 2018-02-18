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
use Monolog\Logger;
use Schoolbox\Learning\Assessment\DataModel\Model as AssessmentModel;
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
    private const TRIGGER_DUEWORK_OPEN = 'dueworkOpen';


    /**
     * @var UserAccessMapper
     */
    private $userAccessMapper;

    /**
     * @return string|void
     */
    protected function getSlug()
    {
        return '';
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
        parent::loadDependencies();
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
        $participant = $bundle->getEvent()->getObject();

        $assessment  = $participant->getAssessment();
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
     * @param Model $plan
     * @param User  $effector
     *
     * @throws \OutOfBoundsException     If severity is not set
     * @throws \InvalidArgumentException If a required property was not set
     * @return Envelope
     */
    public function notifyStudentOpen(Model $plan, User $effector): Envelope
    {
        $envelope = new Envelope();
        $bob      = new Builder();

        // Only do this for dynamic plans; all others have participants assigned manually
        if ($plan->isDynamic()) {
            return $envelope;
        }

        // Hopefully I don't have to hydrate this myself
        $assessment  = $plan->getAssessment();
        $folder      = $assessment->getAssessmentContext()->getFolder();
        $box         = $assessment->getDueWork();
        if (!$box) {
            // I'm in the wrong place
            return $envelope;
        }

        $workType = $assessment->getWorkType();
        $boxType  = mb_strtolower($workType ? $workType->getName() : '');

        // Pull from plan in preference to assessment
        $boxName  = $plan->getName() ?? $assessment->getTitle();
        $dueDate  = $plan->getDueDate() ?? $assessment->getDueDate();
        $description = $plan->getDescription() ?? $assessment->getDescription();

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
            ->addTopic("duework.{$box->getId()}.open")
            ->addFilter('roletype', [Role::STUDENT])
            ->setBody($description)
            ->setByline("Due {$dueDate->getCalendarDateTime()}")
            ->setSeverity(Logger::NOTICE);

        // Hydrate folder to ensure presence of folders codes
        foreach ($this->userAccessMapper->findStudentsInCategory($folder) as $student) {
            $href = $this->buildParticipantRoute($assessment, $student);
            $bob
                ->setHref($href)
                ->setParam('box', new Param($boxName, $href));
            $envelope->targetToUser($student, $bob->build());
        }

        return $envelope;
    }

    /**
     * Convenience helper to build a route to the Participant Summary page
     *
     * @param AssessmentModel $assessment
     * @param User            $student
     * @return string
     */
    private function buildParticipantRoute(AssessmentModel $assessment, User $student)
    {
        return sprintf('/learning/assessments/%s/%s', $assessment->getId(), $student->getId());
    }
}
