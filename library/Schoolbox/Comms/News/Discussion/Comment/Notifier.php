<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Discussion\Comment;

use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Parameter\Param;
use Aura\Di\ContainerInterface;
use Monolog\Logger;
use Schoolbox\Comms\News\Audience\IUserResolver;
use Schoolbox\Comms\News\Audience\RelationshipFilterer;
use Schoolbox\Comms\News\Audience\Segment\StrategyFactory;
use Schoolbox\Comms\News\Audience\UserResolver;
use Schoolbox\Comms\News\Audience\Mapper as AudienceMapper;
use Schoolbox\Comms\News\Discussion\ThreadLocator;
use Schoolbox\Comms\News\Moderation\IUserResolver as IModeratorResolver;
use Schoolbox\Comms\News\Moderation\UserResolver as ModeratorResolver;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper;
use Schoolbox\MapperFactory;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Parameter\UserParam;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundleInterface;
use Schoolbox\Discussion\Comment\Model as Comment;
use Schoolbox\Discussion\Comment\Mapper as CommentMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Comms\News\Mapper as ArticleMapper;
use Schoolbox\Comms\News\Model as Article;
use Schoolbox\Discussion\Thread\Mapper as ThreadMapper;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;

class Notifier extends AbstractModuleNotifier
{
    public const TRIGGER_COMMENTED = 'commented';
    public const TRIGGER_FOLLOWED = 'followed';

    private const PATTERN_COMMENTED_TITLE = ':user commented on your article :title';
    private const PATTERN_ARTICLE_HREF = '/news/%d';

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
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public static function getSetters(ContainerInterface $di): array
    {
        /** @var MapperFactory $mf */
        $mf = $di->get('mapperFactory');

        $relationshipFilterer = $di->lazyNew(RelationshipFilterer::class, [
            'db'                   => $di->lazyGet('databaseInternal'),
            'userMapper'           => $mf->getLazy(UserMapper::class),
            'externalUser'         => $di->lazyGet('externalUser'),
            'folderMapper'         => $mf->getLazy(FolderMapper::class),
            'externalClass'        => $di->lazyGet('externalClass'),
            'userAccessMapper'     => $mf->getLazy(UserAccessMapper::class)
        ]);

        $userResolver = $di->lazyNew(UserResolver::class, [
            'validNullEntities'    => StrategyFactory::getValidNullEntities(),
            'db'                   => $di->lazyGet('databaseInternal'),
            'externalClass'        => $di->lazyGet('externalClass'),
            'relationshipFilterer' => $relationshipFilterer,
            'audienceMapper'       => $mf->getLazy(AudienceMapper::class),
            'folderMapper'         => $mf->getLazy(FolderMapper::class),
        ]);

        $moderatorResolver = $di->lazyNew(ModeratorResolver::class, [
            'audienceMapper'       => $mf->getLazy(AudienceMapper::class),
            'userAccessMapper'     => $mf->getLazy(UserAccessMapper::class),
            'db'                   => $di->lazyGet('databaseInternal')
        ]);

        return [
            'setMapper' => $mf->getLazy(CommentMapper::class),
            'setUserMapper' => $mf->getLazy(UserMapper::class),
            'setThreadMapper' => $mf->getLazy(ThreadMapper::class),
            'setArticleMapper' => $mf->getLazy(ArticleMapper::class),
            'setAudienceResolver'  => $userResolver,
            'setModeratorResolver' => $moderatorResolver
        ];
    }


