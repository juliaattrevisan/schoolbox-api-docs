<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api;

use Alaress\Authorizer\Rule\IsLoggedIn;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;

/**
 * Module for the search API.
 */
class Module extends BaseModule
{
    protected $basename = 'searchApi';

    /**
     * @inheritdoc
     */
    public function registerRoutes(): void
    {
        $permission = new IsLoggedIn();
        $this->register(self::METHOD_GET, '', 'getSearch', $permission);
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller();
    }
}
