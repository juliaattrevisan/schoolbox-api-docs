<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\Component;


use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Instance\Instance;
use Alaress\Async\Notification\Parameter\Param;
use Alaress\Async\Notification\Parameter\ParameterInterface;
use Alaress_Date_Relative;
use Monolog\Logger;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBox\SubmissionMapper;
use Schoolbox\Assessment\Course\Task;
use Schoolbox\Collection;
use Schoolbox\Feed\FeedableMapperInterface;
use Schoolbox\Feed\SourceInterface;
use Schoolbox\Mapper;
use Schoolbox\Notif\Message\Message;
use Schoolbox\Notif\Message\Messenger;
use Schoolbox\Notif\Notif;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Notification\Parameter\CategoryParam;
use Schoolbox\Realtime\Notification\Parameter\UserParam;
use Schoolbox\Realtime\Schedule\Event;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\ScheduleException;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundle;
use Schoolbox\Resource\CategoryMapper;
use Schoolbox\Role;
use Schoolbox\RoleMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

class SubmissionBoxNotifier extends AbstractModuleNotifier implements SourceInterface
{

    // list of triggers that can generate notifications

    const TRIGGER_OPEN        = 'open';
    const TRIGGER_UPDATED     = 'updated';
    const TRIGGER_UPCOMING    = 'upcoming';
    const TRIGGER_OVERDUE     = 'overdue';
    const TRIGGER_CLOSED      = 'closed';
    const TRIGGER_DATECHANGED = 'datechanged';
    const TRIGGER_RENAMED     = 'renamed';

    // this should be configurable, but this will do for now
    const UPCOMING_WINDOW = 2;


    /** @var UserMapper */
    protected $userMapper;

    /** @var SubmissionMapper */
    protected $submissionMapper;

    /** @var CategoryMapper */
    protected $categoryMapper;

    /** @var RoleMapper */
    protected $roleMapper;

    /** @var ReturnedWorkMapper */
    protected $returnedWorkMapper;

    /** @var \User */
    protected $_DEPRECATED_USER_HANDLER;

    /** @var \External_Class */
    protected $_DEPRECATED_EXTERNAL_CLASS_HANDLER;


    /**
     * Instantiate all your dependencies within yourself. Effectively a sub-constructor.
     *
     * @param mixed $dependencies
     * @return void
     */
    public function loadDependencies($dependencies = null)
    {
        $this->userMapper         = UserMapper::getInstance();
        $this->submissionMapper   = SubmissionMapper::getInstance();
        $this->categoryMapper     = CategoryMapper::getInstance();
        $this->roleMapper         = RoleMapper::getInstance();
        $this->returnedWorkMapper = ReturnedWorkMapper::getInstance();

        $this->_DEPRECATED_USER_HANDLER           = new \User();
        $this->_DEPRECATED_EXTERNAL_CLASS_HANDLER = new \External_Class();

        parent::loadDependencies($dependencies);
    }


    /**
     * @return string
     */
    protected function getSlug()
    {
        return NSMapper::NS_DUEWORK;
    }


