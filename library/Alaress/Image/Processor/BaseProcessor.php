<?php
/**
 * Alaress_Image_Processor_Abstract
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Image\Processor;

use Alaress\Image\Cache;
use Alaress\Image\Cache\CacheInterface;
use Alaress\Image\Filetype;
use Alaress\Image\Transformation\Transformation;

class BaseProcessor
{
    /**
     * @var string
     */
    protected $imageId = null;

    /**
     * Cache class to use
     *
     * @var CacheInterface
	 */
    protected $cache = null;

    /**
     * A factory for building processors, replaces $processorStrategyPrefix.
     *
     * @var Strategy\FactoryInterface
     */
    protected $processorFactory;

    /**
     * @var Strategy\StrategyInterface
     */
    protected $processorStrategy = null;

    /**
     * A factory for building file types, replaces $filetypeStrategyPrefix.
     *
     * @var Filetype\FactoryInterface
     */
    protected $filetypeFactory;

    /**
     * @var Filetype\FiletypeInterface
     */
    protected $filetypeStrategy = null;

    /**
     * Image being manipulated
     *
     * @var string
	 */
    protected $image;

    /**
     * A list of all the transformations
     *
     * @var Transformation
	 */
    protected $transformations = null;

    /* I never ask those questions; I make those decisions. --Ryan
	 */

    /**
     * Path to a cached transformation
     *
     * @var string
     */
    protected $filepath = '';

    /**
     * Set image id
     *
     * @param int $imageId
     * @return static
     */
    protected function setImageId($imageId)
    {
        $this->imageId = $imageId;
        return $this;
    }

    /**
     * Get image id
     *
     * @return int
     */
    protected function getImageId()
    {
        return $this->imageId;
    }

    /**
     * Set processing strategy
     *
     * @param Filetype\FiletypeInterface $filetypeStrategy
     * @return static
     */
    protected function setFiletypeStrategy($filetypeStrategy)
    {
        $this->filetypeStrategy = $filetypeStrategy;
        return $this;
    }

    /**
     * Get processing strategy
     *
     * @return Filetype\FiletypeInterface
     */
    protected function getFiletypeStrategy()
    {
        return $this->filetypeStrategy;
    }

    /**
     * Set processor strategy
     *
     * @param Strategy\StrategyInterface $processorStrategy
     * @return static
     */
    protected function setProcessorStrategy($processorStrategy)
    {
        $this->processorStrategy = $processorStrategy;
        return $this;
    }

    /**
     * @return Filetype\FactoryInterface
     */
    public function getFiletypeFactory()
    {
        return $this->filetypeFactory;
    }

    /**
     * @param  Filetype\FactoryInterface $filetypeFactory
     *
     * @return $this
     */
    public function setFiletypeFactory($filetypeFactory)
    {
        $this->filetypeFactory = $filetypeFactory;
        return $this;
    }

    /**
     * Get processor strategy
     *
     * @return Strategy\StrategyInterface
     */
    protected function getProcessorStrategy()
    {
        return $this->processorStrategy;
    }

    /**
     * @return Strategy\FactoryInterface
     */
    public function getProcessorFactory()
    {
        return $this->processorFactory;
    }

    /**
     * @param  Strategy\FactoryInterface $processorFactory
     *
     * @return $this
     */
    public function setProcessorFactory($processorFactory)
    {
        $this->processorFactory = $processorFactory;
        return $this;
    }

    /**
     * Set image resource being manipulated
     *
     * @param $image
     * @return static
     */
    protected function setImage($image)
    {
        $this->image = $image;
        return $this;
    }

    /**
     * Get image resource being manipulated
     *
     * @return mixed
     */
    protected function getImage()
    {
        return $this->image;
    }

    /**
     * Set cache
     *
     * @param Cache\CacheInterface $cache
     * @return static
     */
    protected function setCache($cache)
    {
        $this->cache = $cache;
        return $this;
    }

    /**
     * Get cache
     *
     * @return Cache\CacheInterface
     */
    protected function getCache()
    {
        return $this->cache;
    }

    /**
     * Set transformations
     *
     * @param Transformation $transformations
     * @return static
     */
    protected function setTransformations($transformations)
    {
        $this->transformations = $transformations;
        return $this;
    }

    /**
     * Get transformations
     *
     * @return Transformation
     */
    protected function getTransformations()
    {
        return $this->transformations;
    }

    /**
     * Set filepath
     *
     * @param string $filepath
     * @return static
     */
    protected function setFilepath($filepath)
    {
        $this->filepath = $filepath;
        return $this;
    }

    /**
     * Get filepath
     *
     * @return string
     */
    protected function getFilepath()
    {
        return $this->filepath;
    }
}
