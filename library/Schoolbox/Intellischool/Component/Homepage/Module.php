<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Intellischool\Component\Homepage;

use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;

class Module extends BaseModule
{
    /** @var string */
    protected $basename = 'intellischoolHomepage';

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