    /**
     * @param TriggerBundle $bundle
     * @throws ScheduleException
     */
    public function bundleTriggers($bundle)
    {

        /** @var SubmissionBox $box */
        $box = $bundle->getEvent()
                      ->getObject();

        // check for detachment
        if ($bundle->getMethod() == Mapper::METHOD_DELETE) {
            $bundle->detachEach(
                   array(
                       self::TRIGGER_OPEN,
                       self::TRIGGER_OVERDUE,
                       self::TRIGGER_UPCOMING,
                       self::TRIGGER_CLOSED
                   )
            );
            return;
        }

        // can't work with nonexistent dates
        if (!($box->getOpenDate() && $box->getDueDate() && $box->getLateDate())) {
            return;
        }

        // ok, we're clear to attach some triggers now

        $open     = $box->getOpenDate()->getUnixTimestamp();
        $closed   = $box->getDueDate()->getUnixTimestamp();
        $upcoming = $closed - (self::UPCOMING_WINDOW * 3600);
        $late     = $box->getLateDate()->getUnixTimestamp();

        switch ($bundle->getMethod()) {
            case Mapper::METHOD_UPDATE:

                // don't spawn/replace an open-on-replace if it's in the past
                if ($box->getOpenDate()->getUnixTimestamp() > time()) {
                    $bundle->attach(self::TRIGGER_OPEN, $open);
                }

                $bundle->attachEach(
                       array(
                           self::TRIGGER_UPDATED  => self::IMMEDIATELY,
                           self::TRIGGER_OVERDUE  => $closed,
                           self::TRIGGER_UPCOMING => $upcoming,
                           self::TRIGGER_CLOSED   => $late
                       )
                );
                break;

            case Mapper::METHOD_CREATE:
                $bundle->attachEach(
                       array(
                           self::TRIGGER_OPEN     => $open,
                           self::TRIGGER_OVERDUE  => $closed,
                           self::TRIGGER_UPCOMING => $upcoming,
                           self::TRIGGER_CLOSED   => $late
                       )
                );
                break;
        }

    }


    /**
     * Returns the metadata pertaining to this source.
     * Useful to RSS
     *
     * @return mixed
     */
    public function getChannelMetadata()
    {
        return null;
    }


    /**
     * Generate n Instances, from the perspective of casual observation (rather than targeted at a particular user).
     *
     * @param int    $context  - most likely a category ID or ignored
     * @param string $roletype - the roletype of the user for whom this feed is being retrieved
     * @param int    $limit    - how many messages to retrieve
     * @param int    $offset   - by default, most recent
     * @return Collection|Instance[]
     */
    public function getFeed($context, $roletype, $limit = self::HARD_LIMIT, $offset = 0)
    {
        $c = new Collection();

        // if parents aren't allowed to see due work, return an empty collection
        if ($roletype == Role::PARENT && !$this->conf->get('restrict_parent_duework_access')) {
            return $c;
        }

        // be sure the target method exists
        if (!$this->mapper instanceof FeedableMapperInterface) {
            return $c;
        }

        /** @var Collection|SubmissionBox[] $boxes */
        $boxes = $this->mapper->findForFeed($context, $limit, $offset);

        foreach ($boxes as $box) {
            $c->merge(new Collection($this->notifyStudentOpen($box, $box->getOwner())->getAll($box->getOpenDate()->getUnixTimestamp())));
            $c->merge(new Collection($this->notifyFeedClosed($box, $box->getOwner())->getAll($box->getDueDate()->getUnixTimestamp())));
        }

        return $c;
    }


    /**
     * @param SubmissionBox $submissionBox
     * @return User[]|Collection
     */
    protected function getStudentsUnsubmittedList($submissionBox)
    {
        $userCollection = UserAccessMapper::getInstance()->findStudentsInCategory($submissionBox->getCategory());

        $submissionCollection = $this->submissionMapper->findByColumnList(array(
            'box_id' => $submissionBox->getId()
        ));

        // extract the owner column from the submission collection returned
        $submittedCollection = new Collection($submissionCollection->getColumnValues('owner'));
        $submittedIdArray    = $submittedCollection->getColumnValues('id');

        $unsubmittedCollection = new Collection();

        foreach ($userCollection as $user) {
            if (!in_array($user->getId(), $submittedIdArray)) {
                $unsubmittedCollection->add($user);
            }
        }

        return $unsubmittedCollection;
    }


    /* TRIGGER EVENTS */

