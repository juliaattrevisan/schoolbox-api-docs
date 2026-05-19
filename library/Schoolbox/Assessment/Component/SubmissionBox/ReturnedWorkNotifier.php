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
use Monolog\Logger;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Term;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\WorkType\Mapper as WorkTypeMapper;
use Schoolbox\Mapper;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Notification\Parameter\CategoryParam;
use Schoolbox\Realtime\Notification\Parameter\UserParam;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\ScheduleException;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundle;
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

    /**
     * @var WorkTypeMapper
     */
    protected $workTypeMapper;

    /** @var AssessmentDataSource $assessmentDataSource */
    protected $assessmentDataSource;

    /**
     * Instantiate all your dependencies within yourself.
     *
     * @param mixed $dependencies
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function loadDependencies($dependencies = null)
    {
        if (!is_array($dependencies)) {
            $dependencies = [];
        }
        if (empty($dependencies['userMapper'])) {
            $dependencies['userMapper'] = UserMapper::getInstance();
        }
        if (empty($dependencies['boxMapper'])) {
            $dependencies['boxMapper'] = SubmissionBoxMapper::getInstance();
        }
        if (empty($dependencies['termMapper'])) {
            $dependencies['termMapper'] = Term\Mapper::getInstance();
        }
        if (empty($dependencies['workTypeMapper'])) {
            $dependencies['workTypeMapper'] = WorkTypeMapper::getInstance();
        }
        if (empty($dependencies['assessmentDataSource'])) {
            $dependencies['assessmentDataSource'] = Mapper::getDi()->get("assessmentDataSource");
        }
        parent::loadDependencies($dependencies);
    }

    /**
     * This is called via reflection from AbstractModuleNotifier->loadDependencies().
     *
     * @param UserMapper $userMapper
     * @return $this
     */
    protected function setUserMapper(UserMapper $userMapper): self
    {
        $this->userMapper = $userMapper;
        return $this;
    }

    /**
     * This is called via reflection from AbstractModuleNotifier->loadDependencies().
     *
     * @param SubmissionBoxMapper $boxMapper
     * @return $this
     */
    protected function setBoxMapper(SubmissionBoxMapper $boxMapper): self
    {
        $this->boxMapper = $boxMapper;
        return $this;
    }

    /**
     * This is called via reflection from AbstractModuleNotifier->loadDependencies().
     *
     * @param Term\Mapper $termMapper
     * @return $this
     */
    protected function setTermMapper(Term\Mapper $termMapper): self
    {
        $this->termMapper = $termMapper;
        return $this;
    }

    /**
     * This is called via reflection from AbstractModuleNotifier->loadDependencies().
     *
     * @param WorkTypeMapper $workTypeMapper
     * @return $this
     */
    protected function setWorkTypeMapper(WorkTypeMapper $workTypeMapper): self
    {
        $this->workTypeMapper = $workTypeMapper;
        return $this;
    }

    /**
     * This is called via reflection from AbstractModuleNotifier->loadDependencies().
     *
     * @param AssessmentDataSource $assessmentDataSource
     * @return $this
     */
    protected function setAssessmentDataSource(AssessmentDataSource $assessmentDataSource): self
    {
        $this->assessmentDataSource = $assessmentDataSource;
        return $this;
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

        // Is this return a self-assessment? If so, don't notify about it:
        // a notification ought to have been raised for the due work response.
        if ($mark->isSelfAssess()) {
            return;
        }

        try {
            /** @var SubmissionBox $box */
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
        if ($box->getAssessmentModel()->getWorkType() && $box->getAssessmentModel()->getWorkType()->getId()) {
            // The work type might not be hydrated, and thus unable to determine
            // if it's staff-only. Hydrate it here
            try {
                $workType = $this->workTypeMapper->findById($box->getAssessmentModel()->getWorkType()->getId());
            } catch (Mapper\Exception $e) {
                return;
            }
            if (!$workType->isVisibleToLearners() && !$workType->isVisibleToParents()) {
                return;
            }
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
        if (!($assessment->getWorkType()?->isVisibleToLearners() ?? true)) {
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
        if (!$assessment) {
            return $envelope;
        }
        if (!($assessment->getWorkType()?->isVisibleToParents() ?? true)) {
            return $envelope;
        }
        $cat = $assessment->getBaseContext()->getFolder();

        // Request re-submission, even when mark is not visible.
        $restrictGrades = $this->conf->get('restrict_parent_grades')
            || !$this->conf->get(ConfigMapper::NAME_GRADES_ENABLED)
            || !$cat->isGradePageEnabled();
        $requestResubmission = $returnedWork->getResubmissionRequired();
        if (
            $this->conf->get('restrict_parent_duework_access')  // hide due work
            || ($restrictGrades && $this->conf->get('restrict_student_submissions'))
            || !($assessment->isFeedbackVisibleToRoleType(Role::PARENT))
        ) {
            return $envelope;
        }


        $term      = $this->termMapper->findForDate($assessment->getDueAt());
        $hrefGrade = $restrictGrades ? '' : "/learning/grades/{$student->getId()}/{$term->getCode()}/{$cat->getId()}";
        $href      = $this->conf->get('restrict_student_submissions') ? $hrefGrade : "/learning/assessments/{$assessment->getId()}/{$student->getId()}";

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
                ->setParam('catname', new Param($cat->getName(), $href));
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
