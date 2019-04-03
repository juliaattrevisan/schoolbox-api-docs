<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Discussion\Thread;

use Alaress\Authorizer as Auth;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use Schoolbox\Comms\News;
use Schoolbox\Discussion\Comment;
use Schoolbox\Discussion\Thread\Authorizer\CanActUponThread;

/**
 * Module for discussion thread requests.
 */
class Module extends BaseModule
{
    /**
     * @var string
     */
    protected $basename = 'discussionThreads';


    /**
     * @inheritdoc
     */
    protected function registerRoutes(): void
    {
        /** @var Auth\Authorizer $authorizer */
        /** @var News\Mapper     $newsMapper */
        /** @var Mapper          $threadMapper */
        $authorizer     = $this->getInjector()->get('authorizer');
        $newsMapper     = $this->getMapperFactory()->get(News\Mapper::class);
        $threadMapper   = $this->getMapperFactory()->get(Mapper::class);
        $commsContext   = new News\Discussion\Context($threadMapper, $newsMapper);
        $getAuthorizer  = new Auth\Rule\Any(
            new Auth\Rule\IsSuperuser(),
            new Auth\Rule\LazyCallback(function () use ($authorizer, $commsContext) {
                return new CanActUponThread(
                    new News\Discussion\Permission\CanGet($authorizer, $commsContext)
                );
            })
        );
        $postAuthorizer = new Auth\Rule\Any(
            new Auth\Rule\IsSuperuser(),
            new Auth\Rule\LazyCallback(function () use ($authorizer, $commsContext) {
                return new CanActUponThread(
                    new News\Discussion\Permission\CanPost($authorizer, $commsContext)
                );
            })
        );
        $moderateAuthorizer = new Auth\Rule\Any(
            new Auth\Rule\IsSuperuser(),
            new Auth\Rule\LazyCallback(function () use ($authorizer, $commsContext) {
                return new CanActUponThread(
                    new News\Discussion\Permission\CanModerate($authorizer, $commsContext)
                );
            })
        );


        // Routes
        $this->register(
            'GET',
            '/{id:\d+}',
            'getThread',
            $getAuthorizer,
            [$this, 'preloadThread']
        );
        $this->register(
            'GET',
            '/{id:\d+}/comments',
            'getCommentsByThread',
            $getAuthorizer,
            [$this, 'preloadThread']
        );
        $this->register(
            'POST',
            '',
            'createThread',
            new Auth\Rule\IsLoggedIn()
        );
        $this->register(
            'POST',
            '/{id:\d+}/comments',
            'createCommentInThread',
            $postAuthorizer,
            [$this, 'preloadThread']
        );
        $this->register(
            'PUT',
            '/{id:\d+}/open',
            'openThread',
            $moderateAuthorizer,
            [$this, 'preloadThread']
        );
        $this->register(
            'PUT',
            '/{id:\d+}/close',
            'closeThread',
            $moderateAuthorizer,
            [$this, 'preloadThread']
        );
    }


    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector): void
    {
        $injector->set('controller:' . $this->basename, $injector->lazyNew(Controller::class));
    }


    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }


    /**
     * @inheritdoc
     */
    protected function preInitControllerDependencies(ContainerInterface $injector): void
    {
        /** @var Container $injector */
        $injector->params[Controller::class] = [
            'commentMapper' => $this->getMapperFactory()->getLazy(Comment\Mapper::class),
            'threadMapper'  => $this->getMapperFactory()->getLazy(Mapper::class),
        ];
    }


    /**
     * Preload the requested thread.
     *
     * @param array $params
     * @return array
     * @throws \Schoolbox\Mapper\Exception
     */
    public function preloadThread(array $params): array
    {
        return [
            'thread' => $this->getMapperFactory()->get(Mapper::class)->findById($params['id']),
        ];
    }
}