    /**
     * Student notified when SubmissionBox opens.
     *
     * Returns an array where the keys are the messages to send, and the values
     * are arrays of topic on which those messages are to be published.
     *
     * @var SubmissionBox $submissionBox
     * @param User        $user
     * @param array       $diff
     * @return Envelope
     */
    public function notifyStudentOpen($submissionBox, $user, $diff = null)
    {
        $envelope = new Envelope();
        $bob      = new Builder();

        // re-hydrate, because manual hydration, because fail -_-
        $boxId = $submissionBox->getId();

        /** @var SubmissionBox $submissionBox */
        $submissionBox = $this->mapper->findById($boxId);

        $boxType = $submissionBox->getWorkType()->getName();
        $boxName = $submissionBox->getName();

        $authorExtId = $user->getExternalId();

        $cat   = $submissionBox->getCategory();
        $catId = $cat->getId();

        $indefArticle = in_array(strtolower($boxType[0]), array('a', 'e', 'i', 'o', 'u')) ? 'an' : 'a';

        $bob->setTitle(":user opened $indefArticle $boxType :box in :cat")
            ->setParams(array(
                'user' => new UserParam($user, ParameterInterface::STACKING_REPLACE),
                'box'  => new Param($boxName, "/submission/submissionHistory/$boxId"),
                'cat'  => new CategoryParam($cat)
            ))
            ->setHref("/submission/submissionHistory/$boxId")
            ->setIcon("/portrait?userId=$authorExtId&size=square32")
            ->setNamespace($this->getSlug())
            ->addTopic("duework.$boxId.open")
            ->addFilter('roletype', array(Role::STUDENT))
            ->setBody($submissionBox->getDescription())
            ->setByline("Due " . $submissionBox->getDueDate()->getCalendarDateTime())
            ->setSeverity(Logger::NOTICE);

        $envelope->broadcastToCategory($catId, UserAccess::ACCESS_READ, $bob->build());

        return $envelope;
    }


