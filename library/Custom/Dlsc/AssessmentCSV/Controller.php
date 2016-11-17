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
        fclose($file);

        $now = date('Y-m-d-H-i-s');
        $newFilename = "results-{$now}.csv";

        /* Force download
         */
        if (file_exists($filename)) {

            $headers  = $this->getResponse()->headers;

            // per header
            $headers->set('Content-Description', 'File Transfer');
            $headers->set('Content-Type', 'text/csv');
            $headers->set('Content-Description', 'File Transfer');
            $headers->set('Content-Disposition', 'attachment; filename="' . $newFilename . '"');
            $headers->set('Expires', 0);
            $headers->set('Cache-Control', 'must-revalidate');
            $headers->set('Pragma', 'public');
            $headers->set('Content-Length', filesize($filename));

            $this->getResponse()->content->set([
                'format'     => 'file',
                'file'       => $filename,
                'postOutput' => [function () use ($filename) {
                    /* Delete the temp file
                     */
                    unlink($filename);
                }]
            ]);
        }
    }
}
