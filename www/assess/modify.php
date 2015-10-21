<?php
/**
 * Assessment modification.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */

use Schoolbox\Assessment\Component\OnlineAssessment;
use Schoolbox\Assessment\Component\OnlineAssessmentMapper;
use Schoolbox\Assessment\Course\Task;
use Schoolbox\Assessment\Course\TaskMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Breadcrumbs;

$groupHandler = new Group();
$assessHandler = new Assess_Main();

$assess = $assessHandler->getByID($this->arg[0]);

if ($assessHandler->isDeleted($this->arg[0])) {
    error(112);
}

if (!$assessHandler->isOwner($this->arg[0]) && ($groupHandler->getPermission($assess['category_id']) < WRITE_ACCESS)) {
    error(125);
}

if (!empty($_POST)) {

    /* Cater for unlimited duration tests. Can't use zero because of countdown
     * timer, so we use -1
     */
    $duration = ($_POST['duration']['hour'] * 60 + $_POST['duration']['minute']) * 60;

	if ((!$_POST['durationSelected']) || ($duration == 0)) {
        $duration = Assess_Main::UNLIMITED_DURATION;
    }

    /** @var OnlineAssessment $assessment */
    $assessment = OnlineAssessmentMapper::getInstance()->findById($this->arg[0]);
    $assessment
        ->setCategory($_POST['category_id'])
        ->setUser($_SESSION['id'])
        ->setTitle($_POST['title'])
        ->setDescription($_POST['description'])
        ->setDuration($duration)
        ->setMaxAttempts($_POST['max_attempts'])
        ->setWeight($_POST['weight'])
        ->setMarkVisibility($_POST['mark_visibility'])
        ->setCompletionMessage($_POST['completion_message'])
    ;
    if (isset($_POST['curriculumCodes'])) {
        $assessment->setCurriculumNodes(
            Schoolbox\Curriculum\Node\Mapper::getInstance()
                ->findByColumn('id', $_POST['curriculumCodes'])
        );
    }

    // Is this a task? If so, just set the start and finish to now.
    $task_id = $assessment->getTask()->getId();
    if ($task_id) {
        $task = TaskMapper::getInstance()->findById($task_id);
        $task->setTitle($_POST['title']);
        TaskMapper::getInstance()->save($task);
    }
    else {
        /* Format date, time
         */
        $start = Alaress_Date_Format::newLocalShort($_POST['start']['date'])
            ->set12Hour($_POST['start']['hour'], $_POST['start']['meridiem'])
            ->setMinute($_POST['start']['minute'])
        ;
        $finish = Alaress_Date_Format::newLocalShort($_POST['finish']['date'])
            ->set12Hour($_POST['finish']['hour'], $_POST['finish']['meridiem'])
            ->setMinute($_POST['finish']['minute'])
        ;

		/* Check for valid dates
		 */
		if (!$start->isValid() || !$finish->isValid()) {
			error(15);
		}

		/* Check that finish time cannot be before start time
		  */
        if ($start->getUnixTimestamp() >= $finish->getUnixTimestamp()) {
            error(145);
        }
        $assessment->setStart($start)->setFinish($finish);
    }
    OnlineAssessmentMapper::getInstance()->save($assessment);
    if ($assessment->getTaskInstance()->getId()) {
        $task_instance = Task\InstanceMapper::getInstance()->findById($assessment->getTaskInstance()->getId());
        $task_instance->setTitle($_POST['title']);
        Task\InstanceMapper::getInstance()->save($task_instance);
    }
    SitePage::localRedir('/assess/summary/' . $this->arg[0]);
}
else if(is_numeric($_GET['mark_visibility'])) {
    /* This is used for an ajax update request of just the visibility */
    try {
        $assessment = OnlineAssessmentMapper::getInstance()->findById($this->arg[0]);
        $assessment->setMarkVisibility($_GET['mark_visibility']);
        OnlineAssessmentMapper::getInstance()->save($assessment);
        print "success";
    } catch (Exception $e) {
        print "failure";
    }
    exit;
}
else {

    /* Render modify page
     */
    $this->setFile(TEMPLATE_content,   'assess/modify.html');
    $this->setBlock(TEMPLATE_content,  'assess_timespan', 'assess_timespan_block');
    $this->setBlock('assess_timespan', 'startMeridiem', 'startMeridiem_block');
    $this->setBlock('assess_timespan', 'finishMeridiem', 'finishMeridiem_block');
    $this->setBlock(TEMPLATE_content,  'importXML', 'importXML_block');
    $this->setBlock(TEMPLATE_content,  'max_attempts', 'max_attempts_block');
    $this->setBlock(TEMPLATE_content, 'curriculum_code_input', 'curriculum_code_input_block');

    $this->setVar(array(
        'assess_timespan_block' => '',
        'question_block' => '',
    ));

    $this->setTitle('Manage Quiz');

    /** @var OnlineAssessment $assessment */
    $task_id = null;
    try {
        $assessment = OnlineAssessmentMapper::getInstance()->findById($this->arg[0]);
        $task_id    = $assessment->getTask()->getId();
    }
    catch (Exception $ex) {
        error(112);
    }

    $assessStartDate  = $assessment->getStart();
    $assessFinishDate = $assessment->getFinish();

    if (Config::get('curriculum_tagging_enabled')) {
        $this->parse('curriculum_code_input_block', 'curriculum_code_input');
    } else {
        $this->setVar('curriculum_code_input_block', '');
    }

    $this->setVar(array(
        'ACTION'                    => 'modify',
        'ASSESS_HEADING'            => Scrub::text($assessment->getTitle()),
        'ASSESS_ACTION'             => 'Modify',
        'ASSESS_KIND_LC'            => 'assess',
        'ASSESS_KIND'               => 'Quiz',
        'ASSESS_IS_TASK'            => !empty($task_id) ? 1 : 0,
        'ASSESS_ASSESS_ID'          => $assessment->getId(),
        'ASSESS_CATEGORY_ID'        => $assessment->getCategory()->getId(),
        'ASSESS_CATEGORY_NAME'      => $assessment->getCategory()->getName(),
        'ASSESS_UNIT_ID'            => null,
        'ASSESS_TITLE'              => Scrub::text($assessment->getTitle()),
        'ASSESS_DESCRIPTION'        => Scrub::text($assessment->getDescription()),
        'ASSESS_WEIGHT'             => $assessment->getWeight(),
        'ASSESS_START_DATE'         => $assessStartDate->getLocalShort(),
        'ASSESS_FINISH_DATE'        => $assessFinishDate->getLocalShort(),
        'ALLOW_RESIT_CHECKED'       => $assessment->getMaxAttempts() == 1 ? 'checked=\'checked\'' : '',
        'ASSESS_DURATION_CHECKED'   => $assessment->getDuration() >= 0 ? 'checked=\'checked\'' : '',
        'ASSESS_COMPLETION_MESSAGE' => Scrub::text($assessment->getCompletionMessage()),
        'HAS_ALL_QUESTIONS'         => 'large-8',
        'CURRICULUM_CODE_INPUT'     => $this->getTwig()->render(
            'sub/curriculumCodeSelector.twig',
            ['codeList' => $assessment->getCurriculumNodes(), 'renderButton' => false,]
        ),
        'CURRICULUM_BROWSE_BUTTON' => $this->getTwig()->render(
            'sub/curriculumBrowseButton.twig'
        ),
    ));

    /* Render starting times
     */
	$this->setVar(array(
		'START_HOUR_VALUE' => $assessStartDate->get12Hour(),
		'START_MINUTE_VALUE' => $assessStartDate->getMinute(true),
	));

    foreach (array('AM', 'PM') as $meridiem) {
        $this->setVar(array(
            'START_MERIDIEM_VALUE' => $meridiem,
            'START_MERIDIEM_SELECTED' => $assessStartDate->isMeridiem($meridiem) ? 'selected=\'selected\'' : '',
        ));

        $this->parse('startMeridiem_block', 'startMeridiem', true);
    }

    /* Render finish times
     */
	$this->setVar(array(
		'FINISH_HOUR_VALUE' => $assessFinishDate->get12Hour(),
		'FINISH_MINUTE_VALUE' => $assessFinishDate->getMinute(true),
	));

	foreach (array('AM', 'PM') as $meridiem) {
        $this->setVar(array(
            'FINISH_MERIDIEM_VALUE' => $meridiem,
            'FINISH_MERIDIEM_SELECTED' => $assessFinishDate->isMeridiem($meridiem) ? 'selected=\'selected\'' : '',
        ));

        $this->parse('finishMeridiem_block', 'finishMeridiem', true);
    }

    /* Render duration
     */
    $duration = $assessHandler->parse_sec2hms($assessHandler->sec2hms($assessment->getDuration()));

    $this->setVar(array(
        'DURATION_HOUR_VALUE' => $duration['hours'],
        'DURATION_MINUTE_VALUE' => $duration['minutes'],
    ));

    if (!$task_id) {
        $this->parse('assess_timespan_block', 'assess_timespan');
    }

    /* Resit amounts
      */
    for ($i = 1; $i < 11; $i++) {
        $this->setVar(array(
            'MAX_ATTEMPTS_VALUE' => $i,
            'MAX_ATTEMPTS_CHECKED' => $i == $assessment->getMaxAttempts() ? 'selected="selected"' : '',
        ));

        $this->parse('max_attempts_block', 'max_attempts', true);
    }

	/* Visibility dropdown
	 */
	$this->setBlock(TEMPLATE_content, 'marktype', 'marktype_block');
	$widgets = new \Schoolbox\Assessment\Widgets();
	$widgets->outputVisibilityDropdown($this, $assessment->getMarkVisibility());
	$this->parse('marktype_block', 'marktype');

    /* Prevent importXML form from appearing
     */
    $this->setVar('importXML_block', '');

    /* Render out breadcrumbs
     */
    $folderMapper = new FolderMapper();
    $folder = $folderMapper->findById($assessment->getCategory()->getId());

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
            'text' => $assessment->getTitle(),
            'href' => "/assess/summary/{$assessment->getId()}",
            'class' => 'active',
        ]
    ]);
}

