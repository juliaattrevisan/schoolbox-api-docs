<?php

/**
 * Assess_Response
 *
 * Responses are managed here
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Assess_Response extends Evaluation_Response
{
    public $_table = 'assess_response';
    public $_index = 'response_id';

    /* A const to determine whether to randomise questions
     */
    const RANDOMISE_QUESTIONS = 1;
    const ORDERED_QUESTIONS = 2;


    /**
     * Remove usages of a Question from all existing Attempts as well
     *
     * @param int $questionId
     */
    public function deleteQuestionFromResponses(int $questionId): void
    {
        $query = "
            UPDATE {$this->_table}
            SET deleted_at = NOW()
            WHERE question_id = {$questionId}
        ";

        $this->db->query($query);
    }
}
