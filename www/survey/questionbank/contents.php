<?php
require_once('../library/survey/SurveyQuestion.php');
require_once('../library/survey/SurveyAnswer.php');
require_once('../library/survey/SurveyQuestionBank.php');
require_once('../library/survey/SurveyQuestionCategory.php');

$this->setFile(TEMPLATE_content, 'survey/questionbank/contents.html');
$this->setBlock(TEMPLATE_content, 'welcome', 'welcome_block');
$this->setBlock(TEMPLATE_content, 'category_selected', 'category_selected_block');
$this->setBlock(TEMPLATE_content, 'question_category', 'question_category_block');
$this->setBlock('category_selected', 'show_submit', 'show_submit_block');
$this->setBlock('category_selected', 'show_delete', 'show_delete_block');
$this->setBlock('category_selected', 'no_questions', 'no_questions_block');

$this->setBlock('category_selected', 'question', 'question_block');
$this->setBlock('question', 'answers', 'answers_block');

$this->setVar(array(
    'SURVEY_ID' => $this->arg[1],
	'SECTION_ID' => $this->arg[2],
));

$questionBankHandler = new SurveyQuestionBank();

/* output question bank categories */
$questionBankCategoryHandler = new SurveyQuestionCategory();

$categoryList = $questionBankCategoryHandler->getList();

if(is_array($categoryList) ) {
    foreach($categoryList as $category) {

        $this->setVar(array(
            'CATEGORY_NAME' => $category['name'],
            'CATEGORY_ID'   => $category['question_category_id'],
            'CATEGORY_SELECTED' => $category['question_category_id']==$this->arg[0]?'selected':''));

        if($category['question_category_id']==$this->arg[0])
            $categoryName = $category['name'];

        $this->parse('question_category_block', 'question_category', true);
    }
}
else
    $this->setVar('question_category_block','');


if($this->arg[0] > 0) {

    $allQuestions = $questionBankHandler->getContents($this->arg[0]);
    $this->setVar('QUESTION_CATEGORY', $categoryName);

    /* Iterate and show
     */
    if (is_array($allQuestions) && count($allQuestions)) {
        $previousCategory = false;

        foreach($allQuestions as $currentQuestion) {

            $this->setVar(array(
                'QUESTION_BANK_ID' => $currentQuestion['question_bank_id'],
                'QUESTION_TYPE' => $currentQuestion['description'],
                'QUESTION' => $currentQuestion['question'],
                'MARKS' => $currentQuestion['marks'],
                'answers_block'  => '',
            ));

            if($currentQuestion['answer']) {
                $answerList = unserialize($currentQuestion['answer']);
                 foreach($answerList as $answer) {
                    $this->setVar('ANSWER_DESCRIPTION', $answer->description);
                    $this->parse('answers_block','answers',true);
                 }
            }

            $this->parse('question_block', 'question', true);

            $previous = $currentQuestion['question_category_id'];
        }

        $this->parse('category_selected_block','category_selected');
        
        /* Hide submit button if we aren't adding contents to a section
         */
        if (is_numeric($this->arg[2])) {
            $this->parse('show_submit_block', 'show_submit');
        }
        else {
            $this->setVar('show_submit_block', '');
        }

        /* Show delete functionality
         */
        $this->parse('show_delete_block', 'show_delete');

        /* Hide 'no questions' message
         */
        $this->setVar('no_questions_block', '');
        $this->setVar('welcome_block','');
    }
    else {

        /* There are no questions: hide the add/remove functionality and display a
         * little message
         */
        $this->setVar('show_submit_block', '');
        $this->setVar('show_delete_block', '');
        $this->setVar('question_block', '');
        $this->setVar('welcome_block','');
        $this->parse('no_questions_block', 'no_questions');
    }
}
else {
    $this->parse('welcome_block', 'welcome');
    $this->setVar('category_selected_block','');
}
