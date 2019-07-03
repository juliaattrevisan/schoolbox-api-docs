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
    private $levels;


    /**
     * The capabilities in the imported CSV.
     *
     * Each value contains these keys:
     * * name:     the capability's name
     * * maxValue: if supplied, the mark-per-capability value
     *
     * @var array[]
     */
    private $capabilities;


    /**
     * Each row (aside from the levels) read from the imported CSV.
     *
     * An iteration method will be provided to retrieve only capabilities,
     * then the indicators within, and finally the descriptors within each
     * indicator.
     *
     * @var string[]
     */
    private $square;


    /**
     * Does the imported CSV use mark ranges?
     *
     * @var bool
     */
    private $useMarkRange;


    /**
     * Does the imported CSV have per-capability marks?
     *
     * @var bool
     */
    private $markPerCapability;


    /**
     * Constructor
     *
     * @param array $levels
     * @param array $capabilities
     * @param array $square
     * @param bool  $useMarkRange
     * @param bool  $markPerCapability
     */
    public function __construct(array $levels, array $capabilities, array $square, bool $useMarkRange, bool $markPerCapability)
    {
        $this->levels            = $levels;
        $this->capabilities      = $capabilities;
        $this->square            = $square;
        $this->useMarkRange      = $useMarkRange;
        $this->markPerCapability = $markPerCapability;
    }


    /**
     * @return bool
     */
    public function isUseMarkRange(): bool
    {
        return $this->useMarkRange;
    }


    /**
     * @return bool
     */
    public function isMarkPerCapability(): bool
    {
        return $this->markPerCapability;
    }


    /**
     * Iterate over the levels supplied to this importer.
     *
     * @return iterable|string[]
     */
    public function iterateLevels(): iterable
    {
        foreach ($this->levels as $i => $level) {
            yield $i => $level;
        }
    }


    /**
     * Iterate over the capabilities supplied to this importer.
     *
     * @return iterable|array[]
     */
    public function iterateCapabilities(): iterable
    {
        foreach ($this->capabilities as $i => $capability) {
            yield $i => $capability;
        }
    }


    /**
     * Iterate over the indicators supplied to this importer for the given
     * capability.
     *
     * @param string $capabilityName
     * @return iterable
     */
    public function iterateIndicators(string $capabilityName): iterable
    {
        $squareCount = count($this->square);
        for ($i = 1; $i < $squareCount; $i += 2) {
            [$capName, $indName] = $this->square[$i];
            if ($capName === $capabilityName) {
                // Parse out descriptor values
                $descriptorNames  = array_slice($this->square[$i], 2);
                $descriptorValues = array_slice($this->square[$i+1], 2);
                $descriptors      = [];
                foreach ($descriptorNames as $level => $descriptorName) {
                    $descriptorValue = $descriptorValues[$level] ?? '';
                    if (!($descriptorName === '' && $descriptorValue === '')) {
                        $descriptors[$level] = [
                            'name'  => $descriptorName,
                            'value' => filter_var($descriptorValue, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
                        ];
                    }
                }

                yield [
                    'name'        => $indName,
                    'descriptors' => $descriptors,
                ];
            }
        }
    }
}
