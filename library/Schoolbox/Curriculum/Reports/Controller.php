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
use Schoolbox\Curriculum\Reports\Translator\Counts\ModelToArray as ModelToArrayTranslator;
use Schoolbox\Curriculum\Reports\Translator\Counts\UnitAssessmentUsage as UnitAssessmentUsageTranslator;
use Schoolbox\Curriculum\Reports\Translator\Counts\CourseUsage as CourseUsageTranslator;
use Schoolbox\Curriculum\Reports\Translator\Counts\CourseStructureOnly as CourseStructureTranslator;
use Schoolbox\Curriculum\Reports\Translator\Usage\DetailedUsage;
use Schoolbox\Curriculum\Reports\Translator\Usage\InUseRubricUsage;
use Schoolbox\Curriculum\Reports\Translator\Usage\RubricUsage;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as CourseUnitSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;
use Schoolbox\Assessment\Rubric\DataModel\Capability\Mapper as RubricCapabilityMapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Translator\ModelToArray\LinkOnly as FolderTranslator;
use Schoolbox\Learning\Course\DataModel\Specification\Translator\ModelToArray as CourseSpecTranslator;
use Schoolbox\Curriculum\Api\Translator\ModelToArray as NodeTranslator;
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
     * @param Helper                        $helper
     */
    public function __construct(
        private CurriculumMapper $curriculumMapper,
        private CourseSpecificationMapper $courseSpecificationMapper,
        private CourseUnitSpecificationMapper $courseUnitSpecificationMapper,
        private Helper $helper,
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

        $courseId = $this->formatQueryIds($courseId);
        $nodeId = $this->formatQueryIds($nodeId);

        $courseSpecs = $this->courseSpecificationMapper->findByColumn('id', $courseId);
        $curriculumNodes = $this->curriculumMapper->findByColumn('id', $nodeId);

        $filteredCourse = $this->helper->filterUserPermission($courseSpecs, $this->getUser());
        $folderTranslator = new FolderTranslator(0);
        $courseTranslator = new CourseSpecTranslator(
            $this->routeGenerator,
            $this->authorizer,
            $this->getUser(),
            $folderTranslator
        );

        $courses = $filteredCourse->map($courseTranslator);

        $nodeTranslator = new NodeTranslator(
            $this->routeGenerator,
            $this->authorizer,
            $this->getUser(),
            0,
            0,
            null
        );

        $nodes = $curriculumNodes->map($nodeTranslator);

        $this->response = $this->responseFromTemplate(
            '@curriculumreports/index.twig',
            [
                'selectedCourses' => $courses,
                'selectedNodes' => $nodes,
            ]
        );
    }


    /**
     * Return the counts for the curriculum coverage
     *
     * @return void
     */
    public function generateCoverage(): void
    {
        $query = $this->requestQuery;
        $courseId = $query->get('courseId');
        $nodeId = $query->get('nodeId');
        $limit = $query->getInt('limit', Cursor::LIMIT_DEFAULT);
        $encodedCursor = $query->get('cursor');

        //Making sure both courseId and nodeId are given in the query
        $courseId = $this->formatQueryIds($courseId);
        $nodeId = $this->formatQueryIds($nodeId);

        if (empty($courseId)) {
            $problem = (new ApiProblem("courseId must be defined"))
                ->setStatus(400)
                ->asArray();
            $this->response = $this->responseAsJson($problem)->withStatus(400);
            return;
        }

        if (empty($nodeId)) {
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
                    $this->helper->filterUserPermission(
                        $this->courseUnitSpecificationMapper->findByColumn('course_specification_id', $id, [
                            'order' => ['f.sequence' => 'DESC']
                        ]),
                        $this->getUser()
                    );
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
            $courseUnitSpecs,
            new FolderTranslator(0)
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
     * Pay load for curriculum node usage within the selected course and unit
     *
     * @return void
     */
    public function getCoverageUsage(): void
    {
        $query = $this->requestQuery;
        $courseId = $query->getInt('courseId');
        $unitId = $query->getInt('unitId');
        $nodeId = $query->getInt('nodeId');

        if (null === $courseId || $courseId <= 0) {
            $problem = (new ApiProblem("courseId must be defined and greater than 0"))
                ->setStatus(400)
                ->asArray();
            $this->response = $this->responseAsJson($problem)->withStatus(400);
            return;
        }
        if (null === $unitId || $unitId <= 0) {
            $problem = (new ApiProblem("unitId must be defined and greater than 0"))
                ->setStatus(400)
                ->asArray();
            $this->response = $this->responseAsJson($problem)->withStatus(400);
            return;
        }
        if (null === $nodeId || $nodeId <= 0) {
            $problem = (new ApiProblem("nodeId must be defined and greater than 0"))
                ->setStatus(400)
                ->asArray();
            $this->response = $this->responseAsJson($problem)->withStatus(400);
            return;
        }

        try {
            $courseUnitSpec = $this->courseUnitSpecificationMapper->findOneByColumnList(
                [
                    'course_specification_id' => $courseId,
                    'folder_id' => $unitId,
                ]
            );

            $result = $this->helper->getUsage(
                $nodeId,
                $courseId,
                $unitId
            );

            $models = $this->helper->findModelsForResults($result);
        } catch (\Exception $e) {
            $problem = (new ApiProblem($e->getMessage()))
                ->setStatus(400)
                ->asArray();
            $this->response = $this->responseAsJson($problem)->withStatus(400);
            return;
        }

        $rubricTranslator = new RubricUsage(
            $this->routeGenerator,
            $this->authorizer,
            $this->getUser(),
        );

        $inUseRubricTranslator = new InUseRubricUsage(
            $this->routeGenerator,
            $this->authorizer,
            $this->getUser(),
            $models,
            $rubricTranslator,
        );

        $translator = new DetailedUsage(
            $this->routeGenerator,
            $this->authorizer,
            $this->getUser(),
            $models,
            $rubricTranslator,
            $inUseRubricTranslator,
        );

        $response = $translator($courseUnitSpec, $result);

        $this->response = $this->responseAsJson(
            $response
        );
    }

    /**
     * Format and filter the query parameters before retrieving results
     *
     * @param string|array|null $ids
     * @return array
     */
    private function formatQueryIds(null|string|array $ids): array
    {
        return array_filter(
            is_array($ids) ? $ids : explode(',', $ids ?? ''),
            fn($id) => $id > 0
        );
    }

    /**
     * Export the current coverage report
     *
     * @param string|array|null $ids
     * @return array
     */
    public function exportCoverage(): void
    {
        $filename = sprintf(
            "test.csv",
        );

        $fp = fopen('php://temp', 'w');

        $this->response = $this->responseFromStream(new \GuzzleHttp\Psr7\Stream($fp), $filename, 'text/csv; charset=utf-8');

        // output headers
        fputcsv($fp, ['test']);

        fputcsv($fp, ['test2']);
    }
}
