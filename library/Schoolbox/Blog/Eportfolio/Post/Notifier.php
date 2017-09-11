<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Blog\Eportfolio\Post;

use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Parameter\Param;
use Alaress\Async\Notification\Parameter\ParameterInterface;
use Eportfolio\PageMapper;
use Monolog\Logger;
use Schoolbox\IMapper;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Notification\Parameter\UserParam;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundle;
use Schoolbox\User;
use Schoolbox\UserMapper;
use OutOfBoundsException;
use InvalidArgumentException;


class Notifier extends AbstractModuleNotifier
{

    const TRIGGER_POSTED = 'posted';
    const BASE_URI = '/eportfolio/';

    /**
     * @var PageMapper
     */
    private $pageMapper;

    /**
     * @var UserMapper
     */
    private $userMapper;


    /**
     * @param null|IMapper[] $dependencies
     */
    public function loadDependencies($dependencies = null)
    {
        $dependencies = $dependencies ?: [];

        $dependencies['mapper'] = isset($dependencies['mapper'])
            ? $dependencies['mapper']
            : Mapper::getInstance();

        $dependencies['pageMapper'] = isset($dependencies['pageMapper'])
            ? $dependencies['pageMapper']
            : PageMapper::getInstance();

        $dependencies['userMapper'] = isset($dependencies['userMapper'])
            ? $dependencies['userMapper']
            : UserMapper::getInstance();

        parent::loadDependencies($dependencies);
    }


    /**
     * @param TriggerBundle $bundle
     * @return void
     */
    public function bundleTriggers($bundle)
    {
        // we only care about notifying blog post creation
        if ($bundle->getMethod() === BaseMapper::METHOD_CREATE) {

            /** @var Model $blog */
            $blog = $bundle->getEvent()->getObject();

            //TODO remove this hack when SA-4400 is merged.
            $blog = $this->getMapper()->findByColumn(
                $this->getMapper()->getIdentifierColumn(), $blog->getId(), ['cache' => false]
            )->first();
            $bundle->getEvent()->setObject($blog);

            $bundle->attach(self::TRIGGER_POSTED, $blog->getCreatedAt()->getUnixTimeStamp());
        }
    }


    /* ASYNC NOTIFICATION METHODS */

    /**
     * Advise a student someone posted on their Eportfolio blog
     *
     * @param Model $post
     * @param User  $effector
     * @return Envelope
     * @throws OutOfBoundsException     If severity is not set
     * @throws InvalidArgumentException If a required property was not set
     */
    public function notifyStudentPosted(Model $post, User $effector)
    {
        $envelope    = new Envelope();
        $bob         = new Builder();
        $page        = $this->pageMapper->findById($post->getPage()->getPageId());
        $owner       = $this->userMapper->findById($page->getUid());
        $authorExtId = $effector->getExternalId();

        $bob->setTitle(':author posted about :title on your eportfolio blog')
            ->setParams([
                'author' => new UserParam($effector, ParameterInterface::STACKING_REPLACE),
                'title'  => new Param($post->getHeading(), $this->buildBlogUrl($owner, $post)),
            ])
            ->setIcon("/portrait.php?userId=$authorExtId&size={$this->iconSize}")
            ->setHref($this->buildBlogUrl($owner, $post))
            ->setSeverity(Logger::INFO)
            ->setNamespace($this->getSlug())
            ->addTopic("eportfolio.blog.{$post->getId()}." . self::TRIGGER_POSTED)
            ->setTimestamp($post->getCreatedAt()->getUnixTimeStamp())
            ->setBody($post->getBody());

        $envelope->targetToUser($owner->getId(), $bob->build());
        return $envelope;
    }


    /**
     * @param UserMapper $userMapper
     * @return $this
     */
    protected function setUserMapper(UserMapper $userMapper)
    {
        $this->userMapper = $userMapper;
        return $this;
    }


    /**
     * @param PageMapper $pageMapper
     * @return $this
     */
    protected function setPageMapper(PageMapper $pageMapper)
    {
        $this->pageMapper = $pageMapper;
        return $this;
    }


    /**
     * @return string
     */
    protected function getSlug()
    {
        return NSMapper::NS_EPORTFOLIO_BLOG;
    }


    /**
     * @param User  $student
     * @param Model $post
     * @return string
     */
    private function buildBlogUrl(User $student, Model $post)
    {
        return self::BASE_URI . "{$student->getId()}/{$post->getPage()->getPageId()}#blog_{$post->getId()}";
    }
} 
