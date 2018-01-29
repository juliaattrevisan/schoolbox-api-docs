<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\News;


use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Parameter\Param;
use Monolog\Logger;
use Schoolbox\Mapper;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Notification\Parameter\UserParam;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundle;
use Schoolbox\Role;
use Schoolbox\RoleMapper;
use Schoolbox\User;
use Url;

class ArticleNotifier extends AbstractModuleNotifier
{

    const TRIGGER_POSTED = 'posted';

    /** @var RoleMapper */
    protected $roleMapper;

    /**
     * @return string
     */
    protected function getSlug()
    {
        return NSMapper::NS_NEWS;
    }

    /**
     * Instantiate all your dependencies within yourself.
     *
     * @param mixed $dependencies
     * @return void
     */
    public function loadDependencies($dependencies = null)
    {
        $this->roleMapper = RoleMapper::getInstance();
        parent::loadDependencies($dependencies);
    }


    /**
     * @param TriggerBundle $bundle
     * @return void
     */
    public function bundleTriggers($bundle)
    {
        /** @var Article $article */
        $article = $bundle->getEvent()->getObject();

        $publishDate = $article->getPublishDate()->getUnixTimeStamp();

        switch ($bundle->getMethod()) {
            case Mapper::METHOD_CREATE:
                // fire at the time of publication (if it's in the past or null, it will be immediate)
                $bundle->attach(self::TRIGGER_POSTED, $publishDate);
                break;

            case Mapper::METHOD_UPDATE:
                if ($publishDate >= time()) {
                    // in the future
                    $bundle->attach(self::TRIGGER_POSTED, $publishDate);

                } else {
                    // in the past: detach any existing
                    $bundle->detach(self::TRIGGER_POSTED);
                }
                break;

            case Mapper::METHOD_DELETE:
                $bundle->detach(self::TRIGGER_POSTED);
        }
    }


    /**
     * Notify a group type that news has been posted
     *
     * @param string $roleType
     * @param Article $article
     * @param User $user
     * @return Envelope
     */
    protected function generateNotification($roleType, $article, $user)
    {
        $envelope = new Envelope();

        // self hydration -_-
        /** @var Article $article */
        $article = $this->mapper->findById($article->getId());

        if (is_object($article->getImage())) {
            $hash = $article->getImage()->getHash();
            $icon = "/storage/image.php?hash=$hash&size=square64";
            $thumb = "/storage/image.php?hash=$hash&size=square200";
        } else {
            $icon = "/portrait.php?userId={$user->getExternalId()}&size={$this->iconSize}";
            $thumb = '/images/emails/spacer.gif';
        }

        $href = '/news/item/' . $article->getId();

        $articleId = $article->getId();
        $heading = $article->getHeading();

        // use the more recent of the two available dates
        $activeDate = $article->getPublishDate() < $article->getPostDate()
            ? $article->getPostDate()
            : $article->getPublishDate();

        $shortDate = $activeDate->getDateOnly();

        $authorName = $user->formatName();

        $topicName = $article->getTopic()->getName();
        $url = new Url($topicName, false);
        $topicSlug = $url->getSlug();

        $roleIds = $article->getRoles()->filterBy('type', $roleType)->getColumnValues('id');
        $campusIds = $article->getCampuses()->getColumnValues('id');

        // if there's no overlap, nobody can theoretically receive this message, so don't send it
        if (!(count($roleIds) && count($campusIds))) {
            return $envelope;
        }

        $bob = new Builder();

        $notifBody = strlen($article->getBlurb()) > 0 ? $article->getBlurb() : $article->getBody();

        // title, params (if title :tokenised), icon, href, namespace, severity and topic are mandatory

        $instance = $bob->setTitle(":user posted :title under :topic")
            ->setParams(array(
                'user'  => new UserParam($user, Param::STACKING_REPLACE),
                'title' => new Param($heading, "/news/item/$articleId", Param::STACKING_APPEND),
                'topic' => new Param($topicName, "/news/$topicSlug"),
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
            ->setByline("$shortDate by $authorName in $topicName")
            // timestamp and filters are optional
            ->setTimestamp($activeDate->getUnixTimeStamp())
            // and then you build
            ->build();

        /** @var \Schoolbox\User $articleUser */
        foreach ($article->getUsers() as $articleUser) {
            $envelope->targetToUser($articleUser->getId(), $instance);
        }

        $instance = $bob->addFilter('role', $roleIds)
            ->addFilter('campus', $campusIds)
            ->build();

        $envelope->broadcastToRoleType($roleType, $instance);

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
        return $this->generateNotification(Role::STUDENT, $article, $user);
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
        return $this->generateNotification(Role::STAFF, $article, $user);
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
        return $this->generateNotification(Role::PARENT, $article, $user);
    }

}
