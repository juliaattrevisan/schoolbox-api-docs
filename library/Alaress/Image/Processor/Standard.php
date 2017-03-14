<?php
/**
 * Alaress_Image_Processor
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Image\Processor;

use Alaress\Image\Cache\CacheInterface;
use Alaress\Image\Filetype;
use Alaress\Image\Transformation\Transformation;
use BadMethodCallException;
use League\Flysystem;

class Standard extends BaseProcessor
{
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
        $this->setCache($cache);
        $this->setTransformations($transformations);
        $this->setFiletypeFactory($filetypeFactory);
        $this->setProcessorFactory($processorFactory);
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
        $this->file = $file = $this->getCache()->load();

        // Die extremely early if this is corrupted or giant
        $streamMetadata = stream_get_meta_data($file->readStream());
        if ($streamMetadata['uri']) {
            // Figure out an image size in megapixels
            list($w, $h) = getimagesize($streamMetadata['uri']);
            $megapixelSize = ($w * $h) / 1000000;
            if ($megapixelSize >= 50) {
                // error out
                throw new \OverflowException('File is over 50MP large - aborting');
            }
        }

        $mime = $file->getMimetype();

        $filetypeStrat = $this->getFiletypeFactory()->build([
            'mime' => "$mime" // ensure this is actually a string (it may have been a mimetype object)
        ]);

        $this->setFiletypeStrategy($filetypeStrat);

        $image = $this->getFiletypeStrategy()
                      ->loadImageResource($file);

        $this->setImage($image);

        // Prepare an image resource for chained manipulation
        if (!$this->getCache()->isLoadedFromCache()) {
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
     * @param array  $args
     *
     * @return static
     */
    private function loadProcessorStrategy($strategy, $args = null)
    {
        $processor = $this->getProcessorFactory()->build(
            $strategy,
            $args,
            $this->getFiletypeStrategy()
        );
        $this->setProcessorStrategy($processor);
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
    private function manipulate()
    {
        $manipulatedImage = $this->getProcessorStrategy()->manipulate($this->getImage());

        $this->setImage($manipulatedImage);

        return $this;
    }

    /**
     * Perform all transformations if we didn't load an image from the cache.
     * Otherwise transformations were done previously and saved, and we can skip
     * this step entirely
     *
     * @return static
     */
    public function transformAll()
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
                $this->getTransformations()->retrieve(
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
    protected function getTransformKey()
    {
        return $this->getCache()->getKey();
    }

    /**
     * Facade method
     * Combines loadProcessorStrategy() and manipulate() to support chained
     * methods in an easy-to-ready set of statements
     * Supports chained methods
     *
     * @param      $strategy
     * @param null $args
     *
     * @return static
     */
    public function transform($strategy, $args = null)
    {
        $this->loadProcessorStrategy($strategy, $args);
        $this->manipulate();

        return $this;
    }

    /**
     * Delegate method
     * Cache then stream the manipulated image
     */
    public function stream()
    {
        if (!$this->isCached() && $this->getCache()->getKey()) {
            $this->save();
        }

        $this->getFiletypeStrategy()->stream($this->getImage());
        // will exit
    }

    /**
     * Saves the transformed image
     *
     * @return static
     */
    private function save()
    {
        $this->getFiletypeStrategy()->save($this->getImage(), $this->getCache()->loadCache());
        return $this;
    }

    /**
     * @return bool
     */
    private function isCached()
    {
        return $this->getCache()->isLoadedFromCache();
    }
}
