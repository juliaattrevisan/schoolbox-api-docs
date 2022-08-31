<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Attachment;

use Schoolbox\Resource\File\IFile;
use Schoolbox\Mapper;
use Schoolbox\Resource\Browser\NodeFactory;
use Schoolbox\Resource\Storage\Model as StorageModel;

class GenericZone extends Zone
{
    /** @var string */
    protected $prefix;


    /**
     * @param string $zoneId
     * @param Mapper $mapper - one that maps a FileInterface model
     * @param string $prefix - the "hash" prefix, used for determining a file's source
     */
    public function __construct($zoneId, $mapper, $prefix = NodeFactory::SBFS)
    {
        parent::__construct($zoneId, $mapper);
        $this->prefix = $prefix;
    }


    /**
     * This will obviously return the type of whatever mapper you passed in.
     * Since the returned object will shortly be passed into deriveSitePageVars(), it's
     * worthwhile using a Mapper than returns FileInterfaces.
     *
     * @param string $id
     * @return StorageModel
     */
    protected function findFile($id)
    {
        return $this->mapper->findById($id);
    }
}
