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
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Course\DataModel\Implementation\Mapper as CourseImplementationMapper;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Mapper as UnitImplementationMapper;

class Nesa
{

    /**
     * Teacher Course
     * Course specification id
     */
    public const COURSE_SPEC_ID = 6;

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
     * @var UnitImplementationMapper
     */
    private $unitImplementationMapper;


    /**
     * Nesa constructor.
     *
     * @param array                      $groups   an array of identifiers for Rubric Groups (Capabilities)
     * @param array                      $criteria an array of identifiers for Rubric Criteria (Indicators)
     * @param Rubric\RubricMapper        $rubricMapper
     * @param AssessmentMapperProxy      $assessmentMapperProxy
     * @param CourseImplementationMapper $courseImplementationMapper
     * @param UnitImplementationMapper   $unitImplementationMapper
     */
    public function __construct(
        array $groups,
        array $criteria,
        Rubric\RubricMapper $rubricMapper,
        AssessmentMapperProxy $assessmentMapperProxy,
        CourseImplementationMapper $courseImplementationMapper,
        UnitImplementationMapper $unitImplementationMapper
    ) {
        $this->groups = $groups;
        $this->criteria = $criteria;
        $this->rubricMapper = $rubricMapper;
        $this->assessmentMapperProxy = $assessmentMapperProxy;
        $this->courseImplementationMapper = $courseImplementationMapper;
        $this->unitImplementationMapper = $unitImplementationMapper;
    }


    /**
     * Find all the Unit Implementations for Teacher Course
     *
     * @param int $courseSpecId id of course spec for Teacher Course
     * @return Collection|\Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model[]
     */
    private function findUnitImplementations(int $courseSpecId)
    {
        $allUnitImplementations = new Collection();

        $allCourseImplementations = $this->courseImplementationMapper->findAllByCourseSpecificationId($courseSpecId);

        foreach($allCourseImplementations as $currentCourseImplementation) {
            $implementations = $this->unitImplementationMapper->findAllByCourseId($currentCourseImplementation->getId());

            $allUnitImplementations = $allUnitImplementations->merge($implementations);
        }

        return $allUnitImplementations;
    }



    public function generate()
    {
        $allUnitImplementations = $this->findUnitImplementations(self::COURSE_SPEC_ID);

        $allAssessments = new Collection();

        foreach ($allUnitImplementations as $currentUnitImplementation) {
            $assessments = $this->assessmentMapperProxy->findAllByUnitImplementationId($currentUnitImplementation->getId());

            $allAssessments = $allAssessments->merge($assessments);
        }

//        new \Alaress_Probe($allAssessments);
    }
}