<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\SummativeReport\Payload\VictorianProgression;

use Schoolbox\Assessment\Component\SubmissionBox\MarkType;

class Auditor
{
    /**
     * This worktype is required. In summative report, it's dynamically chosen
     * by the school
     */
    public const WORK_TYPE_NAME = 'Victorian Curriculum Standard';


    /**
     * This is how mariadb sorts a group concat, apparently
     * Anyway, this has to match the values configured in admin in this exact manner for now
     *
     * Though we only use 6-10.5, the school's configured HEAPS more
     */
    private const EXPECTED_OUTCOMES = '0.5, 1, 1.5, 10, 10.5, 11, 2, 2.5, 3, 3.5, 4.0, 4.5, 5, 5.5, 6, 6.5, 7, 7.5, 8, 8.5, 9, 9.5, A, B, C, D, F, F.5';


    /**
     * Constructor.
     *
     * @param \Schoolbox\Learning\Assessment\WorkType\Mapper $workTypeMapper
     */
    public function __construct(private \Schoolbox\Learning\Assessment\WorkType\Mapper $workTypeMapper)
    {
    }


    /**
     * Conduct audit on assessments using a particular worktype to ensure they're configured correctly
     * otherwise you will be missing data somewhere...thus becoming non-compliant.
     *
     * @return array
     * @throws \DomainException
     * @throws \PDOException
     */
    public function audit(): array
    {
        /* Find the Work Type Id first, by name for lack of a better way
         */
        try {
            $workType = $this->workTypeMapper->findOneByColumn('name', self::WORK_TYPE_NAME);
            $workTypeId = (int)$workType->getId();
        } catch (\Schoolbox\Mapper\Exception $e) {
            throw new \DomainException(sprintf(
                'Missing required worktype in admin: %s',
                self::WORK_TYPE_NAME
            ));
        }

        $query = "
            SELECT 
                a.id, 
                a.title,
                smt1.slug AS duework_marktype,
                smt2.slug AS quiz_marktype,
                GROUP_CONCAT(sg1.caption ORDER BY sg1.caption SEPARATOR ', ') AS duework_possible_outcomes,
                GROUP_CONCAT(sg2.caption ORDER BY sg2.caption SEPARATOR ', ') AS quiz_possible_outcomes
            FROM assessment a
            LEFT JOIN submission_box sb ON sb.assessment_id = a.id           
            LEFT JOIN submission_grade sg1 ON sb.submission_grade_group_id = sg1.submission_grade_group_id 
            LEFT JOIN submission_mark_type smt1 ON sb.mark_type = smt1.mark_type_id
            LEFT JOIN assess quiz on quiz.assessment_id = a.id
            LEFT JOIN submission_grade sg2 ON quiz.grade_group_id = sg2.submission_grade_group_id
            LEFT JOIN submission_mark_type smt2 ON quiz.mark_type_id = smt2.mark_type_id
            WHERE work_type_id = :workTypeId
            AND a.deleted_at IS NULL
            GROUP BY a.id 
            ORDER BY a.id
        ";

        $statement = $this->workTypeMapper->getDatabase()->prepare($query);
        $statement->execute([
            'workTypeId' => $workTypeId
        ]);

        /* Compile a list of issues here
         */
        $errors = [];

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            /* Letter Grade mark type must be used in this Due Work
             */
            if ('' !== (string)$row['duework_marktype'] && MarkType::SLUG_LETTER !== $row['duework_marktype']) {
                $errors[$row['id']][] = sprintf(
                    'Assessment %s (%s) is not using a Letter Grade mark type.',
                    $row['id'],
                    $row['title']
                );
            }

            /* Letter Grade mark type must be used in this Quiz Work
             */
            if ('' !== (string)$row['quiz_marktype'] && MarkType::SLUG_LETTER !== $row['quiz_marktype']) {
                $errors[$row['id']][] = sprintf(
                    'Assessment %s (%s) is not using a Letter Grade mark type.',
                    $row['id'],
                    $row['title']
                );
            }

            /* Grade Group is not available for this assessment in either the
             * Due Work or Quiz context. Assume it's missing entirely.
             */
            if ('' === (string)$row['duework_possible_outcomes'] && '' === (string)$row['quiz_possible_outcomes']) {
                $errors[$row['id']][] = sprintf(
                    'Assessment %s (%s) is not using a valid grade group.',
                    $row['id'],
                    $row['title']
                );
            }

            /* Possible outcomes don't match what we need for compliance
             * Schools must reconfigure admin
             *
             * This due work isn't properly configured
             */
            if ('' !== (string)$row['duework_possible_outcomes'] && self::EXPECTED_OUTCOMES !== $row['duework_possible_outcomes']) {
                $errors[$row['id']][] = sprintf(
                    'Assessment %s (%s) is using a misconfigured grade group; check admin settings.',
                    $row['id'],
                    $row['title']
                );
            }

            /* This quiz isn't properly configured
             */
            if ('' !== (string)$row['quiz_possible_outcomes'] && self::EXPECTED_OUTCOMES !== $row['quiz_possible_outcomes']) {
                $errors[$row['id']][] = sprintf(
                    'Assessment %s (%s) is using a misconfigured grade group; check admin settings.',
                    $row['id'],
                    $row['title']
                );
            }
        }

        return $errors;
    }
}
