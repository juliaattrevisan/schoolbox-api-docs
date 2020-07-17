<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Grid\Importer;

use UnexpectedValueException;

/**
 * Factory for building an importer out of a CSV.
 */
class CsvImporterFactory
{
    /**
     * Attempt to parse a CSV import from a file.
     *
     * @param resource $fp
     * @param int      $maxLevels The max number of levels to parse
     * @return CsvImporter
     * @throws UnexpectedValueException if the header isn't parsable as a
     *                                  rubric CSV
     *                                  (must have at least 3 columns)
     */
    public function build($fp, int $maxLevels): CsvImporter
    {
        $headerRow = $this->readCsvLine($fp, $maxLevels);
        if (!is_array($headerRow) || count($headerRow) < 3) {
            throw new UnexpectedValueException('Not a valid rubric CSV file');
        }

        // First two cells may be settings:
        // useMarkRange:      On/Off
        // capabilityMarkType: int|null
        $useMarkRange       = false;
        $capabilityMarkType = null;
        $capabilityGradeGroup = null;
        $settingCells      = [$headerRow[0], $headerRow[1], $headerRow[2]];
        foreach ($settingCells as $settingCell) {
            $settingPair = explode(':', $settingCell);
            $setting     = $settingPair[0] ?? '';
            $value       = trim($settingPair[1] ?? '');

            switch (strtolower($value)) {
                case '1':
                case 'yes':
                case 'on':
                    $boolValue = true;
                    break;

                case '0':
                case 'no':
                case 'off':
                default:
                    $boolValue = false;
                    break;
            }

            switch (trim($setting)) {
                case 'useMarkRange':
                    $useMarkRange = $boolValue;
                    break;

                case 'capabilityMarkType':
                    $capabilityMarkType = (int)$value;
                    break;

                case 'capabilityGradeGroup':
                    $capabilityGradeGroup = (int)$value;
                    break;

                default:
                    break;
            }
        }

        // Note: this used to be able to tell if the first row was actually the
        // first capability, instead of the set of levels. (This would happen if
        // Excel saved a CSV with nothing at all in the first row.)
        // Because the first two cells are now reserved for settings, they
        // should never be empty anymore; however, we can't tell anymore if the
        // spreadsheet used to have an empty first row.
        // (This was raised in SB-12175)

        $square[] = $headerRow;

        // list of levels
        $levels = array_slice($square[0], 2);

        $workingCapName = '';
        $workingIndName = '';

        // build the square, extracting data as we go
        $capabilities    = [];
        $isLastRowNewCap = false;
        while ($row = $this->readCsvLine($fp, $maxLevels)) {
            if ($isLastRowNewCap) {
                $capMaxValue     = $row[0];
                $isLastRowNewCap = false;
            } else {
                // list of indicators, grouped by capability
                if ($row[0]) {
                    $workingCapName  = $row[0];
                    $isLastRowNewCap = true;
                }
            }

            // copy the capability name into the row
            $row[0] = $workingCapName;

            if ($row[1]) {
                $workingIndName = $row[1];
            }
            // copy the indicator name into the row
            $row[1] = $workingIndName;

            // extract the indicator uniquely (can be blank)
            // (The capability values persist from previous rows)
            $capabilities[$workingCapName]['name']     = $workingCapName;
            $capabilities[$workingCapName]['maxValue'] = filter_var(
                $capMaxValue ?? '',
                FILTER_VALIDATE_INT,
                FILTER_NULL_ON_FAILURE
            );

            $square[] = $row;
        }
        return new CsvImporter($levels, array_values($capabilities), $square, $useMarkRange, $capabilityMarkType, $capabilityGradeGroup);
    }


    /**
     * Reads a line from a file pointer that points to a CSV.
     *
     * @param resource $fp
     * @param int      $maxLevels
     * @return array|null
     */
    private function readCsvLine($fp, int $maxLevels): ?array
    {
        $line = fgetcsv($fp);

        if ($line === null) {
            // EOF
            return null;
        }

        if (is_array($line)) {
            // normal data
            // Normalize line values
            return array_map(
                [$this, 'normalizeEncoding'],
                array_slice($line, 0, $maxLevels + 2)
            );
        }

        // bad data, but not EOF
        return [];
    }


    /**
     * Normalize encoding of a cell from the uploaded CSV.
     *
     * `$string` _should_ be UTF-8, but the CSV could've been saved in another
     * encoding. (So far, we only recognise Windows-1252)
     *
     * @param string $string
     * @return string
     */
    private function normalizeEncoding(string $string): string
    {
        // Assume $string is utf-8 encoded, unless proven otherwise
        // If it isn't, assume it's Windows-1252
        $encoding = mb_detect_encoding($string, ['UTF-8', 'Windows-1252', 'ISO-8859-1'], true);

        switch ($encoding) {
            case 'Windows-1252':
            case 'CP1252':
            case 'ISO-8859-1':
                // All the CP1252 test strings I used detected as ISO-8859-1.
                // You actually want CP1252
                return mb_convert_encoding($string, 'UTF-8', 'CP1252');

            case 'UTF-8':
            default:
                return $string;
        }
    }
}
