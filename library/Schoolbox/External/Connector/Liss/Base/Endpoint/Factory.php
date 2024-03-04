<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Liss\Base\Endpoint;

use Alaress\Database\Predicate\InvalidColumnException;
use Psr\Log\LoggerInterface;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\ClassPeriodMapper;
use Schoolbox\External\Connector\Liss\Base\Endpoint\TimetableCalendar\DailyData;
use Schoolbox\External\Connector\Liss\Base\Endpoint\TimetableCalendar\Cleanup;
use Schoolbox\External\Connector\Liss\Base\Endpoint\TimetableCalendar\Prepare;
use Schoolbox\External\Event\Mapper;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\TimetablePeriodMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Resource\Storage\FilesystemProxy;

/**
 * Class Factory
 *
 * The overall import recipe. A composite of multiple classes each of which has its own
 * particular responsibility. The composite execute each of its pieces in sequence.
 *
 * @package Schoolbox\External\Connector\Liss\Base\Endpoint
 */
class Factory
{
    /**
     * An arbitrarily-chosen string to denote where these events came from
     */
    public const SOURCE_NAME = 'suds_liss';


    /**
     * Constructor.
     *
     * @param LoggerInterface $log
     * @param bool $perform
     * @param string $defaultType
     * @param string $exclusions
     * @param string $teacherIdentifier
     * @param FilesystemProxy $filesystemProxy
     * @param \Schoolbox\Resource\Storage\Mapper $storageMapper
     * @param Mapper $eventMapper
     * @param ClassEntryMapper $classEntryMapper
     * @param ClassInvolvementMapper $classInvolvementMapper
     * @param ClassPeriodMapper $classPeriodMapper
     * @param TimetablePeriodMapper $periodMapper
     * @param \Schoolbox\External\Event\Overrides\Mapper $overridesMapper
     */
    public function __construct(
        private LoggerInterface $log,
        private bool $perform,
        private string $defaultType,
        private string $exclusions,
        private string $teacherIdentifier,
        private \Schoolbox\Resource\Storage\FilesystemProxy $filesystemProxy,
        private \Schoolbox\Resource\Storage\Mapper $storageMapper,
        private \Schoolbox\External\Event\Mapper $eventMapper,
        private ClassEntryMapper $classEntryMapper,
        private ClassInvolvementMapper $classInvolvementMapper,
        private ClassPeriodMapper $classPeriodMapper,
        private TimetablePeriodMapper $periodMapper,
        private \Schoolbox\External\Event\Overrides\Mapper $overridesMapper
    ) {
    }


    /**
     * @return GenericCollection
     * @throws InvalidColumnException
     * @throws \UnexpectedValueException
     */
    public function produce(): GenericCollection {
        /* Convert exclusions csv into an array of trimmed elements
         */
        $exclusions = array_map('trim', explode(',', $this->exclusions));

        /* Check if there are any liss payloads available:
         * If not, just do nothing at all
         */
        if ((new PayloadCache($this->storageMapper, $this->filesystemProxy, $this->log))->hasPayloads()) {
            return (new GenericCollection(IStrategy::class))
                ->add(new Prepare(
                    $this->log,
                    $this->perform,
                    $this->eventMapper,
                    $this->classEntryMapper,
                    $this->classInvolvementMapper,
                    $this->classPeriodMapper,
                    $this->periodMapper,
                    $this->overridesMapper
                ))
                ->add(new DailyData(
                    $this->log,
                    $this->perform,
                    $this->defaultType,
                    $exclusions,
                    $this->teacherIdentifier,
                    $this->filesystemProxy,
                    $this->storageMapper,
                    $this->eventMapper,
                    $this->overridesMapper,
                    $this->classEntryMapper,
                    $this->classInvolvementMapper
                ))
                ->add(new Cleanup(
                    $this->log,
                    $this->perform,
                    $this->eventMapper
                ));
        }

        return new GenericCollection(IStrategy::class);
    }
}
