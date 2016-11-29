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
            SELECT          u.synergy_id,
                            sr.mark,
                            sb.name,
                            cc.code
            FROM            submission_return AS sr
            INNER JOIN      submission_box AS sb ON
                            sb.id = sr.box_id
            LEFT JOIN       assessment_folder AS af ON 
                            af.assessment_id = sb.assessment_id
            LEFT JOIN       assessment_course_unit_implementation AS acui ON 
                            acui.assessment_id = sb.assessment_id
            LEFT JOIN       course_unit_implementation AS cui ON 
                            cui.id = acui.course_unit_implementation_id
            LEFT JOIN       course_implementation AS ci ON 
                            ci.id = cui.course_implementation_id
            INNER JOIN      folder AS c ON 
                            c.id = af.assessment_id OR c.id = ci.folder_id
            INNER JOIN      folder_code AS cc ON
                            cc.folder_id = c.id
            INNER JOIN      `user` AS u ON 
                            sr.owner = u.id
            WHERE           YEAR(sr.created_at) = :currentYear
                            AND sb.name LIKE '%[%]%'
                            AND sr.mark != ''
                            AND sr.published_at IS NOT NULL
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
            $closeBracketPos = strrpos($result['name'], ']');
            $codeLength = $closeBracketPos - $openBracketPos;
            $assessmentCode = substr($result['name'], $openBracketPos+1, $codeLength-1);

            if (strlen($assessmentCode) > 0) {
                $data[] = [
                    $headings[0] => $result['synergy_id'],
                    $headings[1] => $result['code'],
                    $headings[2] => $assessmentCode,
                    $headings[3] => $result['mark']
                ];
            }
        }

        $writer = new CsvWriter($file, $data);
        $writer->write();

        return $file;
    }
}