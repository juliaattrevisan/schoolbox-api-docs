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
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\External\Importer\Endpoint;
use Schoolbox\External\Importer\Endpoint\Specification\ISpecification;
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
     * Default message if you happen to have the status report open
     * but you got your settings arbitrarily reset
     */
    private const DEFAULT_UNKNOWN_DATA_SOURCE = 'Unknown data source selected.';


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
            'pageCurrent' => $this->router->generate('adminImportIndex.getDirectIndex'),
            'allBreadcrumbs' => (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('adminImportIndex.getDirectIndex')),
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

        $host = (string)$this->getConfigLoader()->get('schooldata_import_endpoint_host');
        $key = (string)$this->getConfigLoader()->get('schooldata_import_endpoint_key');

        $hasIndicator = $host !== '' && $key !== '';

        $this->getResponse()->content->set($this->buildHtmlOutput('@adminImportIndex/schoolData/index.twig', [
            'pageCurrent' => $this->router->generate('adminImportIndex.getSchoolDataIndex'),
            'allBreadcrumbs' => (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('adminImportIndex.getSchoolDataIndex')),
            'allNavigation'  => (new SchoolData())->buildNavigation($this->getRouter())->getAllNodes(),
            'dataSource' => $this->getConfigLoader()->get('schooldata_import_data_source'),
            'configContext' => $configContext,
            'hasIndicator' => $hasIndicator,
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
        $type = (string)$this->getConfigLoader()->get('schooldata_import_data_source');

        $isReachable = false;

        if ('' !== $type) {
            try {
                $status = (new Endpoint\Status\Factory($host, $key))->produce($type);

                if ($status) {
                    $isReachable = $status->isReachable();
                }
            } catch (\DomainException | \InvalidArgumentException $e) {
                // noop
            }
        }

        $this->getResponse()->content->set($this->buildUnthemedHtmlOutput('@adminImportIndex/schoolData/status.twig', [
            'isReachable' => $isReachable
        ]));
    }


    /**
     * Build asynchronous status report
     */
    public function getStatusReport(): void
    {
        $host = $this->getConfigLoader()->get('schooldata_import_endpoint_host');
        $key = $this->getConfigLoader()->get('schooldata_import_endpoint_key');
        $type = (string)$this->getConfigLoader()->get('schooldata_import_data_source');

        $report = null;
        $message = self::DEFAULT_UNKNOWN_DATA_SOURCE;

        if ('' !== $type) {
            try {
                $status = (new Endpoint\Status\Factory($host, $key))->produce($type);

                if ($status) {
                    $report = $status->getReport();
                }
            } catch (\DomainException | \InvalidArgumentException $e) {
                $message = $e->getMessage();
            }
        }

        $this->getResponse()->content->set($this->buildHtmlOutput('@adminImportIndex/schoolData/statusReport.twig', [
            'pageCurrent' => $this->router->generate('adminImportIndex.getSchoolDataIndex'),
            'report' => $report ?: [[
                'message' => $message,
                'reason' => '',
                'status' => ISpecification::STATUS_ERROR
            ]]
        ]));
    }
}
