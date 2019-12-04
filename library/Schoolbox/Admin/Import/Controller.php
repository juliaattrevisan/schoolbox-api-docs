<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Import;

use Alaress\Database\Predicate\Builder;
use Alaress\Database\Predicate\InvalidColumnException;
use Alaress\Web\Controller\ConfigController;
use Aura\Di\Exception\ServiceNotFound;
use Aura\Router\Exception\RouteNotFound;
use PDOException;
use ReflectionException;
use Schoolbox\Admin\Import\Navigation\Direct;
use Schoolbox\Admin\Import\Navigation\SchoolData;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\External\Importer\Endpoint;
use Schoolbox\Mapper\Exception;
use UnexpectedValueException;

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
     * @throws ServiceNotFound
     * @throws RouteNotFound
     * @throws ReflectionException
     */
    public function getDirectIndex(): void
    {
        $this->getResponse()->content->set($this->buildHtmlOutput('@adminImportIndex/direct/index.twig', [
            'pageCurrent' => 'import/directImport',
            'allNavigation'  => (new Direct($this->getConfigLoader()))->buildNavigation($this->getRouter())->getAllNodes()
        ])->setTitle('Direct Import'));
    }


    /**
     * Landing Page for School Data Import
     *
     * @throws ServiceNotFound
     * @throws RouteNotFound
     * @throws ReflectionException
     * @throws InvalidColumnException
     */
    public function getSchoolDataIndex(): void
    {
        $configContext = $this->buildConfigContext(
            $this->buildConfigPredicate(),
            $this->router->generate('adminImportIndex.postSchoolDataOptions')
        );

        $this->getResponse()->content->set($this->buildHtmlOutput('@adminImportIndex/schoolData/index.twig', [
            'pageCurrent' => 'import/schoolDataImport',
            'allNavigation'  => (new SchoolData())->buildNavigation($this->getRouter())->getAllNodes(),
            'dataSource' => $this->getConfigLoader()->get('schooldata_import_data_source'),
            'configContext' => $configContext,
            'statusIndicatorRoute' => $this->getRouter()->generate('adminImportIndex.getStatusIndicator'),
        ])->setTitle('School Data Import'));
    }


    /**
     * Save the configuration for School Data Import's chosen mechanism
     *
     * @throws InvalidColumnException
     * @throws RouteNotFound
     * @throws PDOException
     * @throws Exception
     * @throws UnexpectedValueException
     */
    public function postSchoolDataOptions(): void
    {
        $this->updateConfig($this->request->post->get(), $this->buildConfigPredicate());
        $this->getResponse()->redirect->afterPost($this->getRouter()->generate('adminImportIndex.getSchoolDataIndex'));
    }


    /**
     * Returns a predicate builder for finding all custom config for this module
     *
     * @return Builder
     * @throws InvalidColumnException
     */
    private function buildConfigPredicate(): Builder
    {
        return $this->getConfigMapper()->getPredicateBuilder()->equals(ConfigMapper::COL_MODULE, ConfigMapper::MODULE_SCHOOLDATA);
    }


    /**
     * Build asynchronous status indicator payload
     */
    public function getStatusIndicator(): void
    {
        $host = $this->getConfigLoader()->get('schooldata_import_endpoint_host');
        $key = $this->getConfigLoader()->get('schooldata_import_endpoint_key');
        $type = $this->getConfigLoader()->get('schooldata_import_data_source');

        $status = (new Endpoint\Status\Factory($host, $key))->produce($type);

        $this->getResponse()->content->set($this->buildUnthemedHtmlOutput('@adminImportIndex/schoolData/status.twig', [
            'isReachable' => $status ? $status->isReachable() : false
        ]));
    }


    /**
     * Build asynchronous status report
     */
    public function getStatusReport(): void
    {
        $host = $this->getConfigLoader()->get('schooldata_import_endpoint_host');
        $key = $this->getConfigLoader()->get('schooldata_import_endpoint_key');
        $type = $this->getConfigLoader()->get('schooldata_import_data_source');

        $status = (new Endpoint\Status\Factory($host, $key))->produce($type);

        $this->getResponse()->content->set($this->buildHtmlOutput('@adminImportIndex/schoolData/statusReport.twig', [
            'pageCurrent' => 'import/schoolDataImport',
            'report' => $status ? $status->getReport() : ''
        ]));
    }
}
