<?php
/**
 * Alaress_Image_Processor_Abstract
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Alaress_Image_Processor_Abstract
{
    protected $imageId = null;

    /* Cache class to use
	 */
    protected $cache = null;

    /**
     * A factory for building processors, replaces $processorStrategyPrefix.
     *
     * @var \Alaress\Image\Processor\Strategy\FactoryInterface
     */
    protected $processorFactory;

    /* Strategies for image manipulation
	 */
    protected $processorStrategyPrefix = 'Alaress\\Image\\Rewrite\\Imagine\\Processor\\Strategy\\';

    /**
    * @var Alaress_Image_Processor_Strategy_Interface
    */
    protected $processorStrategy = null;

    /**
     * A factory for building file types, replaces $filetypeStrategyPrefix.
     *
     * @var \Alaress\Image\Filetype\FactoryInterface
     */
    protected $filetypeFactory;

    /* Strategies for polymorphic image output
	 */
    protected $filetypeStrategyPrefix = 'Alaress\\Image\\Rewrite\\Imagine\\Filetype\\';

    /**
    * @var Alaress_Image_Filetype_Interface
    */
    protected $filetypeStrategy = null;

    /* Image being manipulated
	 */
    protected $image;

    /* A list of all the transformations
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
    * @param $imageId
    */
    protected function setImageId($imageId)
    {
        $this->imageId = $imageId;
    }

    /**
    * Get image id
    *
    * @return mixed
    */
    protected function getImageId()
    {
        return $this->imageId;
    }

    /**
    * Set processing strategy
    *
    * @param Alaress_Image_Filetype_Interface $filetypeStrategy
    */
    protected function setFiletypeStrategy($filetypeStrategy)
    {
        $this->filetypeStrategy = $filetypeStrategy;
    }

    /**
    * Get processing strategy
    *
    * @return Alaress_Image_Filetype_Interface
    */
    protected function getFiletypeStrategy()
    {
        return $this->filetypeStrategy;
    }

    /**
    * Set filetype strategy prefix for class names
    *
    * @param $filetypeStrategyPrefix
    */
    protected function setFiletypeStrategyPrefix($filetypeStrategyPrefix)
    {
        $this->filetypeStrategyPrefix = $filetypeStrategyPrefix;
    }

    /**
    * Get filetype strategy prefix for class names
    *
    * @return string
    */
    protected function getFiletypeStrategyPrefix()
    {
        return $this->filetypeStrategyPrefix;
    }

    /**
    * Set processor strategy
    *
    * @param Alaress_Image_Processor_Strategy_Interface $processorStrategy
    */
    protected function setProcessorStrategy($processorStrategy)
    {
        $this->processorStrategy = $processorStrategy;
    }

    /**
     * @return \Alaress\Image\Filetype\FactoryInterface
     */
    public function getFiletypeFactory()
    {
        return $this->filetypeFactory;
    }

    /**
     * @param  \Alaress\Image\Filetype\FactoryInterface $filetypeFactory
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
    * @return Alaress_Image_Processor_Strategy_Interface
    */
    protected function getProcessorStrategy()
    {
        return $this->processorStrategy;
    }


    /**
    * Set processor strategy prefix
    *
    * @param $processorStrategyPrefix
    */
    protected function setProcessorStrategyPrefix($processorStrategyPrefix)
    {
        $this->processorStrategyPrefix = $processorStrategyPrefix;
    }

    /**
    * Get processor strategy prefix
    *
    * @return string
    */
    protected function getProcessorStrategyPrefix()
    {
        return $this->processorStrategyPrefix;
    }

    /**
     * @return \Alaress\Image\Processor\Strategy\FactoryInterface
     */
    public function getProcessorFactory()
    {
        return $this->processorFactory;
    }

    /**
     * @param  \Alaress\Image\Processor\Strategy\FactoryInterface $processorFactory
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
    */
    protected function setImage($image)
    {
        $this->image = $image;
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
    * @param $cache
    */
    protected function setCache($cache)
    {
        $this->cache = $cache;
    }

    /**
    * Get cache
    *
    * @return Alaress_Image_Cache_Interface
    */
    protected function getCache()
    {
        return $this->cache;
    }

    /**
    * Set transformations
    *
    * @param $transformations
    */
    protected function setTransformations($transformations)
    {
        $this->transformations = $transformations;
    }

    /**
    * Get transformations
    *
    * @return array
    */
    protected function getTransformations()
    {
        return $this->transformations;
    }

    /**
    * Set filepath
    *
    * @param string $filepath
    */
    protected function setFilepath($filepath)
    {
        $this->filepath = $filepath;
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
