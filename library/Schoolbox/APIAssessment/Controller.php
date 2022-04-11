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
use Schoolbox\UserMapper;

class Controller extends Base
{


    protected IConnector $dbMapper;
    /**
     * Controller constructor.
     *
     */
    public function __construct()
    {
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
    COALESCE(task_completion.completion_date, submission_return.created_at) as `CompletionDate`,
    v_most_recent_feedback.feedback_table, v_most_recent_feedback.feedback_id, v_most_recent_feedback.student_id,
    task_completion.completion_status, task_completion.task_id, task_completion.completion_date,
    submission_return.review_route, submission_return.comment, submission_return.mark, submission_return.norm_mark

FROM v_most_recent_feedback

JOIN assessment on v_most_recent_feedback.assessment_id = assessment.id

LEFT JOIN task_completion on (v_most_recent_feedback.feedback_id = task_completion.id AND v_most_recent_feedback.feedback_table = 'task_completion')

LEFT JOIN submission_return on (v_most_recent_feedback.feedback_id = submission_return.id AND v_most_recent_feedback.feedback_table = 'submission_return')

WHERE v_most_recent_feedback.assessment_id = :id";

        $mapper = UserMapper::getInstance()->getDatabase();
        $statement = $mapper->prepare($query);
        $statement->execute(['id' => $assessmentId]);
        /**
         * build the whole thing
         */
        $output = $statement->fetchAll();
        $this->response->content->set(
            $this->buildJsonOutput(
                $output
            )
        );
    }
}
