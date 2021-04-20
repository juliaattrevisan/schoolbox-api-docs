<?php

use Schoolbox\Application\WebRequest\Kernel;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Wiki\RevisionMapper;
use Schoolbox\Wiki\WikiMapper;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new Kernel();

/** @var SitePage $page */
$page = $kernel->get('sitePage');
$page->initSession();
$wikiHandler  = new Wiki();
$groupHandler = new Group(UserAccess::SERVICE_HOMEPAGE);

$wikiMapper     = WikiMapper::getInstance();
$revisionMapper = RevisionMapper::getInstance();
$folderMapper   = Schoolbox\Resource\Folder\Mapper::getInstance();
$errorMapper    = Schoolbox\Error\Mapper::getInstance();

$now = new Alaress_Date_Format(time(), 'U');

//Does a wiki with this name already exist on this homepage?
$potentialSlug = '';

$heading = trim($_POST['heading']);
$url  = new Url($heading);

if (is_ascii($_POST['heading'])) {
    $potentialSlug = $url->getSlug();
}

$pb = $wikiMapper->getPredicateBuilder();
$pb->equals('categoryID', (int)$_POST['categoryID'])
    ->notEquals('wikiID', (int)$_POST['wikiID'])
    ->equals('deleted', 'false')
    ->equals('slug', $potentialSlug);
$count = $wikiMapper->countByPredicates($pb);

if ($count > 0) {
    //You have been naughty and bypassed javascript validation
    error($errorMapper::ERROR_WIKI_NAME_IN_USE);
}

// posting a revision - wiki may or may not exist
if (isset($_POST['heading']) && is_numeric($_POST['categoryID'])) {

    if (strlen($heading) === 0) {
        error(2);
        exit;
    }
    // generate the revision object regardless of whether or not the wiki exists
    $revision = $revisionMapper->createModel()
        ->setHeading($heading)
        ->setCreated($now)
        ->setBody($revisionMapper->createStubLinks($_POST['body'], $_POST['categoryID']))
        ->setUser($_SESSION['user']);

    if (is_numeric($_POST['wikiID']) && strlen($_POST['slug'])) {
        // wiki ostensibly already exists: retrieve if possible
        try {
            /** @var Schoolbox\Wiki\Wiki $wiki */
            $wiki = $wikiMapper->findOneByColumnList(
                array(
                    'wikiID'    => $_POST['wikiID']
                )
            );
            // ok we got a wiki, but if it's not the one you were looking for, freak out
            if ($_POST['wikiID'] != $wiki->getId()) {
                throw new \Schoolbox\Mapper\Exception("Not the droid you're looking for");
            }
        } catch (\Schoolbox\Mapper\Exception $e) {
            error(2);
            exit;
        }

        //The slug now updates with the heading
        /* If the heading contains unicode it can't be used as a slug, so we
        use the wiki ID instead */
        if (is_ascii($_POST['heading'])) {
            $wiki->setSlug($url->getSlug());
        } else {
            /* Need to save new wiki before we can get the auto-incremented
             * ID to use in the slug.
             */
            $wikiMapper->save($wiki);
            $wiki->setSlug($wiki->getId());
        }
    } else {
        // request to create new wiki
        $wiki = $wikiMapper->createModel()
            ->setCategory($folderMapper->createModel()->setId($_POST['categoryID']))
            ->setLatestRevision(null)
            ->setCreated($now)
            ->setLocked(false)
            ->setDeleted(false);

        /* If the heading contains unicode it can't be used as a slug, so we
        use the wiki ID instead */
        if (is_ascii($_POST['heading'])) {
            $wiki->setSlug($url->getSlug());
        } else {
            /* Need to save new wiki before we can get the auto-incremented
             * ID to use in the slug.
             */
            $wikiMapper->save($wiki);
            $wiki->setSlug($wiki->getId());
        }

        // Only check component's create permissions for a new wiki.
        /* First check that Allow Create is active. If it is, continue;
         * otherwise proceed to the usual getPermission()
         */
        $value = \Component_ConfigMapper::getInstance()->findValue('allowCreate', (int)$_POST['instanceId']);

        /* Allow Create is off
         */
        if (((int)$value === 0) && ($groupHandler->getPermission($wiki->getCategory()->getId()) < UserAccess::ACCESS_WRITE)) {
            error(19);
        }
    }

    // check category permission
    if (($permission = $groupHandler->getPermission($wiki->getCategory()->getId())) < UserAccess::ACCESS_READ) {
        error(19);
    }

    // check locked-ness
    if ($wiki->getLocked() && $permission < UserAccess::ACCESS_WRITE) {
        error(19);
    }

    /* at this juncture, $revision has no ID; */
    // If new Wiki, save it so we can link the revision back to it
    if (!$wiki->getId()) {
        $wikiMapper->save($wiki);
    }

    // assign the wiki to the revision we just created
    $revision->setWiki($wiki);

    // persist our revision (which updates with the resultant ID)
    $revisionMapper->save($revision);

    // is there a previous revision that's snuck in and caused merge conflicts?
    if ($wiki->getLatestRevision() && $_POST['revision'] < $wiki->getLatestRevision()->getId()) {
        SitePage::localRedir("/wiki/merge/" . $wiki->getId());
        exit;
    }

    // no merge conflict? ok, persist the wiki entity with the latest revision
    $wiki->setLatestRevision($revision);

    $wikiMapper->save($wiki);

    SitePage::localRedir('/wiki/' . $wiki->getCategory()->getId() . '/' . $wiki->getSlug());

} elseif (is_numeric($_GET['revert']) && is_numeric($_GET['wikiID'])) {
    // applying a reversion
    $wiki = $wikiHandler->getByID($_GET['wikiID']);

    // Check permission
    if (($permission = $groupHandler->getPermission($wiki['categoryID'])) < UserAccess::ACCESS_WRITE) {
        error(19);
    }

    $wikiHandler->revert($wiki['wikiID'], $_GET['revert']);

    SitePage::localRedir('/wiki/' . $wiki['categoryID'] . '/' . $wiki['slug']);
} elseif (is_numeric($_GET['delete'])) {
    // deleting a wiki
    $wiki = $wikiHandler->getByID($_GET['delete']);

    // Check permission
    if (($permission = $groupHandler->getPermission($wiki['categoryID'])) < UserAccess::ACCESS_WRITE) {
        error(19);
    }

    $wikiHandler->delete($wiki['wikiID']);

    SitePage::localRedir('/homepage/' . $wiki['categoryID']);
} elseif (is_numeric($_GET['toggleLock'])) {
    // [un]locking the wiki

    // Wiki exists
    $wiki = $wikiHandler->getByID($_GET['toggleLock']);

    if (!isset($wiki['wikiID'])) {
        error(2);
    }

    // Check permissions
    if ($groupHandler->getPermission($wiki['categoryID']) < UserAccess::ACCESS_WRITE) {
        error(19);
    }

    $data = array(
        'wikiID' => $wiki['wikiID'],
        'locked' => $wiki['locked'] == 'true' ? 'false' : 'true',
    );

    $wikiHandler->update($data);

    $page->backToTheFuture(1);
} else {
    // le fail
    error(2);
}

function is_ascii($str) {
    return mb_check_encoding($str, 'ASCII');
}
