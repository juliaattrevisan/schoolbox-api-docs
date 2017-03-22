<?php
/**
 * CSV exporter for Accelerus
 *
 * @author    Doug MacKenzie <doug.mackenzie@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Exporter;

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
     * Generate a csv of assessment result data in a specific format for Accelerus importing
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
                            folder_code.code,
                            lu_submission_type.name AS type_name
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
            INNER JOIN      folder ON 
                            folder.id = af.folder_id OR folder.id = ci.folder_id
            INNER JOIN      folder_code ON
                            folder_code.folder_id = folder.id
            INNER JOIN      `user` AS u ON 
                            sr.owner = u.id
            LEFT JOIN       lu_submission_type ON 
                            lu_submission_type.id = sb.type
            WHERE           YEAR(sr.created_at) = :currentYear
                            AND sr.deleted_at IS NULL
                            AND sr.mark != ''
                            AND sr.published_at IS NOT NULL
            ORDER BY        folder_code.code
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

            /*
             * Check if there are square brackets inside the submission box name, if so - pull the contents from
             * inside the square brackets and use as assessment code (original way this was built for DLSC).
             * If square brackets aren't found then use the assessment type (new way that will be standard).
             * Note: the square bracket check will be phased out when we have moved De La Salle's assessment codes
             * from inside the title to assessment types.
             */
            $openBracketPos = strpos($result['name'], '[');
            $closeBracketPos = strrpos($result['name'], ']');
            if ($openBracketPos === false && $closeBracketPos === false) {
                $codeLength = $closeBracketPos - $openBracketPos;
                $assessmentCode = substr($result['name'], $openBracketPos + 1, $codeLength - 1);
            } else {
                $assessmentCode = trim($result['type_name']);
            }

            $data[] = [
                $headings[0] => $result['synergy_id'],
                $headings[1] => $result['code'],
                $headings[2] => $assessmentCode,
                $headings[3] => $result['mark']
            ];
        }

        $writer = new CsvWriter($file, $data);
        $writer->write();

        return $file;
    }
}
