<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Comms\News;

use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Instance\Instance;
use Alaress\Async\Notification\Parameter\Param;
use Monolog\Logger;
use Schoolbox\Comms\News\Audience\IUserResolver;
use Schoolbox\Comms\News\Audience\RelationshipFilterer;
use Schoolbox\Comms\News\Audience\Segment\StrategyFactory;
use Schoolbox\Comms\News\Audience\UserResolver;
use Schoolbox\Comms\News\Model as Article;
use Schoolbox\Comms\News\Moderation\IUserResolver as IModeratorResolver;
use Schoolbox\Comms\News\Moderation\UserResolver as ModeratorResolver;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\MapperFactory;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Notification\Parameter\UserParam;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundleInterface;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * @method Article buildPrevious($current, ?array $diff, int $previousKey = 1)
 */
class Notifier extends AbstractModuleNotifier
{
    public const TRIGGER_POSTED    = 'posted';
    public const TRIGGER_MODERATED = 'moderated';
    public const TRIGGER_SUBMITTED = 'submitted';
    public const TRIGGER_EXPIRED   = 'expired';

    // These patterns need to be consts to ensure consistency between methods (revocation relies on these strings matching)

    private const PATTERN_POSTED_TITLE    = ':user posted :title';
    private const PATTERN_SUBMITTED_TITLE = ':user submitted an article, :title';
    private const PATTERN_POSTED_HREF     = '/news/%d?ref=' . Statistics\Mapper::REFERRER_EMAIL;
    private const PATTERN_ARTICLE_HREF    = '/news/%d';

    /** @var IUserResolver */
    private $audienceResolver;

    /** @var iModeratorResolver */
    private $moderatorResolver;

    /** @var UserMapper */
    private $userMapper;

    /**
     * @inheritdoc
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public static function getSetters(\Aura\Di\ContainerInterface $di): array
    {
        /** @var MapperFactory $mf */
        $mf = $di->get('mapperFactory');

        $relationshipFilterer = $di->lazyNew(RelationshipFilterer::class, [
            'db'               => $di->lazyGet('databaseInternal'),
            'userMapper'       => $mf->getLazy(UserMapper::class),
            'externalUser'     => $di->lazyGet('externalUser'),
            'folderMapper'     => $mf->getLazy(\Schoolbox\Resource\Folder\Mapper::class),
            'externalClass'    => $di->lazyGet('externalClass'),
            'userAccessMapper' => $mf->getLazy(UserAccessMapper::class),
        ]);

        $userResolver = $di->lazyNew(UserResolver::class, [
            'validNullEntities'    => StrategyFactory::getValidNullEntities(),
            'db'                   => $di->lazyGet('databaseInternal'),
            'externalClass'        => $di->lazyGet('externalClass'),
            'relationshipFilterer' => $relationshipFilterer,
            'audienceMapper'       => $mf->getLazy(Audience\Mapper::class),
            'folderMapper'         => $mf->getLazy(\Schoolbox\Resource\Folder\Mapper::class),
        ]);

        $moderatorResolver = $di->lazyNew(ModeratorResolver::class, [
            'audienceMapper'   => $mf->getLazy(Audience\Mapper::class),
            'userAccessMapper' => $mf->getLazy(UserAccessMapper::class),
            'db'               => $di->lazyGet('databaseInternal'),
        ]);

