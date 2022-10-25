<?php

/**
 * Base class for curriculum browsers.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Curriculum\Source;

use Schoolbox\Curriculum\LearningArea;
use Schoolbox\Curriculum\LearningLevel;
use Schoolbox\Curriculum\Node;

abstract class BrowserAbstract implements BrowserInterface
{
    /**
     * @var Model
     */
    protected $source;

    /**
     * @var LearningArea\Mapper
     */
    protected $areaMapper;

    /**
     * @var LearningLevel\Mapper
     */
    protected $levelMapper;

    /**
     * @var Node\Mapper
     */
    protected $nodeMapper;

    /**
     * Constructor
     *
     * @param Model $source The curriculum source to use to build a curriculum
     */
    public function __construct(Model $source)
    {
        $this
            ->setSource($source)
            ->setAreaMapper(new LearningArea\Mapper())
            ->setLevelMapper(new LearningLevel\Mapper())
            ->setNodeMapper(new Node\Mapper());
        $this->setup();
    }

    /**
     * Setup hook for post-construct instantiation
     */
    protected function setup()
    {
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

    /**
     * @param  LearningArea\Mapper $areaMapper
     * @return $this
     */
    public function setAreaMapper($areaMapper)
    {
        $this->areaMapper = $areaMapper;
        return $this;
    }

    /**
     * @param  LearningLevel\Mapper $levelMapper
     * @return $this
     */
    public function setLevelMapper($levelMapper)
    {
        $this->levelMapper = $levelMapper;
        return $this;
    }

    /**
     * @param  Node\Mapper $nodeMapper
     * @return $this
     */
    public function setNodeMapper($nodeMapper)
    {
        $this->nodeMapper = $nodeMapper;
        return $this;
    }
}
