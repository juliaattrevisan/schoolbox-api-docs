<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Attachment\Collector;


use Schoolbox\IConfigLoader;
use Schoolbox\Resource\FileMapper;
use Schoolbox\Storage;
use Schoolbox\StorageMapper;

class SbfsHandler implements TypeHandlerInterface {

    /** @var FileMapper */
    protected $fileMapper;

    /** @var StorageMapper */
    protected $storageMapper;

    /** @var IConfigLoader */
    protected $conf;

    /**
     * @param FileMapper $fileMapper
     * @param StorageMapper $storageMapper
     * @param IConfigLoader $conf
     */
    public function __construct(FileMapper $fileMapper, StorageMapper $storageMapper, IConfigLoader $conf)
    {
        $this->fileMapper = $fileMapper;
        $this->storageMapper = $storageMapper;
        $this->conf = $conf;
    }

    /**
     * @param string $id
     * @return bool
     */
    public function matches($id)
    {
        return substr($id, 0, 5) == 'sbfs.';
    }


    /**
     * TODO for now, we are simply copying resources from SBFS to Storage. Upgrade by intelligently linking through to storage
     *
     * @param string $id
     * @return Storage
     */
    public function fetch($id)
    {
        $sbfsFile = $this->fileMapper->findById(substr($id, 8));

        $pattern  = $this->conf->get('file_dir') . '/%d.dat';
        $filename = $sbfsFile->getFilename();
        $location = sprintf($pattern, $sbfsFile->getId());
        $owner    = $sbfsFile->getOwner()->getId();

        return $this->storageMapper->upload($filename, $location, $owner);
    }

} 