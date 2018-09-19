<?php
/**
 * Module
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\Comms\News\Moderation;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use InvalidArgumentException;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Comms\News\Mapper as NewsMapper;
use Schoolbox\Comms\News\Policy\Mapper as PolicyMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\UserMapper;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'commsNewsModeration';

    /**
     * Hook method for setting up routes via self::register()
     *
     * @throws InvalidArgumentException
     */
    protected function registerRoutes(): void
    {
        $moderatorPerms = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasServicePermission(UserAccess::SERVICE_COMMS_OFFICER, UserAccess::ACCESS_FULL)
            )
        );

        $preloadNews = function ($params) {
            return $this->preloadNews($params);
        };

        /* Get a list of articles needing moderation
         */
        $this->register('GET', '', 'getList', $moderatorPerms);

        /* Submit For Moderation
         */
        $this->register('POST', '/{articleId:\d+}/submit', 'postSubmit', null, $preloadNews);

        /* The Fate of the Article: accept/reject it
         */
        $this->register('POST', '/{articleId:\d+}/accept', 'postApprove', $moderatorPerms, $preloadNews);
        $this->register('POST', '/{articleId:\d+}/reject', 'postReject', $moderatorPerms, $preloadNews);
    }

    /**
     * Preload the news article
     *
     * @param array $params
     * @return array
     * @throws \Schoolbox\Application\Exception\NotFound
     * @throws InvalidArgumentException
     * @throws NotFound
     */
    private function preloadNews(array $params) :array
    {
        try {
            $article = $this->getNewsMapper()->findById($params['articleId']);
        } catch (MapperException $e) {
            throw new NotFound($this->getErrorMapper()->getMessageById(ErrorMapper::ERROR_GENERIC_NOT_FOUND));
        }

        return [
            'article' => $article,
        ];
    }


    /**
     * @return Mapper|NewsMapper
     * @throws \InvalidArgumentException If cannot find mapper class
     */
    protected function getNewsMapper()
    {
        return $this->getMapperFactory()->get(NewsMapper::class);
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
     * @throws InvalidArgumentException
     */
    protected function preInitControllerDependencies(ContainerInterface $injector): void
    {
        /** @var Container $injector */
        $injector->params[Controller::class] = [
            'newsMapper'   => $this->getMapperFactory()->getLazy(NewsMapper::class),
            'policyMapper' => $this->getMapperFactory()->getLazy(PolicyMapper::class),
        ];
    }

    /**
     * @return Mapper|UserMapper
     * @throws \InvalidArgumentException If cannot find mapper class
     */
    protected function getUserMapper()
    {
        return $this->getMapperFactory()->get(UserMapper::class);
    }


    /**
     * @return Mapper|ErrorMapper
     * @throws InvalidArgumentException
     */
    protected function getErrorMapper()
    {
        return $this->getMapperFactory()->get(ErrorMapper::class);
    }
}
