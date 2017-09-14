<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Blog\Eportfolio\Post;

use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Parameter\Param;
use Alaress\Async\Notification\Parameter\ParameterInterface;
use Eportfolio\PageMapper;
use Eportfolio\PermissionMapper;
use Monolog\Logger;
use Schoolbox\Collection;
use Schoolbox\IMapper;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Notification\Parameter\ChildParam;
use Schoolbox\Realtime\Notification\Parameter\UserParam;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundle;
use Schoolbox\User;
use Schoolbox\UserMapper;
use OutOfBoundsException;
use InvalidArgumentException;
use External_UserInterface;
use External_User;
use External_ClassInterface;
use External_Class;


class Notifier extends AbstractModuleNotifier
{

    const TRIGGER_POSTED = 'posted';
    const BASE_URI = '/eportfolio/';

    /**
     * @var PageMapper
     */
    private $pageMapper;

    /**
     * @var PermissionMapper
     */
    private $permissionMapper;

    /**
     * @var UserMapper
     */
    private $userMapper;

    /**
     * @var External_UserInterface
     */
    private $externalUser;

    /**
     * @var External_ClassInterface
     */
    private $externalClass;


    /**
     * @param null|IMapper[] $dependencies
     */
    public function loadDependencies($dependencies = null): void
    {
        $dependencies                     = $dependencies ?: [];
        $dependencies['mapper']           = $dependencies['mapper'] ?? Mapper::getInstance();
        $dependencies['pageMapper']       = $dependencies['pageMapper'] ?? PageMapper::getInstance();
        $dependencies['permissionMapper'] = $dependencies['permissionMapper'] ?? PermissionMapper::getInstance();
        $dependencies['userMapper']       = $dependencies['userMapper'] ?? UserMapper::getInstance();
        $dependencies['externalUser']     = $dependencies['externalUser'] ?? new External_User();
        $dependencies['externalClass']    = $dependencies['externalClass'] ?? new External_Class();
        parent::loadDependencies($dependencies);
    }


    /**
     * @param TriggerBundle $bundle
     * @return void
     */
    public function bundleTriggers($bundle): void
    {
        // we only care about notifying blog post creation
        if ($bundle->getMethod() === BaseMapper::METHOD_CREATE) {

            /** @var Model $blog */
            $blog = $bundle->getEvent()->getObject();

            //TODO remove this hack when SA-4400 is merged.
            $blog = $this->getMapper()->findByColumn(
                $this->getMapper()->getIdentifierColumn(), $blog->getId(), ['cache' => false]
            )->first();
            $bundle->getEvent()->setObject($blog);

            $bundle->attach(self::TRIGGER_POSTED, $blog->getCreatedAt()->getUnixTimeStamp());
        }
    }


    /* ASYNC NOTIFICATION METHODS */

    /**
     * Notify staff that someone posted on one of their student's eportfolio blog
     *
     * @param Model $post
     * @param User  $effector
     * @return Envelope
     * @throws OutOfBoundsException     If severity is not set
     * @throws InvalidArgumentException If a required property was not set
     */
    public function notifyStaffPosted(Model $post, User $effector): Envelope
    {
        $page  = $this->pageMapper->findById($post->getPage()->getPageId());
        $owner = $this->userMapper->findById($page->getOwner()->getId());

        // Is author and owner the same user?
        if ($effector->getId() === $owner->getId()) {
            $titleToken = 'their';
            $titleParam = [];
        } else {
            $titleToken = ":student's";
            $titleParam = ['student' => new UserParam($owner, ParameterInterface::STACKING_REPLACE)];
        }

        // Send it to all their teachers (across all subjects)
        $audience = $this->userMapper->createCollection();
        foreach ($this->externalUser->getSubjectList($owner) as $subject) {
            $externalIds = $this->externalClass->getTeacherName($subject['code']);
            foreach ($externalIds as $externalId) {
                try {
                    $classTeacher                     = $this->userMapper->findByExternalId($externalId);
                    $audience[$classTeacher->getId()] = $classTeacher;
                } catch (BaseMapper\Exception $e) {
                    // no teacher found in Schoolbox - that's ok.
                }
            }
        }
        return $this->notifyPosted($post, $effector, $audience, $titleToken, $titleParam);
    }


    /**
     * Notify a student someone posted on their Eportfolio blog
     *
     * @param Model $post
     * @param User  $effector
     * @return Envelope
     * @throws OutOfBoundsException     If severity is not set
     * @throws InvalidArgumentException If a required property was not set
     */
    public function notifyStudentPosted(Model $post, User $effector): Envelope
    {
        $page       = $this->pageMapper->findById($post->getPage()->getPageId());
        $owner      = $this->userMapper->findById($page->getOwner()->getId());
        $audience   = $this->userMapper->createCollection([$owner]);
        $titleToken = 'your';
        return $this->notifyPosted($post, $effector, $audience, $titleToken);
    }


