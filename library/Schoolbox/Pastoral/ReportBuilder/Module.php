<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Pastoral\ReportBuilder;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Router\PathMatcher;
use Aura\Di\ContainerInterface;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Pastoral\Severity\Mapper as PastoralSeverityMapper;
use Schoolbox\Pastoral\Type\Mapper as PastoralTypeMapper;
use Schoolbox\Pastoral\Report\Mapper as PastoralReportMapper;
use Schoolbox\Pastoral\Access\Mapper as PastoralAccessMapper;
use Schoolbox\Pastoral\Tag\Mapper as PastoralTagMapper;
use Schoolbox\Role;
use Twig_Loader_Chain;

// These mappers are injected into controllers where required

use Schoolbox\UserMapper;

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
        $permissionStaff = new Rule\HasRole(Role::STAFF);

        $this->registerRoutesArray([
            'GET' => [
                ''                              => ['getIndex' => $permissionStaff],
                '/insert'                       => ['getReportInsert' => $permissionStaff],
                '/update/{reportId:\d+}'        => ['getReportUpdate' => $permissionStaff],
                '/results/{reportId:\d+}'       => ['getReportResults' => $permissionStaff],
                '/results/tag/{tagId:\d+}'      => ['getReportTags' => $permissionStaff],
                '/results/{reportId:\d+}/print' => ['getReportResultsPrint' => $permissionStaff],
            ],
            'POST' => [
                '/modify'                       => ['postReportModify' => $permissionStaff],
            ]
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector) {
        $mappers = [
            'userMapper' => UserMapper::getInstance(),
            'pastoralMapper' => PastoralMapper::getInstance(),
            'pastoralReportMapper' => PastoralReportMapper::getInstance(),
            'pastoralSeverityMapper' => PastoralSeverityMapper::getInstance(),
            'pastoralTypeMapper' => PastoralTypeMapper::getInstance(),
            'pastoralAccessMapper' => PastoralAccessMapper::getInstance(),
            'pastoralTagMapper' => PastoralTagMapper::getInstance()
        ];

        $injector->set('controller:'.$this->basename, $injector->lazyNew(Controller::class, $mappers));
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader) {
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
                    $pastoralReportMapper = $this->getMapperFactory()->get(PastoralReportMapper::class);
                    $injectedValues['report'] = $pastoralReportMapper->findById($params['reportId']);
                } catch (MapperException $e) {
                }
            }

            if (array_key_exists('tagId', $params)) {
                try {
                    /** @var PastoralTagMapper $pastoralTagMapper */
                    $pastoralTagMapper = $this->getMapperFactory()->get(PastoralTagMapper::class);
                    $injectedValues['tag'] = $pastoralTagMapper->findById($params['tagId']);
                } catch (MapperException $e) {
                }
            }

            return $injectedValues;
        });
    }
}
