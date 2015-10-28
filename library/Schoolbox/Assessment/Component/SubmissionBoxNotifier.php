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
use Alaress_Date_Format;
use Monolog\Logger;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\FileMapper as SubmissionMapper;
use Schoolbox\Assessment\Course\Task;
use Schoolbox\Collection;
use Schoolbox\Feed\FeedableMapperInterface;
use Schoolbox\Feed\SourceInterface;
use Schoolbox\Mapper;
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
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundleInterface;
use Schoolbox\Resource\Folder\Mapper as CategoryMapper;
use Schoolbox\Role;
use Schoolbox\User;

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

    /** @var SubmissionMapper */
    protected $submissionMapper;

    /** @var CategoryMapper */
    protected $categoryMapper;

    /** @var ReturnedWorkMapper */
    protected $returnedWorkMapper;

    /**
     * Instantiate all your dependencies within yourself. Effectively a sub-constructor.
     *
     * @param mixed $dependencies
     * @return void
     */
    public function loadDependencies($dependencies = null)
    {
        if(empty($dependencies['submissionMapper'])) {
            $dependencies['submissionMapper'] = SubmissionMapper::getInstance();
        }
        if(empty($dependencies['categoryMapper'])) {
            $dependencies['categoryMapper'] = CategoryMapper::getInstance();
        }
        if(empty($dependencies['returnedWorkMapper'])) {
            $dependencies['returnedWorkMapper'] = ReturnedWorkMapper::getInstance();
        }
        parent::loadDependencies($dependencies);
    }


    /**
     * @param SubmissionMapper $submissionMapper
     * @return self
     */
    protected function setSubmissionMapper($submissionMapper)
    {
        $this->submissionMapper = $submissionMapper;
        return $this;
    }


    /**
     * @param ReturnedWorkMapper $returnedWorkMapper
     * @return self
     */
    protected function setReturnedWorkMapper($returnedWorkMapper)
    {
        $this->returnedWorkMapper = $returnedWorkMapper;
        return $this;
    }


    /**
     * @param CategoryMapper $categoryMapper
     * @return self
     */
    protected function setCategoryMapper($categoryMapper)
    {
        $this->categoryMapper = $categoryMapper;
        return $this;
    }



    /**
     * @return string
     */
    protected function getSlug()
    {
        return NSMapper::NS_DUEWORK;
    }


    /**
     * @param TriggerBundleInterface $bundle
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
        if ($roletype == Role::PARENT && $this->conf->get('restrict_parent_duework_access')) {
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
        $folder = $this->categoryMapper->findById($submissionBox->getCategory()->getId());

        // full list of students in this folder
        $userCollection = UserAccessMapper::getInstance()->findStudentsInCategory($folder);

        // list of submissions to this submissions box
        $submissionCollection = $this->submissionMapper->findByColumnList(array(
            'box_id' => $submissionBox->getId()
        ));

        // extract the owner column from the submission collection returned
        $submittedCollection = new Collection($submissionCollection->getColumnValues('owner'));
        $submittedIdArray    = $submittedCollection->getColumnValues('id');

        // list of returns to this submission box
        $returnCollection = $this->returnedWorkMapper->findByColumnList(array(
            'box_id' => $submissionBox->getId()
        ));

        // extract the student (owner) column from the return collection returned
        $returnedCollection = new Collection($returnCollection->getColumnValues('owner'));
        $returnedIdArray    = $returnedCollection->getColumnValues('id');

        $unsubmittedCollection = new Collection();

        foreach ($userCollection as $user) {
            // if the student in question is in neither the submission array nor the return array, add them to the list
            if (!in_array($user->getId(), $submittedIdArray) && !in_array($user->getId(), $returnedIdArray)) {
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
            ->setIcon("/portrait?userId=$authorExtId&size={$this->iconSize}")
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
     * @return Envelope
     */
    public function notifyStudentUpdated($submissionBox, $user, $diff = null)
    {
        $envelope = new Envelope();
        $bob = new Builder();

        $boxId = $submissionBox->getId();

        /** @var SubmissionBox $submissionBox */
        $submissionBox = $this->mapper->findById($boxId);

        // discard all update notifications if the submissionbox isn't open (EXCEPT work returns opening up)
        $openStamp  = $submissionBox->getOpenDate()->getUnixTimestamp();
        $closeStamp = $submissionBox->getDueDate()->getUnixTimestamp();
        $now        = time();

        // if the box hasn't even opened yet, we don't want to know about it at all
        if ($openStamp > $now) {
            return $envelope;
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
            return $envelope;
        }

        $bob->setIcon("/portrait?userId={$user->getExternalId()}&size=square64")
            ->setHref("/submission/submissionHistory/$boxId")
            ->setNamespace($this->getSlug())
            ->setSeverity(Logger::NOTICE)
            ->addFilter('roletype', Role::STUDENT);

        // renamed
        if (!empty($diff['name'])) {
            $envelope->broadcastToCategory(
                $submissionBox->getCategory(),
                UserAccess::ACCESS_READ,
                $this->setForNotifyRenamed($bob, $submissionBox, $diff, $user)->build()
            );
        }
        unset($diff['name']);

        // extended or shortened
        if (!empty($diff['dueDate'])) {
            $envelope->broadcastToCategory(
                $submissionBox->getCategory(),
                UserAccess::ACCESS_READ,
                $this->setForNotifyDueDateChanged($bob, $submissionBox, $diff, $user)->build()
            );
        }
        unset($diff['dueDate']);

        // changed accepting online submissions
        if (!empty($diff['acceptOnline'])) {
            $envelope->broadcastToCategory(
                $submissionBox->getCategory(),
                UserAccess::ACCESS_READ,
                $this->setForNotifyAcceptOnlineChanged($bob, $submissionBox)->build()
            );
        }
        unset($diff['acceptOnline']);

        // if none of the above apply, but there are changes, just be generic
        if ($envelope->count() == 0 && count($diff)) {
            $envelope->broadcastToCategory(
                $submissionBox->getCategory(),
                UserAccess::ACCESS_READ,
                $this->setForNotifyUpdated($bob, $submissionBox, $user)->build()
            );
        }

        return $envelope;
    }

    /**
     * Sets up Bob with the required params for notifying about a rename.
     *
     * @param Builder       $bob
     * @param SubmissionBox $box
     * @param array         $diff
     * @param User          $author
     *
     * @return Builder
     */
    protected function setForNotifyRenamed($bob, $box, $diff, $author)
    {
        return $bob->setTitle(":author renamed :oldname to :newname in :catname")
            // params for this instance - oldname doesn't have an associated href
            ->setParams(array(
                'author'  => new UserParam($author),
                'oldname' => new Param($diff['name'][1]),
                'newname' => new Param($diff['name'][0], "/submission/submissionHistory/" . $box->getId()),
                'catname' => new CategoryParam($box->getCategory())
            ))
            ->setTopics(array("duework.{$box->getId()}.renamed"));
    }

    /**
     * Sets up Bob with the required params for notifying about a due date change.
     *
     * @param Builder       $bob
     * @param SubmissionBox $box
     * @param array         $diff
     * @param User          $author
     *
     * @return Builder
     */
    protected function setForNotifyDueDateChanged($bob, $box, $diff, $author)
    {
        // strtotime is safe here because dates are in 'YYYY-MM-DD H:i:s' format
        $oldDate    = strtotime($diff['dueDate'][1]);
        $newDate    = strtotime($diff['dueDate'][0]);
        $verb       = $newDate > $oldDate ? 'extended' : 'changed the due date of';
        $relative   = new Alaress_Date_Format($newDate, 'U');

        $dateformat = $relative->getLongDateOnly();
        if (!$relative->isAtEndOfDay()) {
            $dateformat = $relative->getTimeOnly() .' on '. $dateformat;
        }

        return $bob->setTitle(":author $verb :boxname to :date in :catname")
            ->setTopics(array("duework.{$box->getId()}.datechanged"))
            ->setParams(array(
                'author'  => new UserParam($author),
                'boxname' => new Param($box->getName(), "/submission/submissionHistory/" . $box->getId()),
                'date'    => new Param($dateformat),
                'catname' => new CategoryParam($box->getCategory())
            ));
    }

    /**
     * Sets up Bob with the required params for notifying about accept online change.
     *
     * @param Builder       $bob
     * @param SubmissionBox $box
     *
     * @return Builder
     */
    protected function setForNotifyAcceptOnlineChanged($bob, $box)
    {
        if ($box->getAcceptOnline()) {
            $m = "You now need to submit online for :boxname in :catname";
        } else {
            $m = "You no longer need to submit online for :boxname in :catname";
        }

        return $bob->setTitle($m)
            ->setParams(array(
                'boxname' => new Param($box->getName(), "/submission/submissionHistory/{$box->getId()}"),
                'catname' => new CategoryParam($box->getCategory())
            ))
            ->setTopics(array("duework.{$box->getId()}.updated"));
    }

    /**
     * Sets up Bob with the required params for generic update notify.
     *
     * @param Builder       $bob
     * @param SubmissionBox $box
     * @param User          $author
     *
     * @return Builder
     */
    protected function setForNotifyUpdated($bob, $box, $author)
    {
        return $bob->setTitle(":author updated :boxname in :catname")
            ->setParams(array(
                'author'  => new UserParam($author),
                'boxname' => new Param($box->getName(), "/submission/submissionHistory/" . $box->getId()),
                'catname' => new CategoryParam($box->getCategory())
            ))
            ->setTopics(array("duework.{$box->getId()}.updated"));
    }


    /**
     * Student notified when a SubmissionBox is closing AND they have not submitted
     *
     * @param SubmissionBox $submissionBox
     * @param User          $user
     * @param array         $diff
     * @return Envelope
     */
    public function notifyStudentUpcoming($submissionBox, $user, $diff = null)
    {
        $envelope = new Envelope();
        $bob      = new Builder();
        $boxId    = $submissionBox->getId();

        /** @var SubmissionBox $submissionBox */
        $submissionBox = $this->mapper->findById($boxId);

        // don't raise notifications if this box doesn't accept online submissions
        if (!$submissionBox->getAcceptOnline()) {
            return $envelope;
        }

        $boxName = $submissionBox->getName();
        $cat     = $this->categoryMapper->findById($submissionBox->getCategory()->getId());
        $href    = "/submission/submissionHistory/$boxId";
        $when    = self::UPCOMING_WINDOW . " hour" . (self::UPCOMING_WINDOW == 1 ? '' : 's');

        $bob->setTitle(":boxname is due in $when in :catname")
            ->setParams(array(
                'boxname' => new Param($boxName, $href),
                'catname' => new CategoryParam($cat),
            ))
            ->setIcon("/images/icon.php?size={$this->iconSize}&icon=work")
            ->setHref($href)
            ->setNamespace($this->getSlug())
            ->setSeverity(Logger::WARNING)
            ->addTopic("duework.$boxId.upcoming")
            ->addFilter('roletype', Role::STUDENT);

        $unsubmittedStudents = $this->getStudentsUnsubmittedList($submissionBox);

        foreach ($unsubmittedStudents->getColumnValues('id') as $id) {
            $envelope->targetToUser($id, $bob->build());
        }

        return $envelope;
    }


    /**
     * Parent notified when a SubmissionBox is closing AND their child has not submitted
     *
     * @param SubmissionBox $submissionBox
     * @param User          $user
     * @param array         $diff
     * @return Envelope
     */
    public function notifyParentUpcoming($submissionBox, $user, $diff = null)
    {
        $envelope = new Envelope();
        $bob      = new Builder();
        $boxId    = $submissionBox->getId();

        /** @var SubmissionBox $submissionBox */
        $submissionBox = $this->mapper->findById($boxId);

        // don't raise notifications if this box doesn't accept online submissions or is blocked from parents
        if (!$submissionBox->getAcceptOnline() || $this->conf->get('restrict_student_submissions')) {
            return $envelope;
        }

        $bob->setTitle(":child has not yet submitted work for :boxname in :catname")
            ->setParams(array(
                'catname' => new CategoryParam($submissionBox->getCategory()),
                'boxname' => new Param($submissionBox->getName(), "/submission/submissionHistory/$boxId")
            ))
            ->setNamespace($this->getSlug())
            ->setSeverity(Logger::WARNING)
            ->addTopic("duework.$boxId.upcoming");

        // get a list of students who haven't submitted
        $unsubmittedStudents = $this->getStudentsUnsubmittedList($submissionBox);

        foreach ($unsubmittedStudents as $student) {
            $childId = $student->getId();
            // target the parents of this particular student
            $bob->setIcon('/portrait?size=square64&userId=' . $student->getExternalId())
                ->setHref("/submission/submissionHistory/$boxId/$childId")
                ->setParam('child', new UserParam($student, ParameterInterface::STACKING_REPLACE, '{firstname}'));
            $envelope->targetToParent($childId, $bob->build());
        }

        return $envelope;
    }

    /**
     * Parent notified when a SubmissionBox is updated
     *
     * @param SubmissionBox $submissionBox
     * @param User          $user
     * @param array         $diff
     * @return Envelope
     */
    public function notifyParentUpdated($submissionBox, $user, $diff = null)
    {
        $envelope = new Envelope();
        $bob = new Builder();

        $boxId = $submissionBox->getId();

        /** @var SubmissionBox $submissionBox */
        $submissionBox = $this->mapper->findById($boxId);

        // don't raise notifications if this box doesn't accept online submissions or is blocked from parents
        if (!$submissionBox->getAcceptOnline() || $this->conf->get('restrict_student_submissions')) {
            return $envelope;
        }

        // discard all update notifications if the submissionbox isn't open (EXCEPT work returns opening up)
        $openStamp  = $submissionBox->getOpenDate()->getUnixTimestamp();
        $closeStamp = $submissionBox->getDueDate()->getUnixTimestamp();
        $now        = time();

        // if the box hasn't even opened yet, we don't want to know about it at all
        if ($openStamp > $now) {
            return $envelope;
        }

        // Don't need to do the mark visibility dance
        // because it was done in notifyStudentUpdated

        // if the box is closed, or there's no remaining diff, we're done here
        if ($closeStamp < $now || !count($diff)) {
            return $envelope;
        }

        $bob->setIcon("/portrait?userId={$user->getExternalId()}&size=square64")
            ->setHref("/submission/submissionHistory/$boxId")
            ->setNamespace($this->getSlug())
            ->setSeverity(Logger::NOTICE)
            ->addFilter('roletype', Role::PARENT);

        $students = UserAccessMapper::getInstance()->findStudentsInCategory(
            $this->categoryMapper->findById($submissionBox->getCategory()->getId())
        );

        // renamed
        if (!empty($diff['name'])) {
            foreach ($students as $student) {
                $bob = $this
                    ->setForNotifyRenamed($bob, $submissionBox, $diff, $user)
                    ->setTitle(":child's quiz was renamed from :oldname to :newname in :catname")
                    ->unsetParam('author')
                    ->setParam('child', new UserParam($student))
                ;
                $envelope->targetToParent($student->getId(), $bob->build());
            }
        }
        unset($diff['name']);

        // extended or shortened
        if (!empty($diff['dueDate'])) {
            foreach ($students as $student) {
                $oldDate = strtotime($diff['finish'][1]);
                $newDate = strtotime($diff['finish'][0]);
                $verb = $newDate > $oldDate ? 'extended' : 'changed';
                $bob = $this
                    ->setForNotifyDueDateChanged($bob, $submissionBox, $diff, $user)
                    ->setTitle(":child's quiz :boxname closing date was $verb to :date in :catname")
                    ->unsetParam('author')
                    ->setParam('child', new UserParam($student))
                ;
                $envelope->targetToParent($student->getId(), $bob->build());
            }
        }
        unset($diff['dueDate']);

        // changed accepting online submissions
        if (!empty($diff['acceptOnline'])) {
            foreach ($students as $student) {
                if ($submissionBox->getAcceptOnline()) {
                    $m = ":child needs to submit online for :boxname in :catname";
                } else {
                    $m = ":child no longer need to submit online for :boxname in :catname";
                }
                $bob = $this
                    ->setForNotifyAcceptOnlineChanged($bob, $submissionBox)
                    ->setTitle($m)
                    ->unsetParam('author')
                    ->setParam('child', new UserParam($student))
                ;
                $envelope->targetToParent($student->getId(), $bob->build());
            }
        }
        unset($diff['acceptOnline']);

        // if none of the above apply, but there are changes, just be generic
        if ($envelope->count() == 0 && count($diff)) {
            foreach ($students as $student) {
                $bob = $this
                    ->setForNotifyUpdated($bob, $submissionBox, $user)
                    ->setTitle(":child's quiz :quizname was updated in :catname")
                    ->unsetParam('author')
                    ->setParam('child', new UserParam($student))
                ;
                $envelope->targetToParent($student->getId(), $bob->build());
            }
        }

        return $envelope;
    }

    /**
     * Notify the feed of box closures
     *
     * @param SubmissionBox $submissionBox
     * @param User          $user
     * @param array         $diff
     * @return Envelope
     */
    public function notifyFeedClosed($submissionBox, $user, $diff = null)
    {
        $envelope = new Envelope();
        $bob      = new Builder();
        $boxId    = $submissionBox->getId();

        /** @var SubmissionBox $submissionBox */
        $submissionBox = $this->mapper->findById($boxId);

        // don't raise notifications if this box doesn't accept online submissions
        if (!$submissionBox->getAcceptOnline() || $submissionBox->getDueDate()->getUnixTimestamp() > time()) {
            return $envelope;
        }

        $href = "/submission/submissionHistory/$boxId";
        $bob->setTitle("Submissions have closed for :boxname in :catname")
            ->setParams(array(
                'boxname' => new Param($submissionBox->getName(), $href),
                'catname' => new CategoryParam($submissionBox->getCategory()),
            ))
            ->setIcon("/images/icon.php?size={$this->iconSize}&icon=cross")
            ->setHref($href)
            ->setSeverity(Logger::NOTICE)
            ->setNamespace($this->getSlug())
            ->addTopic("duework.$boxId.closed");

        $envelope->broadcastToCategory($submissionBox->getCategory(), UserAccess::ACCESS_READ, $bob->build());

        return $envelope;
    }


    /**
     * @param SubmissionBox $submissionBox
     * @param Envelope      $envelope
     * @param Builder       $bob
     * @param callable      $callback
     * @return Envelope
     */
    protected function notifyOverdue($submissionBox, $envelope, $bob, $callback)
    {
        $boxId = $submissionBox->getId();

        /** @var SubmissionBox $submissionBox */
        $submissionBox = $this->mapper->findById($boxId);

        // don't raise notifications if this box doesn't accept online submissions
        if (!$submissionBox->getAcceptOnline()) {
            return $envelope;
        }

        // if the late date has passed, ignore this message
        if ($submissionBox->getLateDate()->getUnixTimestamp() > time()) {
            return $envelope;
        }

        $boxName = $submissionBox->getName();
        $cat     = $submissionBox->getCategory();
        $href    = "/submission/submissionHistory/$boxId";

        $bob->setParams(array(
            'boxname' => new Param($boxName, $href),
            'catname' => new CategoryParam($cat),
        ))
            ->setIcon("/images/icon.php?size={$this->iconSize}&icon=work")
            ->setHref($href)
            ->setSeverity(Logger::ERROR)
            ->setNamespace($this->getSlug())
            ->addTopic("duework.$boxId.overdue");

        $unsubmittedStudents = $this->getStudentsUnsubmittedList($submissionBox);

        if (is_callable($callback)) {
            foreach ($unsubmittedStudents as $user) {
                call_user_func($callback, $user);
            }
        }

        return $envelope;
    }


    /**
     * Student notified when a SubmissionBox is overdue AND they have not submitted
     *
     * @param SubmissionBox $submissionBox
     * @param User          $user
     * @param array         $diff
     * @return Envelope
     */
    public function notifyStudentOverdue($submissionBox, $user, $diff = null)
    {
        $envelope = new Envelope();
        $bob      = new Builder();

        $bob->setTitle(":boxname is now overdue in :catname");

        return $this->notifyOverdue($submissionBox, $envelope, $bob, function ($student) use (&$envelope, &$bob) {
            $envelope->targetToUser($student, $bob->build());
        });
    }


    /**
     * Parent notified when a SubmissionBox is overdue AND their child has not submitted
     *
     * @param SubmissionBox $submissionBox
     * @param User          $user
     * @param array         $diff
     * @return Envelope
     */
    public function notifyParentOverdue($submissionBox, $user, $diff = null)
    {
        $envelope = new Envelope();
        $bob      = new Builder();

        $bob->setTitle(":child did not submit work for :boxname in :catname");

        if ($this->conf->get('restrict_student_submissions')) {
            return $envelope;
        }

        return $this->notifyOverdue($submissionBox, $envelope, $bob, function ($student) use (&$envelope, &$bob) {
            $bob->setParam('child', new UserParam($student, ParameterInterface::STACKING_REPLACE, '{firstname}'));
            $envelope->targetToParent($student->getId(), $bob->build());
        });
    }


    /**
     * Staff notified when a SubmissionBox late-date has passed AND students did not submit
     *
     * @param SubmissionBox $submissionBox
     * @param User          $user
     * @param array         $diff
     * @return Envelope
     */
    public function notifyStaffClosed($submissionBox, $user, $diff = null)
    {
        $envelope = new Envelope();
        $bob      = new Builder();
        $boxId    = $submissionBox->getId();

        /** @var SubmissionBox $submissionBox */
        $submissionBox = $this->mapper->findById($boxId);

        // don't raise notifications if this box doesn't accept online submissions
        if (!$submissionBox->getAcceptOnline()) {
            return $envelope;
        }
        $totalUnsubmitted = $this->getStudentsUnsubmittedList($submissionBox)->count();

        // everyone submitted! woohoo!
        if ($totalUnsubmitted == 0) {
            return $envelope;
        }

        $bob->setTitle(":boxname has closed and :number did not submit work")
            ->setIcon("/images/icon.php?size={$this->iconSize}&icon=work")
            ->setHref("/submission/viewSubmission/$boxId")
            ->setNamespace($this->getSlug())
            ->setParams(array(
                'boxname' => new Param($submissionBox->getName(), "/submission/viewSubmission/$boxId"),
                'number'  => new Param(sprintf("%s student%s", $totalUnsubmitted, $totalUnsubmitted == 1 ? '' : 's'))
            ))
            ->setSeverity(Logger::ERROR)
            ->addTopic("duework.$boxId.closed");

        $envelope->targetToUser($user->getId(), $bob->build());

        return $envelope;
    }

}
