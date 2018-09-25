<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News;

use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Parameter\Param;
use Monolog\Logger;
use Schoolbox\Comms\News\Audience\BusinessRules;
use Schoolbox\Comms\News\Audience\IUserResolver;
use Schoolbox\Comms\News\Audience\RelationshipFilterer;
use Schoolbox\Comms\News\Audience\UserResolver;
use Schoolbox\Comms\News\Moderation\UserResolver as ModeratorResolver;
use Schoolbox\Comms\News\Moderation\IUserResolver as IModeratorResolver;
use Schoolbox\IConfigLoader;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\MapperFactory;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Notification\Parameter\UserParam;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Comms\News\Model as Article;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundleInterface;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;


class Notifier extends AbstractModuleNotifier
{
    public const TRIGGER_POSTED    = 'posted';
    public const TRIGGER_MODERATED  = 'moderated';
    public const TRIGGER_SUBMITTED = 'submitted';
    public const TRIGGER_EXPIRED   = 'expired';

    // These patterns need to be consts to ensure consistency between methods (revocation relies on these strings matching)

    private const PATTERN_POSTED_TITLE = ':user posted :title';
    private const PATTERN_POSTED_HREF  = '/comms/news/%d?ref=email';
    private const PATTERN_ARTICLE_HREF  = '/comms/news/%d';

    /** @var IUserResolver */
    private $audienceResolver;

    /** @var iModeratorResolver */
    private $moderatorResolver;

    /** @var UserMapper */
    private $userMapper;

    /**
     * @param \Aura\Di\ContainerInterface $di
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @return array
     */
    public static function getSetters(\Aura\Di\ContainerInterface $di): array
    {
        /** @var MapperFactory $mf */
        $mf = $di->get('mapperFactory');

        $relationshipFilterer = $di->lazyNew(RelationshipFilterer::class, [
            'db'                   => $di->lazyGet('databaseInternal'),
            'userMapper'           => $mf->getLazy(UserMapper::class),
            'externalUser'         => $di->lazyGet('externalUser'),
            'folderMapper'         => $mf->getLazy(\Schoolbox\Resource\Folder\Mapper::class),
            'externalClass'        => $di->lazyGet('externalClass'),
            'userAccessMapper'     => $mf->getLazy(UserAccessMapper::class)
        ]);

        $userResolver = $di->lazyNew(UserResolver::class, [
            'businessRules'        => $di->lazyNew(BusinessRules::class),
            'db'                   => $di->lazyGet('databaseInternal'),
            'externalClass'        => $di->lazyGet('externalClass'),
            'relationshipFilterer' => $relationshipFilterer,
            'audienceMapper'       => $mf->getLazy(Audience\Mapper::class),
        ]);

        $moderatorResolver = $di->lazyNew(ModeratorResolver::class, [
            'audienceMapper'       => $mf->getLazy(Audience\Mapper::class),
            'userAccessMapper'     => $mf->getLazy(UserAccessMapper::class),
            'db'                   => $di->lazyGet('databaseInternal')
        ]);

        return [
            'setMapper'            => $mf->getLazy(Mapper::class),
            'setUserMapper'        => $mf->getLazy(UserMapper::class),
            'setAudienceResolver'  => $userResolver,
            'setModeratorResolver' => $moderatorResolver
        ];
    }


    /**
     * @param IConfigLoader $configLoader
     */
    public function __construct($configLoader)
    {
        parent::__construct($configLoader);
    }


    /**
     * @return string
     */
    protected function getSlug()
    {
        return NSMapper::NS_NEWS;
    }


    /**
     * @param IUserResolver $audienceResolver
     */
    public function setAudienceResolver(IUserResolver $audienceResolver)
    {
        $this->audienceResolver = $audienceResolver;
    }

    /**
     * @param IModeratorResolver $moderatorResolver
     */
    public function setModeratorResolver(IModeratorResolver $moderatorResolver)
    {
        $this->moderatorResolver = $moderatorResolver;
    }

    /**
     * @param UserMapper $userMapper
     */
    public function setUserMapper($userMapper)
    {
        $this->userMapper = $userMapper;
    }

