<?php
/**
 * Base class for curriculum browsers.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 
namespace Schoolbox\Curriculum\Source;

abstract class BrowserAbstract implements BrowserInterface
{
    /**
     * @var Model
     */
    protected $source;

    /**
     * Constructor
     *
     * @param Model $source The curriculum source to use to build a curriculum
     */
    public function __construct(Model $source)
    {
        $this->setSource($source);
        $this->setup();
    }

    /**
     * Setup hook for post-construct instantiation
     */
    protected function setup()
    {
    }

    /**
     * @return Model
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * @param  Model $source
     * @return $this
     */
    public function setSource($source)
    {
        $this->source = $source;
        return $this;
    }
}
