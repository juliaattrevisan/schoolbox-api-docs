<?php
require_once(dirname(__FILE__)  .'/../include/config.php');
require_once(dirname(__FILE__)  .'/../library/user.php');
require_once(dirname(__FILE__)  .'/../library/synergetic/ExternalUser.php');

define('PARENT_GROUP_ID' , 4);

$userHandler = new User();
$synergyIds = array();

$allParents = $userHandler->getByGroup(PARENT_GROUP_ID, true);

/* Get a list of Parents' synergy ids to sync
 */
if (is_array($allParents) && count($allParents)) {
	foreach($allParents as $currentParent) {
		$synergyIds[] = $currentParent['synergy_id'];
	}
}

if (count($synergyIds)) {
	$externalUserHandler = new ExternalUser();
	$externalUserHandler->getCurrentSemester();

	foreach($synergyIds as $currentSynergyId) {
		$currentParent = $externalUserHandler->getParentSync($currentSynergyId);

		/* If the parent could be found, try updating him in Synergetic
		 */
		if ($currentParent) {

			/* The drop down in synergetic's Phone and Occupation tabs indicates
			 * which email the person prefers to use.
			 */
			if ($currentParent['DefaultEmailCode'] == 'O') {
				$preferredEmail = $currentParent['OccupEmail'];
			}
			else {
				$preferredEmail = $currentParent['Email'];
			}

			/* Update Portal with Synergetic data if there is something
			 * different contained in Synergetic db
			 */
			$user = $userHandler->getBySynergy($currentSynergyId);

			if (($user['firstname'] != $currentParent['Preferred']) || ($user['lastname'] != $currentParent['Surname']) || ($user['alt_email'] != $preferredEmail)) {
				$updatedUser = $userHandler->getById($user['uid']);

				$updatedUser['id'] = $user['uid'];
				$updatedUser['firstname'] = $currentParent['Preferred'];
				$updatedUser['lastname'] = $currentParent['Surname'];
				$updatedUser['alt_email'] = $preferredEmail;
				$updatedUser['campus'] = $userHandler->getUserCampus($user['uid']);

				unset($updatedUser['gname']);
				unset($updatedUser['uid']);
				
				$userHandler->update($updatedUser);
			}
		}
	}
}
?>