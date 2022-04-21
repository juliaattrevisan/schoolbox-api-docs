<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Intellischool;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use InvalidArgumentException;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Intellischool\JwtFactory;
use Schoolbox\Mapper;
use Schoolbox\Url\Factory;
use Schoolbox\UserMapper;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'adminIntellischool';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     * @throws InvalidArgumentException
     */
    protected function registerRoutes(): void
    {
        // Various permissions
        $basePermission = new Rule\All(
            new Rule\IsConfigOn(ConfigMapper::NAME_INTELLISCHOOL_JWT_PRIVATE_KEY, $this->getConfig()),
            new Rule\IsConfigOn(ConfigMapper::NAME_INTELLISCHOOL_JWT_PRIVATE_KEY_SIGNING_ALGORITHM, $this->getConfig()),
            new Rule\IsConfigOn(ConfigMapper::NAME_INTELLISCHOOL_DEPLOYMENT_ID, $this->getConfig()),
            new Rule\IsConfigOn(ConfigMapper::NAME_INTELLISCHOOL_LEARNER_DASHBOARD_BASE_URL, $this->getConfig()),
            new Rule\IsConfigOn(ConfigMapper::NAME_INTELLISCHOOL_COHORT_DASHBOARD_BASE_URL, $this->getConfig()),
        );

        /* Anyone can see the form
         */
        $getPerms = new Rule\All(
            $basePermission,
            new Rule\IsLoggedIn(),
            new Rule\IsSuperuser()
        );

        $this->register('GET', '/console', 'getConsole', $getPerms, null);
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            new JwtFactory(
                $this->getConfig()->get(ConfigMapper::NAME_INTELLISCHOOL_JWT_PRIVATE_KEY),
                $this->getConfig()->get(ConfigMapper::NAME_INTELLISCHOOL_JWT_PRIVATE_KEY_SIGNING_ALGORITHM),
                $this->getConfig()->get(ConfigMapper::NAME_INTELLISCHOOL_DEPLOYMENT_ID),
                $this->getConfig()->get(ConfigMapper::NAME_INTELLISCHOOL_LEARNER_DASHBOARD_BASE_URL),
                $this->getConfig()->get(ConfigMapper::NAME_INTELLISCHOOL_COHORT_DASHBOARD_BASE_URL),
                (new Factory($this->getConfig()))->buildFullyQualified()->toString(),
            )
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
