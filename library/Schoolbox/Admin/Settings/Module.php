<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Settings;

use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use Schoolbox\Config\ConfigMapper;
use Alaress\Authorizer\Rule;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;

class Module extends \Alaress\Module\Module
{
    /** @var string */
    protected $basename = 'settings';

    /**
     * @inheritdoc
     */
    protected function registerRoutes(): void
    {
        $isSuper = new Rule\IsSuperuser();
        $this->register('GET', '/search', 'searchSettings', $isSuper);
        $this->register('GET', '{/typeLabel}', 'getSettings', $isSuper);
        $this->register('POST', '/{typeLabel}', 'postSettings', $isSuper);
    }


    /**
     * @param ContainerInterface|Container $injector
     * @inheritdoc
     * @throws \InvalidArgumentException
     */
    protected function preInitControllerDependencies(ContainerInterface $injector): void
    {
        $injector->params[Controller::class] = [
            'configMapper'         => $this->getMapperFactory()->getLazy(ConfigMapper::class),
            'fieldRendererFactory' => $injector->lazy(function () {
                return new FieldStrategyFactory($this->getMapperFactory(), new CollectorFactory());
            }),
        ];
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
     */
    public function initControllers(ContainerInterface $injector): void
    {
        $injector->set("controller:{$this->basename}", $injector->lazyNew(Controller::class));
    }
}
