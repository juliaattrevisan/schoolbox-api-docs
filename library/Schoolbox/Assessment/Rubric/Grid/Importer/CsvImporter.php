<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
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
     * @return string[]
     */
    public function getLevels(): array
    {
        return $this->levels;
    }


    /**
     * @return array[]
     */
    public function getCapabilities(): array
    {
        return $this->capabilities;
    }


    /**
     * Retrieves the capabilities for this importer, with all their indicators
     * and descriptors included.
     *
     * @return array
     */
    public function retrieveCapabilitiesWithIndicators(): array
    {
        $result = [];
        foreach ($this->capabilities as $capability) {
            $capability['indicators'] = $this->iterateIndicators($capability['name']);
            $result[] = $capability;
        }
        return $result;
    }


    /**
     * Iterate over the indicators supplied to this importer for the given
     * capability.
     *
     * @param string $capabilityName
     * @return array[]
     */
    private function iterateIndicators(string $capabilityName): array
    {
        $result      = [];
        $squareCount = count($this->square);
        for ($i = 1; $i < $squareCount; $i += 2) {
            [$capName, $indName] = $this->square[$i];
            if ($capName === $capabilityName) {
                // Parse out descriptor values
                $descriptorNames  = array_slice($this->square[$i], 2);
                $descriptorValues = $this->square[$i + 1] ? array_slice($this->square[$i + 1], 2) : null;
                $descriptors      = [];
                $indicatorInUse   = false;
                foreach ($descriptorNames as $level => $descriptorName) {
                    $descriptorValue = $descriptorValues[$level] ?? '';
                    if (!($descriptorName === '' && $descriptorValue === '')) {
                        $indicatorInUse      = true;
                        $descriptors[$level] = [
                            'name'  => $descriptorName,
                            'value' => filter_var($descriptorValue, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
                        ];
                    } else {
                        $descriptors[$level] = null;
                    }
                }

                // Don't use the indicator if it has no descriptors
                if ($indicatorInUse) {
                    $result[] = [
                        'name'        => $indName,
                        'descriptors' => $descriptors,
                    ];
                }
            }
        }
        return $result;
    }
}
