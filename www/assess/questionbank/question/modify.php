<?php

/**
 * Question modification and Answer modification.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Breadcrumbs;
use Schoolbox\Learning\Assessment\Redirect;

$factory = new Assess_Factory();
$assessHandler = new Assess_Main();
$groupHandler = new Group();
/* If a question was submitted
 */
if (!empty($_POST)) {
    if (!is_numeric($_POST['type_id'])) {
        error(116);
    }

    $questionHandler = $factory->createQuestionModel($_POST['type_id']);
    $answerHandler = $factory->createAnswerModel($_POST['type_id']);
/* If configuration was provided
     */
    $config = null;
    if (isset($_POST['configuration'])) {
        $allConfiguration = json_decode($_POST['configuration']);
        $config = array();
    /* Compress checkboxes
         */
        foreach ($allConfiguration as $currentConfiguration) {
            $config[$currentConfiguration->name] = $currentConfiguration->value;
        }
    }

    /* Put that data in the question
     */
    $data = $_POST;
    $data['config'] = json_encode($config);
/* Because questions and answers aren't stored in separate tables
    * in the QuestionBank context, so this had to get decomposed a bit
     * which is severely unfortunate
    *
  * Non-json stringified answers use 'answer' key
    * Json-stringified answers use 'allAnswers' key
    *
  * Sigh
     *
  * TODO: Sigh.
     */
    if (isset($data['allAnswers'])) {
        $encoded = $answerHandler->encodeForQuestionBank($data['allAnswers']);
    } else if (isset($data['answer'])) {
        $encoded = $answerHandler->encodeForQuestionBank($data['answer']);
    }

    $data['answer'] = $encoded;
/* Context-specific questionHandler, for the QuestionBank
     */
    $questionBank = new Assess_Question_Bank();
    $questionBank->getQuestionHandler()->update($data);
/* Find the assess summary page
     */
    $this->backToTheFuture(2);
} else {
    if (!is_numeric($this->arg[0])) {
        error(115);
    }

    if (!is_numeric($this->arg[1])) {
        error(115);
    }

    if (!is_numeric($this->arg[2])) {
        error(115);
    }

    /* Render modify page
     */
    $this->setFile(TEMPLATE_content, 'assess/questionbank/question/modify.html');
    $this->setTitle('Modify Question');
    $this->setBlock(TEMPLATE_content, 'answerInputs', 'answerInputs_block');
    $this->setBlock(TEMPLATE_content, 'questionType', 'questionType_block');
    $this->setBlock(TEMPLATE_content, 'allQuestions', 'allQuestions_block');
    $this->setBlock(TEMPLATE_content, 'has_answer_configuration', 'has_answer_configuration_block');
    $this->setBlock('has_answer_configuration', 'answer_configuration', 'answer_configuration_block');
    $questionBank = new Assess_Question_Bank();
    $question = $questionBank->getByID($this->arg[0]);
    $answerHandler = $factory->createAnswerModel($question['type_id']);
/* Show question type options. For the Modify, preselect whichever it was
     */
    $currentQuestionType = $questionBank->getQuestionHandler()->getQuestionType($question['type_id']);
/* Question-specific answer configuration
  *
  * Load answer configurations template into answer_configuration_form_fragment
  * block if there are configuration options available
     */
    if ($currentQuestionType['has_config'] == 'true') {
        $this->setFile('answer_configuration_fragment', 'assess/answer/' . $factory->getQuestionType($question['type_id']) . '/configuration.html');
        $this->setBlock('answer_configuration_fragment', 'answer_configuration_form_fragment', 'answer_configuration_form_fragment_block');
        $answerHandler->configure($this, $question['config']);
        $this->parse('answer_configuration_block', 'answer_configuration_fragment');
        $this->parse('has_answer_configuration_block', 'has_answer_configuration');
    } else {
        $this->setVar(array(
            'has_answer_configuration_block' => ''
        ));
    }

    /* Load answer template into answerOption block
     */
    $this->setFile('answerFormFragment', 'assess/answer/' . $factory->getQuestionType($question['type_id']) . '/modify.html');
    $this->setBlock('answerFormFragment', 'answerOption', 'answerOption_block');
    $answerHandler->modifyInQuestionBank($this, $question['answer']);
    $this->parse('answerInputs_block', 'answerFormFragment', true);
    $this->setVar(array(
        'ASSESS_CATEGORY_ID' => 0,
        'RETURN_URL_NAME' => Redirect::RETURN_URL,
        'RETURN_URL' => $_GET[Redirect::RETURN_URL],
        'CATEGORY_LIST_ITEM_ASSESS_ID' => $this->arg[1],
        'SECTION_ID' => $this->arg[2],
        'QUESTION_BANK_ID' => $question['question_bank_id'],
        'QUESTIONTYPE_TYPE_ID' => $currentQuestionType['type_id'],
        'QUESTIONTYPE_DESCRIPTION' => Scrub::text($currentQuestionType['description']),
        'QUESTION' => Scrub::text($question['question']),
        'MARKS' => $question['marks'],
        'QUESTION_BANK_REDIRECT_URL' => '/assess/questionbank/contents'
    ));
    $trail = new Breadcrumbs();
    $trail->renderIntoSitePage($this, 'BREADCRUMB', [
        [
            'text'  => 'Quiz Questionbank',
            'href'  => '#',
            'class' => ''
        ],
        [
            'text'  => 'Modify Question',
            'href'  => '#',
            'class' => 'active'
        ]
    ]);
}
