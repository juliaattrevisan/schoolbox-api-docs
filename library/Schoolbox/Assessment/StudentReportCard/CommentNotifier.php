<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\StudentReportCard;


use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Parameter\ParameterInterface;
use Monolog\Logger;
use Schoolbox\Mapper;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Notification\Parameter\CategoryParam;
use Schoolbox\Realtime\Notification\Parameter\UserParam;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundle;
use Schoolbox\Resource\CategoryMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

class CommentNotifier extends AbstractModuleNotifier
{

    const TRIGGER_COMMENTED = 'commented';

    /** @var CategoryMapper */
    protected $categoryMapper;

    /** @var UserMapper */
    protected $userMapper;


    public function loadDependencies($dependencies = null)
    {
        if (empty($dependencies['categoryMapper'])) {
            $dependencies['categoryMapper'] = CategoryMapper::getInstance();
        }
        if (empty($dependencies['userMapper'])) {
            $dependencies['userMapper'] = UserMapper::getInstance();
        }
        parent::loadDependencies($dependencies);
    }


    /**
     * @return string
     */
    protected function getSlug()
    {
        return NSMapper::NS_REPORT_CARD;
    }


    /**
     * @param TriggerBundle $bundle
     * @return void
     */
    public function bundleTriggers($bundle)
    {
        if ($bundle->getMethod() == Mapper::METHOD_CREATE) {
            $bundle->attach(self::TRIGGER_COMMENTED, self::IMMEDIATELY);
        }
    }


    /**
     * Override existing, since only a few users are ever notified about this event
     *
     * @param string $label
     * @return array
     */
    public function retrieveCallbacks($label)
    {
        $callback = array($this, 'notify' . ucfirst($label));
        if (is_callable($callback)) {
            return array($callback);
        }
        return array();
    }


    /**
     * @param \Schoolbox\Resource\CategoryMapper $categoryMapper
     * @return self
     */
    public function setCategoryMapper($categoryMapper)
    {
        $this->categoryMapper = $categoryMapper;
        return $this;
    }


    /**
     * @return \Schoolbox\Resource\CategoryMapper
     */
    public function getCategoryMapper()
    {
        return $this->categoryMapper;
    }


    /**
     * @param UserMapper $userMapper
     * @return self
     */
    protected function setUserMapper($userMapper)
    {
        $this->userMapper = $userMapper;
        return $this;
    }




    /**
     * @param Comment $comment
     * @param User    $author
     * @return Envelope
     */
    public function notifyCommented($comment, $author)
    {
        $envelope = new Envelope();
        $bob      = new Builder();

        // extract elements we'll be using
        $student  = $comment->getStudent();
        $category = $comment->getCategory();

        // selective hydrations
        if (!$category->getName()) {
            $category = $this->categoryMapper->findById($category->getId());
        }

        if (!$student->getFirstname()) {
            $student = $this->userMapper->findById($comment->getStudent()->getId());
        }

        $category->loadTeachers();

        $href = sprintf("/submission/allWork/%s/%s/%s/%s",
            $student->getId(), date('Y'), date('m') > 6 ? 2 : 1, $category->getId()
        );

        // create base essentials first
        $bob->setNamespace(NSMapper::NS_REPORT_CARD)
            ->setIcon('/portrait.php?size={$this->iconSize}&userId=' . $author->getExternalId())
            ->setHref($href)
            ->setBody($comment->getBody())
            ->setSeverity(Logger::WARNING)
            ->addTopic("report_card.{$category->getId()}.{$student->getId()}.commented")
            ->setParams(array(
                'author'   => new UserParam($author),
                'category' => new CategoryParam($category)
            ));


        // send to student (if relevant)
        if ($author->getId() != $student->getId() && $comment->getStudentVisibility()) {
            $bob->setTitle(":author commented on your grades in :category");

            $envelope->targetToUser($student->getId(), $bob->build());
        }

        // revise text...
        $bob->setTitle(":author commented on :student's grades in :category");

        // send to parents (if relevant)
        if ($comment->getParentVisibility()) {
            // parents don't need to see their child's surname in the message
            $bob->setParam('student', new UserParam($student, ParameterInterface::STACKING_REPLACE, '{firstname}'));
            $envelope->targetToParent($student->getId(), $bob->build());
        }

        // teachers do though
        $bob->setParam('student', new UserParam($student, ParameterInterface::STACKING_REPLACE));

        // and send to class teacher(s)
        foreach ($category->getTeachers() as $staff) {
            $envelope->targetToUser($staff->getId(), $bob->build());
        }

        return $envelope;
    }
}