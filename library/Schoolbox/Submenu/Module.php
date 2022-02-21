<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Submenu;

use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use InvalidArgumentException;

/**
 * Class Module
 *
 * So twig templates can live with their accompanying processor class
 *
 * @package Schoolbox\Submenu
 */
class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'submenu';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     * @throws InvalidArgumentException
     */
    protected function registerRoutes(): void
    {
    }


    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
