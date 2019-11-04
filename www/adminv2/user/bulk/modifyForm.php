<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

// sitepage context is required
use Schoolbox\Realtime\Routing\Cache\WritableCache;
use Schoolbox\Error\Mapper as ErrorMapper;

if (empty($this)) {
    require_once __DIR__ . '/../../../../conf/core/require.php';
    $kernel = new \Schoolbox\Application\WebRequest\Kernel();
    // go away
    error(2);
}

if (!isset($_POST['userId']) || empty($_POST['userId'])) {
    error(ErrorMapper::ERROR_PROCESSING, 'No user selected.');
}

/** @var SitePage $this */

SitePage::extendExecutionTime(300);

$userMapper = \Schoolbox\UserBulkMapper::getInstance();

$modified = new \Schoolbox\Collection();

$userIds = [];
$userIds = array_filter(explode(',', $_POST['userId']));

if (isset($_POST['updateUsers'])) {
    if (count($userIds)) {
        $message = "{$_SESSION['user']->getFullname()} ({$_SESSION['user']->getId()}) started a user bulk modify";

        $userMapper->log($message, array());

        /* Modify each user
         */
        foreach ($userIds as $currentUserId) {
            $user = $userMapper->findById($currentUserId);

            $userMapper->bulkModifyUpdate(
                $user,
                $_POST['apply_campus'],
                $_POST['apply_role'],
                $_POST['apply_year'],
                $_POST['apply_eportfolio'],
                $_POST['apply_emailfromusername'],
                $_POST['apply_externalmailclient'],
                $_POST['apply_webmail'],
                $_POST['apply_accountenabled'],
                $_POST['apply_hidecontacts'],
                $_POST['apply_hidetimetable'],
                $_POST['apply_position_title'],
                $_POST['apply_displaygradegraph']
            );

            $modified->add($user);
        }
    }
} else {
    if (isset($_POST['deleteUsers'])) {
        $userCollection = $userMapper->findByColumn('id', $userIds);
        $userMapper->delete($userCollection);

        $modified = $userCollection;
    }
}

try {
    /** @var WritableCache $cache */
    $cache = $this->getDi()->get('subscriptionCache');
    $cache->cacheUsers(INSTANCE_ID, $userIds);
} catch (\RuntimeException|\Aura\Di\Exception $e) {
    $userMapper->log($e->getMessage(), [ 'exception'=>$e ]);
}

/* Show some confirmation stuff
 */
$this->setFile(TEMPLATE_content, 'adminv2/user/bulk/modifyResults.html');


$this->setVar(
    array(
        'MODIFIED_COUNT' => $modified->count(),
        'OUTPUT_LOG'     => nl2br(Scrub::text($userMapper->getOutputMessage())),
    )
);

