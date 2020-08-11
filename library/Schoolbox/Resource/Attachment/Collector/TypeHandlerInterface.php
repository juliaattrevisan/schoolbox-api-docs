<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Attachment\Collector;


use Schoolbox\Resource\Storage\IStorage;

interface TypeHandlerInterface {

    /**
     * @param string $id
     * @return IStorage
     */
    public function fetch(string $id): IStorage;
}
