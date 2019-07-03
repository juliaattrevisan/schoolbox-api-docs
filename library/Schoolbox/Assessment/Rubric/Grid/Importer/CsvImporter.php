<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Grid\Importer;

/**
 * Helper class for loading rubric grids from CSV.
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
     * Attempt to parse a CSV import from a file.
     *
     * @param resource $fp
     * @param int      $maxLevels The max number of levels to parse
     * @throws \Schoolbox\Resource\Exception
     */
    public function parse($fp, int $maxLevels): void
    {
        $headerRow = $this->readCsvLine($fp, $maxLevels);

        // must be an array, and must have at least 3 "cells"
        if (!is_array($headerRow) || count($headerRow) < 3) {
            throw new \Schoolbox\Resource\Exception("Not a valid rubric CSV file");
        }

        // First two cells may be settings:
        // useMarkRange:      On/Off
        // markPerCapability: On/Off
        $settingCells = [$headerRow[0], $headerRow[1]];
        foreach ($settingCells as $settingCell) {
            [$setting, $value] = explode(':', $settingCell);
            switch (strtolower(trim($value))) {
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
                    $this->useMarkRange = $boolValue;
                    break;

                case 'markPerCapability':
                    $this->markPerCapability = $boolValue;
                    break;

                default:
                    break;
            }
        }

        // Note: this used to be able to tell if the first row was actually the
        // first group, instead of the set of levels. (This would happen if
        // Excel saved a CSV with nothing at all in the first row.)
        // Because the first two cells are now reserved for settings, they
        // should never be empty anymore; however, we can't tell anymore if the
        // spreadsheet used to have an empty first row.
        // (This was raised in SB-12175)

        $square[] = $headerRow;

        // list of levels
        $this->levels = array_slice($square[0], 2);

        $workingGroupName = '';
        $workingCritName = '';

        // build the square, extracting data as we go
        $groups            = [];
        $isLastRowNewGroup = false;
        while ($row = $this->readCsvLine($fp, $maxLevels)) {
            if ($isLastRowNewGroup) {
                $groupMaxValue     = $row[0];
                $isLastRowNewGroup = false;
            } else {
                // list of criteria, grouped by... uh, group
                if ($row[0]) {
                    $workingGroupName  = $row[0];
                    $isLastRowNewGroup = true;
                }
            }

            // copy the groupname into the row
            $row[0] = $workingGroupName;

            if ($row[1]) {
                $workingCritName = $row[1];
            }
            // copy the criteria name into the row
            $row[1] = $workingCritName;

            // extract the criterion uniquely (can be blank)
            // (The group values persist from previous rows)
            $groups[$workingGroupName]['id']                         = null;
            $groups[$workingGroupName]['name']                       = $workingGroupName;
            $groups[$workingGroupName]['maxValue']                   = $groupMaxValue !== '' ? $groupMaxValue : null;
            $groups[$workingGroupName]['criteria'][$workingCritName] = [
                'id'   => null,
                'name' => $workingCritName,
            ];

            $this->square[] = $row;
        }
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
                \array_slice($line, 0, $maxLevels + 2)
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
