<?php

namespace Schoolbox\Admin\HealthCheck;

use PDOStatement;

class Helper
{
    /**
     * converts a db query to an associated array object
     * @param PDOStatement $query
     * @return array
     */
    public function queryToArray(PDOStatement $query)
    {
        $outputArray = [];
        while ($row = $query->fetch(\PDO::FETCH_ASSOC)) {
            $tmpArray = [];
            //iterate through each entry in the row
            foreach ($row as $label => $value) {
                //label is the column heading, add the value array object
                $tmpArray[$label] = $value;
            }
            $outputArray[] = $tmpArray;
        }
        return $outputArray;
    }

    /**
     * converts a db query to a csv return
     * will build the csv headers
     * @param PDOStatement $query
     * @param string $filename
     * @return void
     */
    public function queryToCSV(PDOStatement $query, string $filename)
    {
        header('Content-type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"$filename\"");
        $fp = fopen("php://output", 'w');
        $rowHeadings = [];
        $sentHeaders = false;
        while ($row = $query->fetch(\PDO::FETCH_ASSOC)) {
            $rowOutput = [];
            //iterate through each entry in the row
            foreach ($row as $label => $value) {
                //label is the heading, add the value array object
                $rowHeadings[] = $label;
                $rowOutput[] = $value;
            }
            //send the headers as a csv object, but only the first time
            if (!$sentHeaders) {
                fputcsv($fp, $rowHeadings);
                //set to true, so it does not send the headers again
                $sentHeaders = true;
            }
            fputcsv($fp, $rowOutput);
        }
        exit;
    }
}
