<?php
/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\ReportExport;

use Alaress\Web\Controller\Base;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\CampusMapper;
use Schoolbox\Learning\Assessment\WorkType\Mapper as WorkTypeMapper;
use Schoolbox\YearLevel\Mapper as YearLevelMapper;

/**
 * Class Controller
 *
 * Admin report export page
 *
 * @package Schoolbox\Admin\ReportExport
 */
class Controller extends Base
{

    private TermMapper $termMapper;

    private YearLevelMapper $yearLevelMapper;

    private CampusMapper $campusMapper;

    private WorkTypeMapper $workTypeMapper;

    /**
     * @param TermMapper      $termMapper
     * @param YearLevelMapper $yearLevelMapper
     * @param CampusMapper    $campusMapper
     * @param WorkTypeMapper  $workTypeMapper
     */
    public function __construct(
        TermMapper $termMapper,
        YearLevelMapper $yearLevelMapper,
        CampusMapper $campusMapper,
        WorkTypeMapper $workTypeMapper
    ) {
        $this->termMapper      = $termMapper;
        $this->yearLevelMapper = $yearLevelMapper;
        $this->campusMapper    = $campusMapper;
        $this->workTypeMapper  = $workTypeMapper;
    }

    /**
     * Landing page
     * @return void
     * @throws \Aura\Router\Exception\RouteNotFound
     * @api GET /adminv2/report-export
     */
    public function getIndex()
    {
        $summaryPageOptions = [
            [
                'id'   => 'showOverallGrades',
                'name' => 'Show overall grades',
            ],
        ];
        $classPageOptions   = [
            [
                'id'   => 'showLearningLevels',
                'name' => 'Show learning levels',
            ],
            [
                'id'   => 'showCohortResults',
                'name' => 'Show cohort / class results',
            ],
            [
                'id'   => 'showCapabilityGrades',
                'name' => 'Show capability grades',
            ],
            [
                'id'   => 'showProgression',
                'name' => 'Show progression',
            ],
            [
                'id'   => 'showOverallGrades',
                'name' => 'Show overall grades',
            ],
        ];
        $assessmentOptions  = [
            [
                'id'   => 'showGrades',
                'name' => 'Show grades',
            ],
            [
                'id'   => 'showComments',
                'name' => 'Show comments',
            ],
            [
                'id'   => 'showRubrics',
                'name' => 'Show rubrics',
            ],
        ];

        $this->getResponse()->content->set(
            [
                'template' => '@adminReportExport/index.twig',
                'data'     => [
                    'pageCurrent'        => $this->router->generate('adminReportExport.getIndex'),
                    'allBreadcrumbs'     => (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('adminReportExport.getIndex')),
                    'summaryPageOptions' => $summaryPageOptions,
                    'classPageOptions'   => $classPageOptions,
                    'assessmentOptions'  => $assessmentOptions,
                ],
            ]
        );
    }

    /**
     * Get terms for selectize
     * @return void
     * @api GET /adminv2/report-export/getTerms
     */
    public function getTerms(): void
    {
        $output = [];
        $terms  = $this->termMapper->findAll();
        foreach ($terms as $term) {
            $output[] = [
                'id'   => $term->getId(),
                'name' => $term->getTitle(),
            ];
        }
        $this->getResponse()->content->set($this->buildJsonOutput($output));
    }

    /**
     * Get year levels for selectize
     * @return void
     * @api GET /adminv2/report-export/getYearLevels
     */
    public function getYearLevels(): void
    {
        $output     = [['id' => 0, 'name' => 'No Year Level']];
        $yearLevels = $this->yearLevelMapper->findAll()->sortBy('sequence', 'ASC');
        foreach ($yearLevels as $yearLevel) {
            $output[] = [
                'id'   => $yearLevel->getId(),
                'name' => $yearLevel->getName(),
            ];
        }
        $this->getResponse()->content->set($this->buildJsonOutput($output));
    }

    /**
     * Get campuses for selectize
     * @return void
     * @api GET /adminv2/report-export/getCampuses
     */
    public function getCampuses(): void
    {
        $output   = [];
        $campuses = $this->campusMapper->findAll()->sortBy('sequence', 'ASC');
        foreach ($campuses as $campus) {
            $output[] = [
                'id'   => $campus->getId(),
                'name' => $campus->getName(),
            ];
        }
        $this->getResponse()->content->set($this->buildJsonOutput($output));
    }

    /**
     * Get work types for selectize
     * @return void
     * @api GET /adminv2/report-export/getWorkTypes
     */
    public function getWorkTypes(): void
    {
        $output    = [];
        $workTypes = $this->workTypeMapper->findAll()->sortBy('sequence', 'ASC');
        foreach ($workTypes as $workType) {
            $output[] = [
                'id'   => $workType->getId(),
                'name' => $workType->getName(),
            ];
        }
        $this->getResponse()->content->set($this->buildJsonOutput($output));
    }
}
