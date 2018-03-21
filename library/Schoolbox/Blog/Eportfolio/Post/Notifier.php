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
use Schoolbox\Blog\Eportfolio\Access;
use Schoolbox\Collection;
use Schoolbox\IMapper;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Notification\Parameter\ChildParam;
use Schoolbox\Realtime\Notification\Parameter\UserParam;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundleInterface;
use Schoolbox\RoleMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;
use OutOfBoundsException;
use InvalidArgumentException;
use Schoolbox\Resource\Folder;

class Notifier extends AbstractModuleNotifier
{

    const TRIGGER_POSTED = 'posted';
    const BASE_URI = '/eportfolio/blog/';

    /**
     * @var PageMapper
     */
    private $pageMapper;

    /**
     * @var Access
     */
    private $access;

    /**
     * @var UserMapper
     */
    private $userMapper;

    /**
     * @var Folder\Mapper
     */
    private $folderMapper;


    /**
     * @param null|IMapper[] $dependencies
     */
    public function loadDependencies($dependencies = null): void
    {
        $dependencies                     = $dependencies ?: [];
        $dependencies['mapper']           = $dependencies['mapper'] ?? Mapper::getInstance();
        $dependencies['pageMapper']       = $dependencies['pageMapper'] ?? PageMapper::getInstance();
        $dependencies['folderMapper']     = $dependencies['folderMapper'] ?? Folder\Mapper::getInstance();
        $dependencies['blogAccess']       = $dependencies['blogAccess'] ?? new Access(
            $dependencies['permissionMapper'] ?? PermissionMapper::getInstance(),
            $dependencies['folderMapper']
        );
        $dependencies['userMapper']       = $dependencies['userMapper'] ?? UserMapper::getInstance();
        parent::loadDependencies($dependencies);
    }


    /**
     * @param TriggerBundleInterface $bundle
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
                $this->getMapper()->getIdentifierColumn(),
                $blog->getId(),
                ['cache' => false]
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
        $audience = $owner->loadTeachers()->getTeacherCollection();

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
     * @throws BaseMapper\Exception
     * @throws OutOfBoundsException     If severity is not set
     * @throws InvalidArgumentException If a required property was not set
     */
    public function notifyParentPosted(Model $post, User $effector): Envelope
    {
        // Hydrate
        $post->setPage($this->pageMapper->findById($post->getPage()->getPageId()));
        $post->getPage()->setOwner($this->userMapper->findById($post->getPage()->getOwner()->getId())->loadParents());
        $audience = $this->userMapper->createCollection();

        foreach ($post->getPage()->getOwner()->getParents() as $parent) {
            if ($this->access->userCanView($post, $parent)) {
                $audience[$parent->getId()] = $parent;
            }
        }

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
            $params = [
                'author' => $effectorParam,
                'title'  => new Param($post->getHeading(), $this->buildBlogUrl($post)),
            ];

            $bob = $this->createBuilder($post, $effector, self::TRIGGER_POSTED);
            $bob->setTitle(":author posted :title on {$ownerToken} eportfolio blog")
                ->setParams(array_merge($ownerParam, $params));
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
            ->addTopic("{$this->getSlug()}.{$post->getId()}.$trigger")
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
     * @param Access $access
     * @return $this|self
     */
    protected function setBlogAccess(Access $access): self
    {
        $this->access = $access;
        return $this;
    }


    /**
     * @param Folder\Mapper $folderMapper
     * @return $this|self
     */
    protected function setFolderMapper(Folder\Mapper $folderMapper): self
    {
        $this->folderMapper = $folderMapper;
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

}
