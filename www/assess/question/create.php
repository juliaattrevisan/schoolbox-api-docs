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
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Breadcrumbs;

$assessHandler = new Assess_Main();
$sectionHandler = new Assess_Section();
$questionHandler = new Assess_Question();
$factory = new Assess_Factory();
$groupHandler = new Group();

/* If a question was submitted
 */
if (!empty($_POST)) {
    $section = $sectionHandler->getByID($_POST['section_id']);
    $assess = $assessHandler->getByID($section['assess_id']);

    if (!$assessHandler->isOwner($assess['assess_id']) && ($groupHandler->getPermission($assess['category_id']) < WRITE_ACCESS)) {
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
		foreach($allConfiguration as $currentConfiguration) {
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

    /* Need to keep this so non-ajaxed questions still redirect
     */
    SitePage::localRedir('/assess/question/create/' . $section['section_id']);
} else {
    if (!is_numeric($this->arg[0])) {
        error(51);
    }

    $section = $sectionHandler->getByID($this->arg[0]);
    $assess = $assessHandler->getByID($section['assess_id']);

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
        error(117);
    }

    $assessStartDate = new Alaress_Date_Format(strtotime($assess['start']), 'U');
    $assessFinishDate = new Alaress_Date_Format(strtotime($assess['finish']), 'U');

    /* Render create page
     */
    $this->setFile(TEMPLATE_content, 'assess/question/create.html');
    $this->setBlock(TEMPLATE_content, 'answerInputs', 'answerInputs_block');
    $this->setBlock(TEMPLATE_content, 'allQuestions', 'allQuestions_block');
    $this->setBlock(TEMPLATE_content, 'questionType', 'questionType_block');
	$this->setBlock(TEMPLATE_content, 'has_answer_configuration', 'has_answer_configuration_block');
	$this->setBlock('has_answer_configuration', 'answer_configuration', 'answer_configuration_block');

	$this->setTitle('Create Question');

    $this->setVar(array(
		'question_block' => '',
		'has_answer_configuration_block' => ''
	));

    $this->setVar(array(
        'ASSESS_TITLE'          => $assess['title'],
        'ASSESS_ASSESS_ID'      => $section['assess_id'],
        'ASSESS_KIND_LC'        => 'assess',
        'ASSESS_KIND'           => 'Quiz',
        'ASSESS_START'          => $assessStartDate->getShort(),
        'ASSESS_FINISH'         => $assessFinishDate->getShort(),
        'ASSESS_CATEGORY_ID'    => $folder->getId(),
        'ASSESS_CATEGORY_NAME'  => $folder->getName(),
        'ASSESS_DURATION'       => $assess['duration'] == Assess_Main::UNLIMITED_DURATION ? 'Unlimited' : $assessHandler->sec2hms($assess['duration']),
        'SECTION_ID'            => $this->arg[0],
        'CURRENT_SECTION_TITLE' => Scrub::text($section['title']),
        'SEQUENCE'              => $questionHandler->getNextSequence($section['section_id']),
        'QUESTION'              => '',
        'MARKS'                 => '',
    ));

    /* If a particular question type has been selected; otherwise ignore it
     */
    if (is_numeric($this->arg[1])) {
        $answerHandler = $factory->createAnswerModel($this->arg[1]);

        $this->setVar(array(
            'IMPORT_SELECTED' => '',
            'DISABLED_SELECTED' => '',
        ));
    } else if ($this->arg[1] == 'import') {
        $this->setVar(array(
            'IMPORT_SELECTED' => 'selected=\'selected\'',
            'ANSWER_INPUTS' => ''
        ));
    } else {
        $this->setVar(array(
            'IMPORT_SELECTED' => '',
            'DISABLED_SELECTED' => 'selected=\'selected\'',
            'ANSWER_INPUTS' => $answerTemplate
        ));
    }

    /* Show question type options
     */
    $setTemplate = false;

    $questionTypes = $questionHandler->getAllQuestionTypes();

    foreach ($questionTypes as $currentQuestionType) {
        if (is_numeric($this->arg[1]) && ($this->arg[1] == $currentQuestionType['type_id'])) {
            $selected = 'selected=\'selected\'';

            /* Load answer template into answerOption block
             */
            $this->setFile('answerFormFragment', 'assess/answer/' . $factory->getQuestionType($this->arg[1]) . '/create.html');
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
				$this->setFile('answer_configuration_fragment', 'assess/answer/' . $factory->getQuestionType($this->arg[1]) . '/configuration.html');
				$this->setBlock('answer_configuration_fragment', 'answer_configuration_form_fragment', 'answer_configuration_form_fragment_block');

				$answerHandler->configure($this, json_encode(array()));

				$this->parse('answer_configuration_block', 'answer_configuration_fragment');
				$this->parse('has_answer_configuration_block', 'has_answer_configuration');
			}
			else {
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

    /* If the answer template wasn't set, then we haven't chosen an answer type
     * so hide the block that would normally contain the answer type's form.
     */
    if (!$setTemplate) {
        $this->setVar('answerInputs_block', '');
    }

    /* Show this Assess' contents
     */
    $this->setFile('assessContentsFragment', 'assess/contents.html');

    $assessHandler = new Assess_Main();
    $assessHandler->renderAssessContents($section['assess_id'], $this);

    $assess = $assessHandler->getByID($section['assess_id']);

    $this->setVar(array(
        'ASSESS_CATEGORY_ID' => $assess['category_id'],
    ));

    $this->parse('allQuestions_block', 'assessContentsFragment', true);

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
            'text' => $assess['title'],
            'href' => "/assess/summary/{$assess['assess_id']}",
            'class' => '',
        ],
        [
            'text' => $section['title'],
            'href' => "/assess/summary/{$assess['assess_id']}",
            'class' => '',
        ],
        [
            'text' => 'New Question',
            'href' => '',
            'class' => 'active',
        ]
    ]);
}

