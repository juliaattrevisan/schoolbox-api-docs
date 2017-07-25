<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\MarkScheme;

use Alaress\Module\Module as BaseModule;
use Aura\Di\ContainerInterface;

class Module extends BaseModule
{

    /**
     * @inheritdoc
     */
    public function __construct(ContainerInterface $injector, $basename = '')
    {
        parent::__construct($injector, $basename ?: 'learningMarkScheme');
    }


    /**
     * @inheritdoc
     */
    public function initTemplatePaths(\Twig_Loader_Chain $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
