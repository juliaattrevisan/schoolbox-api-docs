<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\MarkScheme;

use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Psr\Container\ContainerInterface;

class Module extends BaseModule
{

    /**
     * @inheritdoc
     */
    public function __construct(ContainerInterface $injector, string $basename = '')
    {
        parent::__construct($injector, $basename ?: 'learningMarkScheme');
    }


    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
