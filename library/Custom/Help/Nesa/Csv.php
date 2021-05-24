<?php

/**
 * NESA Report CSV format
 *
 * Generates a csv report using the same data as the browser Report screen (Report.php)
 * Logic essentially the same as the twig template, report.twig
 *
 * Generates a temp file, then streams it to the browzer which eats it as a file
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Custom\Help\Nesa;

class Csv
{

    /**
     * Generate a csv output
     *
     * @param array $report
     * @param array $indicators
     * @return string
     */
    public function generate(array $report, array $indicators)
    {
        $fp = fopen('php://temp', 'w');

        /* Headings
         */
        $headings = array_merge(['User', 'PD Number'], $indicators);

        fputcsv($fp, $headings);

        /* Empty row - used to reset $data
         */
        $emptyRow = array_fill(0, \count($headings), null);

        /* Replicated logic from twig template
         * Can't assume ordering of these indicators so do it via hashtable lookup
         */
        foreach ($report as $currentRow) {
            $data = array_combine($headings, $emptyRow);
            $data['User'] = $currentRow['user']['fullname'];
            $data['PD Number'] = $currentRow['user']['number'];

            foreach ($indicators as $indicator) {
                $outcome = $currentRow['condensed'][$indicator]['outcome'];

                if (isset($currentRow['condensed'][$indicator]['outcome'])) {
                    if ($outcome) {
                        $data[$indicator] = 'Y';
                    } else {
                        $data[$indicator] = 'N';
                    }
                } else {
                    $data[$indicator] = '';
                }
            }

            fputcsv($fp, $data);
        }

        rewind($fp);

        $contents = stream_get_contents($fp);

        fclose($fp);

        return $contents;
    }
}
