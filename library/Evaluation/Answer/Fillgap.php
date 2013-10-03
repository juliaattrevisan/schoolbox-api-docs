<?php
/**
 * Evaluation_Answer_Fillgap
 *
 * Handler for Fill Gap questions.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
abstract class Evaluation_Answer_Fillgap extends Evaluation_Answer {

	/**
	 * Javascript class to use when creating UI
	 *
	 * @var string
	 */
	protected $plugin = 'evaluationFillGap';

    /* HTML input element prefix used in conjunction with a question_id to
     * differentiate this Fillgap's inputs from other Fillgap inputs that might
     * appear on the page, as well as other inputs from different questions.
     */
    private $prefix = 'fillgap_';

    /**
     * Creates an Answer. This is used when creating Questions and Answers in
     * the setup phase.
     *
     * @param  $object
     * @param  $question_id
     * @return bool|int
     */
    public function create($object, $question_id)
    {
        $answer = array_intersect_key($object, array_flip($this->allowedFields));
        $answer['question_id'] = $question_id;

        /* 'Short' always has only one answer per question so we can override
         * the sequence here
         */
        $answer['sequence'] = 1;

        $answer_id = parent::create($answer);

        if (!$answer_id) {
            return false;
        }

        return $answer_id;
    }

    /**
     * Updates an existing Answer.
     *
     * @param  $object
     * @return void
     */
    public function update($object)
    {
        $answer = array_intersect_key($object, array_flip($this->allowedFields));

        parent::update($answer);
    }

    /**
     * Renders answers when editing a Question. If a question_id was provided,
     * load its existing answers and populate fields; otherwise empty them or
     * set to other default values.
     *
     * @param  $page
     * @param string $question_id
     * @return void
     */
    public function modify($page, $question_id = '')
    {
        if (is_numeric($question_id)) {
            $currentAnswer = parent::getForQuestion($question_id);

            /* Show what words/phrases were detected. Preserve the original
             * answer text block in the db; this is a convenience for the user
             * so they can double-check their selections.
             */
            $page->setBlock('answerFormFragment', 'answer_list_items', 'answer_list_items_block');

            $page->setVar(array(
                'ANSWER_ID' => $currentAnswer['answer_id'],
                'ANSWER' => Scrub::text($currentAnswer['answer'])
            ));

            $allMatches = array();

            preg_match_all('#<span class="selected-word">([^<]+)</span>#ms', $currentAnswer['answer'], $allMatches);

            if (count($allMatches[1])) {
                foreach ($allMatches[1] as $currentMatch) {
                    $page->setVar(array(
                        'ANSWER_LIST_ITEM' => Scrub::text($currentMatch)
                    ));

                    $page->parse('answer_list_items_block', 'answer_list_items', true);
                }
            } else {
                $page->setVar('answer_list_items_block', '');
            }
        } else {
            $page->setBlock('answerFormFragment', 'answer_list_items', 'answer_list_items_block');
            $page->setVar('answer_list_items_block', '');

            $page->setVar(array(
                'ANSWER_ID' => '',
                'ANSWER' => '',
            ));
        }
    }

    /**
     * Resets answer tokens so a user can fill them in while actually taking
     * the test.
     *
     * @param  $page
     * @param  $question_id
     * @return void
     */
    public function prepareResponse($page, $question_id)
    {
        $existing = $this->responseHandler->isResponseExists($_SESSION['attempt_id'], $question_id);

        $currentAnswer = $this->getByQuestionID($question_id);

        if ($existing['response']) {
            $existingAnswer = unserialize($existing['response']);

            /* Prepare a special way to show Fillgap answers. This replaces
             * %%token%% tokens with inputs and the already-submitted values
             */
            $ans = $currentAnswer['answer'];

            foreach ($existingAnswer as $val) {
                $pattern = '#<span class="selected-word">([^<]+)</span>#ms';
                $replace = " <input name='fillgap_{$question_id}[]' type='text' value=\"$val\" /> ";

                $ans = preg_replace($pattern, $replace, $ans, 1, $x);
            }

            $page->setVar(array(
				'QUESTION_ID' => $question_id,
				'QUESTION_PLUGIN' => $this->plugin,
                'ANSWER_ID' => $currentAnswer['answer_id'],
                'ANSWER' => $ans,
            ));
        } else {
            /* Prepare a special way to show Fillgap answers. This replaces
             * %%token%% tokens with inputs.
             */
            $pattern = '#<span class="selected-word">([^<]+)</span>#ms';
            $replace = " <input name='fillgap_{$question_id}[]' type='text' value='' /> ";

            $page->setVar(array(
				'QUESTION_ID' => $question_id,
				'QUESTION_PLUGIN' => $this->plugin,
                'ANSWER_ID' => $currentAnswer['answer_id'],
                'ANSWER' => preg_replace($pattern, $replace, $currentAnswer['answer']),
            ));
        }

        $page->parse('answerOption_block', 'answerOption', true);
    }

    /**
     * Clear child blocks after parsing out the parent. Keep this in its own
     * method for polymorphic algorithm
     *
     * @param  $page
     * @return void
     */
    public function cleanupParse($page)
    {
    }

    /**
     * Saves a response for this Answer type. This should be customised based on
     * the format and structure of the answer (e.g. serialize multichoice)
     *
     * @param  $data
     *
     * @return array
     */
    public function saveResponse($data)
    {
        $isNotEmpty = false;

        foreach ($data[$this->prefix . $data['question_id']] as $answer) {
            if ($answer != '') {
                $isNotEmpty = true;
            }
        }

        /* Cater for empty answers. If nothing was provided, restore stub
           * placeholder (null) which causes %complete indicator to drop
           */
        if ($isNotEmpty) {
            $processedResponse = serialize($data[$this->prefix . $data['question_id']]);
        } else {
            $processedResponse = NULL;
        }

        return parent::saveResponse($data['section_id'], $data['question_id'], $processedResponse);
    }

    /**
     * Exports the answer to Moodle XML format. This is a modified version of
     * $this->reviewResponse()
     *
     * NOTE: This question type has very little moodle documentation.
     *
     * @param  $page
     * @param  $question_id
     * @return void
     */
    public function exportMoodleAnswer($page, $question_id)
    {
        $currentAnswer = $this->getByQuestionID($question_id);

        /* Show correct answer
         */
        $page->setVar(array(
            'CORRECT_ANSWER' => $currentAnswer['answer']
        ));

        $page->parse('answerOption_block', 'answerOption', true);
    }

    /**
     * Imports form a Moodle XML object. This is largely an alias of create()
     * but we create the array specially because of the naming conventions and
     * structure of the original xml export.
     *
     * @param  $xmlObj
     * @param  $question_id
     * @return bool|int
     */
    public function importMoodleAnswer($xmlObj, $question_id)
    {
        $object = array(
            'answer_id' => '',
            'question_id' => $question_id,
            'sequence' => 1,
            'answer' => trim((string)$xmlObj->answer->text),
        );

        return $this->create($object, $question_id);
    }

    /**
     * Get the response for a particular question
     *
     * @param  $attempt_id
     * @param  $question_id
     * @return mixed
     */
    public function exportResponseToCSV($attempt_id, $question_id)
    {
        $response = $this->responseHandler->getByAttemptAndQuestion($attempt_id, $question_id);

        $data = unserialize($response['response']);

        if (is_array($data)) {
            if ($this->getLegendHTML() !== false) {
                $data['response'] = '<ul><li>' . implode('</li><li>', $data) . '</li></ul>';
            } else {
                $data['response'] = strip_tags(implode(', ', $data));
            }
        }

        /* Append commentary
            */
        $data['comment'] = $response['comment'];

        /* Append awarded mark
           */
        $data['mark'] = $response['mark'];

        return $data;
    }

    /**
     * Gets the text associated with an array-based multiple choice answer
     *
     * @param  $question_id
     * @param  $value
     * @return string
     */
    public function getAnswerText($question_id, $value)
    {
        $answer = $this->getByQuestionID($question_id);

        return $answer['answer'];
    }

    /**
     * Get the response for a particular question
     *
     * @param  $attempt_id
     * @param  $question_id
     * @return mixed
     */
    public function exportResponseToArray($attempt_id, $question_id)
    {
        $response = $this->responseHandler->getByAttemptAndQuestion($attempt_id, $question_id);

        $data = unserialize($response['response']);

        return $data;
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

	/**
	 * Show the response someone submitted and also show the correct answer.
	 * Provide an option for the teacher to review the mark and adjust.
	 *
	 * @param $page
	 * @param $attemptId
	 * @param $currentQuestion
	 */
	public function reviewResponseHorizontal($page, $attemptId, $currentQuestion) {
		$existing = $this->responseHandler->isResponseExists($attemptId, $currentQuestion['question_id']);

		$currentAnswer = $this->getByQuestionID($currentQuestion['question_id']);

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

		$page->setVar(array(
			'USER_FIRSTNAME' => $currentQuestion['firstname'],
			'USER_LASTNAME' => $currentQuestion['lastname'],
			'ANSWER_ID' => $currentAnswer['answer_id'],
		));

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
				'ANSWER' => $ans,
				'RESPONSE_ID' => $existing['response_id'],
				'REVIEWED_COMMENT' => Scrub::text($existing['comment']),
				'REVIEWED_MARKS' => $existing['mark'],
			));
		}
		else {
			/* Prepare a special way to show Fillgap answers. This replaces
			 * %%token%% tokens with inputs.
			 */
			$pattern = '#<span class="selected-word">([^<]+)</span>#ms';
			$replace = " <span class='fillgapIncorrect'>&nbsp;</span> ";

			$page->setVar(array(
				'ANSWER' => preg_replace($pattern, $replace, Scrub::text($currentAnswer['answer'])),
				'RESPONSE_ID' => $existing['response_id'],
				'REVIEWED_COMMENT' => '',
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

	/**
	 * The answer for a question during horizontal marking, so it appears at the
	 * top under the question itself, and not repeated for each attempt
	 *
	 * @param $page
	 * @param $currentQuestion
	 * @return void
	 */
	public function reviewResponseHorizontalAnswerForQuestion($page, $currentQuestion) {
		$currentAnswer = $this->getByQuestionID($currentQuestion['question_id']);

		/* Pattern match spans to find answers
		 */
		$matches = array();
		$pattern = '#<span class="selected-word">([^<]+)</span>#ms';

		preg_match_all($pattern, $currentAnswer['answer'], $matches);

		$page->setVar(array(
			'ANSWER' => Scrub::html($currentAnswer['answer']),
		));

		$page->parse('answerTop_block', 'answerTop', true);
	}
}
