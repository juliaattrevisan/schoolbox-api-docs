<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\RowSet;

use Schoolbox\Assessment\Rubric\Util;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\AssessmentContext;

/**
 * A rubric row set.
 *
 * This is a representation of the capabilities (and their constituent indicators)
 * which are available to an assessment context.
 *
 * It contains:
 * * an assessment context (in practise, always a folder)
 * * a set of rubric capabilities
 */
class RowSet
{
    /**
     * The context within which this row set lives.
     *
     * In practise, this is always a folder.
     *
     * @var AssessmentContext
     */
    private $context;


    /**
     * The capabilities which comprise this row set.
     *
     * @var Capability[]|GenericCollection
     */
    private $capabilities;


    /**
     * Constructor
     *
     * @param AssessmentContext $context
     */
    public function __construct(AssessmentContext $context)
    {
        $this->context      = $context;
        $this->capabilities = new GenericCollection(Capability::class);
    }


    /**
     * Retrieves a capability by its ID.
     *
     * @param int $capabilityId
     * @return null|Capability
     */
    public function retrieveCapability(int $capabilityId): ?Capability
    {
        return $this->capabilities->getBy('id', $capabilityId);
    }


    /**
     * Retrieves a capability by its name.
     *
     * @param string $name
     * @return Capability|null
     */
    public function retrieveCapabilityByName(string $name): ?Capability
    {
        $normalizedName = Util::normalizeName($name);
        foreach ($this->capabilities as $capability) {
            if (Util::normalizeName($capability->getName()) === $normalizedName) {
                return $capability;
            }
        }
        return null;
    }


    /**
     * Retrieves an indicator by its ID.
     *
     * @param int $indicatorId
     * @return null|Indicator
     */
    public function retrieveIndicator(int $indicatorId): ?Indicator
    {
        foreach ($this->capabilities as $capability) {
            $indicator = $capability->retrieveIndicator($indicatorId);
            if ($indicator) {
                return $indicator;
            }
        }
        return null;
    }


    /**
     * Retrieve the ID of all capabilities in this row set.
     *
     * @return array|int[]
     */
    public function retrieveCapabilityIds(): array
    {
        $result = array_unique(array_filter($this->capabilities->getPropertyValues('id')));
        sort($result);
        return $result;
    }


    /**
     * Retrieve the ID of all indicators in this row set.
     *
     * @return array|int[]
     */
    public function retrieveIndicatorIds(): array
    {
        $indicatorIds = [];
        foreach ($this->capabilities as $capability) {
            $indicatorIds = array_merge($indicatorIds, $capability->retrieveIndicatorIds());
        }
        $result = array_unique(array_filter($indicatorIds));
        sort($result);
        return $result;
    }


    /**
     * @return AssessmentContext
     */
    public function getContext(): AssessmentContext
    {
        return $this->context;
    }


    /**
     * @return Capability[]|GenericCollection
     */
    public function getCapabilities(): GenericCollection
    {
        return $this->capabilities;
    }


    /**
     * @param Capability[]|GenericCollection $capabilities
     * @return static
     */
    public function setCapabilities(GenericCollection $capabilities): self
    {
        $this->capabilities = $capabilities;
        return $this;
    }
}
