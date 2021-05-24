<?php

/**
 * Base class for curriculum browsers.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Curriculum\Source;

use Schoolbox\Collection;
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

    /**
     * @return LearningArea\Mapper
     */
    public function getAreaMapper()
    {
        return $this->areaMapper;
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
     * @return LearningLevel\Mapper
     */
    public function getLevelMapper()
    {
        return $this->levelMapper;
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
     * @return Node\Mapper
     */
    public function getNodeMapper()
    {
        return $this->nodeMapper;
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

    /**
     * Retrieves pre-saved learning levels for this browser's source.
     *
     * @return Collection|LearningLevel\Model[]
     */
    public function getSavedLearningLevels()
    {
        return $this->getLevelMapper()->findByColumn(
            'curriculum_source_id',
            $this->getSource()->getId(),
            array(
                'order' => array(
                    'order' => 'asc',
                    'name'  => 'asc',
                ),
            )
        );
    }

    /**
     * Retrieves pre-saved learning areas for this browser's source.
     *
     * @return Collection|LearningArea\Model[]
     */
    public function getSavedLearningAreas()
    {
        return $this->getAreaMapper()->findByColumn(
            'curriculum_source_id',
            $this->getSource()->getId(),
            array(
                'order' => array(
                    'order' => 'asc',
                    'name'  => 'asc',
                ),
            )
        );
    }

    /**
     * Retrieves pre-saved outcome node graph for the given area and level.
     *
     * @param LearningArea\Model  $area
     * @param LearningLevel\Model $level
     *
     * @return Collection|Node\Model[]
     */
    public function getSavedOutcomes(LearningArea\Model $area, LearningLevel\Model $level)
    {
        return $this->getNodeMapper()->getCollectionAsTree(
            $this->getNodeMapper()->findByColumnList(
                array(
                    'curriculum_learning_level_id' => $level->getId(),
                    'curriculum_learning_area_id'  => $area->getId(),
                ),
                array(
                    'order' => array(
                        'curriculum_node_parent_id' => 'asc',
                        'order'                     => 'asc',
                        'name'                      => 'asc',
                    ),
                )
            )
        );
    }
}
