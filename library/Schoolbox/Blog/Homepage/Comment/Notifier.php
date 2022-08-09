<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Blog\Homepage\Comment;

use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Parameter\Param;
use Monolog\Logger;
use Schoolbox\Blog\Homepage\Comment\Model as BlogCommentModel;
use Schoolbox\Blog\Homepage\Post\Mapper as BlogMapper;
use Schoolbox\Blog\Homepage\Post\Model as BlogModel;
use Schoolbox\Collection;
use Schoolbox\Feed\FeedableMapperInterface;
use Schoolbox\Feed\SourceInterface;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Notification\Parameter\CategoryParam;
use Schoolbox\Realtime\Notification\Parameter\UserParam;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundle;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\User;

class Notifier extends AbstractModuleNotifier implements SourceInterface
{
    const TRIGGER_COMMENTED = 'commented';
    const TRIGGER_FOLLOWED = 'followed';

    /** @var BlogMapper */
    protected $blogMapper;

    /** @var FolderMapper */
    protected $catMapper;

    /**
     * Instantiate all your dependencies within yourself.
     *
     * @param mixed $dependencies
     * @return void
     */
    public function loadDependencies($dependencies = null)
    {
        $this->blogMapper = BlogMapper::getInstance();
        $this->catMapper  = FolderMapper::getInstance();
        parent::loadDependencies($dependencies);
    }

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
        // we only care about create
        if ($bundle->getMethod() == BaseMapper::METHOD_CREATE) {
            $bundle->attachEach([
                self::TRIGGER_COMMENTED => self::IMMEDIATELY,
                self::TRIGGER_FOLLOWED  => self::IMMEDIATELY,
            ]);
        }
    }

    /**
     * @inheritdoc
     */
    public function getFeed($context, User $user, $limit = self::HARD_LIMIT, $offset = 0)
    {
        $c = new Collection();

        // be sure the target method exists
        if (!$this->mapper instanceof FeedableMapperInterface) {
            return $c;
        }

        /** @var Collection|BlogCommentModel[] $comments */
        $comments = $this->mapper->findForFeed($context, $limit, $offset);

        foreach ($comments as $comment) {
            $c = $c->merge(
                new Collection(
                    $this->notifyCommented(
                        $comment,
                        $comment->getOwner()
                    )->getAll($comment->getCreatedAt()->getUnixTimeStamp())
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

    /**
     * @param BlogCommentModel $comment
     * @param User             $author
     * @return Builder
     */
    protected function createBuilder($comment, $author)
    {
        /** @var BlogModel $blog */
        $blog        = $this->blogMapper->findById($comment->getBlog()->getId());
        $blogName    = $blog->getHeading();
        $folderId    = (int)$blog->getFolder()->getId();
        $authorExtId = $author->getExternalId();

        /** @var FolderModel $folder */
        $folder = FolderMapper::getInstance()->findById($folderId);

        $bob = new Builder();


        $bob->setParams([
            'author'  => new UserParam($author),
            'title'   => new Param($blogName, "/blog/" . $blog->getId()),
            'catname' => new CategoryParam($folder),
        ])
            ->setIcon($this->getUserIcon($author))
            ->setHref("/blog/" . $blog->getId())// dedupe per blog
            ->setSeverity(Logger::INFO)
            ->setTimestamp($comment->getCreatedAt()->getUnixTimeStamp())
            ->setNamespace($this->getSlug())
            ->setBody($comment->getBody())
            ->setFolderId($folderId)
            ->setByline($author->formatName("{givenname} wrote"));

        return $bob;
    }

    /* ASYNC NOTIFICATION METHODS */

    /**
     * @param BlogCommentModel $comment
     * @param User             $author
     * @return Envelope
     */
    protected function notifyCommented($comment, $author)
    {
        $envelope = new Envelope();

        $commentId   = $comment->getId();
        $blog        = $this->blogMapper->findById($comment->getBlog()->getId());
        $blogOwnerId = $blog->getOwner()->getId();

        $bob = $this->createBuilder($comment, $author)
                    ->setTitle(':author commented on :title in :catname')
                    ->addTopic("{$this->getSlug()}.$commentId.commented");

        // send this to the blog post author
        $envelope->targetToUser($blogOwnerId, $bob->build());
        return $envelope;
    }

    /**
     * @param BlogCommentModel $comment
     * @param User             $author
     * @return Envelope
     * @throws BaseMapper\Exception
     * @throws BaseMapper\Exception\Deleted
     * @throws \InvalidArgumentException
     * @throws \OutOfBoundsException
     */
    protected function notifyFollowed($comment, $author)
    {
        $envelope = new Envelope();

        /** @var BlogModel $blog */
        $blog      = $this->blogMapper->findById($comment->getBlog()->getId());
        $commentId = $comment->getId();

        $bob = $this->createBuilder($comment, $author)
                    ->setTitle(':author commented on :title in :catname')
                    ->addTopic("{$this->getSlug()}.$commentId.followed");

        $commentCollection = $this->mapper->findByColumn('blog_id', $blog->getId());

        /** @var User[]|Collection $userCollection */
        $userCollection = new Collection($commentCollection->getColumnValues('owner'));
        $userIds        = array_unique($userCollection->getColumnValues('id'));

        foreach ($userIds as $id) {
            // send this to anyone who has commented on the blog in question
            $envelope->targetToUser($id, $bob->build());
        }

        return $envelope;
    }

    /**
     * @param BlogCommentModel $comment
     * @param User             $author
     * @param array            $diff
     * @return Envelope
     */
    public function notifyStudentCommented($comment, $author, $diff = [])
    {
        return $this->notifyCommented($comment, $author);
    }

    /**
     * @param BlogCommentModel $comment
     * @param User             $author
     * @param array            $diff
     * @return Envelope
     */
    public function notifyStaffCommented($comment, $author, $diff = [])
    {
        return $this->notifyCommented($comment, $author);
    }

    /**
     * @param BlogCommentModel $comment
     * @param User             $author
     * @param array            $diff
     * @return Envelope
     */
    public function notifyParentCommented($comment, $author, $diff = [])
    {
        return $this->notifyCommented($comment, $author);
    }

    /**
     * @param BlogCommentModel $comment
     * @param User             $author
     * @param array            $diff
     * @return Envelope
     */
    public function notifyStudentFollowed($comment, $author, $diff = [])
    {
        return $this->notifyFollowed($comment, $author);
    }

    /**
     * @param BlogCommentModel $comment
     * @param User             $author
     * @param array            $diff
     * @return Envelope
     */
    public function notifyStaffFollowed($comment, $author, $diff = [])
    {
        return $this->notifyFollowed($comment, $author);
    }

    /**
     * @param BlogCommentModel $comment
     * @param User             $author
     * @param array            $diff
     * @return Envelope
     */
    public function notifyParentFollowed($comment, $author, $diff = [])
    {
        return $this->notifyFollowed($comment, $author);
    }
}
