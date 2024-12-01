<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Curriculum\Reports;

use Alaress\Web\Controller\Base;
use Schoolbox\Collection;
use Schoolbox\Curriculum\Node\Mapper as CurriculumMapper;
use Schoolbox\Curriculum\Reports\Translator\Counts\ModelToArray as ModelToArrayTranslator;
use Schoolbox\Curriculum\Reports\Translator\Counts\UnitAssessmentUsage as UnitAssessmentUsageTranslator;
use Schoolbox\Curriculum\Reports\Translator\Counts\CourseUsage as CourseUsageTranslator;
use Schoolbox\Curriculum\Reports\Translator\Counts\CourseStructureOnly as CourseStructureTranslator;
use Schoolbox\Curriculum\Reports\Translator\Usage\DetailedUsage;
use Schoolbox\Curriculum\Reports\Translator\Usage\InUseRubricUsage;
use Schoolbox\Curriculum\Reports\Translator\Usage\RubricUsage;
use Schoolbox\GenericCollection;
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
        $encodedCursor = $query->get('cursor');

        // Making sure both courseId and nodeId are given in the query
        $courseIds = $this->extractCourseIdsFromQuery();
        if (empty($courseIds)) {
            $problem = (new ApiProblem("courseId must be defined"))
                ->setStatus(400)
                ->asArray();
            $this->response = $this->responseAsJson($problem)->withStatus(400);
            return;
        }

        $nodeIds = $this->extractNodeIdsFromQuery();
        if (empty($nodeIds)) {
            $problem = (new ApiProblem("nodeId must be defined"))
                ->setStatus(400)
                ->asArray();
            $this->response = $this->responseAsJson($problem)->withStatus(400);
            return;
        }

        //Early exit if limit is not of type int
        $limit = $query->getInt('limit', Cursor::LIMIT_DEFAULT);
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
                $courseIds,
                $nodeIds,
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

        $courseSpecs     = $this->courseSpecificationMapper->findByColumn('id', $courseIds);
        $courseUnitSpecs = $this->findCourseUnitSpecs($courseIds);
        $usage           = $this->loadUsageStructure($courseSpecs, $courseUnitSpecs, $data, $cursor->retrieveUsageCount());
        $structure       = $this->loadCourseStructure($courseSpecs, $courseUnitSpecs);

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
     * @return void
     */
    public function exportCoverage(): void
    {
        $courseIds = $this->extractCourseIdsFromQuery();
        $nodeIds   = $this->extractNodeIdsFromQuery();

        $cursor = new Cursor(
            $this->curriculumMapper,
            $courseIds,
            $nodeIds,
            Cursor::LIMIT_DEFAULT,
        );
        $data = $cursor->retrieveResults(null);

        // Supporting data
        $courseSpecs     = $this->courseSpecificationMapper->findByColumn('id', $courseIds);
        $courseUnitSpecs = $this->findCourseUnitSpecs($courseIds);
        $usage           = $this->loadUsageStructure($courseSpecs, $courseUnitSpecs, $data, $cursor->retrieveUsageCount());
        $structure       = $this->loadCourseStructure($courseSpecs, $courseUnitSpecs);

        // Now process $usage and $structure into a CSV

        $filename = sprintf(
            "curriculum-coverage-report.csv",
        );

        $fp = fopen('php://temp', 'w');

        $this->response = $this->responseFromStream(new \GuzzleHttp\Psr7\Stream($fp), $filename, 'text/csv; charset=utf-8');

        $this->outputHeaderRows($fp, $structure);
        foreach ($usage as $node) {
            $this->outputCurriculumNodeRows($fp, $structure, $node);
        }
    }

    /**
     * Output the headers for a set of courses and units as a set of CSV rows.
     *
     * @param resource   $fp        The file pointer to output to.
     * @param Collection $structure The data structure which contains the
     *                              courses and units to output.
     * @return void
     */
    private function outputHeaderRows($fp, Collection $structure): void
    {
        $headerRows    = [''];
        $subheaderRows = ['Curriculum'];
        foreach ($structure as $course) {
            $headerRows[]    = 'Course';
            $subheaderRows[] = $course['name'];
            $unitRowOutput   = false;
            foreach ($course['units'] as $unit) {
                $headerRows[]    = $unitRowOutput ? '' : 'Unit';
                $subheaderRows[] = $unit['name'];
                $unitRowOutput   = true;
            }
        }
        fputcsv($fp, $headerRows);
        fputcsv($fp, $subheaderRows);
    }

    /**
     * Output a curriculum node and its children in a set of CSV rows.
     *
     * @param resource   $fp        The file pointer to output to.
     * @param Collection $structure The data structure which contains the
     *                              courses and units to output.
     * @param array      $usage     The data structure which contains the usages
     *                              to output.
     *                              This also contains the course and unit
     *                              structure, but not in a reliable order.
     * @param int        $depth     The current depth of the curriculum node
     *                              traversal: required for controlling the
     *                              output of the curriculum node.
     * @return void
     */
    private function outputCurriculumNodeRows($fp, Collection $structure, array $usage, int $depth = 0): void
    {
        $curriculumName = $usage['name'];
        if ($depth > 0) {
            $curriculumName = sprintf('%s %s', str_repeat('*', $depth), $curriculumName);
        }
        $row = [$curriculumName];

        // Not assuming that courses in usage are in same order as in structure:
        // instead, iterate the structure and extract usages from `$usage`
        foreach ($structure as $course) {
            $courseUsage = $usage['usage']['courses']->filter(fn ($c) => $course['id'] === $c['id'])->first();
            $row[]       = $courseUsage['usage']['course']['count'] ?? 0;
            foreach ($course['units'] as $unit) {
                // Calculate item counts here, dependent on selected filters
                $unitUsage = $courseUsage['units']->filter(fn ($u) => $unit['id'] === $u['id'])->first();
                $row[]     = $unitUsage['usage']['unit']['count'] ?? 0;
            }
        }
        fputcsv($fp, $row);
        foreach ($usage['children'] ?? [] as $childNode) {
            $this->outputCurriculumNodeRows($fp, $structure, $childNode, $depth + 1);
        }
    }

    /**
     * Extract the course IDs from the query string.
     *
     * @return array
     */
    private function extractCourseIdsFromQuery(): array
    {
        return $this->formatQueryIds($this->requestQuery->get('courseId'));
    }

    /**
     * Extract the curriculum node IDs from the query string.
     *
     * @return array
     */
    private function extractNodeIdsFromQuery(): array
    {
        return $this->formatQueryIds($this->requestQuery->get('nodeId'));
    }

    /**
     * Find course unit specs, given a set of course IDs.
     *
     * @param array $courseIds
     * @return array
     */
    private function findCourseUnitSpecs(array $courseIds): array
    {
        $courseUnitSpecs = [];
        array_map(
            function ($id) use (&$courseUnitSpecs) {
                $courseUnitSpecs[$id] = $this->helper->filterUserPermission(
                    $this->courseUnitSpecificationMapper->findByColumn('course_specification_id', $id, [
                        'order' => ['f.sequence' => 'DESC']
                    ]),
                    $this->getUser()
                );
            },
            $courseIds,
        );
        return $courseUnitSpecs;
    }

    /**
     * Load the course specs into a structure suitable for output and CSV
     * processing.
     *
     * @param GenericCollection $courseSpecs
     * @param array             $courseUnitSpecs
     * @return Collection
     */
    private function loadCourseStructure(GenericCollection $courseSpecs, array $courseUnitSpecs): Collection
    {
        $structureTranslator = new CourseStructureTranslator(
            $this->routeGenerator,
            $this->authorizer,
            $this->getUser(),
            $courseUnitSpecs,
            new FolderTranslator(0)
        );
        return $courseSpecs->map($structureTranslator);
    }

    /**
     * Load the usage specs into a structure suitable for output and CSV
     * processing.
     *
     * @param GenericCollection $courseSpecs
     * @param array             $courseUnitSpecs
     * @param Collection        $data
     * @param array             $usageCount
     * @return Collection
     */
    private function loadUsageStructure(GenericCollection $courseSpecs, array $courseUnitSpecs, Collection $data, array $usageCount): Collection
    {
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


        return $data->map($reportTranslator);
    }
}
