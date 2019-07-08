<?php
/**
 * ImporterInterface
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\External\Connector\Isams\Base\Importer;

interface ImporterInterface
{

    /**
     * Import data from xml into local schema
     */
    public function import(): void;
}
