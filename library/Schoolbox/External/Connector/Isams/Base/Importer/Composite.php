<?php
/**
 * Composite
 *
 * Strategy executor
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Importer;

use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Schoolbox\CampusMapper;
use Schoolbox\Collection;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\ClassPeriodMapper;
use Schoolbox\External\Connector\Isams\Base\Importer;
use Schoolbox\External\TimetableCampusMapper;
use Schoolbox\External\TimetableCycleMapper;
use Schoolbox\External\TimetablePeriodMapper;
use Schoolbox\External\UPS\Batchxml;
use Schoolbox\External\UserProfileMapper;
use Schoolbox\External\UserRelationMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\UserMapper;
use Zend\Uri\Exception\InvalidUriPartException;

class Composite implements ImporterInterface
{

    /**
     * @var Collection
     */
    private $registry;

    /**
     * The "perform" flag from the cli tool
     *
     * @var bool
     */
    private $perform;


    /**
     * @var Batchxml
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
     * @var LoggerInterface
     */
    private $log;


    /**
     * Composite constructor.
     *
     * @param LoggerInterface        $log
     * @param bool                   $perform
     * @param Batchxml               $batchxml
     * @param CampusMapper           $campusMapper
     * @param UserMapper             $userMapper
     * @param UserProfileMapper      $userProfileMapper
     * @param UserRelationMapper     $userRelationMapper
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
        Batchxml $batchxml,
        CampusMapper $campusMapper,
        UserMapper $userMapper,
        UserProfileMapper $userProfileMapper,
        UserRelationMapper $userRelationMapper,
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
        $this->userProfileMapper = $userProfileMapper;
        $this->userRelationMapper = $userRelationMapper;
        $this->timetableCampusMapper = $timetableCampusMapper;
        $this->timetableCycleMapper = $timetableCycleMapper;
        $this->timetablePeriodMapper = $timetablePeriodMapper;
        $this->classEntryMapper = $classEntryMapper;
        $this->classInvolvementMapper = $classInvolvementMapper;
        $this->classPeriodMapper = $classPeriodMapper;
    }


    /**
     * Execute each of the strategies
     *
     * @throws \RuntimeException
     * @throws InvalidUriPartException
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function import(): void
    {
        $this->registry
            ->add(new Importer\Profile($this->log, $this->perform, $this->batchxml, $this->userMapper, $this->userProfileMapper))
            ->add(new Importer\Relations($this->log, $this->perform, $this->batchxml, $this->userMapper, $this->userRelationMapper));
//            ->add(new Importer\Timetable\Identifier($this->log, $this->perform, $this->batchxml))
//            ->add(new Importer\Timetable\Definition($this->log, $this->perform, $this->batchxml, $this->campusMapper, $this->timetableCampusMapper, $this->timetableCycleMapper, $this->timetablePeriodMapper))
//            ->add(new Importer\Timetable\Classes($this->log, $this->perform, $this->batchxml, $this->timetablePeriodMapper, $this->classEntryMapper, $this->classInvolvementMapper, $this->classPeriodMapper, $this->userMapper));

        foreach ($this->registry as $strategy) {
            $strategy->import();
        }
    }
}
