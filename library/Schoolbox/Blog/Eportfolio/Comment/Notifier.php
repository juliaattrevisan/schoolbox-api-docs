<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Blog\Eportfolio\Comment;

use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Parameter\ParameterInterface;
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
use Schoolbox\RoleMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;
use OutOfBoundsException;
use InvalidArgumentException;
use External_UserInterface;
use External_User;
use External_ClassInterface;
use External_Class;

/**
 * @method Mapper getMapper()
 */
class Notifier extends AbstractModuleNotifier
{

    const TRIGGER_CREATED = 'created';
    const TRIGGER_COMMENTED = 'commented';
    const TRIGGER_FOLLOWED = 'followed';

    const BASE_URI = '/eportfolio/blog/';

    /**
     * @var Post\Mapper
     */
    private $postMapper;

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
        $dependencies['postMapper']       = $dependencies['postMapper'] ?? Post\Mapper::getInstance();
        $dependencies['permissionMapper'] = $dependencies['permissionMapper'] ?? PermissionMapper::getInstance();
        $dependencies['userMapper']       = $dependencies['userMapper'] ?? UserMapper::getInstance();
        $dependencies['externalUser']     = $dependencies['externalUser'] ?? new External_User();
        $dependencies['externalClass']    = $dependencies['externalClass'] ?? new External_Class();
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

            //TODO remove this hack when SA-4400 is merged. (timestamp population)
            $comment = $this->getMapper()->findByColumn(
                $this->getMapper()->getIdentifierColumn(),
                $comment->getId(),
                ['cache' => false]
            )->first();
            $bundle->getEvent()->setObject($comment);

            $bundle->attach(self::TRIGGER_CREATED, $comment->getCreatedAt()->getUnixTimeStamp());
            $bundle->attach(self::TRIGGER_COMMENTED, $comment->getCreatedAt()->getUnixTimeStamp());
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

        if ($author->getRole()->isStaff()) {
            $audience->add($author);
        }
        return $this->notify($comment, $effector, $audience, RoleMapper::ROLE_STAFF, self::TRIGGER_CREATED);
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

        // Send it to all their teachers (across all subjects)
        $audience = $this->getStudentTeachers($owner);

        return $this->notifyCommented($comment, $effector, $audience, RoleMapper::ROLE_STAFF);
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
        $post     = $this->postMapper->findById($comment->getBlog()->getId());
        $owner    = $this->userMapper->findById($post->getPage()->getOwner()->getId());
        $audience = $this->userMapper->createCollection([$owner]);

        return $this->notifyCommented($comment, $effector, $audience, RoleMapper::ROLE_STUDENT);
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

        // Empty audience if parents don't have view permission
        $audience = $this->isPostVisibleToParents($post)
            ? $owner->loadParents()->getParents()
            : $this->userMapper->createCollection();

        return $this->notifyCommented($comment, $effector, $audience, RoleMapper::ROLE_PARENT);
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
        return $this->notifyFollowed($comment, $effector, RoleMapper::ROLE_STAFF);
    }


    /**
     * Notify a student that someone commented after them on an eportfolio blog post
     *
     * @param Model $comment
     * @param User  $effector
     * @return Envelope
     * @throws OutOfBoundsException     If severity is not set
     * @throws InvalidArgumentException If a required property was not set
     */
    public function notifyStudentFollowed(Model $comment, User $effector): Envelope
    {
        return $this->notifyFollowed($comment, $effector, RoleMapper::ROLE_STUDENT);
    }


    /**
     * Notify a parent that someone commented after them on an eportfolio blog post
     *
     * @param Model $comment
     * @param User  $effector
     * @return Envelope
     * @throws OutOfBoundsException     If severity is not set
     * @throws InvalidArgumentException If a required property was not set
     */
    public function notifyParentFollowed(Model $comment, User $effector): Envelope
    {
        return $this->notifyFollowed($comment, $effector, RoleMapper::ROLE_PARENT);
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
     * @param PermissionMapper $permissionMapper
     * @return $this|self
     */
    protected function setPermissionMapper(PermissionMapper $permissionMapper): self
    {
        $this->permissionMapper = $permissionMapper;
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
     * Common logic for the 'notify{RoleType}Followed()' public methods
     *
     * @param Model  $comment
     * @param User   $effector
     * @param string $roleType
     * @return Envelope
     * @throws OutOfBoundsException     If severity is not set
     * @throws InvalidArgumentException If a required property was not set
     */
    private function notifyFollowed(Model $comment, User $effector, string $roleType): Envelope
    {
        $otherComments = $this->getMapper()->findSiblings($comment);
        $audience      = $this->userMapper->createCollection();
        foreach ($otherComments as $otherComment) {
            $target                     = $otherComment->getOwner();
            $audience[$target->getId()] = $target;
        }

        return $this->notify($comment, $effector, $audience, $roleType, self::TRIGGER_FOLLOWED);
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
        $owner    = $this->userMapper->findById($post->getPage()->getOwner()->getId())->loadParents();

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

            $bob = $this->createBuilder($post, $effector, $comment, $trigger);
            $bob->setTitle(":effector commented on {$ownerToken} eportfolio blog")
                ->setParams(array_merge($ownerParam, ['effector' => $effectorParam]));
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
        $bob->setIcon("/portrait.php?userId={$effector->getExternalId()}&size={$this->iconSize}")
            ->setHref($this->buildBlogUrl($post, $comment))
            ->setSeverity(Logger::INFO)
            ->setNamespace($this->getSlug())
            ->addTopic("{$this->getSlug()}.comment.{$comment->getId()}.$trigger")
            ->setTimestamp((int)$comment->getCreatedAt()->getUnixTimeStamp())
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
     * Return whether the specified is visible to parents
     *
     * @param Post\Model $post
     * @return bool
     */
    private function isPostVisibleToParents(Post\Model $post): bool
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
