<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Paperly;

use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Authorizer\Rule;
use Alaress\Web\Controller\Base;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'paperly';

    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        $isLoggedIn = new Rule\IsLoggedIn();

        $this->register(
            'GET',
            '/viewPaperly',
            'getPaperlyLinks',
            $isLoggedIn
        );

        $this->register(
            'GET',
            '/view/{encodedUrl}',
            'getPaperlyView',
            $isLoggedIn
        );
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->injector->get('paperly.cache')
        );
    }
}
