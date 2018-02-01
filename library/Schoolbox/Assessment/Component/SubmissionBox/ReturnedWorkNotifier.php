<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\Component\SubmissionBox;


use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Parameter\Param;
use Monolog\Logger;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Term;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Mapper;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Notification\Parameter\CategoryParam;
use Schoolbox\Realtime\Notification\Parameter\UserParam;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\ScheduleException;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundle;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;

class ReturnedWorkNotifier extends AbstractModuleNotifier
{

    const TRIGGER_RETURNED = 'returned';

    /** @var UserMapper */
    protected $userMapper;

    /** @var FolderMapper */
    protected $catMapper;

    /** @var SubmissionBoxMapper */
    protected $boxMapper;

    /** @var Term\Mapper */
    protected $termMapper;

    /**
     * Instantiate all your dependencies within yourself.
     *
     * @param mixed $dependencies
     * @return void
     */
    public function loadDependencies($dependencies = null)
    {
        $this->userMapper = UserMapper::getInstance();
        $this->catMapper = FolderMapper::getInstance();
        $this->boxMapper = SubmissionBoxMapper::getInstance();
        $this->termMapper = Term\Mapper::getInstance();
        parent::loadDependencies($dependencies);
    }

    /**
     * If created, raise a notification. No conditions, no other functions. That's it.
     *
     * @param TriggerBundle $bundle
     * @throws ScheduleException
     * @return void
     */
    public function bundleTriggers($bundle)
    {

        /** @var ReturnedWork $mark */
        $mark = $bundle->getEvent()->getObject();

        // Confirmation of submission? Do nothing
        if ($mark->getIsConfirmationOfSubmission()) {
            return;
        }

        /** @var SubmissionBox $box */
        $box = $this->boxMapper->findByIdWithFallback($mark->getSubmissionBox()->getId());

        if ($box->getId() === null) {
            // don't. even. bother.
            return;
        }

        /*
         * This now on the assessment, will need to be updated to get from the assessment
        if ($box->isDeleted()) {
            // don't. even. bother.
            return;
        }*/

        if ($box->getMarkVisibility() == IAssessable::MARK_VISIBILITY_PRIVATE) {
            // don't. even. bother.
            return;
        }

        if ($mark->isDraft()) {
            // Not published: don't even bother here either
            return;
        }

        switch ($bundle->getMethod()) {
            case Mapper::METHOD_CREATE: // when returned work is generated
            case Mapper::METHOD_UPDATE: // when returned work is updated:
                $bundle->attach(self::TRIGGER_RETURNED, self::IMMEDIATELY);
                break;

            case Mapper::METHOD_READ:
            case Mapper::METHOD_DELETE: // when returned work is...uh, un-returned...
                break;

            default:
                throw new ScheduleException("Unrecognised CRUD operation: '{$bundle->getMethod()}'.");
        }
    }

    /**
     * Notify a student that their work has been marked.
     *
     * @param ReturnedWork $returnedWork
     * @param User         $user
     * @param array|null   $diff
     * @return Envelope
     */
    public function notifyStudentReturned($returnedWork, $user, $diff = null)
    {
        return $this->generateMessage(Role::STUDENT, $returnedWork, $user, $diff);
    }

    /**
     * @param string       $target
     * @param ReturnedWork $returnedWork
     * @param User         $user
     * @param array|null   $diff
     * @return Envelope
     */
    protected function generateMessage($target, $returnedWork, $user, $diff = null)
    {
        $envelope = new Envelope();

        /** @var User $student */
        $student = $this->userMapper->findById($returnedWork->getOwner()->getId());
        /** @var User $teacher */
        $teacher = $this->userMapper->findById($user->getId());
        /** @var SubmissionBox $box */
        $box = $this->boxMapper->findById($returnedWork->getSubmissionBox()->getId());
        /** @var Folder $cat */
        $cat = $this->catMapper->findById($box->getCategory()->getId());

        $teacherExtId = $teacher->getExternalId();

        $studentId = $student->getId();

        $boxId = $box->getId();
        $boxName = $box->getName();

        $catId = $cat->getId();
        $catName = $cat->getName();

        $icon = "/portrait?userId=$teacherExtId&size={$this->iconSize}";
        $href = "/learning/assessments/{$box->getAssessmentId()}/$studentId";

        // if student, 2nd-person language
        if ($target === Role::STUDENT && $box->getMarkVisibility() >= IAssessable::MARK_VISIBILITY_STUDENT) {
            $bob = new Builder();
            $bob->setIcon($icon)
                ->setNamespace($this->getSlug())
                ->addTopic("duework.$boxId.returned")
                ->setSeverity(Logger::NOTICE)
                ->setTitle(":teacher has marked your work for :boxname in :catname")
                ->setHref($href)
                ->setParam('teacher', new UserParam($teacher))
                ->setParam('boxname', new Param($boxName, "/learning/assessments/{$box->getAssessmentId()}/$studentId"))
                ->setParam('catname', new CategoryParam($cat))
                ->addFilter('roletype', [Role::STUDENT]);
            $envelope->targetToUser($studentId, $bob->build());
        } // if parent, 3rd-person language
        elseif ($target === Role::PARENT && $this->parentCanView($box)) {
            // If parent can't view results for this grade:
            // target to student's grades page
            if (!$box->isMarkShownToParents()) {
                $href = "/learning/grades/$studentId";
            }
            $term = $this->termMapper->findForDate($box->getDueDate());
            $bob = new Builder();
            $bob->setIcon($icon)
                ->setNamespace($this->getSlug())
                ->addTopic("duework.$boxId.returned")
                ->setSeverity(Logger::NOTICE)
                ->setTitle(":teacher has marked :student's work for :boxname in :catname")
                ->setHref($href)
                ->setParam('teacher', new UserParam($teacher))
                ->setParam('student', new UserParam($student))
                ->setParam('boxname', new Param($boxName, "/learning/assessments/{$box->getAssessmentId()}/$studentId"))
                ->setParam('catname', new Param($catName, "/learning/grades/$studentId/{$term->getCode()}/$catId"))
                ->addFilter('roletype', [Role::PARENT]);
            $envelope->targetToParent($studentId, $bob->build());
        }

        return $envelope;
    }

    /* ----------------------------------------------------------------------------------------------------------
     *  ASYNC WORKER METHODS
     * ---------------------------------------------------------------------------------------------------------- */

    /**
     * @return string
     */
    protected function getSlug()
    {
        return NSMapper::NS_DUEWORK;
    }

    /**
     * @param SubmissionBox $box
     * @return bool
     */
    protected function parentCanView($box)
    {
        return $box->getMarkVisibility() >= IAssessable::MARK_VISIBILITY_PARENT_STUDENT && !$this->conf->get('restrict_parent_duework_access');
    }

    /**
     * Notify a parent that their child's work has been marked.
     *
     * @param ReturnedWork $returnedWork
     * @param User         $user
     * @param array|null   $diff
     * @return Envelope
     */
    public function notifyParentReturned($returnedWork, $user, $diff = null)
    {
        return $this->generateMessage(Role::PARENT, $returnedWork, $user, $diff);
    }

} 
