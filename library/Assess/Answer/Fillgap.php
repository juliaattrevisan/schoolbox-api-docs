<?php
/**
 * Assess_Answer_Fillgap
 *
 * Handler for Fill Gap questions.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
class Assess_Answer_Fillgap extends Evaluation_Answer_Fillgap
{

    /**
     * Get an instance of a responseHandler upon instantiation
     */
    public function __construct()
    {
        parent::__construct();

        $this->responseHandler = new Assess_Response();
    }

    /**
     * Automatically marks a response. Customised to be applicable to the way in
     * which the response is actually stored.
     *
     * @param  $currentResponse
     * @return void
     */
    public function mark($currentResponse)
    {
        $response = unserialize($currentResponse['response']);
        $answer = $currentResponse['answer'];

        /* Pattern match spans to find answers
           */
        $matches = array();
        $pattern = '#<span class="selected-word">([^<]+)</span>#ms';

        preg_match_all($pattern, $answer, $matches);

        $actualAnswers = $matches[1];

        /* Find out how much each phrase is worth
           * If the number of Fillgap answers is 0 (ie. no blank for students to fill), skip it.
           * TODO: It should be noted that a teacher should be prevented from creating a cloze question that doesn't have any blanks in it...
           */
        if (count($actualAnswers) != 0) {
            $marksPerAnswer = $currentResponse['marks'] / count($actualAnswers);

            for ($i = 0, $totalMarks = 0; $i < count($actualAnswers); $i++) {
                if (strtolower(trim($actualAnswers[$i])) == strtolower(trim($response[$i]))) {
                    $totalMarks += $marksPerAnswer;
                }
            }
        }

        /* Total marks for this question
           */
        $marks = array(
            'response_id' => $currentResponse['response_id'],
            'mark' => $totalMarks,
        );

        $this->responseHandler->update($marks);
    }

    /**
     * Show the response someone submitted and also show the correct answer.
     * Provide an option for the teacher to review the mark and adjust.
     *
     * @param  $page
     * @param  $question_id
     * @return void
     */
    public function reviewResponse($page, $question_id)
    {
        $existing = $this->responseHandler->isResponseExists($_SESSION['attempt_id'], $question_id);

        $currentAnswer = $this->getByQuestionID($question_id);

        /* Pattern match spans to find answers
           */
        $matches = array();
        $pattern = '#<span class="selected-word">([^<]+)</span>#ms';

        preg_match_all($pattern, $currentAnswer['answer'], $matches);

        $actualAnswers = $matches[1];

        /* Remember where the answers were, so we can compare between the
           * student's reponse and the actual answer
           */
        $replace = " <span class='fillgapCorrect'>$1</span> ";
        $correctResponse = preg_replace($pattern, $replace, $currentAnswer['answer']);

        if ($existing['response']) {
            $existingAnswer = unserialize($existing['response']);

            /* Prepare a special way to show Fillgap answers. This replaces
                * %%token%% tokens with inputs and the already-submitted values
                */
            $ans = $currentAnswer['answer'];

            $i = 0;

            foreach ($existingAnswer as $val) {
                $pattern = '#<span class="selected-word">([^<]+)</span>#ms';
                $val = Scrub::text($val);

                /* Indicate matching/non-matching responses to answers
                     */
                if (strtolower(trim($actualAnswers[$i])) == strtolower(trim($existingAnswer[$i]))) {
                    $replace = " <span class='fillgapCorrect'>$val</span> ";
                } else {
                    $replace = " <span class='fillgapIncorrect'>$val</span> ";
                }

                $ans = preg_replace($pattern, $replace, $ans, 1, $x);

                $i++;
            }

            $page->setVar(array(
                'ANSWER_ID' => $currentAnswer['answer_id'],
                'ANSWER' => $ans,
                'RESPONSE_ID' => $existing['response_id'],
                'REVIEWED_COMMENT' => Scrub::text($existing['comment']),
                'REVIEWED_MARKS' => $existing['mark'],
            ));
        } else {
            /* Prepare a special way to show Fillgap answers. This replaces
                * %%token%% tokens with inputs.
                */
            $pattern = '#<span class="selected-word">([^<]+)</span>#ms';
            $replace = " <span class='fillgapIncorrect'>&nbsp;</span> ";

            $page->setVar(array(
                'ANSWER_ID' => $currentAnswer['answer_id'],
                'ANSWER' => preg_replace($pattern, $replace, Scrub::text($currentAnswer['answer'])),
                'RESPONSE_ID' => $existing['response_id'],
                'REVIEWED_MARKS' => 0,
            ));
        }

        /* Show correct answer
           */
        $page->setVar(array(
            'CORRECT_ANSWER' => $correctResponse
        ));

        $page->parse('answerOption_block', 'answerOption', true);
    }
}