    /**
     * @inheritdoc
     */
    public function __construct($configLoader)
    {
        parent::__construct($configLoader, ['mapper' => CommentMapper::getInstance()]);

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
     * @param Comment $comment
     * @param User $effector
     * @param array $diff
     * @return Envelope
     */
    public function notifyStaffCommented(Comment $comment, User $effector, ?array $diff = []): Envelope
    {
        return $this->notifyCommented(Role::STAFF, $comment, $effector);

    }


    /**
     * Notifies student that someone has commented on his article
     * @param Comment $comment
     * @param User $effector
     * @param array $diff
     * @return Envelope
     */
    public function notifyStudentCommented(Comment $comment, User $effector, ?array $diff = []): Envelope
    {
        return $this->notifyCommented(Role::STUDENT, $comment, $effector);
    }


    /**
     * Notifies parent that someone has commented on his article
     * @param Comment $comment
     * @param User $effector
     * @param array $diff
     * @return Envelope
     */
    public function notifyParentCommented(Comment $comment, User $effector, ?array $diff = []): Envelope
    {
        return $this->notifyCommented(Role::PARENT, $comment, $effector);
    }


    /**
     * Notifies the author that someone commented on the article
     * @param string $roleType
     * @param Comment $comment
     * @param User $effector
     * @return Envelope
     */
    private function notifyCommented(string $roleType, Comment $comment, User $effector): Envelope
    {
        // get the item that the comment has created on
        $thread = $comment->getThread();
        $threadLocator = new ThreadLocator($this->threadMapper, $this->articleMapper);

        /** @var GenericCollection $articles */
        $articles = $threadLocator->findAssociations($thread);
        $envelope = new Envelope();

        if ($articles->count() === 0) {
            return $envelope;
        }

        /** @var Article $article */
        $article = $articles->first();

        // filter out recipients with audience (this is more to apply mute settings)
        $validUserIds = \array_merge(
            // adding author to apply mute setting
            $this->audienceResolver->findUsersForArticle($article, true, true),
            // permission for folder is handled above. getting global moderators only.
            $this->moderatorResolver->findModeratorsForArticle($article, false)
        );
        if (!\in_array($article->getAuthor()->getId(), $validUserIds)) {
            return $envelope;
        }

        $bob = new Builder();

        $bob->setTitle(self::PATTERN_COMMENTED_TITLE)
            ->setNamespace($this->getSlug())
            ->setIcon("/portrait?id={$effector->getId()}&size={$this->iconSize}")
            ->setSeverity(Logger::INFO)
            ->setFilter(['roletype' => $roleType])
            ->setHref(sprintf(self::PATTERN_ARTICLE_HREF, $article->getId()))
            ->setParams([
                'user' => new UserParam($effector, Param::STACKING_APPEND),
                'title' => new Param($article->getTitle(), sprintf(self::PATTERN_ARTICLE_HREF, $article->getId()),
                    Param::STACKING_APPEND),
            ])
            ->addTopic("{$this->getSlug()}.{$comment->getId()}." . self::TRIGGER_COMMENTED);

        $envelope->targetToUser($article->getAuthor()->getId(), $bob->build());
        return $envelope;
    }


    /**
     * @param Comment $comment
     * @param User $effector
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyStaffFollowed(Comment $comment, User $effector, ?array $diff = []): Envelope
    {
        return $this->notifyFollowed(Role::STAFF, $comment, $effector);
    }


    /**
     * @param Comment $comment
     * @param User $effector
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyStudentFollowed(Comment $comment, User $effector, ?array $diff = []): Envelope
    {
        return $this->notifyFollowed(Role::STUDENT, $comment, $effector);
    }


    /**
     * @param Comment $comment
     * @param User $effector
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyParentFollowed(Comment $comment, User $effector, ?array $diff = []): Envelope
    {
        return $this->notifyFollowed(Role::PARENT, $comment, $effector);
    }


    /**
     * Notifies users who have made comments on the same article
     * @param string $roleType
     * @param Comment $comment
     * @param User $effector
     * @return Envelope
     */
    private function notifyFollowed(string $roleType, Comment $comment, User $effector): Envelope
    {
        $envelope = new Envelope();
        $threadLocator = new ThreadLocator($this->threadMapper, $this->articleMapper);

        // get the item that the comment has created on
        $thread = $comment->getThread();
        /** @var GenericCollection $articles */
        $articles = $threadLocator->findAssociations($thread);

        if ($articles->count() === 0) {
            return $envelope;
        }

        /** @var Article $article */
        $article = $articles->first();
        $commentAuthors = $this->mapper->findByColumn('discussion_thread_id', $thread->getId())->getPropertyValues('author');
        $recipientIds = [];
        /** @var User $commentAuthor */
        // send to users who have ever made comments and are not the author of the article
        foreach ($commentAuthors as $commentAuthor) {
            if (!\in_array($commentAuthor->getId(), $recipientIds)
                && ($commentAuthor->getId() !== $article->getAuthor()->getId())) {
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
            ->setIcon("/portrait?id={$effector->getId()}&size={$this->iconSize}")
            ->setSeverity(Logger::INFO)
            ->setFilter(['roletype' => $roleType])
            ->setHref(sprintf(self::PATTERN_ARTICLE_HREF, $article->getId()))
            ->setParams([
                'user' => new UserParam($effector, Param::STACKING_APPEND),
                'title' => new Param($article->getTitle(), sprintf(self::PATTERN_ARTICLE_HREF, $article->getId()),
                    Param::STACKING_APPEND),
            ])
            ->addTopic("{$this->getSlug()}.{$comment->getId()}." . self::TRIGGER_FOLLOWED);

        foreach ($recipientIds as $recipient) {
            $envelope->targetToUser($recipient, $bob->build());
        }

        return $envelope;
    }

}