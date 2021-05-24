<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\RelationalDelete;

interface IRelationalDelete
{
    /**
     * @param string $hash
     * @param bool   $performDryRun
     */
    public function remove(string $hash, bool $performDryRun): void;
}
