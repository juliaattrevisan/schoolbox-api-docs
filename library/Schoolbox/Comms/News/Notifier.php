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


class Notifier extends AbstractModuleNotifier
{
    public const TRIGGER_POSTED    = 'posted';
    public const TRIGGER_REJECTED  = 'rejected';
    public const TRIGGER_SUBMITTED = 'submitted';

    // These patterns need to be consts to ensure consistency between methods (revocation relies on these strings matching)

    private const PATTERN_POSTED_TITLE = ':user posted :title';
    private const PATTERN_POSTED_HREF  = '/comms/news/%d';

    /** @var IUserResolver */
    private $audienceResolver;


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
            'userMapper'           => $mf->getLazy(\Schoolbox\UserMapper::class),
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
            'audienceMapper'       => $mf->getLazy(\Schoolbox\Comms\News\Audience\Mapper::class),
        ]);

        return [
            'setMapper'           => $mf->getLazy(Mapper::class),
            'setAudienceResolver' => $userResolver
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


        //// when the article is deleted ////

        if ($crudMethod === \Schoolbox\Mapper::METHOD_DELETE) {
            // article deleted; revoke that bad boy
            $bundle->attach(self::TRIGGER_DELETED, self::IMMEDIATELY);
            $bundle->detach(self::TRIGGER_POSTED);

            return;
        }


        //// when an article is rejected ////

        /** @var Article $article */
        $article = $bundle->getEvent()->getObject();

        $diff = $bundle->getEvent()->getDiff();

        if (isset($diff['rejectedAt'])) {
            $bundle->attach(self::TRIGGER_REJECTED, self::IMMEDIATELY);
        }


        //// when an article is not yet accepted ////

        $acceptedAt = $article->getAcceptedAt();

        if (!$acceptedAt || $acceptedAt->isNull()) {
            // article is not accepted; ensure no trigger exists
            $bundle->detach(self::TRIGGER_POSTED);

            if (isset($diff['acceptedAt'])) {
                // this action is removing the accepted-at: this is a REVOCATION
                $bundle->attach(self::TRIGGER_DELETED, self::IMMEDIATELY);
            }

            if (isset($diff['submittedAt'])) {
                // this action is submitting the article, send in the mods!
                $bundle->attach(self::TRIGGER_SUBMITTED, self::IMMEDIATELY);
            }

            // if the article is not accepted, no further checks are relevant
            return;
        }


        //// when there's no publication date ////

        $publishAt = $article->getPublishAt();

        if (!$publishAt || $publishAt->isNull()) {
            // if there is no publish date on the article, unhook any triggers that might exist
            $bundle->detach(self::TRIGGER_POSTED);

            // and that is all for now
            return;
        }


        // IF YOU GET HERE the article:
        //  - has been accepted, and
        //  - has a publish date (which could be in the past or future)

        if (isset($diff['acceptedAt'])) {
            // this action specifically accepted the article: set up the trigger
            $bundle->attach(self::TRIGGER_POSTED, $publishAt->getUnixTimeStamp());
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
            ->addTopic("news.$articleId.posted");

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
     * @param string  $roleType
     * @param Article $article
     * @param User    $effector
     * @param array $diff
     * @return Envelope
     */
    private function notifyRejected($roleType, $article, $effector, $diff = [])
    {
        $envelope  = new Envelope();

        $bob = new Builder();

        $bob->setTitle(':user rejected your article, :title')
            ->setHref(sprintf(self::PATTERN_POSTED_HREF, $article->getId()))
            ->setParams([
                'user' => new UserParam($effector),
                'title' => new Param($article->getTitle())
            ])
            ->setIcon("/portrait.php?userId={$effector->getExternalId()}&size={$this->iconSize}")
            ->addTopic("news.{$article->getId()}.rejected")
        ;

        $envelope->targetToUser($article->getAuthor()->getId(), $bob->build());

        return $envelope;
    }


    /**
     * @param Article $article
     * @param User    $effector
     * @param array   $diff
     * @return Envelope
     */
    public function notifyStaffRejected($article, $effector, $diff = [])
    {
        return $this->notifyRejected(Role::STAFF, $article, $effector, $diff);
    }


    /**
     * @param Article $article
     * @param User    $effector
     * @param array   $diff
     * @return Envelope
     */
    public function notifyStudentRejected($article, $effector, $diff = [])
    {
        return $this->notifyRejected(Role::STUDENT, $article, $effector, $diff);
    }


    /**
     * @param Article $article
     * @param User    $effector
     * @param array   $diff
     * @return Envelope
     */
    public function notifyParentRejected($article, $effector, $diff = [])
    {
        return $this->notifyRejected(Role::PARENT, $article, $effector, $diff);
    }


    /**
     * Notify a group type that news has been posted
     *
     * @param string  $roleType
     * @param Article $article
     * @param User    $user
     * @return Envelope
     */
    private function notifyPosted($roleType, $article, $user)
    {
        $envelope = new Envelope();

        $articleId = $article->getId();

        $recipientIds = $this->audienceResolver->findUsersForArticle($articleId);

        if (empty($recipientIds)) {
            // no recipients? do nothing
            return $envelope;
        }

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

        if (is_object($article->getFeatureImage())) {
            $hash  = $article->getFeatureImage()->getHash();
            $icon  = "/storage/image.php?hash=$hash&size=square64";
            $thumb = "/storage/image.php?hash=$hash&size=square200";

        } else {
            $icon  = "/portrait.php?userId={$user->getExternalId()}&size={$this->iconSize}";
            $thumb = '/images/emails/spacer.gif';
        }

        $href = sprintf(self::PATTERN_POSTED_HREF, $articleId);

        $heading = $article->getTitle();

        // TODO verify this

        // use the more recent of the two available dates
        $activeDate = $article->getPublishAt() < $article->getCreatedAt()
            ? $article->getCreatedAt()
            : $article->getPublishAt();

        if (!$activeDate) {
            // oh noes
            $this->log->warning('Attempted to process news article with no created_at OR published_at; ID is ' . $article->getId());
            return $envelope;
        }

        $shortDate  = $activeDate->getDateOnly();
        $authorName = $user->formatName();


        $bob = new Builder();

        $notifBody = strlen($article->getBlurb()) > 0 ? $article->getBlurb() : $article->getBody();

        $instance = $bob->setTitle(self::PATTERN_POSTED_TITLE)
            ->setParams(array(
                'user'  => new UserParam($user, Param::STACKING_REPLACE),
                'title' => new Param($heading, "/news/item/$articleId", Param::STACKING_APPEND),
            ))
            ->setIcon($icon)
            ->setHref($href)
            ->setNamespace($this->getSlug())
            ->setSeverity(Logger::INFO)
            ->addTopic("news.$articleId.posted")
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


        /** @var \Schoolbox\User $articleUser */
        foreach ($recipientIds as $recipientId) {
            $envelope->targetToUser($recipientId, $instance);
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