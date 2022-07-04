<?php
/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\ReportExport\Payload;

use Aura\Router\Exception\RouteNotFound;
use Aura\Router\Router;
use Laminas\Uri\UriInterface;
use Schoolbox\Assessment\Term\Mapper;
use Schoolbox\Role;
use Schoolbox\UserBulkMapper;

/**
 * Generate a set of (filename, url[]) tuples
 */
class Url
{
    /**
     * Max length for filenames: anything longer causes issues on upload.
     */
    public const FILENAME_MAX_LENGTH = 250;


    /**
     * The Academic Periods are specified with this array key
     */
    private const TERMS = 'terms';


    private Router $router;
    private UriInterface $baseUrl;
    private \Schoolbox\Assessment\Term\Mapper $termMapper;
    private \Schoolbox\RoleMapper $roleMapper;
    private \Schoolbox\UserBulkMapper $userBulkMapper;
    private \Schoolbox\UserMapper $userMapper;


    /**
     * Constructor.
     *
     * @param Router                $router
     * @param UriInterface          $baseUrl
     * @param Mapper                $termMapper
     * @param \Schoolbox\RoleMapper $roleMapper
     * @param UserBulkMapper        $userBulkMapper
     * @param \Schoolbox\UserMapper $userMapper
     */
    public function __construct(
        Router $router,
        UriInterface $baseUrl,
        \Schoolbox\Assessment\Term\Mapper $termMapper,
        \Schoolbox\RoleMapper $roleMapper,
        \Schoolbox\UserBulkMapper $userBulkMapper,
        \Schoolbox\UserMapper $userMapper
    ) {
        $this->router = $router;
        $this->baseUrl = $baseUrl;
        $this->termMapper = $termMapper;
        $this->roleMapper = $roleMapper;
        $this->userBulkMapper = $userBulkMapper;
        $this->userMapper = $userMapper;
    }


    /**
     * @param \Schoolbox\Admin\ReportExport\Model $report
     * @return array
     * @throws \JsonException
     * @throws \Laminas\Uri\Exception\InvalidUriException
     */
    public function generate(\Schoolbox\Admin\ReportExport\Model $report): array
    {
        $optionsPayload = $report->decode();

        /* Decide what this report's filename should be
         * It's based on the sanitised name of the report
         */
        $filename = \Scrub::filename(trim((string)$report->getTitle()));

        /* Base url for report generation
         */
        $urlBase = $this->baseUrl;

        /* Generate a list of student identifiers:
         * demographic students + additional students
         *
         * Anyone in the selected years (demographic students)
         * plus anyone in the selected campuses (demographic students)
         * plus the specific guys you chose (additional students)
         *
         * This is accumulated via the bulkModify search function, believe it or not
         */
        $demographicStudentsIds = [];
        $additionalStudentIds = $optionsPayload['students'];

        /* Year Levels and Campus student demographics
         *
         * Check the config to see what Role Type should have come out in the zip
         * This lets you prevent staff users from getting a pdf when you only wanted students
         */
        if (\count($optionsPayload['yearLevels']) || \count($optionsPayload['campuses'])) {
            $roleIds = $this->roleMapper->findByColumn('group_type', Role::STUDENT)->indexBy('id')->keys();

            $demographicStudents = $this->userBulkMapper->bulkModifySearch(
                empty($optionsPayload['yearLevels']) ? [ UserBulkMapper::ANY ] : $optionsPayload['yearLevels'],
                $roleIds,
                UserBulkMapper::ANY,
                empty($optionsPayload['campuses']) ? [ UserBulkMapper::ANY ] : $optionsPayload['campuses'],
                UserBulkMapper::ANY
            );

            $demographicStudentsIds = array_column($demographicStudents, 'id');
        }

        /* Combine the list of students and blow away any null/zero/falsey values
         */
        $studentIds = array_filter(array_unique(array_merge($demographicStudentsIds, $additionalStudentIds)));

        /* Fetch hydrated instances
         */
        $reportStudents = $this->userMapper->findByColumn('id', $studentIds);

        /* Start generating report paths and any associated warnings
         */
        $reportConfigs = [];
        $warnings = [];

        foreach ($reportStudents as $student) {
            if (isset($optionsPayload['viewAs']) && $optionsPayload['viewAs'] === Role::PARENT) {
                $student->loadParents();

                /* Student doesn't have parents, but we tried to make reports
                 * formatted specifically for parents; skip them
                 */
                if ($student->getParents()->count() === 0) {
                    $warnings[] = sprintf(
                        'Unable to generate report for student id %d (%s). No parents.',
                        $student->getId(),
                        $student->getExternalId()
                    );

                    continue;
                }
            }

            /* See if we can make a url for this student's report
             */
            try {
                $reportPath = $this->router->generate('adminReportExport.getStudentReport', [
                    'reportId' => $report->getId(),
                    'report' => $report,
                    'studentId' => $student->getId(),
                    'student' => $student,
                ]);

                if ($reportPath) {
                    /* Pacify any weird or wonky data in people's names and external ids
                     * to prevent hacking the filesystem somehow
                     */
                    $externalId = \Scrub::filename($student->getExternalId() ?: 'unknown');
                    $fullName   = \Scrub::filename($student->getFullName());

                    /* Concat the names
                     * Report names were mandatory in the create/update screen
                     */
                    $reportFilename = mb_substr(sprintf(
                        '%s - %s',
                        $filename,
                        $fullName
                    ), 0, self::FILENAME_MAX_LENGTH, 'UTF-8');

                    /* Assemble paths/hierarchy in the zip
                     *
                     * The zip file contains the student's externalId as a folder
                     * and their report as a pdf inside that folder so you can see
                     * who owns which file
                     */
                    $reportConfig = [
                        'filename' => sprintf(
                            "%s/%s.pdf",
                            $externalId,
                            $reportFilename,
                        ),
                        'urls' => [
                            $urlBase->setPath($reportPath)->toString()
                        ],
                    ];

                    $reportConfigs[] = $reportConfig;
                }
            } catch (RouteNotFound $e) {
                /* noop
                 *
                 * Couldn't find the part for this student's report;
                 * just ignore these missing reports
                 */
            }
        }

        /* Return a (warnings, reportConfigs) tuple
         */
        return [
            'warnings' => $warnings,
            'reportConfigs' => $reportConfigs
        ];
    }


