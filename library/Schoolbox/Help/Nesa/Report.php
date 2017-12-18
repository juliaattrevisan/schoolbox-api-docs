<?php
/**
 * NESA Report
 *
 * Generate the NESA report data
 *
 * Used to view the browser version and also populates the csv
 * This way both outputs are derived from the same data source
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\Help\Nesa;

use Schoolbox\Assessment\Component\SubmissionBox\Rubric;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\UserMapper;

class Report
{
    /**
     * @var AssessmentMapperProxy
     */
    private $assessmentMapperProxy;

    /**
     * @var Rubric\RubricMapper
     */
    private $rubricMapper;

    /**
     * @var UserMapper
     */
    private $userMapper;

    /**
     * Report constructor.
     *
     * @param AssessmentMapperProxy          $assessmentMapperProxy
     * @param Rubric\RubricMapper            $rubricMapper
     * @param UserMapper                     $userMapper
     */
    public function __construct(
        AssessmentMapperProxy $assessmentMapperProxy,
        Rubric\RubricMapper $rubricMapper,
        UserMapper $userMapper
    ) {
        $this->assessmentMapperProxy = $assessmentMapperProxy;
        $this->rubricMapper = $rubricMapper;
        $this->userMapper = $userMapper;
    }

    /**
     * Generate report contents in a view-agnostic manner
     *
     * @param array    $states
     * @param int      $unaccreditedOnly
     * @param array    $indicators
     * @return array
     * @throws \Schoolbox\Mapper\Exception
     */
    public function generate(array $states, int $unaccreditedOnly, array $indicators)
    {
        $assessmentMapperProxy = $this->assessmentMapperProxy;

        /* Acquire NESA data for $folder and the chosen indicators
         */
        $dataSet = (new Acquisition(
            $states,
            $unaccreditedOnly,
            $indicators,
            $this->rubricMapper,
            $assessmentMapperProxy,
            $this->userMapper
        ))->generate();

        /* Process the $dataSet into a visual report
         */
        return (new Aggregation($dataSet))->generate();
    }
}