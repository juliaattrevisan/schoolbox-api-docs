<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api;

use Alaress\Authorizer\Rule\IsLoggedIn;
use Alaress\Database\Connector\IConnector;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
use Schoolbox\Search\Api\Database\AggregateResult;
use Schoolbox\Search\Api\Database\ResultType\User\Hydrator;
use Schoolbox\Search\Api\Database\ResultType\User\Matcher;
use Schoolbox\Search\Api\Database\Source;

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
        $config = $this->getConfig();

        /** @var IConnector $database */
        $database = $this->injector->get('databaseInternalRead');

        return new Controller(
            new AggregateResult(
                $database,
                new Matcher($config, $database->getQuoter()),
            ),
        );
    }
}
