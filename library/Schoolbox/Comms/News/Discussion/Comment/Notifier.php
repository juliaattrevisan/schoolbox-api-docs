<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Discussion\Comment;

use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Parameter\Param;
use Alaress\Database\Predicate;
use InvalidArgumentException;
use Monolog\Logger;
use PDO;
use Psr\Container\ContainerInterface;
use Schoolbox\Comms\News;
use Schoolbox\Comms\News\Audience;
use Schoolbox\Comms\News\Audience\IUserResolver;
use Schoolbox\Comms\News\Audience\Mapper as AudienceMapper;
use Schoolbox\Comms\News\Discussion\ThreadLocator;
use Schoolbox\Comms\News\Mapper as ArticleMapper;
use Schoolbox\Comms\News\Model as Article;
use Schoolbox\Comms\News\Moderation\IUserResolver as IModeratorResolver;
use Schoolbox\Comms\News\Moderation\UserResolver as ModeratorResolver;
use Schoolbox\Discussion\Comment\Mapper as CommentMapper;
use Schoolbox\Discussion\Comment\Model as Comment;
use Schoolbox\Discussion\Thread\Mapper as ThreadMapper;
use Schoolbox\GenericCollection;
use Schoolbox\IConfigLoader;
use Schoolbox\Mapper;
use Schoolbox\MapperFactory;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Message\RoutePermission;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Notification\Parameter\UserParam;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundleInterface;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Folder\NNIAN;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;
use UnderflowException;
use UnexpectedValueException;

use function array_map;
use function array_unique;
use function in_array;
use function sprintf;

class Notifier extends AbstractModuleNotifier
{
    public const TRIGGER_COMMENTED = 'commented';
    public const TRIGGER_FOLLOWED  = 'followed';

    private const PATTERN_COMMENTED_TITLE = ':user commented on your article :title';
    private const PATTERN_ARTICLE_HREF    = '/news/%d';

    private const PATTERN_FOLLOWED_TITLE = ':user commented on the article :title';

    /** @var UserMapper */
    private $userMapper;

    /** @var ArticleMapper */
    private $articleMapper;

    /** @var ThreadMapper */
    private $threadMapper;

    /** @var IUserResolver */
    private $audienceResolver;

    /** @var iModeratorResolver */
    private $moderatorResolver;

    /**
     * @inheritdoc
     */
    public static function getSetters(ContainerInterface $di): array
    {
        /** @var MapperFactory $mf */
        $mf = $di->get('mapperFactory');

        $userResolver = $di->get('commsNewsUserResolver');

        $moderatorResolver = new ModeratorResolver(
            $mf->get(AudienceMapper::class),
            $mf->get(UserAccessMapper::class),
            $di->get('databaseInternal'),
        );

        return [
            'setMapper'            => $mf->get(CommentMapper::class),
            'setUserMapper'        => $mf->get(UserMapper::class),
            'setThreadMapper'      => $mf->get(ThreadMapper::class),
            'setArticleMapper'     => $mf->get(ArticleMapper::class),
            'setAudienceResolver'  => $userResolver,
            'setModeratorResolver' => $moderatorResolver,
        ];
    }


    /**
     * @inheritdoc
     */
    public function __construct(IConfigLoader $configLoader, array $dependencies = [])
    {
        parent::__construct($configLoader, ['mapper' => CommentMapper::getInstance()] + $dependencies);
    }


    /**
     * @inheritdoc
     */
    protected function getSlug(): string
    {
        return NSMapper::NS_NEWS_DISCUSSION_COMMENT;
    }


    /**
     * Setter for UserMapper
     * @param UserMapper $userMapper
     */
    public function setUserMapper(UserMapper $userMapper)
    {
        $this->userMapper = $userMapper;
    }


    /**
     * Setter for ThreadMapper
     * @param ThreadMapper $threadMapper
     */
    public function setThreadMapper(ThreadMapper $threadMapper)
    {
        $this->threadMapper = $threadMapper;
    }


    /**
     * Setter for ArticleMapper
     * @param ArticleMapper $articleMapper
     */
    public function setArticleMapper(ArticleMapper $articleMapper)
    {
        $this->articleMapper = $articleMapper;
    }


    /**
     * Setter for Audience\UserResolver
     * @param IUserResolver $audienceResolver
     */
    public function setAudienceResolver(IUserResolver $audienceResolver)
    {
        $this->audienceResolver = $audienceResolver;
    }


    /**
     * Setter for Moderation\UserResolver
     * @param IModeratorResolver $moderatorResolver
     */
    public function setModeratorResolver(IModeratorResolver $moderatorResolver)
    {
        $this->moderatorResolver = $moderatorResolver;
    }


