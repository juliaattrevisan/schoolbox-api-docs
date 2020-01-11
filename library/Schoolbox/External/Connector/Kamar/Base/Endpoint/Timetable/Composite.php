<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Kamar\Base\Endpoint\Timetable;

use Psr\Log\LoggerInterface;
use Schoolbox\Collection;
use Schoolbox\Assessment\Term;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\ClassPeriodMapper;
use Schoolbox\External\Connector\Kamar\Base\Endpoint;
use Schoolbox\External\Connector\Kamar\Base\Endpoint\Profile\Container;
use Schoolbox\External\Importer\Endpoint\IStrategy;

/**
 * Class Composite
 *
 * Strategy executor
 *
 * @package Schoolbox\External\Connector\Kamar\Base\Endpoint\Timetable
 */
class Composite implements IStrategy
{

    /**
     * @var LoggerInterface
     */
    private $log;


    /**
     * The "perform" flag from the cli tool
     *
     * @var bool
     */
    private $perform;


    /**
     * @var Container
     */
    private $container;


    /**
     * @var Endpoint\Batchxml
     */
    private $timetableBatchXml;


    /**
     * @var Lookup
     */
    private $lookup;


    /**
     * @var ClassEntryMapper
     */
    private $classEntryMapper;


    /**
     * @var ClassInvolvementMapper
     */
    private $classInvolvementMapper;


    /**
     * @var ClassPeriodMapper
     */
    private $classPeriodMapper;


    /**
     * @var Term\Mapper
     */
    private $termMapper;


    /**
     * @var Collection
     */
    private $registry;


    /**
     * Composite constructor.
     *
     * @param LoggerInterface        $log
     * @param bool                   $perform
     * @param Container              $container
     * @param Endpoint\Batchxml      $timetableBatchXml
     * @param Lookup                 $lookup
     * @param ClassEntryMapper       $classEntryMapper
     * @param ClassInvolvementMapper $classInvolvementMapper
     * @param ClassPeriodMapper      $classPeriodMapper
     * @param Term\Mapper            $termMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        Container $container,
        Endpoint\Batchxml $timetableBatchXml,
        Lookup $lookup,
        ClassEntryMapper $classEntryMapper,
        ClassInvolvementMapper $classInvolvementMapper,
        ClassPeriodMapper $classPeriodMapper,
        Term\Mapper $termMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->registry = new Collection();
        $this->container = $container;
        $this->timetableBatchXml = $timetableBatchXml;
        $this->lookup = $lookup;
        $this->classEntryMapper = $classEntryMapper;
        $this->classInvolvementMapper = $classInvolvementMapper;
        $this->classPeriodMapper = $classPeriodMapper;
        $this->termMapper = $termMapper;
    }


    /**
     * Execute each of the strategies
     */
    public function import(): void
    {
        $this->registry
            ->add(new Endpoint\Timetable\Classes($this->log, $this->perform, $this->container, $this->timetableBatchXml, $this->lookup, $this->classEntryMapper, $this->classInvolvementMapper, $this->classPeriodMapper, $this->termMapper));

        foreach ($this->registry as $strategy) {
            $strategy->import();
        }
    }
}
