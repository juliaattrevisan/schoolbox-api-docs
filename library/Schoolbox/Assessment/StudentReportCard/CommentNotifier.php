<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\StudentReportCard;

use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Parameter\Param;
use Alaress\Async\Notification\Parameter\ParameterInterface;
use Monolog\Logger;
use Schoolbox\Assessment\Term;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Mapper;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Notification\Parameter\ChildParam;
use Schoolbox\Realtime\Notification\Parameter\UserParam;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundle;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

class CommentNotifier extends AbstractModuleNotifier
{
    const TRIGGER_COMMENTED = 'commented';

    /** @var FolderMapper */
    protected $categoryMapper;

    /** @var UserMapper */
    protected $userMapper;

    /** @var Term\Mapper */
    protected $termMapper;

    public function loadDependencies($dependencies = null)
    {
        if (empty($dependencies['categoryMapper'])) {
            $dependencies['categoryMapper'] = FolderMapper::getInstance();
        }
        if (empty($dependencies['userMapper'])) {
            $dependencies['userMapper'] = UserMapper::getInstance();
        }
        if (empty($dependencies['termMapper'])) {
            $dependencies['termMapper'] = Term\Mapper::getInstance();
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
     * @param \Schoolbox\Resource\Folder\Mapper $categoryMapper
     * @return self
     */
    public function setCategoryMapper($categoryMapper)
    {
        $this->categoryMapper = $categoryMapper;
        return $this;
    }


    /**
     * @return \Schoolbox\Resource\Folder\Mapper
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
     * @param Term\Mapper $termMapper
     * @return self
     */
    protected function setTermMapper($termMapper)
    {
        $this->termMapper = $termMapper;
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

        if (!$this->conf->get(ConfigMapper::NAME_GRADES_ENABLED)) {
            // there is no grades page to show
            return $envelope;
        }

        // extract elements we'll be using
        $student  = $comment->getStudent();
        $category = $comment->getCategory();

        try {
            $category = $this->categoryMapper->findById($category->getId());
        } catch (Mapper\Exception $e) {
            return $envelope;
        }
        if (!$category->isGradePageEnabled()) {
            // there is no grades page to show
            return $envelope;
        }

        if (!$student->getFirstname()) {
            try {
                $student = $this->userMapper->findById($comment->getStudent()->getId());
            } catch (Mapper\Exception $e) {
                return $envelope;
            }
        }

        $category->loadTeachers();

        $term = $this->termMapper->findForToday();
        $href = sprintf("/learning/grades/%s/%s/%s#comments", $student->getId(), $term->getCode(), $category->getId());

        // create base essentials first
        $bob->setNamespace(NSMapper::NS_REPORT_CARD)
            ->setIcon($this->getUserIcon($author))
            ->setHref($href)
            ->setBody($comment->getBody())
            ->setSeverity(Logger::WARNING)
            ->addTopic("report_card.{$category->getId()}.{$student->getId()}.commented")
            ->setParams([
                'author'   => new UserParam($author),
                'category' => new Param($category->getTitle(), $href, ParameterInterface::STACKING_APPEND),
            ]);

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
            $bob->setParam('student', new ChildParam($student, ParameterInterface::STACKING_APPEND));
            $bob->putMeta('studentExternalId', $student->getExternalId());
            $envelope->targetToParent($student->getId(), $bob->build());
        }

        // teachers do though
        $bob->setParam('student', new UserParam($student));

        // and send to class teacher(s)
        foreach ($category->getTeachers() as $staff) {
            if (!$staff->getRole()->getViewStudentGrades()) {
                continue;
            }
            $envelope->targetToUser($staff->getId(), $bob->build());
        }

        return $envelope;
    }
}
