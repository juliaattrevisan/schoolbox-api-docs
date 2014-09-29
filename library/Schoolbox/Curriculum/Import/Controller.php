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
use Schoolbox\Curriculum\LearningArea;
use Schoolbox\Curriculum\LearningLevel;
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

            $testArea  = new LearningArea\Model();
            $testArea->setReference($this->getRequest()->query['area']);
            $testLevel = new LearningLevel\Model();
            $testLevel->setReference($this->getRequest()->query['level']);
            $data['outcomes'] = $browser->getOutcomes($testArea, $testLevel);
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
