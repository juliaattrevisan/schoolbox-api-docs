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

class Controller extends Base
{
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
        $filename = sb_tempnam();
        $file = fopen($filename, 'w');
        $exporter = new Exporter($this->getDi()->get('database'));
        $exporter->generate($file);

        $now = date('Y-m-d-H-i-s');
        $newFilename = "results-{$now}.csv";

        /* Force download
         */
        if (file_exists($filename)) {
            header('Content-Description: File Transfer');
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $newFilename . '"');
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
