<?php
require_once(dirname(__FILE__)  .'/../include/config.php');
require_once(dirname(__FILE__)  .'/../library/user.php');
require_once(dirname(__FILE__)  .'/../library/synergetic/ExternalUser.php');

define('PERFORM_UPDATE', true);

$userHandler = new User();
$synergyIds = array();

$allParents = $userHandler->getByGroup(unserialize(PARENTGROUPIDS), true);

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

			if (($user['title'] != $currentParent['Title']) || ($user['firstname'] != $currentParent['Preferred']) || ($user['lastname'] != $currentParent['Surname']) || ($user['alt_email'] != $preferredEmail)) {
				$updatedUser = $userHandler->getById($user['uid']);

				$updatedUser['id'] = $user['uid'];
				$updatedUser['title'] = trim($currentParent['Title']);
				$updatedUser['firstname'] = trim($currentParent['Preferred']);
				$updatedUser['lastname'] = trim($currentParent['Surname']);

				$updatedUser['campus'] = $userHandler->getUserCampus($user['uid']);
				$updatedUser['defaultCampus'] = array();

				/* Clear email address in Portal if the user is not meant to be
				 * publicly listed
				 */
				if ($currentParent['DirectoryIncludeFlag'] == 0) {
					$updatedUser['alt_email'] = '';

					$emailString = ', clearing email address';
				}
				else {
					$updatedUser['alt_email'] = trim($preferredEmail);

					$emailString = ', updating email address';
				}

				unset($updatedUser['gname']);
				unset($updatedUser['uid']);

				if (PERFORM_UPDATE) {
					$userHandler->update($updatedUser);

					$action = 'Updating';
				}
				else {
					$action = 'Planning update';
				}

				echo "$action on #$user[uid] (Synergetic #$currentSynergyId) $user[title] $user[firstname] $user[lastname] ($user[alt_email]) to $updatedUser[title] $updatedUser[firstname] $updatedUser[lastname] ($updatedUser[alt_email])$emailString\n";
			}
			else {
				$action = 'Ignoring';

				echo "$action #$user[uid] (Synergetic #$currentSynergyId) $user[title] $user[firstname] $user[lastname] ($user[alt_email])\n";
			}
		}
	}
}
?>