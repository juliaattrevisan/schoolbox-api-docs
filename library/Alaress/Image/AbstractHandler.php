<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Image;


use Alaress\Image\Cache\CacheInterface;
use Alaress\Image\Transformation\Transformation;
use Alaress\Image;
use Alaress\Image\Implementation\Imagine;

abstract class AbstractHandler
{
    /** @var Processor\Standard */
    protected $imageProcessor;


    /**
     *
     * @param CacheInterface                      $cache
     * @param Transformation                      $transformations
     * @param Filetype\FactoryInterface           $filetypeFactory
     * @param Processor\Strategy\FactoryInterface $processorFactory
     */
    protected function initProcessor(
        CacheInterface $cache,
        Transformation $transformations,
        Filetype\FactoryInterface $filetypeFactory = null,
        Processor\Strategy\FactoryInterface $processorFactory = null
    ) {
        $this->imageProcessor = new Processor\Standard(
            $cache,
            $transformations,
            $filetypeFactory ?: new Imagine\Filetype\Factory(),
            $processorFactory ?: new Imagine\Processor\Strategy\Factory()
        );
    }

    /**
     * @return void
     */
    public function stream()
    {
        $this->imageProcessor->load()->stream();
    }

} 