<?php

/**
 * Report export admin page
 *
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\ReportExport;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use Schoolbox\Admin\Settings\FieldStrategyFactory;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\CampusMapper;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\WorkType\Mapper as WorkTypeMapper;
use Schoolbox\Mapper\Exception\NotFound;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\UserBulkMapper;
use Schoolbox\UserMapper;
use Schoolbox\YearLevel\Mapper as YearLevelMapper;

class Module extends BaseModule
{
    protected $basename = 'adminReportExport';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes()
    {
        /* Any staff can see the form
         */
        $getPerms = $postPerms = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser()
            )
        );

        /* Presumably this will one day include a special subset of stuff
         * whose duty will be to manufacture these reports. This staff
         * member(s) might not be superusers, nor teachers; their ability
         * to be here wil be based on a Service Permission that might
         * not currently exist
         */
        //new Rule\HasServicePermission(UserAccess::SERVICE_NEWS, UserAccess::ACCESS_FULL)

        $preloadReport = function ($params) {
            return $this->preloadReport($params);
        };

        $preloadReportWithStudent = function ($params) {
            return $this->preloadReportWithStudent($params);
        };

        $this->register('GET', '', 'getList', $getPerms);
        $this->register('GET', '/create', 'getCreate', $getPerms);
        $this->register('POST', '/create', 'postCreate', $getPerms);
        $this->register('GET', '/worktypes', 'getWorkTypes', $getPerms);
        $this->register('GET', '/terms', 'getTerms', $getPerms);
        $this->register('GET', '/yearlevels', 'getYearLevels', $getPerms);
        $this->register('GET', '/campuses', 'getCampuses', $getPerms);
        $this->register('GET', '/{reportId}/update', 'getUpdate', $getPerms, $preloadReport);
        $this->register('POST', '/{reportId}/update', 'postUpdate', $postPerms, $preloadReport);
        $this->register('GET', '/{reportId}/duplicate', 'getDuplicate', $getPerms, $preloadReport);
        $this->register('POST', '/{reportId}/delete', 'postDelete', $postPerms, $preloadReport);
        $this->register('GET', '/{reportId}/generate', 'getGenerate', $postPerms, $preloadReport);
        $this->register('GET', '/{reportId}/studentReport/{studentId}', 'getStudentReport', $getPerms, $preloadReportWithStudent);
    }


    /**
     * Preload a summative report
     *
     * @param array $params
     * @return array
     * @throws NotFound
     * @throws \InvalidArgumentException
     */
    private function preloadReport(array $params): array
    {
        if (\array_key_exists('report', $params)) {
            $report = $params['report'];
        } else {
            try {
                $report = $this->getMapperFactory()->get(Mapper::class)->findById($params['reportId']);
            } catch (\Schoolbox\Mapper\Exception $e) {
                throw new NotFound('Unknown summative report.', 0, $e);
            }
        }
        return [
            'report' => $report
        ];
    }


    /**
     * Preload a summative report with a student
     *
     * @param array $params
     * @return array
     * @throws NotFound
     * @throws \InvalidArgumentException
     */
    private function preloadReportWithStudent(array $params): array
    {
        if (\array_key_exists('report', $params)) {
            $report = $params['report'];
        } else {
            try {
                $report = $this->getMapperFactory()->get(Mapper::class)->findById($params['reportId']);
            } catch (\Schoolbox\Mapper\Exception $e) {
                throw new NotFound('Unknown summative report.', 0, $e);
            }
        }

        if (\array_key_exists('student', $params)) {
            $student = $params['student'];
        } else {
            try {
                $student = $this->getMapperFactory()->get(\Schoolbox\UserMapper::class)->findById($params['studentId']);
            } catch (\Schoolbox\Mapper\Exception $e) {
                throw new NotFound('Unknown student.', 0, $e);
            }
        }

        return [
            'report' => $report,
            'student' => $student
        ];
    }


    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(ErrorMapper::class),
            $this->getMapperFactory()->get(Mapper::class),
            $this->getMapperFactory()->get(UserMapper::class),
            $this->getMapperFactory()->get(TermMapper::class),
            $this->getMapperFactory()->get(YearLevelMapper::class),
            $this->getMapperFactory()->get(CampusMapper::class),
            $this->getMapperFactory()->get(WorkTypeMapper::class),
            $this->getMapperFactory()->get(FolderMapper::class),
            $this->injector->get('assessmentDataSource'),
            $this->getMapperFactory()->get(RubricDataSource::class),
            $this->getMapperFactory()->get(Access::class),
            $this->getMapperFactory()->get(UserBulkMapper::class),
            $this->getMapperFactory()->get(\Schoolbox\RoleMapper::class),
            $this->getMapperFactory()->get(ConfigMapper::class),
            new FieldStrategyFactory($this->getMapperFactory(), new CollectorFactory()),
            $this->injector->get('moduleCache.rebuilder'),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
