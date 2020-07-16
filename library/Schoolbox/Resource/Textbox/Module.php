<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Resource\Textbox;

use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;


/**
 * Module for textboxes.
 *
 * Currently only defines Twig template paths: there are not yet any controller
 * paths for textboxes.
 */
class Module extends BaseModule
{
    /**
     * @var string
     */
    protected $basename = 'textbox';


    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
