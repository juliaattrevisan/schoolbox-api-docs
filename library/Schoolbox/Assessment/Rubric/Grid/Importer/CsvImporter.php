<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Grid\Importer;

/**
 * Helper class for importing a rubric grid from a CSV.
 */
class CsvImporter
{
    /**
     * The levels in the imported CSV.
     *
     * @var string[]
     */
    private $levels = [];


    /**
     * Each row (aside from the levels) read from the imported CSV.
     *
     * An iteration method will be provided to retrieve only capabilities,
     * then the indicators within, and finally the descriptors within each
     * indicator.
     *
     * @var string[]
     */
    private $square = [];


    /**
     * Does the imported CSV use mark ranges?
     *
     * @var bool
     */
    private $useMarkRange = false;


    /**
     * Does the imported CSV have per-capability marks?
     *
     * @var bool
     */
    private $markPerCapability = false;


    /**
     * Constructor
     *
     * @param array $levels
     * @param array $square
     * @param bool  $useMarkRange
     * @param bool  $markPerCapability
     */
    public function __construct(array $levels, array $square, bool $useMarkRange, bool $markPerCapability)
    {
        $this->levels            = $levels;
        $this->square            = $square;
        $this->useMarkRange      = $useMarkRange;
        $this->markPerCapability = $markPerCapability;
    }
}
