<?php
/**
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Discussion\Comment;

use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Parameter\Param;
use Monolog\Logger;
use Schoolbox\Comms\News\Discussion\Context;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Exception;
use Schoolbox\MapperFactory;
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


class NewsNotifier extends AbstractModuleNotifier
{
    public const TRIGGER_COMMENTED = 'commented';
    public const TRIGGER_FOLLOWED = 'followed';

    private const PATTERN_COMMENTED_TITLE = ':user commented on your article, :title';
    private const PATTERN_ARTICLE_HREF = '/news/%d';

    /** @var UserMapper */
    private $userMapper;

    /** @var ArticleMapper */
    private $articleMapper;

    /** @var ThreadMapper */
    private $threadMapper;

    /**
     * @inheritdoc
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public static function getSetters(\Aura\Di\ContainerInterface $di): array
    {
        /** @var MapperFactory $mf */
        $mf = $di->get('mapperFactory');

        return [
            'setMapper' => $mf->getLazy(CommentMapper::class),
            'setUserMapper'        => $mf->getLazy(UserMapper::class),
            'setThreadMapper' => $mf->getLazy(ThreadMapper::class),
            'setArticleMapper' => $mf->getLazy(ArticleMapper::class),
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
        if ($crudMethod === \Schoolbox\Mapper::METHOD_DELETE) {
            // comment is deleted
            $bundle->attach(self::TRIGGER_DELETED, self::IMMEDIATELY);
        }

        /**
         * Created
         */
        if ($crudMethod === \Schoolbox\Mapper::METHOD_CREATE) {
            $bundle->attach(self::TRIGGER_COMMENTED, self::IMMEDIATELY);
            $bundle->attach(self::TRIGGER_FOLLOWED, self::IMMEDIATELY);
        }

        return;
    }


    /**
     * Retracts the deleted comment
     * @param Model $comment
     * @return Envelope
     */
    public function notifySystemDeleted(Comment $comment): Envelope
    {
        $envelope = new Envelope();
        return $this->retractEventObject($envelope, $comment);
    }


    /**
     * Notifies staff that someone has commented on his article
     * @param Model $comment
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
     * @param Model $comment
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
     * @param Model $comment
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
     * @param Model $comment
     * @param User $effector
     * @return Envelope
     */
    private function notifyCommented(string $roleType, Comment $comment, User $effector): Envelope
    {
        // get the item that the comment has created on
        $thread = $comment->getThread();
        $context = new Context($this->threadMapper, $this->articleMapper);

        /** @var GenericCollection $articles */
        $articles = $context->findAssociations($thread);
        $envelope = new Envelope();

        if ($articles->count() === 0) {
            return $envelope;
        }

        /** @var Article $article */
        $article = $articles->first();
        try {
            $author = $this->userMapper->findById($article->getAuthor()->getId());
        } catch (Exception $e) {
            // Failed to find the user
            return $envelope;
        }

        if ($author->getRole()->getType() !== $roleType) {
            return $envelope;
        }

        $bob = new Builder();

        $bob->setTitle(self::PATTERN_COMMENTED_TITLE)
            ->setNamespace($this->getSlug())
            ->setIcon("/portrait.php?userId={$effector->getExternalId()}&size={$this->iconSize}")
            ->setSeverity(Logger::INFO)
            ->setHref(sprintf(self::PATTERN_ARTICLE_HREF, $article->getId()))
            ->setParams([
                'user' => new UserParam($effector, Param::STACKING_REPLACE),
                'title' => new Param($article->getTitle(), sprintf(self::PATTERN_ARTICLE_HREF, $article->getId()),
                    Param::STACKING_APPEND),
            ])
            ->setIcon("/portrait.php?userId={$effector->getExternalId()}&size={$this->iconSize}")
            ->addTopic("{$this->getSlug()}.{$comment->getId()}." . self::TRIGGER_COMMENTED);

        $envelope->targetToUser($author->getId(), $bob->build());
        return $envelope;
    }

    /**
     * @param Model $comment
     * @param User $effector
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyStaffFollowed(Comment $comment, User $effector, ?array $diff = []): Envelope
    {
        return $this->notifyFollowed(Role::STAFF, $comment, $effector);
    }

    /**
     * @param Model $comment
     * @param User $effector
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyStudentFollowed(Comment $comment, User $effector, ?array $diff = []): Envelope
    {
        return $this->notifyFollowed(Role::STUDENT, $comment, $effector);
    }

    /**
     * @param Model $comment
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
     * @param Model $comment
     * @param User $effector
     * @return Envelope
     */
    private function notifyFollowed(string $roleType, Comment $comment, User $effector): Envelope
    {
        // TODO: implement!!
        $envelope = new Envelope();
        return $envelope;
    }

}