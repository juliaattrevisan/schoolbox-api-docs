<?php
/**
 * Alaress\Image\Cache\Storage
 * Loads images from the storage engine.
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Image\Cache;

use Alaress_Byte_Format;
use League\Flysystem\File;
use Schoolbox\Config\Loader;
use Schoolbox\Resource;
use TypeError;

class Storage extends Flysystem
{
    /** @var \League\Flysystem\MountManager */
    protected $mgr;

    /**
     * Constructor
     * Remembers what image you are trying to load from cache
     *
     * @param Resource\Storage\Model $resource
     * @param string         $key
     */
    public function __construct(Resource\Storage\Model $resource, $key)
    {
        // these should *probably* be some sort of DI
        $conf = new Loader();
        $factory = new Resource\FilesystemFactory();

        $this->mgr = $factory->build($conf->get('storage_dir'));

        parent::__construct($resource, $key, $this->mgr->getFilesystem(Resource\Storage\Manager::FS_STORAGE));
    }

    /**
     * @param File $file
     * @throws TypeError if $file isn't a Resource\Storage\Model
     */
    protected function makeCache(File $file)
    {
        // I don't like being this messy, but the alternative is making this
        // method declaration inconsistent with its parent.
        if (!($file instanceof Resource\Storage\Model)) {
            throw new TypeError('$file must be an instance of "\Schoolbox\Resource\Storage\Model"');
        }
        $mgr = new Resource\Storage\Manager($this->mgr, new Alaress_Byte_Format());

        $file->setFilesystem($this->cacheFs);
        $file->setPath($mgr->calcPath($file) . '-' . $this->getKey());
    }

}
