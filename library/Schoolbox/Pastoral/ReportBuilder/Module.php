<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\ReportBuilder;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Router\PathMatcher;
use Aura\Di\ContainerInterface;
use HouseMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Pastoral\Authorizer\Rule\IsReportOwner;
use Schoolbox\Pastoral\Report\Mapper as PastoralReportMapper;
use Schoolbox\Pastoral\Tag\Mapper as PastoralTagMapper;
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
        $permissionStaff       = new Rule\HasRole(Role::STAFF);
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
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector)
    {
        $mappers = [
            'userMapper'                    => $this->getMapperFactory()->getLazy(\Schoolbox\UserMapper::class),
            'houseMapper'                   => $this->getMapperFactory()->getLazy(HouseMapper::class),
            'pastoralDataSource'            => $injector->get('pastoralDataSource'),
            'pastoralReportMapper'          => $this->getMapperFactory()->getLazy(\Schoolbox\Pastoral\Report\Mapper::class),
            'pastoralSeverityMapper'        => $this->getMapperFactory()->getLazy(\Schoolbox\Pastoral\Severity\Mapper::class),
            'pastoralTypeMapper'            => $this->getMapperFactory()->getLazy(\Schoolbox\Pastoral\Type\Mapper::class),
            'pastoralSubTypeMapper'         => $this->getMapperFactory()->getLazy(\Schoolbox\Pastoral\SubType\Mapper::class),
            'pastoralTagMapper'             => $this->getMapperFactory()->getLazy(\Schoolbox\Pastoral\Tag\Mapper::class),
            'errorMapper'                   => $this->getMapperFactory()->getLazy(\Schoolbox\Error\Mapper::class),
            'pastoralGroupMapper'           => $this->getMapperFactory()->getLazy(\Schoolbox\Pastoral\Group\Mapper::class),
            'pastoralReportDashboardMapper' => $this->getMapperFactory()->getLazy(\Schoolbox\Pastoral\Report\Dashboard\Mapper::class),
            'termMapper'                    => $this->getMapperFactory()->getLazy(\Schoolbox\Assessment\Term\Mapper::class)
        ];

        $injector->set('controller:' . $this->basename, $injector->lazyNew(Controller::class, $mappers));
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
