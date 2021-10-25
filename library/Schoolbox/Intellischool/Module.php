<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Intellischool;

use Alaress\Authorizer\Rule\IsLoggedIn;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use Schoolbox\Config\ConfigMapper;

/**
 * Module for the Intellischool integration.
 */
class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'intellischool';

    /**
     * @inheritdoc
     */
    public function registerRoutes()
    {
        $this->register(self::METHOD_GET, '', 'getIndex', new IsLoggedIn());
    }

    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector)
    {
        /** @var Container $injector */
        $injector->set(
            'controller:' . $this->basename,
            $injector->lazyNew(
                Controller::class,
                [
                    'jwtFactory' => $injector->lazy(function () {
                        return new JwtFactory(
                            $this->getConfig()->get(ConfigMapper::NAME_INTELLISCHOOL_JWT_PRIVATE_KEY),
                            $this->getConfig()->get(ConfigMapper::NAME_INTELLISCHOOL_JWT_PRIVATE_KEY_SIGNING_ALGORITHM),
                            $this->getConfig()->get(ConfigMapper::NAME_INTELLISCHOOL_DEPLOYMENT_ID),
                            $this->getConfig()->get(ConfigMapper::NAME_INTELLISCHOOL_LEARNER_DASHBOARD_BASE_URL),
                            $this->getConfig()->get(ConfigMapper::NAME_INTELLISCHOOL_COHORT_DASHBOARD_BASE_URL),
                        );
                    }),
                ]
            )
        );
    }

    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
