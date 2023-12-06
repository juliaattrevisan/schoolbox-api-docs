<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\Index;

use Alaress\Database\Connector\IConnector;

abstract class BaseBuilder implements IBuilder
{
    public function __construct(protected IConnector $connector)
    {
    }
}
