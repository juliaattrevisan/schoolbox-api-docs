<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Discussion\Thread;

use Alaress\Authorizer as Auth;
use Alaress\Web\Router\PathMatcher;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Comms\News;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Discussion\Comment;
use Schoolbox\Discussion\Thread;
use Schoolbox\Mapper\Exception as MapperException;

/**
 * Comms-specific module for discussion threads.
 */
class Module extends Thread\Module
{
    /**
     * @var string
     */
    protected $basename = 'commsDiscussionThreads';


    /**
     * All discussion thread routes have a preloader for fetching the context.
     *
     * Articles require hydrated audience segments to determine some permissions.
     * Only superusers and Comms Officers can edit articles with no audience.
     * If you are write accessor, you need hydrated audience segments to do things
     * First experienced in SA-9164; see CanModerateAudience.php:54
     *
     * @param PathMatcher $matcher
     */
    public function initRoutePreloads(PathMatcher $matcher): void
    {
        $this->addRoutePreload(
            $matcher,
            '.*',
            function ($params) {
                $audienceMapper = $this->getMapperFactory()->get(News\Audience\Mapper::class);

                try {
                    $article = $this->buildContext()->findById($params['contextId']);
                    $article->setAudience($audienceMapper->findForArticles([$article->getId()]));
                } catch (MapperException $e) {
                    throw new NotFound("No article found for 'articleId' {$params['contextId']}", 0, $e);
                }

                return [
                    'context' => $article,
                ];
            }
        );
    }


    /**
     * Builds a context object for retrieving and persisting objects that are
     * related to a discussion thread.
     *
     * @return Thread\Context\IThreadLocator
     */
    protected function buildContext(): Thread\Context\IThreadLocator
    {
        /** @var News\Mapper   $newsMapper */
        /** @var Thread\Mapper $threadMapper */
        $newsMapper   = $this->getMapperFactory()->get(News\Mapper::class);
        $threadMapper = $this->getMapperFactory()->get(Thread\Mapper::class);
        return new News\Discussion\ThreadLocator($threadMapper, $newsMapper);
    }


    /**
     * Builds a factory for building decorators around thread objects.
     *
     * @return Thread\ModelDecorator\IFactory
     */
    protected function buildModelDecoratorFactory(): Thread\ModelDecorator\IFactory
    {
        return new ModelDecorator\Factory();
    }


    /**
     * Builds a factory for building decorators around comment objects.
     *
     * @return Comment\ModelDecorator\IFactory
     */
    protected function buildCommentModelDecoratorFactory(): Comment\ModelDecorator\IFactory
    {
        return new News\Discussion\Comment\ModelDecorator\Factory();
    }


    /**
     * Builds an authorization rule for whether a user has permission to read
     * comments in this thread.
     *
     * @return Auth\Rule\IAuthorizationRule
     */
    protected function buildGetCommentsAuthorizationRule(): Auth\Rule\IAuthorizationRule
    {
        return new Auth\Rule\All(
            new Auth\Rule\IsConfigOn(ConfigMapper::COMMS_COMMENTS_ENABLED, $this->getConfig()),
            new Auth\Rule\Any(
                new Auth\Rule\IsSuperuser(),
                new Auth\Rule\LazyCallback(function () {
                    /** @var Auth\Authorizer $authorizer */
                    $authorizer = $this->getInjector()->get('authorizer');
                    return new Thread\Authorizer\CanActUponContext(
                        new News\Discussion\Permission\CanGetComments($authorizer)
                    );
                })
            )
        );
    }


    /**
     * Builds an authorization rule for whether a user has permission to post
     * comments in this thread.
     *
     * @return Auth\Rule\IAuthorizationRule
     */
    protected function buildPostCommentsAuthorizationRule(): Auth\Rule\IAuthorizationRule
    {
        return new Auth\Rule\All(
            new Auth\Rule\IsConfigOn(ConfigMapper::COMMS_COMMENTS_ENABLED, $this->getConfig()),
            new Auth\Rule\Any(
                new Auth\Rule\IsSuperuser(),
                new Auth\Rule\LazyCallback(function () {
                    /** @var Auth\Authorizer $authorizer */
                    $authorizer = $this->getInjector()->get('authorizer');
                    return new Thread\Authorizer\CanActUponContext(
                        new News\Discussion\Permission\CanPostComment($authorizer)
                    );
                })
            )
        );
    }


    /**
     * Builds an authorization rule for whether a user has permission to create
     * a thread.
     *
     * (In this case, it's the same permission as the moderate thread rule: it
     *  likely will be in all cases, but I don't know that for sure.)
     *
     * @return Auth\Rule\IAuthorizationRule
     */
    protected function buildCreateThreadAuthorizationRule(): Auth\Rule\IAuthorizationRule
    {
        return $this->buildModerateThreadAuthorizationRule();
    }


    /**
     * Builds an authorization rule for whether a user has permission to
     * moderate this thread, including deleting comments that another user
     * has made.
     *
     * @return Auth\Rule\IAuthorizationRule
     */
    protected function buildModerateThreadAuthorizationRule(): Auth\Rule\IAuthorizationRule
    {
        return new Auth\Rule\All(
            new Auth\Rule\IsConfigOn(ConfigMapper::COMMS_COMMENTS_ENABLED, $this->getConfig()),
            new Auth\Rule\Any(
                new Auth\Rule\IsSuperuser(),
                new Auth\Rule\LazyCallback(function () {
                    /** @var Auth\Authorizer $authorizer */
                    $authorizer = $this->getInjector()->get('authorizer');
                    return new Thread\Authorizer\CanActUponContext(
                        new News\Discussion\Permission\CanModerateThread($authorizer)
                    );
                })
            )
        );
    }
}
