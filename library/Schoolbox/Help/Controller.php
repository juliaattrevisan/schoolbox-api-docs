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
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Schoolbox\Mapper\Exception as MapperException;

ini_set('max_execution_time', 300);
ini_set('memory_limit', -1);

class Controller extends Base
{

    /**
     * Course Specification Id
     * This comes from the /course/modify screen, as is an integer in the url
     */
    public const NESA_COURSE_SPEC_ID = 23;

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
     * NESA Report rubric selection
     *
     * Selection of capabilities, indicators, descriptors which are used to configure a report output
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getNesa()
    {
        $courseSpecification = CourseSpecificationMapper::getInstance()->findById(self::NESA_COURSE_SPEC_ID);

        $this->getResponse()->content->set($this->buildHtmlOutput('@help/selector.twig', [
            'course' => $courseSpecification,
            'descriptors' => (new Nesa\Selector(self::NESA_COURSE_SPEC_ID))->generate()
        ]));
    }


    /**
     * NESA Report generation
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function postNesa()
    {
        $folderId = 1858;

        /** @var AssessmentMapperProxy $assessmentMapperProxy */
        $assessmentMapperProxy = $this->getDi()->get('assessmentDataSource');

        $report = (new Nesa\Report(
            self::NESA_COURSE_SPEC_ID,
            $this->getRequest()->post['indicators'],
            Rubric\RubricMapper::getInstance(),
            $assessmentMapperProxy
        ))->generate($folderId);

        $this->getResponse()->content->set($this->buildHtmlOutput('@help/report.twig', [
            'report' => $report,
            'indicators' => array_filter(array_unique($this->getRequest()->post['indicators']))
        ]));
    }
}