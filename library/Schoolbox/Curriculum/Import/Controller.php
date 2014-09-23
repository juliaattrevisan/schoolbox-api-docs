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
     * Controller action for loading the list of learning levels and areas for
     * a given source (in request->params[id]).
     */
    public function learningLevels()
    {
        $code = 200;
        try {
            $source = $this->getSourceMapper()->findById($this->getRequest()->params['id']);
            // Stuff this into a factory and use that to build a list of levels
        } catch (Mapper\Exception $e) {
            $code = 400;
        }
        $this->getResponse()->status->setCode($code);
        $this->getResponse()->content->set(
            array(
                'type' => 'json',
                'data' => array(
                    'levels' => $this->getSourceMapper()->findAll(),
                ),
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
