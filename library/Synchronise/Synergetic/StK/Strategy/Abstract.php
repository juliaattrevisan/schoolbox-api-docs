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
	 * Figure out which campus a staff member is in, based on the code provided
	 *
	 * * 1: Senior
	 * * 2: Junior
	 * * 3: Pre-school
	 *
	 * @param  $code
	 * @return int
	 */
	protected function getCampusFromCode($code)
	{
		switch ($code) {
			case 'S':
				return 1;
			case 'J':
				return 2;
			case 'P':
				return 3;
			default:
				return false;
		}
	}


	/**
	 * Gets house ID out of Synergetic house.
	 *
	 * @todo Can houses and campuses have synergetic codes associated with them?
	 *
	 * @param $house
	 *
	 * @return bool|int
	 */
	protected function getHouseFromCode($house) {
		//
		// PYE: Pye (383)
		// TRI: Tripp (384)
		// MAY: Mayfield (385)
		// HAM: Hamilton (386)
		// CER: Cerutty (387)
		// DOU: Douglas (388)
		// RED, BLU, GOL: ?
		switch ($house) {
			case 'PYE':
				return 383;
			case 'TRI':
				return 384;
			case 'MAY':
				return 385;
			case 'HAM':
				return 386;
			case 'CER':
				return 387;
			case 'DOU':
				return 388;
			default:
				return false;
		}
	}


	/**
	 * Figure out which Campus belongs to this Student
	 *
	 * @param  $year
	 * @return bool|string
	 */
	protected function getCampusFromYearLevel($year)
	{
		switch ($year) {
			case '0';
			case '1';
			case '2':
			case '3':
			case '4':
			case '5':
			case '6':
				return 2;
			case '7':
			case '8':
			case '9':
			case '10':
			case '11':
			case '12':
				return 3;
			case '32':
			case '33':
			case '44':
				return 1;
			default:
				return false;
		}
	}

	/**
	 * Figure out the Group ID based on a Student's year level
	 *
	 * @param  $year
	 * @return bool|int
	 */
	protected function getGroupIDFromYearLevel($year)
	{
		switch ($year) {
			//
			// Year x
			case 0:
				return 18;
			case 1:
				return 17;
			case 2:
				return 16;
			case 3:
				return 15;
			case 4:
				return 14;
			case 5:
				return 13;
			case 6:
				return 12;
			case 7:
				return 11;
			case 8:
				return 10;
			case 9:
				return 9;
			case 10:
				return 8;
			case 11:
				return 7;
			case 12:
				return 6;
			//
			// Pre-schools
			case 32:
			case 33:
			case 44:
				return 19;
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