    /**
     * Student notified when a SubmissionBox is updated
     *
     * @param SubmissionBox $submissionBox
     * @param User          $user
     * @param array         $diff
     * @return Messenger
     */
    public function notifyStudentUpdated($submissionBox, $user, $diff = null)
    {
        $msgr = new Messenger();

        $boxId = $submissionBox->getId();

        /** @var SubmissionBox $submissionBox */
        $submissionBox = $this->mapper->findById($boxId);

        // discard all update notifications if the submissionbox isn't open (EXCEPT work returns opening up)
        $openStamp  = $submissionBox->getOpenDate()->getUnixTimestamp();
        $closeStamp = $submissionBox->getDueDate()->getUnixTimestamp();
        $now        = time();

        // if the box hasn't even opened yet, we don't want to know about it at all
        if ($openStamp > $now) {
            return $msgr;
        }

        // INCREASE in mark visibility (box can be closed in this instance)
        // doesn't actually create messages, just triggers for others to be created
        if (isset($diff['markVisibility'])) {
            list($new, $old) = $diff['markVisibility'];
            if ($new > $old) {
                // for anyone who has marks given, raise the duework.returned notification
                /** @var Collection|ReturnedWork[] $returns */
                $returns = $this->returnedWorkMapper->findByColumn('box_id', $submissionBox->getId());
                foreach ($returns as $return) {
                    // simulate a save event, so as to spawn notifications for returned work
                    $n = new Notif(Notif::TOPIC_APPLICATION);
                    $n->notice(serialize(new Event(
                              $return->getMarker(), Mapper::METHOD_CREATE,
                              $return, $this->returnedWorkMapper, null
                    )));
                }
            }
            unset($diff['markVisibility']);
        }

        // if the box is closed, or there's no remaining diff, we're done here
        if ($closeStamp < $now || !count($diff)) {
            return $msgr;
        }

        $boxName = $submissionBox->getName();

        $cat   = $submissionBox->getCategory();
        $catId = $cat->getId();

        $authorId    = $user->getId();
        $authorExtId = $user->getExternalId();
        $authorName  = $user->formatName();

        $icon = "/portrait?userId=$authorExtId&size=square32";
        $href = "/submission/submissionHistory/$boxId";

        /* use the diff to establish what changed */

        // renamed
        if (isset($diff['name'])) {
            // parameterised text, link and icon
            $msg = new Message(":author renamed :oldname to :newname in :catname", $icon, $href);

            // params for this instance - oldname doesn't have an associated href
            $params = array(
                'author'  => array($authorName, "/search/user/$authorId"),
                'oldname' => $diff['name'][1],
                'newname' => array($diff['name'][0], "/submission/submissionHistory/$boxId"),
                'catname' => array($cat->getName(), "/homepage/$catId")
            );

            $msgr->broadcastToCategory($catId, $msg, "duework.$boxId.renamed", Logger::NOTICE, $params, array('roletype' => Role::STUDENT));

            unset($diff['name']);
        }

        // extended or shortened
        if (isset($diff['dueDate'])) {
            // strtotime is safe here because dates are in 'YYYY-MM-DD H:i:s' format
            $oldDate = strtotime($diff['dueDate'][1]);
            $newDate = strtotime($diff['dueDate'][0]);

            $verb       = $newDate > $oldDate ? 'extended' : 'changed the due date of';
            $relative   = new Alaress_Date_Relative($newDate);
            $dateformat = ((int)$relative->getUsingFormat('Hi') == '1159' ? 'g:ia \o\n ' : '') . 'D, jS M';

            $msg = new Message(":author $verb :boxname to :date in :catname", $icon, $href);

            $msgr->broadcastToCategory($catId, $msg, "duework.$boxId.datechanged", Logger::NOTICE,
                array(
                    'author'  => array($authorName, "/search/user/$authorId"),
                    'boxname' => array($boxName, "/submission/submissionHistory/$boxId"),
                    'date'    => date($dateformat, $relative->getUnixTimestamp()),
                    'catname' => array($cat->getName(), "/homepage/$catId")
                ),
                array(
                    'roletype' => Role::STUDENT
                )
            );

            unset($diff['dueDate']);
        }

        // changed accepting online submissions
        if (isset($diff['acceptOnline'])) {
            if ($submissionBox->getAcceptOnline()) {
                $m = "You now need to submit online for :boxname in :catname";
            } else {
                $m = "You no longer need to submit online for :boxname in :catname";
            }

            $msg = new Message($m, $icon, $href);

            $msgr->broadcastToCategory($catId, $msg, "duework.$boxId.updated", Logger::NOTICE,
                array(
                    'boxname' => array($boxName, "/submission/submissionHistory/$boxId"),
                    'catname' => array($cat->getName(), "/homepage/$catId")
                ),
                array(
                    'roletype' => Role::STUDENT
                )
            );

            unset($diff['acceptOnline']);
        }

        // if none of the above apply, but there are changes, just be generic
        if (count($diff) && $msgr->isEmpty()) {
            $msg = new Message(":author updated :boxname in :catname", $icon, $href);

            $msgr->broadcastToCategory($catId, $msg, "duework.$boxId.updated", Logger::NOTICE,
                array(
                    'author'  => array($authorName, "/search/user/$authorId"),
                    'boxname' => array($boxName, "/submission/submissionHistory/$boxId"),
                    'catname' => array($cat->getName(), "/homepage/$catId")
                ),
                array(
                    'roletype' => Role::STUDENT
                )
            );
        }

        return $msgr;
    }


    /**
     * Student notified when a SubmissionBox is closing AND they have not submitted
     *
     * @param SubmissionBox $submissionBox
     * @param User          $user
     * @param array         $diff
     * @return Messenger
     */
    public function notifyStudentUpcoming($submissionBox, $user, $diff = null)
    {
        $msgr = new Messenger();

        $boxId = $submissionBox->getId();

        /** @var SubmissionBox $submissionBox */
        $submissionBox = $this->mapper->findById($boxId);

        // don't raise notifications if this box doesn't accept online submissions
        if (!$submissionBox->getAcceptOnline()) {
            return $msgr;
        }

        $boxName = $submissionBox->getName();

        $cat     = $this->categoryMapper->findById($submissionBox->getCategory()->getId());
        $catId   = $cat->getId();
        $catName = $cat->getName();

        $icon = '/images/icon.php?size=32&icon=work';
        $href = "/submission/submissionHistory/$boxId";

        $when = self::UPCOMING_WINDOW . " hour" . self::UPCOMING_WINDOW == 1 ? '' : 's';
        $msg  = new Message(":boxname is due in $when in :catname", $icon, $href);

        $unsubmittedStudents = $this->getStudentsUnsubmittedList($submissionBox);

        foreach ($unsubmittedStudents->getColumnValues('id') as $id) {
            $msgr->targetToUser($id, $msg, "duework.$boxId.upcoming", Logger::WARNING,
                array(
                    'boxname' => array($boxName, $href),
                    'catname' => array($catName, "/homepage/$catId"),
                )
            );
        }
        return $msgr;
    }