    /**
     * Attaches and detaches triggers properly depending on the article status and transition
     *
     * @param TriggerBundleInterface $bundle
     * @return void
     */
    public function bundleTriggers($bundle): void
    {

        $crudMethod = $bundle->getMethod();

        /**
         * Deleted
         */
        if ($crudMethod === Mapper::METHOD_DELETE) {
            // comment is deleted
            $bundle->attach(self::TRIGGER_DELETED, self::IMMEDIATELY);
        }

        /**
         * Created
         */
        if ($crudMethod === Mapper::METHOD_CREATE) {
            $bundle->attach(self::TRIGGER_COMMENTED, self::IMMEDIATELY);
            $bundle->attach(self::TRIGGER_FOLLOWED, self::IMMEDIATELY);
        }

        return;
    }


    /**
     * Retracts the deleted comment
     * @param Comment $comment
     * @return Envelope
     */
    public function notifySystemDeleted(Comment $comment): Envelope
    {
        $envelope = new Envelope();
        return $this->retractEventObject($envelope, $comment);
    }


    /**
     * Notifies staff that someone has commented on his article
     * @param Comment    $comment
     * @param User       $effector
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyStaffCommented(Comment $comment, User $effector, ?array $diff = []): Envelope
    {
        return $this->notifyCommented(Role::STAFF, $comment, $effector);
    }


    /**
     * Notifies student that someone has commented on his article
     * @param Comment    $comment
     * @param User       $effector
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyStudentCommented(Comment $comment, User $effector, ?array $diff = []): Envelope
    {
        return $this->notifyCommented(Role::STUDENT, $comment, $effector);
    }


    /**
     * Notifies parent that someone has commented on his article
     * @param Comment    $comment
     * @param User       $effector
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyParentCommented(Comment $comment, User $effector, ?array $diff = []): Envelope
    {
        return $this->notifyCommented(Role::PARENT, $comment, $effector);
    }


    /**
     * Notifies the author that someone commented on the article
     *
     * @param string  $roleType
     * @param Comment $comment
     * @param User    $effector
     * @return Envelope
     * @throws InvalidArgumentException
     * @throws Mapper\DefinitionException
     * @throws Mapper\Exception
     * @throws Mapper\Exception\Deleted
     * @throws Mapper\Exception\InsufficientHydrationException
     * @throws Mapper\Exception\NotFound
     * @throws Predicate\InvalidColumnException
     * @throws UnderflowException
     * @throws UnexpectedValueException
     * @throws \OutOfBoundsException
     */
    private function notifyCommented(string $roleType, Comment $comment, User $effector): Envelope
    {
        // get the item that the comment has created on
        $thread        = $comment->getThread();
        $threadLocator = new ThreadLocator($this->threadMapper, $this->articleMapper);

        /** @var GenericCollection $articles */
        $articles = $threadLocator->findAssociations($thread);
        $envelope = new Envelope();

        if ($articles->count() === 0) {
            return $envelope;
        }

        /** @var Article $article */
        $article = $articles->first();

        // Has user muted all audience segments, and they are not a SU or news moderator?
        /** @var User $author */
        $author = $article->getAuthor();
        if (
            !$author->isSuperuser() &&
            $this->hasAuthorMutedAllAudienceSegments($article) &&
            !in_array($author->getId(), $this->moderatorResolver->getGlobalNewsModerators(), false)
        ) {
            return $envelope;
        }

        $bob = new Builder();

        $bob->setTitle(self::PATTERN_COMMENTED_TITLE)
            ->setNamespace($this->getSlug())
            ->setIcon($this->getUserIcon($effector))
            ->setSeverity(Logger::INFO)
            ->setFilter(['roletype' => $roleType])
            ->setHref(sprintf(self::PATTERN_ARTICLE_HREF, $article->getId()))
            ->setParams([
                'user'  => new UserParam($effector, Param::STACKING_APPEND),
                'title' => new Param(
                    $article->getTitle(),
                    sprintf(self::PATTERN_ARTICLE_HREF, $article->getId()),
                    Param::STACKING_APPEND
                ),
            ])
            ->addTopic("{$this->getSlug()}.{$comment->getId()}." . self::TRIGGER_COMMENTED)
            ->setBody($comment->getContent());

        $envelope->targetToUser($article->getAuthor()->getId(), $bob->build());
        return $envelope;
    }


