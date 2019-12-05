<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Import;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use InvalidArgumentException;
use Schoolbox\Admin\Settings\FieldStrategyFactory;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Mapper;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\UserMapper;

/**
 * Class Module
 *
 * @package Schoolbox\Admin\Import
 */
class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'adminImportIndex';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     * @throws InvalidArgumentException
     */
    protected function registerRoutes(): void
    {
        $perms = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\IsSuperuser()
        );

        $this->register('GET', '/directImport', 'getDirectIndex', $perms);
        $this->register('GET', '/schoolDataImport', 'getSchoolDataIndex', $perms);
        $this->register('POST', '/schoolDataImport', 'postSchoolDataOptions', $perms);
    }


    /**
     * @inheritdoc
     * @param Container|ContainerInterface $injector The dependency injection container.
     * @throws InvalidArgumentException
     */
    protected function preInitControllerDependencies(ContainerInterface $injector): void
    {
        $injector->params[Controller::class] = [
            'configMapper'         => $this->getMapperFactory()->getLazy(ConfigMapper::class),
            'fieldStrategyFactory' => $injector->lazy(function () {
                return new FieldStrategyFactory($this->getMapperFactory(), new CollectorFactory());
            }),
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
     * @return Mapper|UserMapper
     * @throws \InvalidArgumentException If cannot find mapper class
     */
    protected function getUserMapper()
    {
        return $this->getMapperFactory()->get(UserMapper::class);
    }
}
