<?php

/**
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

/**
 * Controller for curriculum importing.
 */
class Controller extends Base
{
    /**
     * Constructor
     *
     * @param Node\Mapper           $nodeMapper
     * @param Source\Mapper         $sourceMapper
     * @param Source\BrowserFactory $browserFactory
     */
    public function __construct(
        private Node\Mapper $nodeMapper,
        private Source\Mapper $sourceMapper,
        private Source\BrowserFactory $browserFactory,
    ) {
    }

    /**
     * Controller action for viewing the import page.
     */
    public function browse()
    {
        // A list of source objects
        // Only show ones with an import class, though.
        $this->response->content->set(
            [
                'title'    => 'Curriculum Import',
                'template' => '@curriculumImport/browse.twig',
                'data'     => [
                    'sourceList' => $this->sourceMapper->findImportable(),
                ],
            ]
        );
    }

    /**
     * Controller action for creating a curriculum.
     */
    public function create()
    {
        // Refetch outcomes
        $post   = $this->request->post;
        $source = $this->sourceMapper->findById($post['id']);

        // Stuff the source into a factory and use that to build list of levels
        $browser = $this->browserFactory->build($source);

        $thisArea  = null;
        $thisLevel = null;

        // Find which area/level was selected
        $areas = $browser->getLearningAreas();
        foreach ($areas as $area) {
            if ($area->getReference() === $post['area']) {
                $thisArea = $area;
                break;
            }
        }
        $levels = $browser->getLearningLevels();
        foreach ($levels as $level) {
            if ($level->getReference() === $post['level']) {
                $thisLevel = $level;
                break;
            }
        }

        // And save the outcomes
        // (Save a root node, and save everything as a child of that.)
        $code = 200;
        $data = [];
        if ($thisArea instanceof LearningArea\Model && $thisLevel instanceof LearningLevel\Model) {
            try {
                $rootNode = $this->nodeMapper->findOneByColumn('reference', $thisArea->getReference(), ["deleted" => false]);
            } catch (\Schoolbox\Mapper\Exception $e) {
                $rootNode = $this->nodeMapper->createModel();
                $rootNode
                    ->setName($thisArea->getName())
                    ->setReference($thisArea->getReference());
            }
            $rootNode->setChildren($browser->getOutcomes($thisArea, $thisLevel));
            $this->nodeMapper->saveTree($rootNode);
            $outcomes = new Collection();
            $outcomes->add($rootNode);
            // All the handling code cares about is the root node's ID
            $data['outcomes'][0] = ['id' => $rootNode->getId()];
        } else {
            $code            = 400;
            $data['message'] = 'Area or level not found';
        }

        // JSON something back to me
        $this->response->status->setCode($code);
        $this->response->content->set($this->buildJsonOutput($data));
    }

    /**
     * Controller action for loading the list of learning levels for a given
     * source (in request->query[id]).
     */
    public function learningLevels()
    {
        $code = 200;
        $data = [];
        try {
            $source = $this->sourceMapper->findById($this->request->query['id']);
            // Stuff this into a factory and use that to build a list of levels
            $browser = $this->browserFactory->build($source);
            // Try to fetch levels and areas asynchronously
            $data['levels'] = $browser->getLearningLevels();
            $data['areas']  = $browser->getLearningAreas();
        } catch (Mapper\Exception $e) {
            $code = 400;
        } catch (\InvalidArgumentException $e) {
            $code = 500;
        }
        $this->response->status->setCode($code);
        $this->response->content->set(
            [
                'format' => 'json',
                'data'   => $data,
            ]
        );
    }

    /**
     * Controller action for loading the list of learning levels for a given
     * source (in request->query[id]).
     */
    public function outcomes()
    {
        $code = 200;
        $data = [];
        try {
            $source = $this->sourceMapper->findById($this->request->query['id']);

            // Stuff this into a factory and use that to build a list of levels
            $browser = $this->browserFactory->build($source);

            // Create level/area for searching for an outcome
            $areaRef  = $this->request->query['area'];
            $levelRef = $this->request->query['level'];
            $level    = new LearningLevel\Model();
            $area     = new LearningArea\Model();
            $level->setReference($levelRef);
            $area->setReference($areaRef);

            $data['outcomes'] = $browser->getOutcomes($area, $level);
        } catch (Mapper\Exception $e) {
            $code = 400;
        } catch (\InvalidArgumentException $e) {
            $code = 500;
        }
        $this->response->status->setCode($code);
        $this->response->content->set(
            [
                'format' => 'json',
                'data'   => $data,
            ]
        );
    }
}
