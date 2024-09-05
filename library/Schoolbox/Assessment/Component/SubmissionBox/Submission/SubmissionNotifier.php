<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Component\SubmissionBox\Submission;

use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Parameter\Param;
use Monolog\Logger;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Notification\Parameter\CategoryParam;
use Schoolbox\Realtime\Notification\Parameter\SimpleParam;
use Schoolbox\Realtime\Notification\Parameter\UserParam;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\ScheduleException;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundle;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Notifier that is used when students submit work to a submission box.
 *
 * Class SubmissionNotifier
 *
 * @package Schoolbox\Assessment\Component\SubmissionBox
 * @method SubmissionMapper getMapper()
 */
class SubmissionNotifier extends AbstractModuleNotifier
{
    const TRIGGER_SUBMITTED   = 'submitted';
    const TRIGGER_RESUBMITTED = 'resubmitted';
    const TRIGGER_DELETED     = 'deleted';

    const PATTERN_DELETED_TITLE   = ':owner removed your previous submission to :boxname in :catname, you can now resubmit';
    const PATTERN_SUBMITTED_TITLE = ':student :type work to :boxname in :catname';
    const PATTERN_SUBMITTED_ON_BEHALF_OF_TITLE = ':teacher :type work on behalf of :student to :boxname in :catname';

    /** @var SubmissionBoxMapper */
    protected $submissionBoxMapper;

    /** @var UserMapper */
    protected $userMapper;

    /** @var UserAccessMapper */
    protected $userAccessMapper;

    /**
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
     * @return void
     */
    public function loadDependencies($dependencies = null): void
    {
        $dependencies['submissionBoxMapper'] = isset($dependencies['submissionBoxMapper']) ? $dependencies['submissionBoxMapper'] : SubmissionBoxMapper::getInstance();
        $dependencies['userMapper']          = isset($dependencies['userMapper']) ? $dependencies['userMapper'] : UserMapper::getInstance();
        $dependencies['mapper']              = isset($dependencies['mapper']) ? $dependencies['mapper'] : SubmissionMapper::getInstance();
        $dependencies['userAccessMapper']    = isset($dependencies['userAccessMapper']) ? $dependencies['userAccessMapper'] : UserAccessMapper::getInstance();

        parent::loadDependencies($dependencies);
    }


    /**
     * @param SubmissionBoxMapper $submissionBoxMapper
     * @return static
     */
    protected function setSubmissionBoxMapper(SubmissionBoxMapper $submissionBoxMapper): self
    {
        $this->submissionBoxMapper = $submissionBoxMapper;
        return $this;
    }


    /**
     * @param UserMapper $userMapper
     * @return static
     */
    protected function setUserMapper(UserMapper $userMapper): self
    {
        $this->userMapper = $userMapper;
        return $this;
    }


    /**
     * @param  UserAccessMapper $userAccessMapper
     * @return $this
     */
    public function setUserAccessMapper(UserAccessMapper $userAccessMapper): self
    {
        $this->userAccessMapper = $userAccessMapper;
        return $this;
    }


    /**
     * This notifier does not actually use future scheduling - all events fire immediately, if at all.
     *
     * @param TriggerBundle $bundle
     * @throws ScheduleException
     * @return void
     */
    public function bundleTriggers($bundle): void
    {
        $method     = $bundle->getMethod();
        $submission = $bundle->getEvent()->getObject();
        $diff       = $bundle->getEvent()->getDiff();

        if (!$submission instanceof SubmissionBox\Submission\Submission) {
            return;
        }

        $studentId = $submission->getOwner()->getId();
        $boxId     = $submission->getSubmissionBox()->getId();

        $mapper = $this->getMapper();

        switch ($method) {
            case Mapper::METHOD_CREATE:
                // is this in draft mode? if so, do nothing
                if ($submission->isDraft()) {
                    break;
                }

                // is this the first? or a resubmit?
                $counts = $mapper->findCountsForSubmissionBoxes(array($boxId), $studentId);
                // because '1' would be the first submission
                if (array_key_exists($boxId, $counts) && $counts[$boxId] > 1) {
                    $bundle->attach(self::TRIGGER_RESUBMITTED, self::IMMEDIATELY);
                    break;
                }
                $bundle->attach(self::TRIGGER_SUBMITTED, self::IMMEDIATELY);
                break;

            case Mapper::METHOD_DELETE: // submission deleted - if single-submit, student can submit again
                $owningBox = $submission->getSubmissionBox();
                // Don't trigger if box permits multi-submit
                if ($owningBox->getAcceptMultiple()) {
                    break;
                }
                $bundle->attach(self::TRIGGER_DELETED, self::IMMEDIATELY);
                break;

            case Mapper::METHOD_READ:
                break;

            case Mapper::METHOD_UPDATE:
                // if no diff, ignore
                if (!is_array($diff)) {
                    break;
                }
                // if we've gone from draft to not, notify submission
                if (array_key_exists('submittedAt', $diff) && $diff['submittedAt'][1] === null) {
                    // is this the first? or a resubmit?
                    $counts = $mapper->findCountsForSubmissionBoxes(array($boxId), $studentId);
                    if (array_key_exists($boxId, $counts) && $counts[$boxId] > 1) {
                        $bundle->attach(self::TRIGGER_RESUBMITTED, self::IMMEDIATELY);
                        break;
                    }
                    $bundle->attach(self::TRIGGER_SUBMITTED, self::IMMEDIATELY);
                }
                break;

            default:
                throw new ScheduleException("Unrecognised CRUD operation: '{$bundle->getMethod()}'.");
        }
    }


