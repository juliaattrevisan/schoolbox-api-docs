<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\APIAssessment;

use Alaress\Database\Connector\IConnector;
use Alaress\Web\Controller\Base;
use Crell\ApiProblem\ApiProblem;
use PDO;
use Schoolbox\UserMapper;

class Controller extends Base
{

    protected Helper $helper;

    protected IConnector $dbMapper;
    /**
     * Controller constructor.
     *
     */
    public function __construct()
    {
        $this->helper = new Helper();
    }

    /**
     * Returns an assessment object
     *
     * @api /api/assessment/{assessmentId}
     * @link TODO:
     */
    public function apiAssessmentGet(string $assessmentId)
    {
        if (!is_numeric($assessmentId)) {
            $problem = (new ApiProblem('Assessment not found, ID must be a number'))
                ->setStatus(404)
                ->asArray();
            $this->getResponse()->status->setCode(404);
            $this->getResponse()->content->set($this->buildJsonOutput($problem));
            return; //exit early the user is part of the group.
        }

        //get all assessment info for object

        $query  = "
SELECT
    assessment.id, assessment.title, assessment.weight,
    COALESCE(task_completion.completion_date, submission_return.created_at) as `completionDate`,
    v_most_recent_feedback.feedback_table, v_most_recent_feedback.feedback_id, v_most_recent_feedback.student_id,

    COALESCE(submission_return.comment, lti_assessment_score.comment) as `comment`,
    COALESCE(submission_return.mark, lti_assessment_score.score_given) as `mark`,
    submission_return.norm_mark,
    submission_return.resubmission_required as `resubmission_required`,
    COALESCE(task_completion.marking_user_id, submission_return.marker, lti_assessment_score.marking_user_id) as `instructorFeedback`

FROM v_most_recent_feedback

JOIN assessment on v_most_recent_feedback.assessment_id = assessment.id

LEFT JOIN task_completion on (v_most_recent_feedback.feedback_id = task_completion.id AND v_most_recent_feedback.feedback_table = 'task_completion')

LEFT JOIN submission_return on (v_most_recent_feedback.feedback_id = submission_return.id AND v_most_recent_feedback.feedback_table = 'submission_return')

LEFT JOIN lti_assessment_score on (v_most_recent_feedback.feedback_id = lti_assessment_score.id AND v_most_recent_feedback.feedback_table = 'lti_assessment_score')

WHERE v_most_recent_feedback.assessment_id = :id";

        $mapper = UserMapper::getInstance()->getDatabase();
        $statement = $mapper->prepare($query);
        $statement->bindValue('id', $assessmentId);

        $statement->execute();
        $output = [];
        $output['participants'] = [];
        /**
         * build the whole thing
         */
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $output['id'] = $row['id'];
            $output['title'] = $row['title'];
            $output['weight'] = $row['weight'];
            $output['participants'][] = [
                'userId' => $row['student_id'],
                'mark'  => $row['mark'],
                'normalisedMark' => $row['norm_mark'],
                'comment' => $row['comment'],
                'date'  => $row['completionDate'],
                'resubmissionRequired'  => $this->helper->castToBool($row['resubmission_required']),
                'assessorId'            => $row['instructorFeedback']

            ];
        }
        /**
         * build the whole thing
         */
        $this->response->content->set(
            $this->buildJsonOutput(
                $output
            )
        );
    }
}
