<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Forum;

use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Parameter\Param;
use Monolog\Logger;
use Schoolbox\Feed\FeedableMapperInterface;
use Schoolbox\Realtime\Notification\Parameter\CategoryParam;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Collection;
use Schoolbox\Feed\SourceInterface;
use Schoolbox\Mapper;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Notification\Parameter\UserParam;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundleInterface;
use Schoolbox\Role;
use Schoolbox\User;

class PostNotifier extends AbstractModuleNotifier implements SourceInterface
{

    // somebody posts on a forum
    const TRIGGER_FOLLOWED  = 'followed';
    // somebody replied to your forum post or thread
    const TRIGGER_MENTIONED = 'mentioned';

    /** @var ThreadMapper */
    protected $threadMapper;

    /** @var FolderMapper */
    protected $categoryMapper;

    /**
     * @param array $dependencies
     */
    public function loadDependencies($dependencies = null): void
    {
        if (empty($dependencies['threadMapper'])) {
            $dependencies['threadMapper'] = ThreadMapper::getInstance();
        }

        if (empty($dependencies['categoryMapper'])) {
            $dependencies['categoryMapper'] = FolderMapper::getInstance();
        }
        parent::loadDependencies($dependencies);
    }


    /**
     * @param Mapper $mapper
     */
    protected function setThreadMapper($mapper): void
    {
        $this->threadMapper = $mapper;
    }


    /**
     * @param Mapper $mapper
     */
    protected function setCategoryMapper($mapper): void
    {
        $this->categoryMapper = $mapper;
    }


    /**
     * @return string
     */
    protected function getSlug(): string
    {
        return NSMapper::NS_FORUM;
    }


    /**
     * @param TriggerBundleInterface $bundle
     * @return void
     */
    public function bundleTriggers($bundle): void
    {
        // this is the only case we're interested in notifying at the moment
        if ($bundle->getMethod() == Mapper::METHOD_CREATE) {
            $bundle->attachEach(array(
                self::TRIGGER_FOLLOWED  => self::IMMEDIATELY,
                self::TRIGGER_MENTIONED => self::IMMEDIATELY
            ));
        }
    }


    /**
     * @inheritdoc
     */
    public function getFeed($context, User $user, $limit = self::HARD_LIMIT, $offset = 0)
    {
        $c = new Collection();

        if (!$this->mapper instanceof FeedableMapperInterface) {
            return $c;
        }

        /** @var Collection|Post[] $posts */
        $posts = $this->mapper->findForFeed($context, $limit, $offset);

        foreach ($posts as $post) {
            $c = $c->merge(
                new Collection(
                    $this->notifyFollowed(
                        $user->getRole()->getType(),
                        $post,
                        $post->getOwner()
                    )->getAll($post->getDate()->getUnixTimeStamp())
                )
            );
        }

        return $c;
    }


    /**
     * If returns false, the given model will be hydrated before being returned to the calling method
     *
     * @param Post $model
     * @return bool
     */
    protected function isHydrated($model): bool
    {
        $thread = !!$model->getThread()->getId();
        $owner  = !!$model->getOwner()->getId();
        // not testing getParent() - is allowed to be null

        return $thread && $owner;
    }


    /**
     * @param Post $post
     * @param Thread $thread
     * @param User $poster
     * @param string $title
     * @param string $action
     * @param bool $deduplicate
     * @return Builder
     */
    protected function newBuilder($post, $thread, User $poster, string $title, string $action, bool $deduplicate = false): Builder
    {
        $bob    = new Builder();
        try {
            $cat    = $this->categoryMapper->findById($thread->getCategory()->getId());
        } catch (Mapper\Exception $e) {
            return $bob;
        }

        $postId = $post->getId();
        $href   = '/forum/thread/' . $thread->getId();

        $bob->setTitle($title)
            ->setParams(array(
                'user'   => new UserParam($poster),
                'thread' => new Param($thread->getTitle(), $href),
                'folder' => new CategoryParam($cat)
            ))
            ->setIcon($this->getUserIcon($poster))
            ->setHref($href . ($deduplicate ? "#$postId" : ''))
            ->setSeverity(Logger::INFO)
            ->setFolderId($cat->getId())
            ->setNamespace($this->getSlug())
            ->setBody($post->getBodyWithoutQuote())
            ->addTopic("forum.$postId.$action");

        return $bob;
    }


