<?php

/**
 * Controller for curriculum importing.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Curriculum\Import;

use Alaress\Web\Controller\Base;
use Schoolbox\Collection;
use Schoolbox\Curriculum\LearningArea;
use Schoolbox\Curriculum\LearningLevel;
use Schoolbox\Curriculum\Node;
use Schoolbox\Curriculum\Source;
use Schoolbox\Mapper;

class Controller extends Base
{
    /**
     * @var Source\Mapper
     */
    protected $sourceMapper;

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
     * Controller action for viewing the import page.
     */
    public function browse()
    {
        // A list of source objects
        // Only show ones with an import class, though.
        $this->getResponse()->content->set(
            array(
                'title'    => 'Curriculum Import',
                'template' => '@curriculumImport/browse.twig',
                'data'     => array(
                    'sourceList' => $this->getSourceMapper()->findImportable(),
                ),
            )
        );
    }

    /**
     * Controller action for creating a curriculum.
     */
    public function create()
    {
        // Refetch outcomes
        $post   = $this->getRequest()->post;
        $source = $this->getSourceMapper()->findById($post['id']);

        // Stuff the source into a factory and use that to build list of levels
        $factory = new Source\BrowserFactory();
        $browser = $factory->build($source);

        $thisArea  = null;
        $thisLevel = null;

        // Save areas, if they're new
        $areas    = $browser->getLearningAreas();
        $newAreas = new Collection();
        foreach ($areas as $i => $area) {
            if ($area->getReference() === $post['area']) {
                $thisArea = $area;
            }
            if (!$area->getId()) {
                try {
                    $this->getAreaMapper()->findOneByColumnList(
                        [
                            'curriculum_source_id' => $area->getSource()->getId(),
                            'name'                 => $area->getName(),
                        ]
                    );
                } catch (Mapper\Exception $e) {
                    $area->setOrder($i);
                    $newAreas->add($area);
                }
            }
        }

        // Save levels, if they're new
        $levels    = $browser->getLearningLevels();
        $newLevels = new Collection();
        foreach ($levels as $i => $level) {
            if ($level->getReference() === $post['level']) {
                $thisLevel = $level;
            }
            if (!$level->getId()) {
                $level->setOrder($i);
                $newLevels->add($level);
            }
        }

        // Save new areas and levels
        $this->getAreaMapper()->save($newAreas);
        $this->getLevelMapper()->save($newLevels);

        // And save the outcomes
        // (Save a root node, and save everything as a child of that.)
        $code = 200;
        $data = array();
        if ($thisArea instanceof LearningArea\Model && $thisLevel instanceof LearningLevel\Model) {
            /** @var Node\Model $rootNode */
            try {
                $rootNode = $this->getNodeMapper()->findOneByColumn('reference', $thisArea->getReference(), ["deleted" => false]);
            } catch (\Schoolbox\Mapper\Exception $e) {
                $rootNode = $this->getNodeMapper()->createModel();
                $rootNode
                    ->setLearningLevel($thisLevel)
                    ->setLearningArea($thisArea)
                    ->setName($thisArea->getName())
                    ->setReference($thisArea->getReference());
            }
            $rootNode->setChildren($browser->getOutcomes($thisArea, $thisLevel));
            $this->getNodeMapper()->saveTree($rootNode);
            $outcomes = new Collection();
            $outcomes->add($rootNode);
            // All the handling code cares about is the root node's ID
            $data['outcomes'][0] = ['id' => $rootNode->getId()];
        } else {
            $code = 400;
            $data['message'] = 'Area or level not found';
        }

        // JSON something back to me
        $this->getResponse()->status->setCode($code);
        $this->getResponse()->content->set($this->buildJsonOutput($data));
    }

    /**
     * Controller action for loading the list of learning levels for a given
     * source (in request->query[id]).
     */
    public function learningLevels()
    {
        $code = 200;
        $data = array();
        try {
            $source = $this->getSourceMapper()->findById($this->getRequest()->query['id']);
            // Stuff this into a factory and use that to build a list of levels
            $factory = new Source\BrowserFactory();
            $browser = $factory->build($source);
            // Try to fetch levels and areas asynchronously
            $data['levels'] = $browser->getLearningLevels();
            $data['areas']  = $browser->getLearningAreas();
        } catch (Mapper\Exception $e) {
            $code = 400;
        } catch (\InvalidArgumentException $e) {
            $code = 500;
        }
        $this->getResponse()->status->setCode($code);
        $this->getResponse()->content->set(
            array(
                'format' => 'json',
                'data'   => $data,
            )
        );
    }

    /**
     * Controller action for loading the list of learning levels for a given
     * source (in request->query[id]).
     */
    public function outcomes()
    {
        $code = 200;
        $data = array();
        try {
            $source = $this->getSourceMapper()->findById($this->getRequest()->query['id']);

            // Stuff this into a factory and use that to build a list of levels
            $factory = new Source\BrowserFactory();
            $browser = $factory->build($source);

            // Try fetching an existing area and level.
            // Otherwise, create a virtual one.
            $areaRef  = $this->getRequest()->query['area'];
            $levelRef = $this->getRequest()->query['level'];
            try {
                $area = $this->getAreaMapper()->findOneByColumn('reference', $areaRef);
            } catch (Mapper\Exception $e) {
                $area = new LearningArea\Model();
                $area->setReference($areaRef);
            }
            try {
                $level = $this->getLevelMapper()->findOneByColumn('reference', $levelRef);
            } catch (Mapper\Exception $e) {
                $level = new LearningLevel\Model();
                $level->setReference($levelRef);
            }

            $data['outcomes'] = $browser->getOutcomes($area, $level);
        } catch (Mapper\Exception $e) {
            $code = 400;
        } catch (\InvalidArgumentException $e) {
            $code = 500;
        }
        $this->getResponse()->status->setCode($code);
        $this->getResponse()->content->set(
            array(
                'format' => 'json',
                'data'   => $data,
            )
        );
    }

    /**
     * @param  Source\Mapper $sourceMapper
     * @return $this
     */
    public function setSourceMapper(Source\Mapper $sourceMapper)
    {
        $this->sourceMapper = $sourceMapper;
        return $this;
    }

    /**
     * @return Source\Mapper
     */
    public function getSourceMapper()
    {
        return $this->sourceMapper;
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
    public function setAreaMapper(LearningArea\Mapper $areaMapper)
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
    public function setLevelMapper(LearningLevel\Mapper $levelMapper)
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
    public function setNodeMapper(Node\Mapper $nodeMapper)
    {
        $this->nodeMapper = $nodeMapper;
        return $this;
    }
}
