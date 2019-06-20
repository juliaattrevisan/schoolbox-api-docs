<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Resource\Storage\GarbageCollector;

interface IGarbageCollector
{
    /**
     * @param string $hash
     *
     * @return mixed
     */
    public function remove(string $hash);
}
