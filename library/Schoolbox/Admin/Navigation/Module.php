<?php
/**
 * Share navigation element for admin pages
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Admin\Navigation;

use Alaress\Module\Module as BaseModule;
use Alaress\Authorizer\Failure;
use Alaress\Authorizer\Rule;
use Twig_Loader_Chain;

class Module extends BaseModule
{
    protected $basename = 'adminNavigation';

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader) {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
