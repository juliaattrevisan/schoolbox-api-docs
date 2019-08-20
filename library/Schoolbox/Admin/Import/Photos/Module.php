<?php
/**
 * Import portrait pictures for users
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\Import\Photos;

use Alaress\Module\Module as BaseModule;
use Alaress\Authorizer\Rule;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use Alaress\Twig\Loader\IChainLoader;
use InvalidArgumentException;
use Schoolbox\Resource\Storage;
use Schoolbox\UserMapper;

class Module extends BaseModule
{
    protected $basename = 'adminImportPhotos';

    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        /* Only supers can get here
         */
        $superuserOnly = new Rule\LazyCallback(function () {
            return new Rule\All(
                new Rule\IsLoggedIn(),
                new Rule\IsSuperuser()
            );
        });

        $this->register('GET', '/upload', 'getIndex', $superuserOnly);
        $this->register('GET', '', 'getView', $superuserOnly);
        $this->register('POST', '/upload', 'postUpload', $superuserOnly);
        $this->register('POST', '/delete', 'postDelete', $superuserOnly);
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
            'storageMapper' => $this->getMapperFactory()->getLazy(Storage\Mapper::class),
            'userMapper' => $this->getMapperFactory()->getLazy(UserMapper::class),
        ];
    }
}
