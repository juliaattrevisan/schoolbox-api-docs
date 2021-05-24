<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Twitter;

use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;

/**
 * Module for the Twitter component
 */
class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'twitter';


    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
