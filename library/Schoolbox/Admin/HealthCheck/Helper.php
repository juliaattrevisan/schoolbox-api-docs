<?php

namespace Schoolbox\Admin\HealthCheck;

use Alaress\Database\Statement\Query\IQuery;

class Helper
{
    /**
     * converts a db query to an associated array object
     * @param IQuery $query
     * @return array
     */
    public function queryToArray(IQuery $query)
    {
        $outputArray = [];
        while ($row = $query->fetch(\PDO::FETCH_ASSOC)) {
            $tmpArray = [];
            //iterate through each entry in the row
            foreach ($row as $label => $value) {
                //check each column in the row
                //if they are numeric ignore them, and continue
                //using the assumption the column headers will NOT be numbers, but be alpha strings
                if (is_numeric($label)) {
                    continue;
                }
                //label is not an int, add the value array object
                $tmpArray[$label] = $value;
            }
            $outputArray[] = $tmpArray;
        }
        return $outputArray;
    }

    /**
     * converts a db query to a csv return
     * will build the csv headers
     * @param IQuery $query
     * @param string $filename
     * @return void
     */
    public function queryToCSV(IQuery $query, string $filename)
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
                //check each column in the row
                //if they are numeric ignore them, and continue
                //using the assumption the column headers will NOT be numbers, but be alpha strings
                if (is_numeric($label)) {
                    continue;
                }
                //label is not an int, add the value array object
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
    }
}
