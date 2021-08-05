<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace SocialStream;

use Alaress\Async\Notification\Instance\Builder;
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
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundle;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\UserMapper;
use Scrub;
use SocialStream_Post_Abstract;

class PostNotifier extends AbstractModuleNotifier implements SourceInterface
{

    const TRIGGER_POSTED = 'posted';
    const TRIGGER_REPLIED = 'replied';

    /** @var FolderMapper */
    protected $catMapper;

    /** @var UserAccessMapper */
    protected $userAccessMapper;

    /** @var UserMapper */
    protected $userMapper;

    /**
     * @return string
     */
    protected function getSlug(): string
    {
        return NSMapper::NS_SSTREAM;
    }


    /**
     * Instantiate all your dependencies within yourself.
     *
     * @param mixed $dependencies
     * @return void
     */
    public function loadDependencies($dependencies = null): void
    {
        $this->catMapper = FolderMapper::getInstance();
        $this->userAccessMapper = UserAccessMapper::getInstance();
        $this->userMapper = UserMapper::getInstance();
        parent::loadDependencies($dependencies);
    }


    /**
     * @param TriggerBundle $bundle
     * @return void
     */
    public function bundleTriggers($bundle): void
    {
        switch ($bundle->getMethod()) {
            case Mapper::METHOD_CREATE:
                /** @var SocialStream_Post_Abstract $post (I THINK!) */
                $post = $bundle->getEvent()->getObject();

                if ($post->getParentId()) {
                    // is a child post: ie. comment
                    $bundle->attach(self::TRIGGER_REPLIED, self::IMMEDIATELY);
                } else {
                    // has no parent: ie. fresh post
                    $bundle->attach(self::TRIGGER_POSTED, self::IMMEDIATELY);
                }
                break;
        }
    }

    /* ASYNC METHODS */

    /**
     * @param string $roleType
     * @param SocialStream_Post_Abstract $post
     * @param User $author
     * @param array $diff
     * @return Envelope
     */
    protected function notifyPosted(string $roleType, SocialStream_Post_Abstract $post, User $author, ?array $diff = null): Envelope
    {
        $envelope = new Envelope();
        $bob = new Builder();

        /** @var Folder $cat */
        try {
            $cat = $this->catMapper->findById($post->getHomepageId());
        } catch (Mapper\Exception $e) {
            $this->log->warning("Failed to find folder with id: {$post->getHomepageId()} {$e->getMessage()}");
            return $envelope;
        }

        if ($post instanceof \SocialStream_Post_Url) {
            $posted = 'posted a link';
        } elseif ($post instanceof \SocialStream_Post_Video) {
            $posted = 'posted a video';
        } elseif ($post instanceof \SocialStream_Post_Image) {
            $posted = 'posted an image';
        } else {
            $posted = 'posted';
        }

        $href = '/homepage/' . $cat->getId() . '#social';

        $bob->setTitle(":author $posted in the social stream in :cat")
            ->setParams(
                [
                    'author' => new UserParam($author),
                    'cat'    => new CategoryParam($cat)
                ]
            )
            ->setIcon($this->getUserIcon($author))
            ->setHref($href)
            ->addTopic("sstream.{$post->getPostId()}.posted")
            ->setSeverity(Logger::NOTICE)
            ->setBody(Scrub::html2text($post->getBody()))
            ->setNamespace($this->getSlug())
            ->addFilter('roletype', [$roleType]);

        $envelope->broadcastToCategory($cat, UserAccess::ACCESS_READ, $bob->build());

        return $envelope;
    }


