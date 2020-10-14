<?php
/**
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Image\Cache;

use Alaress_Byte_Format;
use League\Flysystem\FilesystemInterface;
use League\Flysystem\FilesystemNotFoundException;
use League\Flysystem\MountManager;
use Schoolbox\Config\Loader;
use Schoolbox\Resource;

/**
 * Loads images from the system's configured storage engine.
 */
class Storage implements CacheInterface
{
    /**
     * The actual resource object from storage
     *
     * @var Resource\Storage\Model
     */
    private $file;

    /** @var FilesystemInterface */
    private $fileSystem;

    /**
     * The $key is a way to uniquely associate a cached image with a particular
     * series of transformations applied to it. In this caching implementation,
     * it's the file hash concatenated with the name of the transformation applied.
     *
     * e.g. e2685ab-square64
     *
     * @var string
     */
    private $key;

    /**
     * Remember whether we loaded from the cached file. If it was loaded from the
     * cache, we can skip transformations as we assume they have been done in the past.
     * Otherwise we would be re-transforming an already-transformed image repeatedly
     *
     * @var bool
     */
    private $loadedFromCache = false;

    /** @var MountManager */
    private $mgr;


    /**
     * Constructor
     * Remembers what image you are trying to load from cache
     *
     * @param Resource\Storage\Model $file
     * @param string                 $key
     * @throws FilesystemNotFoundException
     */
    public function __construct(Resource\Storage\Model $file, string $key)
    {
        // these should *probably* be some sort of DI
        // TODO SA-8354: inject storage_dir or manager only
        $conf = new Loader();
        $factory = new Resource\FilesystemFactory();

        $this->file       = $file;
        $this->fileSystem = $this->mgr->getFilesystem(Resource\Storage\Manager::FS_STORAGE);
        $this->key        = $key;
        $this->mgr        = $factory->build($conf->get('storage_dir'));
    }


    /** @inheritDoc */
    public function getKey(): string
    {
        return $this->key;
    }


    /** @inheritDoc */
    public function isLoadedFromCache(): bool
    {
        return $this->loadedFromCache;
    }

    /** @inheritDoc */
    public function load(): Resource\Storage\Model
    {
        $cached = $this->loadCache();

        /* If this particular transformation key existed at some point,
         * this is what it's name would be
         *
         * e.g. e2685ab-Web, e2685ab-ChickenPox, etc. where Web and ChickenPox
         * correspond to a series of transformations
         */
        if ($cached->exists()) {
            // flag that this is an already-cached file
            $this->loadedFromCache = true;
            return $cached;
        }

        /* Otherwise continue with this cache implementation
         */
        return $this->file;
    }

    /** @inheritDoc */
    public function loadCache(): Resource\Storage\Model
    {
        $cached = clone $this->file;

        $this->makeCache($cached);

        return $cached;
    }


    /**
     * @param Resource\Storage\Model $file
     */
    protected function makeCache(Resource\Storage\Model $file): void
    {
        $mgr = new Resource\Storage\Manager($this->mgr, new Alaress_Byte_Format());

        $file->setFilesystem($this->fileSystem);
        $file->setPath($mgr->calcPath($file) . '-' . $this->getKey());
    }
}
