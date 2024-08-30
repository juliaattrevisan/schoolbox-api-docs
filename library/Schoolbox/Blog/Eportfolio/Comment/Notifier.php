<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Blog\Eportfolio\Comment;

use Alaress\Async\Notification\Instance\Builder;
use Schoolbox\Blog\Eportfolio\Access;
use Schoolbox\Resource\Folder;
use Schoolbox\Blog\Eportfolio\Post;
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
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundleInterface;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\Mapper\Exception;
use OutOfBoundsException;
use InvalidArgumentException;

/**
 * @method Mapper getMapper()
 */
class Notifier extends AbstractModuleNotifier
{
    const TRIGGER_CREATED = 'created';
    const TRIGGER_COMMENTED = 'commented';
    // this is to support ePortfolio for staff and parent
    const TRIGGER_BLOG_COMMENTED = 'blogCommented';
    const TRIGGER_FOLLOWED = 'followed';

    const BASE_URI = '/eportfolio/blog/';

    /**
     * @var Post\Mapper
     */
    private $postMapper;

    /**
     * @var Folder\Mapper
     */
    private $folderMapper;

    /**
     * @var Access
     */
    private $access;

    /**
     * @var UserMapper
     */
    private $userMapper;


    /**
     * @param null|IMapper[] $dependencies
     */
    public function loadDependencies($dependencies = null): void
    {
        $dependencies                     = $dependencies ?: [];
        $dependencies['mapper']           = $dependencies['mapper'] ?? Mapper::getInstance();
        $dependencies['postMapper']       = $dependencies['postMapper'] ?? Post\Mapper::getInstance();
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

            /** @var Model $comment */
            $comment = $bundle->getEvent()->getObject();

            $bundle->attach(self::TRIGGER_CREATED, $comment->getCreatedAt()->getUnixTimeStamp());
            $bundle->attach(self::TRIGGER_COMMENTED, $comment->getCreatedAt()->getUnixTimeStamp());
            $bundle->attach(self::TRIGGER_BLOG_COMMENTED, $comment->getCreatedAt()->getUnixTimeStamp());
            $bundle->attach(self::TRIGGER_FOLLOWED, $comment->getCreatedAt()->getUnixTimeStamp());
        }
    }


    /* ASYNC NOTIFICATION METHODS */

    /**
     * Notify a staff member that someone created a comment on an eportfolio blog
     * post that the staff member authored
     *
     * @param Model $comment
     * @param User  $effector The user who created the comment
     * @return Envelope
     * @throws OutOfBoundsException     If severity is not set
     * @throws InvalidArgumentException If a required property was not set
     */
    public function notifyStaffCreated(Model $comment, User $effector): Envelope
    {
        $post     = $this->postMapper->findById($comment->getBlog()->getId());
        $author   = $this->userMapper->findById($post->getOwner()->getId());
        $audience = $this->userMapper->createCollection();

        if (!$author->getRole()->isStaff()) {
            return new Envelope();
        }
        $audience->add($author);
        return $this->notify($comment, $effector, $audience, Role::STAFF, self::TRIGGER_CREATED);
    }


    /**
     * Notify a staff member that someone commented on their student's blog post
     *
     * @param Model $comment
     * @param User  $effector The user who created the comment
     * @return Envelope
     * @throws OutOfBoundsException     If severity is not set
     * @throws InvalidArgumentException If a required property was not set
     */
    public function notifyStaffCommented(Model $comment, User $effector): Envelope
    {
        $post  = $this->postMapper->findById($comment->getBlog()->getId());
        $owner = $this->userMapper->findById($post->getPage()->getOwner()->getId());

        // Care only student's page
        if (!$owner->getRole()->isStudent()) {
            return new Envelope();
        }

        // Send it to all their teachers (across all subjects)
        $this->externalUser->loadTeachers([$owner]);
        $audience = $owner->getTeacherCollection();

        return $this->notifyCommented($comment, $effector, $audience, Role::STAFF);
    }

    /**
     * Notify a staff that someone commented on one of their eportfolio blog posts
     *
     * @param Model $comment
     * @param User  $effector
     * @return Envelope
     * @throws OutOfBoundsException     If severity is not set
     * @throws InvalidArgumentException If a required property was not set
     */
    public function notifyStaffBlogcommented(Model $comment, User $effector): Envelope
    {
        return $this->notifyOwnerCommented($effector, $comment, Role::STAFF, self::TRIGGER_BLOG_COMMENTED);
    }

    /**
     * Notify a parent that someone commented on one of their eportfolio blog posts
     *
     * @param Model $comment
     * @param User  $effector
     * @return Envelope
     * @throws OutOfBoundsException     If severity is not set
     * @throws InvalidArgumentException If a required property was not set
     */
    public function notifyParentBlogcommented(Model $comment, User $effector): Envelope
    {
        return $this->notifyOwnerCommented($effector, $comment, Role::PARENT, self::TRIGGER_BLOG_COMMENTED);
    }

    /**
     * Notify a student that someone commented on one of their eportfolio blog posts
     *
     * @param Model $comment
     * @param User  $effector
     * @return Envelope
     * @throws OutOfBoundsException     If severity is not set
     * @throws InvalidArgumentException If a required property was not set
     */
    public function notifyStudentCommented(Model $comment, User $effector): Envelope
    {
        return $this->notifyOwnerCommented($effector, $comment, Role::STUDENT, self::TRIGGER_COMMENTED);
    }

    /**
     * Notify the post owner that someone commented on their Eportfolio blog, if the owner is in the given role
     * @param User $effector
     * @param Model $comment
     * @param string $roleType
     * @return Envelope
     */
    private function notifyOwnerCommented(User $effector, Model $comment, string $roleType, string $trigger): Envelope
    {
        try {
            $post = $this->postMapper->findById($comment->getBlog()->getId());
            $owner = $this->userMapper->findById($post->getPage()->getOwner()->getId());

            if ($owner->getRole()->getType() !== $roleType) {
                return new Envelope();
            }

            $envelope = new Envelope();
            $bob = $this->createBuilder($post, $effector, $comment, $trigger);
            $bob->setTitle(":effector commented on your eportfolio blog")
                ->setParams(['effector' => new UserParam($effector)]);
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
     * @param Model $comment
     * @param User  $effector
     * @return Envelope
     * @throws OutOfBoundsException     If severity is not set
     * @throws InvalidArgumentException If a required property was not set
     */
    public function notifyParentCommented(Model $comment, User $effector): Envelope
    {
        $post  = $this->postMapper->findById($comment->getBlog()->getId());
        $owner = $this->userMapper->findById($post->getPage()->getOwner()->getId());
        $post->getPage()->setOwner($owner);

        // Only care about student
        if (!$owner->getRole()->isStudent()) {
            return new Envelope();
        }

        $audience = $this->userMapper->createCollection();

        //make it easier, hit the db here and here again...
        $this->externalUser->loadParents([$owner]);
        $this->externalUser->loadParents($owner->getParents());
        foreach ($owner->getParents() as $parent) {
            try {
                if ($this->access->userCanView($post, $parent)) {
                    $audience[$parent->getId()] = $parent;
                }
            } catch (Exception $e) {
                // noop
            }
        }

        return $this->notifyCommented($comment, $effector, $audience, Role::PARENT);
    }


    /**
     * Notify a staff member that someone commented after them on an eportfolio blog post
     *
     * @param Model $comment
     * @param User  $effector The user who created the comment
     * @return Envelope
     * @throws OutOfBoundsException     If severity is not set
     * @throws InvalidArgumentException If a required property was not set
     */
    public function notifyStaffFollowed(Model $comment, User $effector): Envelope
    {
        $audience = $this->getOtherCommentAuthors($comment, Role::STAFF);
        return $this->notify($comment, $effector, $audience, Role::STAFF, self::TRIGGER_FOLLOWED);
    }


    /**
     * Notify a student that someone commented after them on an eportfolio blog post
     *
     * @param Model $comment
     * @param User  $effector
     * @return Envelope
     * @throws OutOfBoundsException     If severity is not set
     * @throws InvalidArgumentException If a required property was not set
     * @throws BaseMapper\Exception     If invalid column name specified
     */
    public function notifyStudentFollowed(Model $comment, User $effector): Envelope
    {
        $post     = $this->postMapper->findById($comment->getBlog()->getId());
        $audience = $this->userMapper->createCollection();
        $studentAuthors = $this->getOtherCommentAuthors($comment, Role::STUDENT);
        $this->externalUser->loadParents($studentAuthors);
        foreach ($studentAuthors as $student) {
            if ($this->access->userCanView($post, $student)) {
                $audience[$student->getId()] = $student;
            }
        }
        return $this->notify($comment, $effector, $audience, Role::STUDENT, self::TRIGGER_FOLLOWED);
    }


    /**
     * Notify a parent that someone commented after them on an eportfolio blog post
     *
     * @param Model $comment
     * @param User  $effector
     * @return Envelope
     * @throws BaseMapper\Exception
     * @throws InvalidArgumentException If a required property was not set
     * @throws OutOfBoundsException If severity is not set
     */
    public function notifyParentFollowed(Model $comment, User $effector): Envelope
    {
        $post = $this->postMapper->findById($comment->getBlog()->getId());
        $post->getPage()->setOwner($this->userMapper->findById($post->getPage()->getOwner()->getId()));

        $audience = $this->userMapper->createCollection();
        $parentAuthors = $this->getOtherCommentAuthors($comment, Role::PARENT);
        $this->externalUser->loadChildren($parentAuthors);
        foreach ($parentAuthors as $parent) {
            if ($this->access->userCanView($post, $parent)) {
                $audience[$parent->getId()] = $parent;
            }
        }
        return $this->notify($comment, $effector, $audience, Role::PARENT, self::TRIGGER_FOLLOWED);
    }


    /**
     * @param Post\Mapper $postMapper
     * @return $this|self
     */
    protected function setPostMapper(Post\Mapper $postMapper): self
    {
        $this->postMapper = $postMapper;
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
     * @param Access $access
     * @return $this|self
     */
    protected function setBlogAccess(Access $access): self
    {
        $this->access = $access;
        return $this;
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
     * @return string
     */
    protected function getSlug(): string
    {
        return NSMapper::NS_EPORTFOLIO_BLOG;
    }


    /**
     * Common logic for the 'notify{RoleType}Commented()' public methods
     *
     * @param Model      $comment
     * @param User       $effector Parents must be loaded if audience is parents
     * @param Collection $audience
     * @param string     $roleType
     * @return Envelope
     * @throws OutOfBoundsException     If severity is not set
     * @throws InvalidArgumentException If a required property was not set
     */
    private function notifyCommented(Model $comment, User $effector, Collection $audience, string $roleType): Envelope
    {
        return $this->notify($comment, $effector, $audience, $roleType, self::TRIGGER_COMMENTED);
    }


    /**
     * Notify an audience of the specified roleType about the specified trigger occurring.
     *
     * Intentionally shared between both 'created', 'commented' and 'followed' as these are all
     * triggers for the same CRUD method.
     *
     * Consider when a student comments on their own blog post. If another user
     * then comments after them, the owner will receive both a 'commented' and 'followed' notification.
     * By using the same title, the system will deduplicate for us, and the owner will receive a single
     * notification if they are subscribed to either, or both, channels.
     *
     * @param Model      $comment
     * @param User       $effector
     * @param Collection $audience
     * @param string     $roleType
     * @param string     $trigger
     * @return Envelope
     * @throws OutOfBoundsException     If severity is not set
     * @throws InvalidArgumentException If a required property was not set
     */
    private function notify(
        Model $comment,
        User $effector,
        Collection $audience,
        string $roleType,
        string $trigger
    ): Envelope {
        $envelope = new Envelope();
        $post     = $this->postMapper->findById($comment->getBlog()->getId());
        $owner    = $this->userMapper->findById($post->getPage()->getOwner()->getId());

        $this->externalUser->loadChildren($audience);
        foreach ($audience as $target) {
            // Just first name if advising parent of child's action
            /** @var User $target */
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

            $bob = $this->createBuilder($post, $effector, $comment, $trigger);
            $bob->setTitle(":effector commented on {$ownerToken} eportfolio blog")
                ->setParams(array_merge($ownerParam, ['effector' => $effectorParam]));
            //Only add the student external id for the parent notification
            if ($ownerParam['student'] instanceof ChildParam) {
                $bob->setStudentExternalIdMeta($owner->getExternalId());
            }
            $envelope->targetToUser($target->getId(), $bob->build());
        }
        return $envelope;
    }


    /**
     * @param Post\Model $post
     * @param User       $effector
     * @param Model      $comment
     * @param string     $trigger
     * @return Builder
     */
    private function createBuilder(
        Post\Model $post,
        User $effector,
        Model $comment,
        string $trigger
    ): Builder {
        $bob = new Builder();
        $bob->setIcon($this->getUserIcon($effector))
            ->setHref($this->buildBlogUrl($post, $comment))
            ->setSeverity(Logger::INFO)
            ->setNamespace($this->getSlug())
            ->addTopic("{$this->getSlug()}.comment.{$comment->getId()}.$trigger")
            ->setTimestamp($comment->getCreatedAt()->getUnixTimeStamp())
            ->setBody($comment->getBody());
        return $bob;
    }


    /**
     * Generate a URL to send users to see a specific eportfolio blog post
     *
     * @param Post\Model $post
     * @param Model      $comment
     * @return string
     */
    private function buildBlogUrl(Post\Model $post, Model $comment): string
    {
        return self::BASE_URI . "{$post->getPage()->getPageId()}/{$comment->getBlog()->getId()}";
    }


    /**
     * Return a list of users of the specified type if have created adjacent comments
     *
     * @param Model  $comment
     * @param string $roleType
     * @return GenericCollection|User[]
     */
    private function getOtherCommentAuthors(Model $comment, string $roleType): GenericCollection
    {
        $otherComments = $this->getMapper()->findSiblings($comment);
        $audience      = $this->userMapper->createCollection();
        foreach ($otherComments as $otherComment) {
            $target = $this->userMapper->findById($otherComment->getOwner()->getId());
            if ($target->getRole()->getType() !== $roleType) {
                continue;
            }
            $audience[$target->getId()] = $target;
        }
        return $audience;
    }
}
