<?php
/**
 * FullCalendar source for submission related events.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

$user_mapper = new \Schoolbox\UserMapper();
$user = $user_mapper->findById($_GET['userId']);
try {
    Calendar_Layout_Full_Javascript::permissionCheck(
        $_GET['userId'],
        $_SESSION['user'],
        new Group()
    );
} catch (Exception $e) {
    error($e->getCode());
}

// Render it
$renderer = new \Calendar\Event\Collection\Renderer(
    new \Calendar_Api_EventType_Submission_Schoolbox_ForDueWork($user, array()),
    $_GET['start'],
    $_GET['end'],
    $user,
    $this
);
echo "[";
echo $renderer->renderAll('javascript');
/* Final null element to help keep IE happy
 */
echo "{}]";
exit;