    /**
     * Convert the payload settings to a single serialized querystring
     * ...but why do we need this? we can get those settings from the report id
     *
     * @param \Schoolbox\Admin\ReportExport\Model $report
     * @return string
     * @throws \JsonException
     */
    public function buildQuerystring(\Schoolbox\Admin\ReportExport\Model $report): string
    {
        $optionsPayload = json_decode($report->getOptions(), true, 512, JSON_THROW_ON_ERROR);

        /* Bad data or couldn't decode, for some obscure reason
         * Kind of common with third parties, more so than us
         */
        if (!$optionsPayload) {
            return '';
        }

        /* Recursively serialize with prefix
         * Example $stack shown below
         *
                Array
                (
                    [0] => startDate=2015-01-01
                    [1] => endDate=2020-12-31
                    [2] => yearLevels=1,2,3
                    [3] => campuses=1
                    [4] => students=45,6,25
                    [5] => summaryActive=1
                    [6] => summaryHeading=werI am a cat
                    [7] => summaryWorkType=1,2,3,5,6,7,8,9,10,11
                    [8] => summaryOverallGrade=1
                    [9] => summaryComments=1
                    [10] => summaryRubrics=1
                    [11] => classActive=0
                    [12] => classWorkType=1,2,3,5,6,7,8,9,10,11,12
                    [13] => classOverallGrade=1
                    [14] => classLearningLevels=1
                    [15] => classCohort=1
                    [16] => classCapabilityGrades=1
                    [17] => classProgression=1
                    [18] => classComments=1
                    [19] => classRubrics=1
                )
         */
        $stack  = [];

        foreach ($optionsPayload as $key => $sectionValue) {
            if (self::TERMS === $key) {
                /* Term is specified with querystring params 'startDate' and 'endDate'
                 * These are the earliest and latest dates out of all the Academic
                 * Periods selected via the selectize
                 */
                $dates = $this->calculateDateRange(explode(',', $sectionValue));

                $stack[] = "startDate={$dates['startDate']}";
                $stack[] = "endDate={$dates['endDate']}";
            } else if (!is_array($sectionValue)) {
                /* Non-hierarchical
                 */
                $stack[] = "{$key}={$sectionValue}";
            } else {
                /* Summary and Class subsections
                 */
                foreach ($sectionValue as $subsectionKey => $subsectionValue) {
                    $stack[] = sprintf(
                        '%s%s=%s',
                        $key,
                        ucwords($subsectionKey),
                        is_array($subsectionValue) ? implode(',', $subsectionValue) : $subsectionValue
                    );
                }
            }
        }

        return implode('&', $stack);
    }


    /**
     * Find the total date range spanned by the all selected Academic Periods
     *
     * @param array $termIds
     * @return array
     */
    public function calculateDateRange(array $termIds): array {
        $startDate = null;
        $endDate = null;

        /* No terms specified? Use today's term's date range
         */
        if (!\count($termIds)) {
            $currentTerm = $this->termMapper->findForToday();

            return [
                'startDate' => $currentTerm->getStart()->getDatabaseDate(),
                'endDate' => $currentTerm->getDisplayFinish()->getDatabaseDate()
            ];
        }

        /* Otherwise traverse the set
         *
         * Find the earliest date and find the latest date
         * and use this as the date range
         */
        $terms = $this->termMapper->findByColumn('id', $termIds);

        foreach ($terms as $term) {
            if (!$startDate || $term->getStart()->lt($startDate)) {
                $startDate = $term->getStart();
            }

            if (!$endDate || $term->getDisplayFinish()->gt($endDate)) {
                $endDate = $term->getDisplayFinish();
            }
        }

        return [
            'startDate' => $startDate->getDatabaseDate(),
            'endDate' => $endDate->getDatabaseDate()
        ];
    }
}