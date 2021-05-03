<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\GoogleFile;

use Alaress\Authorizer\Rule\All;
use Alaress\Authorizer\Rule\IsLoggedIn;
use Alaress\Module\Module as BaseModule;
use Aura\Di\ContainerInterface;
use Alaress\Twig\Loader\IChainLoader;

class Module extends BaseModule
{
    /**
     * @var string
     */
    protected $basename = 'googleFile';

    /**
     * @inheritDoc
     */
    function registerRoutes(): void
    {
        $isStaff = $this->getAuthRule('isStaffOrSuper');
        $this->register(
            self::METHOD_POST,
            '',
            'postGoogleFile',
            new All(
                new IsLoggedIn(),
                $isStaff
            )
        );
    }


    /**
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:' . $this->basename,
            $injector->lazyNew(
                Controller::class,
                [
                    'googleFileMapper' => $this->getMapperFactory()->getLazy(Mapper::class),
                ]
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
