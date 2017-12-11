<?php
/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Tour;

use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;

class Module extends BaseModule
{
    /** @var string $basename */
    protected $basename = 'tour';

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}