<?php
/**
 * Alaress_Image_Processor
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Image\Processor;

use Alaress\Image\Cache\CacheInterface;
use Alaress\Image\Filetype;
use Imagine\Image\ImageInterface;
use Schoolbox\Resource\Storage;
use Alaress\Image\Transformation\Transformation;
use BadMethodCallException;
use League\Flysystem;
use Schoolbox\Resource\Storage\Transform\Mapper;

class Standard
{
    /**
     * Cache class to use
     *
     * @var CacheInterface
     */
    private $cache;

    /**
     * A factory for building processors, replaces $processorStrategyPrefix.
     *
     * @var Strategy\FactoryInterface
     */
    private $processorFactory;

    /**
     * @var Strategy\StrategyInterface
     */
    private $processorStrategy;

    /**
     * A factory for building file types, replaces $filetypeStrategyPrefix.
     *
     * @var Filetype\FactoryInterface
     */
    private $filetypeFactory;

    /**
     * @var Filetype\FiletypeInterface
     */
    private $filetypeStrategy;

    /**
     * Image being manipulated
     *
     * @var ImageInterface
     */
    private $image;

    /**
     * A list of all the transformations
     *
     * @var Transformation
     */
    private $transformations;

    /* I never ask those questions; I make those decisions. --Ryan
	 */

    /** @var Flysystem\File */
    private $file;

    /**
     * Constructor
     *
     * @param CacheInterface            $cache
     * @param Transformation            $transformations
     * @param Filetype\FactoryInterface $filetypeFactory
     * @param Strategy\FactoryInterface $processorFactory
     */
    public function __construct(
        CacheInterface $cache,
        Transformation $transformations,
        Filetype\FactoryInterface $filetypeFactory,
        Strategy\FactoryInterface $processorFactory
    ) {
        $this->cache = $cache;
        $this->transformations = $transformations;
        $this->filetypeFactory = $filetypeFactory;
        $this->processorFactory = $processorFactory;
    }

    /**
     * Load the image from the resource, and apply transformations
     * Supports chained methods
     *
     * @return static
     * @throws \OverflowException if file's too big
     * @throws \RuntimeException if something goes wrong during building
     */
    public function load()
    {
        $this->file = $this->cache->load();

        // Die extremely early if this is corrupted or giant
        // Figure out an image size in megapixels
        [$w, $h] = getimagesizefromstring($this->file->read());
        $megapixelSize = ($w * $h) / 1000000;
        if ($megapixelSize >= 50) {
            // error out
            throw new \OverflowException('File is over 50MP large - aborting');
        }

        $this->filetypeStrategy = $this->filetypeFactory->build([
            'mime' => (string)$this->file->getMimetype() // ensure this is actually a string (it may have been a mimetype object)
        ]);

        # TODO this is the key line, it is creating image magick temp fiels
        $this->image = $this->filetypeStrategy->loadImageResource($this->file);

        // Prepare an image resource for chained manipulation

        if (!$this->cache->isLoadedFromCache()) {
            $this->transformAll();
        }

        return $this;
    }

    /**
     * Select an image modification strategy, and supply it with any
     * desired arguments in an associative array
     * Supports chained methods
     *
     * @param string $strategy
     * @param array|null  $args
     *
     * @return static
     */
    private function loadProcessorStrategy(string $strategy, ?array $args = null)
    {
        $this->processorStrategy = $this->processorFactory->build(
            $strategy,
            $args,
            $this->filetypeStrategy
        );
        return $this;
    }

    /**
     * Delegate method
     * Use processor strategy to manipulate image resource and remember
     * the resultant image resource
     * Supports chained methods
     *
     * @return static
     */
    private function manipulate(): self
    {
        $this->image = $this->processorStrategy->manipulate($this->image);

        return $this;
    }

    /**
     * Perform all transformations if we didn't load an image from the cache.
     * Otherwise transformations were done previously and saved, and we can skip
     * this step entirely
     *
     * @return static
     */
    public function transformAll(): self
    {

        /* Retrieve the list of transformations and do 'em, if we haven't already
         * done them at some point in the past
         */
        if ($this->isCached()) {
            return $this;
        }

        $allTransformations = array(
            array(
                'method' => 'RotateOrientation',
            )
        );
        try {
            $allTransformations = array_merge(
                $allTransformations,
                $this->transformations->retrieve(
                    $this->getTransformKey()
                )
            );
        } catch (BadMethodCallException $e) {
            // sooo, failed to merge. I think we can live with that, will just output default
        }

        /* Do it
         */
        foreach ($allTransformations as $currentTransformation) {
            $this->transform($currentTransformation['method'], $currentTransformation['args']);
        }

        return $this;
    }

    /**
     * @return string
     */
    protected function getTransformKey(): string
    {
        return $this->cache->getKey();
    }

    /**
     * Facade method
     * Combines loadProcessorStrategy() and manipulate() to support chained
     * methods in an easy-to-ready set of statements
     * Supports chained methods
     *
     * @param string $strategy
     * @param null   $args
     *
     * @return static
     */
    public function transform(string $strategy, $args = null): self
    {
        $this->loadProcessorStrategy($strategy, $args);
        $this->manipulate();

        return $this;
    }

    /**
     * Delegate method
     * Cache then stream the manipulated image
     */
    public function stream(): void
    {
        // Add cache header for logging when a cached transform is used
        if (!$this->isCached()) {
            header('X-Cache-Status: MISS');
        } else {
            header('X-Cache-Status: HIT');
        }

        $this->save();

        $this->filetypeStrategy->stream($this->image);
        // will exit
    }

    /**
     * Saves the transformed image
     *
     * @return static
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function save()
    {
        if (!$this->isCached() && $this->cache->getKey()) {
            $this->filetypeStrategy->save($this->image, $this->cache->loadCache());
        }

        if ($this->cache->getKey()) {
            /** @var Storage\Model $file */
            $file = $this->file;
            Mapper::getInstance()->createRecord(
                $file->getHash(),
                $this->cache->getKey(),
                $this->file->getSize()
            );
        }

        return $this;
    }

    /**
     * @return bool
     */
    public function isCached(): bool
    {
        return $this->cache->isLoadedFromCache();
    }
}
