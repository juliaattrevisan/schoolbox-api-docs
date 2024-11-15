<?php

/**
 * Summative Report admin page
 *
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\SummativeReport;

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
use Schoolbox\Learning\Course\DataModel\Implementation\Mapper as ImplementationMapper;
use Schoolbox\Learning\DueWork\StateMapper;
use Schoolbox\Mapper\Exception\NotFound;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\UserBulkMapper;
use Schoolbox\UserMapper;
use Schoolbox\YearLevel\Mapper as YearLevelMapper;

class Module extends BaseModule
{
    protected $basename = 'adminSummativeReport';

    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes()
    {
        /* Any staff can see the form
         */
        $perms = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasServicePermission(UserAccess::SERVICE_LMS, UserAccess::ACCESS_FULL)
            )
        );

        $preloadReport = function ($params) {
            return $this->preloadReport($params);
        };

        $preloadReportWithStudent = function ($params) {
            return $this->preloadReportWithStudent($params);
        };

        $this->register('GET', '', 'getList', $perms);
        $this->register('GET', '/create', 'getCreate', $perms);
        $this->register('POST', '/create', 'postCreate', $perms);
        $this->register('GET', '/worktypes', 'getWorkTypes', $perms);
        $this->register('GET', '/terms', 'getTerms', $perms);
        $this->register('GET', '/yearlevels', 'getYearLevels', $perms);
        $this->register('GET', '/campuses', 'getCampuses', $perms);
        $this->register('GET', '/{reportId}/update', 'getUpdate', $perms, $preloadReport);
        $this->register('POST', '/{reportId}/update', 'postUpdate', $perms, $preloadReport);
        $this->register('GET', '/{reportId}/duplicate', 'getDuplicate', $perms, $preloadReport);
        $this->register('POST', '/{reportId}/delete', 'postDelete', $perms, $preloadReport);
        $this->register('GET', '/{reportId}/generate', 'getGenerate', $perms, $preloadReport);
        $this->register('GET', '/{reportId}/status/{key}', 'getStatus', $perms);
        $this->register('GET', '/{reportId}/preview', 'getPreview', $perms, $preloadReport);
        $this->register('GET', '/{reportId}/studentReport/{studentId}', 'getStudentReport', $perms, $preloadReportWithStudent);
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
            $this->getMapperFactory()->get(StateMapper::class),
            $this->injector->get('assessmentDataSource'),
            $this->getMapperFactory()->get(RubricDataSource::class),
            $this->getMapperFactory()->get(Access::class),
            new \Schoolbox\UserBulkMapper($this->getMapperFactory()->get(\Schoolbox\UserMapper::class)),
            $this->getMapperFactory()->get(\Schoolbox\RoleMapper::class),
            $this->getMapperFactory()->get(ConfigMapper::class),
            new FieldStrategyFactory($this->getMapperFactory(), new CollectorFactory()),
            $this->injector->get('moduleCache.rebuilder'),
            $this->injector->get('oidc.cache'),
            new \Schoolbox\External\User(
                $this->injector->get('config')->get('external_type'),
                new \External_Class(),
                new \External_User(),
                $this->getMapperFactory()->get(UserMapper::class)
            ),
            $this->getMapperFactory()->get(ImplementationMapper::class)
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
