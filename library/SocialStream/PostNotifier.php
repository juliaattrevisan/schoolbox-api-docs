<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace SocialStream;


use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Instance\Instance;
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
use Schoolbox\Resource\Category;
use Schoolbox\Resource\CategoryMapper;
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

    /** @var CategoryMapper */
    protected $catMapper;

    /** @var UserAccessMapper */
    protected $userAccessMapper;

    /** @var UserMapper */
    protected $userMapper;

    /**
     * @return string
     */
    protected function getSlug()
    {
        return NSMapper::NS_SSTREAM;
    }


    /**
     * Instantiate all your dependencies within yourself.
     *
     * @param mixed $dependencies
     * @return void
     */
    public function loadDependencies($dependencies = null)
    {
        $this->catMapper = CategoryMapper::getInstance();
        $this->userAccessMapper = UserAccessMapper::getInstance();
        $this->userMapper = UserMapper::getInstance();
        parent::loadDependencies($dependencies);
    }


    /**
     * @param TriggerBundle $bundle
     * @return void
     */
    public function bundleTriggers($bundle)
    {
        switch ($bundle->getMethod()) {
            case Mapper::METHOD_CREATE:
                /** @var SocialStream_Post_Abstract $post (I THINK!) */
                $post = $bundle->getEvent()->getObject();

                // is a child post: ie. comment
                if ($post->getParentId()) {
                    $bundle->attach(self::TRIGGER_REPLIED, self::IMMEDIATELY);
                } // has no parent: ie. fresh post
                else {
                    $bundle->attach(self::TRIGGER_POSTED, self::IMMEDIATELY);
                }
                break;
        }
    }


    /**
     * @param SocialStream_Post_Abstract $post
     * @param User $author
     * @return string
     */
    protected function getPostIcon($post, $author)
    {
        if ($post instanceof \SocialStream_Post_Url) {
            return '/socialstream/image.php?size=square32&id=' . $post->getPostId();
        } elseif ($post instanceof \SocialStream_Post_Video) {
            return '/portrait.php?size=square32&userId=' . $author->getExternalId();
        } elseif ($post instanceof \SocialStream_Post_Image) {
            return '/socialstream/image.php?size=square32&id=' . $post->getPostId();
        }
        // string
        return '/portrait.php?size=square32&userId=' . $author->getExternalId();
    }


    /* ASYNC METHODS */

    /**
     * @param string $roleType
     * @param SocialStream_Post_Abstract $post
     * @param User $author
     * @param array $diff
     * @return Envelope
     */
    protected function notifyPosted($roleType, $post, $author, $diff = null)
    {
        $envelope = new Envelope();
        $bob = new Builder();

        /** @var Category $cat */
        $cat = $this->catMapper->findById($post->getHomepageId());

        if ($post instanceof \SocialStream_Post_Url) {
            $text = ':author posted a link in :cat';
        } elseif ($post instanceof \SocialStream_Post_Video) {
            $text = ':author posted a video in :cat';
        } elseif ($post instanceof \SocialStream_Post_Image) {
            $text = ':author posted an image in :cat';
        } // string
        else {
            $text = ':author posted in :cat';
        }

        $href = '/homepage/' . $cat->getId();

        $bob->setTitle($text)
            ->setParams(
                array(
                    'author' => new UserParam($author),
                    'cat'    => new CategoryParam($cat)
                )
            )
            ->setIcon($this->getPostIcon($post, $author))
            ->setHref($href)
            ->addTopic("sstream.{$post->getPostId()}.posted")
            ->setSeverity(Logger::NOTICE)
            ->setBody(Scrub::html2text($post->getBody()))
            ->setNamespace($this->getSlug())
            ->addFilter('roletype', $roleType);

        $envelope->broadcastToCategory($cat, UserAccess::ACCESS_READ, $bob->build());

        return $envelope;
    }


    /**
     * @param SocialStream_Post_Abstract $post
     * @param User $author
     * @param array $diff
     * @return Envelope
     */
    protected function notifyReplied($post, $author, $diff = null)
    {

        /** @var PostMapper $mapper */
        $mapper = $this->mapper;
        $envelope = new Envelope();
        $bob = new Builder();

        /** @var Category $cat */
        $cat = $this->catMapper->findById($post->getHomepageId());

        // list of user's to notify: parent ("user replied to your post") and siblings ("user ALSO replied to")
        /** @var SocialStream_Post_Abstract $parent */
        try {
            $parent = $this->mapper->findById($post->getParentId());
        } catch (Mapper\Exception $e) {
            // this is a top-level post, so "replies" aren't relevant
            return $envelope;
        }

        $parentAuthorId = $parent->getOwnerId();

        // compensate for inconsistent return types -_-
        if ($parentAuthorId instanceof User) {
            $parentAuthorId = $parentAuthorId->getId();
        }

        /** @var Collection|SocialStream_Post_Abstract[] $siblings */
        $siblings = $mapper->findChildren($parent);

        // so, this MIGHT return objects, or MIGHT return ids.
        $siblingAuthorsOrIds = $siblings->getColumnValues('ownerId') ? : array();

        $href = '/homepage/' . $cat->getId();
        $icon = $this->getPostIcon($post, $author);

        // base config
        $bob->setHref($href)
            ->setIcon($icon)
            ->addTopic("sstream.{$post->getPostId()}.replied")
            ->setSeverity(Logger::NOTICE)
            ->setNamespace($this->getSlug())
            ->setBody(Scrub::html2text($post->getBody()))
            ->setParams(
                array(
                    'author' => new UserParam($author),
                    'cat'    => new CategoryParam($cat)
                )
            );

        // parent post config
        $envelope->targetToUser($parentAuthorId, $bob->setTitle(':author replied to your post in :cat')->build());

        // sibling post config
        $bob->setTitle(':author also replied to a post in :cat');

        foreach ($siblingAuthorsOrIds as $siblingOrId) {
            $siblingId = $siblingOrId instanceof User ? $siblingOrId->getId() : $siblingOrId;
            $envelope->targetToUser($siblingId, $bob->build());
        }

        return $envelope;
    }


    /**
     * @param SocialStream_Post_Abstract $post
     * @param User $author
     * @param array $diff
     * @return Envelope
     */
    public function notifyStudentPosted($post, $author, $diff = null)
    {
        return $this->notifyPosted(Role::STUDENT, $post, $author, $diff);
    }


    /**
     * @param SocialStream_Post_Abstract $post
     * @param User $author
     * @param array $diff
     * @return Envelope
     */
    public function notifyStudentReplied($post, $author, $diff = null)
    {
        return $this->notifyReplied($post, $author, $diff);
    }


    /**
     * @param SocialStream_Post_Abstract $post
     * @param User $author
     * @param array $diff
     * @return Envelope
     */
    public function notifyStaffPosted($post, $author, $diff = null)
    {
        return $this->notifyPosted(Role::STAFF, $post, $author, $diff);
    }


    /**
     * @param SocialStream_Post_Abstract $post
     * @param User $author
     * @param array $diff
     * @return Envelope
     */
    public function notifyStaffReplied($post, $author, $diff = null)
    {
        return $this->notifyReplied($post, $author, $diff);
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

        // be sure the target method exists
        if(!$this->mapper instanceof FeedableMapperInterface) {
            return $c;
        }

        /** @var Collection|SocialStream_Post_Abstract[] $posts */
        $posts = $this->mapper->findForFeed($context, $limit, $offset);

        foreach($posts as $post) {
            $c->merge(new Collection($this->notifyPosted(Role::STUDENT, $post, $this->userMapper->findById($post->getOwnerId()))->getAll($post->getCreatedAt()->getUnixTimestamp())));
//            $c->merge(new Collection($this->notifyReplied($post, $this->userMapper->findById($post->getOwnerId()))->getAll($post->getCreatedAt()->getUnixTimestamp())));
        }
        return $c;
    }

} 