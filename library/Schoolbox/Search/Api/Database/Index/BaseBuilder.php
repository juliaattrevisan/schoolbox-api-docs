<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\Index;

use Alaress\Database\Connector\IConnector;
use Psr\Log\LoggerInterface;

/**
 * Base index builder.
 */
abstract class BaseBuilder implements IBuilder
{
    /**
     * Constructor
     *
     * @param IConnector      $connector Database connection
     * @param LoggerInterface $logger    Logger
     */
    public function __construct(protected IConnector $connector, protected LoggerInterface $logger)
    {
    }
}
