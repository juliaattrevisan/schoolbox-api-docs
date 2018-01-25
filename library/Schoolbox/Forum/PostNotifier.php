<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Forum;


use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Instance\Instance;
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

    const TRIGGER_FOLLOWED  = 'followed';
    const TRIGGER_MENTIONED = 'mentioned';

    /** @var ThreadMapper */
    protected $threadMapper;

    /** @var FolderMapper */
    protected $categoryMapper;

    /**
     * @param array $dependencies
     */
    public function loadDependencies($dependencies = null)
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
    protected function setThreadMapper($mapper)
    {
        $this->threadMapper = $mapper;
    }


    /**
     * @param Mapper $mapper
     */
    protected function setCategoryMapper($mapper)
    {
        $this->categoryMapper = $mapper;
    }


    /**
     * @return string
     */
    protected function getSlug()
    {
        return NSMapper::NS_FORUM;
    }


    /**
     * @param TriggerBundleInterface $bundle
     * @return void
     */
    public function bundleTriggers($bundle)
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
     * Generate n Instances, from the perspective of casual observation (rather than targeted at a particular user).
     *
     * @param int    $context  - most likely a category ID or ignored
     * @param string $roletype - the roletype of the user for whom this feed is being retrieved
     * @param int    $limit    - how many messages to retrieve
     * @param int    $offset   - by default, most recent
     * @return Collection|Instance[]
     */
    public function getFeed($context, $roletype, $limit = self::HARD_LIMIT, $offset = 0)
    {
        $c = new Collection();

        if(!$this->mapper instanceof FeedableMapperInterface) {
            return $c;
        }

        /** @var Collection|Post[] $posts */
        $posts = $this->mapper->findForFeed($context, $limit, $offset);

        foreach ($posts as $post) {
            $c = $c->merge(
                new Collection(
                    $this->notifyFollowed(
                        $roletype,
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
    protected function isHydrated($model)
    {
        $thread = !!$model->getThread()->getId();
        $owner  = !!$model->getOwner()->getId();
        // not testing getParent() - is allowed to be null

        return $thread && $owner;
    }


    /**
     * @param Post   $post
     * @param User   $poster
     * @param string $title
     * @param string $action
     * @param bool   $deduplicate
     * @return Builder
     */
    protected function newBuilder($post, $poster, $title, $action, $deduplicate = false)
    {
        $bob    = new Builder();
        $thread = $this->threadMapper->findById($post->getThread()->getId());
        $cat    = $this->categoryMapper->findById($thread->getCategory()->getId());
        $postId = $post->getId();
        $href   = '/forum/thread/' . $thread->getId();

        $bob->setTitle($title)
            ->setParams(array(
                'user'   => new UserParam($poster),
                'thread' => new Param($thread->getTitle(), $href),
                'folder' => new CategoryParam($cat)
            ))
            ->setIcon("/portrait.php?size={$this->iconSize}&userId=" . $poster->getExternalId())
            ->setHref($href . ($deduplicate ? "#$postId" : ''))
            ->setSeverity(Logger::INFO)
            ->setNamespace($this->getSlug())
            ->setBody($post->getBodyWithoutQuote())
            ->addTopic("forum.$postId.$action");

        return $bob;
    }


    /**
     * @param string $roletype
     * @param Post   $post
     * @param User   $poster
     * @return Envelope
     */
    protected function notifyFollowed($roletype, $post, $poster)
    {
        /** @var Post $post */
        $post     = $this->hydrate($post);
        $envelope = new Envelope();

        try {
            $bob      = $this->newBuilder($post, $poster, ':user posted in :thread in :folder', self::TRIGGER_FOLLOWED)
                             ->addFilter('roletype', array($roletype));
        } catch (Mapper\Exception $e) {
            return $envelope;
        }

        /** @var Post $parent */
        $quotedId = 0;
        $parent = $post->getParent();
        if ($parent instanceof Post) {
            try {
                $parent   = $this->mapper->findById($parent->getId());
                $quotedId = $parent->getOwner()->getId();
            } catch (Mapper\Exception $e) {
                // leave quoted ID at zero
            }
        }

        foreach ($this->threadMapper->findInvolvedUsers($post->getThread()) as $user) {
            if ($user->getId() != $quotedId) {
                $envelope->targetToUser($user->getId(), $bob->build());
            }
        }

        return $envelope;
    }


    /**
     * @param string $roletype
     * @param Post   $post
     * @param User   $poster
     * @return Envelope
     */
    protected function notifyMentioned($roletype, $post, $poster)
    {
        /** @var Post $post */
        $post     = $this->hydrate($post);
        $envelope = new Envelope();
        try {
            $bob      = $this->newBuilder($post, $poster, ':user replied to your post in :thread in :folder', self::TRIGGER_MENTIONED, true)
                             ->addFilter('roletype', array($roletype));
        } catch (Mapper\Exception $e) {
            return $envelope;
        }

        $parent = $post->getParent();
        if ($parent instanceof Post) {
            try {
                $parent = $this->mapper->findById($parent->getId());
                $envelope->targetToUser($parent->getOwner(), $bob->build());
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
    public function notifyStaffFollowed($post, $poster)
    {
        return $this->notifyFollowed(Role::STAFF, $post, $poster);
    }


    /**
     * @param Post $post
     * @param User $poster
     * @return Envelope
     */
    public function notifyStudentFollowed($post, $poster)
    {
        return $this->notifyFollowed(Role::STUDENT, $post, $poster);
    }


    /**
     * @param Post $post
     * @param User $poster
     * @return Envelope
     */
    public function notifyParentFollowed($post, $poster)
    {
        return $this->notifyFollowed(Role::PARENT, $post, $poster);
    }


    /**
     * @param Post $post
     * @param User $poster
     * @return Envelope
     */
    public function notifyStaffMentioned($post, $poster)
    {
        return $this->notifyMentioned(Role::STAFF, $post, $poster);
    }


    /**
     * @param Post $post
     * @param User $poster
     * @return Envelope
     */
    public function notifyStudentMentioned($post, $poster)
    {
        return $this->notifyMentioned(Role::STUDENT, $post, $poster);
    }


    /**
     * @param Post $post
     * @param User $poster
     * @return Envelope
     */
    public function notifyParentMentioned($post, $poster)
    {
        return $this->notifyMentioned(Role::PARENT, $post, $poster);
    }

}
