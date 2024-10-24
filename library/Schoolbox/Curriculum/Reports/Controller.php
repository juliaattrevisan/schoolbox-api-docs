<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Curriculum\Reports;

use Alaress\Web\Controller\Base;
use Schoolbox\Curriculum\Node\Mapper as CurriculumMapper;
use Schoolbox\Curriculum\Reports\Translator\ModelToArray as ModelToArrayTranslator;
use Schoolbox\Curriculum\Reports\Translator\UnitAssessmentUsage as UnitAssessmentUsageTranslator;
use Schoolbox\Curriculum\Reports\Translator\CourseUsage as CourseUsageTranslator;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as CourseUnitSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Twig\Error\Error as TwigError;

class Controller extends Base
{
    /**
     * Controller constructor.
     *
     * @param CurriculumMapper              $curriculumMapper
     * @param CourseSpecificationMapper     $courseSpecificationMapper
     * @param CourseUnitSpecificationMapper $courseUnitSpecificationMapper
     */
    public function __construct(
        private CurriculumMapper $curriculumMapper,
        private CourseSpecificationMapper $courseSpecificationMapper,
        private CourseUnitSpecificationMapper $courseUnitSpecificationMapper,
    ) {
    }


    /**
     * Get the coverage report
     *
     * @return void
     * @throws TwigError
     */
    public function getCoverage(): void
    {
        $query = $this->requestQuery;
        $courseId = $query->get('courseId');
        $nodeId = $query->get('nodeId');
        $limit = $query->get('limit', Cursor::LIMIT_DEFAULT);
        $encodedCursor = $query->get('encodedCursor');

        $courseId =
            is_array($courseId) ? $courseId : explode(',', $courseId);
        $nodeId =
            is_array($nodeId) ? $nodeId : explode(',', $nodeId);


        $cursor = new Cursor(
            $this->curriculumMapper,
            $courseId,
            $nodeId,
            $limit,
        );

        $data = $cursor->retrieveResults($encodedCursor);

        $usageCount = $cursor->retrieveUsageCount();

        $courseSpecs =
            $this->courseSpecificationMapper->findByColumn('id', $courseId);

        $courseUnitSpecs = [];
        array_map(
            function ($id) use (&$courseUnitSpecs) {
                $courseUnitSpecs[$id] =
                    $this->courseUnitSpecificationMapper
                        ->findByColumn('course_specification_id', $id);
            },
            $courseId
        );

        $unitTranslator = new UnitAssessmentUsageTranslator(
            $this->routeGenerator,
            $this->authorizer,
            $this->getUser(),
            $usageCount
        );

        $courseUsageTranslator = new CourseUsageTranslator(
            $this->routeGenerator,
            $this->authorizer,
            $this->getUser(),
            $unitTranslator,
            $courseUnitSpecs,
            $usageCount
        );

        $reportTranslator = new ModelToArrayTranslator(
            $this->routeGenerator,
            $this->authorizer,
            $this->getUser(),
            $courseUsageTranslator,
            $courseSpecs,
        );

        $response = array_map(
            fn($node) => $node->map(
                $reportTranslator,
            ),
            $data['nodes']
        );


//        $this->response = $this->responseDependentOnRequest('@curriculumreports/index.twig', [
        $this->response = $this->responseAsJson([
            'data' => $response,
            'metadata' => [
                'count' => $cursor->retrieveResultCount(),
                'cursor' => [
                    'current' => $encodedCursor ?? null,
                    'next' => null,
                ]
            ]
        ]);
    }


    /**
     * fetch the coverage report
     *
     * @return void
     * @throws TwigError
     */
    public function postCoverage(): void
    {
        $this->response = $this->responseFromTemplate('@curriculumreports/index.twig', ['var' => 'POST']);
    }
}
