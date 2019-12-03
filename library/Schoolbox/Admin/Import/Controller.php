<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Import;

use Alaress\Database\Predicate;
use Alaress\Web\Controller\Base;
use Alaress\Web\Controller\ConfigController;
use Schoolbox\Admin\Import\Navigation\Direct;
use Schoolbox\Admin\Import\Navigation\SchoolData;
use Schoolbox\Admin\Settings\FieldStrategyFactory;
use Schoolbox\Admin\Settings\ModuleConfig;
use Schoolbox\Config\Config;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\GenericCollection;

/**
 * Class Controller
 *
 * Present a landing page for the import destination you chose
 *
 * @package Schoolbox\Admin\Import
 */
class Controller extends ConfigController
{

    /**
     * Landing Page for Direct Import
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \ReflectionException
     */
    public function getDirectIndex()
    {
        $this->getResponse()->content->set($this->buildHtmlOutput('@adminImportIndex/direct/index.twig', [
            'pageCurrent' => 'import/directImport',
            'allNavigation'  => (new Direct($this->getConfigLoader()))->buildNavigation($this->getRouter())->getAllNodes()
        ])->setTitle('Direct Import'));
    }


    /**
     * Landing Page for School Data Import
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \ReflectionException
     */
    public function getSchoolDataIndex()
    {
        $configContext = $this->buildConfigContext(
            $this->buildConfigPredicate(),
            $this->router->generate('adminImportIndex.postSchoolDataOptions')
        );

        $this->getResponse()->content->set($this->buildHtmlOutput('@adminImportIndex/schoolData/index.twig', [
            'pageCurrent' => 'import/schoolDataImport',
            'allNavigation'  => (new SchoolData())->buildNavigation($this->getRouter())->getAllNodes(),
            'dataSource' => $this->getConfigLoader()->get('schooldata_import_data_source'),
            'configContext' => $configContext
        ])->setTitle('School Data Import'));
    }


    /**
     * Save the configuration for School Data Import's chosen mechanism
     *
     * @throws Predicate\InvalidColumnException
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function postSchoolDataOptions()
    {
        $this->updateConfig($this->request->post->get(), $this->buildConfigPredicate());
        $this->getResponse()->redirect->afterPost($this->getRouter()->generate('adminImportIndex.getSchoolDataIndex'));
    }


    /**
     * Returns a predicate builder for finding all custom config for this module
     *
     * @return \Alaress\Database\Predicate\Builder
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     */
    private function buildConfigPredicate(): Predicate\Builder
    {
        return $this->getConfigMapper()->getPredicateBuilder()->equals(ConfigMapper::COL_MODULE, ConfigMapper::MODULE_SCHOOLDATA);
    }
}
