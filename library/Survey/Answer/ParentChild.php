<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

/**
 * Answer type for surveys: accepts one of the current user's children as a
 * valid response.
 */
class Survey_Answer_ParentChild extends Evaluation_Answer_ParentChild
{
    public function prepareResponse(SitePage $page, $attemptId, $question_id)
    {
        // TODO: Implement prepareResponse() method.
    }

    public function saveResponse($data)
    {
        // TODO: Implement saveResponse() method.
    }

    public function exportMoodleAnswer(SitePage $page, $question_id)
    {
        // TODO: Implement exportMoodleAnswer() method.
    }

    public function importMoodleAnswer(SimpleXMLElement $xmlObj, $question_id)
    {
        // TODO: Implement importMoodleAnswer() method.
    }
}
