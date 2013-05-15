<?php
/**
 * Synchronise_Synergetic_StK_Strategy_Abstract
 *
 * StK-specific methods that are provided for each synchronisation Strategy.
 *
 * You can create custom subclasses of Synchronise_Synergetic_Strategy_Abstract
 * to provide unique methods to your synchronisation strategies.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 * @shamelessly_pilfered_from <steve.chan@alaress.com.au>, <matt.day@alaress.com.au>
 */
abstract class Synchronise_Synergetic_StK_Strategy_Abstract extends Synchronise_Synergetic_Strategy_Abstract
{
	protected $verboseFilter = null;
	const PARENT_GROUP_ID = 4;
	const STAFF_GROUP_ID  = 2;
	const ADMIN_GROUP_ID  = 1;

	const STAFF_DEFAULT_CAMPUS = 1;

	/**
	 * Setup
	 */
	protected function setup()
	{
		$this->setVerboseFilter(new Synchronise_Synergetic_StK_Verbose());
	}

	/**
	 * Identical algorithm used to create/update a user
	 *
	 * @param  $newUser
	 * @return void
	 */
	protected function executeCreateUpdate($newUser)
	{
		$this->getVerboseFilter()->setNewUser($newUser);

		//
		// Does this person have a username? If not, skip them
		if ($this->hasUsername($newUser)) {
			$existing = $this->userExistsBySynergyAndGroup($newUser);

			//
			// If the current user exists in Schoolbox, update them
			if ($existing) {
				if ($this->getPerformUpdate() === true) {
					$newUser['id'] = $existing['uid'];
					$this->getUserHandler()->update($newUser);
					$this->getVerboseFilter()->performUpdate();
				}
				else {
					$this->getVerboseFilter()->planUpdate();
				}
				$this->getVerboseFilter()->incrementUpdated();
			}
			else {
				//
				// Perform action; report what happened
				if ($this->getPerformUpdate() === true) {
					$this->getUserHandler()->create($newUser);
					$this->getVerboseFilter()->performCreation();
				}
				else {
					$this->getVerboseFilter()->planCreation();
				}
				$this->getVerboseFilter()->incrementCreated();
			}
		}
		else {
			$this->getVerboseFilter()->skip();
			$this->getVerboseFilter()->incrementSkipped();
		}
	}

	/**
	 * Figure out which Campus belongs to this Student
	 *
	 * For St. Kentigern this is always based on the user's campus:
	 * * 1: Co-ed
	 * * 2: Girls
	 * * 3: Boys
	 * * 4: Pre-school
	 *
	 * @param  $user
	 * @return bool|string
	 */
	protected function getCampusFromCurrentUser($user)
	{
		switch ($user['StudentCampus']) {
			case 'C':
				return 1;
			case 'G':
				return 2;
			case 'B':
				return 3;
			case 'P':
				return 4;
			default:
				return false;
		}
	}

	/**
	 * Figure out the Group ID for a user - year level is based on a user's
	 * campus and year level.
	 *
	 * @param  $user
	 * @return bool|int
	 */
	protected function getGroupIDFromCurrentUser($user)
	{
		switch ($user['StudentCampus']) {
			case 'P':
				//
				// Pre-school: group 7
				return 7;
			case 'G':
				//
				// Girls: year+8 up to year 8
				// (StK were nice enough to put their groups in order)
				return $user['StudentYearLevel']+8;
			case 'B':
				//
				// Boys: year+17 up to year 8
				return $user['StudentYearLevel']+17;
			case 'C':
				//
				// Co-ed: year+19 (year 7 only up, for 7 -> 26 to 13 -> 32)
				return $user['StudentYearLevel']+19;
			default:
				return false;
		}
	}

	/**
	 * Determine if this person has a username
	 *
	 * @param  $currentUser
	 * @return bool
	 */
	protected function hasUsername($currentUser)
	{
		if (strlen(trim($currentUser['username']))) {
			return true;
		}

		return false;
	}

	/**
	 * Determine if user exists in Schoolbox
	 *
	 * @param $newUser
	 * @return mixed
	 */
	protected function userExists($newUser)
	{
		return $this->getUserHandler()->getBySynergy($newUser['synergy_id']);
	}

	/**
	 * Determine if a user exists in Schoolbox in a particular group
	 *
	 * @param $newUser
	 * @return mixed
	 */
	protected function userExistsBySynergyAndGroup($newUser)
	{
		$query = "
						SELECT
								user.id as uid, synergy_id,
								title, firstname, lastname, username,
								local_email, alt_email,
								userCampus.campus, year, house,
								eportfolio_enabled,
								groups.name AS gname, group_id
						FROM user
						LEFT JOIN groups ON user.group_id = groups.id
						LEFT JOIN userCampus ON user.id = userCampus.uid
						WHERE user.synergy_id = '{$newUser['synergy_id']}'";

		//Only lock user to group if parent account
		if ($newUser['group_id'] == self::PARENT_GROUP_ID)
			$query .= " AND groups.id = '{$newUser['group_id']}'";


		$query .= " ORDER BY userCampus.defaultCampus DESC";

		return $this->getUserHandler()->db->getRow($query);
	}
}