    /**
     * Notify parents that someone posted on their child's eportfolio blog
     *
     * @param Model $post
     * @param User  $effector
     * @return Envelope
     * @throws OutOfBoundsException     If severity is not set
     * @throws InvalidArgumentException If a required property was not set
     */
    public function notifyParentPosted(Model $post, User $effector): Envelope
    {
        $page  = $this->pageMapper->findById($post->getPage()->getPageId());
        $owner = $this->userMapper->findById($page->getOwner()->getId());

        // Is author and owner the same user?
        if ($effector->getId() === $owner->getId()) {
            $titleToken = 'their';
            $titleParam = [];
        } else {
            $titleToken = ":student's";
            $titleParam = ['student' => new ChildParam($owner, ParameterInterface::STACKING_REPLACE)];
        }

        $parentsCanView = false;
        foreach($this->permissionMapper->findParentSetting($page->getPageId()) as $permission){
            if($permission->getValue()){
                $parentsCanView = true;
                break;
            }
        }

        // Empty audience if parents don't have view permission
        $audience = $parentsCanView
            ? $owner->loadParents()->getParents()
            : $this->userMapper->createCollection();

        return $this->notifyPosted($post, $effector, $audience, $titleToken, $titleParam);
    }


    /**
     * Common logic for the 'notify{RoleType}Posted()' public methods
     *
     * @param Model      $post
     * @param User       $effector
     * @param Collection $audience
     * @param string     $titleToken ["their", "your", ":student's"]
     * @param Param[]    $titleParam
     * @return Envelope
     * @throws OutOfBoundsException     If severity is not set
     * @throws InvalidArgumentException If a required property was not set
     */
    private function notifyPosted(
        Model $post,
        User $effector,
        Collection $audience,
        string $titleToken,
        array $titleParam = []
    ): Envelope {
        $envelope    = new Envelope();
        $bob         = new Builder();
        $page        = $this->pageMapper->findById($post->getPage()->getPageId());
        $owner       = $this->userMapper->findById($page->getOwner()->getId());
        $authorExtId = $effector->getExternalId();

        $bob->setTitle(":author posted :title on {$titleToken} eportfolio blog")
            ->setParams(array_merge($titleParam, [
                'author' => new UserParam($effector, ParameterInterface::STACKING_REPLACE),
                'title'  => new Param($post->getHeading(), $this->buildBlogUrl($owner, $post)),
            ]))
            ->setIcon("/portrait.php?userId=$authorExtId&size={$this->iconSize}")
            ->setHref($this->buildBlogUrl($owner, $post))
            ->setSeverity(Logger::INFO)
            ->setNamespace($this->getSlug())
            ->addTopic("eportfolio.blog.{$post->getId()}." . self::TRIGGER_POSTED)
            ->setTimestamp($post->getCreatedAt()->getUnixTimeStamp())
            ->setBody($post->getBody());

        foreach ($audience as $target) {
            $envelope->targetToUser($target->getId(), $bob->build());
        }
        return $envelope;
    }


    /**
     * @param UserMapper $userMapper
     * @return $this|self
     */
    protected function setUserMapper(UserMapper $userMapper): self
    {
        $this->userMapper = $userMapper;
        return $this;
    }


    /**
     * @param PageMapper $pageMapper
     * @return $this|self
     */
    protected function setPageMapper(PageMapper $pageMapper): self
    {
        $this->pageMapper = $pageMapper;
        return $this;
    }


    /**
     * @param PermissionMapper $permissionMapper
     * @return $this|self
     */
    protected function setPermissionMapper(PermissionMapper $permissionMapper): self
    {
        $this->permissionMapper = $permissionMapper;
        return $this;
    }


    /**
     * @param External_UserInterface $externalUser
     * @return $this|self
     */
    protected function setExternalUser(External_UserInterface $externalUser): self
    {
        $this->externalUser = $externalUser;
        return $this;
    }


    /**
     * @param External_ClassInterface $externalClass
     * @return $this|self
     */
    protected function setExternalClass(External_ClassInterface $externalClass): self
    {
        $this->externalClass = $externalClass;
        return $this;
    }


    /**
     * @return string
     */
    protected function getSlug(): string
    {
        return NSMapper::NS_EPORTFOLIO_BLOG;
    }


    /**
     * @param User  $student
     * @param Model $post
     * @return string
     */
    private function buildBlogUrl(User $student, Model $post): string
    {
        return self::BASE_URI . "{$student->getId()}/{$post->getPage()->getPageId()}#blog_{$post->getId()}";
    }
} 
