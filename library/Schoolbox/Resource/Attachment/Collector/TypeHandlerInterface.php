<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Attachment\Collector;

use Schoolbox\Resource\Storage\IStorage;

/**
 * Interface which all attachment collector interfaces must implement.
 */
interface TypeHandlerInterface
{
    /**
     * Retrieves a file from storage, given an identifier.
     *
     * @param string $id This string
     * @return IStorage
     */
    public function fetch(string $id): IStorage;
}
