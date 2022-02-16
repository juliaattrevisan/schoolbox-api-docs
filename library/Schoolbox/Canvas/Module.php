<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.com.au>
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Canvas;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use InvalidArgumentException;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'canvas';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     * @throws InvalidArgumentException
     */
    protected function registerRoutes(): void
    {
        /* Anyone can see the form
         */
        $getPerms = new Rule\All(
            new Rule\IsLoggedIn()
        );

        $this->register('GET', '/assignments', 'getAssignments', $getPerms, null);
    }


    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getConfig()->get('canvas_url'),
            $this->getConfig()->get('canvas_bearer_token'),
        );
    }


    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
