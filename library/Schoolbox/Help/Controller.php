<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Help;

use Alaress\Web\Controller\Base;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\CriterionMapper;
use Schoolbox\Error;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Course\DataModel\Implementation\Mapper as CourseImplementationMapper;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Mapper as UnitImplementationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
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

        /* Course Specification Id
         * This comes from the /course/modify screen, as is an integer in the url
         */
        $courseSpecificationId = 6;

        /* Find all the unit specifications. We'll use those to figure out what Rubric Criteria records exist
         * for the course spec, then accumulate all their ids
         */
        $allUnitSpecifications = UnitSpecificationMapper::getInstance()->findAllByCourseId($courseSpecificationId);

        /* Find all the criteria used in each unit specification
         *
         * These are cloned per course import so we need to specifically get all usages of the original
         * There seems to be no relation between the cloned criteria and their original progenitor
         */
        $ids = [];

        foreach ($allUnitSpecifications as $currentUnitSpecification) {
            $ids[] = $currentUnitSpecification->getFolder()->getId();
        }

        $allSpecCrits = CriterionMapper::getInstance()->findByColumn('parent_id', $ids);

        /* Now find all their usages in the folders that contain a course implementation of $courseSpecificationId
         */
        $ids = [];

        foreach ($allSpecCrits as $currentCrit) {
            $ids[] = $currentCrit->getGrouping();
        }

        $allCriterionUsages = CriterionMapper::getInstance()->findByColumn('grouping', $ids);

        /* Now we can find all the descriptors that were in play, in all the implemented units of
         * $courseSpecificationId
         */
        $ids = [];

        foreach ($allSpecCrits as $currentCrit) {
            $ids[] = $currentCrit->getId();
        }

        $allDescriptorUsages = Rubric\DescriptorMapper::getInstance()->findByColumn('criterion_id', $ids);

//        new \Alaress_Probe($allSpecCrits, $allCriterionUsages, $allDescriptorUsages);



        $nesa = (new Nesa(
            $courseSpecificationId,
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
