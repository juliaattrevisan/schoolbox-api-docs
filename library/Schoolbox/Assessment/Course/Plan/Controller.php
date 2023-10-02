<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Plan;

use Alaress\Web\Controller\Base;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\Translator\UnitAssessments;
use Schoolbox\Learning\Course\DataModel\Specification\Model as CourseSpecification;
use Schoolbox\Assessment\Rubric\DataModel\Indicator\Mapper as RubricIndicatorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Textbox\Mapper as TextboxMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Twig\Error\Error;

class Controller extends Base
{
    /**
     * Controller constructor.
     *
     * @param ErrorMapper $errorMapper
     * @param UserMapper  $userMapper
     */
    public function __construct(private AssessmentMapperProxy $assessmentMapper, private RubricIndicatorMapper $rubricIndicatorMapper)
    {
    }


    /**
     * Get the Course/Unit Planner
     *
     * @param CourseSpecification $course
     * @throws Error
     */
    public function getPlan(CourseSpecification $course): void
    {
        // TODO: Inject the things...
        $textboxMapper = TextboxMapper::getInstance();
        $unitAssessmentsTranslator = new UnitAssessments($this->routeGenerator, $this->getUser());
        $units = [];
        /**
         * for each unit, generate the data...
         */
        foreach ($course->getUnits() as $unit) {
            /**
             * { // UNIT
             * "id": 6,
             * "title": "Romeo and Juliet",
             * "url": "/homepage/88",
             * "learningOutcomes": "",
             * "successCriteria": "",
             * "outcomes": "",
             * "activities": [
             * {
             * "id": 446,
             * "title": "due work 1",
             * "assessmentType": "dueWork",
             * "course": "Year 9 English",
             * "unit": "Romeo and Juliet",
             * "projectName": "Project 1",
             * "_links": {
             * "status": "\/learning\/assessments\/446\/13"
             * },
             * "assessments": null
             * }
             * ],
             * "rubricCapabilities": "",
             * "curriculumCodes": ""
             * }
             */
            // unit title / info
            $unitOut = [
                'id' => $unit->getId(),
                'title' => $unit->getTitle(),
                'homepage' => "/homepage/" . $unit->getFolder()->getId(),
            ];

            // grab rubric info
            $unitOut['rubricCapabilities'] = $this->rubricIndicatorMapper->findForFolder($unit->getFolder()->getId());

            /**
             * text boxes
             */
            $textboxes = $textboxMapper->findUsage($unit->getFolder()->getId());
            $unitOut['textboxes'] = $textboxes;

            // activities
            $options = ['order' => ['sequence' => 'ASC']];
            $assessments = $this->assessmentMapper->findAllByUnitSpecificationId($unit->getId(), $options);
            $unitOut['activities'] = $assessments->map($unitAssessmentsTranslator);

            // curriculum
            // build 
            $unitOut['curriculumCodes'] = [];

            $units[] = $unitOut;
        }

        $this->response = $this->responseAsJson($units);
    return;
//        $this->response = $this->responseFromTemplate('@coursePlan/index.twig', ['course' => $course, 'units' => $units], 'Unit Planner');
    }
}
