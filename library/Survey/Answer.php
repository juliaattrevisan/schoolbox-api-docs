<?php
/**
 * Survey_Answer
 *
 * All AnswerTypes inherit from this class.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
class Survey_Answer extends Evaluation_Answer
{
    public function __construct()
    {
        parent::__construct(new Survey_Response(), new Survey_Question());
    }

    /**
     * Exports the answer to Moodle XML format.
     *
     * @param SitePage $page
     * @param int $question_id
     */
    public function exportMoodleAnswer(SitePage $page, $question_id)
    {
        throw new BadMethodCallException('You need to call this on a concrete Answer type');
    }

    /**
     * @inheritdoc
     */
    public function importMoodleAnswer(SimpleXMLElement $xmlObj, $question_id)
    {
        throw new BadMethodCallException('You need to call this on a concrete Answer type');
    }

    /**
     * @inheritdoc
     */
    public function saveResponse($data)
    {
        throw new BadMethodCallException('You need to call this on a concrete Answer type');
    }

    /**
     * @inheritdoc
     */
    public function prepareResponse(SitePage $page, $attemptId, $question_id)
    {
        throw new BadMethodCallException('You need to call this on a concrete Answer type');
    }
}

