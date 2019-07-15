<?php
/**
 * ImporterInterface
 *
 * Every importer strat must provide this method
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Importer;

interface ImporterInterface
{

    /**
     * Import data from xml into local schema
     */
    public function import(): void;
}
