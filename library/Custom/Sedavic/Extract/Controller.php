<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Sedavic\Extract;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Exception;
use Alaress\Web\Controller\Base;

class Controller extends Base
{
    /**
     * A repeatable query for each person
     */
    private const QUERY_USER_INFO = '
        SELECT
            vmrf.assessment_id,
            u.firstname,
            u.lastname,
            u.synergy_id,
            h.house,
            lyl.name AS yearLevel
        FROM v_most_recent_feedback vmrf
        INNER JOIN `user` u ON u.id = vmrf.student_id
        LEFT JOIN lu_year_level lyl ON u.year_id = lyl.id
        LEFT JOIN house h on u.house = h.id
        WHERE student_id = :userId
    ';


    /**
     * A repeatable query for each person's assessments
     */
    private const QUERY_ASSESSMENT_INFO = "
       SELECT 
            a.title,
            var.*,
            cn1.name AS Qualification,
            cn2.name AS Unit,
            cn3.name AS AreaOfStudy
        FROM curriculum_node_for_assessment cnof
        INNER JOIN assessment a ON cnof.assessment_id = a.id
        INNER JOIN v_assessment_relation var ON var.assessment_id = a.id
        LEFT JOIN curriculum_node cn1 ON cnof.curriculum_node_id = cn1.id
        LEFT JOIN curriculum_node cn2 ON cn2.curriculum_node_parent_id = cn1.id
        LEFT JOIN curriculum_node cn3 ON cn3.curriculum_node_parent_id = cn2.id
        WHERE a.id = :assessmentId
    ";


    /**
     * Prepared statements. For some reason the typehints tell you
     * it's an IQuery, but it's actually a one of these...what kind
     * of sloanish witchery is this??
     *
     * @var \Alaress\Database\Statement\Query\Base
     */
    private \Alaress\Database\Statement\Query\Base $userInfoStatement;
    private \Alaress\Database\Statement\Query\Base $assessmentInfoStatement;


    /**
     * @var ContextLookup
     */
    private ContextLookup $contextLookup;


    /**
     * Controller constructor.
     */
    public function __construct(private IConnector $database)
    {
        $this->contextLookup = new ContextLookup($database);

        $this->userInfoStatement = $database->prepareQuery(self::QUERY_USER_INFO);
        $this->assessmentInfoStatement = $database->prepareQuery(self::QUERY_ASSESSMENT_INFO);
    }


    /**
     * For each student, get all their assessment info and feedback data
     * and pump it into a csv.
     *
     * The date range provided relates to when the feedbacks were published.
     */
    public function getExtract(): void
    {
        /* Find a list of student ids to process one at a time
         */
        $studentManifestStatement = $this->getStudents();

        while ($userId = $studentManifestStatement->fetch(\PDO::FETCH_ASSOC)['student_id']) {
            /* For every student, get all the stuff they need
             *
             * First obtain their basic user details: name, identifiers, house and year level
             * names - because we're reusing this info per curriculum node and there'll be
             * a lot of those
             */
            $this->userInfoStatement->execute([
                'userId' => $userId
            ]);

            while ($userRow = $this->userInfoStatement->fetch(\PDO::FETCH_ASSOC)) {
                /* Find all the assessment info from the v_assessment_feedback table
                 * This contains all the info you need to find their marks, for both
                 * the normalised- and human-readable marks
                 */
                $this->assessmentInfoStatement->execute([
                    'assessmentId' => $userRow['assessment_id']
                ]);

                while ($assessmentInfo = $this->assessmentInfoStatement->fetch(\PDO::FETCH_ASSOC)) {
                    $context = $this->contextLookup->find((int)$assessmentInfo['assessment_id']);

                    new \Alaress_Probe($assessmentInfo, $context);

                }
            }



            /* Find all the curriculum codes associated with the assessments separately
             * since you kinda need the full hierarchy of a curriculum node
             */

            /* Find all the ancestry info about the assessment: course, unit
             */

            /* Combine all that into a csv row
             */

            /* Pump it to an fputcsv
             */
        }
    }


    /**
     * Get a bunch of students who have feedbacks
     *
     * @return \Alaress\Database\Statement\Query\Base
     * @throws Exception
     */
    private function getStudents(): \Alaress\Database\Statement\Query\Base
    {
        $query = "
            SELECT DISTINCT student_id
            FROM v_assessment_feedback vaf
            INNER JOIN `user` u ON u.id = vaf.student_id
            ORDER BY lastname, firstname
        ";

        $studentManifestStatement = $this->database->prepareQuery($query);
        $studentManifestStatement->execute();

        return $studentManifestStatement;
    }
}
