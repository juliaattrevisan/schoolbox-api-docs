<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Assessment\Component\SubmissionBox;

use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Parameter\Param;
use Aura\Di\Exception\ServiceNotFound;
use Monolog\Logger;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Term;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as assessmentDataSource;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Mapper;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Notification\Parameter\CategoryParam;
use Schoolbox\Realtime\Notification\Parameter\UserParam;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\ScheduleException;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundle;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;

class ReturnedWorkNotifier extends AbstractModuleNotifier
{

    const TRIGGER_RETURNED               = 'returned';
    const PATTERN_MARKED_STUDENT_TITLE   = ':teacher has marked your work for :boxname in :catname';
    const PATTERN_MARKED_PARENT_TITLE    = ':teacher has marked :student\'s work for :boxname in :catname';
    const PATTERN_RESUBMIT_STUDENT_TITLE = ':teacher has requested you resubmit to :boxname';
    const PATTERN_RESUBMIT_PARENT_TITLE  = ':teacher has requested :student resubmit to :boxname';

    /** @var UserMapper */
    protected $userMapper;

    /** @var SubmissionBoxMapper */
    protected $boxMapper;

    /** @var Term\Mapper */
    protected $termMapper;

    /** @var assessmentDataSource $assessmentDataSource */
    protected $assessmentDataSource;

    /**
     * Instantiate all your dependencies within yourself.
     *
     * @param mixed $dependencies
     * @throws ServiceNotFound if somehow my assessment data source wasn't found
     * @return void
     */
    public function loadDependencies($dependencies = null)
    {
        $this->userMapper           = UserMapper::getInstance();
        $this->boxMapper            = SubmissionBoxMapper::getInstance();
        $this->termMapper           = Term\Mapper::getInstance();
        $this->assessmentDataSource = Mapper::getDi()->get("assessmentDataSource");
        parent::loadDependencies($dependencies);
    }

    /**
     * If created, raise a notification. No conditions, no other functions. That's it.
     *
     * @param TriggerBundle $bundle
     * @throws ScheduleException
     * @return void
     */
    public function bundleTriggers($bundle): void
    {

        /** @var ReturnedWork $mark */
        $mark = $bundle->getEvent()->getObject();

        if ($mark->isDraft()) {
            // Not published: don't even bother here either
            return;
        }

        /** @var SubmissionBox $box */
        try {
            $box = $this->boxMapper->findByIdWithFallback($mark->getSubmissionBox()->getId());
        } catch (Mapper\Exception $e) {
            return;
        }

        if ($mark->getResubmissionRequired() && $bundle->getMethod() === Mapper::METHOD_UPDATE) {
            $bundle->attach(self::TRIGGER_RETURNED, self::IMMEDIATELY);
            return;
        }

        if ($box->getId() === null) {
            // don't. even. bother.
            return;
        }

        if ($box->getMarkVisibility() == IAssessable::MARK_VISIBILITY_PRIVATE) {
            // don't. even. bother.
            return;
        }

        // As this notifier is for student and parents, if it's only visible to staff, return.
        $worktype = $box->getAssessmentModel()->getWorkType();
        if ($worktype && $worktype->isStaffOrInstructorVisibleOnly()) {
            return;
        }

        switch ($bundle->getMethod()) {
            case Mapper::METHOD_CREATE: // when returned work is generated
            case Mapper::METHOD_UPDATE: // when returned work is updated:
                $bundle->attach(self::TRIGGER_RETURNED, self::IMMEDIATELY);
                break;

            case Mapper::METHOD_READ:
                break;
            case Mapper::METHOD_DELETE: // when returned work is...uh, un-returned...
                // trigger to retract existing notifications
                $bundle->attach(self::TRIGGER_DELETED, self::IMMEDIATELY);
                break;

            default:
                throw new ScheduleException("Unrecognised CRUD operation: '{$bundle->getMethod()}'.");
        }
    }

    /* ----------------------------------------------------------------------------------------------------------
     *  ASYNC WORKER METHODS
     * ---------------------------------------------------------------------------------------------------------- */

    /**
     * @return string
     */
    protected function getSlug(): string
    {
        return NSMapper::NS_DUEWORK;
    }

    /**
     * Notify system that the work has been deleted.
     * @param ReturnedWork $returnedWork
     * @return Envelope
     */
    public function notifySystemDeleted(ReturnedWork $returnedWork): Envelope
    {
        $envelope = new Envelope();
        return $this->retractEventObject($envelope, $returnedWork);
    }

