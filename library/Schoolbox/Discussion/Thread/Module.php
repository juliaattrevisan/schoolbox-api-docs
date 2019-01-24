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
        $authorizer = new Rule\IsSuperuser();


        // Routes:
        // /threads/context/{context_type}/id/{context_id}
        // /threads/{id}/posts
        $this->register(
            'GET',
            '/context/{contextType}/id/{contextId}',
            'getThreadsByContext',
            $authorizer,
            [$this, 'preloadContext']
        );
        $this->register(
            'GET',
            '/{id}/posts',
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
            'threadMapper' => $this->getMapperFactory()->getLazy(Mapper::class),
            'postMapper'   => $this->getMapperFactory()->getLazy(Post\Mapper::class),
        ];
    }


    /**
     * Preload the requested thread context.
     *
     * @param array $params
     * @return array
     * @throws \Schoolbox\Mapper\Exception
     */
    public function preloadContext(array $params): array
    {
        switch ($params['contextType']) {
            case Context\Model::CONTEXT_COMMS:
                // Load the requested comms article
                $context = $this->getMapperFactory()->get(News\Mapper::class)->findById($params['contextId']);
                break;

            default:
                // throw something
                throw new \UnexpectedValueException('contextType is not a known value');
                break;
        }
        return [
            'context' => $context,
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
