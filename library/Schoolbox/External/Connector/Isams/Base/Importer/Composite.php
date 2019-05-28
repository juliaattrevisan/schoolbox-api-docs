<?php
/**
 * Composite
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector\Isams\Base\Importer;

use Psr\Log\LoggerInterface;
use Schoolbox\Collection;
use Schoolbox\External\Connector\Isams\Base\Importer;
use Schoolbox\External\TimetableCampusMapper;
use Schoolbox\External\TimetableCycleMapper;
use Schoolbox\External\TimetablePeriodMapper;
use Schoolbox\External\UPS\Batchxml;
use Schoolbox\External\UserProfileMapper;
use Schoolbox\External\UserRelationMapper;
use Schoolbox\UserMapper;

class Composite implements ImporterInterface
{

    /**
     * @var Collection
     */
    private $registry;


    /**
     * @var Batchxml
     */
    private $api;


    /**
     * The "perform" flag from the cli tool
     *
     * @var bool
     */
    private $perform;


    /**
     * @var UserMapper
     */
    private $userMapper;


    /**
     * @var UserProfileMapper
     */
    private $userProfileMapper;


    /**
     * @var UserRelationMapper
     */
    private $userRelationMapper;


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
     * @var LoggerInterface
     */
    private $log;


    /**
     * Composite constructor.
     *
     * @param LoggerInterface       $log
     * @param bool                  $perform
     * @param Batchxml              $api
     * @param UserMapper            $userMapper
     * @param UserProfileMapper     $userProfileMapper
     * @param UserRelationMapper    $userRelationMapper
     * @param TimetableCampusMapper $timetableCampusMapper
     * @param TimetableCycleMapper  $timetableCycleMapper
     * @param TimetablePeriodMapper $timetablePeriodMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        Batchxml $api,
        UserMapper $userMapper,
        UserProfileMapper $userProfileMapper,
        UserRelationMapper $userRelationMapper,
        TimetableCampusMapper $timetableCampusMapper,
        TimetableCycleMapper $timetableCycleMapper,
        TimetablePeriodMapper $timetablePeriodMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->registry = new Collection();
        $this->api = $api;
        $this->userMapper = $userMapper;
        $this->userProfileMapper = $userProfileMapper;
        $this->userRelationMapper = $userRelationMapper;
        $this->timetableCampusMapper = $timetableCampusMapper;
        $this->timetableCycleMapper = $timetableCycleMapper;
        $this->timetablePeriodMapper = $timetablePeriodMapper;
    }


    /**
     * Execute each of the strategies
     */
    public function import(): void
    {
        $this->registry
//            ->add(new Importer\Profile($this->log, $this->perform, $this->api, $this->userMapper, $this->userProfileMapper))
//            ->add(new Importer\Relations($this->log, $this->perform, $this->api, $this->userMapper, $this->userRelationMapper))
            ->add(new Importer\Timetable($this->log, $this->perform, $this->api, $this->timetableCampusMapper, $this->timetableCycleMapper, $this->timetablePeriodMapper));

        foreach ($this->registry as $strategy) {
            $strategy->import();
        }
    }
}
