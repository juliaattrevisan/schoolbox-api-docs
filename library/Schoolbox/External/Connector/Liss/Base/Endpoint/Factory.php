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
     * @param LoggerInterface $log
     * @param bool            $perform
     * @param string          $source
     * @param string          $defaultType
     * @param array           $exclusions
     * @param string          $teacherIdentifier
     * @param Request         $message
     * @return GenericCollection
     * @throws \UnexpectedValueException
     */
    public function produce(
        LoggerInterface $log,
        bool $perform,
        string $source,
        string $defaultType,
        array $exclusions,
        string $teacherIdentifier,
        Request $message
    ): GenericCollection {
        return (new GenericCollection(IStrategy::class))
            ->add(new Prepare(
                $log,
                $perform,
                \Schoolbox\External\Event\Mapper::getInstance(),
                \Schoolbox\External\ClassEntryMapper::getInstance(),
                \Schoolbox\External\ClassInvolvementMapper::getInstance(),
                \Schoolbox\External\ClassPeriodMapper::getInstance(),
                \Schoolbox\External\TimetablePeriodMapper::getInstance(),
                \Schoolbox\External\Event\Overrides\Mapper::getInstance(),
                $source
            ))
            ->add(new Classes(
                $log,
                $perform,
                $defaultType,
                $exclusions,
                $teacherIdentifier,
                \Schoolbox\External\Event\Mapper::getInstance(),
                \Schoolbox\External\Event\Overrides\Mapper::getInstance(),
                \Schoolbox\External\ClassEntryMapper::getInstance(),
                \Schoolbox\External\ClassInvolvementMapper::getInstance(),
                $message
            ))
            ->add(new Cleanup(
                $log,
                $perform,
                new \Schoolbox\External\Event\Mapper(),
                $source
            ));
    }
}