    /**
     * Parent notified when a SubmissionBox is closing AND their child has not submitted
     *
     * @param SubmissionBox $submissionBox
     * @param User          $user
     * @param array         $diff
     * @return Messenger
     */
    public function notifyParentUpcoming($submissionBox, $user, $diff = null)
    {
        $msgr = new Messenger();

        $boxId = $submissionBox->getId();

        /** @var SubmissionBox $submissionBox */
        $submissionBox = $this->mapper->findById($boxId);

        // don't raise notifications if this box doesn't accept online submissions
        if (!$submissionBox->getAcceptOnline()) {
            return $msgr;
        }

        $boxName = $submissionBox->getName();
        $catId   = $submissionBox->getCategory()->getId();
        $catName = $submissionBox->getCategory()->getName();

        // get a list of students who haven't submitted
        $unsubmittedStudents = $this->getStudentsUnsubmittedList($submissionBox);

        foreach ($unsubmittedStudents as $student) {
            $childName  = $student->getFirstname();
            $childId    = $student->getId();
            $childExtId = $student->getExternalId();

            // target the parents of this particular student

            $icon = "/portrait?userId=$childExtId&size=square32";
            $href = "/submission/submissionHistory/$boxId/$childId";

            $msg = new Message(":child has not yet submitted work for :boxname in :catname", $icon, $href);

            // don't provide a link for parents to click on if it's just going to give the a 403
            $boxdata = $this->conf->get('restrict_student_submissions')
                ? $boxName
                : array($boxName, "/submission/submissionHistory/$boxId");

            $msgr->targetToParent($childId, $msg, "duework.$boxId.upcoming", Logger::WARNING,
                array(
                    'child'   => array($childName, "/search/user/$childId"),
                    'boxname' => $boxdata,
                    'catname' => array($catName, "/homepage/$catId")
                )
            );
        }

        return $msgr;
    }


    /**
     * Notify the feed of box closures
     *
     * @param SubmissionBox $submissionBox
     * @param User          $user
     * @param array         $diff
     * @return Messenger
     */
    public function notifyFeedClosed($submissionBox, $user, $diff = null)
    {
        $msgr = new Messenger();

        $boxId = $submissionBox->getId();

        /** @var SubmissionBox $submissionBox */
        $submissionBox = $this->mapper->findById($boxId);

        // don't raise notifications if this box doesn't accept online submissions
        if (!$submissionBox->getAcceptOnline() || $submissionBox->getDueDate()->getUnixTimestamp() > time()) {
            return $msgr;
        }

        $boxName = $submissionBox->getName();
        $catId   = $submissionBox->getCategory()->getId();
        $catName = $submissionBox->getCategory()->getName();

        $icon = '/images/icon.php?size=32&icon=cross';
        $href = "/submission/submissionHistory/$boxId";

        $msg = new Message("Submissions have closed for :boxname in :catname", $icon, $href);

        $msgr->broadcastToCategory($catId, $msg, "duework.$boxId.closed", Logger::NOTICE,
            array(
                'boxname' => array($boxName, $href),
                'catname' => array($catName, "/homepage/$catId"),
            )
        );

        return $msgr;
    }


