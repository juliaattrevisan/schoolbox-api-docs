<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\RubricUsage;

use InvalidArgumentException;
use Schoolbox\Assessment\Rubric\DataModel\Capability\Model as CapabilityModel;
use Schoolbox\Assessment\Rubric\DataModel\Indicator\Model as IndicatorModel;
use Schoolbox\Filter\Filters\Assessment\IdFinder;
use UnexpectedValueException;

use function preg_match;

/**
 * Responsible for mapping between a filter selection key and a capability id and optional indicator id
 */
class Codec
{

    /**
     * Concrete filter option key.
     *
     * We include the capability id in the indicators for an optimisation in the Assessment IdFinder.
     * Since any assessment that uses an indicator, must use the indicator's capability, we can
     * JOIN just to the capability usage table to find assessments that use any of a set of capabilities/indicators.
     *
     * @example Capability (id 2)                   => 'c2'
     * @example Capability (id 2) Indicator (id 4), => 'c2i4'
     * @see     IdFinder::getClauseCapabilityIndicator()
     *
     * @var string
     */
    private $pattern = '/^c([1-9]\d*)(?:i([1-9]\d*))?$/';


    /**
     * @param CapabilityModel $capability
     * @return string
     * @throws InvalidArgumentException If capability ID not set
     */
    public function encodeCapability(CapabilityModel $capability): string
    {
        if (!$capability->getId()) {
            throw new InvalidArgumentException('Capability ID must be set');
        }
        return "c{$capability->getId()}";
    }


    /**
     * @param IndicatorModel $indicator
     * @return string
     * @throws InvalidArgumentException If capability or indicator ID not set
     */
    public function encodeIndicator(IndicatorModel $indicator): string
    {
        if (!$indicator->getId()) {
            throw new InvalidArgumentException('Indicator ID must be set');
        }

        return "{$this->encodeCapability($indicator->getCapability())}i{$indicator->getId()}";
    }


    /**
     * @param string $key
     * @return array
     * @throws UnexpectedValueException
     */
    public function decode(string $key): array
    {
        if (!preg_match($this->pattern, $key, $matches)) {
            throw new UnexpectedValueException("Unable to decode key: $key");
        }
        $capabilityId = $matches[1];
        $indicatorId  = $matches[2] ?? null;

        return [(int)$capabilityId, null === $indicatorId ? null : (int)$indicatorId];
    }
}
