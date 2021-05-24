<?php

/**
 * Leave_Report_Catering_CSV
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Leave_Report_Catering_CSV extends Leave_Report_Catering_Abstract
{

    /**
     * Render
     */
    public function render()
    {
        $data = $this->getData();

        $this->getPage()->setAjax(true);

        header("Content-type: text/csv");
        header("Cache-Control: no-store, no-cache");
        header('Content-Disposition: attachment; filename="Catering Report.csv"');

        $stream = fopen("php://output", 'w');

        /* For each day, render a heading
         */
        $headers = array();
        $headers[] = 'House';

        $subheaders = array();
        $subheaders[] = '';

        foreach ($data['dateList'] as $currentDate) {
            $headers[] = $currentDate->getLocalShort();
            $headers[] = $currentDate->getLocalShort();
            $headers[] = $currentDate->getLocalShort();

            $subheaders[] = 'Breakfast';
            $subheaders[] = 'Lunch';
            $subheaders[] = 'Dinner';
        }

        fputcsv($stream, $headers);
        fputcsv($stream, $subheaders);

        /* For each house
         */
        if (is_array($data['houseList'][0]) && count($data['houseList'][0])) {
            for ($i = 0; $i < count($data['houseList'][0]); $i++) {
                $row = array();
                $row[] = $data['houseList'][0][$i]['name'];

                /* For each day
                 */
                for ($j = 0; $j < count($data['dateList']); $j++) {
                    $row[] = (int)$data['houseList'][$j][$i]['present_morning'];
                    $row[] = (int)$data['houseList'][$j][$i]['present_day'];
                    $row[] = (int)$data['houseList'][$j][$i]['present_night'];
                }

                fputcsv($stream, $row);
            }
        }

        /* For each day, render totals
         */
        $totals = array();
        $totals[] = 'Total';

        foreach ($data['totals'] as $currentSegment) {
            $totals[] = $currentSegment['present_morning'];
            $totals[] = $currentSegment['present_day'];
            $totals[] = $currentSegment['present_night'];
        }

        fputcsv($stream, $totals);

        fclose($stream);
    }
}
