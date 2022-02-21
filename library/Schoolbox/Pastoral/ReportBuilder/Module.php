<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\ReportBuilder;

use Alaress\Authorizer\Rule;
use Alaress\Authorizer\Rule\HasServicePermission;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
use Alaress\Web\Router\PathMatcher;
use HouseMapper;
use Schoolbox\CampusMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Pastoral\Authorizer\Rule\IsReportOwner;
use Schoolbox\Pastoral\Report\Mapper as PastoralReportMapper;
use Schoolbox\Pastoral\Tag\Mapper as PastoralTagMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Role;
use Alaress\Twig\Loader\IChainLoader;

// These mappers are injected into controllers where required

class Module extends BaseModule
{
    /**
     * @var string $basename Module basename
     */
    protected $basename = 'pastoralReportBuilder';

    /**
     * {@inheritdoc}
     */
    protected function registerRoutes()
    {
        $permissionStaff =
            new Rule\All(
                new Rule\IsLoggedIn(),
                new Rule\Any(
                    new Rule\HasRolePermission(Role::PERMISSION_ACCESS_PASTORAL, Role::STAFF),
                    new Rule\All(
                        new Rule\HasRole(Role::STAFF),
                        new HasServicePermission(UserAccess::SERVICE_PASTORAL, UserAccess::ACCESS_READ)
                    )
                )
            );
        $permissionReportOwner = new Rule\All(new IsReportOwner(), $permissionStaff);

        $this->registerRoutesArray([
            'GET'  => [
                ''                               => ['getIndex' => $permissionStaff],
                '/insert'                        => ['getReportInsert' => $permissionStaff],
                '/update/{reportId:\d+}'         => ['getReportUpdate' => $permissionReportOwner],
                '/results/{reportId:\d+}'        => ['getReportResults' => $permissionStaff],
                '/results/tag/{tagId:\d+}'       => ['getReportTags' => $permissionStaff],
                '/results/{reportId:\d+}/print'  => ['getReportResultsPrint' => $permissionStaff],
                '/results/{reportId:\d+}/export' => ['getReportResultsExport' => $permissionStaff],
            ],
            'POST' => [
                '/modify'                => ['postReportModify' => $permissionReportOwner],
                '/delete/{reportId:\d+}' => ['postReportDelete' => $permissionReportOwner],
                '/dashboard/modify'      => ['postReportDashboardModify' => $permissionStaff],
            ],
        ]);
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(\Schoolbox\Error\Mapper::class),
            $this->injector->get('pastoralDataSource'),
            $this->getMapperFactory()->get(\Schoolbox\Pastoral\Tag\Mapper::class),
            $this->getMapperFactory()->get(\Schoolbox\Pastoral\Type\Mapper::class),
            $this->getMapperFactory()->get(\Schoolbox\Pastoral\SubType\Mapper::class),
            $this->getMapperFactory()->get(\Schoolbox\Pastoral\Severity\Mapper::class),
            $this->getMapperFactory()->get(HouseMapper::class),
            $this->getMapperFactory()->get(CampusMapper::class),
            $this->getMapperFactory()->get(\Schoolbox\Pastoral\Group\Mapper::class),
            $this->getMapperFactory()->get(\Schoolbox\Pastoral\Report\Mapper::class),
            $this->getMapperFactory()->get(\Schoolbox\Pastoral\Report\Dashboard\Mapper::class),
            $this->getMapperFactory()->get(\Schoolbox\Assessment\Term\Mapper::class),
            $this->getMapperFactory()->get(\Schoolbox\YearLevel\Mapper::class),
            $this->getMapperFactory()->get(\Schoolbox\Pastoral\Action\Mapper::class),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        $this->addRoutePreload($matcher, '.*', function ($params) {
            $injectedValues = [];

            if (array_key_exists('reportId', $params)) {
                try {
                    /** @var PastoralReportMapper $pastoralReportMapper */
                    $pastoralReportMapper     = $this->getMapperFactory()->get(PastoralReportMapper::class);
                    $injectedValues['report'] = $pastoralReportMapper->findById($params['reportId']);
                } catch (MapperException $e) {
                    throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
                }
            }

            if (array_key_exists('tagId', $params)) {
                try {
                    /** @var PastoralTagMapper $pastoralTagMapper */
                    $pastoralTagMapper     = $this->getMapperFactory()->get(PastoralTagMapper::class);
                    $injectedValues['tag'] = $pastoralTagMapper->findById($params['tagId']);
                } catch (MapperException $e) {
                    throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
                }
            }

            return $injectedValues;
        });
    }

    /**
     * @return Mapper|ErrorMapper
     * @throws \InvalidArgumentException If cannot find mapper class
     */
    protected function getErrorMapper()
    {
        return $this->getMapperFactory()->get(ErrorMapper::class);
    }
}
