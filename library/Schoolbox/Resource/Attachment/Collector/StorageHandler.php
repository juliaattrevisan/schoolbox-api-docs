<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Attachment\Collector;


use Schoolbox\FileInterface;
use Schoolbox\StorageMapper;
use Schoolbox\StorageMapperInterface;

class StorageHandler implements TypeHandlerInterface {

    /** @var StorageMapper */
    protected $mapper;

    /** @var StorageMapperInterface */
    protected $storer;

    /**
     * @param StorageMapper          $mapper
     * @param StorageMapperInterface $storer
     */
    public function __construct(StorageMapper $mapper, StorageMapperInterface $storer)
    {
        $this->mapper = $mapper;
        $this->storer = $storer;
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
     * @return FileInterface
     */
    public function fetch($id)
    {
        $sourcefile = $this->mapper->findByHash($id);

        // don't try to put storage in storage
        if ($this->storer instanceof StorageMapper) {
            return $sourcefile;
        }

        // transfer from storage to wherever this storer wants to put it
        return $this->storer->upload(
            $sourcefile->getFilename(),
            $sourcefile->getFileLocation(),
            $sourcefile->getOwner()->getId()
        );
    }

}