    /**
     * @param Comment    $comment
     * @param User       $effector
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyStaffFollowed(Comment $comment, User $effector, ?array $diff = []): Envelope
    {
        return $this->notifyFollowed(Role::STAFF, $comment, $effector);
    }


    /**
     * @param Comment    $comment
     * @param User       $effector
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyStudentFollowed(Comment $comment, User $effector, ?array $diff = []): Envelope
    {
        return $this->notifyFollowed(Role::STUDENT, $comment, $effector);
    }


    /**
     * @param Comment    $comment
     * @param User       $effector
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyParentFollowed(Comment $comment, User $effector, ?array $diff = []): Envelope
    {
        return $this->notifyFollowed(Role::PARENT, $comment, $effector);
    }


    /**
     * Notifies users who have made comments on the same article
     * @param string  $roleType
     * @param Comment $comment
     * @param User    $effector
     * @return Envelope
     */
    private function notifyFollowed(string $roleType, Comment $comment, User $effector): Envelope
    {
        $envelope      = new Envelope();
        $threadLocator = new ThreadLocator($this->threadMapper, $this->articleMapper);

        // get the item that the comment has created on
        $thread = $comment->getThread();
        /** @var GenericCollection $articles */
        $articles = $threadLocator->findAssociations($thread);

        if ($articles->count() === 0) {
            return $envelope;
        }

        /** @var Article $article */
        $article        = $articles->first();
        $commentAuthors = $this->mapper->findByColumn('discussion_thread_id', $thread->getId())->getPropertyValues('author');
        $recipientIds   = [];
        /** @var User $commentAuthor */
        // send to users who have ever made comments and are not the author of the article
        foreach ($commentAuthors as $commentAuthor) {
            if (
                !\in_array($commentAuthor->getId(), $recipientIds)
                && ($commentAuthor->getId() !== $article->getAuthor()->getId())
            ) {
                $recipientIds[] = $commentAuthor->getId();
            }
        }
        if (empty($recipientIds)) {
            return $envelope;
        }

        // filter out recipients with audience
        $validUserIds = \array_merge(
            $this->audienceResolver->findUsersForArticle($article, true),
            $this->moderatorResolver->findModeratorsForArticle($article)
        );
        $recipientIds = \array_intersect($recipientIds, $validUserIds);
        if (empty($recipientIds)) {
            return $envelope;
        }

        $bob = new Builder();

        $bob->setTitle(self::PATTERN_FOLLOWED_TITLE)
            ->setNamespace($this->getSlug())
            ->setIcon($this->getUserIcon($effector))
            ->setSeverity(Logger::INFO)
            ->setFilter(['roletype' => $roleType])
            ->setHref(sprintf(self::PATTERN_ARTICLE_HREF, $article->getId()))
            ->setParams([
                'user'  => new UserParam($effector, Param::STACKING_APPEND),
                'title' => new Param(
                    $article->getTitle(),
                    sprintf(self::PATTERN_ARTICLE_HREF, $article->getId()),
                    Param::STACKING_APPEND
                ),
            ])
            ->addTopic("{$this->getSlug()}.{$comment->getId()}." . self::TRIGGER_FOLLOWED)
            ->setBody($comment->getContent());

        foreach ($recipientIds as $recipient) {
            $envelope->targetToUser($recipient, $bob->build());
        }

        return $envelope;
    }


    /**
     * Returns whether the author has muted all audience segments
     *
     * A composite segment is muted if all children of the segment are muted.
     *
     * @param Article $article
     * @return bool
     * @throws InvalidArgumentException
     * @throws Mapper\DefinitionException
     * @throws Mapper\Exception
     * @throws Mapper\Exception\Deleted
     * @throws Mapper\Exception\InsufficientHydrationException
     * @throws Mapper\Exception\NotFound
     * @throws Predicate\InvalidColumnException
     * @throws UnexpectedValueException
     * @throws UnderflowException
     */
    private function hasAuthorMutedAllAudienceSegments(News\Model $article): bool
    {
        // Article needs its entire audience loaded before this will work
        if (!$article->getAudience()) {
            $article->setAudience(AudienceMapper::getInstance()->findForArticles([$article->getId()]));
        }

        // If article has no audience, then treat this as the author not muting all audiences.
        if (!$article->getAudience()->count()) {
            return false;
        }

        // If any segment is not muted, the article is not muted. Try this before hitting the DB.
        foreach ($article->getAudience() as $segment) {
            if ($this->isMuted($segment, null) === false) {
                return false;
            }
        }

        // Get folders targeted by article (either as simple or part of composite)
        $folderIds = $this->getTargetedFolders($article);

        // Fetch folder mute settings from DB
        $db             = $this->mapper->getDatabase();
        $pb             = (new Predicate\Builder($db->getQuoter(), 'msg_reroute_override'))
            ->equals('user_id', $article->getAuthor()->getId())
            ->in('msg_route_key', array_map(static function (string $folderId) {
                return sprintf('service.%s.%s', UserAccess::SERVICE_HOMEPAGE, $folderId);
            }, $folderIds))
            ->equals('delivery', RoutePermission::MUTE);
        $mutedFolderIds = $db->query(
            "SELECT SUBSTRING_INDEX(msg_route_key, '.', -1) FROM msg_reroute_override WHERE {$pb->getWhereIntersect()}",
            $pb->getParams()
        )->fetchAll(PDO::FETCH_COLUMN);

        // Re-check, this time providing muted folder ids
        foreach ($article->getAudience() as $segment) {
            if ($this->isMuted($segment, $mutedFolderIds) === false) {
                return false;
            }
        }

        return true;
    }


