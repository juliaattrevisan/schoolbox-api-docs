<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Storage\GC\Check;

use League\Flysystem\Exception;
use Schoolbox\Resource\Storage;

class FileExists implements Check
{

    /**
     * @param Storage\Mapper $mapper
     */
    public function __construct(Storage\Mapper $mapper)
    {
        $this->mapper = $mapper;
    }
    /**
     * Takes an array of hashes, and returns the subset that this check regards as 'stale'
     *
     * @param array $previous
     * @return array
     */
    public function getStale(array $previous)
    {
        foreach ($previous as $i => $hash) {
            try {
                if ($this->mapper->findByHash($hash)->exists()) {
                    unset($previous[$i]);
                }
            } catch (Exception $e) {
                // if a Flysystem exception occurs, we treat the file as stale, so do nothing
            }
        }
        return $previous;
    }

} 