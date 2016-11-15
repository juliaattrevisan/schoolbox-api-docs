<?php
/**
 * CSV exporter for De La Salle's Accelerus import
 *
 * @author    Doug MacKenzie <doug.mackenzie@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Custom\Dlsc\AssessmentCSV;

use Alaress\Csv\Writer AS CsvWriter;

class Exporter
{
    /**
     * @var \Database
     */
    private $database;

    /**
     * Exporter constructor.
     * @param \Database $database
     */
    public function __construct(\Database $database)
    {
        $this->database = $database;
    }

    /**
     * Generate a csv of assessment result data in a specific format for Accellerus importing
     *
     * @param resource $file
     * @return resource
     */
    public function generate($file)
    {
        $query = "
            SELECT          sr.owner,
                            sr.norm_mark,
                            sb.name,
                            cc.code
            FROM            submission_return AS sr
            INNER JOIN      submission_box AS sb ON
                            sb.id = sr.box_id
            INNER JOIN      folder AS c ON 
                            c.id = sb.category
            INNER JOIN      categoryCode AS cc ON
                            cc.categoryID = c.id
            WHERE           YEAR(sr.returned) = :currentYear AND 
                            sb.name LIKE '%[%' AND sb.name LIKE '%]%'
            ORDER BY        cc.code
        ";

        $statement = $this->database->prepare($query);
        $statement->execute([
            'currentYear' => date('Y')
        ]);

        $headings = [
            'student code',
            'class code',
            'assessment item code',
            'result'
        ];

        $data = [];

        foreach ($statement as $result) {

            /* Assessment codes will be stored in square brackets in the submission
             * box name.
             */
            $openBracketPos = strpos($result['name'], '[');

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

        $writer = new CsvWriter($file, $data);
        $writer->write();

        return $file;
    }
}