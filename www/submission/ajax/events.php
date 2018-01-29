<?php
/**
 * FullCalendar source for submission related events.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Learning\Assessment\DataModel as Assessment;

/** @var SitePage $this */
$user_mapper = new \Schoolbox\UserMapper();
$user = $user_mapper->findById($_GET['userId']);
$viewer = $_SESSION['user'];
try {
    Calendar_Layout_Full_Javascript::permissionCheck(
        $_GET['userId'],
        $_SESSION['user'],
        new Group()
    );
} catch (Exception $e) {
    error($e->getCode());
}

/**
 * @var Assessment\MapperProxy $assessmentDataSource
 * @var Participant\DataSource $participantDataSource
 */
$assessmentDataSource  = $this->getDi()->get('assessmentDataSource');
$participantDataSource = $this->getDi()->get('participantDataSource');
$visibility            = new Visibility(Access::getInstance());
$config                = Calendar_Api_Config_Mapper::getInstance()->findOneForClass(Calendar_Api_EventType_Submission_Schoolbox_ForDueWork::class);
$colourManager         = new Timetable_ColourManager(Timetable_ColourMapper::getInstance()->findAll());
$calendarApi           = new Calendar_Api_EventType_Submission_Schoolbox_ForDueWork(
    $config->getId(),
    $user,
    $assessmentDataSource,
    $participantDataSource,
    $visibility,
    $colourManager
);
$renderer              = new Calendar\Event\Collection\Renderer($calendarApi, (int)$_GET['start'], (int)$_GET['end'], $user, $this, $viewer);

// Render it
echo "[";
echo $renderer->renderAll('javascript');
/* Final null element to help keep IE happy
 */
echo "{}]";
exit;
