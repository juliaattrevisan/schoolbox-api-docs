<?php
/*
CSV fields

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
	11. webmail
	12 alt. email
*/
require_once dirname(__FILE__) . '/../library/user.php';

class BulkAdd {

	/**
	 * Parses and adds data from csv file. Returns a list of users and their
	 * add/update outcome, along with any relevant errors.
	 *
	 * @param <type> $file
	 */
	public function parseCSV($file) {
		$userHandler = new User();

		if (is_file($file)) {
			
			/* List of outcomes for each user in the csv
			 */
			$output = array();

			/* Open and parse csv
			 */
			$handle = fopen($file, "r");

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
						'alt_email' => $user[12],
					);

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
								'status' => 'Updated',
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
}

?>