    /**
     * @param SocialStream_Post_Abstract $post
     * @param string $roleType
     * @param User $author
     * @param array $diff
     * @return Envelope
     */
    protected function notifyReplied(string $roleType, SocialStream_Post_Abstract $post, User $author, ?array $diff = null): Envelope
    {

        $envelope = new Envelope();
        $bob = new Builder();

        /** @var Folder $cat */
        try {
            $cat = $this->catMapper->findById($post->getHomepageId());
        } catch (Mapper\Exception $e) {
            $this->log->warning("Failed to find folder with id: {$post->getHomepageId()} {$e->getMessage()}");
            return $envelope;
        }

        // list of user's to notify: parent ("user replied to your post") and siblings ("user ALSO replied to")
        /** @var SocialStream_Post_Abstract $parent */
        try {
            $parent = $this->mapper->findById($post->getParentId());
        } catch (Mapper\Exception $e) {
            // this is a top-level post, so "replies" aren't relevant
            return $envelope;
        }

        $href = '/homepage/' . $cat->getId();
        $icon = $this->getUserIcon($author);

        // base config
        $bob->setHref($href)
            ->setIcon($icon)
            ->addTopic("sstream.{$parent->getPostId()}.replied")
            ->setTitle(':author replied to a post in :cat')
            ->setSeverity(Logger::NOTICE)
            ->setNamespace($this->getSlug())
            ->setBody(Scrub::html2text($post->getBody()))
            ->setParams(
                [
                    'author' => new UserParam($author),
                    'cat'    => new CategoryParam($cat)
                ]
            )
            ->addFilter('roletype', [$roleType]);

        $envelope->broadcastToCategory($cat, UserAccess::ACCESS_READ, $bob->build());

        return $envelope;
    }


    /**
     * @param SocialStream_Post_Abstract $post
     * @param User $author
     * @param array $diff
     * @return Envelope
     */
    public function notifyStudentPosted(SocialStream_Post_Abstract $post, User $author, ?array $diff = null): Envelope
    {
        return $this->notifyPosted(Role::STUDENT, $post, $author, $diff);
    }


    /**
     * @param SocialStream_Post_Abstract $post
     * @param User $author
     * @param array $diff
     * @return Envelope
     */
    public function notifyStudentReplied(SocialStream_Post_Abstract $post, User $author, ?array $diff = null): Envelope
    {
        return $this->notifyReplied(Role::STUDENT, $post, $author, $diff);
    }


    /**
     * @param SocialStream_Post_Abstract $post
     * @param User $author
     * @param array $diff
     * @return Envelope
     */
    public function notifyStaffPosted(SocialStream_Post_Abstract $post, User $author, ?array $diff = null): Envelope
    {
        return $this->notifyPosted(Role::STAFF, $post, $author, $diff);
    }


    /**
     * @param SocialStream_Post_Abstract $post
     * @param User $author
     * @param array $diff
     * @return Envelope
     */
    public function notifyStaffReplied(SocialStream_Post_Abstract $post, User $author, ?array $diff = null): Envelope
    {
        return $this->notifyReplied(Role::STAFF, $post, $author, $diff);
    }


    /**
     * @param SocialStream_Post_Abstract $post
     * @param User $author
     * @param array $diff
     * @return Envelope
     */
    public function notifyParentPosted(SocialStream_Post_Abstract $post, User $author, ?array $diff = null): Envelope
    {
        return $this->notifyPosted(Role::PARENT, $post, $author, $diff);
    }


    /**
     * @param SocialStream_Post_Abstract $post
     * @param User $author
     * @param array $diff
     * @return Envelope
     */
    public function notifyParentReplied(SocialStream_Post_Abstract $post, User $author, ?array $diff = null): Envelope
    {
        return $this->notifyReplied(Role::PARENT, $post, $author, $diff);
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

        /** @var Collection|SocialStream_Post_Abstract[] $posts */
        $posts = $this->mapper->findForFeed($context, $limit, $offset);

        foreach ($posts as $post) {
            $owner = $post->getOwner();
            $c = $c->merge(new Collection($this->notifyPosted($user->getRole()->getType(), $post, $owner)->getAll($post->getCreatedAt()->getUnixTimestamp())));
        }
        return $c;
    }
}
