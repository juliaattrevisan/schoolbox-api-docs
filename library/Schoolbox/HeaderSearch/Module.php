<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 */

namespace Schoolbox\HeaderSearch;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;

class Module extends BaseModule
{
    protected $basename = 'headersearch';

    /**
     * {@inheritdoc}
     */
    protected function registerRoutes(): void
    {
        $loggedIn = new Rule\LazyCallback(function () {
            return new Rule\All(
                new Rule\IsLoggedIn()
            );
        });

        $this->register('GET', '/search', 'search', $loggedIn);
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller();
    }
}
