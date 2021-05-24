<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Blog\Homepage\Post;

use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Parameter\Param;
use Monolog\Logger;
use Schoolbox\Blog\Homepage\Post\Model as BlogPost;
use Schoolbox\Collection;
use Schoolbox\Feed\FeedableMapperInterface;
use Schoolbox\Feed\SourceInterface;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Notification\Parameter\CategoryParam;
use Schoolbox\Realtime\Notification\Parameter\UserParam;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundle;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Role;
use Schoolbox\User;

class Notifier extends AbstractModuleNotifier implements SourceInterface
{

    const TRIGGER_POSTED = 'posted';


    /**
     * @return string
     */
    protected function getSlug()
    {
        return NSMapper::NS_HOMEPAGE_BLOG;
    }


    /**
     * @param TriggerBundle $bundle
     * @return void
     */
    public function bundleTriggers($bundle)
    {
        // we only care about notifying blog post creation
        if ($bundle->getMethod() == BaseMapper::METHOD_CREATE) {
            $bundle->attach(self::TRIGGER_POSTED, self::IMMEDIATELY);
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

        /** @var BlogPost[]|Collection $blogs */
        $blogs = $this->mapper->findForFeed($context, $limit, $offset);

        foreach ($blogs as $blog) {
            $c = $c->merge(
                new Collection(
                    $this->notifyPosted(
                        $user->getRole()->getType(),
                        $blog,
                        $blog->getOwner()
                    )->getAll($blog->getCreatedAt()->getUnixTimeStamp())
                )
            );
        }
        return $c;
    }


    /**
     * Returns the metadata pertaining to this source.
     * Useful to RSS
     *
     * @return mixed
     */
    public function getChannelMetadata()
    {
        return null;
    }


    /* ASYNC NOTIFICATION METHODS */

    /**
     * @param string   $roletype
     * @param BlogPost $blog
     * @param User     $author
     * @return Envelope
     */
    protected function notifyPosted(string $roletype, BlogPost $blog, User $author): Envelope
    {
        $envelope = new Envelope();

        $blogId = $blog->getId();

        $folderId = $blog->getFolder()->getId();

        try {
            $folder = FolderMapper::getInstance()->findById($folderId);
        } catch (BaseMapper\Exception $e) {
            return $envelope;
        }

        $bob = new Builder();

        $bob->setTitle(':author blogged about :title in :catname')
            ->setParams([
                'author'  => new UserParam($author),
                'title'   => new Param($blog->getHeading(), "/blog/$blogId"),
                'catname' => new CategoryParam($folder),
            ])
            ->setIcon($this->getUserIcon($author))
            ->setHref("/blog/$blogId")
            ->setSeverity(Logger::INFO)
            ->setNamespace($this->getSlug())
            ->addTopic("{$this->getSlug()}.$blogId.posted")
            ->setTimestamp($blog->getCreatedAt()->getUnixTimeStamp())
            ->addFilter('roletype', [$roletype])
            ->setBody($blog->getBody());

        $envelope->broadcastToCategory($folderId, UserAccess::ACCESS_READ, $bob->build());

        return $envelope;
    }


    /**
     * @param BlogPost   $blog
     * @param User       $author
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyStaffPosted($blog, $author, $diff = null)
    {
        return $this->notifyPosted(Role::STAFF, $blog, $author);
    }


    /**
     * @param BlogPost   $blog
     * @param User       $author
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyStudentPosted($blog, $author, $diff = null)
    {
        return $this->notifyPosted(Role::STUDENT, $blog, $author);
    }

    /**
     * @param BlogPost   $blog
     * @param User       $author
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyParentPosted($blog, $author, $diff = null)
    {
        return $this->notifyPosted(Role::PARENT, $blog, $author);
    }
}
