<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Browser;

use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;

abstract class AbstractModule extends BaseModule
{
    /**
     * @param IChainLoader $templateLoader
     * @throws \Twig\Error\LoaderError
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
