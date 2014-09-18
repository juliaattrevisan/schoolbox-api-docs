<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Attachment\Collector;


use Schoolbox\Storage;
use Schoolbox\StorageMapper;

class StorageHandler implements TypeHandlerInterface {

    /** @var StorageMapper */
    protected $mapper;

    /**
     * @param StorageMapper $mapper
     */
    public function __construct(StorageMapper $mapper)
    {
        $this->mapper = $mapper;
    }

    /**
     * @param string $id
     * @return bool
     */
    public function matches($id)
    {
        return preg_match('`^[0-9a-f]{40}$`i', $id);
    }


    /**
     * @param string $id
     * @return Storage
     */
    public function fetch($id)
    {
        return $this->mapper->findByHash($id);
    }

}