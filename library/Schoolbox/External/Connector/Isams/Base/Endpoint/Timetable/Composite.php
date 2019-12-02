<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint\Timetable;

use Psr\Log\LoggerInterface;
use Schoolbox\CampusMapper;
use Schoolbox\Collection;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\ClassPeriodMapper;
use Schoolbox\External\Connector\Isams\Base\Endpoint;
use Schoolbox\External\TimetableCampusMapper;
use Schoolbox\External\TimetableCycleMapper;
use Schoolbox\External\TimetablePeriodMapper;
use Schoolbox\UserMapper;

/**
 * Class Composite
 *
 * Strategy executor
 *
 * @package Schoolbox\External\Connector\Isams\Base\Endpoint
 */
class Composite implements Endpoint\IStrategy
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
     * @var Endpoint\Batchxml
     */
    private $batchxml;


    /**
     * @var CampusMapper
     */
    private $campusMapper;


    /**
     * @var UserMapper
     */
    private $userMapper;


    /**
     * @var TimetableCampusMapper
     */
    private $timetableCampusMapper;


    /**
     * @var TimetableCycleMapper
     */
    private $timetableCycleMapper;


    /**
     * @var TimetablePeriodMapper
     */
    private $timetablePeriodMapper;


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
     * @var Collection
     */
    private $registry;


    /**
     * Composite constructor.
     *
     * @param LoggerInterface        $log
     * @param bool                   $perform
     * @param Endpoint\Batchxml      $batchxml
     * @param CampusMapper           $campusMapper
     * @param UserMapper             $userMapper
     * @param TimetableCampusMapper  $timetableCampusMapper
     * @param TimetableCycleMapper   $timetableCycleMapper
     * @param TimetablePeriodMapper  $timetablePeriodMapper
     * @param ClassEntryMapper       $classEntryMapper
     * @param ClassInvolvementMapper $classInvolvementMapper
     * @param ClassPeriodMapper      $classPeriodMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        Endpoint\Batchxml $batchxml,
        CampusMapper $campusMapper,
        UserMapper $userMapper,
        TimetableCampusMapper $timetableCampusMapper,
        TimetableCycleMapper $timetableCycleMapper,
        TimetablePeriodMapper $timetablePeriodMapper,
        ClassEntryMapper $classEntryMapper,
        ClassInvolvementMapper $classInvolvementMapper,
        ClassPeriodMapper $classPeriodMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->registry = new Collection();
        $this->batchxml = $batchxml;
        $this->campusMapper = $campusMapper;
        $this->userMapper = $userMapper;
        $this->timetableCampusMapper = $timetableCampusMapper;
        $this->timetableCycleMapper = $timetableCycleMapper;
        $this->timetablePeriodMapper = $timetablePeriodMapper;
        $this->classEntryMapper = $classEntryMapper;
        $this->classInvolvementMapper = $classInvolvementMapper;
        $this->classPeriodMapper = $classPeriodMapper;
    }


    /**
     * Execute each of the strategies
     */
    public function import(): void
    {
        $this->registry
            ->add(new Endpoint\Timetable\Identifier($this->log, $this->perform, $this->batchxml))
            ->add(new Endpoint\Timetable\Definition($this->log, $this->perform, $this->batchxml, $this->campusMapper, $this->timetableCampusMapper, $this->timetableCycleMapper, $this->timetablePeriodMapper))
            ->add(new Endpoint\Timetable\Classes($this->log, $this->perform, $this->batchxml, $this->timetablePeriodMapper, $this->classEntryMapper, $this->classInvolvementMapper, $this->classPeriodMapper, $this->userMapper));

        foreach ($this->registry as $strategy) {
            $strategy->import();
        }
    }
}
