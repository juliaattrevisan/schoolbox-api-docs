<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Blog\Eportfolio\Post;

use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Parameter\ParameterInterface;
use Eportfolio\PageMapper;
use Eportfolio\PermissionMapper;
use Monolog\Logger;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\IMapper;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Notification\Parameter\ChildParam;
use Schoolbox\Realtime\Notification\Parameter\UserParam;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundle;
use Schoolbox\RoleMapper;
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
    const BASE_URI = '/eportfolio/blog/';

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

        // Send it to all their teachers (across all subjects)
        $audience = $this->getStudentTeachers($owner);

        return $this->notifyPosted($post, $effector, $audience, RoleMapper::ROLE_STAFF);
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
        $page     = $this->pageMapper->findById($post->getPage()->getPageId());
        $owner    = $this->userMapper->findById($page->getOwner()->getId());
        $audience = $this->userMapper->createCollection([$owner]);

        return $this->notifyPosted($post, $effector, $audience, RoleMapper::ROLE_STUDENT);
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

        // Empty audience if parents don't have view permission
        $audience = $this->isPostVisibleToParents($post)
            ? $owner->loadParents()->getParents()
            : $this->userMapper->createCollection();

        return $this->notifyPosted($post, $effector, $audience, RoleMapper::ROLE_PARENT);
    }


    /**
     * Common logic for the 'notify{RoleType}Posted()' public methods
     *
     * @param Model      $post
     * @param User       $effector
     * @param Collection $audience
     * @param string     $roleType
     * @return Envelope
     * @throws OutOfBoundsException     If severity is not set
     * @throws InvalidArgumentException If a required property was not set
     */
    private function notifyPosted(Model $post, User $effector, Collection $audience, string $roleType): Envelope
    {
        $envelope = new Envelope();
        $page     = $this->pageMapper->findById($post->getPage()->getPageId());
        $owner    = $this->userMapper->findById($page->getOwner()->getId());

        // Are we targeting parents?
        if (RoleMapper::ROLE_PARENT === $roleType) {
            // Load parents so we can display just their child's first name
            $effector->loadParents();
            if ($effector->getId() !== $owner->getId()) {
                $owner->loadParents();
            }
        }

        foreach ($audience as $target) {
            // Just first name if advising parent of child's action
            $effectorParam = RoleMapper::ROLE_PARENT === $roleType && $target->isParentOf($effector)
                ? new ChildParam($effector, ParameterInterface::STACKING_REPLACE)
                : new UserParam($effector, ParameterInterface::STACKING_REPLACE);

            // Adjust title for effector, owner and recipient role combinations
            if ((int)$owner->getId() === (int)$target->getId()) {
                // Are we sending this to post owner?
                $ownerToken = 'your';
                $ownerParam = [];
            } elseif ((int)$owner->getId() === (int)$effector->getId()) {
                // Did effector comment on their own blog?
                $ownerToken = 'their';
                $ownerParam = [];
            } elseif (RoleMapper::ROLE_PARENT === $roleType && $target->isParentOf($owner)) {
                // Are we sending to post owner's parents?
                $ownerToken = ":student's";
                $ownerParam = ['student' => new ChildParam($owner, ParameterInterface::STACKING_REPLACE)];
            } else {
                // Effector is not the owner, target is not owner, target is not parent of owner
                $ownerToken = ":student's";
                $ownerParam = ['student' => new UserParam($owner, ParameterInterface::STACKING_REPLACE)];
            }

            $bob = $this->createBuilder($post, $effector, self::TRIGGER_POSTED);
            $bob->setTitle(":author posted :title on {$ownerToken} eportfolio blog")
                ->setParams(array_merge(['author' => $effectorParam], $ownerParam));
            $envelope->targetToUser($target->getId(), $bob->build());
        }
        return $envelope;
    }


    /**
     * @param Model  $post
     * @param User   $effector
     * @param string $trigger
     * @return Builder
     */
    private function createBuilder(Model $post, User $effector, string $trigger): Builder
    {
        $bob = new Builder();
        $bob->setIcon("/portrait.php?userId={$effector->getExternalId()}&size={$this->iconSize}")
            ->setHref($this->buildBlogUrl($post))
            ->setSeverity(Logger::INFO)
            ->setNamespace($this->getSlug())
            ->addTopic("eportfolio.blog.{$post->getId()}.$trigger")
            ->setTimestamp($post->getCreatedAt()->getUnixTimeStamp())
            ->setBody($post->getBody());
        return $bob;
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
     * Generate a URL to send users to see a specific eportfolio blog post
     * (not the blog thread in the component)
     *
     * @param Model $post
     * @return string
     */
    private function buildBlogUrl(Model $post): string
    {
        return self::BASE_URI . "{$post->getPage()->getPageId()}/{$post->getId()}";
    }


    /**
     * Return whether the specified post is visible to parents
     *
     * @param Model $post
     * @return bool
     */
    private function isPostVisibleToParents(Model $post): bool
    {
        // Can parents view the page where the blog post resides?
        foreach ($this->permissionMapper->findParentSetting($post->getPage()->getPageId()) as $permission) {
            if ($permission->getValue()) {
                return true;
            }
        }
        return false;
    }


    /**
     * @param User $student
     * @return GenericCollection|User[]
     */
    private function getStudentTeachers(User $student)
    {
        $externalIds = [];
        foreach ($this->externalUser->getSubjectList($student) as $subject) {
            foreach ($this->externalClass->getTeacherName($subject['code']) as $externalId) {
                $externalIds[$externalId] = $externalId;
            }
        }
        $teachers = $this->userMapper->createCollection();
        foreach ($externalIds as $externalId) {
            try {
                $classTeacher                     = $this->userMapper->findByExternalId($externalId);
                $teachers[$classTeacher->getId()] = $classTeacher;
            } catch (BaseMapper\Exception $e) {
                // no teacher found in Schoolbox - that's ok.
            }
        }
        return $teachers;
    }
} 
