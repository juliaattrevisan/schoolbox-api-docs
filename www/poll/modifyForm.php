<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/poll.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/SitePage.php';

$page = new SitePage();
$pollHandler = new Poll();
$groupHandler = new Group(HOMEPAGE_SERVICE);

if (is_numeric($_POST['pollID']) && is_numeric($_POST['categoryID']) && isset($_POST['vote'])) {

	// check permission
	if ($groupHandler->getPermission($_POST['categoryID']) < READ_ACCESS) {
		error(19);
	}
	
	$pollHandler->placeVote($_POST['pollID'], $_POST['vote']);

	localRedir("/homepage/" . $_POST['categoryID']);
}
else if ($_POST['title'] && is_numeric($_POST['categoryID'])) {

	/* Check permission
	 */
	if ($groupHandler->getPermission($_POST['categoryID']) < WRITE_ACCESS) {
		error(19);
	}
	
	$poll = array(
		'categoryID'	=> $_POST['categoryID'],
		'title'			=> $_POST['title'],
		'question'		=> $_POST['question'],
		'option_0'		=> $_POST['option_0'],
		'option_1'		=> $_POST['option_1'],
		'option_2'		=> $_POST['option_2'],
		'option_3'		=> $_POST['option_3'],
		'option_4'		=> $_POST['option_4'],
		'option_5'		=> $_POST['option_5'],
		'option_6'		=> $_POST['option_6'],
		'option_7'		=> $_POST['option_7'],
		'option_8'		=> $_POST['option_8'],
		'option_9'		=> $_POST['option_9'],
		'frequency'		=> $_POST['frequency'],
		'close'			=> outputDate(Cfg::get('date_database'), ausDateToUnix($_POST['close'])),
	);

	if (is_numeric($_POST['pollID'])) {
		$poll['pollID'] = scrubTEXT($_POST['pollID']);

		$pollHandler->update($poll);
	}
	else {
		$poll['pollID'] = $pollHandler->create($poll);
	}

	/* Select this poll in the component
	 */
	require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/homepage.php';

	$homepageHandler = new Homepage($_POST['categoryID']);

	$config = serialize(array(
		'pollID' => $poll['pollID']
	));

	$homepageHandler->updateComponentConfig(scrubTEXT($_POST['homepageComponentID']), $config);

	localRedir("/homepage/" . scrubTEXT($_POST['categoryID']));
} 
else if (is_numeric($_GET['delete'])) {

	$poll = $pollHandler->getByID($_GET['delete']);
	// check permission
	if ($groupHandler->getPermission($poll['categoryID']) < WRITE_ACCESS)
		error(19);

	$pollHandler->delete($_GET['delete']);

	/* select next textBox */
	$current = $pollHandler->getCurrentPoll($poll['categoryID']);
	if ($current['pollID']) {
		require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/homepage.php';
		$homepageHandler = new Homepage($current['categoryID']);
		$config = serialize(array('pollID' => $current['pollID']));
		$homepageHandler->updateComponentConfig(33, $config);
	}
	localRedir("/homepage/" . $poll['categoryID']);
}


?>