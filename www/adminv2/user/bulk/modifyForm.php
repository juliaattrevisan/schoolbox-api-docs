<?php
/**
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

SitePage::extendExecutionTime(300);

$userMapper = \Schoolbox\UserBulkMapper::getInstance();

$modified = new \Schoolbox\Collection();

$userIds = array_filter(explode(',', $_POST['userId']));
if (count($userIds) === 0) {
    error(ErrorMapper::ERROR_PROCESSING, 'No user selected.');
}

if (isset($_POST['updateUsers'])) {
    $message = "{$_SESSION['user']->getFullname()} ({$_SESSION['user']->getId()}) started a user bulk modify";
    $userMapper->log($message);


    /* Prepared Statements ftw
     *   One big updater
     *   Campus Deleter
     *   Campus Adder
     */

    /* Gather a set of ids and cast them to ints
     * Implode them into a csv for a WHERE IN clause
     */

    /* Collate SET commands for each of the scalar params provided
     */

    /* Set up a secondary query stack for campus involvements
     * because we have to delete/insert those specifically for each user
     */

    /* Execute the primary query, which is UPDATE user SET ... WHERE id IN ()
     * That updates the scalars for all selected users in one mega query
     */

    /* Iteratively execute the secondary query which updates campus involvements
     * you might be able to reduce queries further by using delete with joins
     * prior to inserting the updated campuses; or doing a diff, but that costs
     * you a query just to start and even then you need to process all rows
     * (we won't be in mapper land with its hydration benefits)
     *
     * https://stackoverflow.com/questions/652770/delete-with-join-in-mysql)
     */





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
} catch (\RuntimeException|\Aura\Di\Exception $e) {
    $userMapper->log($e->getMessage());
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