    /**
     * Objectives of this method: if the request
     *  - rejects the article, trigger REJECTED immediately
     *  - accepts the article, schedule POSTED appropriately
     *
     * @param TriggerBundleInterface $bundle
     * @return void
     */
    public function bundleTriggers($bundle)
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
        $article = $bundle->getEvent()->getObject();
        $diff = $bundle->getEvent()->getDiff();

        /** @var Article $previous */
        $previous = $this->buildPrevious($article, $diff);

        /**
         * Updated
         * Current Status: not yet submitted or any other without content change
         * Diff: none or updatedAt only for articles which have been submitted
         */
        if (!$previous || ($article->isDraft() && $previous->isDraft())
            || ($diff === null || (count($diff) === 1 && key_exists('updatedAt', $diff)))) {
            return;
        }

        /**
         * Rejected
         * Current Status: rejected
         * Diff: rejectedAt
         */
        if ($article->isRejected() && $previous->canBeRejected() && !$previous->isPublished()) {
            $bundle->attach(self::TRIGGER_MODERATED, self::IMMEDIATELY);
            $bundle->detach(self::TRIGGER_POSTED);
            $bundle->detach(self::TRIGGER_EXPIRED);
            return;
        }

        /**
         * Unpublished
         * Current Status: draft
         */
        if ($article->isRejected() && $previous->isPublished()) {
            $bundle->attach(self::TRIGGER_MODERATED, self::IMMEDIATELY);
            $bundle->detach(self::TRIGGER_POSTED);
            $bundle->detach(self::TRIGGER_EXPIRED);
            return;
        }

        /**
         * Submitted for moderation
         * Current Status: submitted
         * Diff: submittedAt
         */
        if ($article->isAwaitingModeration() && $previous->canBeSubmitted()) {
            $bundle->attach(self::TRIGGER_SUBMITTED, self::IMMEDIATELY);
            return;
        }

