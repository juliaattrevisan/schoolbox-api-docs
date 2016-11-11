<?php
/**
 * Controller for De La Salle's assessment CSV export
 *
 * @author    Doug MacKenzie <doug.mackenzie@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Custom\Dlsc\AssessmentCSV;

use Alaress\Web\Controller\Base;
use Alaress\Csv\Writer AS CsvWriter;

class Controller extends Base
{
    /**
     * Generate a csv of assessment result data
     *
     * @param resource $file
     * @return resource
     */
    protected function generate($file)
    {
        $query = "
            SELECT          sr.owner,
                            sr.norm_mark,
                            sb.name,
                            cc.code
            FROM            submission_return AS sr
            INNER JOIN      submission_box AS sb ON
                            sb.id = sr.box_id
            INNER JOIN      category AS c ON 
                            c.id = sb.category
            INNER JOIN      categoryCode AS cc ON
                            cc.categoryID = c.id         
            ORDER BY        cc.code
        ";

        $database = \Database::getInstance();
        $statement = $database->prepare($query);
        $statement->execute();

        $headings = [
            'student code',
            'class code',
            'assessment item code',
            'result'
        ];

        $data = [];

        foreach ($statement as $result) {

            /* Assessment codes will be stored in square brackets in the submission
             * box name. Exclude any that don't have an assessment code.
             */
            $openBracketPos = strpos($result['name'], '[');

            if ($openBracketPos != false) {

                /* Get the assessment code out of the submission box name
                 */
                $closeBracketPos = strpos($result['name'], ']');
                $codeLength = $closeBracketPos - $openBracketPos;
                $assessmentCode = substr($result['name'], $openBracketPos+1, $codeLength-1);

                $data[] = [
                    $headings[0] => $result['owner'],
                    $headings[1] => $result['code'],
                    $headings[2] => $assessmentCode,
                    $headings[3] => $result['norm_mark']
                ];
            }
        }

        $writer = new CsvWriter($file, $data);
        $writer->write();

        return $file;
    }


    /**
     * The method that will be called nightly via a cron job
     *
     * @api GET /modules/custom/dlsc/assessmentcsv/generateNightly
     * @level 160
     *
     */
    public function generateNightly()
    {
        $file = fopen('testfile.csv', 'w');
        $this->generate($file);
    }


    /**
     * Download a fresh csv
     *
     * @api GET /modules/custom/dlsc/assessmentcsv/download
     * @level 160
     *
     */
    public function download()
    {

        /* Generate an updated csv
         */
        $filename = 'testfile_temp.csv';
        $file = fopen($filename, 'w');
        $this->generate($file);

        $now = date('Y-m-d-H-i-s');
        $newFilename = "results-{$now}.csv";

        /* Force download
         */
        if (file_exists($filename)) {
            header('Content-Description: File Transfer');
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $newFilename . '.csv"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($filename));

            readfile($filename);
        }

        /* Close the file pointer and delete the temp file
         */
        fclose($file);
        unlink($filename);

        exit();
    }

}
