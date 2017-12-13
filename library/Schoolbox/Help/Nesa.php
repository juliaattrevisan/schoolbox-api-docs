<?php
/**
 * Nesa
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Help;

use Schoolbox\Assessment\Component\SubmissionBox\Rubric;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Course\DataModel\Implementation\Mapper as CourseImplementationMapper;

class Nesa
{

    /**
     * @var array
     */
    private $groups;

    /**
     * @var array
     */
    private $criteria;

    /**
     * @var Rubric\RubricMapper
     */
    private $rubricMapper;

    /**
     * @var AssessmentMapperProxy
     */
    private $assessmentMapperProxy;

    /**
     * @var CourseImplementationMapper
     */
    private $courseImplementationMapper;

    /**
     * Nesa constructor.
     *
     * @param array                      $groups   an array of identifiers for Rubric Groups (Capabilities)
     * @param array                      $criteria an array of identifiers for Rubric Criteria (Indicators)
     * @param Rubric\RubricMapper        $rubricMapper
     * @param AssessmentMapperProxy      $assessmentMapperProxy
     * @param CourseImplementationMapper $courseImplementationMapper
     */
    public function __construct(
        array $groups,
        array $criteria,
        Rubric\RubricMapper $rubricMapper,
        AssessmentMapperProxy $assessmentMapperProxy,
        CourseImplementationMapper $courseImplementationMapper
    ) {
        $this->groups = $groups;
        $this->criteria = $criteria;
        $this->rubricMapper = $rubricMapper;
        $this->assessmentMapperProxy = $assessmentMapperProxy;
        $this->courseImplementationMapper = $courseImplementationMapper;
    }
}