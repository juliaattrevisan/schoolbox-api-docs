<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Admin\SummativeReport\Payload;

use Alaress\Database\Connector\IConnector;

/**
 * Victorian Curriculum Reporting Progression Point Visualisation
 * Built for PCC
 */
class VictorianProgression
{
    private const QUERY = "
        SELECT
            cn2.id AS parent_node_id,
            cn2.name AS parent_node,
            cn1.id AS curriculum_node_id,
            cn1.name AS curriculum_node,
            a.id AS assessment_id,
            a.title,
            a.due_date,
            vaf.feedback_table,
            vaf.feedback_id,
            vaf.student_id,
            vaf.published_at
        FROM curriculum_node_for_assessment cnoa
        INNER JOIN curriculum_node cn1 ON cnoa.curriculum_node_id = cn1.id
        LEFT JOIN curriculum_node cn2 ON cn1.curriculum_node_parent_id = cn2.id
        LEFT JOIN assessment a ON cnoa.assessment_id = a.id
        LEFT JOIN v_assessment_feedback vaf ON a.id = vaf.assessment_id
        WHERE a.due_date IS NOT NULL
        AND a.work_type_id = :workTypeId                        -- use the actual one corresponding to Vic thing
        AND vaf.student_id = :studentId                         -- filter to actual student
        AND vaf.published_at IS NOT NULL                        -- only accept published feedbackery
        AND vaf.published_at BETWEEN :startDate AND :endDate    -- specify dates of academic period(s) in use
        ORDER BY cn1.id, a.due_date, vaf.published_at DESC
    ";


    /**
     * @param IConnector $db
     */
    public function __construct(private IConnector $db)
    {
    }
}