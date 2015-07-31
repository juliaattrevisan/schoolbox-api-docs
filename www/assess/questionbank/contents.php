<?php
use Schoolbox\Assessment\Component\OnlineAssessment;
use Schoolbox\Assessment\Component\OnlineAssessmentMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Breadcrumbs;

try {
    $quizMapper = new OnlineAssessmentMapper();
    $quiz = $quizMapper->findById($this->arg[1]);
}
catch (\Exception $e) {
    error(112);
}

try {
    $folderMapper = new FolderMapper();
    $folder = $folderMapper->findById($quiz->getCategory()->getId());
}
catch (\Exception $e) {
    error(117);
}

$sectionHandler = new \Assess_Section();
$section = $sectionHandler->getById($this->arg[2]);

$this->setFile(TEMPLATE_content, 'assess/questionbank/contents.html');
$this->setBlock(TEMPLATE_content, 'welcome', 'welcome_block');
$this->setBlock(TEMPLATE_content, 'category_selected', 'category_selected_block');
$this->setBlock('category_selected', 'show_delete', 'show_delete_block');
$this->setBlock('category_selected', 'no_questions', 'no_questions_block');
$this->setBlock('category_selected', 'question', 'question_block');
$this->setBlock('question', 'answers', 'answers_block');
$this->setBlock('question', 'select_question', 'select_question_block');
$this->setBlock('question', 'missing_class', 'missing_class_block');
$this->setBlock(TEMPLATE_content, 'has_edit_category', 'has_edit_category_block');

/* TODO FIXME... XSS attack */
$this->setVar(
    array(
        'QUESTIONBANK_CATEGORY_ID' => $this->arg[0],
        'ASSESS_ID' => $quiz->getId(),
        'SECTION_ID' => $this->arg[2],
    )
);

$questionBankHandler = new Assess_Question_Bank();

/* Derive and render a category list
 */
$questionBankCategoryHandler = new Assess_Question_Category();

$questionCategoryList = new Assess_Question_Category_List($this, $quiz->getId(), $this->arg[0], $questionBankCategoryHandler->getPath());
$questionCategoryList->render();

/* If expanding a particular category
 */
if (is_numeric($this->arg[0]) && ($this->arg[0] > 0)) {
    $category = $questionBankCategoryHandler->getByID($this->arg[0]);

    $this->setVar(
        array(
            'QUESTION_CATEGORY' => Scrub::text($category['name']),
        )
    );

    $allQuestions = $questionBankHandler->getContents($this->arg[0]);

    /* Iterate and show
     */
    if (is_array($allQuestions) && count($allQuestions)) {
        $previousCategory = false;
        $factory = new Assess_Factory();
        foreach ($allQuestions as $currentQuestion) {
            $this->setVar(
                array(
                    'QUESTION_BANK_ID' => $currentQuestion['question_bank_id'],
                    'QUESTION_TYPE' => Scrub::text($currentQuestion['description']),
                    'QUESTION' => Scrub::html($currentQuestion['question']),
                    'MARKS' => $currentQuestion['marks'],
                    'answers_block' => '',
                )
            );

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
                            $this->setVar(
                                'ANSWER_DESCRIPTION',
                                Scrub::Text($answer->answerLeft . ' - ' . $answer->answerRight)
                            );
                        } else {
                            $this->setVar('ANSWER_DESCRIPTION', Scrub::text($answer->description));
                        }

                        $this->parse('answers_block', 'answers', true);
                    }
                } else {
                    $this->setVar('ANSWER_DESCRIPTION', Scrub::html($currentQuestion['answer']));
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

            $previous = $currentQuestion['question_category_id'];
        }

        $this->parse('category_selected_block', 'category_selected');

        /* Hide 'no questions' message
         */
        $this->setVar('no_questions_block', '');
        $this->setVar('welcome_block', '');

        $this->parse('has_edit_category_block', 'has_edit_category');
    } else {

        /* There are no questions: hide the add/remove functionality and display a
         * little message
         */
        $this->setVar(
            array(
                'show_delete_block' => '',
                'question_block' => '',
                'welcome_block' => ''
            )
        );

        $this->parse('no_questions_block', 'no_questions');
        $this->parse('category_selected_block', 'category_selected');
        $this->parse('has_edit_category_block', 'has_edit_category');
    }
} else {
    $this->setVar(
        array(
            'QUESTION_CATEGORY' => '',
            'category_selected_block' => '',
            'has_edit_category_block' => ''
        )
    );

    $this->parse('welcome_block', 'welcome');
}

/* Render out breadcrumbs
 */
$trail = new Breadcrumbs(Schoolbox\Resource\Folder\Mapper::getInstance());
$trail->renderIntoSitePage($this, 'BREADCRUMB', [
    [
        'text' => $folder->getName(),
        'href' => "/homepage/{$folder->getId()}",
        'class' => '',
    ],
    [
        'text' => $quiz->getTitle(),
        'href' => "/assess/summary/{$quiz->getId()}",
        'class' => '',
    ],
    [
        'text' => 'Add from Question Bank',
        'href' => '',
        'class' => 'active',
    ]
]);