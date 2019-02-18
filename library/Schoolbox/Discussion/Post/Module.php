<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Discussion\Post;

use Alaress\Authorizer as Auth;
use Alaress\Module\Module as BaseModule;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use Schoolbox\Comms\News;
use Schoolbox\Discussion\Thread;

/**
 * Module for discussion post requests.
 */
class Module extends BaseModule
{
    /**
     * @var string
     */
    protected $basename = 'discussionPosts';


    /**
     * @inheritdoc
     */
    protected function registerRoutes(): void
    {
        /** @var Auth\Authorizer $authorizer */
        /** @var News\Mapper     $newsMapper */
        $authorizer       = $this->getInjector()->get('authorizer');
        $newsMapper       = $this->getMapperFactory()->get(News\Mapper::class);
        $deleteAuthorizer = new Auth\Rule\Any(
            new Auth\Rule\IsSuperuser(),
            new Auth\Rule\LazyCallback(function () use ($authorizer, $newsMapper) {
                return new Thread\Authorizer\CanDeleteFromThread($authorizer, $newsMapper);
            })
        );
        $getAuthorizer    = new Auth\Rule\Any(
            new Auth\Rule\IsSuperuser(),
            new Auth\Rule\LazyCallback(function () use ($authorizer, $newsMapper) {
                return new Thread\Authorizer\CanGetFromThread($authorizer, $newsMapper);
            })
        );

        // Routes
        $this->register(
            'DELETE',
            '/{id:\d+}',
            'deletePost',
            $deleteAuthorizer,
            [$this, 'preloadPost']
        );
        $this->register(
            'GET',
            '/{id:\d+}/posts',
            'getPostsByParent',
            $getAuthorizer,
            [$this, 'preloadPost']
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
            'mapper'       => $this->getMapperFactory()->getLazy(Mapper::class),
            'threadMapper' => $this->getMapperFactory()->getLazy(Thread\Mapper::class),
        ];
    }


    /**
     * Preload the requested post.
     *
     * @param array $params
     * @return array
     * @throws \Schoolbox\Mapper\Exception
     */
    public function preloadPost(array $params): array
    {
        return [
            'post' => $this->getMapperFactory()->get(Mapper::class)->findById($params['id']),
        ];
    }
}
