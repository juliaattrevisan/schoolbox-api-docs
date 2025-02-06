<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Civica\Base\Endpoint\Calendar;

use Psr\Log\LoggerInterface;
use Schoolbox\External\Connector\Civica\Base\Endpoint\Api;
use Schoolbox\External\Event\Mapper;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\GenericCollection;
use SensitiveParameter;

/**
 * Class Factory
 *
 * The overall import recipe. A composite of multiple classes each of which has its own
 * particular responsibility. The composite execute each of its pieces in sequence.
 *
 * @package Schoolbox\External\Connector\Civica\Base\Endpoint\Calendar
 */
class Factory
{
    /**
     * Event source is an arbitrary string that helps identify the thing that imported
     * them into the local event table so you can do a mass remove if needed
     */
    public const SOURCE_NAME = 'suds_civica';


    /**
     * Collect events from this many months in the past (a negative int)
     */
    private const FROM = -1;


    /**
     * Collect events to this many months in the future
     */
    private const TO = 12;


    /**
     * Factory constructor.
     *
     * @param LoggerInterface $log
     * @param bool $perform
     * @param string $host
     * @param string $key
     * @param Mapper $eventMapper
     * @param \Schoolbox\External\Event\User\Mapper $eventUserMapper
     */
    public function __construct(
        private LoggerInterface $log,
        private bool $perform,
        private string $host,
        #[SensitiveParameter] private string $key,
        private \Schoolbox\External\Event\Mapper $eventMapper,
        private \Schoolbox\External\Event\User\Mapper $eventUserMapper
    ) {
    }


    /**
     * @return null|GenericCollection
     * @throws \DomainException
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     */
    public function produce(): ?GenericCollection
    {
        $this->log->info('Accessing api endpoint');

        $api = new Api($this->host, $this->key);

        $from = \Alaress_Date_Format::now()->addMonths(self::FROM)->startOfDay();
        $to = \Alaress_Date_Format::now()->addMonths(self::TO)->endOfDay();

        /* Purge previously-imported events and event relationships
         */
        return (new GenericCollection(IStrategy::class))
            ->add(new Prepare(
                $this->log,
                $this->perform,
                $this->eventMapper,
                $this->eventUserMapper,
                self::SOURCE_NAME
            ))
            ->add(new NonAcademicEvents(
                $this->log,
                $this->perform,
                $api,
                $this->eventMapper,
                $this->eventUserMapper,
                $from,
                $to
            ))
            ->add(new Cleanup(
                $this->log,
                $this->perform,
                $this->eventMapper,
                self::SOURCE_NAME
            ));
    }
}
