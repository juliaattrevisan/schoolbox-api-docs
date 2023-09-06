<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Mock;

use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
use Faker;

/**
 * The module for the mock version of the search API.
 */
class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'searchv2';


    /**
     * @inheritDoc
     */
    protected function registerRoutes(): void
    {
        $this->register('GET', '', 'getSearch');
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            new ResultBuilder(
                class_exists(Faker\Factory::class)
                    ? Faker\Factory::create()
                    : null
            )
        );
    }
}
