<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Grid\Importer;

use Schoolbox\Assessment\Rubric\Grid\Grid;
use Schoolbox\Assessment\Rubric\Grid\Level;

/**
 * Helper class for importing a rubric grid.
 */
class GenericImporter implements IImporter
{
    /**
     * The rubric grid to import
     *
     * @var Grid
     */
    private $grid;


    /**
     * The capabilities in the imported grid.
     *
     * Each value contains these keys:
     * * name:     the capability's name
     * * maxValue: if supplied, the mark-per-capability value
     *
     * @var array
     */
    private $capabilities;


    /**
     * Does this use mark ranges?
     *
     * @var bool
     */
    private $useMarkRange;


    /**
     * Does this have per-capability marks?
     * This is the capability mark type id now
     *
     * @var int|null
     */
    private $capabilityMarkType;


    /**
     * Does this have a grade group specified for its associated mark type?
     *
     * @var int|null
     */
    private $capabilityGradeGroup;


    /**
     * Constructor
     *
     * @param Grid     $grid
     * @param bool     $useMarkRange
     * @param int|null $capabilityMarkType
     * @param int|null $capabilityGradeGroup
     */
    public function __construct(Grid $grid, bool $useMarkRange, ?int $capabilityMarkType, ?int $capabilityGradeGroup)
    {
        $this->grid                 = $grid;
        $this->useMarkRange         = $useMarkRange;
        $this->capabilityMarkType   = $capabilityMarkType;
        $this->capabilityGradeGroup = $capabilityGradeGroup;
    }

    /**
     * @inheritDoc
     */
    public function getCapabilityMarkType(): ?int
    {
        return $this->capabilityMarkType;
    }

    /**
     * @inheritDoc
     */
    public function getCapabilityGradeGroup(): ?int
    {
        return $this->capabilityGradeGroup;
    }

    /**
     * @inheritDoc
     */
    public function getLevels(): array
    {
        $levels = [];
        /** @var Level $level */
        foreach ($this->grid->getLevels() as $key => $level) {
            $levels[$key] = $level->getName();
        }
        return $levels;
    }

    /**
     * @inheritDoc
     */
    public function isUseMarkRange(): bool
    {
        return $this->useMarkRange;
    }

    /**
     * @inheritDoc
     */
    public function retrieveCapabilitiesWithIndicators(): array
    {
        if (!isset($this->capabilities)) {
            $capabilities = [];
            foreach ($this->grid->getRowSet()->getCapabilities() as $capability) {
                $indicators = [];
                $capUsage   = $this->grid->retrieveCapabilityUsage($capability);
                if (!$capUsage) {
                    continue;
                }
                foreach ($capability->getIndicators() as $indicator) {
                    $indUsage = $this->grid->retrieveIndicatorUsage($indicator);
                    if (!$indUsage) {
                        continue;
                    }
                    $descriptors = [];
                    foreach ($this->grid->getLevels() as $key => $level) {
                        $descriptor    = $indUsage->retrieveDescriptor($key);
                        $descriptors[] = $descriptor ? ['name'  => $descriptor->getDescription(),
                                                        'value' => $descriptor->getMaxValue(),
                        ] : null;
                    }
                    $indicators[] = [
                        'name'        => $indicator->getName(),
                        'descriptors' => $descriptors,
                    ];
                }
                $capabilities[] = [
                    'name'       => $capability->getName(),
                    'maxValue'   => $capUsage ? $capUsage->getMaxValue() : null,
                    'indicators' => $indicators,
                ];
            }
            $this->capabilities = $capabilities;
        }
        return $this->capabilities;
    }
}
