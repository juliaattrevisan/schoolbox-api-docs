<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\MarkScheme;

use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;

class Module extends BaseModule
{
    /**
     * @var string
     */
    protected $basename = 'learningMarkScheme';

    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
