<?php
/**
 * Controller for curriculum importing.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
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
     * Controller action for viewing the import page.
     */
    public function browse()
    {
        // A list of source objects
        $this->getResponse()->content->set(
            array(
                'title'    => 'Curriculum Import',
                'template' => '@curriculumImport/browse.twig',
                'data'     => array(
                    'sourceList' => $this->getSourceMapper()->findAll(),
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
        $post = $this->getRequest()->post;
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
                $area->setOrder($i);
                $newAreas->add($area);
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
        $areaMapper = LearningArea\Mapper::getInstance();
        $areaMapper->save($newAreas);
        $levelMapper = LearningLevel\Mapper::getInstance();
        $levelMapper->save($newLevels);

        // And save the outcomes
        if ($thisArea instanceof LearningArea\Model && $thisLevel instanceof LearningLevel\Model) {
            $outcomes = $browser->getOutcomes($thisArea, $thisLevel);
            foreach ($outcomes as $outcome) {
                $outcome
                    ->setLearningLevel($thisLevel)
                    ->setLearningArea($thisArea)
                    ->setName($post['name']);
                Node\Mapper::getInstance()->saveTree($outcome);
            }
        }

        // JSON something back to me
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
                $area = LearningArea\Mapper::getInstance()->findOneByColumn('reference', $areaRef);
            } catch (Mapper\Exception $e) {
                $area = new LearningArea\Model();
                $area->setReference($areaRef);
            }
            try {
                $level = LearningLevel\Mapper::getInstance()->findOneByColumn('reference', $levelRef);
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
     * @param  \Schoolbox\Curriculum\Source\Mapper $sourceMapper
     * @return $this
     */
    public function setSourceMapper($sourceMapper)
    {
        $this->sourceMapper = $sourceMapper;
        return $this;
    }

    /**
     * @return \Schoolbox\Curriculum\Source\Mapper
     */
    public function getSourceMapper()
    {
        return $this->sourceMapper;
    }
}
