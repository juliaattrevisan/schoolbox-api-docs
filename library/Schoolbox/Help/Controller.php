<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Help;

use Alaress\Web\Controller\Base;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric;
use Schoolbox\Error;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Course\DataModel\Implementation\Mapper as CourseImplementationMapper;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Mapper as UnitImplementationMapper;
use Schoolbox\Mapper\Exception as MapperException;

class Controller extends Base
{

    /**
     * @var Error\Mapper
     */
    private $errorMapper;

    /**
     * Controller constructor.
     * @param Error\Mapper $errorMapper
     */
    public function __construct(Error\Mapper $errorMapper)
    {
        $this->errorMapper = $errorMapper;
    }

    /**
     * NESA Report
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getNesa()
    {
        /** @var AssessmentMapperProxy $assessmentMapperProxy */
        $assessmentMapperProxy = $this->getDi()->get('assessmentDataSource');

        $nesa = (new Nesa(
            6,
            [21, 22],
            [120, 121],
            [91, 92, 93],
            Rubric\RubricMapper::getInstance(),
            $assessmentMapperProxy,
            CourseImplementationMapper::getInstance(),
            UnitImplementationMapper::getInstance()
        ))->generate();

        $this->getResponse()->content->set($this->buildHtmlOutput('@help/nesa.twig', [

        ]));
    }
}
