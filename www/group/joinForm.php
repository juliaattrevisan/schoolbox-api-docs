<?php

use Schoolbox\Mapper;
use Schoolbox\Notif\Notif;
use Schoolbox\Notif\Notifactory;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Realtime\Schedule\Event\Event;
use Schoolbox\Resource\GroupMapper;
use Schoolbox\Resource\Group;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

/** @var SitePage $page */
$page = $kernel->get('sitePage');
$page->initSession();
$groupMapper = GroupMapper::getInstance();
$uaMapper = UserAccessMapper::getInstance();

$response = 'error';


// handle both ajax and form submissions
$groupsSelected = [];
if (!empty($_POST['group'])) {
    $groupsSelected = $_POST['group'];
} elseif (is_numeric($_GET['join'] ?? false)) {
    $groupsSelected[$_GET['join']] = true;
} elseif (is_numeric($_GET['leave'])) {
    $groupsSelected[$_GET['leave']] = false;
}

/** @var Schoolbox\User $user */
$user = $_SESSION['user'];
foreach ($groupsSelected as $groupId => $isJoining) {

    /**
     * @var Group $group
     */
    $group = $groupMapper->findById($groupId);
    $uaExists = false;

    try {
        $ua = $uaMapper->findOneByColumnList(
            [
                'uid' => $user->getId(),
                'service' => UserAccess::SERVICE_HOMEPAGE,
                'sid' => $groupId,
            ]
        );
        $uaExists = true;
    } catch (Mapper\Exception $e) {
        $ua = new UserAccess();
        $ua->setUser($user)
            ->setFavourite(false)
            ->setAccessLevel(UserAccess::ACCESS_DENY)
            ->setService(UserAccess::SERVICE_HOMEPAGE)
            ->setServiceIdentifier($groupId);
    }

    if ($isJoining) {
        if ($group->isFree()) {
            // allow read access immediately for a free group
            if ($ua->getAccessLevel() < UserAccess::ACCESS_READ) {
                $ua->setAccessLevel(UserAccess::ACCESS_READ);
                $uaMapper->save($ua);
                $response = 'joined';
            }
        } else if ($group->isModerated() && !$uaExists) {
            // insert the user into the perms table, but deny them access (pending state)
            // If they already have access, keep it as is.
            $uaMapper->save($ua);
            $response = 'pending';
        }
    } elseif (!$group->isPrivate() && ($ua->getAccessLevel() < UserAccess::ACCESS_FULL)) {
        // non admin in moderated/free group can leave
        // delete the record that matches the given params
        $uaMapper->deleteByColumnList(
            array(
                'service' => UserAccess::SERVICE_HOMEPAGE,
                'uid'     => $user->getId(),
                'sid'     => $groupId
            )
        );
    }
}

// if there is an ajax flag, return a json response
if ($page->detectAjax()) {
    http_response_code(202);
    header('Content-Type: application/json');
    die(json_encode(array('status' => $response)));
}

/* If the Return parameter is set, go back to the referring page.
 * Otherwise redirect to the generic group/join page
 */
if ($_GET['return']) {
    $page->backToTheFuture(1);
} else {
    SitePage::localRedir('/group/join');
}

exit();
