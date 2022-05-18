<?php

/**
 * Question modification and Answer modification.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Learning\Assessment\Redirect;

$factory = new Assess_Factory();
$assessHandler = new Assess_Main();
$sectionHandler = new Assess_Section();
$groupHandler = new Group();
/* If a question was submitted
 */
if (!empty($_POST)) {
    $returnUrl = $_POST[Redirect::RETURN_URL] != '' ? "?" . Redirect::RETURN_URL . "={$_POST[Redirect::RETURN_URL]}" : "";
    if (!is_numeric($_POST['type_id'])) {
        error(116);
    }

    $section = $sectionHandler->getByID($_POST['section_id']);
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
    $questionHandler->update($data);
    $answerHandler->update($_POST);
// Form is AJAX submitted so redirection is handled by javascript
    if ($this->detectAjax()) {
        exit;
    } else {
        SitePage::localRedir('/assess/summary/' . $section['assess_id'] . $returnUrl);
    }
} else {
    $returnUrl = $_GET[Redirect::RETURN_URL] != '' ? "?" . Redirect::RETURN_URL . "={$_GET[Redirect::RETURN_URL]}" : "";
    if (!is_numeric($this->arg[0])) {
        error(115);
    }

    /* Render modify page
     */
    $this->setFile(TEMPLATE_content, 'assess/question/modify.html');
    $this->setTitle('Modify Question');
    $this->setBlock(TEMPLATE_content, 'answerInputs', 'answerInputs_block');
    $this->setBlock(TEMPLATE_content, 'questionType', 'questionType_block');
    $this->setBlock(TEMPLATE_content, 'has_answer_configuration', 'has_answer_configuration_block');
    $this->setBlock('has_answer_configuration', 'answer_configuration', 'answer_configuration_block');
    $questionHandler = new Assess_Question();
    $question = $questionHandler->getByID($this->arg[0]);
    if ($question) {
        $section = $sectionHandler->getByID($question['section_id']);
    }

    if ($section) {
        $assess = $assessHandler->getByID($section['assess_id']);
    }

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

    $answerHandler = $factory->createAnswerModel($question['type_id']);
/* Show question type options. For the Modify, preselect whichever it was
     */
    $currentQuestionType = $questionHandler->getQuestionType($question['type_id']);
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
    $answerHandler->modify($this, $question['question_id']);
    $this->parse('answerInputs_block', 'answerFormFragment', true);
    $this->setVar(array(
        'QUESTIONTYPE_TYPE_ID' => $currentQuestionType['type_id'],
        'QUESTIONTYPE_DESCRIPTION' => Scrub::text($currentQuestionType['description']),
    ));
/* Render contents of this Assess
     */
    if (empty($section)) {
        error(51);
    }

    $this->setVar(array(
        'ASSESS_CATEGORY_ID' => $assess['category_id'],
    ));
    $assessHandler = new Assess_Main();
    $assess = $assessHandler->getByID($section['assess_id']);
    $this->setVar(array(
        'ASSESS_SECTION_TITLE' => Scrub::text($section['title']),
        'RETURN_URL_NAME'      => Redirect::RETURN_URL,
        'RETURN_URL'           => $_GET[Redirect::RETURN_URL],
        'ASSESS_ASSESS_ID'     => $section['assess_id'],
        'ASSESS_KIND_LC'       => 'assess',
        'ASSESS_KIND'          => 'Quiz',
        'ASSESS_CATEGORY_ID'   => $assess['category_id'],
        'SECTION_ID'           => $question['section_id'],
        'SEQUENCE'             => $question['sequence'],
        'QUESTION_ID'          => $question['question_id'],
        'QUESTION'             => Scrub::text($question['question']),
        'MARKS'                => $question['marks'],
        'DISABLED_SELECTED'    => '',
    ));
/* Render out breadcrumbs
     */
    $trail = new \Schoolbox\Breadcrumbs();
// get a crunchy biscuit base
    $crumbs = $trail->getFolderCrumbs($folder, $_GET[Redirect::RETURN_URL]);
// sprinkle some crumbs on top
    $crumbs = array_merge($crumbs, [
        [
            'text' => $assess['title'],
            'href' => "/assess/summary/{$assess['assess_id']}{$returnUrl}",
            'class' => '',
        ],
        [
            'text' => $section['title'],
            'href' => "/assess/summary/{$assess['assess_id']}{$returnUrl}",
            'class' => '',
        ],
        [
            'text' => "Q{$question['sequence']}",
            'href' => "/assess/question/modify/{$question['question_id']}{$returnUrl}",
            'class' => 'active',
        ]
    ]);
// bake in a moderate oven for 40 minutes
    $trail->renderIntoSitePage($this, 'BREADCRUMB', $crumbs);
}
