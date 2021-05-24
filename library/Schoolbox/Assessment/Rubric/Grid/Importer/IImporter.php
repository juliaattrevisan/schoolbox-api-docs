<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Grid\Importer;

interface IImporter
{
    /**
     * @return int|null
     */
    public function getCapabilityMarkType(): ?int;

    /**
     * @return int|null
     */
    public function getCapabilityGradeGroup(): ?int;

    /**
     * @return string[]
     */
    public function getLevels(): array;

    /**
     * Retrieves the capabilities for this importer, with all their indicators
     * and descriptors included.
     *
     * @return array
     */
    public function retrieveCapabilitiesWithIndicators(): array;

    /**
     * @return bool
     */
    public function isUseMarkRange(): bool;
}
