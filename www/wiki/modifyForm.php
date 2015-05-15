<?php
require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

$page         = new SitePage();
$wikiHandler  = new Wiki();
$groupHandler = new Group(HOMEPAGE_SERVICE);

$wikiMapper     = \Schoolbox\Wiki\WikiMapper::getInstance();
$revisionMapper = \Schoolbox\Wiki\RevisionMapper::getInstance();

$now = new Alaress_Date_Format(time(), 'U');

// posting a revision - wiki may or may not exist
if (isset($_POST['heading']) && is_numeric($_POST['categoryID'])) {
    $heading = trim($_POST['heading']);
    if (strlen($heading) === 0) {
        error(2);
        exit;
    }
    // generate the revision object regardless of whether or not the wiki exists
    $revision = new \Schoolbox\Wiki\Revision();
    $revision
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
                    'categoryID' => $_POST['categoryID'],
                    'slug'       => $_POST['slug']
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
    } else {
        // request to create new wiki
        $cat = new \Schoolbox\Resource\Category();
        $cat->setId($_POST['categoryID']);
        $url  = new Url($heading);
        $wiki = new \Schoolbox\Wiki\Wiki();
        $wiki
            ->setCategory($cat)
            ->setLatestRevision(new \Schoolbox\Wiki\Revision())
            ->setCreated($now)
            // Never set slug from form
            ->setSlug($url->getSlug())
            ->setLocked(false)
            ->setDeleted(false);

        // Only check component's create permissions for a new wiki.
        /* First check that Allow Create is active. If it is, continue;
         * otherwise proceed to the usual getPermission()
         */
        $value = \Component_ConfigMapper::getInstance()->findValue('allowCreate', (int)$_POST['instanceId']);

        /* Allow Create is off
         */
        if (((int)$value === 0) && ($groupHandler->getPermission($wiki->getCategory()->getId()) < WRITE_ACCESS)) {
            error(19);
        }
    }

    // check category permission
    if (($permission = $groupHandler->getPermission($wiki->getCategory()->getId())) < READ_ACCESS) {
        error(19);
    }

    // check locked-ness
    if ($wiki->getLocked() && $permission < WRITE_ACCESS) {
        error(19);
    }

    /* at this juncture, $revision has no ID; $wiki *may* have an ID */

    // persist our revision, and update with the resultant ID
    $revisionIds = $revisionMapper->save($revision);
    $revision->setId($revisionIds[0]);

    // is there a previous revision that's snuck in and caused merge conflicts?
    if ($_POST['revision'] < $wiki->getLatestRevision()->getId()) {
        SitePage::localRedir("/wiki/merge/" . $wiki->getId());
        exit;
    }

    // no merge conflict? ok, persist the wiki entity with the latest revision
    $wiki->setLatestRevision($revision);
    $wikiIds = $wikiMapper->save($wiki);

    // update the revision with this wiki ID (if wiki previously existed, this won't change anything)
    $revision->setWiki($wiki->setId($wikiIds[0]));
    $revisionMapper->save($revision);

    SitePage::localRedir('/wiki/' . $wiki->getCategory()->getId() . '/' . $wiki->getSlug());
} elseif (is_numeric($_GET['revert']) && is_numeric($_GET['wikiID'])) {
    // applying a reversion
    $wiki = $wikiHandler->getByID($_GET['wikiID']);

    // Check permission
    if (($permission = $groupHandler->getPermission($wiki['categoryID'])) < WRITE_ACCESS) {
        error(19);
    }

    $wikiHandler->revert($wiki['wikiID'], $_GET['revert']);

    SitePage::localRedir('/wiki/' . $wiki['categoryID'] . '/' . $wiki['slug']);
} elseif (is_numeric($_GET['delete'])) {
    // deleting a wiki
    $wiki = $wikiHandler->getByID($_GET['delete']);

    // Check permission
    if (($permission = $groupHandler->getPermission($wiki['categoryID'])) < WRITE_ACCESS) {
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
    if ($groupHandler->getPermission($wiki['categoryID']) < WRITE_ACCESS) {
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
