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
    public function __construct(private ErrorMapper $errorMapper, private UserMapper $userMapper)
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
        $rubricIndicatorMapper = RubricIndicatorMapper::getInstance();
        $textboxMapper = TextboxMapper::getInstance();
        $units = [];
        /**
         * for each unit, generate the data...
         */
        foreach ($course->getUnits() as $unit) {
            $unitOut = [];
            $unitOut['capabilities'] = $rubricIndicatorMapper->findForFolder($unit->getFolder()->getId());
            /**
             * text boxes
             */
            $textboxes = $textboxMapper->findUsage($unit->getFolder()->getId());
            $unitOut['textboxes'] = $textboxes;

            $units[] = $unitOut;
        }

        $this->response = $this->responseFromTemplate('@coursePlan/index.twig', ['course' => $course, 'units' => $units], 'Unit Planner');
    }
}
