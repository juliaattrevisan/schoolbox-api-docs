<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Blog\Eportfolio\Post;

use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Parameter\Param;
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
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;
use OutOfBoundsException;
use InvalidArgumentException;
use Schoolbox\Resource\Folder;
use Schoolbox\Mapper\Exception;

class Notifier extends AbstractModuleNotifier
{
    const TRIGGER_POSTED = 'posted';
    // This is to support ePortfolio for staff and parent
    const TRIGGER_BLOG_POSTED = 'blogPosted';

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

            $bundle->attach(self::TRIGGER_POSTED, $blog->getCreatedAt()->getUnixTimeStamp());
            $bundle->attach(self::TRIGGER_BLOG_POSTED, $blog->getCreatedAt()->getUnixTimeStamp());
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
        try {
            $page = $this->pageMapper->findById($post->getPage()->getPageId());
            $owner = $this->userMapper->findById($page->getOwner()->getId());

            // Care only about student's blog. The others are handled in the other methods.
            if (!$owner->getRole()->isStudent()) {
                return new Envelope();
            }

            // Send it to all their teachers (across all subjects)
            $this->externalUser->loadTeachers([$owner]);
            $audience = $owner->getTeacherCollection();

            return $this->notifyPosted($post, $owner, $effector, $audience, Role::STAFF);
        } catch (Exception $e) {
            // page or owner was invalid.
            return new Envelope();
        }
    }

    /**
     * Notify staff someone posted on their Eportfolio blog
     *
     * @param Model $post
     * @param User $effector
     * @return Envelope
     * @throws OutOfBoundsException     If severity is not set
     * @throws InvalidArgumentException If a required property was not set
     */
    public function notifyStaffBlogposted(Model $post, User $effector): Envelope
    {
        return $this->notifyOwnerPosted($effector, $post, Role::STAFF, self::TRIGGER_BLOG_POSTED);
    }

    /**
     * Notify parent someone posted on their Eportfolio blog
     *
     * @param Model $post
     * @param User $effector
     * @return Envelope
     * @throws OutOfBoundsException     If severity is not set
     * @throws InvalidArgumentException If a required property was not set
     */
    public function notifyParentBlogposted(Model $post, User $effector): Envelope
    {
        return $this->notifyOwnerPosted($effector, $post, Role::PARENT, self::TRIGGER_BLOG_POSTED);
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
        return $this->notifyOwnerPosted($effector, $post, Role::STUDENT, self::TRIGGER_POSTED);
    }

    /**
     * Notify the post owner that someone posted on their Eportfolio blog, if the owner is in the given role
     * @param User $effector
     * @param Model $post
     * @param string $roleType
     * @param string $trigger
     * @return Envelope
     */
    private function notifyOwnerPosted(User $effector, Model $post, string $roleType, string $trigger): Envelope
    {
        try {
            $page = $this->pageMapper->findById($post->getPage()->getPageId());
            $owner = $this->userMapper->findById($page->getOwner()->getId());

            if ($owner->getRole()->getType() !== $roleType) {
                return new Envelope();
            }

            $params = [
                'author' => new UserParam($effector),
                'title' => new Param($post->getHeading(), $this->buildBlogUrl($post)),
            ];

            $envelope = new Envelope();
            $bob = $this->createBuilder($post, $effector, $trigger);
            $bob->setTitle(":author posted :title on your eportfolio blog")
                ->setParams($params);
            $envelope->targetToUser($owner->getId(), $bob->build());
            return $envelope;
        } catch (Exception $e) {
            // page or owner was invalid.
            return new Envelope();
        }
    }

    /**
     * Notify parents that someone posted on their child's eportfolio blog
     *
     * @param Model $post
     * @param User $effector
     * @return Envelope
     * @throws OutOfBoundsException     If severity is not set
     * @throws InvalidArgumentException If a required property was not set
     */
    public function notifyParentPosted(Model $post, User $effector): Envelope
    {
        try {
            $page = $this->pageMapper->findById($post->getPage()->getPageId());
            $owner = $this->userMapper->findById($page->getOwner()->getId());
        } catch (Exception $e) {
            // page or owner was invalid.
            return new Envelope();
        }

        // Care only about student's blog. The others are handled in the other methods.
        if (!$owner->getRole()->isStudent()) {
            return new Envelope();
        }

        $audience = $this->userMapper->createCollection();
        // Need to explicitly set page owner
        $post->getPage()->setOwner($owner);

        // Send it to all their parents
        //hit the external db, and hit it again for good measure
        $this->externalUser->loadParents([$owner]);
        $parents = $owner->getParents();
        $this->externalUser->loadChildren($parents);
        foreach ($parents as $parent) {
            try {
                if ($this->access->userCanView($post, $parent)) {
                    $audience[$parent->getId()] = $parent;
                }
            } catch (Exception $e) {
                // noop
            }
        }

        return $this->notifyPosted($post, $owner, $effector, $audience, Role::PARENT);
    }

    /**
     * Common logic for the 'notify{RoleType}Posted()' public methods
     *
     * @param Model      $post
     * @param User       $owner
     * @param User       $effector
     * @param Collection $audience
     * @param string     $roleType
     * @return Envelope
     * @throws OutOfBoundsException     If severity is not set
     * @throws InvalidArgumentException If a required property was not set
     */
    private function notifyPosted(Model $post, User $owner, User $effector, Collection $audience, string $roleType): Envelope
    {
        $envelope = new Envelope();

        //hit the db and load all parents for the audience members
        $this->externalUser->loadChildren($audience);
        foreach ($audience as $target) {
            /** @var User $target */
            // Just first name if advising parent of child's action
            $effectorParam = Role::PARENT === $roleType && $target->isParentOf($effector)
                ? new ChildParam($effector)
                : new UserParam($effector);

            // Adjust title for effector, owner and recipient role combinations
            if ((int)$owner->getId() === (int)$target->getId()) {
                // Are we sending this to post owner?
                $ownerToken = 'your';
                $ownerParam = [];
            } elseif ((int)$owner->getId() === (int)$effector->getId()) {
                // Did effector comment on their own blog?
                $ownerToken = 'their';
                $ownerParam = [];
            } elseif (Role::PARENT === $roleType && $target->isParentOf($owner)) {
                // Are we sending to post owner's parents?
                $ownerToken = ":student's";
                $ownerParam = ['student' => new ChildParam($owner)];
            } else {
                // Effector is not the owner, target is not owner, target is not parent of owner
                $ownerToken = ":student's";
                $ownerParam = ['student' => new UserParam($owner)];
            }
            $params = [
                'author' => $effectorParam,
                'title'  => new Param($post->getHeading(), $this->buildBlogUrl($post)),
            ];

            $bob = $this->createBuilder($post, $effector, self::TRIGGER_POSTED);
            $bob->setTitle(":author posted :title on {$ownerToken} eportfolio blog")
                ->setParams(array_merge($ownerParam, $params));
            //Only add the student external id for the parent notification
            if ($ownerParam['student'] instanceof ChildParam) {
                $bob->putMeta('studentExternalId', $owner->getExternalId());
            }
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
        $bob->setIcon($this->getUserIcon($effector))
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