    /**
     * Student notified when a SubmissionBox is overdue AND they have not submitted
     *
     * @param SubmissionBox $submissionBox
     * @param User          $user
     * @param array         $diff
     * @return Messenger
     */
    public function notifyStudentOverdue($submissionBox, $user, $diff = null)
    {
        $msgr = new Messenger();

        $boxId = $submissionBox->getId();

        /** @var SubmissionBox $submissionBox */
        $submissionBox = $this->mapper->findById($boxId);

        // don't raise notifications if this box doesn't accept online submissions
        if (!$submissionBox->getAcceptOnline()) {
            return $msgr;
        }

        // if the late date has passed, ignore this message
        if ($submissionBox->getLateDate()->getUnixTimestamp() > time()) {
            return $msgr;
        }

        $boxName = $submissionBox->getName();

        $cat     = $submissionBox->getCategory();
        $catId   = $cat->getId();
        $catName = $cat->getName();

        $icon = '/images/icon.php?size=32&icon=work';
        $href = "/submission/submissionHistory/$boxId";

        $msg = new Message(":boxname is now overdue in :catname", $icon, $href);

        $unsubmittedStudents = $this->getStudentsUnsubmittedList($submissionBox);

        foreach ($unsubmittedStudents as $user) {
            $msgr->targetToUser($user->getId(), $msg, "duework.$boxId.overdue", Logger::ERROR,
                array(
                    'boxname' => array($boxName, $href),
                    'catname' => array($catName, "/homepage/$catId"),
                )
            );
        }

        return $msgr;
    }


    /**
     * Parent notified when a SubmissionBox is overdue AND their child has not submitted
     *
     * @param SubmissionBox $submissionBox
     * @param User          $user
     * @param array         $diff
     * @return Messenger
     */
    public function notifyParentOverdue($submissionBox, $user, $diff = null)
    {
        $msgr = new Messenger();

        $boxId = $submissionBox->getId();

        /** @var SubmissionBox $submissionBox */
        $submissionBox = $this->mapper->findById($boxId);

        // don't raise notifications if this box doesn't accept online submissions
        if (!$submissionBox->getAcceptOnline()) {
            return $msgr;
        }

        $boxName = $submissionBox->getName();
        $catId   = $submissionBox->getCategory()
                                 ->getId();
        $catName = $submissionBox->getCategory()
                                 ->getName();

        $icon = '/images/icon.php?size=32&icon=work';
        $href = "/submission/submissionHistory/$boxId";

        $msg = new Message(":child did not submit work for :boxname in :catname", $icon, $href);

        // get a list of students who haven't submitted, and get their parents too
        $unsubmittedStudents = $this->getStudentsUnsubmittedList($submissionBox);

        foreach ($unsubmittedStudents as $student) {
            $childName = $student->getFirstname();
            $childId   = $student->getId();

            $msgr->targetToParent($childId, $msg, "duework.$boxId.overdue", Logger::ERROR,
                array(
                    'child'   => array($childName, "/search/user/$childId"),
                    'boxname' => array($boxName, "/submission/submissionHistory/$boxId"),
                    'catname' => array($catName, "/homepage/$catId")
                )
            );
        }

        return $msgr;
    }


    /**
     * Staff notified when a SubmissionBox late-date has passed AND students did not submit
     *
     * @param SubmissionBox $submissionBox
     * @param User          $user
     * @param array         $diff
     * @return Messenger
     */
    public function notifyStaffClosed($submissionBox, $user, $diff = null)
    {
        $msgr = new Messenger();

        $boxId = $submissionBox->getId();

        /** @var SubmissionBox $submissionBox */
        $submissionBox = $this->mapper->findById($boxId);

        // don't raise notifications if this box doesn't accept online submissions
        if (!$submissionBox->getAcceptOnline()) {
            return $msgr;
        }

        $totalUnsubmitted = $this->getStudentsUnsubmittedList($submissionBox)
                                 ->count();

        // everyone submitted! woohoo!
        if ($totalUnsubmitted == 0) {
            return $msgr;
        }

        $icon = '/images/icon.php?size=32&icon=work';
        $href = "/submission/viewSubmission/$boxId";

        $msg = new Message(":boxname has closed and :number did not submit work", $icon, $href);

        $msgr->targetToUser($user->getId(), $msg, "duework.$boxId.closed", Logger::ERROR,
            array(
                'boxname' => array($submissionBox->getName(), "/submission/viewSubmission/$boxId"),
                'number'  => sprintf("%s student%s", $totalUnsubmitted, $totalUnsubmitted == 1 ? '' : 's')
            )
        );

        return $msgr;
    }

}
