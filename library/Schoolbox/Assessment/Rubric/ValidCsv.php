<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric;

use Schoolbox\Resource\Exception;

class ValidCsv
{
    /**
     * Field delimiter for the CSV file
     *
     * @var string
     */
    private const string DELIMITER = ',';


    /**
     * Field enclosure for the CSV file
     *
     * @var string
     */
    private const string ENCLOSURE = '"';


    /**
     * Field escaper for the CSV file
     *
     * As of 8.4, this is an empty string:
     *
     * @var string
     */
    private const string ESCAPE = '';


    /**
     * A unique value of a cell
     */
    private const string IDENTIFIER_CAPABILITY = 'capabilityMarkType:';


    /**
     * Another unique value of a cell
     */
    private const string IDENTIFIER_MARK_RANGE = 'useMarkRange:';


    /**
     * For the given file contents $input, figure out if it's valid rubric csv contents.
     *
     * Rubric CSVs have a distinct signature: its A1 and B1 cells have values that are idiosyncratic and shouldn't appear
     * anywhere else in the schoolbox universe which lets us use this conclusively.
     *
     * Further analysis can be added to ensure not only that the csv was a rubric csv, but that its contents were also
     * meaningful: however, the depth of that meaningfulness might be too strict for how the schools naturally operate.
     *
     * ->isSatisfiedBy($this->filesystemProxy->readStream($this->file))
     *
     * @param resource $filePointer
     * @return bool
     * @throws Exception
     */
    public function isSatisfiedBy($filePointer): bool
    {
        /* Primary checks
         * These must pass in order for us to even THINK about importing csv contents
         */

        /* $filePointer is a return from readStream() but we have to skip some OS-specific stuffs first
         */
        $fp = (new \Alaress\Csv\ValidCSV($filePointer))->skipBom();

        $metadata = stream_get_meta_data($fp);

        // @phpstan-ignore-next-line: stream_get_meta_data _can_ contain seekable
        $seekable = $metadata['seekable'] ?? false;

        /* TODO Find out what this meant: I assume it's important because of its contextual usage in Reader
         *  from where I lifted this idea, although ValidCSV already rewinds pointers already. Not entirely sure what
         *  seekable was meant to provide atm: see SA-11675. It must be that we presume the ability to use fseek
         *  somewhere else in a csv processing, although in our use case, it's a one way trip.
         */
        if (!$seekable) {
            throw new \RuntimeException('File stream does not support seeking');
        }

        /* Find the first row
         * The docblock for fgetcsv suggests array|false, but also array|false|null
         */
        $firstRow = fgetcsv($fp, 0, self::DELIMITER, self::ENCLOSURE, self::ESCAPE);

        /* falsey input: exit early
         */
        if (false === $firstRow) {
            return false;
        }

        /* no columns, for whatever reason
         */
        if (!\count($firstRow)) {
            return false;
        }

        /* A1 and B1 cells do not contain unique rubric signature: exit early
         */
        if (!isset($firstRow[0]) || !isset($firstRow[1])) {
            return false;
        }

        /* First unique marker not present: exit early
         * Note that this is a case-insensitive detection in case people had tinkered on their own, and to support
         * legacy csv files which might have lowercased the t
         */
        if (!str_contains(strtolower($firstRow[0]), strtolower(self::IDENTIFIER_CAPABILITY))) {
            return false;
        }

        /* Second unique marker not present: exit early
         * Note that this is a case-insensitive detection in case people had tinkered on their own, and to support
         * legacy csv files which might have lowercased some of the letters randomly. We haven't seen this occur in the
         * wild yet, but given what happened to capabilityMarkType/capabilityMarktype, I can't discount it from also
         * occurring here
         */
        if (!str_contains(strtolower($firstRow[1]), strtolower(self::IDENTIFIER_MARK_RANGE))) {
            return false;
        }

        /* only one row detected: exit early
         */
        $secondRow = fgetcsv($fp, 0, self::DELIMITER, self::ENCLOSURE, self::ESCAPE);

        if (!$secondRow) {
            return false;
        }

        /* The first two columns of the second row (a capability and an indicator, respectively) were set and meaningful
         * which denotes a -close enough- start to making a grid.
         */
        if (isset($secondRow[0]) && isset($secondRow[1])) {
            /* A meaningful capability wasn't mentioned
             * A whitespace-only string is acceptable as some schools don't require both levels to evaluate students, they only need one
             */
            // if ('' === trim((string)$secondRow[0])) {
            if ('' === (string)$secondRow[0]) {
                return false;
            }

            /* A meaningful indicator wasn't mentioned
             * A whitespace-only string is acceptable as some schools don't require both levels to evaluate students, they only need one
             */
            // if ('' === trim((string)$secondRow[1])) {
            if ('' === (string)$secondRow[1]) {
                return false;
            }

            /* Secondary checks
             * These are more nuanced "requirements" that might make things too difficult for actual schools on a daily
             * basis as it thwarts what they are familiar with doing. Add (or remove) secondary checks as needed.
             *
             * If this is too strict, we can exit even earlier right here.
             */
            // return true;

            /* Otherwise, keep interrogating deeper.
             *
             * At least one of the following is mentioned:
             *     - A cell with descriptor text
             *     - A cell with descriptor points
             *     - Two cells to fulfil both of the above
             *
             * I think this gets a bit more nuanced because a descriptor can exist anywhere in a row.
             * This, I think, also starts limiting what kind of csv contents you can import, especially if you are trying
             * to propagate grid settings rather than grid contents.
             */

            /* Look for the first descriptor.
             * It's in the second row if it's a text-based descriptor.
             * Otherwise, it's in the third row for a points-only based descriptor
             */
            $n = count($secondRow);

            for ($i = 2; $i < $n; $i++) {
                $cell = $secondRow[$i];

                /* Found a descriptor text somewhere in this row
                 * A whitespace-only string is acceptable as some schools don't require both levels to evaluate students, they only need one
                 */
                // if ('' !== trim((string)$cell)) {
                if ('' !== (string)$cell) {
                    return true;
                }
            }

            /* Otherwise, try looking in the third row; a descriptor's points might be here.
             */
            $thirdRow = fgetcsv($fp, 0, self::DELIMITER, self::ENCLOSURE, self::ESCAPE);

            if ($thirdRow) {
                $n = count($thirdRow);

                for ($i = 2; $i < $n; $i++) {
                    $cell = $thirdRow[$i];

                    /* Found a descriptor points somewhere in this row
                     * A whitespace-only string is acceptable as some schools don't require both levels to evaluate students, they only need one
                     */
                    // if ('' !== trim((string)$cell)) {
                    if ('' !== (string)$cell) {
                        return true;
                    }
                }
            }
        }

        /* Deny by default
         */
        return false;
    }
}
