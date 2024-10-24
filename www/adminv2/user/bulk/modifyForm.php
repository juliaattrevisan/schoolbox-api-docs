<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

// sitepage context is required
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Schoolbox\Realtime\Routing\Cache\WritableCache;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Admin\Navigation\Subnav;

if (empty($this)) {
    require_once __DIR__ . '/../../../../conf/core/require.php';
    $kernel = new \Schoolbox\Application\WebRequest\Kernel();
    // go away
    error(ErrorMapper::ERROR_INFORMATION_MISSING);
}

/* Superusers only
 */
if (!$this->getDi()->get('session')->get('user')->isSuperuser()) {
    error(ErrorMapper::ERROR_PERM_PAGE_VIEW);
}

if (!isset($_POST['userId']) || empty($_POST['userId'])) {
    error(ErrorMapper::ERROR_PROCESSING, 'No user selected.');
}

/** @var SitePage $this */

SitePage::extendExecutionTime();

$userMapper = \Schoolbox\UserBulkMapper::getInstance();

$modified = new \Schoolbox\Collection();

$userIds = array_filter(explode(',', $_POST['userId']));
if (count($userIds) === 0) {
    error(ErrorMapper::ERROR_PROCESSING, 'No user selected.');
}

// include the admin navigation
(new Subnav($this->getDi()->get('config'), $this->getDi()->get('user')))
    ->generatePhplib($this, '/adminv2/user/bulk/modify')
    ->generateBreadcrumbsHtml($this, '/adminv2/user/bulk/modify');

if (isset($_POST['updateUsers'])) {
    $message = "{$_SESSION['user']->getFullname()} ({$_SESSION['user']->getId()}) started a user bulk modify";
    $userMapper->log($message, true);

    $modified = (new \Schoolbox\Admin\User\BulkUpdater(
        $_SESSION['user'],
        $userMapper,
        $userIds,
        $_POST['apply_campus'],
        (int)$_POST['apply_role'],
        (int)$_POST['apply_year_id'],
        (int)$_POST['apply_house_id'],
        (int)$_POST['apply_eportfolio'],
        (int)$_POST['apply_emailfromusername'],
        (int)$_POST['apply_externalmailclient'],
        (int)$_POST['apply_accountenabled'],
        (int)$_POST['apply_hidecontacts'],
        (int)$_POST['apply_hidetimetable'],
        $_POST['apply_position_title'],
        (int)$_POST['apply_displaygradegraph'],
        (int)$_POST['apply_revokeToken']
    ))->update();
} else {
    if (isset($_POST['deleteUsers'])) {
        $userCollection = $userMapper->findByColumn('id', $userIds);
        $userMapper->bulkDelete($userCollection);

        $modified = $userCollection;
    }
}

// refresh subscription cache
try {
    /** @var WritableCache $cache */
    $cache = $this->getDi()->get('subscriptionCache');
    $cache->cacheUsers(INSTANCE_ID, $userIds);
} catch (\RuntimeException | NotFoundExceptionInterface | ContainerExceptionInterface $e) {
    $userMapper->log($e->getMessage(), true);
}

/* Show some confirmation stuff
 */
$this->setFile(TEMPLATE_content, 'adminv2/user/bulk/modifyResults.html');


$this->setVar(
    [
        'MODIFIED_COUNT' => $modified->count(),
        'OUTPUT_LOG'     => nl2br(Scrub::text($userMapper->getOutputMessage())),
    ]
);
