<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Participant;

use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Parameter\Param;
use Alaress\Async\Notification\Parameter\ParameterInterface;
use Monolog\Logger;
use Schoolbox\Learning\Assessment\DataModel\Model as AssessmentModel;
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
    private const TRIGGER_DUEWORK_OPEN        = 'dueworkOpen';
    private const TRIGGER_DUEWORK_UPDATED     = 'dueworkUpdated';
    private const TRIGGER_DUEWORK_UPCOMING    = 'dueworkUpcoming';
    private const TRIGGER_DUEWORK_OVERDUE     = 'dueworkOverdue';
    private const TRIGGER_DUEWORK_CLOSED      = 'dueworkClosed';
    private const TRIGGER_DUEWORK_DATECHANGED = 'dueworkDatechanged';
    private const TRIGGER_DUEWORK_RENAMED     = 'dueworkRenamed';

    private const DUEWORK_UPCOMING_WINDOW = 2;

    private const TRIGGER_QUIZ_CLOSED   = 'quizClosed';
    private const TRIGGER_QUIZ_OPEN     = 'quizOpen';
    private const TRIGGER_QUIZ_OVERDUE  = 'quizOverdue';
    private const TRIGGER_QUIZ_UPCOMING = 'quizUpcoming';
    private const TRIGGER_QUIZ_UPDATED  = 'quizUpdated';

    /**
     * Irrelevant as this could notify about different things
     *
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

        $assessment  = $participant->getPlan()->getAssessment();
        if ($assessment->getQuiz()) {
            // Proc quiz notifs to this participant and parents
            // May need to bundle these together with notifs to teachers that things are overdue
            return;
        }

        if ($assessment->getDueWork()) {
            // Proc due work notifs to this participant and parents
            // TODO figure out where the base due date's at
            $bundle->attach(
                self::TRIGGER_DUEWORK_OPEN,
                ($participant->getPlan()->getOpenDate() ?? $assessment->getOpenDate())->getUnixTimeStamp()
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
     * @param Model $participant
     * @param User  $effector
     *
     * @throws \OutOfBoundsException     If severity is not set
     * @throws \InvalidArgumentException If a required property was not set
     * @return Envelope
     */
    public function notifyStudentOpen(Model $participant, User $effector)
    {
        $envelope = new Envelope();
        $bob      = new Builder();

        // Hopefully I don't have to hydrate this myself
        $assessment  = $participant->getPlan()->getAssessment();
        $folder      = $assessment->getAssessmentContext()->getFolder();
        $box         = $assessment->getDueWork();
        if (!$box) {
            // I'm in the wrong place
            return $envelope;
        }

        $workType = $assessment->getWorkType();
        $boxType  = mb_strtolower($workType ? $workType->getName() : '');

        // Pull from plan in preference to assessment
        $boxName  = $participant->getPlan()->getName() ?? $assessment->getTitle();
        $dueDate  = $participant->getPlan()->getDueDate() ?? $assessment->getDueDate();
        $description = $participant->getPlan()->getDescription() ?? $assessment->getDescription();

        $indefArticle = $boxType ?
            in_array(strtolower($boxType[0]), ['a', 'e', 'i', 'o', 'u'], true) ? 'an' : 'a'
            : '';

        $bob->setTitle(":user opened $indefArticle $boxType :box in :cat")
            ->setParams([
                'user' => new UserParam($effector, ParameterInterface::STACKING_REPLACE),
                'cat'  => new CategoryParam($folder),
                'box'  => new Param($boxName, $this->buildParticipantRoute($assessment, $participant->getUser()))
            ])
            ->setIcon("/portrait?" . http_build_query(['userId' => $effector->getExternalId(), 'size' => $this->iconSize,]))
            ->setNamespace(NSMapper::NS_DUEWORK)
            ->addTopic("duework.{$box->getId()}.open")
            ->addFilter('roletype', [Role::STUDENT])
            ->setBody($description)
            ->setByline("Due {$dueDate->getCalendarDateTime()}")
            ->setSeverity(Logger::NOTICE);

        $envelope->targetToUser($participant->getUser(), $bob->build());

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
