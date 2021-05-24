<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Oauth;

use Alaress\Authorizer\Rule;
use Alaress\Database\Quoter\Mysql\Quoter;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use InvalidArgumentException;
use Schoolbox\Admin\Settings\FieldStrategyFactory;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;

class Module extends BaseModule
{

    public const BASE_NAME = 'adminOauth';


    /**
     * @inheritdoc
     */
    public function __construct(ContainerInterface $injector, string $basename = '')
    {
        parent::__construct($injector, $basename ?: self::BASE_NAME);
    }


    /**
     * @inheritdoc
     */
    protected function registerRoutes(): void
    {
        $isSuper = new Rule\IsSuperuser();

        $this->register('GET', '', 'getAdmin', $isSuper);
        $this->register('POST', '', 'postAdmin', $isSuper);
    }


    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector): void
    {
        $injector->set('controller:' . self::BASE_NAME, $injector->lazyNew(Controller::class));
    }


    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
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
            'quoter'               => $injector->lazy(function () {
                return new Quoter();
            }),
        ];
    }
}
