<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Resource\Attachment\Collector;


use Schoolbox\Resource\Storage\IStorage;

interface TypeHandlerInterface {

    /**
     * @param string $id
     * @return IStorage
     */
    public function fetch($id);

} 
