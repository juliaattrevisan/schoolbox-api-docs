<?php

/**
 * Question creation and Answer creation. When a QuestionType is selected such
 * as multiple choice, fill gaps etc. the related AnswerType handler is loaded;
 * actual Answer creation is delegated to the AnswerType handler.
 *
 * The forms used to input data to create an Answer are also loaded and inserted
 * dynamically by the templating.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Learning\Assessment\Redirect;

$assessHandler = new Assess_Main();
$sectionHandler = new Assess_Section();
$questionHandler = new Assess_Question();
$factory = new Assess_Factory();
$groupHandler = new Group();

/* If a question was submitted
 */
/** @var SitePage $this */
if (!empty($_POST)) {
    $returnUrl = ($_POST[Redirect::RETURN_URL] ?? '') !== '' ? "?" . Redirect::RETURN_URL . "={$_POST[Redirect::RETURN_URL]}" : "";

    $section = $sectionHandler->getByID($_POST['section_id']);
    $assess = $assessHandler->getByID($section['assess_id']);

    if (!$assessHandler->isValidUrlAccess($section['assess_id'])) {
        $this->pageNotFound();
    }

    if (!$assessHandler->isOwner($assess['assess_id']) && ($groupHandler->getPermission($assess['category_id']) < UserAccess::ACCESS_WRITE)) {
        error(130);
    }

    if ($assessHandler->isDeleted($section['assess_id'])) {
        error(112);
    }

    if ($sectionHandler->isDeleted($section['section_id'])) {
        error(115);
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

    $question_id = $questionHandler->create($data);
    $answer_id = $answerHandler->create($_POST, $question_id);

    if (!$question_id) {
        error(131);
    }

    if (!$answer_id) {
        error(132);
    }

    // Form is AJAX submitted so redirection is handled by javascript
    if ($this->detectAjax()) {
        exit;
    } else {
        SitePage::localRedir('/assess/question/create/' . $section['section_id'] . $returnUrl);
    }
} else {
    $returnUrl = ($_GET[Redirect::RETURN_URL] ?? '') != '' ? "?" . Redirect::RETURN_URL . "={$_GET[Redirect::RETURN_URL]}" : "";

    if (!is_numeric($this->arg[0])) {
        error(51);
    }

    $section = $sectionHandler->getByID($this->arg[0]);
    $assess = $assessHandler->getByID($section['assess_id']);

    if (!$assessHandler->isValidUrlAccess($section['assess_id'])) {
        $this->pageNotFound();
    }

    if ($assessHandler->isDeleted($section['assess_id'])) {
        error(112);
    }

    if (!$assessHandler->isOwner($assess['assess_id']) && ($groupHandler->getPermission($assess['category_id']) < UserAccess::ACCESS_WRITE)) {
        error(130);
    }


    if ($sectionHandler->isDeleted($section['section_id'])) {
        error(115);
    }

    try {
        $folderMapper = new FolderMapper();
        $folder = $folderMapper->findById($assess['category_id']);
    } catch (\Exception $e) {
        error(117);
    }


    /* Render create page
     */
    $this->setFile(TEMPLATE_content, 'assess/question/create.html');
    $this->setBlock(TEMPLATE_content, 'type_selected', 'type_selected_block');
    $this->setBlock('type_selected', 'answerInputs', 'answerInputs_block');
    $this->setBlock(TEMPLATE_content, 'questionType', 'questionType_block');
    $this->setBlock('type_selected', 'has_answer_configuration', 'has_answer_configuration_block');
    $this->setBlock('type_selected', 'has_marks', 'has_marks_block');
    $this->setBlock('has_answer_configuration', 'answer_configuration', 'answer_configuration_block');

    $this->setTitle('Create Question');

    $this->setVar(array(
        'question_block' => '',
        'has_answer_configuration_block' => '',
        'type_selected_block' => '',
    ));

    $this->setVar(array(
        'ASSESS_TITLE'          => Scrub::text($assess['title']),
        'RETURN_URL_NAME'       => Redirect::RETURN_URL,
        'RETURN_URL'            => $_GET[Redirect::RETURN_URL] ?? '',
        'ASSESS_ASSESS_ID'      => $section['assess_id'],
        'ASSESS_KIND_LC'        => 'assess',
        'ASSESS_KIND'           => 'Quiz',
        'ASSESS_CATEGORY_ID'    => $folder->getId(),
        'ASSESS_CATEGORY_NAME'  => Scrub::text($folder->getName()),
        'ASSESS_DURATION'       => $assess['duration'] == Assess_Main::UNLIMITED_DURATION ? 'Unlimited' : $assessHandler->sec2hms($assess['duration']),
        'SECTION_ID'            => $this->arg[0],
        'CURRENT_SECTION_TITLE' => Scrub::text($section['title']),
        'SEQUENCE'              => $questionHandler->getNextSequence($section['section_id']),
        'QUESTION'              => '',
        'MARKS'                 => '',
        'SUBMIT_DISABLED'       => '',
    ));

    /* Show question type options
     */
    $setTemplate = false;

    $questionTypes = $questionHandler->getAllQuestionTypes();

    /* If a particular question type has been selected; otherwise ignore it
     */
    $typeId = $this->arg[1] ?? null;
    if (is_numeric($typeId)) {
        $answerHandler = $factory->createAnswerModel($typeId);
    }

    foreach ($questionTypes as $currentQuestionType) {
        if (is_numeric($typeId) && ($typeId == $currentQuestionType['type_id'])) {
            $selected = 'selected=\'selected\'';

            /* Load answer template into answerOption block
             */
            $this->setFile('answerFormFragment', 'assess/answer/' . $factory->getQuestionType($typeId) . '/create.html');
            $this->setBlock('answerFormFragment', 'answerOption', 'answerOption_block');

            /* Render existing answers, or clear if none
             */
            $answerHandler->modify($this);

            $this->parse('answerInputs_block', 'answerFormFragment', true);

            /* Question-specific answer configuration
             *
             * Load answer configurations template into answer_configuration_form_fragment
             * block if there are configuration options available
             */
            if ($currentQuestionType['has_config'] == 'true') {
                $this->setFile('answer_configuration_fragment', 'assess/answer/' . $factory->getQuestionType($typeId) . '/configuration.html');
                $this->setBlock('answer_configuration_fragment', 'answer_configuration_form_fragment', 'answer_configuration_form_fragment_block');

                $answerHandler->configure($this, json_encode(array()));

                $this->parse('answer_configuration_block', 'answer_configuration_fragment');
                $this->parse('has_answer_configuration_block', 'has_answer_configuration');
            } else {
                $this->setVar(array(
                    'has_answer_configuration_block' => ''
                ));
            }

            /* We've set the template, so remember this
             */
            $setTemplate = true;
        } else {
            $selected = '';
        }

        $this->setVar(array(
            'QUESTIONTYPE_TYPE_ID' => $currentQuestionType['type_id'],
            'QUESTIONTYPE_DESCRIPTION' => $currentQuestionType['description'],
            'QUESTIONTYPE_SELECTED' => $selected,
        ));

        $this->parse('questionType_block', 'questionType', true);
    }

    /* If a particular question type has been selected; otherwise ignore it
     */
    if (is_numeric($typeId)) {
        $this->setVar(array(
            'IMPORT_SELECTED' => '',
            'DISABLED_SELECTED' => '',
        ));

        $this->parse('type_selected_block', 'type_selected');
    } else if ($typeId == 'import') {
        $this->setVar(array(
            'IMPORT_SELECTED' => 'selected=\'selected\'',
        ));
    } else {
        $this->setVar(array(
            'IMPORT_SELECTED' => '',
            'DISABLED_SELECTED' => 'selected=\'selected\'',
            'SUBMIT_DISABLED' => 'disabled="disabled"'
        ));
    }

    /* If the answer template wasn't set, then we haven't chosen an answer type
     * so hide the block that would normally contain the answer type's form.
     */
    if (!$setTemplate) {
        $this->setVar('answerInputs_block', '');
    }

    $assessHandler = new Assess_Main();
    $assess = $assessHandler->getByID($section['assess_id']);

    $this->setVar(array(
        'ASSESS_CATEGORY_ID' => $assess['category_id'],
    ));

    $this->parse('has_marks_block', 'has_marks');

    // Get the parent folder
    $parent = $folderMapper->findById($folder->getParent()->getId());

    /* Render out breadcrumbs
     */
    $trail = new \Schoolbox\Breadcrumbs();

    // get a crunchy biscuit base
    $crumbs = $trail->getFolderCrumbs($folder, $_GET[Redirect::RETURN_URL] ?? '');

    // sprinkle some crumbs on top
    $crumbs = array_merge($crumbs, [
        [
            'text' => $assess['title'],
            'href' => "/assess/summary/{$assess['assess_id']}{$returnUrl}",
            'class' => '',
        ],
        [
            'text' => $section['title'],
            'href' => "/assess/summary/{$assess['assess_id']}{$returnUrl}#section-{$section['section_id']}",
            'class' => '',
        ],
        [
            'text' => 'New Question',
            'href' => '',
            'class' => 'active',
        ]
    ]);

    // bake in a moderate oven for 40 minutes
    $trail->renderIntoSitePage($this, 'BREADCRUMB', $crumbs);
}
