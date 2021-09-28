<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Grid\Importer;

use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWorkModel;
use UnexpectedValueException;

/**
 * Base class for CSV importers.
 *
 * Implements the basic operation of the CSV import process, but leaves a hole
 * for a subclass to implement its own `convertMarkTypeSlugToId` implementation.
 */
abstract class AbstractCsvImporterFactory
{
    /**
     * Attempt to parse a CSV import from a file.
     *
     * @param resource     $fp
     * @param int          $maxLevels The max number of levels to parse
     * @param DueWorkModel $dueWork   The due work `$fp` is to be imported into.
     *                                Required for legacy mark-per-capability
     *                                import: this will set the cap mark type
     *                                and grade group field to this due work's.
     *
     * @return CsvImporter
     * @throws UnexpectedValueException if the header isn't parsable as a
     *                                  rubric CSV
     *                                  (must have at least 3 columns)
     */
    public function build($fp, int $maxLevels, DueWorkModel $dueWork): CsvImporter
    {
        $headerRow = $this->readCsvLine($fp, $maxLevels);
        if (!is_array($headerRow) || count($headerRow) < 3) {
            throw new UnexpectedValueException('Not a valid rubric CSV file');
        }

        // First three cells may be settings:
        // useMarkRange:       On/Off
        // capabilityMarkType: string (a MarkType::SLUG_* constant), followed by an int grade group
        $useMarkRange           = false;
        $capabilityMarkTypeSlug = MarkType::SLUG_NONE;
        $capabilityGradeGroup   = null;
        $settingCells           = [$headerRow[0], $headerRow[1]];
        foreach ($settingCells as $settingCell) {
            $settingPair = explode(':', $settingCell, 2);
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
                    [$capabilityMarkTypeSlug, $capabilityGradeGroup] = array_pad(
                        array_map('trim', explode(':', $value)),
                        2,
                        null
                    );
                    if (!empty($capabilityGradeGroup)) {
                        $capabilityGradeGroup = (int)$capabilityGradeGroup;
                    } else {
                        $capabilityGradeGroup = null;
                    }
                    break;

                // Legacy mark-per-capability setting, superseded by capabilityMarkType.
                // if on: set capability mark type + grade group to due work's, or Total if that type is None
                // If off: set capability mark type to None
                case 'markPerCapability':
                    $capabilityGradeGroup = null;
                    if ($boolValue) {
                        // No known mark type?
                        // Fall back to Percent for now, as that's the default type for new due works
                        $markType               = $dueWork->getMarkType();
                        $markTypeSlug           = $markType instanceof MarkType
                            ? $markType->getSlug()
                            : MarkType::SLUG_PERCENT;
                        $capabilityMarkTypeSlug = $markTypeSlug === MarkType::SLUG_NONE
                            ? MarkType::SLUG_TOTAL
                            : $markTypeSlug;
                        if ($capabilityMarkTypeSlug === MarkType::SLUG_LETTER && $dueWork->getGradeGroup()) {
                            $capabilityGradeGroup = $dueWork->getGradeGroup()->getId();
                        }
                    } else {
                        $capabilityMarkTypeSlug = MarkType::SLUG_NONE;
                    }
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
        $levelCount      = count($levels);
        while ($row = $this->readCsvLine($fp, $maxLevels)) {
            /* If the $row is just a bunch of zero-length strings, there's no meaningful data here
             * Just skip it, instead of trying to process empty rows somehow "intuitively"
             */
            $trimmedRow = array_filter($row, static function ($element) {
                return '' !== (string)$element;
            });

            if (!\count($trimmedRow)) {
                continue;
            }

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

            // How many descriptors are there in this row?
            // We'll need to ensure every descriptor has a matching level.
            $rowDescriptorCount = count($row) - 2;
            $levelCount         = max($levelCount, $rowDescriptorCount);
            $square[]           = $row;
        }

        // The square may have more columns than there are levels.
        // Expand the levels to the size of the square.
        $levels = array_pad($levels, $levelCount, '');

        return new CsvImporter(
            $levels,
            array_values($capabilities),
            $square,
            $useMarkRange,
            $this->convertMarkTypeSlugToId($capabilityMarkTypeSlug),
            $capabilityGradeGroup
        );
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


    /**
     * Converts the mark type slug in the CSV import to its corresponding ID.
     *
     * @param string|null $slug
     * @return int|null
     */
    abstract protected function convertMarkTypeSlugToId(?string $slug): ?int;
}