        /**
         * Published or Ready to be published
         * Current Status: awaiting publication or published
         * Diff: acceptedAt
         */
        if ($article->isAwaitingPublication() || $article->isPublished() || $article->isExpired()) {

            $publishAt = $article->getPublishAt();
            $expiresAt = $article->getExpiresAt();

            // TODO: If audience has been changed after being published

            // The article has just been approved.
            if (isset($diff['acceptedAt'])) {
                $bundle->attach(self::TRIGGER_MODERATED, self::IMMEDIATELY);
                if (!$article->isExpired()) {
                    $bundle->attach(self::TRIGGER_POSTED, $publishAt->getUnixTimeStamp());
                    $bundle->attach(self::TRIGGER_EXPIRED, $expiresAt->getUnixTimeStamp());
                }
            } else {
                // publish date changed
                if (isset($diff['publishAt'])) {

                    // past -> past .. noop
                    // past -> future .. retract
                    // future -> future .. detach
                    // future -> past .. detach
                    if ($previous->getPublishAt()->isPast() && !$publishAt->isPast()) {
                        $bundle->attach(self::TRIGGER_DELETED, self::IMMEDIATELY);
                        $bundle->attach(self::TRIGGER_POSTED, $publishAt->getUnixTimeStamp());
                    } else if (!$previous->getPublishAt()->isPast()) {
                        $bundle->detach(self::TRIGGER_POSTED);
                        $bundle->attach(self::TRIGGER_POSTED, $publishAt->getUnixTimeStamp());
                    }
                };
                // expiry date changed
                if (isset($diff['expiresAt'])) {
                    // past -> past .. noop
                    // past -> future
                    // future -> future
                    // future -> past
                    if (!$expiresAt->isPast() || !$previous->getExpiresAt()->isPast()) {
                        $bundle->detach(self::TRIGGER_EXPIRED);
                        $bundle->attach(self::TRIGGER_EXPIRED, $expiresAt->getUnixTimeStamp());
                    }
                };
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
        $envelope  = new Envelope();
        $bob       = new Builder(false);
        $articleId = $article->getId();
        $bob->setTitle(self::PATTERN_POSTED_TITLE)
            ->setHref(sprintf(self::PATTERN_POSTED_HREF, $articleId))
            ->addTopic("news.$articleId." . self::TRIGGER_POSTED);

        // Empty params for completeness?
        $bob->setParams([
            'user'  => new Param(''),
            'title' => new Param(''),
        ]);

        // Only broadcast to superusers as there's no system channel
        $envelope->retractAllLike($bob->build());
        return $envelope;
    }

    /**
     * Retracts an expired article.
     *
     * @param Article $article
     * @return Envelope
     */
    public function notifySystemExpired(Article $article): Envelope
    {
        return $this->notifySystemDeleted($article);
    }

    /**
     * Retracts a rejected/unpublished article.
     *
     * @param Article $article
     * @return Envelope
     */
    public function notifySystemModerated(Article $article): Envelope
    {
        // Retract when rejected or unpublished.
        if ($article->isDraft() || $article->isRejected()) {
            return $this->notifySystemDeleted($article);
        }
        return new Envelope();
    }

    /**
     * Notify a moderator that an article needs moderation
     *
     * @param string $roleType
     * @param Model $article
     * @param User  $effector
     * @param array $diff
     * @return Envelope
     */
    private function notifySubmitted($roleType, $article, $effector, $diff = [])
    {
        $envelope  = new Envelope();
        $recipientIds = $this->moderatorResolver->findModeratorsForArticle($article);

        if (empty($recipientIds)) {
            // no recipients? do nothing
            return $envelope;
        }

        $recipients = $this->userMapper->findByColumn('id', $recipientIds);

        $bob = new Builder();

        $bob->setTitle(':user submitted an article, :title')
            ->setNamespace($this->getSlug())
            ->setIcon("/portrait.php?userId={$effector->getExternalId()}&size={$this->iconSize}")
            ->setSeverity(Logger::INFO)
            ->setHref(sprintf(self::PATTERN_ARTICLE_HREF, $article->getId()))
            ->setParams([
                'user'  => new UserParam($effector, Param::STACKING_REPLACE),
                'title' => new Param($article->getTitle(), sprintf(self::PATTERN_ARTICLE_HREF, $article->getId()),
                    Param::STACKING_APPEND),
            ])
            ->setIcon("/portrait.php?userId={$effector->getExternalId()}&size={$this->iconSize}")
            ->addTopic("news.{$article->getId()}." . self::TRIGGER_SUBMITTED)
        ;

        foreach ($recipients as $recipient) {
            if ($roleType !== $recipient->getRole()->getType()) {
                continue;
            }
            $envelope->targetToUser($recipient->getId(), $bob->build());
        }

        return $envelope;
    }

    /**
     * Notify a staff member that an article needs moderation
     *
     * @param Model $article
     * @param User  $effector
     * @param array $diff
     * @return Envelope
     */
    public function notifyStaffSubmitted($article, $effector, $diff = [])
    {
        return $this->notifySubmitted(Role::STAFF, $article, $effector, $diff);
    }


    /**
     * Notify a student member that an article needs moderation
     *
     * @param Model $article
     * @param User  $effector
     * @param array $diff
     * @return Envelope
     */
    public function notifyStudentSubmitted($article, $effector, $diff = [])
    {
        return $this->notifySubmitted(Role::STUDENT, $article, $effector, $diff);
    }


    /**
     * Notify a parent that an article needs moderation
     *
     * @param Model $article
     * @param User  $effector
     * @param array $diff
     * @return Envelope
     */
    public function notifyParentSubmitted($article, $effector, $diff = [])
    {
        return $this->notifySubmitted(Role::PARENT, $article, $effector, $diff);
    }


    /**
     * Notify author that the article is moderated (rejected, unpublished or approved)
     * @param string  $roleType
     * @param Article $article
     * @param User    $effector
     * @param array $diff
     * @return Envelope
     */
    private function notifyModerated($roleType, $article, $effector, $diff = [])
    {
        $envelope  = new Envelope();
        $bob = new Builder();
        /** @var Article $previous */
        $previous = $this->buildPrevious($article, $diff);

        if ($article->isRejected() && $previous->isPublished()) {
            $action = 'unpublished';
        } else if ($article->isRejected()) {
            $action = 'rejected';
        } else {
            $action = 'approved';
        }
        $bob->setTitle(':user ' . $action . ' your article, :title')
            ->setNamespace($this->getSlug())
            ->setIcon("/portrait.php?userId={$effector->getExternalId()}&size={$this->iconSize}")
            ->setSeverity(Logger::INFO)
            ->setHref(sprintf(self::PATTERN_ARTICLE_HREF, $article->getId()))
            ->setParams([
                'user' => new UserParam($effector, Param::STACKING_REPLACE),
                'title' => new Param($article->getTitle(), sprintf(self::PATTERN_ARTICLE_HREF, $article->getId()),
                    Param::STACKING_APPEND)
            ])
            ->addTopic("news.{$article->getId()}." . self::TRIGGER_MODERATED);

        $envelope->targetToUser($article->getAuthor()->getId(), $bob->build());

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
    public function notifyStaffModerated($article, $effector, $diff = [])
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
    public function notifyStudentModerated($article, $effector, $diff = [])
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
    public function notifyParentModerated($article, $effector, $diff = [])
    {
        return $this->notifyModerated(Role::PARENT, $article, $effector, $diff);
    }


    /**
     * Notify a group type that news has been posted
     *
     * @param string  $roleType
     * @param Article $article
     * @param User    $effector
     * @return Envelope
     */
    private function notifyPosted($roleType, $article, $effector)
    {
        $envelope = new Envelope();

        $recipientIds = $this->audienceResolver->findUsersForArticle($article);

        if (empty($recipientIds)) {
            // no recipients? do nothing
            return $envelope;
        }

        $recipients = $this->userMapper->findByColumn('id', $recipientIds);
        $articleId = $article->getId();

        try {
            // self hydration -_-
            /** @var Article $article */
            $article = $this->mapper->findById($article->getId());

        } catch (Deleted $e) {
            $this->log->error('Tried to send an article notification, but it has been deleted; ID is ' . $articleId, [ $e ]);
            return $envelope;

        } catch (Exception $e) {
            $this->log->error($e->getMessage(), [ $e ]);
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

        $instance = $bob->setTitle(self::PATTERN_POSTED_TITLE)
            ->setParams(array(
                'user'  => new UserParam($author, Param::STACKING_REPLACE),
                'title' => new Param($heading, sprintf(self::PATTERN_ARTICLE_HREF, $articleId), Param::STACKING_APPEND),
            ))
            ->setIcon($icon)
            ->setHref($href)
            ->setNamespace($this->getSlug())
            ->setSeverity(Logger::INFO)
            ->addTopic("news.$articleId." . self::TRIGGER_POSTED)
            // body and byline are optional, but recommended since they render out in emails
            ->setMeta(array(
                'title'  => $heading,
                'author' => $authorName,
                'date'   => $activeDate->getLong(),
                'thumb'  => $thumb,
                'blurb'  => $article->getBlurb(),
            ))
            ->setBody($notifBody)
            ->setByline("$shortDate by $authorName")
            // timestamp and filters are optional
            ->setTimestamp($activeDate->getUnixTimeStamp())
            // and then you build
            ->addFilter('roleType', [ $roleType ])
            ->build();

        foreach ($recipients as $recipient) {
            if ($recipient->getId() === intval($author->getId()) || $recipient->getRole()->getType() !== $roleType) {
                continue;
            }
            $envelope->targetToUser($recipient->getId(), $instance);
        }

        return $envelope;
    }


    /**
     * Notify students that news has been posted.
     *
     * @param Article $article
     * @param User $user
     * @param array $diff
     * @return Envelope
     */
    public function notifyStudentPosted($article, $user, $diff = array())
    {
        return $this->notifyPosted(Role::STUDENT, $article, $user);
    }

    /**
     * Notify staff that news has been posted.
     *
     * @param Article $article
     * @param User $user
     * @param array $diff
     * @return Envelope
     */
    public function notifyStaffPosted($article, $user, $diff = array())
    {
        return $this->notifyPosted(Role::STAFF, $article, $user);
    }

    /**
     * Notify parents that news has been posted.
     *
     * @param Article $article
     * @param User $user
     * @param array $diff
     * @return Envelope
     */
    public function notifyParentPosted($article, $user, $diff = array())
    {
        return $this->notifyPosted(Role::PARENT, $article, $user);
    }

}