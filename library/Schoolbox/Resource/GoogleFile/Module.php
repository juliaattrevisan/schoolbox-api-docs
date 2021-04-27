<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\GoogleFile;

use Alaress\Module\Module as BaseModule;
use Aura\Di\ContainerInterface;
use Alaress\Twig\Loader\IChainLoader;
use Schoolbox\Resource\GoogleFile\Component\Homepage\Controller;

class Module extends BaseModule
{
    /**
     * @var string
     */
    protected $basename = 'googleFile';

    /**
     * {@inheritdoc}
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
}
