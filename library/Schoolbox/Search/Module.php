<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Search;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use Twig\Error\LoaderError;

class Module extends BaseModule
{
    /** @var string */
    protected $basename = 'search';

    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     * @throws \InvalidArgumentException
     */
    protected function registerRoutes()
    {
        $this->register('GET', '', 'search', new Rule\IsLoggedIn());
    }

    /**
     * @param IChainLoader $templateLoader
     * @throws LoaderError
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->injector->get('l10n')
        );
    }
}