    /**
     * Notifies about the new post to all the users who has posted in the same thread,
     * except for the author of its parent post and the owner of the thread.
     *
     * @param string $roletype
     * @param Post   $post
     * @param User   $poster
     * @return Envelope
     */
    protected function notifyFollowed(string $roletype, Post $post, User $poster): Envelope
    {
        /** @var Post $post */
        $post     = $this->hydrate($post);
        $envelope = new Envelope();
        try {
            $thread = $this->threadMapper->findById($post->getThread()->getId());
        } catch (Mapper\Exception $e) {
            return $envelope;
        }

        $bob = $this->newBuilder($post, $thread, $poster, ':user posted in :thread in :folder', self::TRIGGER_FOLLOWED)
            ->addFilter('roletype', array($roletype));

        $quotedId = 0;

        /** @var Post $parent */
        $parent = $post->getParent();

        if ($parent instanceof Post) {
            try {
                $parent   = $this->mapper->findById($parent->getId());
                $quotedId = $parent->getOwner()->getId();
            } catch (Mapper\Exception $e) {
                // leave quoted ID at zero
            }
        }

        $threadOwnerId = $thread->getUser()->getId();
        foreach ($this->threadMapper->findInvolvedUsers($post->getThread()) as $user) {
            if ($user->getId() !== $quotedId && $user->getId() !== $threadOwnerId) {
                $envelope->targetToUser($user->getId(), $bob->build());
            }
        }

        return $envelope;
    }


    /**
     * Notifies the author of the parent post of the given post and the owner of the thread, that a reply has been posted.
     *
     * @param string $roletype
     * @param Post   $post
     * @param User   $poster
     * @return Envelope
     */
    protected function notifyMentioned(string $roletype, Post $post, User $poster): Envelope
    {
        /** @var Post $post */
        $post     = $this->hydrate($post);
        $envelope = new Envelope();
        try {
            $thread = $this->threadMapper->findById($post->getThread()->getId());
        } catch (Mapper\Exception $e) {
            return $envelope;
        }

        $bob = $this->newBuilder($post, $thread, $poster, ':user replied to your post in :thread in :folder', self::TRIGGER_MENTIONED, true)
            ->addFilter('roletype', array($roletype));

        $parent = $post->getParent();
        if ($parent instanceof Post) {
            try {
                /** @var Post $parent */
                $parent = $this->mapper->findById($parent->getId());
                $envelope->targetToUser($parent->getOwner(), $bob->build());
                // Target to the thread owner as well
                if ($parent->getOwner()->getId() !== $thread->getUser()->getId()) {
                    $envelope->targetToUser($thread->getUser(), $bob->build());
                }
            } catch (Mapper\Exception $e) {
                // I can't target a non-existent post
            }
        }

        return $envelope;
    }


    /**
     * @param Post $post
     * @param User $poster
     * @return Envelope
     */
    public function notifyStaffFollowed(Post $post, User $poster): Envelope
    {
        return $this->notifyFollowed(Role::STAFF, $post, $poster);
    }


    /**
     * @param Post $post
     * @param User $poster
     * @return Envelope
     */
    public function notifyStudentFollowed(Post $post, User $poster): Envelope
    {
        return $this->notifyFollowed(Role::STUDENT, $post, $poster);
    }


    /**
     * @param Post $post
     * @param User $poster
     * @return Envelope
     */
    public function notifyParentFollowed(Post $post, User $poster): Envelope
    {
        return $this->notifyFollowed(Role::PARENT, $post, $poster);
    }


    /**
     * @param Post $post
     * @param User $poster
     * @return Envelope
     */
    public function notifyStaffMentioned(Post $post, User $poster): Envelope
    {
        return $this->notifyMentioned(Role::STAFF, $post, $poster);
    }


    /**
     * @param Post $post
     * @param User $poster
     * @return Envelope
     */
    public function notifyStudentMentioned(Post $post, User $poster): Envelope
    {
        return $this->notifyMentioned(Role::STUDENT, $post, $poster);
    }


    /**
     * @param Post $post
     * @param User $poster
     * @return Envelope
     */
    public function notifyParentMentioned(Post $post, User $poster): Envelope
    {
        return $this->notifyMentioned(Role::PARENT, $post, $poster);
    }
}