    /**
     * Returns all folder ids referenced by the article's audience segments.
     *
     * If the folder inherits, it will be replaced with it's NNIAN folder's id
     *
     * @param Article $article
     * @return string[]
     * @throws Mapper\DefinitionException
     * @throws Mapper\Exception
     * @throws Mapper\Exception\Deleted
     * @throws Mapper\Exception\InsufficientHydrationException
     * @throws Mapper\Exception\NotFound
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     */
    private function getTargetedFolders(News\Model $article): array
    {
        $nnian            = NNIAN::getInstance(Folder\Mapper::getInstance());
        $folderIds        = [];
        $entityTypeFolder = News\Audience\Segment\Strategy\Folder::getEntityType();

        foreach ($article->getAudience() as $segment) {
            if ($segment->isSimple()) {
                if ($segment->getEntityType() === $entityTypeFolder && $segment->getEntityValue() !== null) {
                    $folderIds[] = $nnian->findNearestNonInheritingAncestralNode(
                        (new Folder\Model())->setId((int)$segment->getEntityValue())
                    )->getId();
                }
                continue;
            }
            foreach ($segment->getChildren() as $childSegment) {
                if ($childSegment->getEntityType() === $entityTypeFolder && $childSegment->getEntityValue() !== null) {
                    $folderIds[] = $nnian->findNearestNonInheritingAncestralNode(
                        (new Folder\Model())->setId((int)$childSegment->getEntityValue())
                    )->getId();
                }
            }
        }
        return array_unique(array_map(static function ($folderId): string {
            return (string)$folderId;
        }, $folderIds));
    }


    /**
     * Returns whether a (simple/complex) audience segment is muted.
     * * TRUE  => is muted
     * * FALSE => is not muted
     * * NULL  => may be muted, but not sure because $mutedFolderIds not supplied
     *
     * @param Audience\Model $segment
     * @param string[]       $mutedFolderIds
     * @return bool
     * @throws Mapper\Exception\InsufficientHydrationException
     */
    private function isMuted(Audience\Model $segment, ?array $mutedFolderIds): ?bool
    {
        // Simple
        if ($segment->isSimple()) {
            return $this->isLeafSegmentMuted($segment, $mutedFolderIds);
        }

        // Complex. If user has muted any child, then composite is also muted.
        $hasUnknownChild = false;
        foreach ($segment->getChildren() as $childSegment) {
            $childMuted = $this->isLeafSegmentMuted($childSegment, $mutedFolderIds);
            if ($childMuted === true) {
                return true;
            }
            if ($childMuted === null) {
                $hasUnknownChild = true;
            }
        }
        return $hasUnknownChild ? null : false;
    }


    /**
     * Returns whether a leaf segment is muted.
     * * TRUE  => is muted
     * * FALSE => is not muted
     * * NULL  => may be muted, but not sure because $mutedFolderIds not supplied
     *
     * @param Audience\Model $segment
     * @param array|null     $mutedFolderIds
     * @return bool
     */
    private function isLeafSegmentMuted(Audience\Model $segment, ?array $mutedFolderIds): ?bool
    {
        if ($segment->getEntityType() !== News\Audience\Segment\Strategy\Folder::getEntityType()) {
            // Not a folder segment, never muted.
            return false;
        }
        if ($segment->getEntityValue() === null) {
            // Cannot mute 'no folder'
            return false;
        }
        if ($mutedFolderIds === null) {
            // Is a folder segment, but we don't have muted values, so cannot provide result
            return null;
        }
        return in_array($segment->getEntityValue(), $mutedFolderIds, false);
    }
}
