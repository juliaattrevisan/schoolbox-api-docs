<?php
/**
 * Assessment creation. Assessments must always belong in a Category.
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
use Schoolbox\Assessment\Course\UnitInstance;
use Schoolbox\Assessment\Course\UnitInstanceMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Breadcrumbs;

$groupHandler = new Group();
$assessHandler = new Assess_Main();

if (!empty($_POST)) {
    if ($groupHandler->getPermission($_POST['category_id']) < WRITE_ACCESS) {
        error(126);
    }

    /* Cater for unlimited duration tests. Can't use zero because of countdown
     * timer, so we use -1
     */
    $duration = ($_POST['duration']['hour'] * 60 + $_POST['duration']['minute']) * 60;

    if ($duration == 0) {
        $duration = Assess_Main::UNLIMITED_DURATION;
    }

    $assessment = new OnlineAssessment();
    $assessment
        ->setCategory($_POST['category_id'])
        ->setUser($_SESSION['id'])
        ->setTitle($_POST['title'])
        ->setDescription($_POST['description'])
        ->setDuration($duration)
        ->setMaxAttempts($_POST['max_attempts'])
        ->setWeight($_POST['weight'])
        ->setAvailability('assess')
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
    $is_task       = !empty($_POST['is_task']);
    $is_unit       = !empty($_POST['unit_id']);
    $task_instance = new Task\Instance();
    if ($is_task) {
        $task = new Task();
        $task
            ->setCategory($_POST['category_id'])
            ->setOwner($_SESSION['id'])
            ->setTitle($_POST['title']);
        TaskMapper::getInstance()->save($task);
        $assessment->setTask($task);
    }
    else {
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

        if ($start->getUnixTimestamp() >= $finish->getUnixTimestamp()) {
            error(145);
        }
        $assessment->setStart($start)->setFinish($finish);
    }

    if ($is_unit) {
        /** @var UnitInstance $unit */
        try {
            $unit = UnitInstanceMapper::getInstance()->findByIdAndCategoryId($_POST['unit_id'], $_POST['category_id']);
        }
        catch (Schoolbox\Mapper\Exception $e) {
            error(2);
        }
        $task_instance
            ->setTitle($assessment->getTitle())
            ->setUnitInstance($unit)
            ->setOwner($_SESSION['id'])
        ;
        Task\InstanceMapper::getInstance()->save($task_instance);
        $assessment->setTaskInstance($task_instance);
    }

    OnlineAssessmentMapper::getInstance()->save($assessment);

    // Is there a task for this item? If so, create it and link it back to this
    // new item.
    if ($is_task) {
        $task = $assessment->getTask();
        $task->setAssessment($assessment);
        TaskMapper::getInstance()->save($task);
    }
    if ($is_unit) {
        $task_instance = $assessment->getTaskInstance();
        $task_instance->setAssessment($assessment);
        Task\InstanceMapper::getInstance()->save($task_instance);
    }

    if (!($assess_id = $assessment->getId())) {
        error(112);
    }

    SitePage::localRedir('/assess/section/create/' . $assess_id);
}
else {
    // Params:
    // 0: category ID
    // 1: "task" or "unit:$unit_id"
    $category_id    = $this->arg[0];
    $is_task        = ($this->arg[1] === 'task');
    $unit_id        = null;
    $is_unit        = preg_match('/^unit:(\d+)$/', $this->arg[1], $matches);
    $assessDate     = new Alaress_Date_Format(time(), 'U');
    $assessEndDate  = new Alaress_Date_Format(strtotime('+1 week'), 'U');
    $startHour      = date('g', time() + 3600);
	$startMinute    = '00';
    $startMeridiem  = date('A', time() + 3600);
    $finishHour     = date('g', time() + 2 * 3600);
	$finishMinute   = '00';
    $finishMeridiem = date('A', time() + 2 * 3600);

    if ($is_unit) {
        $unit_id = $matches[1];

        /** @var UnitInstance $unit */
        try {
            $unit           = UnitInstanceMapper::getInstance()->findByIdAndCategoryId($unit_id, $category_id);
            $assessDate     = $unit->getOpenDate();
            $end            = $unit->getDueDate()->getDateTimeObject();
            $end->modify('00:00:00 +1 day');
            $assessEndDate  = new Alaress_Date_Format($end);
            $startHour      = $assessDate->get12Hour();
            $startMeridiem  = $assessDate->getMeridiem();
            $finishHour     = $assessEndDate->get12Hour();
            $finishMeridiem = $assessEndDate->getMeridiem();
        }
        catch (Schoolbox\Mapper\Exception $e) {
            error(126);
        }
    }

    if (!is_numeric($category_id)) {
        error(117);
    }

    try {
        $folderMapper = new FolderMapper();
        $folder = $folderMapper->findById($category_id);
    } catch (\Schoolbox\Mapper\Exception $e) {
        error(117);
        exit;
    }

    if ($groupHandler->getPermission($category_id) < WRITE_ACCESS) {
        error(126);
    }

    /* Render create page
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
    ));

    $this->setTitle('Manage Quiz');

    if (Config::get('curriculum_tagging_enabled')) {
        $this->parse('curriculum_code_input_block', 'curriculum_code_input');
    } else {
        $this->setVar('curriculum_code_input_block', '');
    }

    $this->setVar(array(
        'ACTION'                    => 'create',
        'ASSESS_HEADING'            => 'New Quiz',
        'ASSESS_ACTION'             => 'Create',
        'ASSESS_KIND_LC'            => 'assess',
        'ASSESS_KIND'               => 'Quiz',
        'ASSESS_IS_TASK'            => $is_task ? 1 : 0,
        'ASSESS_ASSESS_ID'          => '',
        'ASSESS_CATEGORY_ID'        => $folder->getId(),
        'ASSESS_CATEGORY_NAME'      => $folder->getName(),
        'ASSESS_UNIT_ID'            => $unit_id,
        'ASSESS_TITLE'              => '',
        'ASSESS_WEIGHT'             => 0,
        'ASSESS_DESCRIPTION'        => '',
        'ASSESS_START_DATE'         => $assessDate->getLocalShort(),
        'ASSESS_FINISH_DATE'        => $assessEndDate->getLocalShort(),
        'ASSESS_COMPLETION_MESSAGE' => '',
        'HAS_ALL_QUESTIONS'         => 'large-12',
        // Curriculum codes: by default use the ones that are part of the folder
        'CURRICULUM_CODE_INPUT'     => $this->getTwig()->render(
            'sub/curriculumCodeSelector.twig',
            ['codeList' => $folder->getCurriculumNodes(), 'renderButton' => false,]
        ),
        'CURRICULUM_BROWSE_BUTTON' => $this->getTwig()->render(
            'sub/curriculumBrowseButton.twig'
        ),
    ));

    /* Render starting times
     */
	$this->setVar(array(
		'START_HOUR_VALUE' => $startHour,
		'START_MINUTE_VALUE' => $startMinute,
	));

	foreach (array('AM', 'PM') as $meridiem) {
        if ($meridiem == $startMeridiem) {
            $selected = 'selected=\'selected\'';
        }
		else {
            $selected = '';
        }

        $this->setVar(array(
            'START_MERIDIEM_VALUE' => $meridiem,
            'START_MERIDIEM_SELECTED' => $selected
        ));

        $this->parse('startMeridiem_block', 'startMeridiem', true);
    }

    /* Render finish times
     */
	$this->setVar(array(
		'FINISH_HOUR_VALUE' => $finishHour,
		'FINISH_MINUTE_VALUE' => $finishMinute,
	));

	foreach (array('AM', 'PM') as $meridiem) {
        if ($meridiem == $finishMeridiem) {
            $selected = 'selected=\'selected\'';
        }
		else {
            $selected = '';
        }

        $this->setVar(array(
            'FINISH_MERIDIEM_VALUE' => $meridiem,
            'FINISH_MERIDIEM_SELECTED' => $selected
        ));

        $this->parse('finishMeridiem_block', 'finishMeridiem', true);
    }

    // Task or instance?
    if (!$is_task) {
        $this->parse('assess_timespan_block', 'assess_timespan');
    }

    /* Resit amounts
      */
    for ($i = 1; $i < 11; $i++) {
        $this->setVar(array(
            'MAX_ATTEMPTS_VALUE' => $i,
            'MAX_ATTEMPTS_CHECKED' => $i==1 ? 'selected="selected"':'',
        ));

        $this->parse('max_attempts_block', 'max_attempts', true);
    }

    /* Set default to be 1 hour
      */
    $this->setVar(array(
        'DURATION_HOUR_VALUE' => 0,
        'DURATION_MINUTE_VALUE' => 0,
    ));

	/* Visibility dropdown
	 */
	$this->setBlock(TEMPLATE_content, 'marktype', 'marktype_block');
	$widgets = new \Schoolbox\Assessment\Widgets();
	$widgets->outputVisibilityDropdown($this, \Config::get('submission_access'));
	$this->parse('marktype_block', 'marktype');

    $this->parse('importXML_block', 'importXML');

    /* Render out breadcrumbs
     */
    $trail = new Breadcrumbs(Schoolbox\Resource\Folder\Mapper::getInstance());

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
            'text' => 'New Quiz',
            'href' => '',
            'class' => 'active',
        ]
    ]);
}


