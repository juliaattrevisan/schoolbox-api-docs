<?php
/**
 * Question modification and Answer modification.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Breadcrumbs;

$factory = new Survey_Factory();
$assessHandler = new Survey_Main();
$sectionHandler = new Survey_Section();
$groupHandler = new Group();

/* If a question was submitted
 */
if (!empty($_POST)) {
    if (!is_numeric($_POST['type_id'])) {
        error(116);
    }

    $section = $sectionHandler->getByID(Scrub::text($_POST['section_id']));
    $assess = $assessHandler->getByID($section['assess_id']);

    if ($assessHandler->isDeleted($assess['assess_id'])) {
        error(112);
    }

    if (!$assessHandler->isOwner($assess['assess_id']) && ($groupHandler->getPermission($assess['category_id']) < WRITE_ACCESS)) {
        error(130);
    }

    if ($sectionHandler->isDeleted($section['section_id'])) {
        error(115);
    }

    $questionHandler = $factory->createQuestionModel(Scrub::text($_POST['type_id']));
    $answerHandler = $factory->createAnswerModel(Scrub::text($_POST['type_id']));

	/* If configuration was provided
	 */
	$config = null;

	if (isset($_POST['configuration'])) {
		$allConfiguration = json_decode($_POST['configuration']);

		$config = array();

		/* Compress checkboxes
		 */
		foreach($allConfiguration as $currentConfiguration) {
			$config[$currentConfiguration->name] = $currentConfiguration->value;
		}
	}

	/* Put that data in the question
	 */
	$data = $_POST;
	$data['config'] = json_encode($config);

	$questionHandler->update($data);
    $answerHandler->update($_POST);

    /* Create new question
     */
    SitePage::localRedir('/survey/question/create/' . $section['section_id']);
}
else {
    if (!is_numeric($this->arg[0])) {
        error(115);
    }

    /* Render modify page
     */
    $this->setFile(TEMPLATE_content, 'survey/question/modify.html');
    $this->setTitle('Modify Question');
    $this->setBlock(TEMPLATE_content, 'answerInputs', 'answerInputs_block');
    $this->setBlock(TEMPLATE_content, 'questionType', 'questionType_block');
    $this->setBlock(TEMPLATE_content, 'allQuestions', 'allQuestions_block');
	$this->setBlock(TEMPLATE_content, 'has_answer_configuration', 'has_answer_configuration_block');
	$this->setBlock('has_answer_configuration', 'answer_configuration', 'answer_configuration_block');

    $questionHandler = new Survey_Question();
    $question = $questionHandler->getByID($this->arg[0]);

    if ($question) {
        $section = $sectionHandler->getByID($question['section_id']);
    }

    if ($section) {
        $assess = $assessHandler->getByID($section['assess_id']);
    }

    if ($assessHandler->isDeleted($section['assess_id'])) {
        error(112);
    }

    if (!$assessHandler->isOwner($assess['assess_id']) && ($groupHandler->getPermission($assess['category_id']) < WRITE_ACCESS)) {
        error(130);
    }

    if ($sectionHandler->isDeleted($section['section_id'])) {
        error(115);
    }

    try {
        $folderMapper = new FolderMapper();
        $folder = $folderMapper->findById($assess['category_id']);
    }
    catch (\Exception $e) {
        error(13);
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
		$this->setFile('answer_configuration_fragment', 'survey/answer/' . $factory->getQuestionType($question['type_id']) . '/configuration.html');
		$this->setBlock('answer_configuration_fragment', 'answer_configuration_form_fragment', 'answer_configuration_form_fragment_block');

		$answerHandler->configure($this, $question['config']);

		$this->parse('answer_configuration_block', 'answer_configuration_fragment');
		$this->parse('has_answer_configuration_block', 'has_answer_configuration');
	}
	else {
		$this->setVar(array(
			'has_answer_configuration_block' => ''
		));
	}

    /* Load answer template into answerOption block
     */
    $this->setFile('answerFormFragment', 'survey/answer/' . $factory->getQuestionType($question['type_id']) . '/modify.html');
    $this->setBlock('answerFormFragment', 'answerOption', 'answerOption_block');

    $answerHandler->modify($this, $question['question_id']);

    $this->parse('answerInputs_block', 'answerFormFragment', true);

    $this->setVar(array(
        'QUESTIONTYPE_TYPE_ID' => $currentQuestionType['type_id'],
        'QUESTIONTYPE_DESCRIPTION' => Scrub::html($currentQuestionType['description']),
        'QUESTIONTYPE_SELECTED' => $selected,
    ));

    /* Render contents of this Survey
     */
    if (empty($section)) {
        error(51);
    }

    /* Show this Survey' contents
      */
    $this->setFile('assessContentsFragment', 'assess/contents.html');

    /* Options for importing into the Question Bank
      */
    $this->setBlock(TEMPLATE_content, 'question_category', 'question_category_block');

    $assessHandler = new Survey_Main();
    $assess = $assessHandler->getByID($section['assess_id']);

    $this->setVar(array(
        'ASSESS_SECTION_TITLE' => $section['title'],
        'ASSESS_ASSESS_ID'     => $section['assess_id'],
        'ASSESS_KIND_LC'       => 'survey',
        'ASSESS_KIND'          => 'Survey',
        'ASSESS_CATEGORY_ID'   => $assess['category_id'],
        'SECTION_ID'           => $question['section_id'],
        'SEQUENCE'             => $question['sequence'],
        'QUESTION_ID'          => $question['question_id'],
        'QUESTION'             => Scrub::text($question['question']),
        'MARKS'                => $question['marks'],
        'DISABLED_SELECTED'    => '',
    ));

    $assessHandler->renderAssessContents($section['assess_id'], $this);
    $this->parse('allQuestions_block', 'assessContentsFragment', true);

    /* Derive and render a category list
      */
    $questionBankCategoryHandler = new Survey_Question_Category();

    $questionCategoryList = new Survey_Question_Category_List($this, $assess['assess_id'], 0, $questionBankCategoryHandler->getPath());
    $questionCategoryList->render();

    /* Render out breadcrumbs
     */
    $trail = new Breadcrumbs(Schoolbox\Resource\Folder\Mapper::getInstance());
    $trail->renderIntoSitePage($this, 'BREADCRUMB', $folder, true);
}

