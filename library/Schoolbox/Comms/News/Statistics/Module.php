<?php
/**
 * Module
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\Comms\News\Statistics;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use InvalidArgumentException;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Comms\News;
use Schoolbox\Comms\News\Authorizer\Rule\IsAuthorOrModerator;
use Schoolbox\Mapper\Exception as MapperException;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'commsNewsStatistics';

    /**
     * Hook method for setting up routes via self::register()
     */
    protected function registerRoutes(): void
    {

        /* Only comms officers and authors can view statistics and obtain their data
         * Supers, Comms Officers, Article Authors
         */
        $modifyPerms = new Rule\LazyCallback(function () {
            return new Rule\All(
                new Rule\IsLoggedIn(),
                new IsAuthorOrModerator($this->getNewsMapper())
            );
        });

        $preloadArticle = function ($params) {
            return $this->preloadArticle($params);
        };

        /* Json payload for the "Conversions, Impressions vs Time" graph
         */
        $this->register('GET', '/{articleId}/interactionsvstime', 'getInteractionsVsTime', $modifyPerms, $preloadArticle);

        /* Overview
         */
        $this->register('GET', '/{articleId}', 'getOverview', $modifyPerms, $preloadArticle);
    }


    /**
     * Preload an article instance
     *
     * @param array $params
     * @return array
     * @throws InvalidArgumentException
     * @throws NotFound
     */
    private function preloadArticle(array $params): array
    {
        try {
            $article = $this->getNewsMapper()->findById($params['articleId']);
        } catch (MapperException $e) {
            throw new NotFound();
        }

        return [
            'article' => $article,
        ];
    }

    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector): void
    {
        $injector->set('controller:' . $this->basename, $injector->lazyNew(Controller::class));
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * Override with any steps required for a controller and its routes
     *
     * You should use this method to initialize any mappers or other objects
     * which the controller needs, but are too heavy to initialize at module
     * init time.
     *
     * @param Container|ContainerInterface $injector The dependency injection container.
     */
    protected function preInitControllerDependencies(ContainerInterface $injector): void
    {
        /** @var Container $injector */
        $injector->params[Controller::class] = [
            'databaseInternal' => $injector->lazy(function () {
                return $this->getInjector()->get('databaseInternal');
            }),
        ];
    }

    /**
     * @return \Schoolbox\Mapper|News\Mapper
     * @throws InvalidArgumentException
     */
    protected function getNewsMapper(): News\Mapper
    {
        return $this->getMapperFactory()->get(News\Mapper::class);
    }
}
