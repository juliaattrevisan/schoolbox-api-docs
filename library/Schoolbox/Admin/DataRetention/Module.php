<?php

/**
 * Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\DataRetention;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use Schoolbox\Admin\Settings\FieldStrategyFactory;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Mapper;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\UserMapper;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'dataRetention';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        /* Only superusers can receive data from the form
         */
        $superPerms = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\IsSuperuser()
        );

        $this->register('GET', '', 'getSettings', $superPerms, null);
        $this->register('POST', '', 'postSave', $superPerms, null);
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(ConfigMapper::class),
            new FieldStrategyFactory($this->getMapperFactory(), new CollectorFactory()),
            $this->injector->get('moduleCache.rebuilder'),
            $this->injector->get('oidc.cache'),
        );
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
