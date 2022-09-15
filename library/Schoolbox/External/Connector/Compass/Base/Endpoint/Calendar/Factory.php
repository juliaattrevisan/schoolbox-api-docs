<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Compass\Base\Endpoint\Calendar;

use GuzzleHttp\Exception\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Schoolbox\Calendar\EventTypeMapper;
use Schoolbox\External\Connector\Compass\Base\Endpoint\Api;
use Schoolbox\External\Connector\Compass\Base\Endpoint\ApiCertification;
use Schoolbox\External\Connector\Compass\Base\Endpoint\Calendar\NonAcademicEvents;
use Schoolbox\External\Event\Mapper;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\GenericCollection;

/**
 * Class Factory
 *
 * The overall import recipe. A composite of multiple classes each of which has its own
 * particular responsibility. The composite execute each of its pieces in sequence.
 *
 * @package Schoolbox\External\Connector\Compass\Base\Endpoint\Calendar
 */
class Factory
{

    /**
     * Event source is an arbitrary string that helps identify the thing that imported
     * them into the local event table so you can do a mass remove if needed
     */
    public const SOURCE_NAME = 'suds_compass';


    /**
     * @var LoggerInterface
     */
    private LoggerInterface $log;


    /**
     * The "perform" flag from the cli tool
     *
     * @var bool
     */
    private bool $perform;


    /**
     * @var string
     */
    private string $host;


    /**
     * @var string
     */
    private string $key;


    /**
     * @var EventTypeMapper
     */
    private \Schoolbox\Calendar\EventTypeMapper $eventTypeMapper;


    /**
     * @var \Schoolbox\External\Event\Mapper
     */
    private \Schoolbox\External\Event\Mapper $eventMapper;


    /**
     * @var \Schoolbox\External\Event\Overrides\Mapper
     */
    private \Schoolbox\External\Event\Overrides\Mapper $overridesMapper;


    /**
     * Factory constructor.
     *
     * @param LoggerInterface                            $log
     * @param bool                                       $perform
     * @param string                                     $host
     * @param string                                     $key
     * @param EventTypeMapper                            $eventTypeMapper
     * @param Mapper                                     $eventMapper
     * @param \Schoolbox\External\Event\Overrides\Mapper $overridesMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        string $host,
        string $key,
        \Schoolbox\Calendar\EventTypeMapper $eventTypeMapper,
        \Schoolbox\External\Event\Mapper $eventMapper,
        \Schoolbox\External\Event\Overrides\Mapper $overridesMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->host = $host;
        $this->key = $key;
        $this->eventTypeMapper = $eventTypeMapper;
        $this->eventMapper = $eventMapper;
        $this->overridesMapper = $overridesMapper;
    }


    /**
     * @return null|GenericCollection
     * @throws \DomainException
     * @throws InvalidArgumentException
     * @throws \UnexpectedValueException
     */
    public function produce(): ?GenericCollection
    {
        $api = new Api($this->host, $this->key);

        /* Data is obtained daily; remember lower and upper bound values
         * for api calls. It needs to be in a specific format so might
         * as well remember that
         */
        $from = \Alaress_Date_Format::now()->startOfDay()->getUsingFormat('c');
        $to = \Alaress_Date_Format::now()->endOfDay()->getUsingFormat('c');

        /* Purge previously-imported events and event relationships
         */
        return (new GenericCollection(IStrategy::class))
            ->add(new Prepare(
                $this->log,
                $this->perform,
                $this->eventMapper,
                self::SOURCE_NAME
            ))
            ->add(new NonAcademicEvents(
                $this->log,
                $this->perform,
                $api,
                $this->eventMapper,
                $this->overridesMapper,
                $from,
                $to
            ))
            ->add(new Cleanup(
                $this->log,
                $this->perform,
                $this->eventMapper,
                self::SOURCE_NAME
            ))
            ->add(new ApiCertification(
                $this->log,
                $api
            ));
    }
}
