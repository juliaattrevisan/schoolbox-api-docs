<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
$authenticator = new \EOTC\Authenticator();

if ($authenticator->isEOTCCreator($_SESSION['user'])) {
	$eventMapper = new \EOTC\EventMapper();

	try {
		$event = $eventMapper->findById($_POST['eventId']);
	}
	catch (\Exception $e) {
		error(160);
	}

	/* If people were chosen, process the random input
	 */
	if (isset($_POST['selected']) && strlen($_POST['selected'])) {

		/* Convert commas to whitespace
		 */
		$items = preg_replace('/,/', ' ', $_POST['selected']);

		/* Condense multiple whitespaces
		 */
		$items = preg_replace('/\s+/', ' ', $items);

		/* Snap them out into individuals
		 */
		$allComponents = explode(' ', $items);

		/* Spawn mapper
		 */
		$userMapper = new \Schoolbox\UserMapper();

		foreach($allComponents as $currentComponent) {

			/* Numerics are assumed as schoolbox user ids and can go into
			 * the stack straightaway
			 */
			if (is_numeric($currentComponent)) {
				$allIds[] = $currentComponent;
			}
			/* Otherwise they are assumed to be usernames
			 */
			else {
				try {
					$user = $userMapper->findOneByColumn('username', $currentComponent);
					$allIds[] = $user->getId();
				}
				catch (\Exception $e) {
				}
			}
		}

		$eventMapper->addStudentAttendeesRelationsSpecific($event, $allIds);
	}

	$this->localRedir('/modules/eotc/manage/attendance/list/' . $event->getId());
}
else {
	error(19);
}