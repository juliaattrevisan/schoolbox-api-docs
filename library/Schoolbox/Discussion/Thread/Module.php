<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Discussion\Thread;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use Schoolbox\Comms\News;
use Schoolbox\Discussion\Post;

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
        // Permission for getting a thread or its contents:
        // If it's a comms news item, it should be the same as the perms for
        // viewing the comms item.
        $authorizer = new Rule\Any(
            new Rule\IsSuperuser(),
            new Rule\LazyCallback(function () {
                return new Authorizer\CanReadThread(
                    $this->getInjector()->get('authorizer'),
                    $this->getMapperFactory()->get(News\Mapper::class)
                );
            })
        );


        // Routes
        $this->register(
            'GET',
            '/{id:\d+}/posts',
            'getPostsByThread',
            $authorizer,
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
