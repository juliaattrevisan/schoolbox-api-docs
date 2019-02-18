<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Discussion\Post;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;

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
        // Permission for getting a thread or its contents:
        // If it's a comms news item, it should be the same as the perms for
        // viewing the comms item.
        $authorizer = new Rule\Any(
            new Rule\IsSuperuser()
        );


        // Routes
        $this->register(
            'POST',
            '',
            'createPost',
            $authorizer
        );
        $this->register(
            'DELETE',
            '/{id:\d+}',
            'deletePost',
            $authorizer
        );
        $this->register(
            'GET',
            '/{id:\d+}/posts',
            'getPostsByParent',
            $authorizer
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
            'mapper' => $this->getMapperFactory()->getLazy(Mapper::class),
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
