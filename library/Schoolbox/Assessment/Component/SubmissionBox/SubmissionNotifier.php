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
use Schoolbox\Mapper;
use Schoolbox\Notif\Message\Message;
use Schoolbox\Notif\Message\Messenger;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Notification\Parameter\CategoryParam;
use Schoolbox\Realtime\Notification\Parameter\UserParam;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\ScheduleException;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundle;
use Schoolbox\Resource\Folder;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Notifier that is used when students submit work to a submission box.
 *
 * Class SubmissionNotifier
 * @package Schoolbox\Assessment\Component\SubmissionBox
 */
class SubmissionNotifier extends AbstractModuleNotifier
{

    const TRIGGER_SUBMITTED   = 'submitted';
    const TRIGGER_RESUBMITTED = 'resubmitted';
    const TRIGGER_DELETED     = 'deleted';

    /** @var SubmissionBoxMapper */
    protected $submissionBoxMapper;

    /** @var UserMapper */
    protected $userMapper;

    /** @var Folder\Mapper */
    protected $folderMapper;


    /**
     * @return string
     */
    protected function getSlug()
    {
        return NSMapper::NS_DUEWORK;
    }


    /**
     * Instantiate all your dependencies within yourself.
     *
     * @param mixed $dependencies
     * @return void
     */
    public function loadDependencies($dependencies = null)
    {
        $dependencies['submissionBoxMapper'] = $dependencies['submissionBoxMapper'] ?: SubmissionBoxMapper::getInstance();
        $dependencies['userMapper']          = $dependencies['userMapper'] ?: UserMapper::getInstance();
        $dependencies['folderMapper']        = $dependencies['folderMapper'] ?: Folder\Mapper::getInstance();
        $dependencies['mapper']              = $dependencies['mapper'] ?: Submission\FileMapper::getInstance();

        parent::loadDependencies($dependencies);
    }


    /**
     * @param Folder\Mapper $folderMapper
     * @return static
     */
    protected function setFolderMapper($folderMapper)
    {
        $this->folderMapper = $folderMapper;
        return $this;
    }


    /**
     * @param SubmissionBoxMapper $submissionBoxMapper
     * @return static
     */
    protected function setSubmissionBoxMapper($submissionBoxMapper)
    {
        $this->submissionBoxMapper = $submissionBoxMapper;
        return $this;
    }


    /**
     * @param UserMapper $userMapper
     * @return static
     */
    protected function setUserMapper($userMapper)
    {
        $this->userMapper = $userMapper;
        return $this;
    }


    /**
     * This notifier does not actually use future scheduling - all events fire immediately, if at all.
     *
     * @param TriggerBundle $bundle
     * @throws ScheduleException
     * @return void
     */
    public function bundleTriggers($bundle)
    {
        $method     = $bundle->getMethod();
        $submission = $bundle->getEvent()->getObject();
        $diff       = $bundle->getEvent()->getDiff();

        if (!$submission instanceof SubmissionBox\Submission\File) {
            return;
        }

        $studentId = $submission->getOwner()->getId();
        $boxId     = $submission->getSubmissionBox()->getId();

        /** @var SubmissionMapper $mapper */
        $mapper = $this->mapper;

        switch ($method) {
            case Mapper::METHOD_CREATE: // new submission created

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
                /** @var SubmissionBox $owningBox */
                $owningBox = $this->submissionBoxMapper->findById($submission->getSubmissionBox()->getId());
                // if multi-submit, don't notify
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
                if (array_key_exists('draft', $diff) && $diff['draft'] == true) {
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
     * @param Submission\File $submission
     * @param User $user
     * @param array|null $diff
     * @return Messenger
     */
    protected function submissionNotification($type, $submission, $user, $diff = null)
    {
        $envelope = new Envelope();

        $studentExtId = $user->getExternalId();

        $box = $this->submissionBoxMapper->findById($submission->getSubmissionBox()->getId());
        $cat = $this->folderMapper->findById($box->getCategory()->getId());

        $boxId = $box->getId();

        $bob = new Builder();

        $bob->setTitle(":student $type work to :boxname in :catname")
            ->setParams(
                array(
                    'student' => new UserParam($user),
                    'boxname' => new Param($box->getName(), "/submission/viewSubmission/$boxId"),
                    'catname' => new CategoryParam($cat)
                )
            )
            ->setIcon("/portrait?userId=$studentExtId&size=square32")
            ->setHref("/submission/viewSubmission/$boxId")
            ->setNamespace($this->getSlug())
            ->setSeverity(Logger::NOTICE)
            ->addTopic("duework.$boxId.$type");

        // send this message to the owner of the submission box
        $envelope->targetToUser($box->getOwner(), $bob->build());

        return $envelope;
    }


    /* ----------------------------------------------------------------------------------------------------------
     *  ASYNC WORKER METHODS
     * ---------------------------------------------------------------------------------------------------------- */

    /**
     * Notify a teacher that students have submitted to their submission box
     *
     * @param Submission\File $submission
     * @param User $user
     * @param array|null $diff
     * @return Messenger
     */
    public function notifyStaffSubmitted($submission, $user, $diff = null)
    {
        return $this->submissionNotification(self::TRIGGER_SUBMITTED, $submission, $user, $diff);
    }


    /**
     * Notify a teacher that a student has submitted to their submission box...again
     *
     * @param Submission\File $submission
     * @param User $user
     * @param array|null $diff
     * @return Messenger
     */
    public function notifyStaffResubmitted($submission, $user, $diff = null)
    {
        return $this->submissionNotification(self::TRIGGER_RESUBMITTED, $submission, $user, $diff);
    }


    /**
     * Notify a student that their (only) submission has been deleted, so they can resubmit.
     *
     * @param Submission\File $submission
     * @param User $user
     * @param array|null $diff
     * @return Messenger
     */
    public function notifyStudentDeleted($submission, $user, $diff = null)
    {
        $msgr = new Messenger();

        $student   = $submission->getOwner();
        $studentId = $student->getId();

        $box   = $this->submissionBoxMapper->findById($submission->getSubmissionBox()->getId());
        $boxId = $box->getId();

        $cat   = $this->folderMapper->findById($box->getCategory()->getId());
        $catId = $cat->getId();

        $ownerId    = $user->getId();
        $ownerExtId = $user->getExternalId();

        $icon = "/portrait?userId=$ownerExtId&size=square32";
        $href = "/submission/submissionHistory/$boxId/$studentId";

        $msg = new Message(":owner removed your previous submission to :boxname in :catname, you can now resubmit",
            $icon, $href
        );

        $params = array(
            'owner'   => array($user->formatName(), "/search/user/$ownerId"),
            'boxname' => array($box->getName(), "/submission/submissionHistory/$boxId/$studentId"),
            'catname' => array($cat->getName(), "/homepage/$catId"),
        );

        $msgr->targetToUser($studentId, $msg, "duework.$boxId.unsubmitted", Logger::NOTICE, $params);

        return $msgr;
    }

}