    /**
     * Notify a student that their work has been marked.
     *
     * @param ReturnedWork $returnedWork
     * @param User         $effector
     * @param array|null   $diff
     * @return Envelope
     */
    public function notifyStudentReturned(ReturnedWork $returnedWork, User $effector, $diff = null): Envelope
    {
        $envelope = new Envelope();

        $student    = $returnedWork->getOwner();
        $box        = $returnedWork->getSubmissionBox();
        $assessment = $this->getAssessment($box, $student);

        // Request re-submission, even when mark is not visible.
        $requestResubmission = $returnedWork->getResubmissionRequired();
        if (!$assessment || !($assessment->isFeedbackVisibleToRoleType(Role::STUDENT) || $requestResubmission)) {
            return $envelope;
        }
        $href = "/learning/assessments/{$assessment->getId()}/{$student->getId()}";

        $bob = new Builder();
        $bob->setIcon($this->getUserIcon($effector))
            ->setNamespace($this->getSlug())
            ->addTopic("duework.{$box->getId()}.returned")
            ->setSeverity(Logger::NOTICE)
            ->setHref($href)
            ->setParam('teacher', new UserParam($effector))
            ->setParam('boxname', new Param($assessment->getTitle(), $href))
            ->addFilter('roletype', [Role::STUDENT]);

        if ($requestResubmission) {
            $bob->setTitle(self::PATTERN_RESUBMIT_STUDENT_TITLE);
        } else {
            $bob->setTitle(self::PATTERN_MARKED_STUDENT_TITLE)
                ->setParam('catname', new CategoryParam($assessment->getBaseContext()->getFolder()));
        }

        $envelope->targetToUser($student->getId(), $bob->build());
        return $envelope;
    }

    /**
     * Notify a parent that their child's work has been marked.
     *
     * @param ReturnedWork $returnedWork
     * @param User         $effector
     * @param array|null   $diff
     * @return Envelope
     */
    public function notifyParentReturned(ReturnedWork $returnedWork, User $effector, $diff = null): Envelope
    {
        $envelope = new Envelope();

        $student = $returnedWork->getOwner();
        // hydrate student to display name properly.
        try {
            $student = $this->userMapper->findById($student->getId());
        } catch (Mapper\Exception $e) {
            return $envelope;
        }

        $box        = $returnedWork->getSubmissionBox();
        $assessment = $this->getAssessment($box, $student);

        // Request re-submission, even when mark is not visible.
        $requestResubmission = $returnedWork->getResubmissionRequired();
        if (!$assessment
            || $this->conf->get('restrict_parent_duework_access')
            || !($assessment->isFeedbackVisibleToRoleType(Role::PARENT) || $requestResubmission)) {
            return $envelope;
        }

        /** @var Folder $cat */
        $cat  = $assessment->getBaseContext()->getFolder();
        $href = "/learning/assessments/{$assessment->getId()}/{$student->getId()}";

        $term = $this->termMapper->findForDate($assessment->getDueAt());
        $bob  = new Builder();
        $bob->setIcon($this->getUserIcon($effector))
            ->setNamespace($this->getSlug())
            ->addTopic("duework.{$box->getId()}.returned")
            ->setSeverity(Logger::NOTICE)
            ->setHref($href)
            ->setParam('teacher', new UserParam($effector))
            ->setParam('student', new UserParam($student))
            ->setParam('boxname', new Param($assessment->getTitle(), $href))
            ->addFilter('roletype', [Role::PARENT]);
        if ($requestResubmission) {
            $bob->setTitle(self::PATTERN_RESUBMIT_PARENT_TITLE);
        } else {
            $bob->setTitle(self::PATTERN_MARKED_PARENT_TITLE)
                ->setParam('catname', new Param($cat->getName(), "/learning/grades/{$student->getId()}/{$term->getCode()}/{$cat->getId()}"));
        }
        $envelope->targetToParent($student->getId(), $bob->build());

        return $envelope;
    }

    /**
     * Gets assessable from submission box
     * @param SubmissionBox $box
     * @param User          $student
     * @return IAssessable|null
     */
    private function getAssessment(SubmissionBox $box, User $student): ?IAssessable
    {
        try {
            $box        = $this->boxMapper->findById($box->getId());
            $assessment = $this->assessmentDataSource->findByIdForReview($box->getAssessmentId(), $student);
        } catch (Mapper\Exception $e) {
            return null;
        }

        $participant = $assessment->getParticipantIfExtant($student);

        //Don't send a notification for an item that the user cannot see
        if (!$participant || $participant->getPublishDate()->isFuture()) {
            return null;
        }
        return $assessment;
    }
}
