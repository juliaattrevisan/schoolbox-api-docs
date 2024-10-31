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
use Schoolbox\Curriculum\Reports\Translator\CourseStructureOnly as CourseStructureTranslator;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as CourseUnitSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Twig\Error\Error as TwigError;
use ValueError;
use Crell\ApiProblem\ApiProblem;

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
        $response = [];
        $this->response = $this->responseDependentOnRequest(
            '@curriculumreports/index.twig',
            $response
        );
    }


    public function generateCoverage(): void
    {
        $query = $this->requestQuery;
        $courseId = $query->get('courseId');
        $nodeId = $query->get('nodeId');
        $limit = $query->getInt('limit', Cursor::LIMIT_DEFAULT);
        $encodedCursor = $query->get('cursor');

        //Making sure both courseId and nodeId are given in the query
        $courseId = is_array($courseId) ? $courseId : explode(',', $courseId ?? '');
        $nodeId = is_array($nodeId) ? $nodeId : explode(',', $nodeId ?? '');

        if (empty(array_filter($courseId))) {
            $problem = (new ApiProblem("courseId must be defined"))
                ->setStatus(400)
                ->asArray();
            $this->response = $this->responseAsJson($problem)->withStatus(400);
            return;
        }

        if (empty(array_filter($nodeId))) {
            $problem = (new ApiProblem("nodeId must be defined"))
                ->setStatus(400)
                ->asArray();
            $this->response = $this->responseAsJson($problem)->withStatus(400);
            return;
        }

        //Early exit if limit is not of type int
        if (null === $limit) {
            $problem = (new ApiProblem("Limit must be an positive integer"))
                ->setStatus(400)
                ->asArray();
            $this->response = $this->responseAsJson($problem)->withStatus(400);
            return;
        }

        try {
            $cursor = new Cursor(
                $this->curriculumMapper,
                $courseId,
                $nodeId,
                $limit,
            );

            $data = $cursor->retrieveResults($encodedCursor);
        } catch (\Exception | ValueError $e) {
            $problem = (new ApiProblem($e->getMessage()))
                ->setStatus(400)
                ->asArray();
            $this->response = $this->responseAsJson($problem)->withStatus(400);
            return;
        }

        $usageCount = $cursor->retrieveUsageCount();

        $courseSpecs =
            $this->courseSpecificationMapper->findByColumn('id', $courseId);

        $courseUnitSpecs = [];
        array_map(
            function ($id) use (&$courseUnitSpecs) {
                $courseUnitSpecs[$id] =
                    $this->courseUnitSpecificationMapper
                        ->findByColumn('course_specification_id', $id)
                        ->reindex();
            },
            $courseId
        );

        $unitTranslator = new UnitAssessmentUsageTranslator(
            $this->routeGenerator,
            $this->authorizer,
            $this->getUser(),
        );

        $courseUsageTranslator = new CourseUsageTranslator(
            $this->routeGenerator,
            $this->authorizer,
            $this->getUser(),
            $unitTranslator,
            $courseUnitSpecs,
        );

        $reportTranslator = new ModelToArrayTranslator(
            $this->routeGenerator,
            $this->authorizer,
            $this->getUser(),
            $courseUsageTranslator,
            $courseSpecs,
            $usageCount
        );

        $structureTranslator = new CourseStructureTranslator(
            $this->routeGenerator,
            $this->authorizer,
            $this->getUser(),
            $courseUnitSpecs
        );

        $usage = $data->map($reportTranslator);

        $structure = $courseSpecs->map($structureTranslator);

        $response =  [
            'data' => [
                'courses' => $structure,
                'nodes' => $usage,
            ],
            'metadata' => [
                'count' => $cursor->retrieveResultCount(),
                'cursor' => [
                    'current' => $encodedCursor ?? $cursor->current($data),
                    'next' => (count($data) < $limit) ? null : $cursor->next($data),
                ]
            ]
        ];

        $this->response = $this->responseAsJson(
            $response
        );
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
