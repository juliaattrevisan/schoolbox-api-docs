<?php
/**
 * FullCalendar source for submission related events.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Calendar\Event\Collection\Renderer;
use Calendar\Event\Type\EventBase;
use Calendar\Event\Type\Submission\Event as SubmissionEvent;
use Schoolbox\IConfigLoader;
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

/** @var Assessment\MapperProxy $assessmentDataSource */
$assessmentDataSource = $this->getDi()->get('assessmentDataSource');
$visibility           = new Visibility(Access::getInstance());
$calendarConfig       = Calendar_Api_Config_Mapper::getInstance()->findOneForClass(Calendar_Api_EventType_Submission_Schoolbox_ForDueWork::class);
$colourManager        = new Timetable_ColourManager(Timetable_ColourMapper::getInstance()->findAll());
$calendarApi          = new Calendar_Api_EventType_Submission_Schoolbox_ForDueWork(
    $calendarConfig->getId(),
    $user,
    $viewer,
    $assessmentDataSource,
    $visibility,
    $colourManager
);
$renderer             = new Renderer($calendarApi, (int)$_GET['start'], (int)$_GET['end'], $user, $this->getDi(), $viewer);

// until this script becomes an Aura route, this is really the best we can do...
/** @var IConfigLoader $config */
$config   = $this->getDi()->get('config');
$strategy = $config->get('site_location') === 'mggs'
    ? SubmissionEvent::RENDER_FORMAT_JSON_PBL
    : EventBase::RENDER_FORMAT_JSON;

printf('[%s]', \implode(',', $renderer->renderAll($strategy)));
exit;
