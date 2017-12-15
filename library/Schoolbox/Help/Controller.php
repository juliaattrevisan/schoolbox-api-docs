<?php
/**
 * help.schoolbox.com.au
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Help;

use Alaress\Web\Controller\Base;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric;
use Schoolbox\Error;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Schoolbox\Mapper\Exception as MapperException;

ini_set('max_execution_time', 300);

class Controller extends Base
{

    /**
     * @var Error\Mapper
     */
    private $errorMapper;

    /**
     * @var CourseSpecificationMapper
     */
    private $courseSpecificationMapper;

    /**
     * @var Rubric\RubricMapper
     */
    private $rubricMapper;


    /**
     * Controller constructor.
     * @param Error\Mapper               $errorMapper
     * @param CourseSpecificationMapper  $courseSpecificationMapper
     * @param Rubric\RubricMapper        $rubricMapper
     */
    public function __construct(
        Error\Mapper $errorMapper,
        CourseSpecificationMapper $courseSpecificationMapper,
        Rubric\RubricMapper $rubricMapper)
    {
        $this->errorMapper = $errorMapper;
        $this->courseSpecificationMapper = $courseSpecificationMapper;
        $this->rubricMapper = $rubricMapper;
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
        $courseSpecification = $this->courseSpecificationMapper->findById(Nesa\Acquisition::NESA_COURSE_SPEC_ID);

        $this->getResponse()->content->set($this->buildHtmlOutput('@help/selector.twig', [
            'course' => $courseSpecification,
            'descriptors' => (new Nesa\Selector(Nesa\Acquisition::NESA_COURSE_SPEC_ID))->generate()
        ]));
    }

    /**
     * NESA Report generation
     *
     * @throws \Schoolbox\Application\Exception\Exception
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function postNesa()
    {
        $start = microtime(true);

        /** @var AssessmentMapperProxy $assessmentMapperProxy */
        $assessmentMapperProxy = $this->getDi()->get('assessmentDataSource');

        /* Find all the folders that have users with Professional Development details
         */
        $prefilter = new Nesa\Prefilter();
        $involvement = $prefilter->findInvolvement();

        /* Acquire NESA data for $folder and the chosen indicators
         */
        $dataSet = (new Nesa\Acquisition(
            $involvement,
            $this->getRequest()->post['indicators'],
            $this->rubricMapper,
            $assessmentMapperProxy
        ))->generate();

        /* Process the $dataSet into a visual report
         */
        $report = (new Nesa\Aggregation($dataSet))->generate();

        $end = microtime(true);

        /* The usual output
         */
        $this->getResponse()->content->set($this->buildHtmlOutput('@help/report.twig', [
            'report' => $report,
            'duration' => round($end - $start),
            'indicators' => array_filter(array_unique($this->getRequest()->post['indicators']))
        ]));
    }
}