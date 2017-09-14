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

class Notifier extends AbstractModuleNotifier
{

    const TRIGGER_COMMENTED = 'commented';
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
     * @param null|IMapper[] $dependencies
     */
    public function loadDependencies($dependencies = null): void
    {
        $dependencies                     = $dependencies ?: [];
        $dependencies['mapper']           = $dependencies['mapper'] ?? Mapper::getInstance();
        $dependencies['postMapper']       = $dependencies['postMapper'] ?? Post\Mapper::getInstance();
        $dependencies['permissionMapper'] = $dependencies['permissionMapper'] ?? PermissionMapper::getInstance();
        $dependencies['userMapper']       = $dependencies['userMapper'] ?? UserMapper::getInstance();
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

            /** @var Model $comment */
            $comment = $bundle->getEvent()->getObject();

            //TODO remove this hack when SA-4400 is merged. (timestamp population)
            $comment = $this->getMapper()->findByColumn(
                $this->getMapper()->getIdentifierColumn(), $comment->getId(), ['cache' => false]
            )->first();
            $bundle->getEvent()->setObject($comment);

            $bundle->attach(self::TRIGGER_COMMENTED, $comment->getCreatedAt()->getUnixTimeStamp());
        }
    }


    /* ASYNC NOTIFICATION METHODS */

    /**
     * Notify a staff member that someone commented on an eportfolio blog post that the
     * staff member authored
     *
     * @param Model $comment
     * @param User  $effector The user who created the comment
     * @return Envelope
     * @throws OutOfBoundsException     If severity is not set
     * @throws InvalidArgumentException If a required property was not set
     */
    public function notifyStaffCommented(Model $comment, User $effector): Envelope
    {
        $post     = $this->postMapper->findById($comment->getBlog()->getId());
        $author   = $this->userMapper->findById($post->getOwner()->getId());
        $audience = $this->userMapper->createCollection();

        if($author->getRole()->isStaff()){
            $audience->add($author);
        }
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
        return $this->notifyCommented($comment, $effector, $audience, 'your');
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

        // Can parents view the page where the blog post resides?
        $parentsCanView = false;
        foreach ($this->permissionMapper->findParentSetting($post->getPage()->getPageId()) as $permission) {
            if ($permission->getValue()) {
                $parentsCanView = true;
                break;
            }
        }

        // Empty audience if parents don't have view permission
        $audience = $parentsCanView
            ? $owner->loadParents()->getParents()
            : $this->userMapper->createCollection();

        // Did student comment on their own blog?
        if((int)$owner->getId() === (int)$effector->getId()){
            $studentToken = 'their';
            $studentParam = [];
        } else {
            $studentToken = ":student's";
            // Parents only need to see child's first name
            $studentParam = ['student' => new ChildParam($owner, ParameterInterface::STACKING_REPLACE)];
        }

        return $this->notifyCommented($comment, $effector, $audience, $studentToken, $studentParam);
    }


    /**
     * Common logic for the 'notify{RoleType}Commented()' public methods
     *
     * @param Model       $comment
     * @param User        $effector
     * @param Collection  $audience
     * @param string      $studentToken
     * @param UserParam[] $studentParam
     * @return Envelope
     * @throws OutOfBoundsException     If severity is not set
     * @throws InvalidArgumentException If a required property was not set
     */
    private function notifyCommented(
        Model $comment,
        User $effector,
        Collection $audience,
        string $studentToken,
        array $studentParam = []
    ): Envelope {
        $envelope    = new Envelope();
        $bob         = new Builder();
        $post        = $this->postMapper->findById($comment->getBlog()->getId());
        $authorExtId = $effector->getExternalId();

        $bob->setTitle(":commenter commented on {$studentToken} eportfolio blog")
            ->setParams(array_merge($studentParam, [
                'commenter' => new UserParam($effector, ParameterInterface::STACKING_REPLACE),
            ]))
            ->setIcon("/portrait.php?userId=$authorExtId&size={$this->iconSize}")
            ->setHref($this->buildBlogUrl($post, $comment))
            ->setSeverity(Logger::INFO)
            ->setNamespace($this->getSlug())
            ->addTopic("eportfolio.blog.comment.{$comment->getId()}." . self::TRIGGER_COMMENTED)
            ->setTimestamp($comment->getCreatedAt()->getUnixTimeStamp())
            ->setBody($comment->getBody());

        foreach ($audience as $target) {
            $envelope->targetToUser($target->getId(), $bob->build());
        }
        return $envelope;
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
     * @return string
     */
    protected function getSlug(): string
    {
        return NSMapper::NS_EPORTFOLIO_BLOG;
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
} 
