<?php
/**
 * Bulk_User
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Bulk_User extends Bulk_Abstract {

	/**
	 * Parses and adds data from csv file. Returns a list of users and their
	 * add/update outcome, along with any relevant errors.
	 * CSV fields
		0. username
		1. title
		2. firstname
		3. surname
		4. synergetic
		5. group
		6. campus
		7. campus 2
		8. campus 3
		9. year
		10. password
	    11. local email
		12. mail handler
		13 alt. email
	 *
	 * @return array
	 */
	public function addUsers() {
		$userHandler = new User();

		/* List of outcomes for each user in the csv
		 */
		$output = array();

		if (is_file($this->getFile())) {

			/* Process rows
			 */
			$handle = fopen($this->getFile(), 'r');
			$headerRow = fgetcsv($handle, 1000, ",");

			while (($user = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $user = array_map('trim', $user);

				/* Only add someone if they have a username and a name
				 */
				if ($user[0] != "" && ($user[2] != "" || $user[3] != "")) {

					/* User groups (student, teacher, etc)
					 */
					$user[5] = is_numeric($user[5]) ? $user[5] : $userHandler->getGroupID($user[5]);

					if (!$user[5]) {
						$output[] = array(
							'username' => $user[0],
							'status' => 'Not Added',
							'error' => 'Unknown Group',
						);

						continue;
					}

					/* Get campus id and group
					 */
					$userCampus = array();

					if ($user[6]) {
						$campus = $userHandler->getCampusID($user[6]);

						if (is_numeric($campus)) {
							$userCampus[] = $campus;
						}
					}

					if ($user[7]) {
						$campus = $userHandler->getCampusID($user[7]);

						if (is_numeric($campus)) {
							$userCampus[] = $campus;
						}
					}

					if ($user[8]) {
						$campus = $userHandler->getCampusID($user[8]);

						if (is_numeric($campus)) {
							$userCampus[] = $campus;
						}
					}
					$userCampus = array_unique(array_filter($userCampus));

					/* New User object
					 */
					$newUser = array(
						'username' => $user[0],
						'title' => $user[1],
						'firstname' => $user[2],
						'lastname' => $user[3],
						'synergy_id' => $user[4],
						'group_id' => $user[5],
						'campus' => $userCampus,
						'year' => $user[9],
						'password' => $user[10],
						'local_email' => $user[11],
						'mail_handler' => $user[11],
						'alt_email' => $user[12],
					);

					if (empty($userCampus)) {
						$output[] = array(
							'username' => $newUser['username'] . ' (' . $newUser['firstname'] . ' ' . $newUser['lastname'] . ')',
							'status' => 'Not Added',
							'error' => 'No known campuses',
						);
						continue;
					}

					$result = $userHandler->create($newUser);

					/* Adding failed
					 * Check username and synergy already exists
					 */
					if (!$result) {
						$query = "SELECT id FROM user WHERE username = '".mysql_real_escape_string($newUser['username'])."' AND synergy_id = '{$newUser['synergy_id']}'";

						$updateUser = $userHandler->db->getRow($query);

						if (is_array($updateUser)) {
							$newUser['id'] = $updateUser['id'];

							$userHandler->update($newUser);

							$output[] = array(
								'username' => $newUser['username'],
								'status' => 'Updated user',
								'error' => 'OK',
							);
						}
						else {
							$output[] = array(
								'username' => $newUser['username'],
								'status' => 'Not Added',
								'error' => 'Collision',
							);
						}
					}
					else {
						$output[] = array(
							'username' => $newUser['username'],
							'status' => 'Added',
							'error' => 'OK',
						);
					}
				}
                else {
					$output[] = array(
						'username' => $newUser['username'] . ' (' . $newUser['firstname'] . ' ' . $newUser['lastname'] . ')',
						'status' => 'Not Added',
						'error' => 'Insufficient data',
					);
				}
			}
		}

		return $output;
	}

	/**
	 * Remove a list of usernames, or synergy ID's
	 *
	 * @return array
	 */
	public function removeUsers() {
		$userHandler = new User();

		/* List of outcomes for each user in the csv
		 */
		$output = array();

		if (is_file($this->getFile())) {

			/* Process rows
			 */
			$handle = fopen($this->getFile(), 'r');
			$headerRow = fgetcsv($handle, 1000, ",");

			while (($thisRow = fgetcsv($handle, 1000, ",")) !== FALSE) {
				if ($thisRow[0] != "") {

					/* If transfer info is included use that otherwise just use default
					 */
					if ($thisRow[1]) {
						$trans = $thisRow[1];
					}
					else {
						$trans = 1;
					}

					/* If synergy id delete as id
					 */
					if (is_numeric($thisRow[0])) {
						$user = $userHandler->getBySynergy($thisRow[0]);

                        if (is_array($user)) {
                            $userHandler->delete($user['uid'], $trans);
							$output[] = array(
								'username' => $user['username'].'('.$thisRow[0].')',
								'status' => 'Removed',
								'error' => 'OK',
							);
						}
						else {
							$output[] = array(
								'username' => $thisRow[0],
								'status' => 'Warning',
								'error' => 'Could not locate user via external ID',
							);
						}
					}
					else {
						/* Try username
						 */
						$user = $userHandler->getByUsername($thisRow[0]);

						if ($user) {
							$userHandler->delete($user['uid'], $trans);

							$output[] = array(
								'username' => $thisRow[0],
								'status' => 'Removed',
								'error' => 'OK',
							);
						}
						else {
							$output[] = array(
								'username' => $thisRow[0],
								'status' => 'Warning',
								'error' => "Could not locate user via username",
							);
						}
					}
				}
			}
		}

		return $output;
	}
}