        return [
            'setMapper'            => $mf->getLazy(Mapper::class),
            'setUserMapper'        => $mf->getLazy(UserMapper::class),
            'setAudienceResolver'  => $userResolver,
            'setModeratorResolver' => $moderatorResolver,
        ];
    }


    /**
     * @inheritdoc
     */
    public function __construct($configLoader)
    {
        parent::__construct($configLoader);
    }


    /**
     * @inheritdoc
     */
    protected function getSlug(): string
    {
        return NSMapper::NS_NEWS;
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
     * Setter for UserMapper
     * @param UserMapper $userMapper
     */
    public function setUserMapper(UserMapper $userMapper)
    {
        $this->userMapper = $userMapper;
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
            // article deleted; revoke that bad boy
            $bundle->attach(self::TRIGGER_DELETED, self::IMMEDIATELY);
            $bundle->detach(self::TRIGGER_POSTED);
            $bundle->detach(self::TRIGGER_EXPIRED);
            return;
        }

        /** @var Article $article */
        $article  = $bundle->getEvent()->getObject();
        $diff     = $bundle->getEvent()->getDiff();
        $previous = $this->buildPrevious($article, $diff);

        /**
         * Draft or no-change
         */
        if ($diff === null ||                                                     // no change at all
            (\count($diff) === 1 && \array_key_exists('updatedAt', $diff)) ||            // no real changes
            ($article->isDraft() && (!$previous || $previous->isDraft()))) {      // creation or updating of draft
            return;
        }

        // Invalid state transition. Article has been created in a non-draft state
        if (!$previous && !$article->isDraft()) {
            // Don't even attempt to process as it will likely cause fatal NPEs
            $this->log->error(sprintf("Invalid transition. Article: %d has been created in a '%s' state.", $article->getId(), $article->getStatus()));
            return;
        }

        /**
         * Rejected or Unpublished
         */
        if ($article->isRejected() && $previous->isRejected()) {
            // likely to be auto-saved
            return;
        } elseif ($article->isRejected() && $previous->canBeRejected()) {
            // just been rejected or unpublished from a valid status
            $bundle->attach(self::TRIGGER_MODERATED, self::IMMEDIATELY);
            $bundle->detach(self::TRIGGER_POSTED);
            $bundle->detach(self::TRIGGER_EXPIRED);
            return;
        }

        /**
         * Awaiting for moderation
         */
        if ($article->isAwaitingModeration() && $previous->isAwaitingModeration()) {
            // likely to be auto-saved
            return;
        } elseif ($article->isAwaitingModeration() && $previous->canBeSubmitted()) {
            // just been submitted from a valid status
            $bundle->attach(self::TRIGGER_SUBMITTED, self::IMMEDIATELY);
            return;
        }

        /**
         * Approved (Awaiting Publication / Published / Expired)
         */
        if ($article->isApproved()) {

            $publishAt = $article->getPublishAt();
            $expiresAt = $article->getExpiresAt();

            // TODO: If audience has been changed after being published

            // The article has just been approved from a valid status.
            if ($previous->canBeApproved()) {
                $bundle->attach(self::TRIGGER_MODERATED, self::IMMEDIATELY);
                if (!$article->isExpired()) {
                    $bundle->attach(self::TRIGGER_POSTED, $publishAt->getUnixTimeStamp());
                    $bundle->attach(self::TRIGGER_EXPIRED, $expiresAt->getUnixTimeStamp());
                }
            } else {
                // publish date changed
                if (isset($diff['publishAt'])) {
                    // past -> past .. noop
                    // past -> future .. retract + re-attach
                    // future -> future .. detach + re-attach
                    // future -> past .. detach + re-attach
                    if ($previous->getPublishAt()->isFuture()) {
                        // this will replace existing scheduled trigger
                        $bundle->attach(self::TRIGGER_POSTED, $publishAt->getUnixTimeStamp());
                    } elseif ($publishAt->isFuture()) {
                        $bundle->attach(self::TRIGGER_DELETED, self::IMMEDIATELY);
                        $bundle->attach(self::TRIGGER_POSTED, $publishAt->getUnixTimeStamp());
                    }
                }
                // expiry date changed
                if (isset($diff['expiresAt'])) {
                    // past -> past .. noop
                    // past -> future .. detach + re-attach
                    // future -> future .. detach + re-attach
                    // future -> past .. detach
                    if ($expiresAt->isFuture()) {
                        // this will replace existing scheduled trigger
                        $bundle->attach(self::TRIGGER_EXPIRED, $expiresAt->getUnixTimeStamp());
                    } elseif ($previous->getExpiresAt()->isFuture()) {
                        $bundle->detach(self::TRIGGER_EXPIRED);
                    }
                }
            }
            return;
        }

    }

    /**
     * Retracts a deleted article.
     *
     * @param Article $article
     * @return Envelope
     */
    public function notifySystemDeleted(Article $article): Envelope
    {
        $envelope = new Envelope();
        return $this->retractPosted($envelope, $article);
    }

    /**
     * Retracts a given article.
     *
     * @param Envelope $envelope
     * @param Article  $article
     * @return Envelope
     */
    private function retractPosted(Envelope $envelope, Article $article): Envelope
    {
        return $this->retractEventObject($envelope, $article);
    }

    /**
     * Retracts an expired article.
     *
     * @param Article $article
     * @return Envelope
     */
    public function notifySystemExpired(Article $article): Envelope
    {
        $envelope = new Envelope();
        return $this->retractPosted($envelope, $article);
    }

    /**
     * Notify a moderator that an article needs moderation
     *
     * @param string  $roleType
     * @param Article $article
     * @param User    $effector
     * @return Envelope
     */
    private function notifySubmitted(string $roleType, Article $article, User $effector): Envelope
    {
        $envelope     = new Envelope();
        $recipientIds = $this->moderatorResolver->findModeratorsForArticle($article);

        if (empty($recipientIds)) {
            // no recipients? do nothing
            return $envelope;
        }

        try {
            $recipients = $this->userMapper->findByColumnList(['id' => $recipientIds, 'g.group_type' => $roleType]);
        } catch (Exception $e) {
            $this->log->warning('Failed to find users with given ids:' . implode(',', $recipientIds) . $e->getMessage());
            return $envelope;
        }

        $bob = new Builder();

        $bob->setTitle(self::PATTERN_SUBMITTED_TITLE)
            ->setNamespace($this->getSlug())
            ->setIcon("/portrait.php?userId={$effector->getExternalId()}&size={$this->iconSize}")
            ->setSeverity(Logger::INFO)
            ->setHref(sprintf(self::PATTERN_ARTICLE_HREF, $article->getId()))
            ->setParams([
                'user'  => new UserParam($effector, Param::STACKING_REPLACE),
                'title' => new Param($article->getTitle(), sprintf(self::PATTERN_ARTICLE_HREF, $article->getId()), Param::STACKING_APPEND),
            ])
            ->setIcon("/portrait.php?userId={$effector->getExternalId()}&size={$this->iconSize}")
            ->addTopic("news.{$article->getId()}." . self::TRIGGER_SUBMITTED);

        foreach ($recipients as $recipient) {
            $envelope->targetToUser($recipient->getId(), $bob->build());
        }

        return $envelope;
    }

    /**
     * Notify a staff member that an article needs moderation
     *
     * @param Article $article
     * @param User    $effector
     * @param array   $diff
     * @return Envelope
     */
    public function notifyStaffSubmitted(Article $article, User $effector, array $diff = []): Envelope
    {
        return $this->notifySubmitted(Role::STAFF, $article, $effector);
    }


    /**
     * Notify a student member that an article needs moderation
     *
     * @param Article $article
     * @param User    $effector
     * @param array   $diff
     * @return Envelope
     */
    public function notifyStudentSubmitted(Article $article, User $effector, array $diff = []): Envelope
    {
        return $this->notifySubmitted(Role::STUDENT, $article, $effector);
    }


    /**
     * Notify a parent that an article needs moderation
     *
     * @param Article $article
     * @param User    $effector
     * @param array   $diff
     * @return Envelope
     */
    public function notifyParentSubmitted(Article $article, User $effector, array $diff = []): Envelope
    {
        return $this->notifySubmitted(Role::PARENT, $article, $effector);
    }


    /**
     * Notify author that the article is moderated (rejected, unpublished or approved)
     *
     * @param string  $roleType
     * @param Article $article
     * @param User    $effector
     * @param array   $diff
     * @return Envelope
     */
    private function notifyModerated(string $roleType, Article $article, User $effector, array $diff = []): Envelope
    {
        $envelope = new Envelope();
        $bob      = new Builder();
        // hydrate to get role
        try {
            $author = $this->userMapper->findById($article->getAuthor()->getId());
        } catch (Exception $e) {
            $this->log->warning('Failed to find author using userId: ' . $e->getMessage());
            return $envelope;
        }

        // if the author does not have the targeted role type, return.
        if ($author->getRole()->getType() !== $roleType) {
            return $envelope;
        }

        $previous = $this->buildPrevious($article, $diff);

        if ($article->isApproved()) {
            $action = 'approved';
        } elseif ($previous->isPublished()) {
            $action = 'unpublished';
        } else {
            $action = 'rejected';
        }
        $bob->setTitle(':user ' . $action . ' your article, :title')
            ->setNamespace($this->getSlug())
            ->setIcon("/portrait.php?userId={$effector->getExternalId()}&size={$this->iconSize}")
            ->setSeverity(Logger::INFO)
            ->setHref(sprintf(self::PATTERN_ARTICLE_HREF, $article->getId()))
            ->setParams([
                'user'  => new UserParam($effector, Param::STACKING_REPLACE),
                'title' => new Param($article->getTitle(), sprintf(self::PATTERN_ARTICLE_HREF, $article->getId()), Param::STACKING_REPLACE),
            ])
            ->addTopic("news.{$article->getId()}." . self::TRIGGER_MODERATED);

        if (!$article->isApproved()) {
            // magical meta to delete messages related to object first.
            $bob->setRetractFirst(true);
        }

        $envelope->targetToUser($author, $bob->build());

        return $envelope;
    }

    /**
     * Notify staff that an article the staff authored has been approved/rejected
     *
     * @param Article $article
     * @param User    $effector
     * @param array   $diff
     * @return Envelope
     */
    public function notifyStaffModerated(Article $article, User $effector, array $diff = []): Envelope
    {
        return $this->notifyModerated(Role::STAFF, $article, $effector, $diff);
    }

    /**
     * Notify student that an article the student authored has been approved/rejected
     *
     * @param Article $article
     * @param User    $effector
     * @param array   $diff
     * @return Envelope
     */
    public function notifyStudentModerated(Article $article, User $effector, array $diff = []): Envelope
    {
        return $this->notifyModerated(Role::STUDENT, $article, $effector, $diff);
    }

    /**
     * Notify parent that an article the parent authored has been approved/rejected
     *
     * @param Article $article
     * @param User    $effector
     * @param array   $diff
     * @return Envelope
     */
    public function notifyParentModerated(Article $article, User $effector, array $diff = []): Envelope
    {
        return $this->notifyModerated(Role::PARENT, $article, $effector, $diff);
    }

    /**
     * Notify an article's audience in given role type that news has been posted
     *
     * @param string  $roleType
     * @param Article $article
     * @param User    $effector
     * @return Envelope
     */
    private function notifyPosted(string $roleType, Article $article, User $effector): Envelope
    {
        $envelope = new Envelope();

        if (!$article->isPublished()) {
            $this->log->warning("Tried to send posted notification for an article {$article->getId()} which is not published.");
            return $envelope;
        }

        $recipientIds = $this->audienceResolver->findUsersForArticle($article, true);

        if (empty($recipientIds)) {
            // no recipients? do nothing
            return $envelope;
        }

        try {
            $recipients = $this->userMapper->findByColumnList(['id' => $recipientIds, 'g.group_type' => $roleType]);
        } catch (Exception $e) {
            $this->log->warning('Failed to find users with given ids:' . implode(',', $recipientIds) . $e->getMessage());
            return $envelope;
        }

        $articleId = $article->getId();

        try {
            // self hydration -_-
            /** @var Article $article */
            $article = $this->mapper->findById($article->getId());

        } catch (Deleted $e) {
            $this->log->error('Tried to send an article notification, but it has been deleted; ID is ' . $articleId, [$e]);
            return $envelope;

        } catch (Exception $e) {
            $this->log->error($e->getMessage(), [$e]);
            return $envelope;
        }

        $author = $article->getAuthor();

        if (is_object($article->getFeatureImage())) {
            $hash  = $article->getFeatureImage()->getHash();
            $icon  = "/storage/image.php?hash=$hash&size=square64";
            $thumb = "/storage/image.php?hash=$hash&size=square200";

        } else {
            $icon  = "/portrait.php?userId={$author->getExternalId()}&size={$this->iconSize}";
            $thumb = '/images/emails/spacer.gif';
        }

        $href = sprintf(self::PATTERN_POSTED_HREF, $articleId);

        $heading = $article->getTitle();

        // use the more recent of the two available dates
        $activeDate = $article->getPublishAt() < $article->getCreatedAt()
            ? $article->getCreatedAt()
            : $article->getPublishAt();

        if (!$activeDate) {
            // oh noes
            $this->log->warning('Attempted to process news article with no created_at OR published_at; ID is ' . $articleId);
            return $envelope;
        }

        $shortDate  = $activeDate->getDateOnly();
        $authorName = $author->formatName();


        $bob = new Builder();

        $notifBody = strlen($article->getBlurb()) > 0 ? $article->getBlurb() : $article->getBody();

        $instance = $bob
            ->setTitle(self::PATTERN_POSTED_TITLE)
            ->setParams([
                'user'  => new UserParam($author, Param::STACKING_REPLACE),
                'title' => new Param($heading, sprintf(self::PATTERN_ARTICLE_HREF, $articleId), Param::STACKING_APPEND),
            ])
            ->setIcon($icon)
            ->setHref($href)
            ->setNamespace($this->getSlug())
            ->setSeverity(Logger::INFO)
            ->addTopic("news.$articleId." . self::TRIGGER_POSTED)
            // body and byline are optional, but recommended since they render out in emails
            ->setMeta([
                'title'  => $heading,
                'author' => $authorName,
                'date'   => $activeDate->getLong(),
                'thumb'  => $thumb,
                'blurb'  => $article->getBlurb(),
            ])
            ->setBody($notifBody)
            ->setByline("$shortDate by $authorName")
            // timestamp and filters are optional
            ->setTimestamp($activeDate->getUnixTimeStamp())
            // and then you build
            ->addFilter('roleType', [$roleType])
            ->build();

        foreach ($recipients as $recipient) {
            // to avoid author getting the notification
            if ($recipient->getId() === $author->getId()) {
                continue;
            }

            // Is this an important article?
            // If so, bypass standard delivery mechanisms
            $envelope->targetToUser(
                $recipient->getId(),
                $instance,
                $article->getSticky() ? Instance::DELIVERY_ALERT : Instance::DELIVERY_NOTIFY
            );
        }

        return $envelope;
    }


    /**
     * Notify students that news has been posted.
     *
     * @param Article $article
     * @param User    $user
     * @param array   $diff
     * @return Envelope
     */
    public function notifyStudentPosted(Article $article, User $user, array $diff = []): Envelope
    {
        return $this->notifyPosted(Role::STUDENT, $article, $user);
    }

    /**
     * Notify staff that news has been posted.
     *
     * @param Article $article
     * @param User    $user
     * @param array   $diff
     * @return Envelope
     */
    public function notifyStaffPosted(Article $article, User $user, array $diff = []): Envelope
    {
        return $this->notifyPosted(Role::STAFF, $article, $user);
    }

    /**
     * Notify parents that news has been posted.
     *
     * @param Article $article
     * @param User    $user
     * @param array   $diff
     * @return Envelope
     */
    public function notifyParentPosted(Article $article, User $user, array $diff = []): Envelope
    {
        return $this->notifyPosted(Role::PARENT, $article, $user);
    }

}
