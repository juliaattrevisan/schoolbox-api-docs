<?php

/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Import\Pastoral;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Pastoral\Log\Mapper;

/**
 * Class Module
 *
 * @package Schoolbox\Admin\Import\Pastoral
 */
class Module extends BaseModule
{

    /**
     * @inheritdoc
     */
    protected $basename = 'adminImportPastoral';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     * @throws InvalidArgumentException
     */
    protected function registerRoutes(): void
    {
        $perms = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\IsSuperuser()
        );

        $preload = function ($params) {
            return $this->preload($params);
        };

        $this->register('GET', '', 'getIndex', $perms);
        $this->register('GET', '/log', 'getLog', $perms);
        $this->register('GET', '/log/{logId}', 'getLogDetail', $perms, $preload);
        $this->register('POST', '/process', 'postImport', $perms);
    }


    /**
     * Preload log data
     *
     * @param array $params
     * @return array
     * @throws InvalidArgumentException
     */
    private function preload(array $params): array
    {
        $injectedValues = [];

        // Load log entries

        if (array_key_exists('logId', $params)) {
            try {
                /** @var Mapper $logMapper */
                $logMapper = $this->getMapperFactory()->get(Mapper::class);

                $injectedValues['log'] = $logMapper->findByColumn('import_id', $params['logId']);
            } catch (MapperException $e) {
            }
        }

        return $injectedValues;
    }


    /**
     * @inheritdoc
     */
    public function buildController(ContainerInterface $container): Base
    {
        return new Controller($this->getConfig());
    }


    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
