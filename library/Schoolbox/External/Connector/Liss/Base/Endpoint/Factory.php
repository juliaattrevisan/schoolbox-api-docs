<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Liss\Base\Endpoint;

use PhpXmlRpc\Request;
use Psr\Log\LoggerInterface;
use Schoolbox\External\Connector\Liss\Base\Endpoint\TimetableCalendar\Classes;
use Schoolbox\External\Connector\Liss\Base\Endpoint\TimetableCalendar\Cleanup;
use Schoolbox\External\Connector\Liss\Base\Endpoint\TimetableCalendar\Prepare;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\GenericCollection;

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
     * @param LoggerInterface $log
     * @param bool            $perform
     * @param string          $defaultType
     * @param array           $exclusions
     * @param string          $teacherIdentifier
     * @param string          $sourceName
     * @param Request         $message
     * @return GenericCollection
     * @throws \UnexpectedValueException
     */
    public function produce(
        LoggerInterface $log,
        bool $perform,
        string $defaultType,
        array $exclusions,
        string $teacherIdentifier,
        string $sourceName,
        Request $message
    ): GenericCollection {
        return (new GenericCollection(IStrategy::class))
            ->add(new Prepare(
                $log,
                $perform,
                $sourceName,
                \Schoolbox\External\Event\Mapper::getInstance()->disableCache(),
                \Schoolbox\External\ClassEntryMapper::getInstance()->disableCache(),
                \Schoolbox\External\ClassInvolvementMapper::getInstance()->disableCache(),
                \Schoolbox\External\ClassPeriodMapper::getInstance()->disableCache(),
                \Schoolbox\External\TimetablePeriodMapper::getInstance()->disableCache(),
                \Schoolbox\External\Event\Overrides\Mapper::getInstance()->disableCache()
            ))
            ->add(new Classes(
                $log,
                $perform,
                $defaultType,
                $exclusions,
                $teacherIdentifier,
                $sourceName,
                \Schoolbox\External\Event\Mapper::getInstance()->disableCache(),
                \Schoolbox\External\Event\Overrides\Mapper::getInstance()->disableCache(),
                \Schoolbox\External\ClassEntryMapper::getInstance()->disableCache(),
                \Schoolbox\External\ClassInvolvementMapper::getInstance()->disableCache(),
                $message
            ))
            ->add(new Cleanup(
                $log,
                $perform,
                $sourceName,
                new \Schoolbox\External\Event\Mapper()
            ));
    }
}
