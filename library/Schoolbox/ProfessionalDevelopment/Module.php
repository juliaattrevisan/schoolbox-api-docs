<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\ProfessionalDevelopment;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use InvalidArgumentException;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\UserMapper;

class Module extends BaseModule
{
    /** @var string */
    protected $basename = 'professionaldevelopment';

    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes()
    {
        $updatePD = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\IsUser('userId')
            )
        );

        $preloadUser = function ($params) { return $this->preloadUser($params); };

        $this->register('POST', '/update/{userId}', 'postUpdate', $updatePD, $preloadUser);
    }

    /**
     * Preload the user
     *
     * @param array $params
     * @return array
     * @throws InvalidArgumentException
     * @throws NotFound
     */
    private function preloadUser(array $params)
    {
        try {
            $user = $this->getUserMapper()->findById($params['userId']);
        }
        catch (MapperException $e) {
            throw new NotFound();
        }

        return [
            'user' => $user,
        ];
    }

    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set('controller:' . $this->basename, $injector->lazyNew(Controller::class));
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
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
    protected function preInitControllerDependencies(ContainerInterface $injector)
    {
        /** @var Container $injector */
        $injector->params[Controller::class] = [
            'userMapper' => $this->getMapperFactory()->getLazy(UserMapper::class)
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
}
