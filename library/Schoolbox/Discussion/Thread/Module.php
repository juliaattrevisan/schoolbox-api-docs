<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Discussion\Thread;

use Alaress\Authorizer as Auth;
use Alaress\Module\Module as BaseModule;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use Schoolbox\Comms\News;
use Schoolbox\Discussion\Post;
use Schoolbox\Discussion\Thread\Authorizer\CanGetFromThread;
use Schoolbox\Discussion\Thread\Authorizer\CanPostIntoThread;

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
        $authorizer       = $this->getInjector()->get('authorizer');
        $newsMapper       = $this->getMapperFactory()->get(News\Mapper::class);
        $getAuthorizer    = new Auth\Rule\Any(
            new Auth\Rule\IsSuperuser(),
            new Auth\Rule\LazyCallback(function () use ($authorizer, $newsMapper) {
                return new CanGetFromThread($authorizer, $newsMapper);
            })
        );
        $postAuthorizer   = new Auth\Rule\Any(
            new Auth\Rule\IsSuperuser(),
            new Auth\Rule\LazyCallback(function () use ($authorizer, $newsMapper) {
                return new CanPostIntoThread($authorizer, $newsMapper);
            })
        );


        // Routes
        $this->register(
            'GET',
            '/{id:\d+}/posts',
            'getPostsByThread',
            $getAuthorizer,
            [$this, 'preloadThread']
        );
        $this->register(
            'POST',
            '/{id:\d+}/posts',
            'createPostInThread',
            $postAuthorizer,
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
    protected function preInitControllerDependencies(ContainerInterface $injector): void
    {
        /** @var Container $injector */
        $injector->params[Controller::class] = [
            'postMapper' => $this->getMapperFactory()->getLazy(Post\Mapper::class),
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
