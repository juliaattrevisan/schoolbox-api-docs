<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\DifferentiationProfile;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use InvalidArgumentException;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\DifferentiationProfile\Controller;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\UserMapper;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'differentiationProfiles';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        /* Only logged in users with the differentiation profile service permission can view the form
         */
        $getPerms = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\HasServicePermission(
                UserAccess::SERVICE_DIFFERENTIATION_PROFILE,
                UserAccess::ACCESS_WRITE
            )
        );

        /* Only superusers can receive data from the form
         */
        $postPerms = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\IsSuperuser()
        );

        $this->register('GET', '/manage', 'manageDifferentiationProfiles', $getPerms);
//        $this->register('POST', '/user', 'postExample', $postPerms, null);
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->injector->get('l10n'),
            $this->getMapperFactory()->get(ErrorMapper::class),
            $this->getMapperFactory()->get(UserMapper::class)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
