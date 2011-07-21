<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/SitePage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/wiki.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';

$page = new SitePage;
$wikiHandler = new Wiki;
$groupHandler = new Group(HOMEPAGE_SERVICE);

if (isset($_POST['heading']) && is_numeric($_POST['categoryID'])) {


	$data = array(	'heading'		=> $_POST['heading'],
					'body'			=> $_POST['body'],
				);
				
	if (is_numeric($_POST['wikiID'])) {
	
		// wiki exists
		$wiki = $wikiHandler->getByID($_POST['wikiID']);
		if (!isset($wiki['wikiID']))
			error(2);

		if (($permission = $groupHandler->getPermission($wiki['categoryID'])) < READ_ACCESS)
			error(19);

		// check locked
		if ($wiki['locked'] == 'true' && $permission < WRITE_ACCESS)
			error(19);		
	
		$data['wikiID'] = $wiki['wikiID'];

        /* Check if there has been a revision added for this wiki since you
         * last received a lastRevision id.
         */
        $latestRevision = $wikiHandler->getLatestRevision($wiki['wikiID']);

        /* Save your version regardless
         */
        $wikiHandler->update($data);

        /* If there was a version between the one you were editing and the one
         * you just submitted, compare them
         */
        if ($_POST['revision'] < $latestRevision['revisionID']) {
            localRedir("/wiki/merge/$wiki[wikiID]");
        }
	}
    else if($_POST['categoryID']) {
		// check permission
		if (($permission = $groupHandler->getPermission($_POST['categoryID'])) < READ_ACCESS)
			error(19);
		$data['categoryID'] = $_POST['categoryID'];
		$wiki['wikiID'] = $wikiHandler->create($data);
	}

	localRedir("/wiki/" . $wiki['wikiID']);
}
elseif (is_numeric($_GET['revert']) && is_numeric($_GET['wikiID'])) {

	$wiki = $wikiHandler->getByID($_GET['wikiID']);

	// check permission
	if (($permission = $groupHandler->getPermission($wiki['categoryID'])) < WRITE_ACCESS)
		error(19);	
		
	$wikiHandler->revert($wiki['wikiID'], $_GET['revert']);
	
	localRedir('/wiki/' . $wiki['wikiID']);
}
elseif (is_numeric($_GET['delete'])) {
	$wiki = $wikiHandler->getByID($_GET['delete']);

	// check permission
	if (($permission = $groupHandler->getPermission($wiki['categoryID'])) < WRITE_ACCESS)
		error(19);	
		
	$wikiHandler->delete($wiki['wikiID']);
	
	localRedir('/homepage/' . $wiki['categoryID']);

}
elseif (is_numeric($_GET['toggleLock'])) {

	// wiki exists
	$wiki = $wikiHandler->getByID($_GET['toggleLock']);
	if (!isset($wiki['wikiID']))
		error(2);

	/* check permissions */
	if ($groupHandler->getPermission($wiki['categoryID']) < WRITE_ACCESS)
		error(19);	

	$data = array('wikiID'	=> $wiki['wikiID'],
				  'locked'	=> $wiki['locked'] == 'true' ? 'false' : 'true',
				  );
	$wikiHandler->update($data);		
	
	$page->backToTheFuture(1);

} else
	error(2);

?>