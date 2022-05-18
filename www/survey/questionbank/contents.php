<?php

use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Breadcrumbs;

/** @var SitePage $this */
try {
    $surveyHandler = new \Survey_Main();
    $survey = $surveyHandler->getByID($this->arg[1]);
} catch (\Exception $e) {
    error(\Schoolbox\Error\Mapper::ERROR_SURVEY_NOT_FOUND);
}

try {
    $folderMapper = new FolderMapper();
    /** @var \Schoolbox\Survey\Model $survey */
    $folder = $folderMapper->findById($survey['category_id']);
} catch (\Exception $e) {
    error(\Schoolbox\Error\Mapper::ERROR_CATEGORY_DELETED);
}

$sectionHandler = new \Assess_Section();
$section = $sectionHandler->getById($this->arg[2]);
//if (!$section) {
//    error(115);
//}

$this->setFile(TEMPLATE_content, 'survey/questionbank/contents.html');
$this->setBlock(TEMPLATE_content, 'welcome', 'welcome_block');
$this->setBlock(TEMPLATE_content, 'category_selected', 'category_selected_block');
$this->setBlock('category_selected', 'show_submit', 'show_submit_block');
$this->setBlock('category_selected', 'show_delete', 'show_delete_block');
$this->setBlock('category_selected', 'no_questions', 'no_questions_block');
$this->setBlock('category_selected', 'question', 'question_block');
$this->setBlock('question', 'answers', 'answers_block');
$this->setBlock('question', 'select_question', 'select_question_block');
$this->setBlock('question', 'missing_class', 'missing_class_block');
$this->setBlock(TEMPLATE_content, 'has_edit_category', 'has_edit_category_block');
$this->setVar(array(
    'QUESTIONBANK_CATEGORY_ID' => $this->arg[0],
    'SURVEY_ID' => $survey['assess_id'],
    'SECTION_ID' => $this->arg[2],
));
$questionBankHandler = new Survey_Question_Bank();
/* Derive and render a category list
 */
$questionBankCategoryHandler = new Survey_Question_Category();
$questionCategoryList = new Survey_Question_Category_List($this, $survey['assess_id'], $this->arg[0], $questionBankCategoryHandler->getPath());
$questionCategoryList->render();
/* If expanding a particular category
 */
if (is_numeric($this->arg[0]) && ($this->arg[0] > 0)) {
    $category = $questionBankCategoryHandler->getByID($this->arg[0]);
    $this->setVar(array(
        'QUESTION_CATEGORY' => Scrub::text($category['name']),
    ));
    $allQuestions = $questionBankHandler->getContents($this->arg[0]);
/* Iterate and show
     */
    if (is_array($allQuestions) && count($allQuestions)) {
        $factory = new Survey_Factory();
        foreach ($allQuestions as $currentQuestion) {
            $this->setVar(array(
                'QUESTION_BANK_ID' => $currentQuestion['question_bank_id'],
                'QUESTION_TYPE' => $currentQuestion['description'],
                'QUESTION' => Scrub::html($currentQuestion['question']),
                'MARKS' => $currentQuestion['marks'],
                'answers_block' => '',
            ));
        //
                    // Need to be able to create a question and answer for this question
                    // Otherwise, it can't be added to the survey.
            if ($factory->isQuestionTypeAvailable($currentQuestion['type_id'])) {
                $this->parse('select_question_block', 'select_question');
                $this->setVar('missing_class_block', '');
            } else {
                $this->setVar('select_question_block', '');
                $this->parse('missing_class_block', 'missing_class');
            }

            if ($currentQuestion['answer']) {
                $answerList = unserialize($currentQuestion['answer']);
                if (is_array($answerList)) {
                    foreach ($answerList as $answer) {
        /* Matching pairs uses a different property than
                               * multiple choice
                               */
                        if ($currentQuestion['type_id'] == $questionBankHandler::MATCHING_PAIRS_TYPE_ID) {
                            $this->setVar('ANSWER_DESCRIPTION', Scrub::text($answer->answerLeft . ' - ' . $answer->answerRight));
                        } else {
                                $this->setVar('ANSWER_DESCRIPTION', Scrub::text($answer->description));
                        }
                        $this->parse('answers_block', 'answers', true);
                    }
                } else {
                    if ($currentQuestion['type_id'] == $questionBankHandler::CLOZE_EXERCISE_TYPE_ID || $currentQuestion['type_id'] == $questionBankHandler::RICH_TEXT_TYPE_ID) {
                        //only add the 'article' tags to Rich Text and Cloze Exercise question types, and fix it so it scrubs the data
                        $tmp = Scrub::html($currentQuestion['answer']);
                        $this->setVar('ANSWER_DESCRIPTION', "<article class='no-clearfix'>${tmp}</article>");
                    } else {
                        $this->setVar('ANSWER_DESCRIPTION', Scrub::html($currentQuestion['answer']));
                    }
                    $this->parse('answers_block', 'answers', true);
                }
            }

            /* Show delete functionality
             */
            $this->parse('show_delete_block', 'show_delete');
            $this->parse('question_block', 'question', true);
        /* Clear children after parsing out the parent
             */
            $this->setVar(array(
                'show_delete_block' => ''
            ));
        }

        $this->parse('category_selected_block', 'category_selected');
/* Hide submit button if we aren't adding contents to a section
         */
        if (is_numeric($this->arg[2])) {
            $this->parse('show_submit_block', 'show_submit');
        } else {
            $this->setVar('show_submit_block', '');
        }

        /* Hide 'no questions' message
         */
        $this->setVar('no_questions_block', '');
        $this->setVar('welcome_block', '');
        $this->parse('has_edit_category_block', 'has_edit_category');
    } else {
    /* There are no questions: hide the add/remove functionality and display a
         * little message
         */
        $this->setVar(array(
                'show_submit_block' => '',
                'show_delete_block' => '',
                'question_block' => '',
                'welcome_block' => ''
            ));
        $this->parse('no_questions_block', 'no_questions');
        $this->parse('category_selected_block', 'category_selected');
        $this->parse('has_edit_category_block', 'has_edit_category');
    }
} else {
    $this->setVar(array(
            'QUESTION_CATEGORY' => '',
            'category_selected_block' => '',
            'has_edit_category_block' => ''
        ));
    $this->parse('welcome_block', 'welcome');
}

/* Render out breadcrumbs
 */
$trail = new Breadcrumbs();
/** @var \Schoolbox\Resource\Folder\Model $folder */
$trail->renderIntoSitePage($this, 'BREADCRUMB', [
    [
        'text' => $folder->getName(),
        'href' => "/homepage/{$folder->getId()}",
        'class' => '',
    ],
    [
        'text' => Scrub::text($survey['title']),
        'href' => "/survey/summary/{$survey['assess_id']}",
        'class' => '',
    ],
    [
        'text' => Scrub::text($section['title']),
        'href' => "/survey/summary/{$survey['assess_id']}",
        'class' => '',
    ],
    [
        'text' => 'Add from Question Bank',
        'href' => '',
        'class' => 'active',
    ]
]);