    /**
     * @param string $type
     * @param Submission $submission
     * @param User $effector
     * @param array|null $diff
     * @return Envelope
     */
    protected function submissionNotification(string $type, Submission $submission, User $effector, $diff = null): Envelope
    {
        $envelope = new Envelope();

        try {
            $box = $this->submissionBoxMapper->findById($submission->getSubmissionBox()->getId());
        } catch (Mapper\Exception $e) {
            return $envelope;
        }

        $folder = $box->getAssessmentModel()->getAssessmentContext()->getFolder();

        $boxId = $box->getId();

        $student = $submission->getOwner();
        $params = [
            'boxname' => new Param(
                $box->getAssessmentModel()->getTitle(),
                "/learning/assessments/{$box->getAssessmentId()}/{$student->getId()}"
            ),
            'catname' => new CategoryParam($folder),
            'type'    => new SimpleParam($type),
        ];

        if ($effector->getId() === $student->getId()) {
            $params['student'] = new UserParam($effector);
            $title = self::PATTERN_SUBMITTED_TITLE;
        } else {
            $title = self::PATTERN_SUBMITTED_ON_BEHALF_OF_TITLE;
            $params['teacher'] = new UserParam($effector);
            try {
                $student = $this->userMapper->findById($submission->getOwner()->getId());
            } catch (Mapper\Exception $e) {
                return $envelope;
            }
            $params['student'] = new UserParam($student);
        }

        $bob = new Builder();
        $bob->setTitle($title)
            ->setParams($params)
            ->setIcon($this->getUserIcon($student))
            ->setHref("/learning/assessments/{$box->getAssessmentId()}/{$student->getId()}")
            ->setNamespace($this->getSlug())
            ->setSeverity(Logger::NOTICE)
            ->addTopic("duework.$boxId.$type")
            ->setFilter([
                'roletype' => Role::STAFF
            ]);

        // send this message to teachers and to write-level group members.
        $envelope->broadcastToCategory($folder, UserAccess::ACCESS_WRITE, $bob->build());

        return $envelope;
    }


    /* ----------------------------------------------------------------------------------------------------------
     *  ASYNC WORKER METHODS
     * ---------------------------------------------------------------------------------------------------------- */

    /**
     * Notify a teacher that students have submitted to their submission box
     *
     * @param Submission $submission
     * @param User $user
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyStaffSubmitted(Submission $submission, User $user, $diff = null): Envelope
    {
        return $this->submissionNotification(self::TRIGGER_SUBMITTED, $submission, $user, $diff);
    }


    /**
     * Notify a teacher that a student has submitted to their submission box...again
     *
     * @param Submission $submission
     * @param User $user
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyStaffResubmitted(Submission $submission, User $user, $diff = null): Envelope
    {
        return $this->submissionNotification(self::TRIGGER_RESUBMITTED, $submission, $user, $diff);
    }


    /**
     * Notify a student that their (only) submission has been deleted, so they can resubmit.
     *
     * @param Submission $submission
     * @param User $user
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyStudentDeleted(Submission $submission, User $user, $diff = null): Envelope
    {
        $envelope = new Envelope();

        $student      = $submission->getOwner();
        $studentId    = $student->getId();

        try {
            $box = $this->submissionBoxMapper->findById($submission->getSubmissionBox()->getId());
        } catch (Mapper\Exception $e) {
            // Suppress notification if box is also deleted.
            return $envelope;
        }

        $worktype = $box->getAssessmentModel()->getWorkType();
        if ($worktype && $worktype->isStaffOrInstructorVisibleOnly()) {
            return $envelope;
        }

        $folder = $box->getAssessmentModel()->getAssessmentContext()->getFolder();

        $boxId = $box->getId();

        $bob = new Builder();

        $bob->setTitle(self::PATTERN_DELETED_TITLE)
            ->setParams(
                array(
                    'owner'   => new UserParam($user),
                    'boxname' => new Param(
                        $box->getAssessmentModel()->getTitle(),
                        "/learning/assessments/{$box->getAssessmentId()}/$studentId"
                    ),
                    'catname' => new CategoryParam($folder)
                )
            )
            ->setIcon($this->getUserIcon($user))
            ->setHref("/learning/assessments/{$box->getAssessmentId()}/$studentId")
            ->setNamespace($this->getSlug())
            ->setSeverity(Logger::NOTICE)
            ->addTopic("duework.$boxId.deleted")
            ->setFilter([
                'roletype' => Role::STUDENT
            ]);

        $envelope->targetToUser($studentId, $bob->build());

        return $envelope;
    }


    /**
     * Notify system that the submission has been deleted.
     * @param Submission $submission
     * @return Envelope
     */
    public function notifySystemDeleted(Submission $submission): Envelope
    {
        $envelope = new Envelope();
        return $this->retractEventObject($envelope, $submission);
    }
}
