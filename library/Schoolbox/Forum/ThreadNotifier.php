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
use Schoolbox\Collection;
use Schoolbox\Feed\FeedableMapperInterface;
use Schoolbox\Feed\SourceInterface;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Notification\Parameter\CategoryParam;
use Schoolbox\Realtime\Notification\Parameter\UserParam;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundleInterface;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Role;
use Schoolbox\User;

class ThreadNotifier extends AbstractModuleNotifier implements SourceInterface {

    const TRIGGER_FOLLOWED  = 'followed';

    /** @var FolderMapper */
    protected $categoryMapper;


    /**
     * @param array $dependencies
     */
    public function loadDependencies($dependencies = null)
    {
        if (empty($dependencies['categoryMapper'])) {
            $dependencies['categoryMapper'] = FolderMapper::getInstance();
        }
        parent::loadDependencies($dependencies);

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
            $bundle->attach(self::TRIGGER_FOLLOWED, self::IMMEDIATELY);
        }

    }


    /**
     * @inheritdoc
     */
    public function getFeed($context, User $user, $limit = self::HARD_LIMIT, $offset = 0)
    {
        $c = new Collection();

        if(!$this->mapper instanceof FeedableMapperInterface) {
            return $c;
        }

        /** @var Collection|Thread[] $threads */
        $threads = $this->mapper->findForFeed($context, $limit, $offset);

        foreach ($threads as $thread) {
            $c = $c->merge(
                new Collection(
                    $this->notifyFollowed(
                        $user->getRole()->getType(),
                        $thread,
                        $thread->getUser()
                    )->getAll($thread->getDate()->getUnixTimeStamp())
                )
            );
        }

        return $c;
    }

    /**
     * @param Thread $thread
     * @param User   $author
     * @param string $title
     * @param string $action
     * @return Builder
     */
    protected function newBuilder($thread, $author, $title, $action)
    {
        $bob      = new Builder();
        $cat      = $this->categoryMapper->findById($thread->getCategory()->getId());
        $threadId = $thread->getId();

        /** @var Thread $thread */
        $thread   = $this->mapper->findById($threadId);
        $post     = $thread->getFirstPost();
        $href     = "/forum/thread/$threadId";

        $bob->setTitle($title)
            ->setParams(array(
                'user'   => new UserParam($author),
                'thread' => new Param($thread->getTitle(), $href),
                'folder' => new CategoryParam($cat)
            ))
            ->setIcon("/portrait.php?size={$this->iconSize}&userId=" . $author->getExternalId())
            ->setHref($href)
            ->setSeverity(Logger::INFO)
            ->setNamespace($this->getSlug())
            ->setBody($post->getBodyWithoutQuote())
            ->addTopic("forum.$threadId.$action");

        return $bob;
    }


    /**
     * @param string $roletype
     * @param Thread $thread
     * @param User   $author
     * @return Envelope
     */
    protected function notifyFollowed($roletype, $thread, $author)
    {
        $envelope = new Envelope();
        $bob      = $this->newBuilder($thread, $author, ':user started a new thread, :thread, in :folder', self::TRIGGER_FOLLOWED)
                         ->addFilter('roletype', array($roletype));

        $envelope->broadcastToCategory($thread->getCategory()->getId(), UserAccess::ACCESS_READ, $bob->build());

        return $envelope;
    }


    /**
     * @param Thread $thread
     * @param User $author
     * @return Envelope
     */
    public function notifyStaffFollowed($thread, $author)
    {
        return $this->notifyFollowed(Role::STAFF, $thread, $author);
    }


    /**
     * @param Thread $thread
     * @param User $author
     * @return Envelope
     */
    public function notifyStudentFollowed($thread, $author)
    {
        return $this->notifyFollowed(Role::STUDENT, $thread, $author);
    }


    /**
     * @param Thread $thread
     * @param User $author
     * @return Envelope
     */
    public function notifyParentFollowed($thread, $author)
    {
        return $this->notifyFollowed(Role::PARENT, $thread, $author);
    }


}
