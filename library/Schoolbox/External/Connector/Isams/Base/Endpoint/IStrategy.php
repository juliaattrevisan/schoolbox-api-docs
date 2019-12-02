<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint;

/**
 * Interface IInterface
 *
 * Every importer strat must provide this method
 *
 * @package Schoolbox\External\Connector\Isams\Base\Endpoint
 */
interface IStrategy
{

    /**
     * Import data from xml into local schema
     */
    public function import(): void;
}
