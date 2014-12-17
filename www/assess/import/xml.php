<?php
/**
 * Assessment export. Uses the handlers to export their question types into a
 * Moodle format.
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

if (!is_numeric($this->arg[0])) {
    error(18);
}

if ($_FILES['importXML']['tmp_name']) {
    $data = file_get_contents($_FILES['importXML']['tmp_name']);
}
else {
    error(134);
}

SitePage::extendExecutionTime(300);
$import = new Assess_Import_Xml($this->arg[0], new Assess_Main(), new Assess_Section(), new Assess_Factory(), $data, $this);
$import->import();

/* For the Course Builder
 */
/** @var OnlineAssessment $assessment */
$is_task = !empty($_POST['is_task']);
if ($is_task) {
	$task = new Task();
	$task
		->setCategory($this->arg[0])
		->setOwner($_SESSION['id'])
		->setTitle($import->getTitle());

	TaskMapper::getInstance()->save($task);

	$assessment = OnlineAssessmentMapper::getInstance()->findById($import->getAssessId());
	$assessment->setTask($task);
	OnlineAssessmentMapper::getInstance()->save($assessment);

	$task->setAssessment($assessment);
	TaskMapper::getInstance()->save($task);
}
elseif (!empty($_POST['unit_id'])) {
	$task_instance = new Task\Instance();
	$task_instance
		->setUnitInstance($_POST['unit_id'])
		->setOwner($_SESSION['id'])
		->setTitle($import->getTitle());

	$assessment = OnlineAssessmentMapper::getInstance()->findById($import->getAssessId());
	$assessment->setTaskInstance($task_instance);
	OnlineAssessmentMapper::getInstance()->save($assessment);

	$task_instance->setAssessment($assessment);
	Task\InstanceMapper::getInstance()->save($task_instance);
}

SitePage::localRedir('/assess/modify/' . $import->getAssessId());
